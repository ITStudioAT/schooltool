<?php

namespace App\Http\Controllers\Admin\ABA;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ABA\AbaAttachmentStoreFromTempRequest;
use App\Http\Resources\Admin\ABA\AbaAttachmentResource;
use App\Models\Aba;
use App\Models\AbaAttachment;
use App\Services\AbaAttachmentService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AbaAttachmentController extends Controller
{
    public function storeFromTemp(
        AbaAttachmentStoreFromTempRequest $request,
        Aba $aba,
        AbaAttachmentService $service
    ): JsonResponse {
        if (! $authUser = $this->userHasRole(['aba_teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (! $this->canAccessAba($aba, (int) $authUser->id, (int) $authUser->school_id)) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated()['data'];
        $attachment = $service->addAttachmentFromTempUpload(
            $authUser,
            $aba,
            (string) ($validated['upload_id'] ?? ''),
            (string) ($validated['document_kind'] ?? ''),
            isset($validated['original_name']) ? (string) $validated['original_name'] : null,
        );

        return response()->json(new AbaAttachmentResource($attachment), 201);
    }

    public function destroy(Aba $aba, AbaAttachment $attachment, AbaAttachmentService $service): Response
    {
        if (! $authUser = $this->userHasRole(['aba_teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (! $this->canAccessAba($aba, (int) $authUser->id, (int) $authUser->school_id)) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ((int) $attachment->aba_id !== (int) $aba->id) {
            abort(404);
        }

        $service->deleteAttachment($attachment);

        return response()->noContent();
    }

    private function canAccessAba(Aba $aba, int $userId, int $schoolId): bool
    {
        return (int) $aba->school_id === $schoolId && (int) $aba->user_id === $userId;
    }
}
