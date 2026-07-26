<?php

namespace Database\Factories;

use App\Models\MaterialV2Category;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MaterialV2Category>
 */
class MaterialV2CategoryFactory extends Factory
{
    protected $model = MaterialV2Category::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'school_id' => School::factory(),
            'user_id' => User::factory(),
            'name' => Str::title($name),
            'normalized_name' => Str::lower($name),
        ];
    }
}
