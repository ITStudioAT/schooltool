<?php

namespace Database\Factories;

use App\Models\TeachingCourseStudentEntryNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeachingCourseStudentEntryNotification>
 */
class TeachingCourseStudentEntryNotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipient_type' => 'student',
            'recipient_label' => 'Schüler:in',
            'email' => fake()->unique()->safeEmail(),
            'informed_at' => now(),
            'opened_at' => null,
            'confirmed_at' => null,
            'confirmation_method' => null,
            'confirmed_by_user_id' => null,
            'confirmed_by_label' => null,
        ];
    }
}
