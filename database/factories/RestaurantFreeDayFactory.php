<?php

namespace Database\Factories;

use App\Models\RestaurantFreeDay;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantFreeDay>
 */
class RestaurantFreeDayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'free_date' => $this->faker->dateTimeBetween('first day of january this year', 'last day of december this year')
                ->format('Y-m-d'),
        ];
    }

    public function forSchool(School $school): static
    {
        return $this->state(fn (): array => [
            'school_id' => $school->id,
        ]);
    }
}
