<?php

namespace Database\Factories;

use App\Models\Register;
use App\Models\RegisterDate;
use App\Models\RegisterDateBooking;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RegisterDateBookingFactory extends Factory
{
    protected $model = RegisterDateBooking::class;

    public function definition(): array
    {
        $registerDate = RegisterDate::factory()->create();
        
        return [
            'register_date_id' => $registerDate->id,
            'register_id' => $registerDate->register_id,
            'user_id' => User::factory(),
            'school_id' => $registerDate->school_id,
            'schoolyear_id' => $registerDate->schoolyear_id,
            'student_first_name' => $this->faker->firstName(),
            'student_last_name' => $this->faker->lastName(),
            'student_birthdate' => $this->faker->date(),
        ];
    }
}
