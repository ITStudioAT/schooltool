<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingEntryArea;
use App\Models\TeachingEntryGradingPart;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeachingEntryGradingPart>
 */
class TeachingEntryGradingPartFactory extends Factory
{
    protected $model = TeachingEntryGradingPart::class;

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
            'teaching_entry_area_id' => fn (array $attributes) => TeachingEntryArea::factory()->create([
                'school_id' => $attributes['school_id'],
                'schoolyear_id' => $attributes['schoolyear_id'],
                'user_id' => $attributes['user_id'],
            ])->id,
            'name' => fake()->unique()->words(2, true),
        ];
    }
}
