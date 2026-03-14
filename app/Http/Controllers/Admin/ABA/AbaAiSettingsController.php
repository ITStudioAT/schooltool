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
use Illuminate\Http\JsonResponse;

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
    ): JsonResponse {
        return response()->json([
            'seed_report' => $seedMeta->read(),
            'review_state' => $reviewState->read(),
            'source_registry' => $sourceRegistry->read(),
            'open_claims' => $openClaims->read(),
            'freshness_results' => $freshnessResults->read(),
            'hardening_status' => $hardeningStatus->read(),
            'proposals' => $seedDiffView->listDiffableProposals(),
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
}
