<?php

namespace App\Http\Controllers\Admin\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Restaurant\StoreRestaurantFoodRequest;
use App\Http\Requests\Admin\Restaurant\UpdateRestaurantFoodRequest;
use App\Http\Resources\Admin\Restaurant\RestaurantFoodResource;
use App\Models\RestaurantFood;
use App\Services\RestaurantService;
use Illuminate\Http\JsonResponse;

class RestaurantFoodController extends Controller
{
    public function index(RestaurantService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        return response()->json([
            'data' => RestaurantFoodResource::collection($service->foodsForUser($authUser)),
        ]);
    }

    public function store(StoreRestaurantFoodRequest $request, RestaurantService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $food = $service->createFoodForUser($authUser, $request->validated());

        return response()->json([
            'data' => new RestaurantFoodResource($food),
        ], 201);
    }

    public function update(UpdateRestaurantFoodRequest $request, RestaurantFood $food, RestaurantService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $food = $service->updateFoodForUser($authUser, $food, $request->validated());

        return response()->json([
            'data' => new RestaurantFoodResource($food),
        ]);
    }

    public function destroy(RestaurantFood $food, RestaurantService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $service->deleteFoodForUser($authUser, $food);

        return response()->json(null, 204);
    }
}
