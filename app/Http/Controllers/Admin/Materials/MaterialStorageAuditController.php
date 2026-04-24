<?php

namespace App\Http\Controllers\Admin\Materials;

use App\Http\Controllers\Controller;
use App\Jobs\BuildMaterialStorageAuditJob;
use App\Jobs\SyncActiveSchoolMaterialFilesToLocalJob;
use App\Models\MaterialCardAttachment;
use App\Models\User;
use App\Services\Materials\MaterialService;
use App\Services\Materials\MaterialStorageAuditService;
use App\Services\Materials\MaterialStorageAuditStatusStore;
use App\Services\Materials\MaterialStorageSyncStatusStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialStorageAuditController extends Controller
{
    public function show(Request $request, MaterialStorageAuditService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $schoolId = (int) $request->integer('school_id', 0);

        return response()->json([
            'data' => $service->auditForUser($authUser, $schoolId > 0 ? $schoolId : null),
        ], 200);
    }

    public function startAudit(Request $request, MaterialStorageAuditStatusStore $statusStore): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'school_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $selectedSchoolId = isset($validated['school_id'])
            ? (int) $validated['school_id']
            : (int) ($authUser->selectedSchool?->id ?? $authUser->school_id ?? 0);

        $operation = $statusStore->createOperation(
            (int) $authUser->id,
            $selectedSchoolId > 0 ? $selectedSchoolId : null,
        );

        BuildMaterialStorageAuditJob::dispatch(
            (int) $authUser->id,
            (string) $operation['operation_id'],
            $selectedSchoolId > 0 ? $selectedSchoolId : null,
        );

        return response()->json([
            'message' => 'Speicherprüfung wurde gestartet.',
            'data' => $operation,
        ], 202);
    }

    public function auditStatus(Request $request, string $operationId, MaterialStorageAuditStatusStore $statusStore): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $operation = $statusStore->getOperation((int) $authUser->id, $operationId);
        if (! is_array($operation)) {
            return response()->json([
                'message' => 'Der Prüfstatus wurde nicht gefunden.',
            ], 404);
        }

        return response()->json([
            'data' => $operation,
        ], 200);
    }

    public function purge(Request $request): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'scope_key' => ['required', 'string', 'in:active_school,all_schools'],
            'school_id' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json([
            'message' => 'Remote-Löschungen sind hier deaktiviert. Bitte zuerst direkt gegen die Remote-Daten prüfen.',
        ], 422);
    }

    public function syncLocal(Request $request, MaterialStorageSyncStatusStore $statusStore): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'scope_key' => ['required', 'string', 'in:active_school'],
            'school_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $selectedSchoolId = isset($validated['school_id'])
            ? (int) $validated['school_id']
            : (int) ($authUser->selectedSchool?->id ?? $authUser->school_id ?? 0);

        if ($selectedSchoolId <= 0) {
            return response()->json([
                'message' => 'Es ist keine aktive Schule ausgewählt.',
            ], 422);
        }

        $operation = $statusStore->createOperation((int) $authUser->id, $selectedSchoolId);

        SyncActiveSchoolMaterialFilesToLocalJob::dispatch(
            (int) $authUser->id,
            (string) $operation['operation_id'],
            $selectedSchoolId,
        );

        return response()->json([
            'message' => 'Der Download der Materialdateien wurde im Hintergrund gestartet.',
            'data' => array_merge($operation, [
                'scope_key' => (string) $validated['scope_key'],
                'school_id' => $selectedSchoolId,
            ]),
        ], 202);
    }

    public function syncStatus(Request $request, string $operationId, MaterialStorageSyncStatusStore $statusStore): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $operation = $statusStore->getOperation((int) $authUser->id, $operationId);
        if (! is_array($operation)) {
            return response()->json([
                'message' => 'Der Download-Status wurde nicht gefunden.',
            ], 404);
        }

        return response()->json([
            'data' => $operation,
        ], 200);
    }

    public function destroyDatabaseOnlyAttachment(
        Request $request,
        int $attachmentId,
        MaterialStorageAuditService $storageAuditService,
        MaterialService $materialService,
    ): JsonResponse {
        if (! $authUser = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'scope_key' => ['required', 'string', 'in:active_school'],
            'school_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $attachment = MaterialCardAttachment::query()
            ->with('materialCard')
            ->findOrFail($attachmentId);

        if (! $storageAuditService->isDatabaseOnlyAttachment($attachment)) {
            return response()->json([
                'message' => 'Dieses Material hat keine fehlende Datei und wurde nicht gelöscht.',
            ], 422);
        }

        $materialCard = $attachment->materialCard;
        if (! $materialCard) {
            return response()->json([
                'message' => 'Der Materialeintrag wurde nicht gefunden.',
            ], 404);
        }

        $selectedSchoolId = $this->selectedSchoolIdForStorageAudit($validated, $authUser);
        if ($validated['scope_key'] === 'active_school' && (int) $materialCard->school_id !== $selectedSchoolId) {
            return response()->json([
                'message' => 'Dieses Material gehört nicht zur aktiven Schule.',
            ], 422);
        }

        $materialService->deleteCard($materialCard);

        return response()->json([
            'message' => 'Das Material mit fehlender Datei wurde gelöscht.',
            'data' => [
                'deleted_count' => 1,
            ],
        ], 200);
    }

    public function destroyDatabaseOnlyMaterials(
        Request $request,
        MaterialStorageAuditService $storageAuditService,
        MaterialService $materialService,
    ): JsonResponse {
        if (! $authUser = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'scope_key' => ['required', 'string', 'in:active_school'],
            'school_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $selectedSchoolId = $this->selectedSchoolIdForStorageAudit($validated, $authUser);
        if ($selectedSchoolId <= 0) {
            return response()->json([
                'message' => 'Es ist keine aktive Schule ausgewählt.',
            ], 422);
        }

        $attachments = MaterialCardAttachment::query()
            ->with('materialCard')
            ->whereHas('materialCard', function ($query) use ($selectedSchoolId): void {
                $query->where('school_id', $selectedSchoolId);
            })
            ->get()
            ->filter(fn (MaterialCardAttachment $attachment): bool => $storageAuditService->isDatabaseOnlyAttachment($attachment));

        $cards = $attachments
            ->map(fn (MaterialCardAttachment $attachment) => $attachment->materialCard)
            ->filter()
            ->unique('id')
            ->values();

        foreach ($cards as $card) {
            $materialService->deleteCard($card);
        }

        return response()->json([
            'message' => $cards->count() === 1
                ? 'Ein Material mit fehlender Datei wurde gelöscht.'
                : $cards->count().' Materialien mit fehlender Datei wurden gelöscht.',
            'data' => [
                'deleted_count' => $cards->count(),
            ],
        ], 200);
    }

    /**
     * @param  array{scope_key: string, school_id?: int|null}  $validated
     */
    private function selectedSchoolIdForStorageAudit(array $validated, User $authUser): int
    {
        return isset($validated['school_id'])
            ? (int) $validated['school_id']
            : (int) ($authUser->selectedSchool?->id ?? $authUser->school_id ?? 0);
    }
}
