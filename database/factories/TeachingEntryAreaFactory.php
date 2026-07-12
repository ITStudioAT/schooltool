<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingEntryArea;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeachingEntryArea>
 */
class TeachingEntryAreaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'schoolyear_id' => fn (array $attributes) => Schoolyear::factory()->create([
                'school_id' => $attributes['school_id'],
            ])->id,
            'user_id' => fn (array $attributes) => User::factory()->create([
                'school_id' => $attributes['school_id'],
                'schoolyear_id' => $attributes['schoolyear_id'],
            ])->id,
            'name' => $this->faker->unique()->words(2, true),
        ];
    }
}
