<?php

namespace App\Http\Controllers\Admin\ABA;

use App\ABA\Services\BuildSeedDiffView;
use App\ABA\Services\CheckSeedFreshness;
use App\ABA\Services\ProposeSeedUpdate;
use App\ABA\Services\ReadFreshnessResults;
use App\ABA\Services\ReadOpenClaims;
use App\ABA\Services\ReadSeedHardeningStatus;
use App\ABA\Services\ReadSeedReportMeta;
use App\ABA\Services\ReadSeedReviewState;
use App\ABA\Services\ReadSeedSourceRegistry;
use App\ABA\Services\RebuildKnowledgeBaseFromSeed;
use App\ABA\Services\RunOnlineFreshnessCheck;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ABA\AbaPandocDebugRunRequest;
use App\Http\Requests\Admin\ABA\AbaPdfOpenAiDebugRequest;
use App\Models\AbaAttachment;
use App\Services\AbaDocumentRuleService;
use App\Services\AbaExtractionPathComparisonService;
use App\Services\AbaLocalDocumentStructureExtractor;
use App\Services\AbaLocalDocumentTextExtractor;
use App\Services\AbaPandocAstNormalizerService;
use App\Services\AbaPandocDocxExtractionService;
use App\Services\AbaPandocReviewBuilderService;
use App\Services\AbaPdfOpenAiDebugService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Admin-API für die KI-Einstellungen-Seite der ABA-Wissensbasis.
 *
 * Nur für Rollen admin und super_admin zugänglich (über Middleware in api.php).
 */
class AbaAiSettingsController extends Controller
{
    public function index(
        ReadSeedReportMeta $seedMeta,
        ReadSeedReviewState $reviewState,
        ReadSeedSourceRegistry $sourceRegistry,
        ReadOpenClaims $openClaims,
        ReadFreshnessResults $freshnessResults,
        ReadSeedHardeningStatus $hardeningStatus,
        BuildSeedDiffView $seedDiffView,
        AbaDocumentRuleService $documentRuleService,
    ): JsonResponse {
        return response()->json([
            'seed_report' => $seedMeta->read(),
            'review_state' => $reviewState->read(),
            'source_registry' => $sourceRegistry->read(),
            'open_claims' => $openClaims->read(),
            'freshness_results' => $freshnessResults->read(),
            'hardening_status' => $hardeningStatus->read(),
            'proposals' => $seedDiffView->listDiffableProposals(),
            'document_rule_base' => $documentRuleService->summary(),
        ]);
    }

    public function checkFreshness(CheckSeedFreshness $service): JsonResponse
    {
        try {
            $report = $service->check();

            return response()->json([
                'success' => true,
                'report' => $report,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Prüfung fehlgeschlagen: '.$e->getMessage(),
            ], 500);
        }
    }

    public function checkFreshnessOnline(RunOnlineFreshnessCheck $service): JsonResponse
    {
        try {
            $result = $service->run();

            return response()->json([
                'success' => true,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Online-Prüfung fehlgeschlagen: '.$e->getMessage(),
            ], 500);
        }
    }

    public function proposeSeedUpdate(CheckSeedFreshness $freshness, ProposeSeedUpdate $proposer): JsonResponse
    {
        try {
            $report = $freshness->check();
            $path = $proposer->propose($report);

            return response()->json([
                'success' => true,
                'proposal_filename' => basename($path),
                'proposal_path' => 'ai/knowledge/aba/sources/proposals/'.basename($path),
                'report' => $report,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Vorschlagserstellung fehlgeschlagen: '.$e->getMessage(),
            ], 500);
        }
    }

    public function rebuildFromSeed(RebuildKnowledgeBaseFromSeed $service): JsonResponse
    {
        try {
            $result = $service->rebuild();

            return response()->json([
                'success' => true,
                'result' => [
                    'claims_count' => $result['claims_count'],
                    'chunks_count' => $result['chunks_count'],
                    'files_written_count' => count($result['files_written']),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Aufbau fehlgeschlagen: '.$e->getMessage(),
            ], 500);
        }
    }

    public function runPandocDebug(
        AbaPandocDebugRunRequest $request,
        AbaPandocDocxExtractionService $extractionService,
        AbaPandocAstNormalizerService $normalizerService,
        AbaLocalDocumentTextExtractor $localTextExtractor,
        AbaLocalDocumentStructureExtractor $localStructureExtractor,
        AbaExtractionPathComparisonService $comparisonService,
        AbaPandocReviewBuilderService $reviewBuilderService,
    ): JsonResponse {
        /** @var UploadedFile $uploadedFile */
        $uploadedFile = $request->file('file');
        if (! $uploadedFile->isValid()) {
            return response()->json([
                'success' => false,
                'stage' => 'upload',
                'message' => 'Die hochgeladene Datei konnte nicht verarbeitet werden.',
            ], 422);
        }

        $tempPath = $this->storeUploadedDocxTemporarily($uploadedFile);
        $legacyRelativePath = null;

        try {
            $extraction = $extractionService->extractFromPath($tempPath);
            if (($extraction['ok'] ?? false) !== true) {
                $errorType = (string) ($extraction['error']['type'] ?? 'extraction_failed');

                return response()->json([
                    'success' => false,
                    'stage' => 'extraction',
                    'message' => 'Die DOCX-Datei konnte nicht mit Pandoc extrahiert werden.',
                    'error' => $extraction['error'] ?? null,
                    'extraction' => [
                        'engine' => $extraction['engine'] ?? 'pandoc',
                        'runtime' => $extraction['runtime'] ?? [],
                        'metadata' => $extraction['metadata'] ?? [],
                        'warnings' => $extraction['warnings'] ?? [],
                    ],
                ], $this->statusCodeForExtractionError($errorType));
            }

            $normalization = $normalizerService->normalizeAst(
                is_array($extraction['ast'] ?? null)
                    ? $extraction['ast']
                    : []
            );

            if (($normalization['ok'] ?? false) !== true) {
                return response()->json([
                    'success' => false,
                    'stage' => 'normalization',
                    'message' => 'Die Pandoc-Ausgabe konnte nicht normalisiert werden.',
                    'error' => $normalization['error'] ?? null,
                ], 500);
            }

            $blocks = is_array($normalization['blocks'] ?? null)
                ? array_values($normalization['blocks'])
                : [];
            $review = $reviewBuilderService->buildReview($blocks);
            $summary = array_merge(
                $reviewBuilderService->buildSummary($blocks),
                [
                    'document_title_candidate_count' => (int) ($review['counts']['document_title_candidate_count'] ?? 0),
                    'empty_heading_count' => (int) ($review['counts']['empty_heading_count'] ?? 0),
                    'probable_toc_artifact_count' => (int) ($review['counts']['probable_toc_artifact_count'] ?? 0),
                    'suspicious_heading_count' => (int) ($review['counts']['suspicious_heading_count'] ?? 0),
                ]
            );
            $legacyPayload = [
                'ok' => false,
                'error' => 'Lokaler Vergleichspfad konnte nicht ausgeführt werden.',
            ];

            try {
                $legacyRelativePath = $this->storeUploadedDocxForLegacyComparison($uploadedFile);
                $legacyAttachment = new AbaAttachment;
                $legacyAttachment->disk = 'local';
                $legacyAttachment->path = $legacyRelativePath;
                $legacyAttachment->mime_type = (string) ($uploadedFile->getClientMimeType() ?? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
                $legacyAttachment->original_name = $uploadedFile->getClientOriginalName();

                $legacyExtraction = $localTextExtractor->extractDocument($legacyAttachment);
                $legacySections = $localStructureExtractor->extractSections(
                    (string) ($legacyExtraction['text'] ?? ''),
                    [
                        'outline' => is_array($legacyExtraction['outline'] ?? null) ? array_values($legacyExtraction['outline']) : [],
                        'toc_lines' => is_array($legacyExtraction['toc_lines'] ?? null) ? array_values($legacyExtraction['toc_lines']) : [],
                        'selected_candidate' => $legacyExtraction['selected_candidate'] ?? null,
                        'extraction_candidates' => is_array($legacyExtraction['candidates'] ?? null) ? array_values($legacyExtraction['candidates']) : [],
                    ]
                );

                $legacyPayload = [
                    'ok' => true,
                    'sections' => is_array($legacySections) ? array_values($legacySections) : [],
                    'diagnostics' => $localStructureExtractor->lastDiagnostics(),
                    'extraction' => [
                        'selected_candidate' => $legacyExtraction['selected_candidate'] ?? null,
                        'candidate_count' => is_array($legacyExtraction['candidates'] ?? null) ? count($legacyExtraction['candidates']) : 0,
                        'metadata' => is_array($legacyExtraction['metadata'] ?? null) ? $legacyExtraction['metadata'] : [],
                    ],
                ];
            } catch (\Throwable $legacyException) {
                $legacyPayload = [
                    'ok' => false,
                    'error' => 'Lokaler Vergleichspfad fehlgeschlagen: '.$legacyException->getMessage(),
                ];
            }

            $pandocPayload = [
                'ok' => true,
                'blocks' => $blocks,
                'model_version' => $normalization['model_version'] ?? null,
                'extraction_engine' => $extraction['engine'] ?? 'pandoc',
            ];
            $comparison = $comparisonService->compare($legacyPayload, $pandocPayload);

            return response()->json([
                'success' => true,
                'note' => 'Interne Prüfansicht für DOCX-Extraktion und Normalisierung. Keine finale ABA-Bewertung.',
                'document' => [
                    'original_name' => $uploadedFile->getClientOriginalName(),
                    'size_bytes' => $uploadedFile->getSize(),
                ],
                'summary' => $summary,
                'review' => $review,
                'comparison' => $comparison,
                'legacy_local' => [
                    'ok' => (bool) ($legacyPayload['ok'] ?? false),
                    'error' => $legacyPayload['error'] ?? null,
                    'diagnostics' => is_array($legacyPayload['diagnostics'] ?? null) ? $legacyPayload['diagnostics'] : [],
                    'extraction' => is_array($legacyPayload['extraction'] ?? null) ? $legacyPayload['extraction'] : [],
                ],
                'extraction' => [
                    'engine' => $extraction['engine'] ?? 'pandoc',
                    'format' => $extraction['format'] ?? null,
                    'runtime' => $extraction['runtime'] ?? [],
                    'metadata' => $extraction['metadata'] ?? [],
                    'warnings' => $extraction['warnings'] ?? [],
                ],
                'normalization' => [
                    'format' => $normalization['format'] ?? null,
                    'model_version' => $normalization['model_version'] ?? null,
                    'metadata' => $normalization['metadata'] ?? [],
                    'warnings' => $normalization['warnings'] ?? [],
                    'blocks' => $blocks,
                ],
            ]);
        } finally {
            @unlink($tempPath);
            if (is_string($legacyRelativePath) && $legacyRelativePath !== '') {
                Storage::disk('local')->delete($legacyRelativePath);
            }
        }
    }

    public function runPdfOpenAiDebug(
        AbaPdfOpenAiDebugRequest $request,
        AbaPdfOpenAiDebugService $service,
    ): JsonResponse {
        /** @var UploadedFile $uploadedFile */
        $uploadedFile = $request->file('file');
        if (! $uploadedFile->isValid()) {
            return response()->json([
                'success' => false,
                'error' => 'Die hochgeladene PDF-Datei konnte nicht verarbeitet werden.',
            ], 422);
        }

        $result = $service->analyze($uploadedFile);

        if (! ($result['success'] ?? false)) {
            return response()->json($result, 500);
        }

        return response()->json($result);
    }

    private function storeUploadedDocxTemporarily(UploadedFile $file): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'aba_pandoc_debug_');
        if (! is_string($tempPath) || $tempPath === '') {
            throw new \RuntimeException('Temporäre Datei konnte nicht erstellt werden.');
        }

        $docxPath = $tempPath.'.docx';
        @rename($tempPath, $docxPath);

        $content = @file_get_contents($file->getPathname());
        if (! is_string($content)) {
            throw new \RuntimeException('Upload-Inhalt konnte nicht gelesen werden.');
        }

        file_put_contents($docxPath, $content);

        return $docxPath;
    }

    private function storeUploadedDocxForLegacyComparison(UploadedFile $file): string
    {
        $relativePath = 'aba/pandoc-debug-uploads/'.Str::uuid().'.docx';
        $content = @file_get_contents($file->getPathname());
        if (! is_string($content)) {
            throw new \RuntimeException('Upload-Inhalt für lokalen Vergleich konnte nicht gelesen werden.');
        }

        Storage::disk('local')->put($relativePath, $content);

        return $relativePath;
    }

    /**
     * @param  array<int, array<string,mixed>>  $blocks
     * @return array<string,int>
     */
    private function buildPandocDebugSummary(array $blocks): array
    {
        $headingCount = 0;
        $imageCount = 0;
        $sectionHintCount = 0;
        $uncertainCount = 0;
        $zoneCounts = [];

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = (string) ($block['type'] ?? '');
            if ($type === 'heading') {
                $headingCount++;
            }
            if ($type === 'image') {
                $imageCount++;
            }
            if (is_array($block['section_hint'] ?? null)) {
                $sectionHintCount++;
            }
            $zoneKey = trim((string) ($block['document_zone']['zone'] ?? ''));
            if ($zoneKey !== '') {
                $zoneCounts[$zoneKey] = (int) ($zoneCounts[$zoneKey] ?? 0) + 1;
            }

            $classification = is_array($block['classification'] ?? null)
                ? $block['classification']
                : [];
            $confidence = (string) ($classification['confidence'] ?? '');
            $strategy = (string) ($classification['strategy'] ?? '');

            if ($confidence === 'low' || $strategy === 'heuristic') {
                $uncertainCount++;
            }
        }

        return [
            'normalized_block_count' => count($blocks),
            'heading_count' => $headingCount,
            'image_count' => $imageCount,
            'section_hint_count' => $sectionHintCount,
            'uncertain_or_heuristic_count' => $uncertainCount,
            'zone_count' => count($zoneCounts),
            'zone_title_page_count' => (int) ($zoneCounts['title_page'] ?? 0),
            'zone_front_matter_count' => (int) ($zoneCounts['front_matter'] ?? 0),
            'zone_table_of_contents_count' => (int) ($zoneCounts['table_of_contents'] ?? 0),
            'zone_main_content_count' => (int) ($zoneCounts['main_content'] ?? 0),
            'zone_bibliography_area_count' => (int) ($zoneCounts['bibliography_area'] ?? 0),
            'zone_appendix_area_count' => (int) ($zoneCounts['appendix_area'] ?? 0),
            'zone_declaration_area_count' => (int) ($zoneCounts['declaration_area'] ?? 0),
            'zone_end_matter_count' => (int) ($zoneCounts['end_matter'] ?? 0),
        ];
    }

    /**
     * @param  array<int, array<string,mixed>>  $blocks
     * @return array{
     *   recognized_main_sections:array<int, array<string,mixed>>,
     *   document_title_candidates:array<int, array<string,mixed>>,
     *   uncertain_headings:array<int, array<string,mixed>>,
     *   empty_or_problematic_headings:array<int, array<string,mixed>>,
     *   probable_toc_artifacts:array<int, array<string,mixed>>,
     *   suspicious_heading_texts:array<int, array<string,mixed>>,
     *   bibliography_groups:array<int, array<string,mixed>>,
     *   zone_overview:array<int, array<string,mixed>>,
     *   counts:array<string,int>
     * }
     */
    private function buildPandocDebugReview(array $blocks): array
    {
        $recognizedMainSections = [];
        $recognizedLookup = [];
        $documentTitleCandidates = [];
        $uncertainHeadings = [];
        $emptyHeadings = [];
        $probableTocArtifacts = [];
        $suspiciousHeadings = [];
        $bibliographyGroupAccumulator = [];
        $zoneOverviewAccumulator = [];
        $mainSectionTypes = [
            'title_page',
            'abstract',
            'foreword',
            'table_of_contents',
            'chapter',
            'bibliography',
            'figure_index',
            'consent_declaration',
        ];

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $zoneKey = trim((string) ($block['document_zone']['zone'] ?? ''));
            if ($zoneKey === '') {
                continue;
            }

            $zoneLabel = trim((string) ($block['document_zone']['label'] ?? '')) ?: $this->documentZoneLabel($zoneKey);
            $order = (int) ($block['order'] ?? 0);
            $zoneConfidence = trim((string) ($block['document_zone']['confidence'] ?? ''));

            if (! isset($zoneOverviewAccumulator[$zoneKey])) {
                $zoneOverviewAccumulator[$zoneKey] = [
                    'zone_key' => $zoneKey,
                    'zone_label' => $zoneLabel,
                    'count' => 0,
                    'heading_count' => 0,
                    'image_count' => 0,
                    'first_order' => $order > 0 ? $order : null,
                    'last_order' => $order > 0 ? $order : null,
                    'high_confidence_count' => 0,
                    'medium_confidence_count' => 0,
                    'low_confidence_count' => 0,
                ];
            }

            $zoneOverviewAccumulator[$zoneKey]['count']++;
            if ((string) ($block['type'] ?? '') === 'heading') {
                $zoneOverviewAccumulator[$zoneKey]['heading_count']++;
            }
            if ((string) ($block['type'] ?? '') === 'image') {
                $zoneOverviewAccumulator[$zoneKey]['image_count']++;
            }
            if ($order > 0) {
                $currentFirstOrder = $zoneOverviewAccumulator[$zoneKey]['first_order'];
                $currentLastOrder = $zoneOverviewAccumulator[$zoneKey]['last_order'];
                $zoneOverviewAccumulator[$zoneKey]['first_order'] = $currentFirstOrder === null ? $order : min((int) $currentFirstOrder, $order);
                $zoneOverviewAccumulator[$zoneKey]['last_order'] = $currentLastOrder === null ? $order : max((int) $currentLastOrder, $order);
            }
            if ($zoneConfidence === 'high') {
                $zoneOverviewAccumulator[$zoneKey]['high_confidence_count']++;
            } elseif ($zoneConfidence === 'medium') {
                $zoneOverviewAccumulator[$zoneKey]['medium_confidence_count']++;
            } else {
                $zoneOverviewAccumulator[$zoneKey]['low_confidence_count']++;
            }
        }

        foreach ($blocks as $block) {
            if (! is_array($block) || (string) ($block['type'] ?? '') !== 'heading') {
                continue;
            }

            $item = $this->toReviewHeadingItem($block);
            $sectionType = trim((string) ($item['section_type'] ?? ''));
            $problemTags = is_array($item['problem_tags'] ?? null)
                ? array_values(array_map('strval', $item['problem_tags']))
                : [];
            $confidence = (string) ($item['confidence'] ?? 'low');
            $strategy = (string) ($item['strategy'] ?? 'heuristic');
            $isUsable = (bool) ($item['is_usable_heading'] ?? false);
            $sectionGroup = trim((string) ($item['section_group'] ?? ''));
            $sectionSubtype = trim((string) ($item['section_subtype'] ?? ''));

            if (
                $sectionType !== ''
                && in_array($sectionType, $mainSectionTypes, true)
                && ! in_array('probable_toc_artifact', $problemTags, true)
                && ! in_array('document_title_candidate', $problemTags, true)
                && $isUsable
                && trim((string) ($item['text'] ?? '')) !== ''
            ) {
                $lookupKey = $sectionType.'|'.mb_strtolower((string) ($item['text'] ?? ''));
                if (! isset($recognizedLookup[$lookupKey])) {
                    $recognizedLookup[$lookupKey] = true;
                    $recognizedMainSections[] = $item;
                }
            }

            if (
                in_array($confidence, ['low', 'medium'], true)
                || $strategy === 'heuristic'
                || $problemTags !== []
                || ! $isUsable
            ) {
                $uncertainHeadings[] = $item;
            }

            if (in_array('document_title_candidate', $problemTags, true)) {
                $documentTitleCandidates[] = $item;
            }

            if (in_array('empty_heading', $problemTags, true)) {
                $emptyHeadings[] = $item;
            }

            if (in_array('probable_toc_artifact', $problemTags, true)) {
                $probableTocArtifacts[] = $item;
            }

            if (in_array('suspicious_heading_text', $problemTags, true)) {
                $suspiciousHeadings[] = $item;
            }

            if ($sectionGroup !== '') {
                $groupLabel = trim((string) ($item['section_group_label'] ?? '')) ?: $sectionGroup;
                $subtypeKey = $sectionSubtype !== '' ? $sectionSubtype : 'general';
                $subtypeLabel = trim((string) ($item['section_subtype_label'] ?? '')) ?: $subtypeKey;

                if (! isset($bibliographyGroupAccumulator[$sectionGroup])) {
                    $bibliographyGroupAccumulator[$sectionGroup] = [
                        'group_key' => $sectionGroup,
                        'group_label' => $groupLabel,
                        'subtypes' => [],
                    ];
                }

                if (! isset($bibliographyGroupAccumulator[$sectionGroup]['subtypes'][$subtypeKey])) {
                    $bibliographyGroupAccumulator[$sectionGroup]['subtypes'][$subtypeKey] = [
                        'subtype_key' => $subtypeKey,
                        'subtype_label' => $subtypeLabel,
                        'count' => 0,
                        'samples' => [],
                    ];
                }

                $bibliographyGroupAccumulator[$sectionGroup]['subtypes'][$subtypeKey]['count']++;
                if (count($bibliographyGroupAccumulator[$sectionGroup]['subtypes'][$subtypeKey]['samples']) < 6) {
                    $bibliographyGroupAccumulator[$sectionGroup]['subtypes'][$subtypeKey]['samples'][] = $item;
                }
            }
        }

        $recognizedMainSections = array_values(array_slice($recognizedMainSections, 0, 30));
        $documentTitleCandidates = array_values(array_slice($documentTitleCandidates, 0, 30));
        $uncertainHeadings = array_values(array_slice($uncertainHeadings, 0, 120));
        $emptyHeadings = array_values(array_slice($emptyHeadings, 0, 120));
        $probableTocArtifacts = array_values(array_slice($probableTocArtifacts, 0, 120));
        $suspiciousHeadings = array_values(array_slice($suspiciousHeadings, 0, 120));

        $bibliographyGroups = [];
        foreach ($bibliographyGroupAccumulator as $group) {
            $subtypes = array_values($group['subtypes'] ?? []);
            usort($subtypes, fn (array $left, array $right): int => ((int) ($right['count'] ?? 0)) <=> ((int) ($left['count'] ?? 0)));
            $group['subtypes'] = $subtypes;
            $bibliographyGroups[] = $group;
        }
        usort($bibliographyGroups, fn (array $left, array $right): int => strcmp((string) ($left['group_key'] ?? ''), (string) ($right['group_key'] ?? '')));

        $zoneOverview = array_values($zoneOverviewAccumulator);
        usort($zoneOverview, fn (array $left, array $right): int => ((int) ($left['first_order'] ?? PHP_INT_MAX)) <=> ((int) ($right['first_order'] ?? PHP_INT_MAX)));

        return [
            'recognized_main_sections' => $recognizedMainSections,
            'document_title_candidates' => $documentTitleCandidates,
            'uncertain_headings' => $uncertainHeadings,
            'empty_or_problematic_headings' => $emptyHeadings,
            'probable_toc_artifacts' => $probableTocArtifacts,
            'suspicious_heading_texts' => $suspiciousHeadings,
            'bibliography_groups' => $bibliographyGroups,
            'zone_overview' => $zoneOverview,
            'counts' => [
                'main_sections_count' => count($recognizedMainSections),
                'document_title_candidate_count' => count($documentTitleCandidates),
                'uncertain_heading_count' => count($uncertainHeadings),
                'empty_heading_count' => count($emptyHeadings),
                'probable_toc_artifact_count' => count($probableTocArtifacts),
                'suspicious_heading_count' => count($suspiciousHeadings),
                'zone_count' => count($zoneOverview),
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $block
     * @return array<string,mixed>
     */
    private function toReviewHeadingItem(array $block): array
    {
        $classification = is_array($block['classification'] ?? null)
            ? $block['classification']
            : [];
        $signals = is_array($classification['signals'] ?? null)
            ? array_values(array_map('strval', $classification['signals']))
            : [];
        $sectionHint = is_array($block['section_hint'] ?? null)
            ? $block['section_hint']
            : null;
        $sectionType = trim((string) ($sectionHint['section_type'] ?? ''));
        $text = trim((string) ($block['plain_text'] ?? $block['text'] ?? ''));
        $problemTags = is_array($block['problem_tags'] ?? null)
            ? array_values(array_map('strval', $block['problem_tags']))
            : [];
        $documentZone = is_array($block['document_zone'] ?? null)
            ? $block['document_zone']
            : [];
        $documentZoneKey = trim((string) ($documentZone['zone'] ?? ''));

        return [
            'id' => $block['id'] ?? null,
            'order' => (int) ($block['order'] ?? 0),
            'type' => (string) ($block['type'] ?? 'heading'),
            'text' => $text,
            'section_type' => $sectionType !== '' ? $sectionType : null,
            'section_type_label' => $this->sectionTypeLabel($sectionType),
            'confidence' => (string) ($classification['confidence'] ?? 'low'),
            'strategy' => (string) ($classification['strategy'] ?? 'heuristic'),
            'reason' => (string) (
                $sectionHint['reason']
                ?? ($signals[0] ?? 'no_signal')
            ),
            'problem_tags' => $problemTags,
            'problem_notes' => is_array($block['problem_notes'] ?? null)
                ? array_values(array_map('strval', $block['problem_notes']))
                : [],
            'signals' => $signals,
            'heading_level' => is_numeric($block['heading_level'] ?? null) ? (int) $block['heading_level'] : null,
            'is_usable_heading' => (bool) ($block['is_usable_heading'] ?? false),
            'structure_role' => $block['structure_role'] ?? null,
            'document_zone' => $documentZoneKey !== '' ? $documentZoneKey : null,
            'document_zone_label' => $documentZoneKey !== '' ? $this->documentZoneLabel($documentZoneKey) : null,
            'document_zone_confidence' => $documentZone['confidence'] ?? null,
            'document_zone_reason' => $documentZone['reason'] ?? null,
            'section_group' => $sectionHint['group'] ?? null,
            'section_group_label' => $sectionHint['group_label'] ?? null,
            'section_subtype' => $sectionHint['subtype'] ?? null,
            'section_subtype_label' => $sectionHint['subtype_label'] ?? null,
        ];
    }

    private function sectionTypeLabel(string $sectionType): ?string
    {
        return match ($sectionType) {
            'title_page' => 'Titelblatt',
            'abstract' => 'Abstract',
            'foreword' => 'Vorwort',
            'table_of_contents' => 'Inhaltsverzeichnis',
            'chapter' => 'Kapitel',
            'bibliography' => 'Literatur-/Quellenverzeichnis',
            'figure_index' => 'Abbildungsverzeichnis',
            'consent_declaration' => 'Eigenständigkeitserklärung',
            default => null,
        };
    }

    private function documentZoneLabel(string $zone): ?string
    {
        return match ($zone) {
            'title_page' => 'Titelblatt',
            'front_matter' => 'Frontmatter',
            'table_of_contents' => 'Inhaltsverzeichnis',
            'main_content' => 'Hauptteil',
            'bibliography_area' => 'Verzeichnisse / Bibliographie',
            'appendix_area' => 'Anhang',
            'declaration_area' => 'Erklärungsbereich',
            'end_matter' => 'Endmatter',
            default => null,
        };
    }

    private function statusCodeForExtractionError(string $errorType): int
    {
        if (in_array($errorType, ['invalid_path', 'file_not_found', 'file_not_readable', 'invalid_extension'], true)) {
            return 422;
        }

        if (in_array($errorType, ['binary_not_found', 'pandoc_disabled'], true)) {
            return 500;
        }

        return 422;
    }
}
