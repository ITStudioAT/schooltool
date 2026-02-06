<?php

namespace App\Services;

use App\Models\TeachingCourse;
use App\Models\User;
use Illuminate\Support\Str;

class TeachingService
{
    /**
     * Check if any of the given schema IDs are used by courses belonging to the user.
     * Returns the names of schemas that are in use, or an empty collection.
     */
    public function schemasInUse(User $user, array $schemaIds): \Illuminate\Support\Collection
    {
        $usedIds = TeachingCourse::where('user_id', $user->id)
            ->whereIn('teaching_schema_id', $schemaIds)
            ->pluck('teaching_schema_id')
            ->unique();

        if ($usedIds->isEmpty()) {
            return collect();
        }

        return collect($user->teaching_schemas ?? [])
            ->whereIn('id', $usedIds)
            ->pluck('name');
    }

    /**
     * Check if removing schemas (old vs new) would violate course dependencies.
     * Returns the names of schemas that cannot be removed, or an empty collection.
     */
    public function hasDependencies(User $user, array $newSchemas): \Illuminate\Support\Collection
    {
        $oldIds = collect($user->teaching_schemas ?? [])->pluck('id');
        $newIds = collect($newSchemas)->pluck('id');
        $removedIds = $oldIds->diff($newIds)->values()->all();

        if (empty($removedIds)) {
            return collect();
        }

        return $this->schemasInUse($user, $removedIds);
    }

    /**
     * Check if any "Standard" schema has been renamed in the new schemas.
     */
    public function standardSchemaRenamed(User $user, array $newSchemas): bool
    {
        $oldSchemas = collect($user->teaching_schemas ?? []);
        $newSchemasCollection = collect($newSchemas);

        return $oldSchemas
            ->filter(fn ($s) => ($s['name'] ?? '') === 'Standard')
            ->contains(fn ($old) => $newSchemasCollection->contains(fn ($new) => $new['id'] === $old['id'] && $new['name'] !== 'Standard'));
    }

    /**
     * Ensure the user has at least the default "Standard" schema.
     * Creates and saves it if none exist.
     */
    public function ensureDefaultSchema(User $user): void
    {
        if (! empty($user->teaching_schemas)) {
            return;
        }

        $user->teaching_schemas = [self::defaultSchema()];
        $user->save();
    }

    public static function defaultSchema(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'name' => 'Standard',
            'works' => [
                [
                    'short_name' => 'MA',
                    'name' => 'Mitarbeit',
                    'calculation' => 'points',
                    'grades' => [
                        ['grade' => '-', 'name' => 'Minus', 'value' => '-1'],
                        ['grade' => '+', 'name' => 'Plus', 'value' => '1'],
                        ['grade' => '~', 'name' => 'Mittel', 'value' => '0'],
                    ],
                    'points_table' => [
                        ['grade' => '1', 'min_points' => 3],
                        ['grade' => '2', 'min_points' => 2],
                        ['grade' => '3', 'min_points' => 1],
                        ['grade' => '4', 'min_points' => 0],
                        ['grade' => '5', 'min_points' => -999],
                    ],
                ],
                [
                    'short_name' => 'SA',
                    'name' => 'Schularbeit',
                    'calculation' => 'average',
                    'grades' => [
                        ['grade' => '1', 'name' => 'Sehr gut', 'value' => '1'],
                        ['grade' => '2', 'name' => 'Gut', 'value' => '2'],
                        ['grade' => '3', 'name' => 'Befriedigend', 'value' => '3'],
                        ['grade' => '4', 'name' => 'Genügend', 'value' => '4'],
                        ['grade' => '5', 'name' => 'Nicht genügend', 'value' => '5'],
                    ],
                    'points_table' => [],
                ],
            ],
            'grading' => [
                'semester_count' => 2,
                'semester_1_weight' => 40,
                'semester_2_weight' => 60,
                'categories' => [
                    [
                        'name' => 'Schularbeiten',
                        'weight' => 50,
                        'calculation' => 'mean',
                        'works' => [['short_name' => 'SA', 'factor' => 100]],
                    ],
                    [
                        'name' => 'Mitarbeit',
                        'weight' => 50,
                        'calculation' => 'mean',
                        'works' => [['short_name' => 'MA', 'factor' => 100]],
                    ],
                ],
            ],
        ];
    }
}
