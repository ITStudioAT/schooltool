<?php

namespace App\Http\Controllers\Admin\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Restaurant\StoreRestaurantIngredientIconRequest;
use App\Http\Requests\Admin\Restaurant\UpdateRestaurantIngredientIconRequest;
use App\Http\Resources\Admin\Restaurant\RestaurantIngredientIconResource;
use App\Models\RestaurantIngredientIcon;
use App\Services\RestaurantService;
use Illuminate\Http\JsonResponse;

class RestaurantIngredientIconController extends Controller
{
    public function store(StoreRestaurantIngredientIconRequest $request, RestaurantService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $ingredientIcon = $service->createIngredientIconForUser($authUser, $request->validated());

        return response()->json([
            'data' => new RestaurantIngredientIconResource($ingredientIcon),
        ], 201);
    }

    public function update(UpdateRestaurantIngredientIconRequest $request, RestaurantIngredientIcon $ingredient_icon, RestaurantService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $ingredientIcon = $service->updateIngredientIconForUser($authUser, $ingredient_icon, $request->validated());

        return response()->json([
            'data' => new RestaurantIngredientIconResource($ingredientIcon),
        ]);
    }

    public function destroy(RestaurantIngredientIcon $ingredient_icon, RestaurantService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $service->deleteIngredientIconForUser($authUser, $ingredient_icon);

        return response()->json(null, 204);
    }
}
