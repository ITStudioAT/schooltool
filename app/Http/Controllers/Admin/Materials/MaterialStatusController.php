<?php

namespace App\Http\Controllers\Admin\Materials;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Materials\MaterialStatusStoreRequest;
use App\Http\Requests\Admin\Materials\MaterialStatusUpdateRequest;
use App\Models\MaterialStatus;
use App\Services\Materials\MaterialService;
use Illuminate\Http\Request;

class MaterialStatusController extends Controller
{
    public function index(Request $request, MaterialService $service)
    {
        $authUser = $this->authorizeForStatusManagement();

        return response()->json([
            'data' => $service->statusValuesForUser($authUser),
        ], 200);
    }

    public function store(MaterialStatusStoreRequest $request, MaterialService $service)
    {
        $authUser = $this->authorizeForStatusManagement();
        $validated = $request->validated()['data'];

        $status = $service->createStatus(
            $authUser,
            (string) ($validated['label'] ?? ''),
            isset($validated['color']) ? (string) $validated['color'] : null
        );

        return response()->json([
            'data' => [
                'id' => $status->id,
                'value' => $status->value,
                'label' => $status->label,
                'color' => $status->color,
            ],
        ], 200);
    }

    public function update(
        MaterialStatusUpdateRequest $request,
        MaterialStatus $material_status,
        MaterialService $service
    ) {
        $authUser = $this->authorizeForStatusManagement();
        $validated = $request->validated()['data'];

        $status = $service->updateStatus(
            $authUser,
            $material_status,
            (string) ($validated['label'] ?? ''),
            isset($validated['color']) ? (string) $validated['color'] : null
        );

        return response()->json([
            'data' => [
                'id' => $status->id,
                'value' => $status->value,
                'label' => $status->label,
                'color' => $status->color,
            ],
        ], 200);
    }

    public function destroy(MaterialStatus $material_status, MaterialService $service)
    {
        $authUser = $this->authorizeForStatusManagement();
        $service->deleteStatus($authUser, $material_status);

        return response()->noContent();
    }

    private function authorizeForStatusManagement()
    {
        if (! $authUser = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return $authUser;
    }
}
