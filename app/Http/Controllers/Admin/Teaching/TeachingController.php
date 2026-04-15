<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PaginateResource;
use App\Http\Resources\Admin\Teaching\Import116Resource;
use App\Models\Import116;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
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

        return response()->json([
            'settings' => $this->settingsPayloadForUser($auth_user, $teachingService),
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

        $semester2Date = $validated['teaching_count_for_semester_2_date'];
        $schoolyear = $auth_user->selectedSchoolyear;

        if ($schoolyear) {
            $schoolyear->sem_2_start = $semester2Date;
            $schoolyear->save();
        }

        $auth_user->teaching_count_for_semester_2_date = $semester2Date;
        $auth_user->save();

        return response()->json([
            'teaching_count_for_semester_2_date' => $auth_user->teaching_count_for_semester_2_date,
            'schoolyear_sem_2_start' => $schoolyear?->sem_2_start,
        ]);
    }

    public function saveSettings(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $teachingService = new TeachingService;
        $previousTeachingSchemas = $teachingService->schemasForUser($auth_user, $auth_user->schoolyear_id);
        $previousTeachingBehaviour = collect($this->teachingBehaviourForSchoolyear($auth_user, $auth_user->schoolyear_id));
        $previousTeachingNotifications = collect($this->teachingNotificationsForSchoolyear($auth_user, $auth_user->schoolyear_id));

        $validated = $request->validate([
            'teaching_schemas' => 'nullable|array',
            'teaching_schemas.*.id' => 'required|string|max:36',
            'teaching_schemas.*.name' => 'required|string|max:255',
            'teaching_schemas.*.works' => 'nullable|array',
            'teaching_schemas.*.works.*.work_id' => 'nullable|string|uuid',
            'teaching_schemas.*.works.*.short_name' => 'required|string|max:10',
            'teaching_schemas.*.works.*.name' => 'required|string|max:255',
            'teaching_schemas.*.works.*.grades' => 'nullable|array',
            'teaching_schemas.*.works.*.grades.*.grade' => 'required|string|max:10',
            'teaching_schemas.*.works.*.grades.*.name' => 'nullable|string|max:50',
            'teaching_schemas.*.works.*.grades.*.value' => 'nullable|string|max:10',
            'teaching_schemas.*.works.*.calculation' => 'nullable|string|in:average,points',
            'teaching_schemas.*.works.*.require_all_entries' => 'nullable|boolean',
            'teaching_schemas.*.works.*.default_grade' => 'nullable|string|max:10',
            'teaching_schemas.*.works.*.points_note_enabled' => 'nullable|boolean',
            'teaching_schemas.*.works.*.points_table' => 'nullable|array',
            'teaching_schemas.*.works.*.points_table.*.min_points' => 'required|numeric',
            'teaching_schemas.*.works.*.points_table.*.grade' => 'required|string|max:10',
            'teaching_schemas.*.works.*.points_sonst_grade' => 'nullable|string|max:10',
            'teaching_schemas.*.works.*.semester_points_table' => 'nullable|array',
            'teaching_schemas.*.works.*.semester_points_table.*.min_points' => 'required|numeric',
            'teaching_schemas.*.works.*.semester_points_table.*.grade' => 'required|string|max:10',
            'teaching_schemas.*.works.*.semester_points_sonst_grade' => 'nullable|string|max:10',
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

            $blockedWorks = $teachingService->worksRemovedButInUse($auth_user, $validated['teaching_schemas'], $auth_user->schoolyear_id);

            if ($blockedWorks->isNotEmpty()) {
                abort(409, "Arbeit wird in Kursen verwendet und kann nicht gelöscht werden: {$blockedWorks->implode(', ')}");
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
                $auth_user->schoolyear_id,
                $previousTeachingBehaviour,
                $validated['teaching_behaviour']
            );
            $this->storeTeachingBehaviourForSchoolyear($auth_user, $auth_user->schoolyear_id, $validated['teaching_behaviour']);
        }
        if (isset($validated['teaching_notifications'])) {
            $this->syncCourseBehaviourEntryTypesForSchool(
                $auth_user,
                'notification',
                $auth_user->schoolyear_id,
                $previousTeachingNotifications,
                $validated['teaching_notifications']
            );
            $this->storeTeachingNotificationsForSchoolyear($auth_user, $auth_user->schoolyear_id, $validated['teaching_notifications']);
        }
        if (array_key_exists('teaching_show_behaviour', $validated)) {
            $auth_user->teaching_show_behaviour = (bool) $validated['teaching_show_behaviour'];
        }
        $auth_user->save();

        return response()->json([
            'settings' => $this->settingsPayloadForUser($auth_user, $teachingService),
        ]);
    }

    public function importBehaviour(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $currentSchoolyear = $auth_user->selectedSchoolyear;
        $previousSchoolyear = $this->previousSchoolyearForImport($currentSchoolyear);

        if (! $currentSchoolyear || ! $previousSchoolyear) {
            abort(422, 'Import nicht möglich.');
        }

        $entriesToImport = $this->teachingBehaviourForSchoolyear($auth_user, $previousSchoolyear->id);

        TeachingCourseBehaviourEntry::query()
            ->where('kind', 'behaviour')
            ->whereHas('teachingCourse', function (Builder $query) use ($auth_user): void {
                $query->where('school_id', $auth_user->school_id)
                    ->where('schoolyear_id', $auth_user->schoolyear_id)
                    ->where('user_id', $auth_user->id);
            })
            ->delete();

        $this->storeTeachingBehaviourForSchoolyear($auth_user, $currentSchoolyear->id, $entriesToImport);
        $auth_user->save();

        return response()->json([
            'settings' => $this->settingsPayloadForUser($auth_user, new TeachingService),
        ]);
    }

    public function resetBehaviour(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        TeachingCourseBehaviourEntry::query()
            ->where('kind', 'behaviour')
            ->whereHas('teachingCourse', function (Builder $query) use ($auth_user): void {
                $query->where('school_id', $auth_user->school_id)
                    ->where('schoolyear_id', $auth_user->schoolyear_id)
                    ->where('user_id', $auth_user->id);
            })
            ->delete();

        $this->storeTeachingBehaviourForSchoolyear($auth_user, $auth_user->schoolyear_id, []);
        $auth_user->save();

        return response()->json([
            'settings' => $this->settingsPayloadForUser($auth_user, new TeachingService),
        ]);
    }

    public function importNotifications(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $currentSchoolyear = $auth_user->selectedSchoolyear;
        $previousSchoolyear = $this->previousSchoolyearForImport($currentSchoolyear);

        if (! $currentSchoolyear || ! $previousSchoolyear) {
            abort(422, 'Import nicht möglich.');
        }

        $entriesToImport = $this->teachingNotificationsForSchoolyear($auth_user, $previousSchoolyear->id);

        TeachingCourseBehaviourEntry::query()
            ->where('kind', 'notification')
            ->whereHas('teachingCourse', function (Builder $query) use ($auth_user): void {
                $query->where('school_id', $auth_user->school_id)
                    ->where('schoolyear_id', $auth_user->schoolyear_id)
                    ->where('user_id', $auth_user->id);
            })
            ->delete();

        $this->storeTeachingNotificationsForSchoolyear($auth_user, $currentSchoolyear->id, $entriesToImport);
        $auth_user->save();

        return response()->json([
            'settings' => $this->settingsPayloadForUser($auth_user, new TeachingService),
        ]);
    }

    public function resetNotifications(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        TeachingCourseBehaviourEntry::query()
            ->where('kind', 'notification')
            ->whereHas('teachingCourse', function (Builder $query) use ($auth_user): void {
                $query->where('school_id', $auth_user->school_id)
                    ->where('schoolyear_id', $auth_user->schoolyear_id)
                    ->where('user_id', $auth_user->id);
            })
            ->delete();

        $this->storeTeachingNotificationsForSchoolyear($auth_user, $auth_user->schoolyear_id, []);
        $auth_user->save();

        return response()->json([
            'settings' => $this->settingsPayloadForUser($auth_user, new TeachingService),
        ]);
    }

    public function importSchema(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'selected_schema_id' => 'required|string|max:36',
        ]);

        $teachingService = new TeachingService;
        $currentSchoolyear = $auth_user->selectedSchoolyear;
        $previousSchoolyear = $this->previousSchoolyearForImport($currentSchoolyear);

        if (! $currentSchoolyear || ! $previousSchoolyear) {
            abort(422, 'Import nicht möglich.');
        }

        $currentSchemas = $teachingService->schemasForUser($auth_user, $currentSchoolyear->id);
        if (! is_array($currentSchemas->firstWhere('id', (string) $validated['selected_schema_id']))) {
            abort(404, 'Benotungsschema nicht gefunden.');
        }

        $previousSchemas = $teachingService->schemasForUser($auth_user, $previousSchoolyear->id)
            ->filter(fn ($schema) => is_array($schema))
            ->values();

        if ($previousSchemas->isEmpty()) {
            abort(422, 'Import nicht möglich.');
        }

        $currentSchemaIds = $currentSchemas
            ->filter(fn ($schema) => is_array($schema))
            ->pluck('id')
            ->filter(fn ($schemaId) => is_scalar($schemaId) && (string) $schemaId !== '')
            ->map(fn ($schemaId) => (string) $schemaId)
            ->all();

        foreach ($currentSchemaIds as $schemaId) {
            $this->clearSchemaDataForCourses($auth_user, $schemaId, $currentSchoolyear->id);
        }

        $currentSchemasByName = $currentSchemas
            ->filter(fn ($schema) => is_array($schema))
            ->keyBy(fn (array $schema): string => (string) ($schema['name'] ?? ''));

        $importedSchemaNames = $previousSchemas
            ->map(fn (array $schema): string => (string) ($schema['name'] ?? ''))
            ->filter(fn (string $name): bool => $name !== '');

        $replacementSchemas = $previousSchemas
            ->map(function (array $previousSchema) use ($currentSchemasByName): array {
                $schemaName = (string) ($previousSchema['name'] ?? 'Standard');
                $matchingCurrentSchema = $currentSchemasByName->get($schemaName);

                return [
                    'id' => (string) ($matchingCurrentSchema['id'] ?? $previousSchema['id'] ?? ''),
                    'name' => $schemaName,
                    'works' => is_array($previousSchema['works'] ?? null) ? $previousSchema['works'] : [],
                    'grading' => is_array($previousSchema['grading'] ?? null) ? $previousSchema['grading'] : [],
                ];
            })
            ->merge(
                $currentSchemas
                    ->filter(fn ($schema) => is_array($schema))
                    ->reject(function (array $schema) use ($importedSchemaNames): bool {
                        return $importedSchemaNames->contains((string) ($schema['name'] ?? ''));
                    })
            )
            ->values()
            ->all();

        $teachingService->saveSchemas(
            $auth_user,
            $replacementSchemas,
            $currentSchoolyear->id
        );

        return response()->json([
            'settings' => $this->settingsPayloadForUser($auth_user->fresh(), $teachingService),
        ]);
    }

    public function resetSchema(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'selected_schema_id' => 'required|string|max:36',
        ]);

        $teachingService = new TeachingService;
        $currentSchoolyearId = $auth_user->schoolyear_id;
        $currentSchemas = $teachingService->schemasForUser($auth_user, $currentSchoolyearId);
        $currentSchema = $currentSchemas->firstWhere('id', (string) $validated['selected_schema_id']);

        if (! is_array($currentSchema)) {
            abort(404, 'Benotungsschema nicht gefunden.');
        }

        $schemaId = (string) $currentSchema['id'];

        $this->clearSchemaDataForCourses($auth_user, $schemaId, $currentSchoolyearId);
        $teachingService->saveSchemas(
            $auth_user,
            $this->replaceCurrentSchemaDefinition(
                $currentSchemas,
                $schemaId,
                $this->resetSchemaPayload($currentSchema)
            ),
            $currentSchoolyearId
        );

        return response()->json([
            'settings' => $this->settingsPayloadForUser($auth_user->fresh(), $teachingService),
        ]);
    }

    private function syncCourseBehaviourEntryTypesForSchool(
        User $authUser,
        string $kind,
        ?int $schoolyearId,
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
                ->whereHas('teachingCourse', function ($query) use ($authUser, $schoolyearId) {
                    $query->where('school_id', $authUser->school_id);
                    if ($schoolyearId !== null) {
                        $query->where('schoolyear_id', $schoolyearId);
                    }
                })
                ->update(['type' => $newType]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function teachingBehaviourForSchoolyear(User $user, ?int $schoolyearId): array
    {
        $bySchoolyear = $user->teaching_behaviour_by_schoolyear;

        if ($schoolyearId !== null && is_array($bySchoolyear)) {
            $entries = $bySchoolyear[(string) $schoolyearId] ?? null;

            if (is_array($entries)) {
                return $entries;
            }
        }

        return [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function storeTeachingBehaviourForSchoolyear(User $user, ?int $schoolyearId, array $entries): void
    {
        $bySchoolyear = is_array($user->teaching_behaviour_by_schoolyear) ? $user->teaching_behaviour_by_schoolyear : [];

        if ($schoolyearId !== null) {
            $bySchoolyear[(string) $schoolyearId] = array_values($entries);
            $user->teaching_behaviour_by_schoolyear = $bySchoolyear;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function teachingNotificationsForSchoolyear(User $user, ?int $schoolyearId): array
    {
        $bySchoolyear = $user->teaching_notifications_by_schoolyear;

        if ($schoolyearId !== null && is_array($bySchoolyear)) {
            $entries = $bySchoolyear[(string) $schoolyearId] ?? null;

            if (is_array($entries)) {
                return $entries;
            }
        }

        return [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function storeTeachingNotificationsForSchoolyear(User $user, ?int $schoolyearId, array $entries): void
    {
        $bySchoolyear = is_array($user->teaching_notifications_by_schoolyear) ? $user->teaching_notifications_by_schoolyear : [];

        if ($schoolyearId !== null) {
            $bySchoolyear[(string) $schoolyearId] = array_values($entries);
            $user->teaching_notifications_by_schoolyear = $bySchoolyear;
        }
    }

    private function teachingCourseBehaviourEntryCountForKind(User $user, ?int $schoolyearId, string $kind): int
    {
        return TeachingCourseBehaviourEntry::query()
            ->where('kind', $kind)
            ->whereHas('teachingCourse', function (Builder $query) use ($user, $schoolyearId): void {
                $query->where('school_id', $user->school_id)
                    ->where('user_id', $user->id);

                if ($schoolyearId !== null) {
                    $query->where('schoolyear_id', $schoolyearId);
                }
            })
            ->count();
    }

    /**
     * @return array<string, int>
     */
    private function teachingCourseBehaviourEntryCountsByTypeForKind(User $user, ?int $schoolyearId, string $kind): array
    {
        return TeachingCourseBehaviourEntry::query()
            ->selectRaw('type, COUNT(*) as aggregate')
            ->where('kind', $kind)
            ->whereHas('teachingCourse', function (Builder $query) use ($user, $schoolyearId): void {
                $query->where('school_id', $user->school_id)
                    ->where('user_id', $user->id);

                if ($schoolyearId !== null) {
                    $query->where('schoolyear_id', $schoolyearId);
                }
            })
            ->groupBy('type')
            ->pluck('aggregate', 'type')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    private function settingsPayloadForUser(User $user, TeachingService $teachingService): array
    {
        $schemas = $teachingService->schemasForUser($user, $user->schoolyear_id);
        $categoryEvaluationUsageCounts = $this->categoryEvaluationUsageCountsBySchema($user, $user->schoolyear_id);

        return [
            'teaching_schemas' => $schemas
                ->map(function ($schema) use ($categoryEvaluationUsageCounts) {
                    if (! is_array($schema)) {
                        return $schema;
                    }

                    $schemaId = (string) ($schema['id'] ?? '');
                    $grading = is_array($schema['grading'] ?? null) ? $schema['grading'] : [];
                    $grading['category_evaluation_usage_counts'] = $categoryEvaluationUsageCounts[$schemaId] ?? [];
                    $schema['grading'] = $grading;

                    return $schema;
                })
                ->all(),
            'teaching_behaviour' => $this->teachingBehaviourForSchoolyear($user, $user->schoolyear_id),
            'teaching_behaviour_usage_count' => $this->teachingCourseBehaviourEntryCountForKind($user, $user->schoolyear_id, 'behaviour'),
            'teaching_behaviour_usage_counts' => $this->teachingCourseBehaviourEntryCountsByTypeForKind($user, $user->schoolyear_id, 'behaviour'),
            'teaching_notifications' => $this->teachingNotificationsForSchoolyear($user, $user->schoolyear_id),
            'teaching_notifications_usage_count' => $this->teachingCourseBehaviourEntryCountForKind($user, $user->schoolyear_id, 'notification'),
            'teaching_notifications_usage_counts' => $this->teachingCourseBehaviourEntryCountsByTypeForKind($user, $user->schoolyear_id, 'notification'),
            'teaching_show_behaviour' => $user->teaching_show_behaviour ?? true,
        ];
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function categoryEvaluationUsageCountsBySchema(User $user, ?int $schoolyearId): array
    {
        $courses = TeachingCourse::query()
            ->where('school_id', $user->school_id)
            ->where('user_id', $user->id)
            ->when($schoolyearId !== null, fn (Builder $query) => $query->where('schoolyear_id', $schoolyearId))
            ->whereNotNull('teaching_schema_id')
            ->get(['id', 'teaching_schema_id']);

        if ($courses->isEmpty()) {
            return [];
        }

        $schemaIdsByCourseId = $courses
            ->pluck('teaching_schema_id', 'id')
            ->map(fn ($schemaId): string => (string) $schemaId);

        $counts = [];

        TeachingCourseStudentCategoryEvaluation::query()
            ->whereIn('teaching_course_id', $courses->pluck('id'))
            ->get(['teaching_course_id', 'value'])
            ->each(function (TeachingCourseStudentCategoryEvaluation $evaluation) use (&$counts, $schemaIdsByCourseId): void {
                $schemaId = $schemaIdsByCourseId->get($evaluation->teaching_course_id);
                $value = trim((string) $evaluation->value);

                if ($schemaId === null || $value === '') {
                    return;
                }

                $counts[$schemaId] ??= [];
                $counts[$schemaId][$value] = (int) (($counts[$schemaId][$value] ?? 0) + 1);
            });

        return $counts;
    }

    private function previousSchoolyearForImport(?Schoolyear $schoolyear): ?Schoolyear
    {
        if (! $schoolyear) {
            return null;
        }

        $previousConcern = $this->previousSchoolyearConcern($schoolyear->concerns);

        if ($previousConcern === null) {
            return null;
        }

        return Schoolyear::query()
            ->where('school_id', $schoolyear->school_id)
            ->get()
            ->first(function (Schoolyear $candidate) use ($previousConcern): bool {
                return $this->normalizeSchoolyearConcern($candidate->concerns) === $previousConcern;
            });
    }

    private function matchingPreviousSchemaForImport(
        TeachingService $teachingService,
        User $user,
        int $schoolyearId,
        string $schemaName
    ): ?array {
        $schema = $teachingService->schemasForUser($user, $schoolyearId)
            ->first(function ($candidate) use ($schemaName): bool {
                return is_array($candidate)
                    && (string) ($candidate['name'] ?? '') === $schemaName;
            });

        return is_array($schema) ? $schema : null;
    }

    private function clearSchemaDataForCourses(User $user, string $schemaId, ?int $schoolyearId): void
    {
        $courseIds = TeachingCourse::query()
            ->where('school_id', $user->school_id)
            ->where('user_id', $user->id)
            ->where('teaching_schema_id', $schemaId)
            ->when($schoolyearId !== null, fn (Builder $query) => $query->where('schoolyear_id', $schoolyearId))
            ->pluck('id');

        if ($courseIds->isEmpty()) {
            return;
        }

        TeachingCourseStudentCategoryEvaluation::query()
            ->whereIn('teaching_course_id', $courseIds)
            ->delete();

        TeachingCourseStudentEntry::query()
            ->whereIn('teaching_course_id', $courseIds)
            ->delete();

        TeachingCourseWork::query()
            ->whereIn('teaching_course_id', $courseIds)
            ->delete();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $schemas
     * @return array<int, array<string, mixed>>
     */
    private function replaceCurrentSchemaDefinition(Collection $schemas, string $schemaId, array $replacementSchema): array
    {
        return $schemas
            ->map(function ($schema) use ($schemaId, $replacementSchema) {
                if (! is_array($schema)) {
                    return $schema;
                }

                return (string) ($schema['id'] ?? '') === $schemaId
                    ? $replacementSchema
                    : $schema;
            })
            ->filter(fn ($schema) => is_array($schema))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $currentSchema
     * @return array<string, mixed>
     */
    private function resetSchemaPayload(array $currentSchema): array
    {
        return [
            'id' => (string) ($currentSchema['id'] ?? ''),
            'name' => (string) ($currentSchema['name'] ?? 'Standard'),
            'works' => [],
            'grading' => [
                'semester_count' => 1,
                'semester_1_weight' => 100,
                'semester_2_weight' => 0,
                'use_semester_grade_only' => false,
                'category_evaluation_values' => [],
                'default_category_evaluation_value' => '',
                'categories' => [],
            ],
        ];
    }

    private function previousSchoolyearConcern(?string $value): ?string
    {
        $normalizedValue = $this->normalizeSchoolyearConcern($value);

        if (! preg_match('/^(\d{4})\/(\d{2})$/', $normalizedValue, $matches)) {
            return null;
        }

        $startYear = (int) $matches[1];
        $endYear = (int) substr((string) ($startYear + 1), -2);

        return sprintf('%d/%02d', $startYear - 1, $endYear - 1);
    }

    private function normalizeSchoolyearConcern(?string $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        preg_match('/(\d{4})\/(\d{2}|\d{4})/', $value, $matches);

        if ($matches === []) {
            return '';
        }

        return sprintf('%s/%s', $matches[1], substr($matches[2], -2));
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
