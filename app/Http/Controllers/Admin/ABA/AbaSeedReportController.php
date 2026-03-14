<?php

namespace App\Http\Controllers\Admin\ABA;

use App\ABA\Services\ReadFreshnessResults;
use App\ABA\Services\ReadOpenClaims;
use App\ABA\Services\ReadSeedReportContent;
use App\ABA\Services\ReadSeedReportMeta;
use App\ABA\Services\ReadSeedReviewState;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Admin-API für die Seed-Report-Verwaltungsseite.
 *
 * Liest den vollständigen Inhalt und Governance-Kontext der Hauptdatei
 * (aba-knowledge-seed-report.md). Schreiboperationen laufen über die
 * bestehenden Endpunkte in AbaAiSettingsController.
 *
 * Nur für admin und super_admin zugänglich.
 */
class AbaSeedReportController extends Controller
{
    public function index(
        ReadSeedReportContent $content,
        ReadSeedReportMeta $meta,
        ReadSeedReviewState $reviewState,
        ReadFreshnessResults $freshnessResults,
        ReadOpenClaims $openClaims,
    ): JsonResponse {
        return response()->json([
            'content' => $content->read(),
            'meta' => $meta->read(),
            'review_state' => $reviewState->read(),
            'freshness_results' => $freshnessResults->read(),
            'open_claims' => $openClaims->read(),
        ]);
    }
}
