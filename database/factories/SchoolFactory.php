<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\School>
 */
class SchoolFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'long_name'     => $this->faker->company() . ' Schule',
            'short_name'    => strtoupper($this->faker->lexify('???')),
            'email'         => $this->faker->unique()->safeEmail(),
            'logo'          => null,
            'is_selectable' => $this->faker->boolean(90), // 90% true
        ];
    }
}
