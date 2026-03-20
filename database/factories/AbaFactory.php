<?php

namespace Database\Factories;

use App\Models\Aba;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Aba>
 */
class AbaFactory extends Factory
{
    protected $model = Aba::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'schoolyear_id' => Schoolyear::factory(),
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'student_name' => fake()->name(),
            'created_on' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'evaluated_on' => fake()->optional()->dateTimeBetween('-1 year', 'now')?->format('Y-m-d'),
        ];
    }
}
