<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingEntryArea;
use App\Models\TeachingEntryDefinition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeachingEntryDefinition>
 */
class TeachingEntryDefinitionFactory extends Factory
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
            'schoolyear_id' => function (array $attributes) {
                return Schoolyear::factory()->create([
                    'school_id' => $attributes['school_id'],
                ])->id;
            },
            'user_id' => function (array $attributes) {
                return User::factory()->create([
                    'school_id' => $attributes['school_id'],
                    'schoolyear_id' => $attributes['schoolyear_id'],
                ])->id;
            },
            'teaching_entry_area_id' => function (array $attributes) {
                return TeachingEntryArea::query()->firstOrCreate([
                    'schoolyear_id' => $attributes['schoolyear_id'],
                    'user_id' => $attributes['user_id'],
                    'name' => 'Standard',
                ], [
                    'school_id' => $attributes['school_id'],
                ])->id;
            },
            'short_name' => $this->faker->unique()->bothify('?'),
            'name' => $this->faker->words(2, true),
            'category' => $this->faker->randomElement(['Benotung', 'Verhalten', 'Weitere']),
            'has_properties' => false,
            'properties_mode' => 'free',
            'fixed_properties' => [],
            'has_notifications' => false,
            'notification_recipients' => [],
        ];
    }
}
