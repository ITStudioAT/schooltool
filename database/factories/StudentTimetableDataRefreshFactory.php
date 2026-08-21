<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\StudentTimetableDataRefresh;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentTimetableDataRefresh>
 */
class StudentTimetableDataRefreshFactory extends Factory
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
            'status' => 'completed',
            'total_students' => 10,
            'processed_students' => 10,
            'study_selections_updated' => 8,
            'course_results_updated' => 7,
            'error_message' => null,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ];
    }
}
