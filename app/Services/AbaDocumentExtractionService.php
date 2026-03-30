<?php

namespace App\Services;

use App\Jobs\ABA\ProcessAbaAnalysisRunJob;
use App\Models\Aba;
use App\Models\AbaAnalysisRun;
use App\Models\User;

class AbaDocumentExtractionService
{
    private const EXTRACTION_OPTIONS_KEY = '_extraction_options';

    public function __construct(
        private readonly AbaSectionRuleProvider $sectionRuleProvider,
        private readonly DocxMainDocumentExtractor $documentExtractor,
        private readonly AbaSectionMatcher $sectionMatcher,
        private readonly AbaExtractionResultBuilder $resultBuilder,
        private readonly AbaExtractionPersister $persister,
    ) {}

    public function latest(Aba $aba): ?AbaAnalysisRun
    {
        return AbaAnalysisRun::query()
            ->extraction()
            ->where('aba_id', $aba->id)
            ->latest('id')
            ->first();
    }

    public function start(User $user, Aba $aba, bool $overwriteExistingFields = false): AbaAnalysisRun
    {
        $activeRun = AbaAnalysisRun::query()
            ->extraction()
            ->where('aba_id', $aba->id)
            ->whereIn('status', [AbaAnalysisRun::STATUS_STARTED, AbaAnalysisRun::STATUS_RUNNING])
            ->latest('id')
            ->first();

        if ($activeRun) {
            if ($overwriteExistingFields) {
                $activeRun->forceFill([
                    'summary' => $this->withExtractionOptions(
                        is_array($activeRun->summary ?? null) ? $activeRun->summary : [],
                        true,
                    ),
                ])->save();
            }

            return $activeRun->fresh();
        }

        $attachment = $aba->mainDocument()->first();
        if (! $attachment) {
            $run = $this->persister->startRun(
                $user,
                $aba,
                null,
                $this->withExtractionOptions([], $overwriteExistingFields),
            );

            return $this->persister->markFailed($run, 'Kein Hauptdokument vorhanden.', [
                'sections' => [],
                'missing_required_section_keys' => $this->sectionRuleProvider->requiredSectionKeys(),
                'found_optional_section_keys' => [],
                'uncertain_matches' => [],
                'unmatched_blocks_count' => 0,
                'warnings' => [],
                'errors' => ['Kein Hauptdokument vorhanden.'],
            ]);
        }

        $run = $this->persister->startRun(
            $user,
            $aba,
            $attachment,
            $this->withExtractionOptions([], $overwriteExistingFields),
        );

        if (! $this->documentExtractor->supports($attachment)) {
            return $this->persister->markFailed($run, 'Das Hauptdokument ist derzeit nicht als DOCX verfügbar.', [
                'sections' => [],
                'missing_required_section_keys' => $this->sectionRuleProvider->requiredSectionKeys(),
                'found_optional_section_keys' => [],
                'uncertain_matches' => [],
                'unmatched_blocks_count' => 0,
                'warnings' => [],
                'errors' => ['Für die Extraktion wird derzeit ein DOCX-Hauptdokument benötigt.'],
            ]);
        }

        if ($this->shouldRunSynchronously()) {
            $this->processRun($run->id);

            return $run->fresh('results');
        }

        ProcessAbaAnalysisRunJob::dispatch($run->id);

        return $run;
    }

    public function processRun(int $runId): void
    {
        $run = AbaAnalysisRun::query()
            ->with(['aba.mainDocument', 'attachment'])
            ->find($runId);

        if (! $run) {
            return;
        }

        if (! in_array($run->status, [AbaAnalysisRun::STATUS_STARTED, AbaAnalysisRun::STATUS_RUNNING], true)) {
            return;
        }

        $this->persister->markRunning($run);

        try {
            $attachment = $run->attachment ?? $run->aba?->mainDocument()->first();
            if (! $attachment) {
                $this->persister->markFailed($run, 'Das Hauptdokument ist nicht mehr verfügbar.', [
                    'sections' => [],
                    'missing_required_section_keys' => $this->sectionRuleProvider->requiredSectionKeys(),
                    'found_optional_section_keys' => [],
                    'uncertain_matches' => [],
                    'unmatched_blocks_count' => 0,
                    'warnings' => [],
                    'errors' => ['Das Hauptdokument ist nicht mehr verfügbar.'],
                ]);

                return;
            }

            $ruleDefinition = $this->sectionRuleProvider->definition();
            $documentExtraction = $this->documentExtractor->extract($attachment, [
                'aba_id' => $run->aba_id,
                'run_id' => $run->id,
            ]);
            $matchedSections = $this->sectionMatcher->match(
                is_array($ruleDefinition['sections'] ?? null) ? $ruleDefinition['sections'] : [],
                is_array($documentExtraction['sections'] ?? null) ? $documentExtraction['sections'] : [],
            );
            $summary = $this->resultBuilder->build($ruleDefinition, $documentExtraction, $matchedSections);
            $overwriteExistingFields = $this->shouldOverwriteExistingFields($run);

            if ($overwriteExistingFields && $run->aba) {
                $this->applyExtractedFieldsToAba($run->aba, $summary);
            }

            $summary = $this->withExtractionOptions($summary, $overwriteExistingFields);

            $this->persister->markCompleted($run, $documentExtraction, $summary);
        } catch (\Throwable $exception) {
            $this->persister->markFailed($run, $exception->getMessage(), [
                'sections' => [],
                'missing_required_section_keys' => $this->sectionRuleProvider->requiredSectionKeys(),
                'found_optional_section_keys' => [],
                'uncertain_matches' => [],
                'unmatched_blocks_count' => 0,
                'warnings' => [],
                'errors' => [trim($exception->getMessage()) !== '' ? $exception->getMessage() : 'Extraktion fehlgeschlagen.'],
            ]);
        }
    }

    private function shouldRunSynchronously(): bool
    {
        return trim((string) config('queue.default', 'sync')) === 'sync';
    }

    /**
     * @param  array<string,mixed>  $summary
     * @return array<string,mixed>
     */
    private function withExtractionOptions(array $summary, bool $overwriteExistingFields): array
    {
        $summary[self::EXTRACTION_OPTIONS_KEY] = [
            'overwrite_existing_fields' => $overwriteExistingFields,
        ];

        return $summary;
    }

    private function shouldOverwriteExistingFields(AbaAnalysisRun $run): bool
    {
        return (bool) data_get($run->summary, self::EXTRACTION_OPTIONS_KEY.'.overwrite_existing_fields', false);
    }

    /**
     * @param  array<string,mixed>  $summary
     */
    private function applyExtractedFieldsToAba(Aba $aba, array $summary): void
    {
        $titlePage = $this->resolveTitlePageSummary($summary);
        $managedOverrideKeys = ['subtitle', 'advisor', 'school_full', 'date'];
        $currentOverrides = is_array($aba->title_page_overrides) ? $aba->title_page_overrides : [];
        $updates = [
            'title' => '',
            'student_name' => '',
            'student_class' => null,
            'title_page_overrides' => array_merge(
                $currentOverrides,
                array_fill_keys($managedOverrideKeys, null),
            ),
        ];

        $title = $this->normalizeOptionalString($titlePage['title'] ?? null);
        if ($title !== null) {
            $updates['title'] = $title;
        }

        $author = $this->normalizeOptionalString($titlePage['author'] ?? null);
        if ($author !== null) {
            $updates['student_name'] = $author;
        }

        $class = $this->normalizeOptionalString($titlePage['class'] ?? null);
        if ($class !== null) {
            $updates['student_class'] = $class;
        }

        foreach ($managedOverrideKeys as $overrideKey) {
            $summaryKey = $overrideKey;
            $value = $this->normalizeOptionalString($titlePage[$summaryKey] ?? null);
            if ($value !== null) {
                $updates['title_page_overrides'][$overrideKey] = $value;
            }
        }

        $aba->update($updates);
    }

    /**
     * @param  array<string,mixed>  $summary
     * @return array<string,mixed>
     */
    private function resolveTitlePageSummary(array $summary): array
    {
        $sections = is_array($summary['sections'] ?? null) ? array_values($summary['sections']) : [];

        foreach ($sections as $section) {
            if (! is_array($section) || (string) ($section['key'] ?? '') !== 'title_page') {
                continue;
            }

            return is_array($section['title_page'] ?? null) ? $section['title_page'] : [];
        }

        return [];
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
