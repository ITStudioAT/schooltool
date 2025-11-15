<?php

namespace Database\Factories;

use App\Models\Register;
use App\Models\School;
use App\Models\Schoolyear;
use Illuminate\Database\Eloquent\Factories\Factory;

class RegisterFactory extends Factory
{
    protected $model = Register::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'schoolyear_id' => Schoolyear::factory(),
            'name' => $this->faker->words(3, true),
            'is_active' => true,
            'show_phone' => true,
            'must_phone' => false,
            'show_student_last_name' => true,
            'must_student_last_name' => false,
            'show_student_first_name' => true,
            'must_student_first_name' => false,
            'show_booked' => true,
            'show_end_time' => true,
            'show_supervisor' => true,
        ];
    }
}
