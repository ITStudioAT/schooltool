<?php

namespace App\Console\Commands;

use App\Models\Aba;
use App\Models\AbaAnalysisResult;
use App\Models\AbaAnalysisRun;
use App\Models\AbaAttachment;
use App\Services\AbaDocumentExtractionService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class AbaDocxBenchmarkCommand extends Command
{
    protected $signature = 'aba:benchmark-docx
                            {--run : Start a fresh analysis run for each benchmark document}
                            {--aba=* : Restrict execution to one or more ABA IDs}
                            {--output= : Relative or absolute JSON report path}
                            {--fail-on-regression : Exit with failure when metric regressions are detected}
                            {--regression-threshold= : Override metric regression threshold (default from config)}
                            {--pretty : Print the full JSON report to stdout}';

    protected $description = 'Runs and reports the local DOCX ABA benchmark suite for regression tracking.';

    /**
     * @var array<int, string>
     */
    private array $metricKeys = [];

    public function handle(AbaDocumentExtractionService $extractionService): int
    {
        $documents = config('aba_docx_benchmark.documents', []);
        if (! is_array($documents) || $documents === []) {
            $this->error('No benchmark documents configured in config/aba_docx_benchmark.php');

            return self::FAILURE;
        }

        $taxonomy = config('aba_docx_benchmark.taxonomy', []);
        if (! is_array($taxonomy)) {
            $taxonomy = [];
        }

        $this->metricKeys = array_values(array_filter(array_map(
            fn ($key): string => (string) $key,
            Arr::wrap(config('aba_docx_benchmark.metric_keys', []))
        )));
        if ($this->metricKeys === []) {
            $this->metricKeys = [
                'required_section_coverage',
                'matched_section_ratio',
                'confident_match_ratio',
                'recognized_block_ratio',
            ];
        }

        $requestedAbaIds = array_values(array_unique(array_filter(
            array_map(fn ($value): int => (int) $value, Arr::wrap($this->option('aba'))),
            fn (int $value): bool => $value > 0
        )));

        $selectedDocuments = array_values(array_filter(
            $documents,
            fn (array $document): bool => $requestedAbaIds === [] || in_array((int) ($document['aba_id'] ?? 0), $requestedAbaIds, true)
        ));

        if ($selectedDocuments === []) {
            $this->warn('No matching benchmark documents after applying --aba filter.');

            return self::SUCCESS;
        }

        $runFreshAnalysis = (bool) $this->option('run');
        $regressionThreshold = $this->resolveRegressionThreshold();

        $this->info(sprintf(
            'Running DOCX benchmark for %d document(s) (%s mode).',
            count($selectedDocuments),
            $runFreshAnalysis ? 'rerun' : 'latest-run'
        ));

        $documentReports = [];
        foreach ($selectedDocuments as $documentDefinition) {
            $documentReports[] = $this->evaluateBenchmarkDocument(
                documentDefinition: $documentDefinition,
                extractionService: $extractionService,
                runFreshAnalysis: $runFreshAnalysis,
                regressionThreshold: $regressionThreshold,
            );
        }

        $coverage = $this->buildCoverageReport($selectedDocuments, $taxonomy);
        $summary = $this->buildSummary($documentReports, $coverage, $runFreshAnalysis, $regressionThreshold);

        $report = [
            'generated_at' => now()->toIso8601String(),
            'mode' => $runFreshAnalysis ? 'rerun' : 'latest_run',
            'regression_threshold' => $regressionThreshold,
            'taxonomy' => $taxonomy,
            'coverage' => $coverage,
            'documents' => $documentReports,
            'summary' => $summary,
            'recommended_minimum_suite' => config('aba_docx_benchmark.minimum_useful_suite', []),
            'missing_class_recommendations' => config('aba_docx_benchmark.missing_class_recommendations', []),
            'regression_protocol' => config('aba_docx_benchmark.regression_protocol', []),
            'evaluation_report_format' => config('aba_docx_benchmark.evaluation_report_format', []),
        ];

        $outputPath = $this->resolveOutputPath();
        $this->writeReport($outputPath, $report);
        $this->renderConsoleSummary($documentReports, $summary, $outputPath);

        if ((bool) $this->option('pretty')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        if ((bool) $this->option('fail-on-regression') && ((int) ($summary['regression_document_count'] ?? 0)) > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string,mixed>  $documentDefinition
     * @return array<string,mixed>
     */
    private function evaluateBenchmarkDocument(array $documentDefinition, AbaDocumentExtractionService $extractionService, bool $runFreshAnalysis, float $regressionThreshold): array
    {
        $abaId = (int) ($documentDefinition['aba_id'] ?? 0);
        $attachmentId = (int) ($documentDefinition['attachment_id'] ?? 0);

        $aba = Aba::query()->find($abaId);
        if (! $aba) {
            return [
                'key' => (string) ($documentDefinition['key'] ?? ('aba_'.$abaId)),
                'label' => (string) ($documentDefinition['label'] ?? ('ABA '.$abaId)),
                'aba_id' => $abaId,
                'attachment_id' => $attachmentId > 0 ? $attachmentId : null,
                'status' => 'missing_aba',
                'error' => 'ABA not found',
                'characteristics' => array_values(array_map('strval', Arr::wrap($documentDefinition['characteristics'] ?? []))),
            ];
        }

        $attachment = null;
        if ($attachmentId > 0) {
            $attachment = AbaAttachment::query()->find($attachmentId);
        }
        if (! $attachment) {
            $attachment = $aba->mainDocument()->first();
        }

        if (! $attachment) {
            return [
                'key' => (string) ($documentDefinition['key'] ?? ('aba_'.$abaId)),
                'label' => (string) ($documentDefinition['label'] ?? ('ABA '.$abaId)),
                'aba_id' => $abaId,
                'attachment_id' => $attachmentId > 0 ? $attachmentId : null,
                'status' => 'missing_attachment',
                'error' => 'Main DOCX attachment not found',
                'characteristics' => array_values(array_map('strval', Arr::wrap($documentDefinition['characteristics'] ?? []))),
            ];
        }

        [$currentRun, $baselineRun] = $this->resolveRuns($aba->id, $attachment->id, $extractionService, $attachment, $aba, $runFreshAnalysis);

        if (! $currentRun || $currentRun->status !== AbaAnalysisRun::STATUS_COMPLETED) {
            return [
                'key' => (string) ($documentDefinition['key'] ?? ('aba_'.$abaId)),
                'label' => (string) ($documentDefinition['label'] ?? ('ABA '.$abaId)),
                'aba_id' => $abaId,
                'attachment_id' => $attachment->id,
                'attachment_name' => (string) ($attachment->original_name ?? ''),
                'status' => 'run_unavailable',
                'error' => 'No completed run available',
                'characteristics' => array_values(array_map('strval', Arr::wrap($documentDefinition['characteristics'] ?? []))),
            ];
        }

        $currentSnapshot = $this->collectRunSnapshot($currentRun);
        $baselineSnapshot = $baselineRun ? $this->collectRunSnapshot($baselineRun) : null;

        $metricDeltas = $this->computeMetricDeltas(
            is_array($baselineSnapshot['metrics'] ?? null) ? $baselineSnapshot['metrics'] : null,
            is_array($currentSnapshot['metrics'] ?? null) ? $currentSnapshot['metrics'] : null,
        );

        $regressionMetrics = array_values(array_filter(
            array_keys($metricDeltas),
            fn (string $metric): bool => (float) ($metricDeltas[$metric] ?? 0.0) < -$regressionThreshold
        ));

        return [
            'key' => (string) ($documentDefinition['key'] ?? ('aba_'.$abaId)),
            'label' => (string) ($documentDefinition['label'] ?? ('ABA '.$abaId)),
            'aba_id' => $abaId,
            'attachment_id' => $attachment->id,
            'attachment_name' => (string) ($attachment->original_name ?? ''),
            'status' => 'ok',
            'characteristics' => array_values(array_map('strval', Arr::wrap($documentDefinition['characteristics'] ?? []))),
            'known_strengths' => array_values(array_map('strval', Arr::wrap($documentDefinition['known_strengths'] ?? []))),
            'known_weaknesses' => array_values(array_map('strval', Arr::wrap($documentDefinition['known_weaknesses'] ?? []))),
            'current_run' => $currentSnapshot,
            'baseline_run' => $baselineSnapshot,
            'metric_deltas' => $metricDeltas,
            'regression_metrics' => $regressionMetrics,
            'has_regression' => $regressionMetrics !== [],
        ];
    }

    /**
     * @return array{0:AbaAnalysisRun|null,1:AbaAnalysisRun|null}
     */
    private function resolveRuns(int $abaId, int $attachmentId, AbaDocumentExtractionService $extractionService, AbaAttachment $attachment, Aba $aba, bool $runFreshAnalysis): array
    {
        $completedRuns = AbaAnalysisRun::query()
            ->conventionalExtraction()
            ->where('aba_id', $abaId)
            ->where('aba_attachment_id', $attachmentId)
            ->where('status', AbaAnalysisRun::STATUS_COMPLETED)
            ->orderByDesc('id')
            ->limit(2)
            ->get();

        $latestCompleted = $completedRuns->get(0);
        $previousCompleted = $completedRuns->get(1);

        if (! $runFreshAnalysis) {
            return [$latestCompleted, $previousCompleted];
        }

        $newRun = AbaAnalysisRun::query()->create([
            'aba_id' => $aba->id,
            'aba_attachment_id' => $attachment->id,
            'created_by_user_id' => $this->resolveRunCreatorId($aba, $attachment),
            'status' => AbaAnalysisRun::STATUS_STARTED,
            'status_message' => AbaAnalysisRun::EXTRACTION_STATUS_MESSAGE_PREFIX.' benchmark started.',
            'source_original_name' => (string) ($attachment->original_name ?? ''),
            'source_path' => (string) ($attachment->path ?? ''),
            'source_mime_type' => (string) ($attachment->mime_type ?? ''),
            'started_at' => now(),
        ]);

        $extractionService->processRun($newRun->id);
        $newRun->refresh();

        return [$newRun, $latestCompleted];
    }

    /**
     * @return array<string,mixed>
     */
    private function collectRunSnapshot(AbaAnalysisRun $run): array
    {
        $summary = is_array($run->summary) ? $run->summary : [];
        $resultRows = AbaAnalysisResult::query()
            ->where('aba_analysis_run_id', $run->id)
            ->get(['section_type', 'section_title', 'hierarchy_level', 'start_line', 'end_line', 'metadata']);

        $sectionTypeCounts = $resultRows
            ->groupBy('section_type')
            ->map(fn ($group): int => $group->count())
            ->all();

        $chapterTitles = $resultRows
            ->where('section_type', 'chapter')
            ->pluck('section_title')
            ->filter(fn ($title): bool => is_string($title) && trim($title) !== '')
            ->map(fn ($title): string => trim((string) $title))
            ->values()
            ->all();

        $subchapterTitles = $resultRows
            ->where('section_type', 'subchapter')
            ->pluck('section_title')
            ->filter(fn ($title): bool => is_string($title) && trim($title) !== '')
            ->map(fn ($title): string => trim((string) $title))
            ->values()
            ->all();

        $hasUnnumberedChapters = collect($chapterTitles)
            ->contains(fn (string $title): bool => preg_match('/^\s*\d+(?:\.\d+)*\.?\s+/u', $title) !== 1);

        $hasNumberedSubchapters = collect($subchapterTitles)
            ->contains(fn (string $title): bool => preg_match('/^\s*\d+\.\d+/u', $title) === 1);

        $longSubsectionsCount = $resultRows
            ->where('section_type', 'subchapter')
            ->filter(function ($row): bool {
                $start = (int) ($row->start_line ?? 0);
                $end = (int) ($row->end_line ?? 0);

                return $start > 0 && $end >= $start && ($end - $start) >= 20;
            })
            ->count();

        $foundRequiredSectionKeys = $this->stringList($summary['found_required_section_keys'] ?? []);
        $missingRequiredSectionKeys = $this->stringList($summary['missing_required_section_keys'] ?? []);
        $foundOptionalSectionKeys = $this->stringList($summary['found_optional_section_keys'] ?? []);
        $uncertainMatches = is_array($summary['uncertain_matches'] ?? null) ? array_values($summary['uncertain_matches']) : [];
        $unmatchedBlocksCount = $this->toInt($summary['unmatched_blocks_count'] ?? null);
        $matchedRuleKeys = $resultRows
            ->flatMap(fn ($row): array => $this->stringList(data_get($row->metadata, 'matched_rule_keys', [])))
            ->unique()
            ->values();
        $matchedSectionCount = $resultRows
            ->filter(fn ($row): bool => $this->stringList(data_get($row->metadata, 'matched_rule_keys', [])) !== [])
            ->count();
        $requiredSectionCount = count($foundRequiredSectionKeys) + count($missingRequiredSectionKeys);
        $resultCount = $resultRows->count();

        $availableMetrics = [
            'required_section_coverage' => $this->ratio(count($foundRequiredSectionKeys), $requiredSectionCount),
            'matched_section_ratio' => $this->ratio($matchedSectionCount, $resultCount),
            'confident_match_ratio' => $matchedRuleKeys->isEmpty()
                ? null
                : max(0.0, 1.0 - (count($uncertainMatches) / $matchedRuleKeys->count())),
            'recognized_block_ratio' => $this->ratio($matchedSectionCount, $matchedSectionCount + $unmatchedBlocksCount),
        ];
        $metrics = [];
        foreach ($this->metricKeys as $metricKey) {
            $metrics[$metricKey] = $this->toFloat($availableMetrics[$metricKey] ?? null);
        }

        $errorTaxonomy = [
            'missing_required_sections' => count($missingRequiredSectionKeys),
            'uncertain_matches' => count($uncertainMatches),
            'unmatched_blocks' => $unmatchedBlocksCount,
        ];

        return [
            'run_id' => $run->id,
            'created_at' => optional($run->created_at)->toIso8601String(),
            'completed_at' => optional($run->completed_at)->toIso8601String(),
            'status' => $run->status,
            'metrics' => $metrics,
            'extraction_stats' => [
                'found_required_section_count' => count($foundRequiredSectionKeys),
                'missing_required_section_count' => count($missingRequiredSectionKeys),
                'found_optional_section_count' => count($foundOptionalSectionKeys),
                'matched_section_count' => $matchedSectionCount,
                'unmatched_blocks_count' => $unmatchedBlocksCount,
                'uncertain_matches_count' => count($uncertainMatches),
            ],
            'section_type_counts' => $sectionTypeCounts,
            'feature_flags' => [
                'has_title_page' => ($sectionTypeCounts['title_page'] ?? 0) > 0,
                'has_abstract' => ($sectionTypeCounts['abstract'] ?? 0) > 0,
                'has_toc' => ($sectionTypeCounts['table_of_contents'] ?? 0) > 0,
                'has_bibliography' => ($sectionTypeCounts['bibliography'] ?? 0) > 0,
                'has_figure_index' => ($sectionTypeCounts['figure_index'] ?? 0) > 0,
                'has_consent_declaration' => ($sectionTypeCounts['consent_declaration'] ?? 0) > 0,
                'has_numbered_subchapters' => $hasNumberedSubchapters,
                'has_unnumbered_chapters' => $hasUnnumberedChapters,
                'has_long_subsection_spans' => $longSubsectionsCount > 0,
                'long_subsection_spans_count' => $longSubsectionsCount,
            ],
            'error_taxonomy' => $errorTaxonomy,
        ];
    }

    /**
     * @param  array<string,float|null>|null  $baselineMetrics
     * @param  array<string,float|null>|null  $currentMetrics
     * @return array<string,float|null>
     */
    private function computeMetricDeltas(?array $baselineMetrics, ?array $currentMetrics): array
    {
        $deltas = [];
        foreach ($this->metricKeys as $metricKey) {
            $baseline = $baselineMetrics[$metricKey] ?? null;
            $current = $currentMetrics[$metricKey] ?? null;
            if ($baseline === null || $current === null) {
                $deltas[$metricKey] = null;

                continue;
            }

            $deltas[$metricKey] = round($current - $baseline, 4);
        }

        return $deltas;
    }

    /**
     * @param  array<int,array<string,mixed>>  $documents
     * @param  array<string,string>  $taxonomy
     * @return array<string,mixed>
     */
    private function buildCoverageReport(array $documents, array $taxonomy): array
    {
        $coverageMap = [];
        foreach ($documents as $document) {
            $documentKey = (string) ($document['key'] ?? ('aba_'.(int) ($document['aba_id'] ?? 0)));
            foreach (array_values(array_map('strval', Arr::wrap($document['characteristics'] ?? []))) as $classKey) {
                $coverageMap[$classKey][] = $documentKey;
            }
        }

        foreach ($coverageMap as $classKey => $documentKeys) {
            $coverageMap[$classKey] = array_values(array_unique($documentKeys));
        }

        $missingClasses = [];
        foreach (array_keys($taxonomy) as $classKey) {
            if (! isset($coverageMap[$classKey]) || $coverageMap[$classKey] === []) {
                $missingClasses[] = (string) $classKey;
            }
        }

        return [
            'covered_classes' => $coverageMap,
            'missing_classes' => $missingClasses,
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $documentReports
     * @param  array<string,mixed>  $coverage
     * @return array<string,mixed>
     */
    private function buildSummary(array $documentReports, array $coverage, bool $runFreshAnalysis, float $regressionThreshold): array
    {
        $okDocuments = array_values(array_filter(
            $documentReports,
            fn (array $report): bool => (string) ($report['status'] ?? '') === 'ok'
        ));

        $regressionDocuments = array_values(array_filter(
            $okDocuments,
            fn (array $report): bool => (bool) ($report['has_regression'] ?? false)
        ));

        $changedDocuments = array_values(array_filter($okDocuments, function (array $report): bool {
            $deltas = is_array($report['metric_deltas'] ?? null) ? $report['metric_deltas'] : [];
            foreach ($deltas as $delta) {
                if (is_float($delta) || is_int($delta)) {
                    if (abs((float) $delta) > 0.0001) {
                        return true;
                    }
                }
            }

            return false;
        }));

        $failedDocuments = array_values(array_filter(
            $documentReports,
            fn (array $report): bool => (string) ($report['status'] ?? '') !== 'ok'
        ));

        $aggregateMetrics = [];
        foreach ($this->metricKeys as $metricKey) {
            $values = [];
            foreach ($okDocuments as $report) {
                $currentRun = is_array($report['current_run'] ?? null) ? $report['current_run'] : [];
                $metrics = is_array($currentRun['metrics'] ?? null) ? $currentRun['metrics'] : [];
                $value = $metrics[$metricKey] ?? null;
                if (is_float($value) || is_int($value)) {
                    $values[] = (float) $value;
                }
            }

            $aggregateMetrics[$metricKey] = $values === []
                ? null
                : round(array_sum($values) / count($values), 4);
        }

        return [
            'document_count' => count($documentReports),
            'ok_document_count' => count($okDocuments),
            'failed_document_count' => count($failedDocuments),
            'changed_document_count' => count($changedDocuments),
            'unchanged_document_count' => max(0, count($okDocuments) - count($changedDocuments)),
            'regression_document_count' => count($regressionDocuments),
            'run_mode' => $runFreshAnalysis ? 'rerun' : 'latest_run',
            'regression_threshold' => $regressionThreshold,
            'average_metrics' => $aggregateMetrics,
            'coverage_missing_classes' => array_values(array_map('strval', Arr::wrap($coverage['missing_classes'] ?? []))),
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $documentReports
     * @param  array<string,mixed>  $summary
     */
    private function renderConsoleSummary(array $documentReports, array $summary, string $outputPath): void
    {
        $rows = [];
        foreach ($documentReports as $report) {
            $status = (string) ($report['status'] ?? 'unknown');
            $currentRun = is_array($report['current_run'] ?? null) ? $report['current_run'] : [];
            $metrics = is_array($currentRun['metrics'] ?? null) ? $currentRun['metrics'] : [];
            $rows[] = [
                'key' => (string) ($report['key'] ?? ''),
                'aba' => (string) ($report['aba_id'] ?? ''),
                'status' => $status,
                'run' => (string) ($currentRun['run_id'] ?? '-'),
                'required' => $this->formatMetric($metrics['required_section_coverage'] ?? null),
                'matched' => $this->formatMetric($metrics['matched_section_ratio'] ?? null),
                'regression' => (bool) ($report['has_regression'] ?? false) ? 'yes' : 'no',
            ];
        }

        $this->table(['key', 'aba', 'status', 'run', 'required_coverage', 'matched_ratio', 'regression'], $rows);

        $this->info(sprintf(
            'Summary: %d ok, %d failed, %d regressions, %d changed docs.',
            (int) ($summary['ok_document_count'] ?? 0),
            (int) ($summary['failed_document_count'] ?? 0),
            (int) ($summary['regression_document_count'] ?? 0),
            (int) ($summary['changed_document_count'] ?? 0),
        ));

        $missingCoverage = array_values(array_map('strval', Arr::wrap($summary['coverage_missing_classes'] ?? [])));
        if ($missingCoverage !== []) {
            $this->warn('Missing taxonomy classes: '.implode(', ', $missingCoverage));
        }

        $this->info('Report saved to: '.$outputPath);
    }

    private function resolveOutputPath(): string
    {
        $outputOption = trim((string) $this->option('output'));
        if ($outputOption !== '') {
            if (str_starts_with($outputOption, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\\/]/', $outputOption) === 1) {
                return $outputOption;
            }

            return base_path($outputOption);
        }

        return storage_path('app/aba-benchmarks/docx-benchmark-'.now()->format('Ymd-His').'.json');
    }

    /**
     * @param  array<string,mixed>  $report
     */
    private function writeReport(string $outputPath, array $report): void
    {
        $directory = dirname($outputPath);
        if (! is_dir($directory)) {
            File::ensureDirectoryExists($directory);
        }

        file_put_contents($outputPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function resolveRegressionThreshold(): float
    {
        $configured = (float) config('aba_docx_benchmark.regression_threshold', 0.01);
        $override = $this->option('regression-threshold');
        if ($override !== null && $override !== '') {
            $configured = (float) $override;
        }

        return max(0.0001, $configured);
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_float($value) || is_int($value)) {
            return round((float) $value, 4);
        }

        if (is_numeric($value)) {
            return round((float) $value, 4);
        }

        return null;
    }

    private function toInt(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }

    /**
     * @return array<int,string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn (mixed $item): string => trim((string) $item), $value),
            fn (string $item): bool => $item !== '',
        ));
    }

    private function ratio(int $numerator, int $denominator): ?float
    {
        if ($denominator <= 0) {
            return null;
        }

        return round($numerator / $denominator, 6);
    }

    private function formatMetric(mixed $value): string
    {
        $floatValue = $this->toFloat($value);
        if ($floatValue === null) {
            return '-';
        }

        return number_format($floatValue, 4, '.', '');
    }

    private function resolveRunCreatorId(Aba $aba, AbaAttachment $attachment): ?int
    {
        $abaUserId = is_numeric($aba->user_id) ? (int) $aba->user_id : null;
        if ($abaUserId !== null && $abaUserId > 0) {
            return $abaUserId;
        }

        $uploadedByUserId = is_numeric($attachment->uploaded_by_user_id) ? (int) $attachment->uploaded_by_user_id : null;
        if ($uploadedByUserId !== null && $uploadedByUserId > 0) {
            return $uploadedByUserId;
        }

        return null;
    }
}
