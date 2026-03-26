<?php

namespace Database\Factories;

use App\Models\RestaurantMenuPlan;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantMenuPlan>
 */
class RestaurantMenuPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('now', '+1 month');
        $end = (clone $start)->modify('+4 days');

        return [
            'school_id' => School::factory(),
            'title' => $this->faker->words(2, true),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'is_available' => false,
        ];
    }
}
