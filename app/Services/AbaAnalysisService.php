<?php

namespace App\Services;

use App\Jobs\ABA\ProcessAbaAnalysisRunJob;
use App\Models\Aba;
use App\Models\AbaAnalysisResult;
use App\Models\AbaAnalysisRun;
use App\Models\AbaAttachment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AbaAnalysisService
{
    public function __construct(
        private readonly AbaLocalDocumentTextExtractor $textExtractor,
        private readonly AbaLocalDocumentStructureExtractor $structureExtractor,
        private readonly AbaCanonicalDocumentBuilder $canonicalBuilder,
        private readonly AbaOpenAiNormalizationService $normalizationService,
        private readonly AbaExtractionValidationService $validationService,
        private readonly AbaReviewDecisionService $reviewDecisionService,
    ) {}

    /**
     * @return array{run:AbaAnalysisRun,already_running:bool,precondition_failed:bool,dispatched:bool,message:string}
     */
    public function startRun(User $user, Aba $aba): array
    {
        $activeRun = $aba->analysisRuns()
            ->whereIn('status', [AbaAnalysisRun::STATUS_STARTED, AbaAnalysisRun::STATUS_RUNNING])
            ->first();

        if ($activeRun) {
            return [
                'run' => $activeRun,
                'already_running' => true,
                'precondition_failed' => false,
                'dispatched' => false,
                'message' => 'Für diese ABA läuft bereits eine Analyse.',
            ];
        }

        $mainDocument = $aba->mainDocument()->first();
        if (! $mainDocument) {
            $run = AbaAnalysisRun::query()->create([
                'aba_id' => $aba->id,
                'aba_attachment_id' => null,
                'created_by_user_id' => $user->id,
                'status' => AbaAnalysisRun::STATUS_ABORTED,
                'status_message' => 'Kein Hauptdokument vorhanden.',
                'error_message' => 'Analyse abgebrochen: Kein Hauptdokument vorhanden.',
                'started_at' => now(),
                'aborted_at' => now(),
            ]);

            return [
                'run' => $run,
                'already_running' => false,
                'precondition_failed' => true,
                'dispatched' => false,
                'message' => 'Analyse konnte nicht gestartet werden: Kein Hauptdokument vorhanden.',
            ];
        }

        $started = DB::transaction(function () use ($aba, $mainDocument, $user): array {
            $deletedResults = AbaAnalysisResult::query()
                ->where('aba_id', $aba->id)
                ->delete();

            $run = AbaAnalysisRun::query()->create([
                'aba_id' => $aba->id,
                'aba_attachment_id' => $mainDocument->id,
                'created_by_user_id' => $user->id,
                'status' => AbaAnalysisRun::STATUS_STARTED,
                'status_message' => 'Analyselauf wurde gestartet.',
                'source_original_name' => $mainDocument->original_name,
                'source_path' => $mainDocument->path,
                'source_mime_type' => $mainDocument->mime_type,
                'started_at' => now(),
            ]);

            return [
                'run' => $run,
                'deleted_results' => $deletedResults,
            ];
        });

        /** @var AbaAnalysisRun $run */
        $run = $started['run'];

        ProcessAbaAnalysisRunJob::dispatch($run->id);

        $this->logDebug('aba.analysis.previous_results_deleted_on_start', [
            'run_id' => $run->id,
            'aba_id' => $aba->id,
            'deleted_results' => (int) ($started['deleted_results'] ?? 0),
        ]);

        return [
            'run' => $run,
            'already_running' => false,
            'precondition_failed' => false,
            'dispatched' => true,
            'message' => 'Analyselauf wurde gestartet.',
        ];
    }

    public function processRun(int $runId): void
    {
        $run = AbaAnalysisRun::query()
            ->with(['aba', 'attachment'])
            ->find($runId);

        if (! $run) {
            return;
        }

        if (! in_array($run->status, [AbaAnalysisRun::STATUS_STARTED, AbaAnalysisRun::STATUS_RUNNING], true)) {
            return;
        }

        $run->forceFill([
            'status' => AbaAnalysisRun::STATUS_RUNNING,
            'status_message' => 'Analyse läuft.',
            'running_at' => $run->running_at ?? now(),
            'error_message' => null,
            'failed_at' => null,
        ])->save();

        $this->logDebug('aba.analysis.run_started', [
            'run_id' => $run->id,
            'aba_id' => $run->aba_id,
            'attachment_id' => $run->aba_attachment_id,
        ]);

        try {
            $attachment = $run->attachment;
            if (! $attachment) {
                $this->markAborted($run, 'Analyse abgebrochen: Hauptdokument ist nicht mehr verfügbar.');

                return;
            }

            $extraction = $this->textExtractor->extractDocument($attachment);
            $text = trim((string) ($extraction['text'] ?? ''));
            if (trim($text) === '') {
                throw new \RuntimeException('Aus dem Hauptdokument konnte kein auswertbarer Text extrahiert werden.');
            }

            $sections = $this->structureExtractor->extractSections($text, [
                'outline' => is_array($extraction['outline'] ?? null) ? $extraction['outline'] : [],
                'toc_lines' => is_array($extraction['toc_lines'] ?? null) ? $extraction['toc_lines'] : [],
                'selected_candidate' => $extraction['selected_candidate'] ?? null,
                'extraction_candidates' => is_array($extraction['candidates'] ?? null) ? $extraction['candidates'] : [],
            ]);
            if ($sections === []) {
                $sections = $this->fallbackSection($text);
            }
            $structureDiagnostics = $this->structureExtractor->lastDiagnostics();

            $pageMap = is_array($extraction['page_map'] ?? null) ? $extraction['page_map'] : [];
            $hasDocxPageBreaks = (bool) ($pageMap['has_real_pagination'] ?? false);
            $hasFormFeedPages = substr_count($text, "\f") > 0;

            if ($hasDocxPageBreaks) {
                $sections = $this->applyPageMapping($sections, $pageMap);
            } elseif (! $hasFormFeedPages) {
                // No real page info from any source — clear heuristic defaults (all page 1)
                // to prevent misleading "Seite 1" from leaking to the UI.
                $sections = $this->clearSectionPageData($sections);
            }

            $canonical = $this->canonicalBuilder->build($attachment, $extraction, $text, $sections);
            $this->logDebug('aba.analysis.canonical_structure_built', [
                'run_id' => $run->id,
                'aba_id' => $run->aba_id,
                'document_version_id' => $canonical['document_version_id'] ?? null,
                'selected_candidate' => $canonical['selected_candidate'] ?? null,
                'block_count' => $canonical['metrics']['block_count'] ?? null,
                'section_candidate_count' => $canonical['metrics']['section_candidate_count'] ?? null,
                'toc_count' => $canonical['metrics']['toc_count'] ?? null,
                'body_start_line' => $canonical['body_start_line'] ?? null,
            ]);

            $normalized = $this->normalizationService->normalize($canonical);
            $this->logDebug('aba.analysis.normalization_completed', [
                'run_id' => $run->id,
                'aba_id' => $run->aba_id,
                'document_type' => $normalized['document_type'] ?? null,
                'section_count' => is_array($normalized['sections'] ?? null) ? count($normalized['sections']) : 0,
                'confidence' => $normalized['confidence'] ?? null,
                'normalization_source' => $normalized['normalization_source'] ?? null,
            ]);

            $validation = $this->validationService->validate($canonical, $normalized);
            $reviewDecision = $this->reviewDecisionService->decide($validation);
            $this->logDebug('aba.analysis.validation_completed', [
                'run_id' => $run->id,
                'aba_id' => $run->aba_id,
                'is_valid' => $validation['is_valid'] ?? false,
                'errors' => $validation['errors'] ?? [],
                'warnings' => $validation['warnings'] ?? [],
                'missing_fields' => $validation['missing_fields'] ?? [],
                'final_confidence' => $validation['final_confidence'] ?? null,
                'review_state' => $reviewDecision['state'] ?? null,
                'review_reason' => $reviewDecision['reason'] ?? null,
            ]);

            $summary = $this->persistResults($run, $attachment, $sections);
            $analysisStats = $this->buildAnalysisStatistics(
                text: $text,
                extraction: $extraction,
                canonical: $canonical,
                normalized: $normalized,
                validation: $validation,
                reviewDecision: $reviewDecision,
                sections: $sections,
                persistedSummary: $summary,
                structureDiagnostics: $structureDiagnostics,
            );

            $summary['extraction'] = [
                'selected_candidate' => $extraction['selected_candidate'] ?? null,
                'candidate_count' => count($extraction['candidates'] ?? []),
                'candidates' => $extraction['candidates'] ?? [],
                'outline_count' => count($extraction['outline'] ?? []),
                'toc_line_count' => count($extraction['toc_lines'] ?? []),
                'text_length' => mb_strlen($text),
            ];
            $summary['diagnostics'] = $structureDiagnostics;
            $summary['canonical_json'] = $canonical;
            $summary['normalized_json'] = $normalized;
            $summary['validation'] = $validation;
            $summary['review'] = $reviewDecision;
            $summary['analysis_stats'] = $analysisStats;
            $summary['record_counts'] = [
                'detected_record_count' => (int) ($analysisStats['detected_record_count'] ?? 0),
                'normalized_record_count' => (int) ($analysisStats['normalized_record_count'] ?? 0),
                'validated_record_count' => (int) ($analysisStats['validated_record_count'] ?? 0),
                'persisted_record_count' => (int) ($analysisStats['persisted_record_count'] ?? 0),
            ];
            $summary['detected_record_count'] = (int) ($analysisStats['detected_record_count'] ?? 0);
            $summary['normalized_record_count'] = (int) ($analysisStats['normalized_record_count'] ?? 0);
            $summary['validated_record_count'] = (int) ($analysisStats['validated_record_count'] ?? 0);
            $summary['persisted_record_count'] = (int) ($analysisStats['persisted_record_count'] ?? 0);
            $summary['count_delta_detected_vs_persisted'] = (int) ($analysisStats['count_delta_detected_vs_persisted'] ?? 0);
            $summary['count_delta_normalized_vs_persisted'] = (int) ($analysisStats['count_delta_normalized_vs_persisted'] ?? 0);
            $summary['count_delta_validated_vs_persisted'] = (int) ($analysisStats['count_delta_validated_vs_persisted'] ?? 0);
            $summary['count_mismatch_detected'] = (bool) ($analysisStats['count_mismatch_detected'] ?? false);
            $summary['count_mismatch_reason'] = $analysisStats['count_mismatch_reason'] ?? null;

            if (($analysisStats['count_mismatch_detected'] ?? false) === true) {
                $this->logDebug('aba.analysis.count_mismatch_detected', [
                    'run_id' => $run->id,
                    'aba_id' => $run->aba_id,
                    'detected_record_count' => $analysisStats['detected_record_count'] ?? null,
                    'normalized_record_count' => $analysisStats['normalized_record_count'] ?? null,
                    'validated_record_count' => $analysisStats['validated_record_count'] ?? null,
                    'persisted_record_count' => $analysisStats['persisted_record_count'] ?? null,
                    'reason' => $analysisStats['count_mismatch_reason'] ?? null,
                ]);
            }

            $statusMessage = ($reviewDecision['state'] ?? '') === 'review_required'
                ? 'Analyse abgeschlossen (Review erforderlich).'
                : 'Analyse abgeschlossen.';

            $run->forceFill([
                'status' => AbaAnalysisRun::STATUS_COMPLETED,
                'status_message' => $statusMessage,
                'completed_at' => now(),
                'aborted_at' => null,
                'failed_at' => null,
                'error_message' => null,
                'extracted_sections_count' => (int) ($summary['persisted_record_count'] ?? 0),
                'extracted_figures_count' => (int) ($summary['persisted_figure_count'] ?? 0),
                'summary' => $summary,
            ])->save();

            $this->logDebug('aba.analysis.run_completed', [
                'run_id' => $run->id,
                'aba_id' => $run->aba_id,
                'attachment_id' => $run->aba_attachment_id,
                'persisted_record_count' => $analysisStats['persisted_record_count'] ?? 0,
                'chapter_count' => $analysisStats['chapter_count'] ?? 0,
                'subchapter_count' => $analysisStats['subchapter_count'] ?? 0,
                'figure_count' => $analysisStats['figure_count'] ?? 0,
                'title_page_detected' => $analysisStats['title_page_detected'] ?? false,
                'abstract_de_detected' => $analysisStats['abstract_de_detected'] ?? false,
                'abstract_en_detected' => $analysisStats['abstract_en_detected'] ?? false,
                'toc_detected' => $analysisStats['table_of_contents_detected'] ?? false,
                'bibliography_detected' => $analysisStats['bibliography_detected'] ?? false,
                'figure_index_detected' => $analysisStats['figure_index_detected'] ?? false,
                'consent_declaration_detected' => $analysisStats['consent_declaration_detected'] ?? false,
                'max_hierarchy_level' => $analysisStats['max_hierarchy_level'] ?? 1,
                'final_confidence' => $analysisStats['final_confidence'] ?? null,
                'analysis_quality_score' => $analysisStats['analysis_quality_score'] ?? null,
                'extraction_consistency_score' => $analysisStats['extraction_consistency_score'] ?? null,
                'review_state' => $analysisStats['review_state'] ?? null,
                'count_mismatch_detected' => $analysisStats['count_mismatch_detected'] ?? false,
            ]);
        } catch (\Throwable $exception) {
            $this->markFailed($run, $exception->getMessage());
            $this->logDebug('aba.analysis.run_failed', [
                'run_id' => $run->id,
                'aba_id' => $run->aba_id,
                'attachment_id' => $run->aba_attachment_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function markAborted(AbaAnalysisRun $run, string $message): void
    {
        $run->forceFill([
            'status' => AbaAnalysisRun::STATUS_ABORTED,
            'status_message' => $message,
            'error_message' => $message,
            'aborted_at' => now(),
            'completed_at' => null,
            'failed_at' => null,
        ])->save();
    }

    private function markFailed(AbaAnalysisRun $run, string $message): void
    {
        $run->forceFill([
            'status' => AbaAnalysisRun::STATUS_FAILED,
            'status_message' => 'Analyse fehlgeschlagen.',
            'error_message' => trim($message) !== '' ? $message : 'Unbekannter Fehler.',
            'failed_at' => now(),
            'completed_at' => null,
            'aborted_at' => null,
        ])->save();
    }

    /**
     * Remove heuristic start_page / end_page values from sections.
     * Called when no trustworthy page source exists (no DOCX page breaks, no form feeds).
     *
     * @param  array<int, array<string,mixed>>  $sections
     * @return array<int, array<string,mixed>>
     */
    private function clearSectionPageData(array $sections): array
    {
        foreach ($sections as &$section) {
            $section['start_page'] = null;
            $section['end_page'] = null;
        }
        unset($section);

        return $sections;
    }

    /**
     * Apply a line-to-page mapping to all sections, populating start_page / end_page.
     *
     * @param  array<int, array<string,mixed>>  $sections
     * @param  array{line_to_page:array<int,int>,page_mapping_method:string}  $pageMap
     * @return array<int, array<string,mixed>>
     */
    private function applyPageMapping(array $sections, array $pageMap): array
    {
        $lineToPage = is_array($pageMap['line_to_page'] ?? null) ? $pageMap['line_to_page'] : [];
        $method = (string) ($pageMap['page_mapping_method'] ?? 'docx_xml_pagebreaks');

        if ($lineToPage === []) {
            return $sections;
        }

        foreach ($sections as &$section) {
            $startLine = $this->normalizeIntOrNull($section['start_line'] ?? null);
            $endLine = $this->normalizeIntOrNull($section['end_line'] ?? null);

            $startPage = $startLine !== null ? $this->resolvePageForLine($startLine, $lineToPage) : null;
            $endPage = $endLine !== null ? $this->resolvePageForLine($endLine, $lineToPage) : null;

            if ($startPage !== null) {
                $section['start_page'] = $startPage;
                if (! is_array($section['metadata'] ?? null)) {
                    $section['metadata'] = [];
                }

                $section['metadata']['page_mapping_method'] = $method;
            }

            if ($endPage !== null) {
                $section['end_page'] = $endPage;
            } elseif ($startPage !== null) {
                $section['end_page'] = $startPage;
            }
        }
        unset($section);

        return $sections;
    }

    /**
     * Resolve the page number for a line using nearest-neighbour interpolation.
     *
     * @param  array<int, int>  $lineToPage
     */
    private function resolvePageForLine(int $line, array $lineToPage): ?int
    {
        if ($line <= 0 || $lineToPage === []) {
            return null;
        }

        if (isset($lineToPage[$line])) {
            return $lineToPage[$line];
        }

        $nearest = null;
        $minDistance = PHP_INT_MAX;
        foreach ($lineToPage as $mappedLine => $page) {
            $distance = abs($mappedLine - $line);
            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearest = $page;
            }
        }

        return $nearest;
    }

    /**
     * @param  array<int, array{
     *   section_key:string,
     *   parent_key:string|null,
     *   section_type:string,
     *   section_title:string|null,
     *   extracted_text:string,
     *   hierarchy_level:int|null,
     *   start_line:int|null,
     *   end_line:int|null,
     *   start_page:int|null,
     *   end_page:int|null,
     *   anchor:array<string,mixed>,
     *   metadata:array<string,mixed>
     * }>  $sections
     * @return array{
     *   sections_total:int,
     *   figures_total:int,
     *   section_type_counts:array<string,int>,
     *   persisted_record_count:int,
     *   persisted_figure_count:int,
     *   record_type_counts:array<string,int>
     * }
     */
    private function persistResults(AbaAnalysisRun $run, AbaAttachment $attachment, array $sections): array
    {
        $orderedSections = array_values($sections);
        $summary = [
            'sections_total' => 0,
            'figures_total' => 0,
            'section_type_counts' => [],
            'persisted_record_count' => 0,
            'persisted_figure_count' => 0,
            'record_type_counts' => [],
        ];

        DB::transaction(function () use ($run, $attachment, $orderedSections, &$summary): void {
            $run->results()->delete();

            $map = [];
            foreach ($orderedSections as $index => $section) {
                $sectionType = trim((string) ($section['section_type'] ?? 'other_section'));
                if ($sectionType === '') {
                    $sectionType = 'other_section';
                }

                $parentKey = $section['parent_key'] ?? null;
                $parentId = is_string($parentKey) ? ($map[$parentKey] ?? null) : null;

                $result = $run->results()->create([
                    'aba_id' => $run->aba_id,
                    'aba_attachment_id' => $attachment->id,
                    'parent_result_id' => $parentId,
                    'section_type' => $sectionType,
                    'section_title' => $this->normalizeSectionTitle($section['section_title'] ?? null),
                    'extracted_text' => $this->normalizeExtractedText($section['extracted_text'] ?? ''),
                    'sort_order' => $index + 1,
                    'hierarchy_level' => $this->normalizeHierarchyLevel($section['hierarchy_level'] ?? null),
                    'start_line' => $this->normalizeIntOrNull($section['start_line'] ?? null),
                    'end_line' => $this->normalizeIntOrNull($section['end_line'] ?? null),
                    'start_page' => $this->normalizeIntOrNull($section['start_page'] ?? null),
                    'end_page' => $this->normalizeIntOrNull($section['end_page'] ?? null),
                    'anchor' => is_array($section['anchor'] ?? null) ? $section['anchor'] : [],
                    'metadata' => is_array($section['metadata'] ?? null) ? $section['metadata'] : [],
                ]);

                $sectionKey = (string) ($section['section_key'] ?? '');
                if ($sectionKey !== '') {
                    $map[$sectionKey] = $result->id;
                }

                $summary['sections_total']++;
                if ($sectionType === 'figure') {
                    $summary['figures_total']++;
                }
                $summary['section_type_counts'][$sectionType] = (int) ($summary['section_type_counts'][$sectionType] ?? 0) + 1;
            }
        });

        $summary['persisted_record_count'] = (int) $run->results()->count();
        $summary['persisted_figure_count'] = (int) $run->results()->where('section_type', 'figure')->count();
        $summary['record_type_counts'] = $summary['section_type_counts'];
        $summary['sections_total'] = $summary['persisted_record_count'];
        $summary['figures_total'] = $summary['persisted_figure_count'];

        return $summary;
    }

    /**
     * @param  array<string,mixed>  $extraction
     * @param  array<string,mixed>  $canonical
     * @param  array<string,mixed>  $normalized
     * @param  array<string,mixed>  $validation
     * @param  array<string,mixed>  $reviewDecision
     * @param  array<int, array{
     *   section_key:string,
     *   parent_key:string|null,
     *   section_type:string,
     *   section_title:string|null,
     *   extracted_text:string,
     *   hierarchy_level:int|null,
     *   start_line:int|null,
     *   end_line:int|null,
     *   start_page:int|null,
     *   end_page:int|null,
     *   anchor:array<string,mixed>,
     *   metadata:array<string,mixed>
     * }>  $sections
     * @param  array{
     *   persisted_record_count:int,
     *   persisted_figure_count:int,
     *   section_type_counts:array<string,int>
     * }  $persistedSummary
     * @param  array<string,mixed>  $structureDiagnostics
     * @return array<string,mixed>
     */
    private function buildAnalysisStatistics(
        string $text,
        array $extraction,
        array $canonical,
        array $normalized,
        array $validation,
        array $reviewDecision,
        array $sections,
        array $persistedSummary,
        array $structureDiagnostics,
    ): array {
        $sectionStats = $this->computeSectionStatistics($sections);
        $typeCounts = is_array($sectionStats['type_counts'] ?? null) ? $sectionStats['type_counts'] : [];
        $filterStats = is_array($structureDiagnostics['filter_stats'] ?? null) ? $structureDiagnostics['filter_stats'] : [];

        $detectedRecordCount = (int) ($structureDiagnostics['detected_record_count'] ?? count($sections));
        $normalizedRecordCount = (int) ($validation['normalized_record_count'] ?? (is_array($normalized['sections'] ?? null) ? count($normalized['sections']) : 0));
        $validatedRecordCount = (int) ($validation['validated_record_count'] ?? $normalizedRecordCount);
        $persistedRecordCount = (int) ($persistedSummary['persisted_record_count'] ?? 0);

        $countDeltaDetectedVsPersisted = $detectedRecordCount - $persistedRecordCount;
        $countDeltaNormalizedVsPersisted = $normalizedRecordCount - $persistedRecordCount;
        $countDeltaValidatedVsPersisted = $validatedRecordCount - $persistedRecordCount;
        $countMismatchDetected = $countDeltaDetectedVsPersisted !== 0
            || $countDeltaNormalizedVsPersisted !== 0
            || $countDeltaValidatedVsPersisted !== 0;

        $validationErrorCount = (int) ($validation['validation_error_count'] ?? count(is_array($validation['errors'] ?? null) ? $validation['errors'] : []));
        $validationWarningCount = (int) ($validation['validation_warning_count'] ?? count(is_array($validation['warnings'] ?? null) ? $validation['warnings'] : []));
        $missingFieldsCount = (int) ($validation['missing_fields_count'] ?? count(is_array($validation['missing_fields'] ?? null) ? $validation['missing_fields'] : []));
        $finalConfidence = is_numeric($validation['final_confidence'] ?? null) ? (float) $validation['final_confidence'] : 0.0;
        $localExtractionConfidence = is_numeric($validation['local_confidence'] ?? null) ? (float) $validation['local_confidence'] : 0.0;

        $tocRange = $this->tocLineRange($canonical, $sectionStats);
        $bodyStartLine = $this->normalizeIntOrNull($canonical['body_start_line'] ?? ($structureDiagnostics['body_start_line'] ?? null));

        $titlePageDetected = ((int) ($typeCounts['title_page'] ?? 0)) > 0;
        $abstractDetected = ((int) ($typeCounts['abstract'] ?? 0)) > 0;
        $abstractLanguageStats = $this->resolveAbstractLanguageStatistics($sections, $structureDiagnostics);
        $forewordDetected = ((int) ($typeCounts['foreword'] ?? 0)) > 0;
        $tableOfContentsDetected = ((int) ($typeCounts['table_of_contents'] ?? 0)) > 0;
        $bibliographyDetected = ((int) ($typeCounts['bibliography'] ?? 0)) > 0;
        $figureIndexDetected = ((int) ($typeCounts['figure_index'] ?? 0)) > 0;
        $consentDeclarationDetected = ((int) ($typeCounts['consent_declaration'] ?? 0)) > 0;
        $bodyDetected = $bodyStartLine !== null;

        $centralDetectedCount = count(array_filter([
            $titlePageDetected,
            $abstractDetected,
            $forewordDetected,
            $tableOfContentsDetected,
            $bibliographyDetected,
            $figureIndexDetected,
            $consentDeclarationDetected,
            $bodyDetected,
        ]));
        $centralCoverage = $centralDetectedCount / 8;

        $structureConfidence = $this->clampScore(
            0.28
            + ($centralCoverage * 0.5)
            + (((int) ($typeCounts['chapter'] ?? 0)) > 0 ? 0.09 : 0.0)
            + (((int) ($typeCounts['subchapter'] ?? 0)) > 0 ? 0.06 : 0.0)
            - min(0.18, $validationWarningCount * 0.015)
            - min(0.24, $validationErrorCount * 0.06)
        );
        $tocDetectionConfidence = $this->clampScore(
            (((int) ($structureDiagnostics['toc_count'] ?? 0)) > 0 ? 0.45 : 0.1)
            + min(0.3, ((int) ($structureDiagnostics['toc_line_count'] ?? 0)) / 40)
            + (($bodyStartLine !== null && $tocRange['end_line'] !== null && $bodyStartLine > $tocRange['end_line']) ? 0.15 : 0.0)
            - min(0.25, ((int) ($filterStats['toc_candidates_rejected_count'] ?? 0)) * 0.01)
        );
        $hierarchyConfidence = $this->clampScore(
            0.24
            + min(0.24, ((int) ($sectionStats['max_hierarchy_level'] ?? 1)) / 6)
            + min(0.2, ((int) ($sectionStats['chapters_with_children_count'] ?? 0)) * 0.08)
            + min(0.15, ((int) ($sectionStats['subchapters_with_children_count'] ?? 0)) * 0.08)
            - min(0.2, ((int) ($sectionStats['empty_parent_sections_count'] ?? 0)) * 0.08)
        );

        $extractionConsistencyScore = $this->clampScore(
            1.0
            - min(0.6, $validationErrorCount * 0.12)
            - min(0.25, $validationWarningCount * 0.03)
            - min(0.2, $missingFieldsCount * 0.03)
            - ($countMismatchDetected ? 0.25 : 0.0)
        );
        $maxCountForDelta = max(1, $detectedRecordCount, $normalizedRecordCount, $validatedRecordCount, $persistedRecordCount);
        $deltaSum = abs($countDeltaDetectedVsPersisted) + abs($countDeltaNormalizedVsPersisted) + abs($countDeltaValidatedVsPersisted);
        $persistenceConsistencyScore = $countMismatchDetected
            ? $this->clampScore(1.0 - min(1.0, $deltaSum / ($maxCountForDelta * 1.5)))
            : 1.0;

        $structureQualityScore = $this->clampScore(
            ($structureConfidence * 0.45)
            + ($tocDetectionConfidence * 0.25)
            + ($hierarchyConfidence * 0.3)
        );
        $analysisQualityScore = $this->clampScore(
            ($structureQualityScore * 0.3)
            + ($tocDetectionConfidence * 0.15)
            + ($hierarchyConfidence * 0.15)
            + ($extractionConsistencyScore * 0.2)
            + ($persistenceConsistencyScore * 0.1)
            + ($finalConfidence * 0.1)
        );

        $qualityScoreReasons = [];
        $qualityScoreReasons[] = $countMismatchDetected ? 'count_mismatch_detected' : 'counts_consistent';
        $qualityScoreReasons[] = $validationErrorCount > 0 ? 'validation_errors_present' : 'validation_errors_absent';
        $qualityScoreReasons[] = $validationWarningCount > 0 ? 'validation_warnings_present' : 'validation_warnings_absent';
        if ((int) ($filterStats['toc_duplicates_removed_count'] ?? 0) > 0) {
            $qualityScoreReasons[] = 'toc_duplicates_filtered';
        }
        if ($tableOfContentsDetected) {
            $qualityScoreReasons[] = 'table_of_contents_detected';
        }
        if (((int) ($typeCounts['chapter'] ?? 0)) > 0) {
            $qualityScoreReasons[] = 'chapters_detected';
        }
        if (((int) ($sectionStats['max_hierarchy_level'] ?? 1)) >= 2) {
            $qualityScoreReasons[] = 'hierarchy_detected';
        }
        $qualityScoreReasons[] = ((string) ($reviewDecision['state'] ?? '')) === 'auto_approved'
            ? 'auto_approved'
            : 'review_required';

        $countMismatchReason = $this->buildCountMismatchReason(
            countMismatchDetected: $countMismatchDetected,
            countDeltaDetectedVsPersisted: $countDeltaDetectedVsPersisted,
            countDeltaNormalizedVsPersisted: $countDeltaNormalizedVsPersisted,
            countDeltaValidatedVsPersisted: $countDeltaValidatedVsPersisted,
            filterStats: $filterStats,
            validationErrorCount: $validationErrorCount,
            validationWarningCount: $validationWarningCount,
        );

        $titleRange = $this->firstTypeRange($sectionStats, 'title_page');
        $bibliographyRange = $this->firstTypeRange($sectionStats, 'bibliography');
        $figureIndexRange = $this->firstTypeRange($sectionStats, 'figure_index');
        $consentRange = $this->firstTypeRange($sectionStats, 'consent_declaration');

        return [
            'document_type' => (string) ($normalized['document_type'] ?? ($canonical['document_type'] ?? 'aba')),
            'selected_candidate' => $extraction['selected_candidate'] ?? null,
            'candidate_count' => count($extraction['candidates'] ?? []),
            'block_count' => (int) ($canonical['metrics']['block_count'] ?? (is_array($canonical['blocks'] ?? null) ? count($canonical['blocks']) : 0)),
            'line_count' => (int) ($structureDiagnostics['line_count'] ?? (substr_count($text, "\n") + 1)),
            'text_length' => (int) ($structureDiagnostics['text_length'] ?? mb_strlen($text)),
            'body_start_line' => $bodyStartLine,
            'toc_count' => (int) ($structureDiagnostics['toc_count'] ?? count((array) ($canonical['toc_ranges'] ?? []))),
            'toc_line_count' => (int) ($structureDiagnostics['toc_line_count'] ?? count($extraction['toc_lines'] ?? [])),
            'heading_count' => (int) ($structureDiagnostics['heading_count'] ?? 0),
            'detected_record_count' => $detectedRecordCount,
            'normalized_record_count' => $normalizedRecordCount,
            'validated_record_count' => $validatedRecordCount,
            'persisted_record_count' => $persistedRecordCount,
            'count_delta_detected_vs_persisted' => $countDeltaDetectedVsPersisted,
            'count_delta_normalized_vs_persisted' => $countDeltaNormalizedVsPersisted,
            'count_delta_validated_vs_persisted' => $countDeltaValidatedVsPersisted,
            'count_mismatch_detected' => $countMismatchDetected,
            'count_mismatch_reason' => $countMismatchReason,

            'title_page_detected' => $titlePageDetected,
            'abstract_detected' => $abstractDetected,
            'abstract_de_detected' => (bool) ($abstractLanguageStats['abstract_de_detected'] ?? false),
            'abstract_en_detected' => (bool) ($abstractLanguageStats['abstract_en_detected'] ?? false),
            'abstract_missing_languages' => array_values((array) ($abstractLanguageStats['abstract_missing_languages'] ?? [])),
            'foreword_detected' => $forewordDetected,
            'table_of_contents_detected' => $tableOfContentsDetected,
            'bibliography_detected' => $bibliographyDetected,
            'figure_index_detected' => $figureIndexDetected,
            'consent_declaration_detected' => $consentDeclarationDetected,
            'body_detected' => $bodyDetected,

            'title_page_start_line' => $titleRange['start_line'],
            'title_page_end_line' => $titleRange['end_line'],
            'abstract_de_start_line' => $abstractLanguageStats['abstract_de_start_line'] ?? null,
            'abstract_de_end_line' => $abstractLanguageStats['abstract_de_end_line'] ?? null,
            'abstract_en_start_line' => $abstractLanguageStats['abstract_en_start_line'] ?? null,
            'abstract_en_end_line' => $abstractLanguageStats['abstract_en_end_line'] ?? null,
            'toc_start_line' => $tocRange['start_line'],
            'toc_end_line' => $tocRange['end_line'],
            'bibliography_start_line' => $bibliographyRange['start_line'],
            'bibliography_end_line' => $bibliographyRange['end_line'],
            'figure_index_start_line' => $figureIndexRange['start_line'],
            'figure_index_end_line' => $figureIndexRange['end_line'],
            'consent_declaration_start_line' => $consentRange['start_line'],
            'consent_declaration_end_line' => $consentRange['end_line'],

            'title_page_count' => (int) ($typeCounts['title_page'] ?? 0),
            'abstract_count' => (int) ($typeCounts['abstract'] ?? 0),
            'abstract_de_count' => (int) ($abstractLanguageStats['abstract_de_count'] ?? 0),
            'abstract_en_count' => (int) ($abstractLanguageStats['abstract_en_count'] ?? 0),
            'foreword_count' => (int) ($typeCounts['foreword'] ?? 0),
            'table_of_contents_count' => (int) ($typeCounts['table_of_contents'] ?? 0),
            'chapter_count' => (int) ($typeCounts['chapter'] ?? 0),
            'subchapter_count' => (int) ($typeCounts['subchapter'] ?? 0),
            'bibliography_count' => (int) ($typeCounts['bibliography'] ?? 0),
            'figure_index_count' => (int) ($typeCounts['figure_index'] ?? 0),
            'figure_count' => (int) ($typeCounts['figure'] ?? 0),
            'consent_declaration_count' => (int) ($typeCounts['consent_declaration'] ?? 0),
            'other_section_count' => (int) ($typeCounts['other_section'] ?? 0),

            'max_hierarchy_level' => (int) ($sectionStats['max_hierarchy_level'] ?? 1),
            'chapters_with_children_count' => (int) ($sectionStats['chapters_with_children_count'] ?? 0),
            'subchapters_with_children_count' => (int) ($sectionStats['subchapters_with_children_count'] ?? 0),
            'leaf_sections_count' => (int) ($sectionStats['leaf_sections_count'] ?? 0),
            'empty_parent_sections_count' => (int) ($sectionStats['empty_parent_sections_count'] ?? 0),
            'sections_with_body_text_count' => (int) ($sectionStats['sections_with_body_text_count'] ?? 0),
            'sections_without_body_text_count' => (int) ($sectionStats['sections_without_body_text_count'] ?? 0),
            'average_body_chars_per_section' => (float) ($sectionStats['average_body_chars_per_section'] ?? 0.0),
            'median_body_chars_per_section' => (float) ($sectionStats['median_body_chars_per_section'] ?? 0.0),
            'longest_section_chars' => (int) ($sectionStats['longest_section_chars'] ?? 0),
            'shortest_non_empty_section_chars' => (int) ($sectionStats['shortest_non_empty_section_chars'] ?? 0),

            'local_extraction_confidence' => $localExtractionConfidence,
            'structure_confidence' => $structureConfidence,
            'toc_detection_confidence' => $tocDetectionConfidence,
            'hierarchy_confidence' => $hierarchyConfidence,
            'final_confidence' => $finalConfidence,
            'validation_error_count' => $validationErrorCount,
            'validation_warning_count' => $validationWarningCount,
            'missing_fields_count' => $missingFieldsCount,
            'review_state' => (string) ($reviewDecision['state'] ?? 'review_required'),
            'auto_approved' => (bool) ($reviewDecision['auto_approved'] ?? false),

            'analysis_quality_score' => $analysisQualityScore,
            'structure_quality_score' => $structureQualityScore,
            'extraction_consistency_score' => $extractionConsistencyScore,
            'persistence_consistency_score' => $persistenceConsistencyScore,
            'quality_score_reasons' => array_values(array_unique($qualityScoreReasons)),

            'toc_candidates_rejected_count' => (int) ($filterStats['toc_candidates_rejected_count'] ?? 0),
            'toc_duplicates_removed_count' => (int) ($filterStats['toc_duplicates_removed_count'] ?? 0),
            'low_evidence_headings_rejected_count' => (int) ($filterStats['low_evidence_headings_rejected_count'] ?? 0),
            'bibliography_entry_lines_filtered_count' => (int) ($filterStats['bibliography_entry_lines_filtered_count'] ?? 0),
            'figure_index_entry_lines_filtered_count' => (int) ($filterStats['figure_index_entry_lines_filtered_count'] ?? 0),
            'pre_body_candidates_rejected_count' => (int) ($filterStats['pre_body_candidates_rejected_count'] ?? 0),
            'short_heading_candidates_rejected_count' => (int) ($filterStats['short_heading_candidates_rejected_count'] ?? 0),
            'context_rejected_candidates_count' => (int) ($filterStats['context_rejected_candidates_count'] ?? 0),

            ...$this->buildPaginationStatistics($extraction, $sections),
        ];
    }

    /**
     * Compute pagination statistics from the extraction page map and the (already page-mapped) sections.
     *
     * @param  array<string,mixed>  $extraction
     * @param  array<int, array<string,mixed>>  $sections
     * @return array<string,mixed>
     */
    private function buildPaginationStatistics(array $extraction, array $sections): array
    {
        $pageMap = is_array($extraction['page_map'] ?? null) ? $extraction['page_map'] : [];
        $hasRealPagination = (bool) ($pageMap['has_real_pagination'] ?? false);
        $paginationSource = (string) ($pageMap['page_mapping_method'] ?? 'not_available');
        $pageCountTotal = (int) ($pageMap['total_page_count'] ?? 0);
        $pageBreakCount = (int) ($pageMap['page_break_count'] ?? 0);

        $recordsWithPageMapping = 0;
        $recordsWithoutPageMapping = 0;
        $pageValidationWarnings = [];

        foreach ($sections as $section) {
            $startPage = $this->normalizeIntOrNull($section['start_page'] ?? null);
            $endPage = $this->normalizeIntOrNull($section['end_page'] ?? null);

            if ($startPage !== null && $startPage > 0) {
                $recordsWithPageMapping++;

                if ($endPage !== null && $endPage < $startPage) {
                    $pageValidationWarnings[] = 'page_end_before_start:'.($section['section_key'] ?? 'unknown');
                }

                if ($pageCountTotal > 0 && $endPage !== null && $endPage > $pageCountTotal) {
                    $pageValidationWarnings[] = 'page_exceeds_total:'.($section['section_key'] ?? 'unknown');
                }
            } else {
                $recordsWithoutPageMapping++;
                if ($hasRealPagination) {
                    $pageValidationWarnings[] = 'missing_page_mapping:'.($section['section_key'] ?? 'unknown');
                }
            }
        }

        $totalSections = count($sections);
        $pageMappingCoverage = $totalSections > 0
            ? round($recordsWithPageMapping / $totalSections, 4)
            : 0.0;
        $pageMappingConfidence = $hasRealPagination
            ? $this->clampScore(0.9 - min(0.4, count($pageValidationWarnings) * 0.05))
            : 0.0;

        return [
            'has_real_pagination' => $hasRealPagination,
            'pagination_source' => $paginationSource,
            'page_count_total' => $pageCountTotal,
            'page_break_count' => $pageBreakCount,
            'records_with_page_mapping_count' => $recordsWithPageMapping,
            'records_without_page_mapping_count' => $recordsWithoutPageMapping,
            'page_mapping_coverage' => $pageMappingCoverage,
            'page_mapping_confidence' => $pageMappingConfidence,
            'page_validation_warnings' => array_values(array_unique($pageValidationWarnings)),
        ];
    }

    /**
     * @param  array<int, array{
     *   section_key:string,
     *   parent_key:string|null,
     *   section_type:string,
     *   extracted_text:string,
     *   hierarchy_level:int|null,
     *   start_line:int|null,
     *   end_line:int|null
     * }>  $sections
     * @return array<string,mixed>
     */
    private function computeSectionStatistics(array $sections): array
    {
        $typeCounts = [];
        $typeRanges = [];
        $sectionByKey = [];
        $childrenByKey = [];
        $bodyCharLengths = [];
        $nonEmptyBodyCharLengths = [];
        $sectionBodyCharsByKey = [];
        $maxHierarchyLevel = 1;
        $sectionsWithBodyTextCount = 0;
        $sectionsWithoutBodyTextCount = 0;

        foreach ($sections as $section) {
            $key = (string) ($section['section_key'] ?? '');
            if ($key === '') {
                continue;
            }

            $sectionByKey[$key] = $section;
            $parentKey = trim((string) ($section['parent_key'] ?? ''));
            if ($parentKey !== '') {
                $childrenByKey[$parentKey] ??= [];
                $childrenByKey[$parentKey][] = $key;
            }

            $type = trim((string) ($section['section_type'] ?? 'other_section'));
            if ($type === '') {
                $type = 'other_section';
            }
            $typeCounts[$type] = (int) ($typeCounts[$type] ?? 0) + 1;

            $startLine = $this->normalizeIntOrNull($section['start_line'] ?? null);
            $endLine = $this->normalizeIntOrNull($section['end_line'] ?? null);
            if ($startLine !== null && $endLine !== null) {
                if (! isset($typeRanges[$type])) {
                    $typeRanges[$type] = [
                        'start_line' => $startLine,
                        'end_line' => max($startLine, $endLine),
                    ];
                } else {
                    $typeRanges[$type]['start_line'] = min((int) $typeRanges[$type]['start_line'], $startLine);
                    $typeRanges[$type]['end_line'] = max((int) $typeRanges[$type]['end_line'], max($startLine, $endLine));
                }
            }

            $level = (int) ($section['hierarchy_level'] ?? 1);
            if ($level <= 0) {
                $level = 1;
            }
            $maxHierarchyLevel = max($maxHierarchyLevel, $level);

            $bodyChars = mb_strlen(trim((string) ($section['extracted_text'] ?? '')));
            $sectionBodyCharsByKey[$key] = $bodyChars;
            $bodyCharLengths[] = $bodyChars;
            if ($bodyChars > 0) {
                $sectionsWithBodyTextCount++;
                $nonEmptyBodyCharLengths[] = $bodyChars;
            } else {
                $sectionsWithoutBodyTextCount++;
            }
        }

        $chaptersWithChildrenCount = 0;
        $subchaptersWithChildrenCount = 0;
        $leafSectionsCount = 0;
        $emptyParentSectionsCount = 0;

        foreach ($sectionByKey as $key => $section) {
            $children = $childrenByKey[$key] ?? [];
            $childCount = count($children);
            if ($childCount === 0) {
                $leafSectionsCount++;
            } else {
                $type = (string) ($section['section_type'] ?? 'other_section');
                if ($type === 'chapter') {
                    $chaptersWithChildrenCount++;
                } elseif ($type === 'subchapter') {
                    $subchaptersWithChildrenCount++;
                }

                if (((int) ($sectionBodyCharsByKey[$key] ?? 0)) === 0) {
                    $emptyParentSectionsCount++;
                }
            }
        }

        return [
            'type_counts' => $typeCounts,
            'type_ranges' => $typeRanges,
            'max_hierarchy_level' => $maxHierarchyLevel,
            'chapters_with_children_count' => $chaptersWithChildrenCount,
            'subchapters_with_children_count' => $subchaptersWithChildrenCount,
            'leaf_sections_count' => $leafSectionsCount,
            'empty_parent_sections_count' => $emptyParentSectionsCount,
            'sections_with_body_text_count' => $sectionsWithBodyTextCount,
            'sections_without_body_text_count' => $sectionsWithoutBodyTextCount,
            'average_body_chars_per_section' => $bodyCharLengths === []
                ? 0.0
                : round(array_sum($bodyCharLengths) / count($bodyCharLengths), 2),
            'median_body_chars_per_section' => $this->median($bodyCharLengths),
            'longest_section_chars' => $bodyCharLengths === [] ? 0 : max($bodyCharLengths),
            'shortest_non_empty_section_chars' => $nonEmptyBodyCharLengths === [] ? 0 : min($nonEmptyBodyCharLengths),
        ];
    }

    /**
     * @param  array<string,mixed>  $canonical
     * @param  array<string,mixed>  $sectionStats
     * @return array{start_line:int|null,end_line:int|null}
     */
    private function tocLineRange(array $canonical, array $sectionStats): array
    {
        $ranges = is_array($canonical['toc_ranges'] ?? null) ? $canonical['toc_ranges'] : [];
        $startLine = null;
        $endLine = null;

        foreach ($ranges as $range) {
            if (! is_array($range)) {
                continue;
            }

            $rangeStart = $this->normalizeIntOrNull($range['start_line'] ?? null);
            $rangeEnd = $this->normalizeIntOrNull($range['end_line'] ?? null);
            if ($rangeStart === null || $rangeEnd === null) {
                continue;
            }

            $startLine = $startLine === null ? $rangeStart : min($startLine, $rangeStart);
            $endLine = $endLine === null ? $rangeEnd : max($endLine, $rangeEnd);
        }

        if ($startLine === null || $endLine === null) {
            $fallback = $this->firstTypeRange($sectionStats, 'table_of_contents');

            return [
                'start_line' => $fallback['start_line'],
                'end_line' => $fallback['end_line'],
            ];
        }

        return [
            'start_line' => $startLine,
            'end_line' => $endLine,
        ];
    }

    /**
     * @param  array<string,mixed>  $sectionStats
     * @return array{start_line:int|null,end_line:int|null}
     */
    private function firstTypeRange(array $sectionStats, string $type): array
    {
        $ranges = is_array($sectionStats['type_ranges'] ?? null) ? $sectionStats['type_ranges'] : [];
        $range = is_array($ranges[$type] ?? null) ? $ranges[$type] : null;
        if ($range === null) {
            return [
                'start_line' => null,
                'end_line' => null,
            ];
        }

        return [
            'start_line' => $this->normalizeIntOrNull($range['start_line'] ?? null),
            'end_line' => $this->normalizeIntOrNull($range['end_line'] ?? null),
        ];
    }

    /**
     * @param  array<int, array{
     *   section_type:string,
     *   section_title:string|null,
     *   extracted_text:string,
     *   start_line:int|null,
     *   end_line:int|null,
     *   metadata:array<string,mixed>
     * }>  $sections
     * @param  array<string,mixed>  $structureDiagnostics
     * @return array<string,mixed>
     */
    private function resolveAbstractLanguageStatistics(array $sections, array $structureDiagnostics): array
    {
        $deDetected = (bool) ($structureDiagnostics['abstract_de_detected'] ?? false);
        $enDetected = (bool) ($structureDiagnostics['abstract_en_detected'] ?? false);
        $deCount = (int) ($structureDiagnostics['abstract_de_count'] ?? 0);
        $enCount = (int) ($structureDiagnostics['abstract_en_count'] ?? 0);
        $deStart = $this->normalizeIntOrNull($structureDiagnostics['abstract_de_start_line'] ?? null);
        $deEnd = $this->normalizeIntOrNull($structureDiagnostics['abstract_de_end_line'] ?? null);
        $enStart = $this->normalizeIntOrNull($structureDiagnostics['abstract_en_start_line'] ?? null);
        $enEnd = $this->normalizeIntOrNull($structureDiagnostics['abstract_en_end_line'] ?? null);

        if ($deCount === 0 && $enCount === 0) {
            foreach ($sections as $section) {
                if ((string) ($section['section_type'] ?? '') !== 'abstract') {
                    continue;
                }

                $metadata = is_array($section['metadata'] ?? null) ? $section['metadata'] : [];
                $language = trim((string) ($metadata['abstract_language'] ?? ''));
                if (! in_array($language, ['de', 'en'], true)) {
                    $language = $this->inferAbstractLanguage(
                        (string) ($section['section_title'] ?? ''),
                        (string) ($section['extracted_text'] ?? ''),
                    );
                }

                $startLine = $this->normalizeIntOrNull($section['start_line'] ?? null);
                $endLine = $this->normalizeIntOrNull($section['end_line'] ?? null);

                if ($language === 'de') {
                    $deDetected = true;
                    $deCount++;
                    if ($startLine !== null) {
                        $deStart = $deStart === null ? $startLine : min($deStart, $startLine);
                    }
                    if ($endLine !== null) {
                        $deEnd = $deEnd === null ? $endLine : max($deEnd, $endLine);
                    }
                } elseif ($language === 'en') {
                    $enDetected = true;
                    $enCount++;
                    if ($startLine !== null) {
                        $enStart = $enStart === null ? $startLine : min($enStart, $startLine);
                    }
                    if ($endLine !== null) {
                        $enEnd = $enEnd === null ? $endLine : max($enEnd, $endLine);
                    }
                }
            }
        }

        $missing = is_array($structureDiagnostics['abstract_missing_languages'] ?? null)
            ? array_values($structureDiagnostics['abstract_missing_languages'])
            : [];
        if ($missing === []) {
            if (! $deDetected) {
                $missing[] = 'de';
            }
            if (! $enDetected) {
                $missing[] = 'en';
            }
        }

        return [
            'abstract_de_detected' => $deDetected,
            'abstract_en_detected' => $enDetected,
            'abstract_de_count' => $deCount,
            'abstract_en_count' => $enCount,
            'abstract_missing_languages' => array_values(array_unique($missing)),
            'abstract_de_start_line' => $deStart,
            'abstract_de_end_line' => $deEnd,
            'abstract_en_start_line' => $enStart,
            'abstract_en_end_line' => $enEnd,
        ];
    }

    private function inferAbstractLanguage(string $title, string $text): string
    {
        $normalizedTitle = mb_strtolower(trim($title));
        if (preg_match('/\b(zusammenfassung|kurzfassung|deutsch)\b/u', $normalizedTitle) === 1) {
            return 'de';
        }

        if (preg_match('/\b(abstract|english|summary)\b/u', $normalizedTitle) === 1) {
            return 'en';
        }

        $sample = mb_strtolower(mb_substr(trim($text), 0, 1200));
        $deHits = 0;
        $enHits = 0;
        foreach ([' der ', ' die ', ' das ', ' und ', ' ist ', ' mit ', ' für ', ' nicht '] as $needle) {
            $deHits += substr_count(' '.$sample.' ', $needle);
        }
        foreach ([' the ', ' and ', ' of ', ' is ', ' with ', ' this ', ' for ', ' in '] as $needle) {
            $enHits += substr_count(' '.$sample.' ', $needle);
        }

        if ($deHits >= $enHits + 2) {
            return 'de';
        }

        if ($enHits >= $deHits + 2) {
            return 'en';
        }

        return 'unknown';
    }

    /**
     * @param  array<string,int>  $filterStats
     */
    private function buildCountMismatchReason(
        bool $countMismatchDetected,
        int $countDeltaDetectedVsPersisted,
        int $countDeltaNormalizedVsPersisted,
        int $countDeltaValidatedVsPersisted,
        array $filterStats,
        int $validationErrorCount,
        int $validationWarningCount,
    ): ?string {
        if (! $countMismatchDetected) {
            return null;
        }

        $reasons = [];
        if ($countDeltaDetectedVsPersisted !== 0) {
            $reasons[] = 'detected_vs_persisted_delta:'.$countDeltaDetectedVsPersisted;
        }
        if ($countDeltaNormalizedVsPersisted !== 0) {
            $reasons[] = 'normalized_vs_persisted_delta:'.$countDeltaNormalizedVsPersisted;
        }
        if ($countDeltaValidatedVsPersisted !== 0) {
            $reasons[] = 'validated_vs_persisted_delta:'.$countDeltaValidatedVsPersisted;
        }
        if ((int) ($filterStats['toc_duplicates_removed_count'] ?? 0) > 0) {
            $reasons[] = 'toc_duplicates_filtered_before_persist';
        }
        if ((int) ($filterStats['bibliography_entry_lines_filtered_count'] ?? 0) > 0) {
            $reasons[] = 'bibliography_entries_filtered_before_persist';
        }
        if ((int) ($filterStats['figure_index_entry_lines_filtered_count'] ?? 0) > 0) {
            $reasons[] = 'figure_index_entries_filtered_before_persist';
        }
        if ($validationErrorCount > 0 || $validationWarningCount > 0) {
            $reasons[] = 'validation_constraints_applied';
        }

        return implode('; ', array_values(array_unique($reasons)));
    }

    /**
     * @param  array<int, int>  $values
     */
    private function median(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        sort($values);
        $count = count($values);
        $mid = (int) floor($count / 2);
        if ($count % 2 === 0) {
            return round(($values[$mid - 1] + $values[$mid]) / 2, 2);
        }

        return round((float) $values[$mid], 2);
    }

    private function clampScore(float $score): float
    {
        return round(max(0.0, min(1.0, $score)), 4);
    }

    /**
     * @return array<int, array{
     *   section_key:string,
     *   parent_key:string|null,
     *   section_type:string,
     *   section_title:string|null,
     *   extracted_text:string,
     *   hierarchy_level:int|null,
     *   start_line:int|null,
     *   end_line:int|null,
     *   start_page:int|null,
     *   end_page:int|null,
     *   anchor:array<string,mixed>,
     *   metadata:array<string,mixed>
     * }>
     */
    private function fallbackSection(string $text): array
    {
        return [[
            'section_key' => 'section-1',
            'parent_key' => null,
            'section_type' => 'other_section',
            'section_title' => 'Dokument',
            'extracted_text' => trim($text),
            'hierarchy_level' => 1,
            'start_line' => 1,
            'end_line' => max(1, substr_count($text, "\n") + 1),
            'start_page' => substr_count($text, "\f") > 0 ? 1 : null,
            'end_page' => substr_count($text, "\f") > 0 ? max(1, substr_count($text, "\f") + 1) : null,
            'anchor' => [
                'line_start' => 1,
            ],
            'metadata' => [
                'fallback' => true,
            ],
        ]];
    }

    private function normalizeSectionTitle(mixed $value): ?string
    {
        $title = trim((string) $value);
        if ($title === '') {
            return null;
        }

        return mb_substr($title, 0, 255);
    }

    private function normalizeExtractedText(string $text): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $text);
        $value = preg_replace("/\n{3,}/", "\n\n", $value) ?? $value;

        return trim((string) $value);
    }

    private function normalizeHierarchyLevel(mixed $value): ?int
    {
        $normalized = $this->normalizeIntOrNull($value);
        if ($normalized === null || $normalized <= 0) {
            return null;
        }

        return min(9, $normalized);
    }

    private function normalizeIntOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $integer = (int) $value;
        if ($integer <= 0) {
            return null;
        }

        return $integer;
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function logDebug(string $message, array $context = []): void
    {
        try {
            Log::channel('aba-run-debug')->debug($message, $context);
        } catch (\Throwable) {
            Log::debug($message, $context);
        }
    }
}
