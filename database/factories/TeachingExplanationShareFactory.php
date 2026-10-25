<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\TeachingExplanationShare;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TeachingExplanationShare>
 */
class TeachingExplanationShareFactory extends Factory
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
            'user_id' => User::factory(),
            'explanation' => 'decimal-to-binary',
            'token' => Str::random(48),
            'access_mode' => 'authenticated',
            'is_active' => false,
        ];
    }
}
