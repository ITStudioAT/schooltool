<?php

namespace Database\Factories;

use App\Models\NumberSystemGameSession;
use App\Models\TeachingCourse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NumberSystemGameSession>
 */
class NumberSystemGameSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'teaching_course_id' => TeachingCourse::factory(),
            'school_id' => fn (array $attributes): int => TeachingCourse::query()->findOrFail($attributes['teaching_course_id'])->school_id,
            'teacher_id' => fn (array $attributes): int => TeachingCourse::query()->findOrFail($attributes['teaching_course_id'])->user_id,
            'title' => 'Signal aus dem Rechenzentrum',
            'join_code' => strtoupper(bin2hex(random_bytes(5))),
            'seed' => bin2hex(random_bytes(32)),
            'engine_version' => 1,
            'planned_minutes' => 30,
            'difficulty' => 2,
            'mode' => 'solo',
            'status' => 'waiting',
        ];
    }
}
