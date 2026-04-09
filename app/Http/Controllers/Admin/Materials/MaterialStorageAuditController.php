<?php

namespace App\Http\Controllers\Admin\Materials;

use App\Http\Controllers\Controller;
use App\Services\Materials\MaterialStorageAuditService;
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

    public function purge(Request $request, MaterialStorageAuditService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'scope_key' => ['required', 'string', 'in:active_school,all_schools'],
            'school_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $result = $service->purgeBucketOnlyObjectsForUser(
            $authUser,
            (string) $validated['scope_key'],
            isset($validated['school_id']) ? (int) $validated['school_id'] : null,
        );

        return response()->json([
            'message' => 'Verwaiste Dateien wurden gelöscht.',
            'data' => $result,
        ], 200);
    }
}
