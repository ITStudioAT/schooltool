<?php

namespace App\Http\Controllers\Admin\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Restaurant\RestaurantGeneralSettingsUpdateRequest;
use App\Services\RestaurantService;
use Illuminate\Http\JsonResponse;

class RestaurantGeneralSettingsController extends Controller
{
    public function update(
        RestaurantGeneralSettingsUpdateRequest $request,
        RestaurantService $service
    ): JsonResponse {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $settings = $service->updateGeneralSettings($authUser, $request->validated()['data']);

        return response()->json([
            'data' => $settings,
        ]);
    }
}
