<?php

namespace Database\Factories;

use App\Models\Register;
use App\Models\RegisterDate;
use Illuminate\Database\Eloquent\Factories\Factory;

class RegisterDateFactory extends Factory
{
    protected $model = RegisterDate::class;

    public function definition(): array
    {
        $register = Register::factory()->create();

        return [
            'register_id' => $register->id,
            'school_id' => $register->school_id,
            'schoolyear_id' => $register->schoolyear_id,
            'date' => $this->faker->date(),
            'from' => $this->faker->time('H:i'),
            'to' => $this->faker->time('H:i'),
            'supervisor' => $this->faker->name(),
            'max_registrations' => $this->faker->numberBetween(10, 50),
        ];
    }
}
