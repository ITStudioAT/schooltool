<?php

namespace Database\Factories;

use App\Models\DropboxConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DropboxConnection>
 */
class DropboxConnectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => 'dbid:'.fake()->uuid(),
            'credentials' => ['refresh_token' => fake()->sha256()],
        ];
    }
}
