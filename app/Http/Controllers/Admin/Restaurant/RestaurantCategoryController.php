<?php

namespace App\Http\Controllers\Admin\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Restaurant\StoreRestaurantCategoryRequest;
use App\Http\Requests\Admin\Restaurant\UpdateRestaurantCategoryRequest;
use App\Http\Resources\Admin\Restaurant\RestaurantCategoryResource;
use App\Models\RestaurantCategory;
use App\Services\RestaurantService;
use Illuminate\Http\JsonResponse;

class RestaurantCategoryController extends Controller
{
    public function store(StoreRestaurantCategoryRequest $request, RestaurantService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $category = $service->createCategoryForUser($authUser, $request->validated());

        return response()->json([
            'data' => new RestaurantCategoryResource($category),
        ], 201);
    }

    public function update(UpdateRestaurantCategoryRequest $request, RestaurantCategory $category, RestaurantService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $category = $service->updateCategoryForUser($authUser, $category, $request->validated());

        return response()->json([
            'data' => new RestaurantCategoryResource($category),
        ]);
    }

    public function destroy(RestaurantCategory $category, RestaurantService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $service->deleteCategoryForUser($authUser, $category);

        return response()->json(null, 204);
    }
}
