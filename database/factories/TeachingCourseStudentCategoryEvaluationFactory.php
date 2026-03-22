<?php

namespace Database\Factories;

use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudentCategoryEvaluation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeachingCourseStudentCategoryEvaluation>
 */
class TeachingCourseStudentCategoryEvaluationFactory extends Factory
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
            'user_id' => User::factory(),
            'semester' => 1,
            'category_name' => $this->faker->words(2, true),
            'value' => $this->faker->randomElement(['Keine Bewertung', 'Offen', 'Bestanden', '1', '2', '3', '4', '5', 'Nicht bestanden']),
        ];
    }
}
