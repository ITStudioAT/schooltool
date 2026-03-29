<?php

namespace Database\Factories;

use App\Models\RestaurantEatingTime;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantEatingTime>
 */
class RestaurantEatingTimeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hours = $this->faker->numberBetween(10, 14);
        $minutes = $this->faker->randomElement([0, 15, 30, 45]);

        return [
            'school_id' => School::factory(),
            'eating_time' => sprintf('%02d:%02d:00', $hours, $minutes),
        ];
    }
}
