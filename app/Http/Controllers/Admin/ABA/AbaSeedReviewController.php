<?php

namespace App\Http\Controllers\Admin\ABA;

use App\ABA\Services\ApplySeedReplacementDraft;
use App\ABA\Services\BuildSeedDiffView;
use App\ABA\Services\BuildSeedReplacementDraft;
use App\ABA\Services\SaveSeedProposalEdits;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

/**
 * Admin-API für den Seed-Review-Screen.
 *
 * Stellt Endpunkte bereit für:
 *   - Liste der diffbaren Proposal-Dateien (Ersatzdrafts bevorzugt)
 *   - Zeilenweisen Diff zwischen Seed und einem Proposal
 *   - Erzeugung eines echten Seed-Ersatzdrafts
 *
 * Governance:
 *   - Kein automatisches Apply – nur Entwürfe für manuelles Review.
 *   - Apply nur für echte Seed-Ersatzdrafts (seed-replacement-draft-*), nie für Analyseberichte.
 */
class AbaSeedReviewController extends Controller
{
    public function proposals(BuildSeedDiffView $service): JsonResponse
    {
        return response()->json([
            'proposals' => $service->listDiffableProposals(),
        ]);
    }

    public function diff(Request $request, string $filename, BuildSeedDiffView $service): JsonResponse
    {
        $filename = basename($filename);

        if (! preg_match('/^[a-zA-Z0-9_\-]+\.[a-zA-Z0-9]+$/', $filename)) {
            return response()->json(['success' => false, 'error' => 'Ungültiger Dateiname.'], 422);
        }

        $result = $service->build($filename, $request->boolean('expand', false));

        if (! $result['success']) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    public function applyReplacement(Request $request, ApplySeedReplacementDraft $service): JsonResponse
    {
        $filename = $request->input('filename');

        if (! $filename || ! is_string($filename)) {
            return response()->json(['success' => false, 'error' => 'Dateiname fehlt.'], 422);
        }

        $filename = basename($filename);

        if (! preg_match('/^[a-zA-Z0-9_\-]+\.md$/', $filename)) {
            return response()->json(['success' => false, 'error' => 'Ungültiger Dateiname.'], 422);
        }

        $appliedBy = $request->user()?->name ?? $request->user()?->email ?? 'Unbekannt';

        try {
            $result = $service->apply($filename, $appliedBy);

            if (! $result['success']) {
                return response()->json($result, 422);
            }

            return response()->json([
                'success' => true,
                'archive_filename' => $result['archive_filename'],
                'applied_filename' => $result['applied_filename'],
                'message' => "Vorschlag erfolgreich übernommen. Backup: archive/{$result['archive_filename']}",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Übernahme fehlgeschlagen: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Gibt den rohen Markdown-Inhalt einer Proposal-Datei zurück (für Vollansicht im UI).
     * Nur Proposal-Dateien im proposals/-Verzeichnis – keine Pfadtraversierung.
     */
    public function content(string $filename): JsonResponse
    {
        $filename = basename($filename);

        if (! preg_match('/^[a-zA-Z0-9_\-]+\.[a-zA-Z0-9]+$/', $filename)) {
            return response()->json(['success' => false, 'error' => 'Ungültiger Dateiname.'], 422);
        }

        $path = base_path('ai/knowledge/aba/sources/proposals/'.$filename);

        if (! File::exists($path)) {
            return response()->json(['success' => false, 'error' => 'Datei nicht gefunden.'], 404);
        }

        return response()->json([
            'success' => true,
            'filename' => $filename,
            'content' => File::get($path),
        ]);
    }

    public function updateContent(Request $request, string $filename, SaveSeedProposalEdits $service): JsonResponse
    {
        $content = $request->input('content');

        if (! is_string($content)) {
            return response()->json([
                'success' => false,
                'error' => 'Ungültiger Inhalt.',
            ], 422);
        }

        try {
            $result = $service->save($filename, $content);

            if (! $result['success']) {
                return response()->json($result, 422);
            }

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Speichern fehlgeschlagen: '.$e->getMessage(),
            ], 500);
        }
    }

    public function generateReplacement(BuildSeedReplacementDraft $service): JsonResponse
    {
        try {
            $result = $service->build();

            if (! $result['success']) {
                return response()->json($result, 422);
            }

            $qualityIssues = $result['quality_issues'] ?? [];
            $qualityOk = empty($qualityIssues);

            return response()->json([
                'success' => true,
                'filename' => $result['filename'],
                'lines_in_draft' => $result['lines_in_draft'],
                'editorial_items_removed' => $result['editorial_items_removed'],
                'unresolved_count' => $result['unresolved_count'],
                'quality_issues' => $qualityIssues,
                'is_valid_replacement' => $qualityOk,
                'message' => $qualityOk
                    ? "Vorschlag erstellt: {$result['filename']} ({$result['lines_in_draft']} Zeilen, {$result['editorial_items_removed']} redaktionelle Marker entfernt)"
                    : 'Vorschlag erstellt, aber mit Qualitätsproblemen: '.implode(', ', $qualityIssues),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Vorschlag konnte nicht erstellt werden: '.$e->getMessage(),
            ], 500);
        }
    }
}
