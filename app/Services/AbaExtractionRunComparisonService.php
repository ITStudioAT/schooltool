<?php

namespace App\Services;

use App\Models\AbaAnalysisRun;

class AbaExtractionRunComparisonService
{
    /**
     * @return array<string,mixed>
     */
    public function compare(?AbaAnalysisRun $conventionalRun, ?AbaAnalysisRun $parselRun): array
    {
        $conventionalSummary = $this->summary($conventionalRun);
        $parselSummary = $this->summary($parselRun);

        return [
            'ready' => $this->isCompleted($conventionalRun) && $this->isCompleted($parselRun),
            'conventional' => $this->runSnapshot($conventionalRun, $conventionalSummary),
            'parsel' => $this->runSnapshot($parselRun, $parselSummary),
            'delta' => [
                'text_length' => $this->numericDelta($conventionalRun?->text_length, $parselRun?->text_length),
                'text_length_without_spaces' => $this->numericDelta($conventionalRun?->text_length_without_spaces, $parselRun?->text_length_without_spaces),
                'required_found' => count($this->keys($parselSummary, 'found_required_section_keys')) - count($this->keys($conventionalSummary, 'found_required_section_keys')),
                'required_missing' => count($this->keys($parselSummary, 'missing_required_section_keys')) - count($this->keys($conventionalSummary, 'missing_required_section_keys')),
                'optional_found' => count($this->keys($parselSummary, 'found_optional_section_keys')) - count($this->keys($conventionalSummary, 'found_optional_section_keys')),
            ],
            'sections' => $this->sectionRows($conventionalSummary, $parselSummary),
            'warnings' => $this->warnings($conventionalRun, $parselRun),
        ];
    }

    /**
     * @param  array<string,mixed>  $summary
     * @return array<string,mixed>
     */
    private function runSnapshot(?AbaAnalysisRun $run, array $summary): array
    {
        return [
            'id' => $run?->id,
            'status' => $run?->status,
            'completed_at' => $run?->completed_at,
            'source_original_name' => $run?->source_original_name,
            'text_length' => $run?->text_length,
            'text_length_without_spaces' => $run?->text_length_without_spaces,
            'found_required_count' => count($this->keys($summary, 'found_required_section_keys')),
            'missing_required_count' => count($this->keys($summary, 'missing_required_section_keys')),
            'found_optional_count' => count($this->keys($summary, 'found_optional_section_keys')),
            'uncertain_count' => count($this->keys($summary, 'uncertain_matches')),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function summary(?AbaAnalysisRun $run): array
    {
        return is_array($run?->summary ?? null) ? $run->summary : [];
    }

    private function isCompleted(?AbaAnalysisRun $run): bool
    {
        return $run?->status === AbaAnalysisRun::STATUS_COMPLETED;
    }

    private function numericDelta(?int $conventionalValue, ?int $parselValue): ?int
    {
        if ($conventionalValue === null || $parselValue === null) {
            return null;
        }

        return $parselValue - $conventionalValue;
    }

    /**
     * @param  array<string,mixed>  $summary
     * @return array<int, string>
     */
    private function keys(array $summary, string $key): array
    {
        return is_array($summary[$key] ?? null) ? array_values(array_map('strval', $summary[$key])) : [];
    }

    /**
     * @param  array<string,mixed>  $conventionalSummary
     * @param  array<string,mixed>  $parselSummary
     * @return array<int, array<string,mixed>>
     */
    private function sectionRows(array $conventionalSummary, array $parselSummary): array
    {
        $conventionalSections = $this->sectionsByKey($conventionalSummary);
        $parselSections = $this->sectionsByKey($parselSummary);
        $keys = array_values(array_unique(array_merge(array_keys($conventionalSections), array_keys($parselSections))));
        sort($keys);

        return array_values(array_map(
            fn (string $key): array => $this->sectionRow($key, $conventionalSections[$key] ?? null, $parselSections[$key] ?? null),
            $keys,
        ));
    }

    /**
     * @param  array<string,mixed>  $summary
     * @return array<string, array<string,mixed>>
     */
    private function sectionsByKey(array $summary): array
    {
        $sections = is_array($summary['sections'] ?? null) ? array_values($summary['sections']) : [];
        $byKey = [];

        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }

            $key = trim((string) ($section['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $byKey[$key] = $section;
        }

        return $byKey;
    }

    /**
     * @param  array<string,mixed>|null  $conventionalSection
     * @param  array<string,mixed>|null  $parselSection
     * @return array<string,mixed>
     */
    private function sectionRow(string $key, ?array $conventionalSection, ?array $parselSection): array
    {
        $conventionalFound = (bool) ($conventionalSection['found'] ?? false);
        $parselFound = (bool) ($parselSection['found'] ?? false);

        return [
            'key' => $key,
            'label' => (string) ($conventionalSection['label'] ?? $parselSection['label'] ?? $key),
            'required' => (bool) ($conventionalSection['required'] ?? $parselSection['required'] ?? false),
            'conventional_found' => $conventionalFound,
            'parsel_found' => $parselFound,
            'status' => $this->sectionStatus($conventionalFound, $parselFound),
            'conventional_preview' => $this->preview($conventionalSection),
            'parsel_preview' => $this->preview($parselSection),
        ];
    }

    private function sectionStatus(bool $conventionalFound, bool $parselFound): string
    {
        if ($conventionalFound && $parselFound) {
            return 'both_found';
        }

        if (! $conventionalFound && ! $parselFound) {
            return 'both_missing';
        }

        return $conventionalFound ? 'only_conventional' : 'only_parsel';
    }

    /**
     * @param  array<string,mixed>|null  $section
     */
    private function preview(?array $section): ?string
    {
        $preview = trim((string) ($section['preview_text'] ?? ''));

        return $preview === '' ? null : $preview;
    }

    /**
     * @return array<int, string>
     */
    private function warnings(?AbaAnalysisRun $conventionalRun, ?AbaAnalysisRun $parselRun): array
    {
        $warnings = [];

        if (! $conventionalRun) {
            $warnings[] = 'Keine konventionelle Extraktion vorhanden.';
        }

        if (! $parselRun) {
            $warnings[] = 'Keine Parsel-Extraktion vorhanden.';
        }

        if ($conventionalRun && ! $this->isCompleted($conventionalRun)) {
            $warnings[] = 'Die konventionelle Extraktion ist noch nicht abgeschlossen.';
        }

        if ($parselRun && ! $this->isCompleted($parselRun)) {
            $warnings[] = 'Die Parsel-Extraktion ist noch nicht abgeschlossen.';
        }

        return $warnings;
    }
}
