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
            'visible_start_at' => null,
            'visible_end_at' => null,
            'order_start_at' => null,
            'order_end_at' => null,
            'use_individual_schedule_values' => false,
            'visibility_start_mode' => 'when_available',
            'visibility_start_week_offset' => null,
            'visibility_start_day_of_week' => null,
            'visibility_start_time' => null,
            'order_start_mode' => 'when_available',
            'order_start_week_offset' => null,
            'order_start_day_of_week' => null,
            'order_start_time' => null,
            'order_end_week_offset' => 1,
            'order_end_day_of_week' => 5,
            'order_end_time' => '17:00:00',
            'visibility_end_mode' => 'plan_end',
        ];
    }
}
