<?php

namespace App\Http\Controllers\Admin\Materials;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Materials\MaterialUserSettingsUpdateRequest;
use App\Services\Materials\MaterialService;

class MaterialUserSettingsController extends Controller
{
    public function update(MaterialUserSettingsUpdateRequest $request, MaterialService $service)
    {
        $authUser = $this->authorizeForMaterialUserSettings();
        $validated = $request->validated()['data'];

        $settings = $service->updateUserSettings(
            $authUser,
            (int) ($validated['materials_pagination_number'] ?? 0)
        );

        return response()->json([
            'data' => $settings,
        ], 200);
    }

    private function authorizeForMaterialUserSettings()
    {
        if (! $authUser = $this->userHasRole(['admin', 'materials_admin', 'materials_moderator'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return $authUser;
    }
}
