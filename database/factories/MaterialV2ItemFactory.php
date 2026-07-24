<?php

namespace Database\Factories;

use App\Models\MaterialV2Item;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaterialV2Item>
 */
class MaterialV2ItemFactory extends Factory
{
    protected $model = MaterialV2Item::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'category' => fake()->optional()->word(),
            'description' => fake()->optional()->sentence(10),
            'user_keywords' => fake()->words(3),
            'generated_keywords' => fake()->words(5),
            'search_text' => fake()->paragraph(),
            'processing_status' => MaterialV2Item::STATUS_READY,
            'processing_error' => null,
            'processing_started_at' => now(),
            'processed_at' => now(),
        ];
    }
}
