<?php

namespace App\Http\Controllers\Admin\Restaurant;

use App\Http\Controllers\Controller;
use App\Services\LegacyRestaurantStatsService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class RestaurantCdgymLegacyStatsController extends Controller
{
    public function __invoke(LegacyRestaurantStatsService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $authUser->loadMissing('selectedSchool');

        if ($authUser->selectedSchool?->long_name !== 'Christian-Doppler-Gymnasium Salzburg') {
            abort(403, 'Diese Live-Daten sind nur für das Christian-Doppler-Gymnasium Salzburg verfügbar.');
        }

        try {
            return response()->json([
                'data' => $service->remoteStats((int) $authUser->school_id),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 502);
        }
    }
}
