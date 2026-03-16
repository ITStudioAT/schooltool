<?php

namespace App\Http\Controllers\Admin\ABA;

use App\ABA\Knowledge\KnowledgeQueryService;
use App\ABA\Services\QueryKnowledgeBase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ermöglicht Abfragen der lokalen ABA-Wissensbasis über die Admin-API.
 *
 * Endpunkte:
 *   GET  /api/admin/aba/knowledge          – Übersicht & Statistik der Wissensbasis
 *   POST /api/admin/aba/knowledge/query    – Frage an die Wissensbasis stellen
 */
class AbaKnowledgeQueryController extends Controller
{
    /** Alle verfügbaren topic_groups mit lesbaren Bezeichnungen. */
    private const TOPIC_GROUPS = [
        'fristen' => 'Fristen und Termine',
        'formate' => 'ABA-Formate (schriftlich/mündlich)',
        'aufbau' => 'Aufbau und Struktur',
        'einreichung' => 'Einreichung und Abgabe',
        'ki_policy' => 'KI-Nutzung und KI-Policy',
        'bewertung' => 'Bewertung und Notenvergabe',
        'zitation' => 'Zitation und Quellenangaben',
        'unsicherheiten' => 'Offene Fragen und Unsicherheiten',
    ];

    /**
     * Gibt Statistik und verfügbare Themenbereiche der Wissensbasis zurück.
     */
    public function index(KnowledgeQueryService $queryService): JsonResponse
    {
        if (! $this->userHasRole(['admin', 'super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $stats = $queryService->getStats();

        return response()->json([
            'data' => [
                'topic_groups' => self::TOPIC_GROUPS,
                'stats' => $stats,
            ],
        ]);
    }

    /**
     * Beantwortet eine Frage aus der lokalen ABA-Wissensbasis.
     *
     * Request body:
     *   - question      (required, string, 5-500 Zeichen)
     *   - topic_groups  (optional, array, Einschränkung auf Themenbereiche)
     */
    public function query(Request $request, QueryKnowledgeBase $queryService): JsonResponse
    {
        if (! $this->userHasRole(['admin', 'super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'question' => ['required', 'string', 'min:5', 'max:500'],
            'topic_groups' => ['sometimes', 'array'],
            'topic_groups.*' => ['string', 'in:'.implode(',', array_keys(self::TOPIC_GROUPS))],
        ]);

        $result = $queryService->query(
            question: $validated['question'],
            topicGroups: $validated['topic_groups'] ?? [],
        );

        $statusCode = $result['success'] ? 200 : 422;

        return response()->json(['data' => $result], $statusCode);
    }
}
