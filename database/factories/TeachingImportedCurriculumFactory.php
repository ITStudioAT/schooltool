<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\TeachingImportedCurriculum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<TeachingImportedCurriculum>
 */
class TeachingImportedCurriculumFactory extends Factory
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
            'user_id' => User::factory(),
            'curriculum_key' => (string) Str::uuid(),
            'title' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'semester_count' => 2,
            'free_weeks' => ['2025-10-06'],
            'topics' => [],
            'source_schema_version' => 1,
            'source_exported_at' => Carbon::parse('2026-04-21 10:00:00'),
            'imported_at' => Carbon::parse('2026-04-21 10:05:00'),
        ];
    }
}
