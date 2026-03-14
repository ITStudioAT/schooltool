<?php

namespace App\Services;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Throwable;

use function Laravel\Ai\agent;

class AbaOpenAiNormalizationService
{
    /**
     * @param  array<string,mixed>  $canonical
     * @return array<string,mixed>
     */
    public function normalize(array $canonical): array
    {
        $fallback = $this->normalizeDeterministically($canonical);

        if (! $this->openAiNormalizationEnabled()) {
            $fallback['normalization_source'] = 'local_deterministic';

            return $fallback;
        }

        try {
            $response = agent(
                instructions: $this->instructions(),
                schema: fn (JsonSchema $schema): array => $this->schema($schema),
            )->prompt(
                $this->buildPrompt($canonical),
                provider: 'openai',
                model: $this->configuredModel(),
            );

            $normalized = method_exists($response, 'toArray')
                ? $response->toArray()
                : (is_array($response) ? $response : []);

            $normalized = $this->sanitizeNormalized($normalized, $canonical);
            $normalized['normalization_source'] = 'openai';

            return $normalized;
        } catch (Throwable $exception) {
            Log::warning('aba.analysis.normalization.openai_failed', [
                'error' => $exception->getMessage(),
            ]);

            $fallback['normalization_source'] = 'local_fallback_after_openai_error';
            $fallback['warnings'][] = 'openai_normalization_failed';

            return $fallback;
        }
    }

    private function openAiNormalizationEnabled(): bool
    {
        return (bool) config('aba_analysis.openai_normalization_enabled', false) === true
            && trim((string) config('ai.providers.openai.key', '')) !== '';
    }

    private function configuredModel(): ?string
    {
        $model = trim((string) config('aba_analysis.openai_model', ''));

        return $model !== '' ? $model : null;
    }

    private function instructions(): string
    {
        return implode("\n", [
            'Du normalisierst nur auf Basis des bereitgestellten canonical_json.',
            'Erfinde keine Abschnitte.',
            'Jeder Abschnitt muss source_block_ids enthalten, die im canonical_json existieren.',
            'Nutze nur die erlaubten section_type-Werte.',
            'Bewahre die Reihenfolge konsistent mit der lokalen Evidenz.',
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    private function schema(JsonSchema $schema): array
    {
        $allowedTypes = $this->allowedSectionTypes();

        return [
            'document_type' => $schema->string()->enum(['aba', 'other'])->required(),
            'sections' => $schema->array()->items(
                $schema->object([
                    'section_type' => $schema->string()->enum($allowedTypes)->required(),
                    'title' => $schema->string()->nullable(),
                    'order' => $schema->integer()->min(1)->required(),
                    'parent_order' => $schema->integer()->min(1)->nullable(),
                    'text' => $schema->string()->required(),
                    'confidence' => $schema->number()->min(0)->max(1)->required(),
                    'warnings' => $schema->array()->items($schema->string()),
                    'missing_fields' => $schema->array()->items($schema->string()),
                    'source_block_ids' => $schema->array()->items($schema->string())->required(),
                ])->withoutAdditionalProperties()
            )->required(),
            'confidence' => $schema->number()->min(0)->max(1)->required(),
            'warnings' => $schema->array()->items($schema->string()),
            'missing_fields' => $schema->array()->items($schema->string()),
        ];
    }

    /**
     * @param  array<string,mixed>  $canonical
     */
    private function buildPrompt(array $canonical): string
    {
        $payload = [
            'document_type' => $canonical['document_type'] ?? 'aba',
            'selected_candidate' => $canonical['selected_candidate'] ?? null,
            'frontmatter' => $canonical['frontmatter'] ?? [],
            'toc_ranges' => $canonical['toc_ranges'] ?? [],
            'body_start_line' => $canonical['body_start_line'] ?? null,
            'section_candidates' => $canonical['section_candidates'] ?? [],
            'warnings' => $canonical['warnings'] ?? [],
            'metrics' => $canonical['metrics'] ?? [],
        ];

        return "Normalisiere die folgende lokale Dokument-Evidenz in das geforderte Zielschema:\n\n"
            .json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<string,mixed>  $canonical
     * @return array<string,mixed>
     */
    private function normalizeDeterministically(array $canonical): array
    {
        $sections = is_array($canonical['section_candidates'] ?? null)
            ? array_values($canonical['section_candidates'])
            : [];

        usort($sections, fn (array $left, array $right): int => ((int) ($left['order'] ?? 0) <=> (int) ($right['order'] ?? 0)));

        $normalizedSections = [];
        $orderToCandidateKey = [];
        foreach ($sections as $index => $section) {
            $order = $index + 1;
            $candidateKey = (string) ($section['candidate_key'] ?? '');
            if ($candidateKey !== '') {
                $orderToCandidateKey[$order] = $candidateKey;
            }
        }

        $candidateKeyToOrder = array_flip($orderToCandidateKey);

        foreach ($sections as $index => $section) {
            $order = $index + 1;
            $parentOrder = null;
            $parentKey = trim((string) ($section['parent_key'] ?? ''));
            if ($parentKey !== '' && isset($candidateKeyToOrder[$parentKey])) {
                $parentOrder = (int) $candidateKeyToOrder[$parentKey];
            }

            $qualityScore = is_numeric($section['quality_score'] ?? null)
                ? (float) $section['quality_score']
                : 0.55;
            $confidence = $qualityScore > 1 ? min(1.0, $qualityScore / 100.0) : min(1.0, max(0.0, $qualityScore));
            if ($confidence <= 0.0) {
                $confidence = 0.55;
            }

            $normalizedSections[] = [
                'section_type' => $this->sanitizeSectionType((string) ($section['section_type'] ?? 'other_section')),
                'title' => $this->normalizeNullableString($section['title'] ?? null),
                'order' => $order,
                'parent_order' => $parentOrder,
                'text' => trim((string) ($section['text'] ?? '')),
                'confidence' => round($confidence, 4),
                'warnings' => [],
                'missing_fields' => [],
                'source_block_ids' => is_array($section['source_block_ids'] ?? null) ? array_values(array_unique(array_map('strval', $section['source_block_ids']))) : [],
            ];
        }

        $overallConfidence = count($normalizedSections) > 0
            ? round(array_sum(array_map(fn (array $section): float => (float) ($section['confidence'] ?? 0.0), $normalizedSections)) / count($normalizedSections), 4)
            : 0.0;

        return [
            'document_type' => in_array((string) ($canonical['document_type'] ?? 'aba'), ['aba', 'other'], true)
                ? (string) ($canonical['document_type'] ?? 'aba')
                : 'aba',
            'sections' => $normalizedSections,
            'confidence' => $overallConfidence,
            'warnings' => is_array($canonical['warnings'] ?? null) ? array_values($canonical['warnings']) : [],
            'missing_fields' => [],
        ];
    }

    /**
     * @param  array<string,mixed>  $normalized
     * @param  array<string,mixed>  $canonical
     * @return array<string,mixed>
     */
    private function sanitizeNormalized(array $normalized, array $canonical): array
    {
        $sectionCandidates = is_array($canonical['section_candidates'] ?? null)
            ? array_values($canonical['section_candidates'])
            : [];

        $fallbackByOrder = [];
        foreach ($sectionCandidates as $index => $candidate) {
            $fallbackByOrder[$index + 1] = $candidate;
        }

        $sections = is_array($normalized['sections'] ?? null) ? array_values($normalized['sections']) : [];
        $sanitizedSections = [];
        foreach ($sections as $index => $section) {
            if (! is_array($section)) {
                continue;
            }

            $order = (int) ($section['order'] ?? ($index + 1));
            if ($order <= 0) {
                $order = $index + 1;
            }

            $fallback = $fallbackByOrder[$order] ?? null;
            $sourceBlockIds = is_array($section['source_block_ids'] ?? null)
                ? array_values(array_unique(array_map('strval', $section['source_block_ids'])))
                : [];
            if ($sourceBlockIds === [] && is_array($fallback['source_block_ids'] ?? null)) {
                $sourceBlockIds = array_values(array_unique(array_map('strval', $fallback['source_block_ids'])));
            }

            $sanitizedSections[] = [
                'section_type' => $this->sanitizeSectionType((string) ($section['section_type'] ?? ($fallback['section_type'] ?? 'other_section'))),
                'title' => $this->normalizeNullableString($section['title'] ?? ($fallback['title'] ?? null)),
                'order' => $order,
                'parent_order' => $this->normalizePositiveInt($section['parent_order'] ?? null),
                'text' => trim((string) ($section['text'] ?? ($fallback['text'] ?? ''))),
                'confidence' => $this->sanitizeConfidence($section['confidence'] ?? null, $fallback),
                'warnings' => is_array($section['warnings'] ?? null) ? array_values(array_map('strval', $section['warnings'])) : [],
                'missing_fields' => is_array($section['missing_fields'] ?? null) ? array_values(array_map('strval', $section['missing_fields'])) : [],
                'source_block_ids' => $sourceBlockIds,
            ];
        }

        usort($sanitizedSections, fn (array $left, array $right): int => ((int) $left['order'] <=> (int) $right['order']));

        $documentType = in_array((string) ($normalized['document_type'] ?? 'aba'), ['aba', 'other'], true)
            ? (string) ($normalized['document_type'] ?? 'aba')
            : 'aba';

        return [
            'document_type' => $documentType,
            'sections' => $sanitizedSections,
            'confidence' => $this->sanitizeOverallConfidence($normalized['confidence'] ?? null, $sanitizedSections),
            'warnings' => is_array($normalized['warnings'] ?? null) ? array_values(array_map('strval', $normalized['warnings'])) : [],
            'missing_fields' => is_array($normalized['missing_fields'] ?? null) ? array_values(array_map('strval', $normalized['missing_fields'])) : [],
        ];
    }

    /**
     * @param  array<string,mixed>|null  $fallback
     */
    private function sanitizeConfidence(mixed $value, ?array $fallback): float
    {
        if (is_numeric($value)) {
            $confidence = (float) $value;
            if ($confidence >= 0 && $confidence <= 1) {
                return round($confidence, 4);
            }
        }

        if (is_array($fallback) && is_numeric($fallback['quality_score'] ?? null)) {
            $score = (float) $fallback['quality_score'];
            if ($score > 1) {
                $score = min(1.0, $score / 100.0);
            }

            return round(max(0.0, min(1.0, $score)), 4);
        }

        return 0.55;
    }

    /**
     * @param  array<int, array<string,mixed>>  $sections
     */
    private function sanitizeOverallConfidence(mixed $value, array $sections): float
    {
        if (is_numeric($value)) {
            $confidence = (float) $value;
            if ($confidence >= 0 && $confidence <= 1) {
                return round($confidence, 4);
            }
        }

        if ($sections === []) {
            return 0.0;
        }

        $sum = array_sum(array_map(fn (array $section): float => (float) ($section['confidence'] ?? 0.0), $sections));

        return round($sum / count($sections), 4);
    }

    /**
     * @return array<int, string>
     */
    private function allowedSectionTypes(): array
    {
        return [
            'title_page',
            'abstract',
            'foreword',
            'table_of_contents',
            'chapter',
            'subchapter',
            'bibliography',
            'figure_index',
            'consent_declaration',
            'other_section',
            'figure',
            'table',
        ];
    }

    private function sanitizeSectionType(string $type): string
    {
        $normalized = trim($type);
        if (! in_array($normalized, $this->allowedSectionTypes(), true)) {
            return 'other_section';
        }

        return $normalized;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $integer = (int) $value;

        return $integer > 0 ? $integer : null;
    }
}
