<?php

namespace App\Services;

use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingCourseWork;
use App\Models\TeachingSchema;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

        $oldSchemaRows = $this->schemaRows($user, $schoolyearId)->keyBy('schema_id');

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
            $oldSchema = $oldSchemaRows->get($row['schema_id']);
            if ($oldSchema) {
                $oldWorks = is_array($oldSchema->works) ? $oldSchema->works : [];
                $this->cascadeWorkChanges($user, $schoolyearId, $row['schema_id'], $oldWorks, $row['works']);
            }

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
     * Cascade work definition changes (short_name renames, grade key renames/removals)
     * to all courses, course works, and student entries that use the given schema.
     *
     * @param  array<int, array<string, mixed>>  $oldWorks
     * @param  array<int, array<string, mixed>>  $newWorks
     */
    private function cascadeWorkChanges(User $user, int $schoolyearId, string $schemaId, array $oldWorks, array $newWorks): void
    {
        $courseIds = TeachingCourse::where('user_id', $user->id)
            ->where('schoolyear_id', $schoolyearId)
            ->where('teaching_schema_id', $schemaId)
            ->pluck('id');

        if ($courseIds->isEmpty()) {
            return;
        }

        $oldByShortName = collect($oldWorks)->keyBy('short_name');
        $newByShortName = collect($newWorks)->keyBy('short_name');

        // Detect short_name renames: disappeared from old, appeared in new, same display name
        $oldOnlyKeys = $oldByShortName->keys()->diff($newByShortName->keys());
        $newOnlyKeys = $newByShortName->keys()->diff($oldByShortName->keys());

        /** @var array<string, string> $shortNameRenames old => new */
        $shortNameRenames = [];
        foreach ($oldOnlyKeys as $oldKey) {
            // Match by stable work_id first (handles simultaneous short_name + name changes)
            $oldWorkId = $oldByShortName->get($oldKey)['work_id'] ?? null;
            if ($oldWorkId) {
                foreach ($newOnlyKeys as $newKey) {
                    if (($newByShortName->get($newKey)['work_id'] ?? null) === $oldWorkId) {
                        $shortNameRenames[$oldKey] = $newKey;
                        break;
                    }
                }
                if (isset($shortNameRenames[$oldKey])) {
                    continue;
                }
            }

            // Fall back to matching by display name
            $oldName = (string) ($oldByShortName->get($oldKey)['name'] ?? '');
            if ($oldName === '') {
                continue;
            }
            foreach ($newOnlyKeys as $newKey) {
                if ((string) ($newByShortName->get($newKey)['name'] ?? '') === $oldName) {
                    $shortNameRenames[$oldKey] = $newKey;
                    break;
                }
            }
        }

        DB::transaction(function () use ($courseIds, $shortNameRenames, $oldByShortName, $newByShortName) {
            // Apply short_name renames
            foreach ($shortNameRenames as $oldShortName => $newShortName) {
                TeachingCourseWork::whereIn('teaching_course_id', $courseIds)
                    ->where('type', $oldShortName)
                    ->update(['type' => $newShortName]);

                TeachingCourseStudentEntry::whereIn('teaching_course_id', $courseIds)
                    ->where('type', $oldShortName)
                    ->update(['type' => $newShortName]);
            }

            // For each work present in both old and new, cascade grade key changes
            foreach ($oldByShortName->keys() as $oldShortName) {
                $currentShortName = $shortNameRenames[$oldShortName] ?? $oldShortName;
                if (! $newByShortName->has($currentShortName)) {
                    continue; // work was removed entirely
                }

                $oldGrades = collect($oldByShortName->get($oldShortName)['grades'] ?? [])->keyBy('grade');
                $newGrades = collect($newByShortName->get($currentShortName)['grades'] ?? [])->keyBy('grade');

                $oldOnlyGrades = $oldGrades->keys()->diff($newGrades->keys());
                $newOnlyGrades = $newGrades->keys()->diff($oldGrades->keys());

                // Match renames: same non-empty value field
                /** @var array<string, string> $gradeRenames old_key => new_key */
                $gradeRenames = [];
                foreach ($oldOnlyGrades as $oldGradeKey) {
                    $oldValue = (string) ($oldGrades->get($oldGradeKey)['value'] ?? '');
                    if ($oldValue === '') {
                        continue;
                    }
                    foreach ($newOnlyGrades as $newGradeKey) {
                        if ((string) ($newGrades->get($newGradeKey)['value'] ?? '') === $oldValue) {
                            $gradeRenames[$oldGradeKey] = $newGradeKey;
                            break;
                        }
                    }
                }

                // Cast keys to string to prevent PHP's int-key coercion (e.g. '2' → 2)
                // from producing numeric bindings in SQL WHERE clauses.
                $gradeRenamesStr = [];
                foreach ($gradeRenames as $k => $v) {
                    $gradeRenamesStr[(string) $k] = (string) $v;
                }
                $gradeRenames = $gradeRenamesStr;

                $removedGrades = $oldOnlyGrades->diff(array_keys($gradeRenames))->map(fn ($k) => (string) $k)->values();
                $newDefault = $newByShortName->get($currentShortName)['default_grade'] ?? null;
                $newDefault = ($newDefault !== '' && $newDefault !== null) ? (string) $newDefault : null;

                // Update student entries
                foreach ($gradeRenames as $oldKey => $newKey) {
                    TeachingCourseStudentEntry::whereIn('teaching_course_id', $courseIds)
                        ->where('type', $currentShortName)
                        ->where('grade', (string) $oldKey)
                        ->update(['grade' => (string) $newKey]);
                }

                foreach ($removedGrades as $removedKey) {
                    TeachingCourseStudentEntry::whereIn('teaching_course_id', $courseIds)
                        ->where('type', $currentShortName)
                        ->where('grade', (string) $removedKey)
                        ->update(['grade' => $newDefault]);
                }

                // Update grade keys inside the groups JSON of each affected course work
                if (! empty($gradeRenames) || $removedGrades->isNotEmpty()) {
                    TeachingCourseWork::whereIn('teaching_course_id', $courseIds)
                        ->where('type', $currentShortName)
                        ->get()
                        ->each(function (TeachingCourseWork $courseWork) use ($gradeRenames, $removedGrades, $newDefault): void {
                            $groups = is_array($courseWork->groups) ? $courseWork->groups : [];
                            $changed = false;

                            foreach ($groups as &$group) {
                                if (! is_array($group)) {
                                    continue;
                                }

                                // Group-level grade (used by group works)
                                $g = isset($group['grade']) ? (string) $group['grade'] : null;
                                if ($g !== null && $g !== '') {
                                    if (isset($gradeRenames[$g])) {
                                        $group['grade'] = $gradeRenames[$g];
                                        $changed = true;
                                    } elseif ($removedGrades->contains($g)) {
                                        $group['grade'] = $newDefault;
                                        $changed = true;
                                    }
                                }

                                // Per-student grades array — must use a variable (not ??)
                                // so that foreach-by-reference modifies the original array.
                                if (isset($group['grades']) && is_array($group['grades'])) {
                                    foreach ($group['grades'] as &$gradeItem) {
                                        if (! is_array($gradeItem)) {
                                            continue;
                                        }
                                        $gv = isset($gradeItem['grade']) ? (string) $gradeItem['grade'] : null;
                                        if ($gv === null || $gv === '') {
                                            continue;
                                        }
                                        if (isset($gradeRenames[$gv])) {
                                            $gradeItem['grade'] = $gradeRenames[$gv];
                                            $changed = true;
                                        } elseif ($removedGrades->contains($gv)) {
                                            $gradeItem['grade'] = $newDefault ?? '';
                                            $changed = true;
                                        }
                                    }
                                    unset($gradeItem);
                                }
                            }
                            unset($group);

                            if ($changed) {
                                $courseWork->groups = $groups;
                                $courseWork->save();
                            }
                        });
                }
            }
        });
    }

    /**
     * Check if any works being removed from schemas are still referenced in course works.
     * Returns display names of blocked works ("SA – Schularbeit"), or an empty collection.
     *
     * @param  array<int, array<string, mixed>>  $newSchemas
     */
    public function worksRemovedButInUse(User $user, array $newSchemas, ?int $schoolyearId = null): Collection
    {
        $schoolyearId = $this->resolveSchoolyearId($user, $schoolyearId);
        if (! $schoolyearId) {
            return collect();
        }

        $oldSchemas = $this->schemasForUser($user, $schoolyearId);
        $newSchemasCollection = collect($newSchemas)->keyBy('id');
        $blockedNames = collect();

        foreach ($oldSchemas as $oldSchema) {
            $schemaId = (string) ($oldSchema['id'] ?? '');
            if (! $newSchemasCollection->has($schemaId)) {
                continue; // Schema itself is being deleted — handled by hasDependencies
            }

            $newSchema = $newSchemasCollection->get($schemaId);
            $oldWorks = collect(is_array($oldSchema['works'] ?? null) ? $oldSchema['works'] : []);
            $newWorks = collect(is_array($newSchema['works'] ?? null) ? $newSchema['works'] : []);

            $newWorkIds = $newWorks->pluck('work_id')->filter()->values();
            $newShortNames = $newWorks->pluck('short_name')->filter()->values();

            $removedShortNames = $oldWorks->filter(function (array $work) use ($newWorkIds, $newShortNames): bool {
                // If short_name is still present in new works, it was not removed
                if ($newShortNames->contains($work['short_name'] ?? '')) {
                    return false;
                }

                // Short_name is gone — check if it was renamed via a stable work_id
                $workId = $work['work_id'] ?? null;
                if ($workId && $newWorkIds->contains($workId)) {
                    return false;
                }

                return true;
            })->pluck('short_name')->filter()->values();

            if ($removedShortNames->isEmpty()) {
                continue;
            }

            $courseIds = TeachingCourse::where('user_id', $user->id)
                ->where('schoolyear_id', $schoolyearId)
                ->where('teaching_schema_id', $schemaId)
                ->pluck('id');

            if ($courseIds->isEmpty()) {
                continue;
            }

            $usedShortNames = TeachingCourseWork::whereIn('teaching_course_id', $courseIds)
                ->whereIn('type', $removedShortNames->all())
                ->pluck('type')
                ->unique();

            foreach ($usedShortNames as $usedShortName) {
                $work = $oldWorks->firstWhere('short_name', $usedShortName);
                $displayName = isset($work['name']) && $work['name'] !== ''
                    ? "{$usedShortName} – {$work['name']}"
                    : $usedShortName;
                $blockedNames->push($displayName);
            }
        }

        return $blockedNames;
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
                    'points_table' => [],
                    'points_sonst_grade' => null,
                    'semester_points_table' => [
                        ['grade' => '1', 'min_points' => 3],
                        ['grade' => '2', 'min_points' => 2],
                        ['grade' => '3', 'min_points' => 1],
                        ['grade' => '4', 'min_points' => 0],
                    ],
                    'semester_points_sonst_grade' => '5',
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
                    'semester_points_table' => [],
                    'semester_points_sonst_grade' => null,
                ],
            ],
            'grading' => [
                'semester_count' => 2,
                'semester_1_weight' => 40,
                'semester_2_weight' => 60,
                'category_evaluation_values' => self::defaultCategoryEvaluationValues(),
                'default_category_evaluation_value' => self::defaultCategoryEvaluationDefaultValue(),
                'categories' => [
                    [
                        'name' => 'Schularbeiten',
                        'weight' => 50,
                        'require_all_entries' => true,
                        'category_evaluation_enabled' => false,
                        'calculation' => 'mean',
                        'works' => [['short_name' => 'SA', 'factor' => 100]],
                    ],
                    [
                        'name' => 'Mitarbeit',
                        'weight' => 50,
                        'require_all_entries' => true,
                        'category_evaluation_enabled' => false,
                        'calculation' => 'mean',
                        'works' => [['short_name' => 'MA', 'factor' => 100]],
                    ],
                ],
            ],
        ];
    }

    public static function defaultCategoryEvaluationValues(): array
    {
        return [
            ['value' => 'Keine Bewertung', 'color' => '#b0bec5'],
            ['value' => 'Offen', 'color' => '#fb8c00'],
            ['value' => 'Bestanden', 'color' => '#43a047'],
            ['value' => '1', 'color' => '#2e7d32'],
            ['value' => '2', 'color' => '#7cb342'],
            ['value' => '3', 'color' => '#f9a825'],
            ['value' => '4', 'color' => '#ef6c00'],
            ['value' => '5', 'color' => '#e53935'],
            ['value' => 'Nicht bestanden', 'color' => '#c62828'],
        ];
    }

    public static function defaultCategoryEvaluationDefaultValue(): string
    {
        return (string) (self::defaultCategoryEvaluationValues()[0]['value'] ?? 'Keine Bewertung');
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
        $grading['category_evaluation_values'] = $this->normalizeCategoryEvaluationValues(
            array_key_exists('category_evaluation_values', $grading) && is_array($grading['category_evaluation_values'])
                ? $grading['category_evaluation_values']
                : null
        );
        $grading['default_category_evaluation_value'] = $this->normalizeDefaultCategoryEvaluationValue(
            $grading['default_category_evaluation_value'] ?? null,
            $grading['category_evaluation_values']
        );

        $categories = is_array($grading['categories'] ?? null) ? $grading['categories'] : [];
        $grading['categories'] = collect($categories)
            ->filter(fn ($category) => is_array($category))
            ->map(function (array $category) use ($works) {
                $normalizedWorks = $this->normalizeCategoryWorks(is_array($category['works'] ?? null) ? $category['works'] : []);
                $hasOwnRequireAll = array_key_exists('require_all_entries', $category);

                $category['works'] = $normalizedWorks;
                $category['category_evaluation_enabled'] = (bool) ($category['category_evaluation_enabled'] ?? false);
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

    private function normalizeCategoryEvaluationValues(?array $values): array
    {
        if ($values === null) {
            return self::defaultCategoryEvaluationValues();
        }

        $defaultColorMap = $this->defaultCategoryEvaluationColorMap();

        $normalizedValues = collect($values)
            ->map(function ($value) use ($defaultColorMap): ?array {
                if (is_array($value)) {
                    $label = trim((string) ($value['value'] ?? ''));
                    if ($label === '') {
                        return null;
                    }

                    return [
                        'value' => $label,
                        'color' => $this->normalizeCategoryEvaluationColor(
                            $value['color'] ?? null,
                            $defaultColorMap[$label] ?? '#4f6fb3'
                        ),
                    ];
                }

                if (! is_scalar($value)) {
                    return null;
                }

                $label = trim((string) $value);
                if ($label === '') {
                    return null;
                }

                return [
                    'value' => $label,
                    'color' => $defaultColorMap[$label] ?? '#4f6fb3',
                ];
            })
            ->filter(fn (?array $value): bool => is_array($value) && ($value['value'] ?? '') !== '')
            ->unique(fn (array $value): string => Str::lower((string) ($value['value'] ?? '')))
            ->values()
            ->all();

        return $normalizedValues !== [] ? $normalizedValues : self::defaultCategoryEvaluationValues();
    }

    private function normalizeDefaultCategoryEvaluationValue(mixed $value, array $availableValues): string
    {
        $normalizedValue = trim((string) ($value ?? ''));
        $availableValueLabels = $this->categoryEvaluationValueLabels($availableValues);

        if ($availableValueLabels === []) {
            return '';
        }

        if ($normalizedValue !== '' && in_array($normalizedValue, $availableValueLabels, true)) {
            return $normalizedValue;
        }

        return $availableValueLabels[0] ?? '';
    }

    private function normalizeCategoryEvaluationColor(mixed $color, string $fallback): string
    {
        $raw = trim((string) ($color ?? ''));
        if ($raw === '') {
            return $fallback;
        }

        $normalized = Str::startsWith($raw, '#') ? $raw : '#'.$raw;

        if (! preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $normalized)) {
            return $fallback;
        }

        if (strlen($normalized) === 4) {
            return Str::lower(sprintf(
                '#%1$s%1$s%2$s%2$s%3$s%3$s',
                $normalized[1],
                $normalized[2],
                $normalized[3]
            ));
        }

        return Str::lower($normalized);
    }

    private function defaultCategoryEvaluationColorMap(): array
    {
        return collect(self::defaultCategoryEvaluationValues())
            ->filter(fn ($value): bool => is_array($value))
            ->mapWithKeys(function (array $value): array {
                $label = trim((string) ($value['value'] ?? ''));
                $color = trim((string) ($value['color'] ?? ''));

                if ($label === '' || $color === '') {
                    return [];
                }

                return [$label => $color];
            })
            ->all();
    }

    private function categoryEvaluationValueLabels(array $values): array
    {
        return collect($values)
            ->filter(fn ($value): bool => is_array($value))
            ->map(fn (array $value): string => trim((string) ($value['value'] ?? '')))
            ->filter(fn (string $value): bool => $value !== '')
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
                if (empty($work['work_id'])) {
                    $work['work_id'] = (string) Str::uuid();
                }

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

                $work = $this->normalizeSemesterPointsConfiguration($work);

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

    /**
     * @param  array<string, mixed>  $work
     * @return array<string, mixed>
     */
    private function normalizeSemesterPointsConfiguration(array $work): array
    {
        if (($work['calculation'] ?? null) !== 'points') {
            $work['semester_points_table'] = is_array($work['semester_points_table'] ?? null)
                ? $work['semester_points_table']
                : [];
            $work['semester_points_sonst_grade'] = $this->nullableTrimmedString($work['semester_points_sonst_grade'] ?? null);

            return $work;
        }

        if (array_key_exists('semester_points_table', $work)) {
            $work['semester_points_table'] = is_array($work['semester_points_table'] ?? null)
                ? $work['semester_points_table']
                : [];
            $work['semester_points_sonst_grade'] = $this->nullableTrimmedString($work['semester_points_sonst_grade'] ?? null);

            return $work;
        }

        $legacyPointsTable = is_array($work['points_table'] ?? null) ? $work['points_table'] : [];
        $split = $this->splitPointsTableFallback($legacyPointsTable);

        $work['semester_points_table'] = $split['table'];
        $work['semester_points_sonst_grade'] = $this->nullableTrimmedString($work['semester_points_sonst_grade'] ?? null)
            ?? $split['fallback']
            ?? $this->nullableTrimmedString($work['points_sonst_grade'] ?? null);

        return $work;
    }

    /**
     * @param  array<int, mixed>  $pointsTable
     * @return array{table: array<int, mixed>, fallback: ?string}
     */
    private function splitPointsTableFallback(array $pointsTable): array
    {
        $table = [];
        $fallback = null;

        foreach ($pointsTable as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if ($fallback === null && (float) ($entry['min_points'] ?? 0) <= -999) {
                $fallback = $this->nullableTrimmedString($entry['grade'] ?? null);

                continue;
            }

            $table[] = $entry;
        }

        return [
            'table' => $table,
            'fallback' => $fallback,
        ];
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}
