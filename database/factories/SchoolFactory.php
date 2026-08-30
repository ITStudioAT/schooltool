<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<School>
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
            'long_name' => 'Test Schule '.rand(100, 999),
            'short_name' => strtoupper(substr(md5(uniqid()), 0, 3)),
            'email' => 'school'.rand(1000, 9999).'@test.local',
            'logo' => null,
            'color' => '#1976D2',
            'is_selectable' => rand(0, 10) > 1, // 90% true
        ];
    }
}
