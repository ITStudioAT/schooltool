<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PaginateResource;
use App\Http\Resources\Admin\Teaching\Import116Resource;
use App\Models\Import116;
use App\Models\TeachingCourseBehaviourEntry;
use App\Models\TeachingCourseStudentCategoryEvaluation;
use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Services\TeachingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TeachingController extends Controller
{
    public function search116(Request $request)
    {

        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'search_string' => 'nullable|string|max:255',
            'page' => 'sometimes|integer|min:1',
        ]);

        $searchString = $validated['search_string'] ?? null;

        $import116 = Import116::where('school_id', $auth_user->school_id)
            ->where('schoolyear_id', $auth_user->schoolyear_id)
            ->when($searchString, function ($query) use ($searchString) {
                $like = '%'.$searchString.'%';
                $query->where(function ($query) use ($like) {
                    $query->where('last_name', 'like', $like)
                        ->orWhere('first_name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('mother_name', 'like', $like)
                        ->orWhere('mother_email', 'like', $like)
                        ->orWhere('father_name', 'like', $like)
                        ->orWhere('father_email', 'like', $like)
                        ->orWhere('class', 'like', $like);
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(config('schooltool.pagination'));

        return response()->json([
            'data' => Import116Resource::collection($import116),
            'meta' => new PaginateResource($import116),
        ]);
    }

    public function loadSettings(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $teachingService = new TeachingService;
        $teachingService->ensureDefaultSchema($auth_user, $auth_user->schoolyear_id);

        $settings = [
            'teaching_schemas' => $teachingService->schemasForUser($auth_user, $auth_user->schoolyear_id)->all(),
            'teaching_behaviour' => $auth_user->teaching_behaviour ?? [],
            'teaching_notifications' => $auth_user->teaching_notifications ?? [],
            'teaching_show_behaviour' => $auth_user->teaching_show_behaviour ?? true,
        ];

        return response()->json([
            'settings' => $settings,
        ]);
    }

    public function saveActiveSemester(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'teaching_active_semester' => 'required|integer|in:1,2,3',
        ]);

        $auth_user->teaching_active_semester = $validated['teaching_active_semester'];
        $auth_user->save();

        return response()->json([
            'teaching_active_semester' => $auth_user->teaching_active_semester,
        ]);
    }

    public function saveSemester2Date(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'teaching_count_for_semester_2_date' => 'nullable|date',
        ]);

        $auth_user->teaching_count_for_semester_2_date = $validated['teaching_count_for_semester_2_date'];
        $auth_user->save();

        return response()->json([
            'teaching_count_for_semester_2_date' => $auth_user->teaching_count_for_semester_2_date,
        ]);
    }

    public function saveSettings(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $teachingService = new TeachingService;
        $previousTeachingSchemas = $teachingService->schemasForUser($auth_user, $auth_user->schoolyear_id);
        $previousTeachingBehaviour = collect($auth_user->teaching_behaviour ?? []);
        $previousTeachingNotifications = collect($auth_user->teaching_notifications ?? []);

        $validated = $request->validate([
            'teaching_schemas' => 'nullable|array',
            'teaching_schemas.*.id' => 'required|string|max:36',
            'teaching_schemas.*.name' => 'required|string|max:255',
            'teaching_schemas.*.works' => 'nullable|array',
            'teaching_schemas.*.works.*.short_name' => 'required|string|max:10',
            'teaching_schemas.*.works.*.name' => 'required|string|max:255',
            'teaching_schemas.*.works.*.grades' => 'nullable|array',
            'teaching_schemas.*.works.*.grades.*.grade' => 'required|string|max:10',
            'teaching_schemas.*.works.*.grades.*.name' => 'nullable|string|max:50',
            'teaching_schemas.*.works.*.grades.*.value' => 'nullable|string|max:10',
            'teaching_schemas.*.works.*.calculation' => 'nullable|string|in:average,points',
            'teaching_schemas.*.works.*.require_all_entries' => 'nullable|boolean',
            'teaching_schemas.*.works.*.default_grade' => 'nullable|string|max:10',
            'teaching_schemas.*.works.*.points_table' => 'nullable|array',
            'teaching_schemas.*.works.*.points_table.*.min_points' => 'required|numeric',
            'teaching_schemas.*.works.*.points_table.*.grade' => 'required|string|max:10',
            'teaching_schemas.*.grading' => 'nullable|array',
            'teaching_schemas.*.grading.semester_count' => 'nullable|integer|min:1|max:2',
            'teaching_schemas.*.grading.semester_1_weight' => 'nullable|integer|min:0|max:100',
            'teaching_schemas.*.grading.semester_2_weight' => 'nullable|integer|min:0|max:100',
            'teaching_schemas.*.grading.use_semester_grade_only' => 'nullable|boolean',
            'teaching_schemas.*.grading.category_evaluation_values' => 'nullable|array',
            'teaching_schemas.*.grading.category_evaluation_values.*' => 'required|array',
            'teaching_schemas.*.grading.category_evaluation_values.*.value' => 'required|string|max:50',
            'teaching_schemas.*.grading.category_evaluation_values.*.color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{6})$/'],
            'teaching_schemas.*.grading.default_category_evaluation_value' => 'nullable|string|max:50',
            'teaching_schemas.*.grading.categories' => 'nullable|array',
            'teaching_schemas.*.grading.categories.*.name' => 'required|string|max:100',
            'teaching_schemas.*.grading.categories.*.weight' => 'required|integer|min:0|max:100',
            'teaching_schemas.*.grading.categories.*.require_all_entries' => 'nullable|boolean',
            'teaching_schemas.*.grading.categories.*.category_evaluation_enabled' => 'nullable|boolean',
            'teaching_schemas.*.grading.categories.*.works' => 'nullable|array',
            'teaching_schemas.*.grading.categories.*.works.*.short_name' => 'required|string|max:10',
            'teaching_schemas.*.grading.categories.*.works.*.factor' => 'required|integer|min:0|max:100',
            'teaching_schemas.*.grading.categories.*.calculation' => 'nullable|string|in:mean,sum,best,worst',
            'teaching_behaviour' => 'nullable|array',
            'teaching_behaviour.*.short_name' => 'required|string|max:10',
            'teaching_behaviour.*.name' => 'required|string|max:255',
            'teaching_notifications' => 'nullable|array',
            'teaching_notifications.*.short_name' => 'required|string|max:10',
            'teaching_notifications.*.name' => 'required|string|max:255',
            'teaching_show_behaviour' => 'nullable|boolean',
        ]);

        if (isset($validated['teaching_schemas'])) {
            $usedNames = $teachingService->hasDependencies($auth_user, $validated['teaching_schemas'], $auth_user->schoolyear_id);

            if ($usedNames->isNotEmpty()) {
                abort(409, "Schema wird in Fächern verwendet und kann nicht gelöscht werden: {$usedNames->implode(', ')}");
            }

            if ($teachingService->standardSchemaRenamed($auth_user, $validated['teaching_schemas'], $auth_user->schoolyear_id)) {
                abort(409, 'Das Standard-Schema kann nicht umbenannt werden.');
            }

            $teachingService->saveSchemas($auth_user, $validated['teaching_schemas'], $auth_user->schoolyear_id);
            $this->syncSchemaWorkItemsForSchool(
                $auth_user,
                $auth_user->schoolyear_id,
                $previousTeachingSchemas,
                collect($validated['teaching_schemas'])
            );
            $this->syncSchemaCategoryEvaluationNamesForSchool(
                $auth_user,
                $auth_user->schoolyear_id,
                $previousTeachingSchemas,
                collect($validated['teaching_schemas'])
            );
        }
        if (isset($validated['teaching_behaviour'])) {
            $this->syncCourseBehaviourEntryTypesForSchool(
                $auth_user,
                'behaviour',
                $previousTeachingBehaviour,
                $validated['teaching_behaviour']
            );
            $auth_user->teaching_behaviour = $validated['teaching_behaviour'];
        }
        if (isset($validated['teaching_notifications'])) {
            $this->syncCourseBehaviourEntryTypesForSchool(
                $auth_user,
                'notification',
                $previousTeachingNotifications,
                $validated['teaching_notifications']
            );
            $auth_user->teaching_notifications = $validated['teaching_notifications'];
        }
        if (array_key_exists('teaching_show_behaviour', $validated)) {
            $auth_user->teaching_show_behaviour = (bool) $validated['teaching_show_behaviour'];
        }
        $auth_user->save();

        $settings = [
            'teaching_schemas' => $teachingService->schemasForUser($auth_user, $auth_user->schoolyear_id)->all(),
            'teaching_behaviour' => $auth_user->teaching_behaviour ?? [],
            'teaching_notifications' => $auth_user->teaching_notifications ?? [],
            'teaching_show_behaviour' => $auth_user->teaching_show_behaviour ?? true,
        ];

        return response()->json([
            'settings' => $settings,
        ]);
    }

    private function syncCourseBehaviourEntryTypesForSchool(
        User $authUser,
        string $kind,
        Collection $previousEntries,
        array $currentEntries
    ): void {
        $renamedTypes = $this->resolveRenamedKeys(
            $previousEntries,
            collect($currentEntries),
            'short_name',
            ['name']
        );

        foreach ($renamedTypes as $oldType => $newType) {
            TeachingCourseBehaviourEntry::query()
                ->where('kind', $kind)
                ->where('type', $oldType)
                ->whereHas('teachingCourse', function ($query) use ($authUser) {
                    $query->where('school_id', $authUser->school_id);
                })
                ->update(['type' => $newType]);
        }
    }

    private function syncSchemaWorkItemsForSchool(
        User $authUser,
        ?int $schoolyearId,
        Collection $previousSchemas,
        Collection $currentSchemas
    ): void {
        $workChanges = $this->resolveSchemaWorkChanges($previousSchemas, $currentSchemas);

        foreach ($workChanges as $schemaId => $changesForSchema) {
            foreach ($changesForSchema as $change) {
                $oldType = (string) ($change['old_type'] ?? '');
                $newType = (string) ($change['new_type'] ?? '');
                $gradeRenames = collect($change['grade_renames'] ?? [])
                    ->filter(fn (string $newGrade, string $oldGrade): bool => $oldGrade !== '' && $newGrade !== '' && $oldGrade !== $newGrade)
                    ->all();

                if ($oldType === '') {
                    continue;
                }

                if (! empty($gradeRenames)) {
                    $this->syncSchemaGradeRenamesForSchool(
                        $authUser,
                        $schoolyearId,
                        (string) $schemaId,
                        $oldType,
                        $gradeRenames
                    );
                }

                if ($newType !== '' && $newType !== $oldType) {
                    $this->syncSchemaWorkTypeRenameForSchool(
                        $authUser,
                        $schoolyearId,
                        (string) $schemaId,
                        $oldType,
                        $newType
                    );
                }
            }
        }
    }

    private function syncSchemaGradeRenamesForSchool(
        User $authUser,
        ?int $schoolyearId,
        string $schemaId,
        string $workType,
        array $gradeRenames
    ): void {
        foreach ($gradeRenames as $oldGrade => $newGrade) {
            TeachingCourseStudentEntry::query()
                ->where('type', $workType)
                ->where('grade', $oldGrade)
                ->where($this->scopeQueryToSchemaCourses($authUser, $schoolyearId, $schemaId))
                ->update(['grade' => $newGrade]);
        }

        TeachingCourseWork::query()
            ->where('type', $workType)
            ->where($this->scopeQueryToSchemaCourses($authUser, $schoolyearId, $schemaId))
            ->orderBy('id')
            ->chunkById(200, function (Collection $works) use ($gradeRenames) {
                foreach ($works as $work) {
                    $groups = is_array($work->groups) ? $work->groups : [];
                    [$updatedGroups, $hasChanges] = $this->replaceWorkGroupGrades($groups, $gradeRenames);
                    if (! $hasChanges) {
                        continue;
                    }

                    $work->groups = $updatedGroups;
                    $work->save();
                }
            });
    }

    private function syncSchemaWorkTypeRenameForSchool(
        User $authUser,
        ?int $schoolyearId,
        string $schemaId,
        string $oldType,
        string $newType
    ): void {
        TeachingCourseStudentEntry::query()
            ->where('type', $oldType)
            ->where($this->scopeQueryToSchemaCourses($authUser, $schoolyearId, $schemaId))
            ->update(['type' => $newType]);

        TeachingCourseWork::query()
            ->where('type', $oldType)
            ->where($this->scopeQueryToSchemaCourses($authUser, $schoolyearId, $schemaId))
            ->update(['type' => $newType]);
    }

    private function syncSchemaCategoryEvaluationNamesForSchool(
        User $authUser,
        ?int $schoolyearId,
        Collection $previousSchemas,
        Collection $currentSchemas
    ): void {
        $renames = $this->resolveSchemaCategoryEvaluationRenames($previousSchemas, $currentSchemas);

        foreach ($renames as $schemaId => $categoryRenames) {
            foreach ($categoryRenames as $oldName => $newName) {
                TeachingCourseStudentCategoryEvaluation::query()
                    ->where('category_name', $oldName)
                    ->where($this->scopeQueryToSchemaCourses($authUser, $schoolyearId, (string) $schemaId))
                    ->update(['category_name' => $newName]);
            }
        }
    }

    private function resolveSchemaWorkChanges(Collection $previousSchemas, Collection $currentSchemas): array
    {
        $changes = [];

        $previousById = $previousSchemas
            ->filter(fn ($schema): bool => is_array($schema))
            ->keyBy(fn (array $schema): string => (string) ($schema['id'] ?? ''))
            ->filter(fn (array $schema, string $schemaId): bool => $schemaId !== '');

        $currentById = $currentSchemas
            ->filter(fn ($schema): bool => is_array($schema))
            ->keyBy(fn (array $schema): string => (string) ($schema['id'] ?? ''))
            ->filter(fn (array $schema, string $schemaId): bool => $schemaId !== '');

        foreach ($previousById as $schemaId => $previousSchema) {
            if (! $currentById->has($schemaId)) {
                continue;
            }

            $currentSchema = $currentById->get($schemaId);
            $oldWorks = collect((array) ($previousSchema['works'] ?? []))
                ->filter(fn ($work): bool => is_array($work))
                ->values();
            $newWorks = collect((array) ($currentSchema['works'] ?? []))
                ->filter(fn ($work): bool => is_array($work))
                ->values();

            $workTypeRenames = $this->resolveRenamedKeys($oldWorks, $newWorks, 'short_name', ['name']);
            $changesForSchema = [];

            foreach ($oldWorks as $oldWork) {
                $oldType = trim((string) ($oldWork['short_name'] ?? ''));
                if ($oldType === '') {
                    continue;
                }

                $newType = $workTypeRenames[$oldType] ?? $oldType;
                $newWork = $newWorks->first(fn (array $work): bool => trim((string) ($work['short_name'] ?? '')) === $newType);
                if (! is_array($newWork)) {
                    continue;
                }

                $gradeRenames = $this->resolveRenamedKeys(
                    collect((array) ($oldWork['grades'] ?? [])),
                    collect((array) ($newWork['grades'] ?? [])),
                    'grade',
                    ['name', 'value']
                );

                if ($newType !== $oldType || ! empty($gradeRenames)) {
                    $changesForSchema[] = [
                        'old_type' => $oldType,
                        'new_type' => $newType,
                        'grade_renames' => $gradeRenames,
                    ];
                }
            }

            if (empty($changesForSchema)) {
                continue;
            }

            $changes[$schemaId] = $changesForSchema;
        }

        return $changes;
    }

    private function resolveSchemaCategoryEvaluationRenames(Collection $previousSchemas, Collection $currentSchemas): array
    {
        $renames = [];

        $previousById = $previousSchemas
            ->filter(fn ($schema): bool => is_array($schema))
            ->keyBy(fn (array $schema): string => (string) ($schema['id'] ?? ''))
            ->filter(fn (array $schema, string $schemaId): bool => $schemaId !== '');

        $currentById = $currentSchemas
            ->filter(fn ($schema): bool => is_array($schema))
            ->keyBy(fn (array $schema): string => (string) ($schema['id'] ?? ''))
            ->filter(fn (array $schema, string $schemaId): bool => $schemaId !== '');

        foreach ($previousById as $schemaId => $previousSchema) {
            if (! $currentById->has($schemaId)) {
                continue;
            }

            $previousCategories = collect((array) data_get($previousSchema, 'grading.categories', []))
                ->filter(fn ($category): bool => is_array($category))
                ->values();
            $currentCategories = collect((array) data_get($currentById->get($schemaId), 'grading.categories', []))
                ->filter(fn ($category): bool => is_array($category))
                ->values();

            $schemaRenames = [];
            $count = min($previousCategories->count(), $currentCategories->count());

            for ($index = 0; $index < $count; $index++) {
                $oldCategory = (array) $previousCategories[$index];
                $newCategory = (array) $currentCategories[$index];
                $oldName = trim((string) ($oldCategory['name'] ?? ''));
                $newName = trim((string) ($newCategory['name'] ?? ''));

                if ($oldName === '' || $newName === '' || $oldName === $newName) {
                    continue;
                }

                if (! ((bool) ($oldCategory['category_evaluation_enabled'] ?? false) || (bool) ($newCategory['category_evaluation_enabled'] ?? false))) {
                    continue;
                }

                $schemaRenames[$oldName] = $newName;
            }

            if ($schemaRenames !== []) {
                $renames[$schemaId] = $schemaRenames;
            }
        }

        return $renames;
    }

    private function scopeQueryToSchemaCourses(
        User $authUser,
        ?int $schoolyearId,
        string $schemaId
    ): \Closure {
        return function (Builder $query) use ($authUser, $schoolyearId, $schemaId): void {
            $query->whereHas('teachingCourse', function (Builder $courseQuery) use ($authUser, $schoolyearId, $schemaId) {
                $courseQuery->where('school_id', $authUser->school_id)
                    ->where('teaching_schema_id', $schemaId);

                if ($schoolyearId !== null) {
                    $courseQuery->where('schoolyear_id', $schoolyearId);
                }
            });
        };
    }

    /**
     * @param  array<int, mixed>  $groups
     * @param  array<string, string>  $gradeRenames
     * @return array{0: array<int, mixed>, 1: bool}
     */
    private function replaceWorkGroupGrades(array $groups, array $gradeRenames): array
    {
        $updatedGroups = [];
        $hasChanges = false;

        foreach ($groups as $group) {
            if (! is_array($group)) {
                $updatedGroups[] = $group;

                continue;
            }

            if (array_key_exists('grade', $group)) {
                $oldGrade = trim((string) ($group['grade'] ?? ''));
                if ($oldGrade !== '' && array_key_exists($oldGrade, $gradeRenames)) {
                    $group['grade'] = $gradeRenames[$oldGrade];
                    $hasChanges = true;
                }
            }

            if (is_array($group['grades'] ?? null)) {
                $group['grades'] = collect($group['grades'])
                    ->map(function ($item) use ($gradeRenames, &$hasChanges) {
                        if (! is_array($item) || ! array_key_exists('grade', $item)) {
                            return $item;
                        }

                        $oldGrade = trim((string) ($item['grade'] ?? ''));
                        if ($oldGrade === '' || ! array_key_exists($oldGrade, $gradeRenames)) {
                            return $item;
                        }

                        $item['grade'] = $gradeRenames[$oldGrade];
                        $hasChanges = true;

                        return $item;
                    })
                    ->all();
            }

            $updatedGroups[] = $group;
        }

        return [$updatedGroups, $hasChanges];
    }

    private function resolveRenamedKeys(
        Collection $previousEntries,
        Collection $currentEntries,
        string $keyField,
        array $matchFields
    ): array {
        $previous = $this->normalizeKeyedEntries($previousEntries, $keyField, $matchFields);
        $current = $this->normalizeKeyedEntries($currentEntries, $keyField, $matchFields);

        $renames = [];
        $previousBySignature = $previous
            ->filter(fn (array $entry): bool => $entry['signature'] !== '')
            ->groupBy('signature');
        $currentBySignature = $current
            ->filter(fn (array $entry): bool => $entry['signature'] !== '')
            ->groupBy('signature');

        foreach ($previousBySignature as $signature => $oldEntries) {
            if (! $currentBySignature->has($signature)) {
                continue;
            }

            $newEntries = $currentBySignature->get($signature);
            if ($oldEntries->count() !== 1 || $newEntries->count() !== 1) {
                continue;
            }

            $oldKey = (string) $oldEntries->first()['key'];
            $newKey = (string) $newEntries->first()['key'];
            if ($oldKey === '' || $newKey === '' || $oldKey === $newKey) {
                continue;
            }

            $renames[$oldKey] = $newKey;
        }

        if ($previous->count() === $current->count()) {
            $indexDifferences = [];
            for ($index = 0; $index < $previous->count(); $index++) {
                $oldKey = (string) $previous[$index]['key'];
                $newKey = (string) $current[$index]['key'];

                if ($oldKey === '' || $newKey === '' || $oldKey === $newKey) {
                    continue;
                }

                $indexDifferences[] = [$oldKey, $newKey];
            }

            if (count($indexDifferences) === 1) {
                [$oldKey, $newKey] = $indexDifferences[0];
                $renames[$oldKey] = $newKey;
            }
        }

        return collect($renames)
            ->filter(fn (string $newKey, string $oldKey): bool => $oldKey !== '' && $newKey !== '' && $oldKey !== $newKey)
            ->all();
    }

    private function normalizeKeyedEntries(Collection $entries, string $keyField, array $matchFields): Collection
    {
        return $entries
            ->filter(fn ($entry): bool => is_array($entry))
            ->values()
            ->map(function (array $entry) use ($keyField, $matchFields): array {
                $key = trim((string) ($entry[$keyField] ?? ''));
                $signature = collect($matchFields)
                    ->map(fn (string $field): string => trim((string) ($entry[$field] ?? '')))
                    ->implode('|');

                return [
                    'key' => $key,
                    'signature' => $signature,
                ];
            })
            ->filter(fn (array $entry): bool => $entry['key'] !== '')
            ->values();
    }
}
