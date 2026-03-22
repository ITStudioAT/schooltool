<?php

namespace Database\Factories;

use App\Models\RestaurantCategory;
use App\Models\RestaurantFood;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantFood>
 */
class RestaurantFoodFactory extends Factory
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
            'restaurant_category_id' => function (array $attributes) {
                return RestaurantCategory::factory()->create([
                    'school_id' => $attributes['school_id'],
                ])->id;
            },
            'title' => $this->faker->unique()->words(2, true),
            'description' => $this->faker->sentence(10),
            'allergens' => ['Gluten', 'Milch'],
            'price' => $this->faker->randomFloat(2, 2, 18),
            'food_image_path' => null,
        ];
    }

    public function forSchool(School $school): static
    {
        return $this->state(fn (array $attributes) => [
            'school_id' => $school->id,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'school_id' => $user->school_id,
        ]);
    }

    public function forCategory(RestaurantCategory $category): static
    {
        return $this->state(fn (array $attributes) => [
            'school_id' => $category->school_id,
            'restaurant_category_id' => $category->id,
        ]);
    }
}
