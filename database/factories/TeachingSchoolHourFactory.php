<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TeachingSchoolHour>
 */
class TeachingSchoolHourFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hour = $this->faker->numberBetween(1, 10);

        return [
            'school_id' => School::factory(),
            'hour' => $hour,
            'from' => sprintf('%02d:00:00', 7 + $hour),
            'until' => sprintf('%02d:50:00', 7 + $hour),
        ];
    }
}
