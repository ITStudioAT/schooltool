<?php

namespace Database\Factories;

use App\Models\TeachingCourse;
use App\Models\TeachingCourseWork;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeachingCourseWork>
 */
class TeachingCourseWorkFactory extends Factory
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
            'type' => 'MA',
            'title' => 'Leistungsarbeit',
            'groups' => [],
        ];
    }
}
