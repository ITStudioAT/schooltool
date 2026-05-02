<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\SchoolTool;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolTool>
 */
class SchoolToolFactory extends Factory
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
            'register_visible_admin' => true,
            'register_visible_user' => true,
            'register_user_test_mode' => false,
            'register_user_comming_soon' => false,
            'tutoring_visible_admin' => false,
            'tutoring_visible_user' => false,
            'tutoring_user_test_mode' => false,
            'tutoring_user_comming_soon' => false,
            'teaching_visible_admin' => false,
            'teaching_visible_user' => false,
            'teaching_user_test_mode' => false,
            'teaching_user_comming_soon' => false,
            'materials_visible_admin' => false,
            'materials_visible_user' => false,
            'materials_user_test_mode' => false,
            'materials_user_comming_soon' => false,
            'restaurant_visible_admin' => false,
            'restaurant_visible_user' => false,
            'restaurant_user_test_mode' => false,
            'restaurant_user_comming_soon' => false,
            'aba_visible_admin' => true,
            'aba_visible_user' => true,
            'aba_user_test_mode' => false,
            'aba_user_comming_soon' => false,
            'students_timetables_visible_admin' => false,
            'students_timetables_visible_user' => false,
            'students_timetables_user_test_mode' => false,
            'students_timetables_user_comming_soon' => false,
            'tutoring_student_must_be_confirmed' => false,
            'tutoring_confirmer_email' => null,
            'tutoring_max_offers_per_student' => 0,
            'material_max_file_upload_size' => 20480,
        ];
    }

    /**
     * Indicate that tutoring students must be confirmed.
     */
    public function requiresConfirmation(): static
    {
        return $this->state(fn (array $attributes) => [
            'tutoring_student_must_be_confirmed' => true,
            'tutoring_confirmer_email' => $this->faker->safeEmail(),
        ]);
    }

    /**
     * Set a maximum number of offers per student.
     */
    public function withMaxOffers(int $max): static
    {
        return $this->state(fn (array $attributes) => [
            'tutoring_max_offers_per_student' => $max,
        ]);
    }
}
