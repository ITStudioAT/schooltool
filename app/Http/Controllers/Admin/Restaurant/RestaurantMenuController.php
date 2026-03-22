<?php

namespace App\Http\Controllers\Admin\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Restaurant\StoreRestaurantMenuRequest;
use App\Http\Requests\Admin\Restaurant\UpdateRestaurantMenuRequest;
use App\Http\Resources\Admin\Restaurant\RestaurantMenuResource;
use App\Models\RestaurantMenu;
use App\Services\RestaurantService;
use Illuminate\Http\JsonResponse;

class RestaurantMenuController extends Controller
{
    public function index(RestaurantService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        return response()->json([
            'data' => RestaurantMenuResource::collection($service->menusForUser($authUser)),
        ]);
    }

    public function store(StoreRestaurantMenuRequest $request, RestaurantService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $menu = $service->createMenuForUser($authUser, $request->validated());

        return response()->json([
            'data' => new RestaurantMenuResource($menu),
        ], 201);
    }

    public function update(UpdateRestaurantMenuRequest $request, RestaurantMenu $menu, RestaurantService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $menu = $service->updateMenuForUser($authUser, $menu, $request->validated());

        return response()->json([
            'data' => new RestaurantMenuResource($menu),
        ]);
    }

    public function destroy(RestaurantMenu $menu, RestaurantService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $service->deleteMenuForUser($authUser, $menu);

        return response()->json(null, 204);
    }
}
