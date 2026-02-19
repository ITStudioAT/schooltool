<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SchoolTool>
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
