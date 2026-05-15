<?php

namespace App\Http\Controllers\Admin\Restaurant;

use App\Http\Controllers\Controller;
use App\Services\LegacyRestaurantStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class RestaurantCdgymLegacyImportController extends Controller
{
    public function __invoke(Request $request, LegacyRestaurantStatsService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $authUser->loadMissing('selectedSchool');

        if ($authUser->selectedSchool?->long_name !== 'Christian-Doppler-Gymnasium Salzburg') {
            abort(403, 'Diese Live-Daten sind nur für das Christian-Doppler-Gymnasium Salzburg verfügbar.');
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => [
                'string',
                Rule::in([
                    'foods',
                    'menus',
                    'menu_plans',
                    'bookings',
                    'lunch_users',
                    'lunch_admins',
                ]),
            ],
        ]);

        try {
            return response()->json([
                'data' => $service->importSelected((int) $authUser->school_id, $validated['items']),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
