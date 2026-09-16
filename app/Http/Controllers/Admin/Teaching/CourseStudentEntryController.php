<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseBehaviourEntry;
use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Services\TeachingCourseEntryTransferService;
use App\Services\TeachingCourseStudentEntryService;
use App\Services\TeachingCourseWorkEntrySyncService;
use App\Services\TeachingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CourseStudentEntryController extends Controller
{
    public function index(Request $request, TeachingCourseWorkEntrySyncService $entrySyncService)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'course_id' => 'required|integer|exists:teaching_courses,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'include_table_data' => 'nullable|boolean',
        ]);

        $course = TeachingCourse::findOrFail($validated['course_id']);
        $this->authorizeTeachingCourseAccess($course, $auth_user);

        $query = TeachingCourseStudentEntry::where('teaching_course_id', $course->id);

        if (! empty($validated['user_id'])) {
            $student = User::findOrFail($validated['user_id']);
            if ($student->school_id !== $auth_user->school_id) {
                abort(403, 'Sie haben keine Berechtigung');
            }
            $query->where('user_id', $student->id);
        }

        $entries = $query
            ->withExists([
                'notifications as has_pending_notification_confirmation' => fn (Builder $query): Builder => $query
                    ->whereNotNull('informed_at')
                    ->whereNull('confirmed_at'),
            ])
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $defaultsByType = $this->defaultGradesByType($course, $auth_user);
        $entries->each(function (TeachingCourseStudentEntry $entry) use ($defaultsByType) {
            $this->attachEffectiveGrade($entry, $defaultsByType);
        });

        $response = ['data' => $entries];
        if (! ($validated['include_table_data'] ?? false)) {
            return response()->json($response);
        }

        $response['behaviour_entries'] = TeachingCourseBehaviourEntry::query()
            ->where('teaching_course_id', $course->id)
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
        $response['course_works'] = $course->teachingCourseWorks()
            ->with('teachingCourseWorkGroupStudents')
            ->orderBy('date_for_all_groups', 'desc')
            ->get()
            ->map(fn (TeachingCourseWork $work): array => $entrySyncService->serializeWork($work))
            ->values();

        return response()->json($response);
    }

    public function store(Request $request, TeachingCourseStudentEntryService $entryService)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = TeachingCourse::findOrFail($request->input('teaching_course_id'));
        $this->authorizeTeachingCourseAccess($course, $auth_user);

        $allowedTypes = $entryService->allowedTypesForCourse(
            $this->teachingCourseActor($auth_user, $course),
            $course
        );

        $validated = $request->validate([
            'teaching_course_id' => 'required|integer|exists:teaching_courses,id',
            'user_id' => 'required|integer|exists:users,id',
            'type' => ['required', 'string', 'max:255', Rule::in($allowedTypes)],
            'grade' => $entryService->gradeRulesForCourse($this->teachingCourseActor($auth_user, $course), $course, $request->input('type')),
            'date' => 'nullable|date',
            'description' => 'nullable|string|max:1024',
            'teaching_course_work_id' => 'nullable|integer|exists:teaching_course_works,id',
            'status' => 'nullable|array',
        ]);

        $course = TeachingCourse::findOrFail($validated['teaching_course_id']);
        $this->authorizeTeachingCourseAccess($course, $auth_user);

        $student = User::findOrFail($validated['user_id']);
        if ($student->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->validateLinkedWorkMaximumPlus($validated, $course, $this->teachingCourseActor($auth_user, $course), $entryService);
        $entry = TeachingCourseStudentEntry::create($validated);
        $this->loadPendingNotificationConfirmationState($entry);
        $this->attachEffectiveGrade($entry, $this->defaultGradesByType($course, $auth_user));

        return response()->json(['data' => $entry], 201);
    }

    public function transfer(Request $request, TeachingCourseStudentEntry $course_student_entry, TeachingCourseStudentEntryService $entryService, TeachingCourseEntryTransferService $transferService): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $entries = DB::transaction(function () use ($request, $course_student_entry, $entryService, $transferService, $authUser) {
            $source = TeachingCourseStudentEntry::query()->lockForUpdate()->findOrFail($course_student_entry->id);
            $course = $source->teachingCourse;
            abort_unless($course, 403, 'Sie haben keine Berechtigung');
            $this->authorizeTeachingCourseAccess($course, $authUser);
            abort_if(($source->source ?? 'manual') !== 'manual', 409, 'Dieser Eintrag wird aus einer Arbeit abgeleitet und kann nicht übertragen werden.');

            $userIds = $transferService->targetUserIds($request, $course, $source->user_id, $source->date);
            $actor = $this->teachingCourseActor($authUser, $course);
            $payload = Validator::make($source->only(['type', 'grade', 'description', 'status', 'teaching_course_work_id']), [
                'type' => ['required', 'string', 'max:255', Rule::in($entryService->allowedTypesForCourse($actor, $course))],
                'grade' => $entryService->gradeRulesForCourse($actor, $course, $source->type),
                'description' => ['nullable', 'string', 'max:1024'],
                'status' => ['nullable', 'array'],
                'teaching_course_work_id' => ['nullable', 'integer', 'exists:teaching_course_works,id'],
            ])->validate();
            $this->validateLinkedWorkMaximumPlus($payload, $course, $actor, $entryService);
            $defaults = $this->defaultGradesByType($course, $authUser);

            return collect($userIds)->map(function (int $userId) use ($payload, $source, $course, $defaults): TeachingCourseStudentEntry {
                $entry = TeachingCourseStudentEntry::query()->create([
                    ...$payload,
                    'teaching_course_id' => $course->id,
                    'user_id' => $userId,
                    'date' => $source->date,
                    'source' => 'manual',
                ]);
                $this->loadPendingNotificationConfirmationState($entry);
                $this->attachEffectiveGrade($entry, $defaults);

                return $entry;
            });
        });

        return response()->json(['data' => $entries], 201);
    }

    public function update(Request $request, TeachingCourseStudentEntry $course_student_entry, TeachingCourseStudentEntryService $entryService)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (($course_student_entry->source ?? 'manual') === 'course_work') {
            abort(409, 'Dieser Eintrag wird aus einer Arbeit abgeleitet und kann hier nicht direkt geändert werden.');
        }

        $course = $course_student_entry->teachingCourse;
        if (! $course) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->authorizeTeachingCourseAccess($course, $auth_user);

        $allowedTypes = $entryService->allowedTypesForCourse(
            $this->teachingCourseActor($auth_user, $course),
            $course
        );

        $validated = $request->validate([
            'type' => ['required', 'string', 'max:255', Rule::in($allowedTypes)],
            'grade' => $entryService->gradeRulesForCourse($this->teachingCourseActor($auth_user, $course), $course, $request->input('type')),
            'date' => 'nullable|date',
            'description' => 'nullable|string|max:1024',
            'teaching_course_work_id' => 'nullable|integer|exists:teaching_course_works,id',
            'status' => 'nullable|array',
        ]);

        if (! array_key_exists('grade', $validated) && $validated['type'] !== $course_student_entry->type) {
            Validator::make(['grade' => $course_student_entry->grade], [
                'grade' => $entryService->gradeRulesForCourse($this->teachingCourseActor($auth_user, $course), $course, $validated['type']),
            ])->validate();
        }

        $student = $course_student_entry->user;
        if (! $student || $student->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->validateLinkedWorkMaximumPlus([
            'teaching_course_work_id' => $course_student_entry->teaching_course_work_id,
            'grade' => $course_student_entry->grade,
            ...$validated,
        ], $course, $this->teachingCourseActor($auth_user, $course), $entryService);
        $course_student_entry->update($validated);
        $this->loadPendingNotificationConfirmationState($course_student_entry);
        $this->attachEffectiveGrade($course_student_entry, $this->defaultGradesByType($course, $auth_user));

        return response()->json(['data' => $course_student_entry]);
    }

    private function validateLinkedWorkMaximumPlus(array $validated, TeachingCourse $course, User $actor, TeachingCourseStudentEntryService $entryService): void
    {
        if (empty($validated['teaching_course_work_id'])) {
            return;
        }

        $work = $course->teachingCourseWorks()->find($validated['teaching_course_work_id']);
        if (! $work) {
            throw ValidationException::withMessages(['teaching_course_work_id' => 'Die Arbeit gehört nicht zu diesem Kurs.']);
        }

        $definition = $entryService->entryDefinitionsForCourse($actor, $course)->firstWhere('short_name', $work->type);
        if ($definition?->properties_mode !== 'plus' || ! $definition->allows_maximum_plus) {
            return;
        }

        $grade = trim((string) ($validated['grade'] ?? ''));
        if (preg_match('/^\++$/', $grade) !== 1) {
            return;
        }

        if (! $work->maximum_plus || strlen($grade) > $work->maximum_plus) {
            throw ValidationException::withMessages(['grade' => ! $work->maximum_plus
                ? 'Bitte zuerst die maximal erreichbare Anzahl an Plus bei der Arbeit festlegen.'
                : 'Die Anzahl an Plus darf die maximal erreichbare Anzahl nicht überschreiten.']);
        }
    }

    public function destroy(TeachingCourseStudentEntry $course_student_entry)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (($course_student_entry->source ?? 'manual') === 'course_work') {
            abort(409, 'Dieser Eintrag wird aus einer Arbeit abgeleitet und kann hier nicht direkt gelöscht werden.');
        }

        $course = $course_student_entry->teachingCourse;
        if (! $course) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->authorizeTeachingCourseAccess($course, $auth_user);

        $student = $course_student_entry->user;
        if (! $student || $student->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course_student_entry->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, string>
     */
    private function defaultGradesByType(TeachingCourse $course, User $authUser): array
    {
        if (! $course->teaching_schema_id) {
            return [];
        }

        $schemaOwner = $course->user ?: $authUser;
        $schema = (new TeachingService)->schemaById($schemaOwner, (string) $course->teaching_schema_id, $course->schoolyear_id);
        if (! is_array($schema)) {
            return [];
        }

        $defaults = [];
        foreach ((array) ($schema['works'] ?? []) as $work) {
            if (! is_array($work)) {
                continue;
            }

            $type = trim((string) ($work['short_name'] ?? ''));
            if ($type === '') {
                continue;
            }

            $defaultGrade = trim((string) ($work['default_grade'] ?? ''));
            if ($defaultGrade === '') {
                continue;
            }

            $grades = is_array($work['grades'] ?? null) ? $work['grades'] : [];
            $hasGrade = collect($grades)->contains(function ($grade) use ($defaultGrade) {
                if (! is_array($grade)) {
                    return false;
                }

                return strtoupper(trim((string) ($grade['grade'] ?? ''))) === strtoupper($defaultGrade);
            });
            if (! $hasGrade) {
                continue;
            }

            $defaults[$type] = $defaultGrade;
        }

        return $defaults;
    }

    private function attachEffectiveGrade(TeachingCourseStudentEntry $entry, array $defaultsByType): void
    {
        $rawGrade = trim((string) ($entry->grade ?? ''));
        if ($rawGrade !== '') {
            $entry->setAttribute('effective_grade', $rawGrade);

            return;
        }

        $type = trim((string) ($entry->type ?? ''));
        $entry->setAttribute('effective_grade', $type !== '' ? ($defaultsByType[$type] ?? null) : null);
    }

    private function loadPendingNotificationConfirmationState(TeachingCourseStudentEntry $entry): void
    {
        $entry->loadExists([
            'notifications as has_pending_notification_confirmation' => fn (Builder $query): Builder => $query
                ->whereNotNull('informed_at')
                ->whereNull('confirmed_at'),
        ]);
    }
}
