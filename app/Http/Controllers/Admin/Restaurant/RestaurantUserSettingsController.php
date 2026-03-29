<?php

namespace App\Http\Controllers\Admin\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Restaurant\RestaurantUserSettingsUpdateRequest;
use App\Services\RestaurantService;

class RestaurantUserSettingsController extends Controller
{
    public function update(RestaurantUserSettingsUpdateRequest $request, RestaurantService $service)
    {
        $authUser = $this->authorizeForRestaurantUserSettings();
        $validated = $request->validated()['data'];

        $settings = $service->updateUserSettings(
            $authUser,
            (int) ($validated['restaurant_foods_pagination_number'] ?? 0)
        );

        return response()->json([
            'data' => $settings,
        ], 200);
    }

    private function authorizeForRestaurantUserSettings()
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return $authUser;
    }
}
