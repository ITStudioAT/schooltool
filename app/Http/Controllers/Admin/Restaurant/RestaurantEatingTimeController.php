<?php

namespace App\Http\Controllers\Admin\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Restaurant\StoreRestaurantEatingTimeRequest;
use App\Http\Requests\Admin\Restaurant\UpdateRestaurantEatingTimeRequest;
use App\Http\Resources\Admin\Restaurant\RestaurantEatingTimeResource;
use App\Services\RestaurantEatingTimeService;
use Illuminate\Http\JsonResponse;

class RestaurantEatingTimeController extends Controller
{
    public function index(RestaurantEatingTimeService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $eatingTimes = $service->eatingTimesForUser($authUser);

        return response()->json([
            'data' => RestaurantEatingTimeResource::collection($eatingTimes),
        ]);
    }

    public function store(StoreRestaurantEatingTimeRequest $request, RestaurantEatingTimeService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $eatingTime = $service->createForUser($authUser, $request->validated()['eating_time']);

        return response()->json([
            'message' => 'Speisezeit wurde gespeichert.',
            'data' => RestaurantEatingTimeResource::make($eatingTime),
        ], 201);
    }

    public function update(UpdateRestaurantEatingTimeRequest $request, int $id, RestaurantEatingTimeService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $eatingTime = $service->updateForUser($authUser, $id, $request->validated()['eating_time']);

        if (! $eatingTime) {
            abort(404, 'Speisezeit nicht gefunden.');
        }

        return response()->json([
            'message' => 'Speisezeit wurde aktualisiert.',
            'data' => RestaurantEatingTimeResource::make($eatingTime),
        ]);
    }

    public function destroy(int $id, RestaurantEatingTimeService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $deleted = $service->deleteForUser($authUser, $id);

        if (! $deleted) {
            abort(404, 'Speisezeit nicht gefunden.');
        }

        return response()->json([
            'message' => 'Speisezeit wurde gelöscht.',
        ]);
    }
}
