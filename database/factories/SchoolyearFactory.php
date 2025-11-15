<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\Schoolyear;
use Illuminate\Database\Eloquent\Factories\Factory;

class SchoolyearFactory extends Factory
{
    protected $model = Schoolyear::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => $this->faker->year() . '/' . ($this->faker->year() + 1),
            'is_active' => true,
        ];
    }
}
