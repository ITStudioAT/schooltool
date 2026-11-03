<?php

namespace Database\Factories;

use App\Models\NumberSystemGameRun;
use App\Models\NumberSystemGameSession;
use App\Models\User;
use App\Services\NumberSystems\NumberSystemEngine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NumberSystemGameRun>
 */
class NumberSystemGameRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => NumberSystemGameSession::factory(),
            'user_id' => User::factory(),
            'puzzles' => function (array $attributes): array {
                $session = NumberSystemGameSession::query()->findOrFail($attributes['session_id']);
                $engine = new NumberSystemEngine;

                return array_map(fn (int $position): array => $engine->mission($session->seed, $attributes['user_id'], $position, $session->planned_minutes, $session->difficulty), range(0, $engine->count($session->planned_minutes) - 1));
            },
            'draft' => [],
            'history' => [],
        ];
    }
}
