<?php

namespace App\Http\Controllers\Admin\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Restaurant\StoreRestaurantMenuPlanRequest;
use App\Http\Requests\Admin\Restaurant\UpdateRestaurantMenuPlanRequest;
use App\Http\Resources\Admin\Restaurant\RestaurantMenuPlanResource;
use App\Services\RestaurantMenuPlanService;
use Illuminate\Http\JsonResponse;

class RestaurantMenuPlanController extends Controller
{
    public function index(RestaurantMenuPlanService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $plans = $service->plansForUser($authUser);

        return response()->json([
            'data' => RestaurantMenuPlanResource::collection($plans),
        ]);
    }

    public function show(int $id, RestaurantMenuPlanService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $plan = $service->findForUser($authUser, $id);

        if (! $plan) {
            abort(404, 'Menüplan nicht gefunden.');
        }

        return response()->json([
            'data' => RestaurantMenuPlanResource::make($plan),
        ]);
    }

    public function store(StoreRestaurantMenuPlanRequest $request, RestaurantMenuPlanService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $plan = $service->createForUser($authUser, $request->validated());

        return response()->json([
            'message' => 'Menüplan wurde gespeichert.',
            'data' => RestaurantMenuPlanResource::make($plan),
        ], 201);
    }

    public function update(UpdateRestaurantMenuPlanRequest $request, int $id, RestaurantMenuPlanService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $plan = $service->updateForUser($authUser, $id, $request->validated());

        if (! $plan) {
            abort(404, 'Menüplan nicht gefunden.');
        }

        return response()->json([
            'message' => 'Menüplan wurde aktualisiert.',
            'data' => RestaurantMenuPlanResource::make($plan),
        ]);
    }

    public function destroy(int $id, RestaurantMenuPlanService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $deleted = $service->deleteForUser($authUser, $id);

        if (! $deleted) {
            abort(404, 'Menüplan nicht gefunden.');
        }

        return response()->json([
            'message' => 'Menüplan wurde gelöscht.',
        ]);
    }
}
