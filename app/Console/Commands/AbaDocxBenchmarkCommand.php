<?php

namespace App\Console\Commands;

use App\Models\Aba;
use App\Models\AbaAnalysisResult;
use App\Models\AbaAnalysisRun;
use App\Models\AbaAttachment;
use App\Services\AbaAnalysisService;
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

    public function handle(AbaAnalysisService $analysisService): int
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
                'analysis_quality_score',
                'structure_quality_score',
                'heading_assignment_confidence',
                'hierarchy_confidence',
                'frontmatter_boundary_confidence',
                'body_reentry_confidence',
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
                analysisService: $analysisService,
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
    private function evaluateBenchmarkDocument(array $documentDefinition, AbaAnalysisService $analysisService, bool $runFreshAnalysis, float $regressionThreshold): array
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

        [$currentRun, $baselineRun] = $this->resolveRuns($aba->id, $attachment->id, $analysisService, $attachment, $aba, $runFreshAnalysis);

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
    private function resolveRuns(int $abaId, int $attachmentId, AbaAnalysisService $analysisService, AbaAttachment $attachment, Aba $aba, bool $runFreshAnalysis): array
    {
        $completedRuns = AbaAnalysisRun::query()
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
            'status_message' => 'Benchmark rerun started.',
            'source_original_name' => (string) ($attachment->original_name ?? ''),
            'source_path' => (string) ($attachment->path ?? ''),
            'source_mime_type' => (string) ($attachment->mime_type ?? ''),
            'started_at' => now(),
        ]);

        $analysisService->processRun($newRun->id);
        $newRun->refresh();

        return [$newRun, $latestCompleted];
    }

    /**
     * @return array<string,mixed>
     */
    private function collectRunSnapshot(AbaAnalysisRun $run): array
    {
        $summary = is_array($run->summary) ? $run->summary : [];
        $stats = is_array($summary['analysis_stats'] ?? null) ? $summary['analysis_stats'] : [];

        $resultRows = AbaAnalysisResult::query()
            ->where('aba_analysis_run_id', $run->id)
            ->get(['section_type', 'section_title', 'start_line', 'end_line']);

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

        $metrics = [];
        foreach ($this->metricKeys as $metricKey) {
            $metrics[$metricKey] = $this->toFloat($stats[$metricKey] ?? null);
        }

        $errorTaxonomy = [
            'wrong_parent_attachment' => $this->toInt($stats['hierarchy_wrong_parent_attachment_count'] ?? null),
            'missing_parent' => $this->toInt($stats['hierarchy_missing_parent_count'] ?? null),
            'level_too_deep_or_jump' => $this->toInt($stats['hierarchy_impossible_level_jump_count'] ?? null),
            'flattened_incorrectly' => $this->toInt($stats['hierarchy_flattened_count'] ?? null),
            'boundary_related_detachment' => $this->toInt($stats['hierarchy_boundary_detachment_count'] ?? null),
            'numbering_mismatch' => $this->toInt($stats['hierarchy_numbering_mismatch_count'] ?? null),
            'unnumbered_heading_ambiguity' => $this->toInt($stats['hierarchy_uncertain_parent_count'] ?? null),
        ];

        return [
            'run_id' => $run->id,
            'created_at' => optional($run->created_at)->toIso8601String(),
            'completed_at' => optional($run->completed_at)->toIso8601String(),
            'status' => $run->status,
            'metrics' => $metrics,
            'analysis_stats' => [
                'title_page_year' => $stats['title_page_year'] ?? null,
                'title_page_advisor' => $stats['title_page_advisor'] ?? null,
                'chapter_count' => $this->toInt($stats['chapter_count'] ?? null),
                'subchapter_count' => $this->toInt($stats['subchapter_count'] ?? null),
                'table_of_contents_count' => $this->toInt($stats['table_of_contents_count'] ?? null),
                'toc_special_entries_count' => $this->toInt($stats['toc_special_entries_count'] ?? null),
                'unresolved_heading_candidates_count' => $this->toInt($stats['unresolved_heading_candidates_count'] ?? null),
                'toc_candidates_rejected_count' => $this->toInt($stats['toc_candidates_rejected_count'] ?? null),
                'context_rejected_candidates_count' => $this->toInt($stats['context_rejected_candidates_count'] ?? null),
            ],
            'section_type_counts' => $sectionTypeCounts,
            'feature_flags' => [
                'has_title_page' => (bool) ($stats['title_page_detected'] ?? false),
                'has_abstract' => (bool) ($stats['abstract_detected'] ?? false),
                'has_toc' => $this->toInt($stats['table_of_contents_count'] ?? null) > 0,
                'has_bibliography' => (bool) ($stats['bibliography_detected'] ?? false),
                'has_figure_index' => (bool) ($stats['figure_index_detected'] ?? false),
                'has_consent_declaration' => (bool) ($stats['consent_declaration_detected'] ?? false),
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
                'heading' => $this->formatMetric($metrics['heading_assignment_confidence'] ?? null),
                'hierarchy' => $this->formatMetric($metrics['hierarchy_confidence'] ?? null),
                'regression' => (bool) ($report['has_regression'] ?? false) ? 'yes' : 'no',
            ];
        }

        $this->table(['key', 'aba', 'status', 'run', 'heading_conf', 'hierarchy_conf', 'regression'], $rows);

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
