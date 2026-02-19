<?php

namespace App\Http\Controllers\Admin\Materials;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Materials\MaterialFileSettingsUpdateRequest;
use App\Services\Materials\MaterialService;

class MaterialFileSettingsController extends Controller
{
    public function update(MaterialFileSettingsUpdateRequest $request, MaterialService $service)
    {
        $authUser = $this->authorizeForFileSettingsManagement();
        $validated = $request->validated()['data'];

        $settings = $service->updateFileSettings($authUser, (int) ($validated['max_upload_size_kb'] ?? 0));

        return response()->json([
            'data' => $settings,
        ], 200);
    }

    private function authorizeForFileSettingsManagement()
    {
        if (! $authUser = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return $authUser;
    }
}
