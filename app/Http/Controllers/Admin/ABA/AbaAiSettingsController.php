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
use App\Services\AbaDocumentRuleService;
use App\Services\AbaPandocAstNormalizerService;
use App\Services\AbaPandocDocxExtractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

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

            return response()->json([
                'success' => true,
                'note' => 'Interne Prüfansicht für DOCX-Extraktion und Normalisierung. Keine finale ABA-Bewertung.',
                'document' => [
                    'original_name' => $uploadedFile->getClientOriginalName(),
                    'size_bytes' => $uploadedFile->getSize(),
                ],
                'summary' => $this->buildPandocDebugSummary($blocks),
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
        }
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
        ];
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
