<?php

namespace App\Http\Controllers\Admin\Materials;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Materials\MaterialTypeStoreRequest;
use App\Http\Requests\Admin\Materials\MaterialTypeUpdateRequest;
use App\Models\MaterialType;
use App\Services\Materials\MaterialService;
use Illuminate\Http\Request;

class MaterialTypeController extends Controller
{
    public function index(Request $request, MaterialService $service)
    {
        $authUser = $this->authorizeForTypeManagement();

        return response()->json([
            'data' => $service->typeValuesForUser($authUser),
        ], 200);
    }

    public function store(MaterialTypeStoreRequest $request, MaterialService $service)
    {
        $authUser = $this->authorizeForTypeManagement();
        $validated = $request->validated()['data'];

        $type = $service->createType($authUser, (string) ($validated['name'] ?? ''));

        return response()->json([
            'data' => [
                'id' => $type->id,
                'value' => $type->name,
                'label' => $type->name,
            ],
        ], 200);
    }

    public function update(
        MaterialTypeUpdateRequest $request,
        MaterialType $material_type,
        MaterialService $service
    ) {
        $authUser = $this->authorizeForTypeManagement();
        $validated = $request->validated()['data'];

        $type = $service->updateType($authUser, $material_type, (string) ($validated['name'] ?? ''));

        return response()->json([
            'data' => [
                'id' => $type->id,
                'value' => $type->name,
                'label' => $type->name,
            ],
        ], 200);
    }

    public function destroy(MaterialType $material_type, MaterialService $service)
    {
        $authUser = $this->authorizeForTypeManagement();
        $service->deleteType($authUser, $material_type);

        return response()->noContent();
    }

    private function authorizeForTypeManagement()
    {
        if (! $authUser = $this->userHasRole(['admin', 'materials_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return $authUser;
    }
}
