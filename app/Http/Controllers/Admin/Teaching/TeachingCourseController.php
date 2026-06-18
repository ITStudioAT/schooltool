<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Teaching\CourseResource;
use App\Http\Resources\Admin\Teaching\StudentResource;
use App\Models\Import116;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseDate;
use App\Models\TeachingCourseStudent;
use App\Models\TeachingCurriculum;
use App\Models\User;
use App\Services\TeachingCourseService;
use App\Services\TeachingCourseWorkEntrySyncService;
use App\Services\TeachingService;
use App\Services\TeachingStudentPerformancePdfService;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TeachingCourseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, TeachingCourseService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $coursesQuery = TeachingCourse::with([
            'user:id,first_name,last_name,short,email,teaching_behaviour_by_schoolyear,teaching_notifications_by_schoolyear,teaching_show_behaviour,teaching_student_grade_columns_by_schoolyear',
            'teachingCurriculum:id,school_id,schoolyear_id,user_id,title,description,semester_count',
            'teachingCourseDates' => fn ($q) => $q->orderBy('date')->orderByRaw('JSON_EXTRACT(hours, "$[0]")'),
            'teachingCourseDates.materials.attachments',
            'teachingCourseStudents',
            'teachingCourseStudentsWithTrashed',
        ])
            ->where('school_id', $auth_user->school_id)
            ->where('schoolyear_id', $auth_user->schoolyear_id);

        if ($auth_user->hasRole('teacher') && ! $auth_user->hasAnyRole(['admin', 'teaching_admin'])) {
            $coursesQuery->where('user_id', $auth_user->id);
        }

        $courses = $coursesQuery
            ->orderBy('title')
            ->get();

        $courses->each(function (TeachingCourse $course): void {
            $course->teachingCourseDates->each(
                fn (TeachingCourseDate $courseDate) => $courseDate->setRelation('teachingCourse', $course)
            );
        });

        $allCourseStudents = $courses->flatMap(
            fn (TeachingCourse $course) => $course->teachingCourseStudentsWithTrashed
        );

        $userIds = $allCourseStudents
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();
        $importIds = $allCourseStudents
            ->pluck('import116_id')
            ->filter()
            ->unique()
            ->values();

        $studentsById = $userIds->isEmpty()
            ? collect()
            : User::whereIn('id', $userIds)->get()->keyBy('id');
        $importsById = $importIds->isEmpty()
            ? collect()
            : Import116::where('school_id', $auth_user->school_id)->whereIn('id', $importIds)->get()->keyBy('id');

        $teachingService = new TeachingService;
        $schemaCache = [];
        $removalReasonsByCourseId = $service->removalReasonsForCourses($courses);

        $courses->each(function (TeachingCourse $course) use ($auth_user, $studentsById, $importsById, $request, $teachingService, &$schemaCache, $removalReasonsByCourseId) {
            $removalReasons = $removalReasonsByCourseId[(int) $course->id] ?? [];
            $courseActor = $this->teachingCourseActor($auth_user, $course);
            $courseSchema = $this->teachingSchemaForCourse($courseActor, $course, $teachingService, $schemaCache);

            $activeStudents = [];
            foreach ($course->teachingCourseStudents as $courseStudent) {
                $payload = $this->serializeCourseStudent($courseStudent, $studentsById, $importsById, $request, $removalReasons);
                if ($payload) {
                    $activeStudents[] = $payload;
                }
            }

            $deletedStudents = [];
            foreach ($course->teachingCourseStudentsWithTrashed as $courseStudent) {
                if (! $courseStudent->trashed()) {
                    continue;
                }

                $payload = $this->serializeCourseStudent($courseStudent, $studentsById, $importsById, $request, $removalReasons);
                if ($payload) {
                    $deletedStudents[] = $payload;
                }
            }

            $course->setAttribute('students', $activeStudents);
            $course->setAttribute('students_deleted', $deletedStudents);
            $course->setAttribute('teacher_teaching_schema', $courseSchema);
            $course->setAttribute('teacher_teaching_behaviour', $this->teachingBehaviourForSchoolyear($courseActor, $course->schoolyear_id));
            $course->setAttribute('teacher_teaching_notifications', $this->teachingNotificationsForSchoolyear($courseActor, $course->schoolyear_id));
            $course->setAttribute('teacher_teaching_show_behaviour', (bool) ($courseActor->teaching_show_behaviour ?? true));
            $course->setAttribute('teaching_student_grade_columns', $this->teachingStudentGradeColumnsForCourse($course, $courseActor));
        });

        $classes = Import116::where('school_id', $auth_user->school_id)
            ->where('schoolyear_id', $auth_user->schoolyear_id)
            ->distinct()
            ->orderBy('class')
            ->pluck('class');

        return response()->json([
            'data' => CourseResource::collection($courses),
            'classes' => $classes,
        ]);
    }

    public function studentPerformancesPdf(
        TeachingCourse $course,
        TeachingCourseStudent $course_student,
        TeachingStudentPerformancePdfService $service
    ): Responsable {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->authorizeTeachingCourseAccess($course, $auth_user);

        if ((int) $course_student->teaching_course_id !== (int) $course->id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return $service->download($course, $course_student);
    }

    public function coursePerformancesPdf(
        Request $request,
        TeachingCourse $course,
        TeachingStudentPerformancePdfService $service
    ): Responsable {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->authorizeTeachingCourseAccess($course, $auth_user);

        $validated = $request->validate([
            'course_student_id' => ['nullable', 'integer'],
        ]);

        $courseStudent = null;
        if (! empty($validated['course_student_id'])) {
            $courseStudent = TeachingCourseStudent::query()->findOrFail($validated['course_student_id']);

            if ((int) $courseStudent->teaching_course_id !== (int) $course->id) {
                abort(403, 'Sie haben keine Berechtigung');
            }
        } elseif (! $course->teachingCourseStudents()->exists()) {
            abort(422, 'Keine Schüler:innen für den Druck vorhanden.');
        }

        return $service->downloadCourse($course, $courseStudent);
    }

    public function courseGradesPdf(
        Request $request,
        TeachingCourse $course,
        TeachingStudentPerformancePdfService $service
    ): Responsable {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->authorizeTeachingCourseAccess($course, $auth_user);

        $validated = $request->validate([
            'semesters' => ['required', 'string'],
        ]);

        $semesters = collect(explode(',', $validated['semesters']))
            ->map(fn (string $v): int => (int) trim($v))
            ->filter(fn (int $v): bool => in_array($v, [1, 2]))
            ->unique()
            ->values()
            ->all();

        if (empty($semesters)) {
            abort(422, 'Mindestens ein Semester muss ausgewählt werden.');
        }

        if (! $course->teachingCourseStudents()->exists()) {
            abort(422, 'Keine Schüler:innen für den Druck vorhanden.');
        }

        return $service->downloadGrades($course, $semesters);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, TeachingCourseService $service, TeachingCourseWorkEntrySyncService $entrySyncService)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $classes = Import116::where('school_id', $auth_user->school_id)
            ->where('schoolyear_id', $auth_user->schoolyear_id)
            ->distinct()
            ->orderBy('class')
            ->pluck('class');
        $schemaIds = (new TeachingService)->schemaIdsForUser($auth_user, $auth_user->schoolyear_id);
        $curriculumRule = Rule::exists(TeachingCurriculum::query()->getModel()->getTable(), 'id')
            ->where(fn (Builder $query) => $query
                ->where('school_id', $auth_user->school_id)
                ->where('schoolyear_id', $auth_user->schoolyear_id)
                ->where('user_id', $auth_user->id));

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'classes' => 'required|array|min:1',
            'classes.*' => ['required', 'string', Rule::in($classes)],
            'students' => 'nullable|array',
            'students.*.stars' => 'nullable|array',
            'students.*.stars.*.id' => 'nullable|string|max:64',
            'students.*.stars.*.value' => 'nullable|integer|in:1',
            'students.*.stars.*.comment' => 'nullable|string|max:1024',
            'students.*.stars.*.date' => 'nullable|date',
            'students.*.canceled_at' => 'nullable|date',
            'students_info' => 'nullable|array',
            'students_info.*.canceled_at' => 'nullable|date',
            'students_deleted' => 'nullable|array',
            'students_deleted_info' => 'nullable|array',
            'students_deleted_info.*.canceled_at' => 'nullable|date',
            'teaching_schema_id' => ['required', 'string', 'max:36', Rule::in($schemaIds)],
            'teaching_curriculum_id' => ['nullable', 'integer', $curriculumRule],
        ]);

        $sortedClasses = $validated['classes'];
        sort($sortedClasses);

        // Prefer students_info (has user_id/import116_id) over students (just IDs)
        $studentsInfoPayload = $request->input('students_info', []);
        if (is_array($studentsInfoPayload) && ! empty($studentsInfoPayload)) {
            $studentsPayload = $studentsInfoPayload;
        } else {
            $studentsPayload = $validated['students'] ?? [];
        }

        $studentsDeletedInfoPayload = $request->input('students_deleted_info', []);
        if (is_array($studentsDeletedInfoPayload) && ! empty($studentsDeletedInfoPayload)) {
            $studentsDeletedPayload = $studentsDeletedInfoPayload;
        } else {
            $studentsDeletedPayload = $validated['students_deleted'] ?? [];
        }

        $course = TeachingCourse::create([
            'school_id' => $auth_user->school_id,
            'schoolyear_id' => $auth_user->schoolyear_id,
            'user_id' => $auth_user->id,
            'title' => $validated['title'],
            'classes' => $sortedClasses,
            'teaching_schema_id' => $validated['teaching_schema_id'] ?? null,
            'teaching_curriculum_id' => $validated['teaching_curriculum_id'] ?? null,
        ]);

        $service->syncCourseStudents($course, $studentsPayload, $studentsDeletedPayload);
        $entrySyncService->syncNonGroupWorksForCourse($course);

        return response()->json(new CourseResource($course), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(TeachingCourse $course)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TeachingCourse $course, TeachingCourseService $service, TeachingCourseWorkEntrySyncService $entrySyncService)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->authorizeTeachingCourseAccess($course, $auth_user);

        $classes = Import116::where('school_id', $auth_user->school_id)
            ->where('schoolyear_id', $course->schoolyear_id)
            ->distinct()
            ->orderBy('class')
            ->pluck('class');
        $courseActor = $this->teachingCourseActor($auth_user, $course);
        $schemaIds = (new TeachingService)->schemaIdsForUser($courseActor, $course->schoolyear_id);
        $curriculumRule = Rule::exists(TeachingCurriculum::query()->getModel()->getTable(), 'id')
            ->where(fn (Builder $query) => $query
                ->where('school_id', $course->school_id)
                ->where('schoolyear_id', $course->schoolyear_id)
                ->where('user_id', $courseActor->id));

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:4096',
            'classes' => 'required|array|min:1',
            'classes.*' => ['required', 'string', Rule::in($classes)],
            'students' => 'nullable|array',
            'students.*.stars' => 'nullable|array',
            'students.*.stars.*.id' => 'nullable|string|max:64',
            'students.*.stars.*.value' => 'nullable|integer|in:1',
            'students.*.stars.*.comment' => 'nullable|string|max:1024',
            'students.*.stars.*.date' => 'nullable|date',
            'students.*.canceled_at' => 'nullable|date',
            'students_info' => 'nullable|array',
            'students_info.*.canceled_at' => 'nullable|date',
            'students_deleted' => 'nullable|array',
            'students_deleted_info' => 'nullable|array',
            'students_deleted_info.*.canceled_at' => 'nullable|date',
            'teaching_schema_id' => ['required', 'string', 'max:36', Rule::in($schemaIds)],
            'teaching_curriculum_id' => ['nullable', 'integer', $curriculumRule],
        ]);

        $sortedClasses = $validated['classes'];
        sort($sortedClasses);

        // Prefer students_info (has user_id/import116_id) over students (just IDs)
        $studentsInfoPayload = $request->input('students_info', []);
        if (is_array($studentsInfoPayload) && ! empty($studentsInfoPayload)) {
            $studentsPayload = $studentsInfoPayload;
        } else {
            $studentsPayload = $validated['students'] ?? [];
        }

        $studentsDeletedInfoPayload = $request->input('students_deleted_info', []);
        if (is_array($studentsDeletedInfoPayload) && ! empty($studentsDeletedInfoPayload)) {
            $studentsDeletedPayload = $studentsDeletedInfoPayload;
        } else {
            $studentsDeletedPayload = $validated['students_deleted'] ?? [];
        }

        $course->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'classes' => $sortedClasses,
            'teaching_schema_id' => $validated['teaching_schema_id'],
            'teaching_curriculum_id' => $validated['teaching_curriculum_id'] ?? null,
        ]);

        $service->syncCourseStudents($course, $studentsPayload, $studentsDeletedPayload);
        $entrySyncService->syncNonGroupWorksForCourse($course);

        return response()->json(new CourseResource($course));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TeachingCourse $course)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->authorizeTeachingCourseAccess($course, $auth_user);

        if ($course->hasDependencies()) {
            abort(409, 'Der Kurs hat noch Abhängigkeiten und kann nicht gelöscht werden');
        }

        $course->delete();

        return response()->json(null, 204);
    }

    /**
     * @param  array<string, Collection<string, array<string, mixed>>>  $schemaCache
     * @return array<string, mixed>|null
     */
    private function teachingSchemaForCourse(
        User $courseActor,
        TeachingCourse $course,
        TeachingService $teachingService,
        array &$schemaCache
    ): ?array {
        $schemaId = trim((string) ($course->teaching_schema_id ?? ''));
        if ($schemaId === '') {
            return null;
        }

        $cacheKey = implode(':', [
            (string) $courseActor->id,
            (string) $course->schoolyear_id,
        ]);

        if (! array_key_exists($cacheKey, $schemaCache)) {
            $schemaCache[$cacheKey] = $teachingService
                ->schemasForUser($courseActor, $course->schoolyear_id)
                ->keyBy(fn (array $schema): string => (string) ($schema['id'] ?? ''));
        }

        $schema = $schemaCache[$cacheKey]->get($schemaId);

        return is_array($schema) ? Arr::only($schema, ['id', 'name', 'works', 'grading']) : null;
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
     * @return array{show_sem1: bool, show_sem2: bool, show_year: bool}
     */
    private function teachingStudentGradeColumnsForCourse(TeachingCourse $course, User $user): array
    {
        if (is_array($course->teaching_student_grade_columns)) {
            return [
                'show_sem1' => (bool) ($course->teaching_student_grade_columns['show_sem1'] ?? false),
                'show_sem2' => (bool) ($course->teaching_student_grade_columns['show_sem2'] ?? false),
                'show_year' => (bool) ($course->teaching_student_grade_columns['show_year'] ?? false),
            ];
        }

        $bySchoolyear = $user->teaching_student_grade_columns_by_schoolyear;

        if ($course->schoolyear_id === null || ! is_array($bySchoolyear)) {
            return [
                'show_sem1' => false,
                'show_sem2' => false,
                'show_year' => false,
            ];
        }

        $columns = $bySchoolyear[(string) $course->schoolyear_id] ?? null;

        if (! is_array($columns)) {
            return [
                'show_sem1' => false,
                'show_sem2' => false,
                'show_year' => false,
            ];
        }

        return [
            'show_sem1' => (bool) ($columns['show_sem1'] ?? false),
            'show_sem2' => (bool) ($columns['show_sem2'] ?? false),
            'show_year' => (bool) ($columns['show_year'] ?? false),
        ];
    }

    private function serializeCourseStudent(
        TeachingCourseStudent $courseStudent,
        Collection $studentsById,
        Collection $importsById,
        Request $request,
        array $removalReasons = []
    ): ?array {
        $source = $this->courseStudentPayloadSource($courseStudent, $studentsById, $importsById);

        if ($source === 'user') {
            $student = $studentsById->get((int) $courseStudent->user_id);
            if (! $student) {
                return null;
            }

            $payload = (new StudentResource($student))->toArray($request);
        } else {
            $import = $courseStudent->import116_id ? $importsById->get((int) $courseStudent->import116_id) : null;
            if (! $import) {
                return null;
            }

            $payload = [
                'id' => $import->id,
                'first_name' => $import->first_name,
                'last_name' => $import->last_name,
                'email' => $import->email,
                'email_is_placeholder' => false,
                'schoolclass' => $import->class,
                'class' => $import->class,
            ];
        }

        $resolvedId = $source === 'user'
            ? (int) $courseStudent->user_id
            : (int) $courseStudent->import116_id;
        if (! $resolvedId) {
            return null;
        }

        $payload['id'] = $resolvedId;
        $payload['course_student_id'] = $courseStudent->id;
        $payload['user_id'] = $source === 'user' ? $courseStudent->user_id : null;
        $payload['import116_id'] = $courseStudent->import116_id;
        $payload['comment'] = $courseStudent->comment;
        $payload['sem_1_grade'] = $courseStudent->sem_1_grade;
        $payload['sem_2_grade'] = $courseStudent->sem_2_grade;
        $payload['sem_grade'] = $courseStudent->sem_grade;
        $payload['behaviour_1_grade'] = $courseStudent->behaviour_1_grade;
        $payload['behaviour_2_grade'] = $courseStudent->behaviour_2_grade;
        $payload['behaviour_grade'] = $courseStudent->behaviour_grade;
        $payload['stars'] = $courseStudent->stars ?? [];
        $payload['canceled_at'] = $courseStudent->canceled_at?->toDateTimeString();

        $studentKey = $payload['user_id']
            ? 'u:'.$payload['user_id']
            : ($payload['import116_id'] ? 'i:'.$payload['import116_id'] : null);
        $removalReasonCode = $studentKey ? ($removalReasons[$studentKey] ?? null) : null;

        $payload['is_removable'] = $removalReasonCode === null;
        $payload['remove_block_reason'] = $this->translateRemovalReasonCode($removalReasonCode);

        return $payload;
    }

    private function courseStudentPayloadSource(
        TeachingCourseStudent $courseStudent,
        Collection $studentsById,
        Collection $importsById
    ): string {
        if (! $courseStudent->user_id) {
            return 'import';
        }

        if (! $courseStudent->import116_id) {
            return 'user';
        }

        $student = $studentsById->get((int) $courseStudent->user_id);
        $import = $importsById->get((int) $courseStudent->import116_id);

        if ($student && $import && ! $this->studentReferencesMatch($student, $import)) {
            return 'import';
        }

        return 'user';
    }

    private function studentReferencesMatch(User $student, Import116 $import): bool
    {
        $studentEmail = $this->normalizedStudentReferenceValue($student->email ?? null);
        $importEmail = $this->normalizedStudentReferenceValue($import->email ?? null);

        if ($studentEmail !== '' && $importEmail !== '') {
            return $studentEmail === $importEmail;
        }

        $studentFirstName = $this->normalizedStudentReferenceValue($student->first_name ?? null);
        $studentLastName = $this->normalizedStudentReferenceValue($student->last_name ?? null);
        $importFirstName = $this->normalizedStudentReferenceValue($import->first_name ?? null);
        $importLastName = $this->normalizedStudentReferenceValue($import->last_name ?? null);

        if ($studentFirstName === '' || $studentLastName === '' || $importFirstName === '' || $importLastName === '') {
            return false;
        }

        if ($studentFirstName !== $importFirstName || $studentLastName !== $importLastName) {
            return false;
        }

        $studentClass = $this->normalizedStudentReferenceValue($student->schoolclass ?? null);
        $importClass = $this->normalizedStudentReferenceValue($import->class ?? null);

        return $studentClass === '' || $importClass === '' || $studentClass === $importClass;
    }

    private function normalizedStudentReferenceValue(mixed $value): string
    {
        return Str::lower(trim((string) $value));
    }

    private function translateRemovalReasonCode(?string $reasonCode): ?string
    {
        if (! $reasonCode) {
            return null;
        }

        return match ($reasonCode) {
            'course_student_data' => 'Entfernen nicht möglich: Beim Schüler sind bereits Kursdaten erfasst.',
            'dependent_records' => 'Entfernen nicht möglich: Es gibt bereits abhängige Einträge in anderen Kurs-Tabellen.',
            default => 'Entfernen nicht möglich: Für diesen Schüler bestehen bereits Abhängigkeiten.',
        };
    }
}
