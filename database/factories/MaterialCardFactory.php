<?php

namespace Database\Factories;

use App\Models\MaterialCard;
use App\Models\MaterialWorkspace;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaterialCard>
 */
class MaterialCardFactory extends Factory
{
    protected $model = MaterialCard::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'user_id' => User::factory(),
            'workspace_id' => function (array $attributes): ?int {
                $userId = (int) ($attributes['user_id'] ?? 0);
                if ($userId <= 0) {
                    return null;
                }

                return (int) MaterialWorkspace::query()->firstOrCreate(
                    [
                        'user_id' => $userId,
                        'name' => 'Workspace',
                    ],
                    [
                        'is_default' => true,
                    ]
                )->id;
            },
            'title' => fake()->sentence(4),
            'source_url' => fake()->url(),
            'source_text' => fake()->sentence(10),
            'subject' => fake()->randomElement(['Deutsch', 'Mathematik', 'Englisch', null]),
            'area' => fake()->randomElement(['Grammatik', 'Geometrie', 'Vokabeln', null]),
            'unit' => fake()->randomElement(['Einheit 1', 'Einheit 2', null]),
            'type' => fake()->randomElement(['Arbeitsblatt', 'Einführung', 'Test', null]),
            'status' => fake()->randomElement(MaterialCard::statusValues()),
            'notes' => fake()->optional()->sentence(8),
            'keywords' => [],
        ];
    }
}
