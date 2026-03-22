<?php

namespace Database\Factories;

use App\Models\RestaurantMenu;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantMenu>
 */
class RestaurantMenuFactory extends Factory
{
    protected $model = RestaurantMenu::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'title' => $this->faker->unique()->words(2, true),
            'price' => $this->faker->randomFloat(2, 4, 25),
        ];
    }

    public function forSchool(School $school): static
    {
        return $this->state(fn () => [
            'school_id' => $school->id,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => [
            'school_id' => $user->school_id,
        ]);
    }
}
