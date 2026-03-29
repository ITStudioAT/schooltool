<?php

namespace Database\Factories;

use App\Models\RestaurantIngredientIcon;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantIngredientIcon>
 */
class RestaurantIngredientIconFactory extends Factory
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
            'title' => $this->faker->unique()->randomElement([
                'Schwein',
                'Rind',
                'Fisch',
                'Vegan',
            ]),
            'image_path' => null,
            'sort_order' => $this->faker->numberBetween(0, 50),
        ];
    }

    public function forSchool(School $school): static
    {
        return $this->state(fn (array $attributes) => [
            'school_id' => $school->id,
        ]);
    }
}
