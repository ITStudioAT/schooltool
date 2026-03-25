<?php

namespace Database\Factories;

use App\Models\RestaurantMenu;
use App\Models\RestaurantMenuPlan;
use App\Models\RestaurantMenuPlanEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantMenuPlanEntry>
 */
class RestaurantMenuPlanEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_menu_plan_id' => RestaurantMenuPlan::factory(),
            'plan_date' => $this->faker->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
            'restaurant_menu_id' => RestaurantMenu::factory(),
            'price_override' => null,
        ];
    }
}
