<?php

namespace App\Http\Controllers\Admin\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Restaurant\RestaurantFreeDayIndexRequest;
use App\Http\Requests\Admin\Restaurant\StoreRestaurantFreeDayRequest;
use App\Http\Resources\Admin\Restaurant\RestaurantFreeDayResource;
use App\Services\RestaurantFreeDayService;
use Illuminate\Http\JsonResponse;

class RestaurantFreeDayController extends Controller
{
    public function index(RestaurantFreeDayIndexRequest $request, RestaurantFreeDayService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $year = $request->year();
        $freeDays = $service->freeDaysForUserAndYear($authUser, $year);

        return response()->json([
            'data' => RestaurantFreeDayResource::collection($freeDays),
            'meta' => [
                'year' => $year,
                'count' => $freeDays->count(),
            ],
        ]);
    }

    public function store(StoreRestaurantFreeDayRequest $request, RestaurantFreeDayService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $result = $service->saveChangesForUser($authUser, $request->validated());
        $status = $result['mode'] === 'set' ? 201 : 200;
        $message = match ($result['mode']) {
            'unset' => 'Freie Tage wurden entfernt.',
            'bulk' => 'Aenderungen wurden gespeichert.',
            default => 'Freie Tage wurden gespeichert.',
        };

        return response()->json([
            'message' => $message,
            'meta' => [
                'mode' => $result['mode'],
                'affected_count' => $result['affected_count'],
                'set_count' => $result['set_count'],
                'unset_count' => $result['unset_count'],
                'years' => $result['years'],
            ],
        ], $status);
    }
}
