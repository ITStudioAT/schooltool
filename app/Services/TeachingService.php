<?php

namespace App\Services;

use App\Models\TeachingCourse;
use App\Models\TeachingSchema;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TeachingService
{
    public function schemasForUser(User $user, ?int $schoolyearId = null): Collection
    {
        $rows = $this->schemaRows($user, $schoolyearId);

        return $rows->map(function (TeachingSchema $schema) {
            $works = $this->normalizeWorks(is_array($schema->works) ? $schema->works : []);
            $grading = $this->normalizeGrading(is_array($schema->grading) ? $schema->grading : [], $works);
            $works = $this->ensureMandatoryNaGradesForRequiredCategories($works, $grading);

            return [
                'id' => (string) $schema->schema_id,
                'name' => (string) $schema->name,
                'works' => $works,
                'grading' => $grading,
            ];
        })
            ->values();
    }

    public function schemaIdsForUser(User $user, ?int $schoolyearId = null): Collection
    {
        return $this->schemasForUser($user, $schoolyearId)
            ->pluck('id')
            ->filter(fn ($id) => is_scalar($id) && (string) $id !== '')
            ->map(fn ($id) => (string) $id)
            ->values();
    }

    public function schemaById(User $user, ?string $schemaId, ?int $schoolyearId = null): ?array
    {
        if (! $schemaId) {
            return null;
        }

        $found = $this->schemasForUser($user, $schoolyearId)
            ->firstWhere('id', (string) $schemaId);

        return is_array($found) ? $found : null;
    }

    public function saveSchemas(User $user, array $schemas, ?int $schoolyearId = null): void
    {
        $schoolyearId = $this->resolveSchoolyearId($user, $schoolyearId);
        if (! $schoolyearId) {
            return;
        }

        $rows = collect($schemas)
            ->filter(fn ($schema) => is_array($schema))
            ->map(function (array $schema) use ($user, $schoolyearId) {
                $schemaId = (string) ($schema['id'] ?? '');
                if ($schemaId === '') {
                    return null;
                }

                $works = $this->normalizeWorks(is_array($schema['works'] ?? null) ? $schema['works'] : []);
                $grading = $this->normalizeGrading(is_array($schema['grading'] ?? null) ? $schema['grading'] : [], $works);
                $works = $this->ensureMandatoryNaGradesForRequiredCategories($works, $grading);

                return [
                    'school_id' => $user->school_id,
                    'schoolyear_id' => $schoolyearId,
                    'user_id' => $user->id,
                    'schema_id' => $schemaId,
                    'name' => (string) ($schema['name'] ?? 'Standard'),
                    'works' => $works,
                    'grading' => $grading,
                ];
            })
            ->filter()
            ->values();

        $incomingIds = $rows->pluck('schema_id')->all();

        $query = TeachingSchema::query()
            ->where('user_id', $user->id)
            ->where('schoolyear_id', $schoolyearId);

        if (! empty($incomingIds)) {
            $query->whereNotIn('schema_id', $incomingIds)->delete();
        } else {
            $query->delete();
        }

        foreach ($rows as $row) {
            TeachingSchema::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'schoolyear_id' => $schoolyearId,
                    'schema_id' => $row['schema_id'],
                ],
                [
                    'school_id' => $row['school_id'],
                    'name' => $row['name'],
                    'works' => $row['works'],
                    'grading' => $row['grading'],
                ]
            );
        }
    }

    /**
     * Check if any of the given schema IDs are used by courses belonging to the user.
     * Returns the names of schemas that are in use, or an empty collection.
     */
    public function schemasInUse(User $user, array $schemaIds, ?int $schoolyearId = null): Collection
    {
        $schoolyearId = $this->resolveSchoolyearId($user, $schoolyearId);

        $usedIds = TeachingCourse::where('user_id', $user->id)
            ->when($schoolyearId, fn ($query) => $query->where('schoolyear_id', $schoolyearId))
            ->whereIn('teaching_schema_id', $schemaIds)
            ->pluck('teaching_schema_id')
            ->unique();

        if ($usedIds->isEmpty()) {
            return collect();
        }

        return $this->schemasForUser($user, $schoolyearId)
            ->whereIn('id', $usedIds)
            ->pluck('name');
    }

    /**
     * Check if removing schemas (old vs new) would violate course dependencies.
     * Returns the names of schemas that cannot be removed, or an empty collection.
     */
    public function hasDependencies(User $user, array $newSchemas, ?int $schoolyearId = null): Collection
    {
        $oldIds = $this->schemaIdsForUser($user, $schoolyearId);
        $newIds = collect($newSchemas)->pluck('id');
        $removedIds = $oldIds->diff($newIds)->values()->all();

        if (empty($removedIds)) {
            return collect();
        }

        return $this->schemasInUse($user, $removedIds, $schoolyearId);
    }

    /**
     * Check if any "Standard" schema has been renamed in the new schemas.
     */
    public function standardSchemaRenamed(User $user, array $newSchemas, ?int $schoolyearId = null): bool
    {
        $oldSchemas = $this->schemasForUser($user, $schoolyearId);
        $newSchemasCollection = collect($newSchemas);

        return $oldSchemas
            ->filter(fn ($s) => ($s['name'] ?? '') === 'Standard')
            ->contains(fn ($old) => $newSchemasCollection->contains(fn ($new) => $new['id'] === $old['id'] && $new['name'] !== 'Standard'));
    }

    /**
     * Ensure the user has at least the default "Standard" schema.
     * Creates and saves it if none exist.
     */
    public function ensureDefaultSchema(User $user, ?int $schoolyearId = null): void
    {
        $schoolyearId = $this->resolveSchoolyearId($user, $schoolyearId);
        if (! $schoolyearId) {
            return;
        }

        $rows = $this->schemaRows($user, $schoolyearId);
        if ($rows->isNotEmpty()) {
            return;
        }

        $this->saveSchemas($user, [self::defaultSchema()], $schoolyearId);
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
                    'require_all_entries' => true,
                    'default_grade' => null,
                    'grades' => [
                        ['grade' => '-', 'name' => 'Minus', 'value' => '-1'],
                        ['grade' => '+', 'name' => 'Plus', 'value' => '1'],
                        ['grade' => '~', 'name' => 'Mittel', 'value' => '0'],
                        ['grade' => 'NA', 'name' => 'NICHT ABGEGEBEN', 'value' => ''],
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
                    'require_all_entries' => true,
                    'default_grade' => null,
                    'grades' => [
                        ['grade' => '1', 'name' => 'Sehr gut', 'value' => '1'],
                        ['grade' => '2', 'name' => 'Gut', 'value' => '2'],
                        ['grade' => '3', 'name' => 'Befriedigend', 'value' => '3'],
                        ['grade' => '4', 'name' => 'Genügend', 'value' => '4'],
                        ['grade' => '5', 'name' => 'Nicht genügend', 'value' => '5'],
                        ['grade' => 'NA', 'name' => 'NICHT ABGEGEBEN', 'value' => ''],
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
                        'require_all_entries' => true,
                        'calculation' => 'mean',
                        'works' => [['short_name' => 'SA', 'factor' => 100]],
                    ],
                    [
                        'name' => 'Mitarbeit',
                        'weight' => 50,
                        'require_all_entries' => true,
                        'calculation' => 'mean',
                        'works' => [['short_name' => 'MA', 'factor' => 100]],
                    ],
                ],
            ],
        ];
    }

    private function resolveSchoolyearId(User $user, ?int $schoolyearId = null): ?int
    {
        return $schoolyearId ?? $user->schoolyear_id;
    }

    private function schemaRows(User $user, ?int $schoolyearId = null): Collection
    {
        $schoolyearId = $this->resolveSchoolyearId($user, $schoolyearId);
        if (! $schoolyearId) {
            return collect();
        }

        return TeachingSchema::query()
            ->where('user_id', $user->id)
            ->where('schoolyear_id', $schoolyearId)
            ->orderBy('id')
            ->get();
    }

    private function normalizeGrading(array $grading, array $works): array
    {
        $categories = is_array($grading['categories'] ?? null) ? $grading['categories'] : [];
        $grading['categories'] = collect($categories)
            ->filter(fn ($category) => is_array($category))
            ->map(function (array $category) use ($works) {
                $normalizedWorks = $this->normalizeCategoryWorks(is_array($category['works'] ?? null) ? $category['works'] : []);
                $hasOwnRequireAll = array_key_exists('require_all_entries', $category);

                $category['works'] = $normalizedWorks;
                $category['require_all_entries'] = $hasOwnRequireAll
                    ? (bool) ($category['require_all_entries'] ?? false)
                    : $this->inferCategoryRequireAllEntriesFromWorks($normalizedWorks, $works);

                return $category;
            })
            ->values()
            ->all();

        return $grading;
    }

    private function normalizeCategoryWorks(array $works): array
    {
        return collect($works)
            ->map(function ($work) {
                if (is_string($work)) {
                    $shortName = trim($work);

                    return $shortName === '' ? null : ['short_name' => $shortName, 'factor' => 100];
                }

                if (! is_array($work)) {
                    return null;
                }

                $shortName = trim((string) ($work['short_name'] ?? ''));
                if ($shortName === '') {
                    return null;
                }

                $factorRaw = $work['factor'] ?? 100;
                $factor = is_numeric($factorRaw) ? (int) $factorRaw : 100;

                return ['short_name' => $shortName, 'factor' => $factor];
            })
            ->filter(fn ($work) => is_array($work))
            ->values()
            ->all();
    }

    private function inferCategoryRequireAllEntriesFromWorks(array $categoryWorks, array $works): bool
    {
        if (empty($categoryWorks) || empty($works)) {
            return false;
        }

        $worksByType = collect($works)
            ->filter(fn ($work) => is_array($work) && trim((string) ($work['short_name'] ?? '')) !== '')
            ->keyBy(fn ($work) => trim((string) ($work['short_name'] ?? '')));

        return collect($categoryWorks)->contains(function ($workItem) use ($worksByType) {
            if (! is_array($workItem)) {
                return false;
            }

            $shortName = trim((string) ($workItem['short_name'] ?? ''));
            if ($shortName === '') {
                return false;
            }

            $work = $worksByType->get($shortName);

            return is_array($work) && (bool) ($work['require_all_entries'] ?? false);
        });
    }

    private function ensureMandatoryNaGradesForRequiredCategories(array $works, array $grading): array
    {
        $requiredTypes = collect(is_array($grading['categories'] ?? null) ? $grading['categories'] : [])
            ->filter(fn ($category) => is_array($category) && (bool) ($category['require_all_entries'] ?? false))
            ->flatMap(function (array $category) {
                return collect(is_array($category['works'] ?? null) ? $category['works'] : [])
                    ->map(function ($workItem) {
                        if (is_string($workItem)) {
                            return trim($workItem);
                        }
                        if (! is_array($workItem)) {
                            return '';
                        }

                        return trim((string) ($workItem['short_name'] ?? ''));
                    });
            })
            ->filter(fn ($shortName) => $shortName !== '')
            ->unique()
            ->values()
            ->all();

        if (empty($requiredTypes)) {
            return $works;
        }

        return collect($works)
            ->map(function ($work) use ($requiredTypes) {
                if (! is_array($work)) {
                    return $work;
                }

                $shortName = trim((string) ($work['short_name'] ?? ''));
                if ($shortName === '' || ! in_array($shortName, $requiredTypes, true)) {
                    return $work;
                }

                $grades = is_array($work['grades'] ?? null) ? $work['grades'] : [];
                $naIndex = collect($grades)->search(function ($grade) {
                    if (! is_array($grade)) {
                        return false;
                    }

                    return strtoupper(trim((string) ($grade['grade'] ?? ''))) === 'NA';
                });

                if ($naIndex === false) {
                    $grades[] = ['grade' => 'NA', 'name' => 'NICHT ABGEGEBEN', 'value' => ''];
                } else {
                    $existing = is_array($grades[$naIndex] ?? null) ? $grades[$naIndex] : [];
                    $grades[$naIndex] = array_merge($existing, [
                        'grade' => 'NA',
                        'name' => 'NICHT ABGEGEBEN',
                    ]);
                }

                $work['grades'] = $grades;

                return $work;
            })
            ->values()
            ->all();
    }

    private function normalizeWorks(array $works): array
    {
        return collect($works)
            ->filter(fn ($work) => is_array($work))
            ->map(function (array $work) {
                $work['require_all_entries'] = (bool) ($work['require_all_entries'] ?? false);

                $grades = is_array($work['grades'] ?? null) ? $work['grades'] : [];
                if ($work['require_all_entries']) {
                    $naIndex = collect($grades)->search(function ($grade) {
                        if (! is_array($grade)) {
                            return false;
                        }

                        $key = strtoupper(trim((string) ($grade['grade'] ?? '')));

                        return $key === 'NA';
                    });

                    if ($naIndex === false) {
                        $grades[] = ['grade' => 'NA', 'name' => 'NICHT ABGEGEBEN', 'value' => ''];
                    } else {
                        $existing = is_array($grades[$naIndex] ?? null) ? $grades[$naIndex] : [];
                        $grades[$naIndex] = array_merge($existing, [
                            'grade' => 'NA',
                            'name' => 'NICHT ABGEGEBEN',
                        ]);
                    }
                }

                $work['grades'] = $grades;

                $defaultGrade = trim((string) ($work['default_grade'] ?? ''));
                if ($defaultGrade === '') {
                    $work['default_grade'] = null;

                    return $work;
                }

                $matchedGrade = collect($grades)->first(function ($grade) use ($defaultGrade) {
                    if (! is_array($grade)) {
                        return false;
                    }

                    $key = strtoupper(trim((string) ($grade['grade'] ?? '')));

                    return $key === strtoupper($defaultGrade);
                });

                $work['default_grade'] = is_array($matchedGrade)
                    ? trim((string) ($matchedGrade['grade'] ?? '')) ?: null
                    : null;

                return $work;
            })
            ->values()
            ->all();
    }
}
