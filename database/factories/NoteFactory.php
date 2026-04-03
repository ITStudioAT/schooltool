<?php

namespace Database\Factories;

use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
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
            'title' => $this->faker->sentence(3),
            'content' => $this->faker->paragraphs(2, true),
            'is_pinned' => $this->faker->boolean(20), // 20% chance of being pinned
        ];
    }

    /**
     * Indicate that the note is pinned.
     */
    public function pinned(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pinned' => true,
        ]);
    }

    /**
     * Indicate that the note is not pinned.
     */
    public function unpinned(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pinned' => false,
        ]);
    }

    /**
     * Indicate that the note belongs to a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
