<?php

namespace App\Http\Controllers\Admin\ABA;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ABA\AbaAnalysisResultResource;
use App\Http\Resources\Admin\ABA\AbaAnalysisRunResource;
use App\Models\Aba;
use App\Services\AbaAnalysisService;
use Illuminate\Http\JsonResponse;

class AbaAnalysisRunController extends Controller
{
    public function store(Aba $aba, AbaAnalysisService $analysisService): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['aba_teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (! $this->canAccessAba($aba, (int) $authUser->id, (int) $authUser->school_id)) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $result = $analysisService->startRun($authUser, $aba);
        $run = $result['run'];
        $statusCode = $result['precondition_failed']
            ? 422
            : ($result['already_running'] ? 200 : 202);

        return response()->json([
            'message' => $result['message'],
            'data' => new AbaAnalysisRunResource($run),
        ], $statusCode);
    }

    public function showLatest(Aba $aba): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['aba_teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (! $this->canAccessAba($aba, (int) $authUser->id, (int) $authUser->school_id)) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $aba->load('mainDocument');

        $latestRun = $aba->analysisRuns()
            ->with([
                'attachment',
                'results' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            ])
            ->latest('id')
            ->first();

        return response()->json([
            'data' => [
                'aba' => [
                    'id' => (int) $aba->id,
                    'title' => $aba->title,
                    'student_name' => $aba->student_name,
                    'schoolyear_id' => $aba->schoolyear_id,
                ],
                'main_document' => $aba->mainDocument ? [
                    'id' => (int) $aba->mainDocument->id,
                    'original_name' => $aba->mainDocument->original_name,
                    'mime_type' => $aba->mainDocument->mime_type,
                    'size_bytes' => $aba->mainDocument->size_bytes !== null ? (int) $aba->mainDocument->size_bytes : null,
                    'created_at' => $aba->mainDocument->created_at?->toISOString(),
                ] : null,
                'analysis_run' => $latestRun ? new AbaAnalysisRunResource($latestRun) : null,
                'sections' => $latestRun ? AbaAnalysisResultResource::collection($latestRun->results)->resolve() : [],
            ],
        ]);
    }

    private function canAccessAba(Aba $aba, int $userId, int $schoolId): bool
    {
        return (int) $aba->school_id === $schoolId && (int) $aba->user_id === $userId;
    }
}
