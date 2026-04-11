<?php

namespace App\Http\Controllers\Admin\Materials;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Materials\MaterialWorkspaceStoreRequest;
use App\Http\Requests\Admin\Materials\MaterialWorkspaceUpdateRequest;
use App\Models\MaterialWorkspace;
use App\Services\Materials\MaterialService;
use App\Services\Materials\MaterialWorkspaceService;
use Illuminate\Validation\ValidationException;

class MaterialWorkspaceController extends Controller
{
    public function store(MaterialWorkspaceStoreRequest $request, MaterialWorkspaceService $service)
    {
        $authUser = $this->authorizeForWorkspaceManagement();
        $validated = $request->validated()['data'];

        $workspace = $service->createWorkspace(
            $authUser,
            (string) ($validated['name'] ?? '')
        );

        return response()->json([
            'data' => [
                'id' => (int) $workspace->id,
                'name' => (string) $workspace->name,
                'is_default' => (bool) $workspace->is_default,
            ],
        ], 200);
    }

    public function update(
        MaterialWorkspaceUpdateRequest $request,
        MaterialWorkspace $material_workspace,
        MaterialWorkspaceService $service
    ) {
        $authUser = $this->authorizeForWorkspaceManagement();

        if ((int) ($material_workspace->user_id ?? 0) !== (int) $authUser->id) {
            throw ValidationException::withMessages([
                'workspace' => ['Workspace wurde nicht gefunden.'],
            ]);
        }

        $validated = $request->validated()['data'];
        $workspace = $service->renameWorkspace(
            $material_workspace,
            (string) ($validated['name'] ?? '')
        );

        return response()->json([
            'data' => [
                'id' => (int) $workspace->id,
                'name' => (string) $workspace->name,
                'is_default' => (bool) $workspace->is_default,
            ],
        ], 200);
    }

    public function destroy(
        MaterialWorkspace $material_workspace,
        MaterialService $service
    ) {
        $authUser = $this->authorizeForWorkspaceManagement();

        if ((int) ($material_workspace->user_id ?? 0) !== (int) $authUser->id) {
            throw ValidationException::withMessages([
                'workspace' => ['Workspace wurde nicht gefunden.'],
            ]);
        }

        $service->clearWorkspace($authUser, $material_workspace);

        return response()->noContent();
    }

    private function authorizeForWorkspaceManagement()
    {
        if (! $authUser = $this->userHasRole(['admin', 'materials_admin', 'materials_moderator'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return $authUser;
    }
}
