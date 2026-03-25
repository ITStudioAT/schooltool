<?php

namespace App\Http\Controllers\Admin\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Restaurant\RestaurantCategoryResource;
use App\Http\Resources\Admin\Restaurant\RestaurantIngredientIconResource;
use App\Services\RestaurantService;
use Illuminate\Http\JsonResponse;

class RestaurantSettingsController extends Controller
{
    public function index(RestaurantService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $settings = $service->settingsForUser($authUser);

        return response()->json([
            'categories' => RestaurantCategoryResource::collection($settings['categories']),
            'ingredient_icons' => RestaurantIngredientIconResource::collection($settings['ingredient_icons']),
            'allergen_options' => $settings['allergen_options'],
            'allergen_suggestions' => $settings['allergen_suggestions'],
            'user_settings' => $settings['user_settings'],
            'can_manage_user_settings' => $settings['can_manage_user_settings'],
            'online_settings' => $settings['online_settings'],
            'can_manage_online_settings' => $settings['can_manage_online_settings'],
            'stats' => $settings['stats'],
        ]);
    }
}
