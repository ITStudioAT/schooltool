<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingPersonalAppointment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeachingPersonalAppointment>
 */
class TeachingPersonalAppointmentFactory extends Factory
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
            'schoolyear_id' => Schoolyear::factory(),
            'user_id' => User::factory(),
            'kind' => 'standby',
            'title' => null,
            'date' => '2026-10-12',
            'starts_at' => '12:30',
            'ends_at' => '13:20',
            'repeat_until' => null,
        ];
    }
}
