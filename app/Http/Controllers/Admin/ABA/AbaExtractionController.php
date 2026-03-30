<?php

namespace App\Http\Controllers\Admin\ABA;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ABA\AbaExtractionResource;
use App\Models\Aba;
use App\Services\AbaDocumentExtractionService;
use Illuminate\Http\JsonResponse;

class AbaExtractionController extends Controller
{
    public function show(Aba $aba, AbaDocumentExtractionService $extractionService): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['aba_teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (! $this->canAccessAba($aba, (int) $authUser->id, (int) $authUser->school_id)) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $run = $extractionService->latest($aba);

        return response()->json([
            'data' => $run ? new AbaExtractionResource($run) : null,
        ]);
    }

    public function store(Aba $aba, AbaDocumentExtractionService $extractionService): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['aba_teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (! $this->canAccessAba($aba, (int) $authUser->id, (int) $authUser->school_id)) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $run = $extractionService->start($authUser, $aba);
        $statusCode = in_array($run->status, ['started', 'running'], true) ? 202 : 200;

        return response()->json([
            'data' => new AbaExtractionResource($run),
        ], $statusCode);
    }

    private function canAccessAba(Aba $aba, int $userId, int $schoolId): bool
    {
        return (int) $aba->school_id === $schoolId && (int) $aba->user_id === $userId;
    }
}
