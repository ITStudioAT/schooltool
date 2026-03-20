<?php

namespace App\Http\Controllers\Admin\ABA;

use App\ABA\Services\BuildSeedReplacementDraft;
use App\ABA\Services\ExtractSeedEditorialNotes;
use App\ABA\Services\FindSeedOpenIssues;
use App\ABA\Services\ReadSeedHardeningStatus;
use App\ABA\Services\ResolveSeedSourcePlaceholders;
use App\ABA\Services\RunSeedHardening;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Admin-API für die Seed-Härtungs-Pipeline.
 *
 * Nur für Rollen admin und super_admin zugänglich (über Middleware in api.php).
 * Kein autonomes Überschreiben der Seed-Datei – alle Aktionen erzeugen nur Entwürfe.
 */
class AbaSeedHardeningController extends Controller
{
    public function status(ReadSeedHardeningStatus $service): JsonResponse
    {
        return response()->json($service->read());
    }

    public function scan(FindSeedOpenIssues $service): JsonResponse
    {
        try {
            $result = $service->find();

            return response()->json([
                'success' => true,
                'total_issues' => $result['summary']['total_issues'],
                'by_type' => $result['summary']['by_type'],
                'generated_at' => $result['generated_at'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erkennung offener Punkte fehlgeschlagen: '.$e->getMessage(),
            ], 500);
        }
    }

    public function run(RunSeedHardening $service): JsonResponse
    {
        try {
            $result = $service->run(useAi: false);

            return response()->json([
                'success' => $result['success'],
                'mode' => $result['mode'],
                'summary' => $result['summary'],
                'error' => $result['error'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Hardening-Pipeline fehlgeschlagen: '.$e->getMessage(),
            ], 500);
        }
    }

    public function runAi(RunSeedHardening $service): JsonResponse
    {
        if (! $this->isOpenAiConfigured()) {
            return response()->json([
                'success' => false,
                'error' => 'OpenAI ist nicht konfiguriert. Bitte OPENAI_API_KEY in der .env-Datei setzen.',
            ], 422);
        }

        try {
            $result = $service->run(useAi: true);

            return response()->json([
                'success' => $result['success'],
                'mode' => $result['mode'],
                'summary' => $result['summary'],
                'proposal_file' => isset($result['summary']['proposal_file'])
                    ? basename($result['summary']['proposal_file'])
                    : null,
                'error' => $result['error'],
            ]);
        } catch (\Throwable $e) {
            $message = $this->humanizeAiError($e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $message,
            ], 500);
        }
    }

    public function cleanup(ExtractSeedEditorialNotes $service): JsonResponse
    {
        try {
            $result = $service->extract();

            return response()->json([
                'success' => true,
                'summary' => $result['summary'],
                'cleanup_file' => isset($result['cleanup_file']) ? basename($result['cleanup_file']) : null,
                'notes_file' => 'ai/knowledge/aba/sources/seed-editorial-notes.json',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Redaktionelle Bereinigung fehlgeschlagen: '.$e->getMessage(),
            ], 500);
        }
    }

    public function analyzeAndPropose(
        RunSeedHardening $hardeningService,
        ResolveSeedSourcePlaceholders $resolveService,
        BuildSeedReplacementDraft $draftService,
    ): JsonResponse {
        $useAi = $this->isOpenAiConfigured();

        try {
            // Phase 1: Analyse + Verifikation (erzeugt Analyse-/Report-Artefakte)
            $result = $hardeningService->run(useAi: $useAi);

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                ], 422);
            }

            // Phase 2: Quellen-Auflösung (Registry-Only – kein Web-Fetch, keine Halluzination)
            // Fehler hier blockieren nicht die Antwort.
            $resolveResult = null;
            try {
                $resolveResult = $resolveService->resolve();
            } catch (\Throwable) {
                // silent – kein kritischer Fehler
            }

            // Phase 3: Seed-Ersatzdraft erzeugen (direkt aus der aktuellen Seed-Datei,
            // mit redaktioneller Bereinigung + Verification-Ergebnissen als Basis).
            // Nur wenn die Analyse erfolgreich war. Fehler hier blockieren nicht die Antwort.
            $draftResult = null;
            $draftError = null;

            try {
                $draftResult = $draftService->build();
                if (! $draftResult['success']) {
                    $draftError = $draftResult['error'];
                }
            } catch (\Throwable $e) {
                $draftError = 'Vorschlag konnte nicht erstellt werden: '.$e->getMessage();
            }

            $draftGenerated = $draftResult !== null && $draftResult['success'];
            $draftValid = $draftGenerated && empty($draftResult['quality_issues'] ?? []);

            return response()->json([
                'success' => true,
                'mode' => $result['mode'],
                'ai_used' => $useAi,
                'summary' => $result['summary'],
                'proposal_file' => isset($result['summary']['proposal_file'])
                    ? basename($result['summary']['proposal_file'])
                    : null,
                // Source-Resolution-Ergebnis
                'source_resolution' => $resolveResult !== null ? [
                    'total' => $resolveResult['total'],
                    'resolved' => $resolveResult['resolved'],
                    'partially_resolved' => $resolveResult['partially_resolved'],
                    'unresolved' => $resolveResult['unresolved'],
                    'fully_resolved' => $resolveResult['resolved'],
                    'open_for_main_file' => $resolveResult['partially_resolved'] + $resolveResult['unresolved'],
                    'clarified_total' => $resolveResult['resolved'] + $resolveResult['partially_resolved'],
                    'note' => $resolveResult['note'],
                    'items' => $this->formatSourceResolutionItems($resolveResult['items'] ?? []),
                    'error' => null,
                ] : [
                    'total' => 0,
                    'resolved' => 0,
                    'partially_resolved' => 0,
                    'unresolved' => 0,
                    'fully_resolved' => 0,
                    'open_for_main_file' => 0,
                    'clarified_total' => 0,
                    'note' => null,
                    'items' => [],
                    'error' => 'Quellenauflösung nicht ausgeführt.',
                ],
                // Replacement-Draft-Ergebnis
                'replacement_draft' => [
                    'generated' => $draftGenerated,
                    'valid' => $draftValid,
                    'filename' => $draftGenerated ? $draftResult['filename'] : null,
                    'lines' => $draftGenerated ? $draftResult['lines_in_draft'] : null,
                    'editorial_removed' => $draftGenerated ? $draftResult['editorial_items_removed'] : null,
                    'quality_issues' => $draftGenerated ? ($draftResult['quality_issues'] ?? []) : [],
                    'error' => $draftError,
                ],
                'error' => null,
            ]);
        } catch (\Throwable $e) {
            $message = $useAi
                ? $this->humanizeAiError($e->getMessage())
                : 'Analyse fehlgeschlagen: '.$e->getMessage();

            return response()->json(['success' => false, 'error' => $message], 500);
        }
    }

    private function isOpenAiConfigured(): bool
    {
        $key = config('ai.providers.openai.key', '');

        return ! empty($key);
    }

    private function humanizeAiError(string $raw): string
    {
        if (str_contains($raw, '401') || str_contains($raw, 'invalid_api_key') || str_contains($raw, 'Unauthorized')) {
            return 'OpenAI-Authentifizierung fehlgeschlagen. Bitte OPENAI_API_KEY prüfen.';
        }
        if (str_contains($raw, '429') || str_contains($raw, 'rate_limit')) {
            return 'OpenAI-Rate-Limit erreicht. Bitte kurz warten und erneut versuchen.';
        }
        if (str_contains($raw, '503') || str_contains($raw, 'overloaded')) {
            return 'OpenAI ist momentan überlastet. Bitte später erneut versuchen.';
        }

        return 'KI-Hardening-Pipeline fehlgeschlagen. Details: '.$raw;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function formatSourceResolutionItems(array $items): array
    {
        return array_map(
            fn (array $item): array => [
                'issue_id' => $item['issue_id'] ?? null,
                'resolution_status' => $item['resolution_status'] ?? 'unresolved',
                'source_id' => $item['source_id'] ?? null,
                'source_title' => $item['source_title'] ?? null,
                'url' => $item['url'] ?? null,
                'confidence' => $item['confidence'] ?? null,
                'note' => $item['note'] ?? null,
                'reason' => $item['reason'] ?? null,
                'evidence_level' => $item['evidence_level'] ?? 'none',
                'is_direct_document' => (bool) ($item['is_direct_document'] ?? false),
                'is_citable' => (bool) ($item['is_citable'] ?? false),
                'missing_requirements' => $item['missing_requirements'] ?? [],
            ],
            $items
        );
    }
}
