<?php

namespace App\Http\Controllers\Admin\Materials;

use App\Http\Controllers\Controller;
use App\Jobs\BuildMaterialStorageAuditJob;
use App\Jobs\SyncActiveSchoolMaterialFilesToLocalJob;
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
    ): JsonResponse {
        if (! $authUser = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'scope_key' => ['required', 'string', 'in:active_school,all_schools'],
            'school_id' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json([
            'message' => 'Löschungen aus dem Storage-Audit sind hier deaktiviert. Bitte zuerst direkt gegen die Remote-Daten prüfen.',
        ], 422);
    }
}
