<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Teaching\CourseResource;
use App\Http\Resources\Admin\Teaching\CourseSummaryResource;
use App\Http\Resources\Admin\Teaching\StudentResource;
use App\Models\Import116;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseDate;
use App\Models\TeachingCourseStudent;
use App\Models\TeachingCurriculum;
use App\Models\TeachingEntryArea;
use App\Models\User;
use App\Services\PdfTableGenerator;
use App\Services\TeachingClassHeadEmailService;
use App\Services\TeachingCourseService;
use App\Services\TeachingCourseWorkEntrySyncService;
use App\Services\TeachingHolidaySyncService;
use App\Services\TeachingService;
use App\Services\TeachingStudentPerformancePdfService;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class TeachingCourseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(TeachingClassHeadEmailService $classHeadEmailService): JsonResponse
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $coursesQuery = TeachingCourse::with([
            'teachingEntryArea:id,name',
            'teachingCourseDates' => fn ($query) => $query
                ->select([
                    'id',
                    'teaching_course_id',
                    'date',
                    'hours',
                    'content',
                    'status',
                    'attendance_checked',
                ])
                ->orderBy('date')
                ->orderByRaw('JSON_EXTRACT(hours, "$[0]")'),
            'teachingCourseStudents' => fn ($query) => $query->select([
                'id',
                'teaching_course_id',
                'user_id',
                'import116_id',
                'canceled_at',
            ]),
            'teachingCourseStudents.import116:id,school_id,schoolyear_id,user_id',
        ])
            ->select([
                'id',
                'school_id',
                'schoolyear_id',
                'user_id',
                'title',
                'classes',
                'teaching_schema_id',
                'teaching_entry_area_id',
                'teaching_curriculum_id',
                'teaching_show_student_age',
                'teaching_show_student_last_login',
            ])
            ->where('school_id', $auth_user->school_id)
            ->where('schoolyear_id', $auth_user->schoolyear_id);

        if ($auth_user->hasRole('teacher') && ! $auth_user->hasAnyRole(['admin', 'teaching_admin'])) {
            $coursesQuery->where('user_id', $auth_user->id);
        }

        $courses = $coursesQuery
            ->orderBy('title')
            ->get();

        $authEntryAreas = $this->teachingEntryAreasForUser($auth_user, $auth_user->schoolyear_id, $auth_user->school_id);

        $classes = Import116::where('school_id', $auth_user->school_id)
            ->where('schoolyear_id', $auth_user->schoolyear_id)
            ->distinct()
            ->orderBy('class')
            ->pluck('class');

        return response()->json([
            'data' => CourseSummaryResource::collection($courses),
            'classes' => $classes,
            'class_head_emails' => $classHeadEmailService->listForUser($auth_user),
            'entry_areas' => $authEntryAreas,
            'uses_entry_areas_for_grading_schema' => $this->usesEntryAreasForSchoolyear($auth_user->schoolyear_id),
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

        Gate::authorize('view', $course);

        if ((int) $course_student->teaching_course_id !== (int) $course->id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ($course_student->canceled_at !== null) {
            abort(422, 'Diese:r Schüler:in ist im Kurs nicht aktiv.');
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

        Gate::authorize('view', $course);

        $validated = $request->validate([
            'course_student_id' => ['nullable', 'integer'],
        ]);

        $courseStudent = null;
        if (! empty($validated['course_student_id'])) {
            $courseStudent = TeachingCourseStudent::query()->findOrFail($validated['course_student_id']);

            if ((int) $courseStudent->teaching_course_id !== (int) $course->id) {
                abort(403, 'Sie haben keine Berechtigung');
            }

            if ($courseStudent->canceled_at !== null) {
                abort(422, 'Diese:r Schüler:in ist im Kurs nicht aktiv.');
            }
        } elseif (! $course->teachingCourseStudents()->whereNull('canceled_at')->exists()) {
            abort(422, 'Keine Schüler:innen für den Druck vorhanden.');
        }

        return $service->downloadCourse($course, $courseStudent);
    }

    public function courseOverviewPdf(
        TeachingCourse $course,
        PdfTableGenerator $pdfTableGenerator,
        TeachingHolidaySyncService $teachingHolidaySyncService,
    ): Responsable {
        if (! $authUser = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        Gate::authorize('view', $course);

        $schoolName = (string) $course->school()->value('long_name');
        $courseDates = $course->teachingCourseDates()
            ->select(['id', 'teaching_course_id', 'date', 'status'])
            ->whereNotNull('date')
            ->oldest('date')
            ->oldest('id')
            ->get();
        $courseStudents = TeachingCourseStudent::query()
            ->where('teaching_course_id', $course->id)
            ->whereNull('canceled_at')
            ->get();
        $userIds = $courseStudents->pluck('user_id')->filter()->unique()->values();
        $importIds = $courseStudents->pluck('import116_id')->filter()->unique()->values();
        $studentsById = User::query()
            ->select(['id', 'first_name', 'last_name', 'schoolclass', 'email'])
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');
        $importsById = Import116::query()
            ->select(['id', 'user_id', 'first_name', 'last_name', 'class', 'email'])
            ->where('school_id', $course->school_id)
            ->where('schoolyear_id', $course->schoolyear_id)
            ->whereIn('id', $importIds)
            ->get()
            ->keyBy('id');
        $studentRows = $courseStudents
            ->map(function (TeachingCourseStudent $courseStudent) use ($studentsById, $importsById): array {
                $source = $this->courseStudentPayloadSource($courseStudent, $studentsById, $importsById);

                if ($source === 'user') {
                    $student = $studentsById->get((int) $courseStudent->user_id);

                    return [
                        'last_name' => trim((string) $student?->last_name),
                        'first_name' => trim((string) $student?->first_name),
                        'class' => trim((string) $student?->schoolclass),
                    ];
                }

                $student = $importsById->get((int) $courseStudent->import116_id);

                return [
                    'last_name' => trim((string) $student?->last_name),
                    'first_name' => trim((string) $student?->first_name),
                    'class' => trim((string) $student?->class),
                ];
            })
            ->filter(fn (array $student): bool => $student['last_name'] !== '' || $student['first_name'] !== '')
            ->sortBy(
                fn (array $student): string => implode('|', [
                    $student['last_name'],
                    $student['first_name'],
                    $student['class'],
                ]),
                SORT_NATURAL | SORT_FLAG_CASE,
            )
            ->values()
            ->map(fn (array $student): array => [
                'student' => [
                    trim("{$student['last_name']} {$student['class']}"),
                    $student['first_name'],
                ],
            ])
            ->all();
        $studentColumn = [
            'key' => 'student',
            'label' => 'Schüler:in',
            'width' => '37mm',
            'font_size' => 8,
            'cell_padding' => ['top' => 1.2, 'right' => 2.8, 'bottom' => 1.2, 'left' => 0],
        ];
        $dateColumnGroups = collect();
        $currentDateColumns = collect();
        $currentDateWidth = 0.0;
        $availableDateWidth = 230.0;

        foreach ($courseDates as $courseDate) {
            $isFreeDay = in_array('free', is_array($courseDate->status) ? $courseDate->status : [], true);
            $columnWidth = $isFreeDay ? 10.0 : 23.0;

            if ($currentDateColumns->isNotEmpty() && $currentDateWidth + $columnWidth > $availableDateWidth) {
                $dateColumnGroups->push($currentDateColumns);
                $currentDateColumns = collect();
                $currentDateWidth = 0.0;
            }

            $column = [
                'key' => "course_date_{$courseDate->id}",
                'label' => $courseDate->date?->format('d.m.') ?? '',
                'width' => "{$columnWidth}mm",
                'align' => 'center',
                'cell_padding' => [
                    'top' => 1.2,
                    'right' => 1.2,
                    'bottom' => 1.2,
                    'left' => 1.2,
                ],
            ];

            if ($isFreeDay) {
                $freeReason = $teachingHolidaySyncService->resolveFreeReason(
                    (int) $course->school_id,
                    (int) $course->schoolyear_id,
                    (int) $course->user_id,
                    $courseDate->date?->format('Y-m-d'),
                );
                $column['row_span_value'] = $freeReason ?: 'Frei';
                $column['row_span_rotation'] = -90;
                $column['row_span_font_size'] = 6;
                $column['cell_background'] = '#d9f0df';
                $column['header_font_size'] = 6;
                $column['header_padding'] = [
                    'top' => 1.4,
                    'right' => 0.2,
                    'bottom' => 1.4,
                    'left' => 0.2,
                ];
                $column['cell_padding'] = [
                    'top' => 0,
                    'right' => 0,
                    'bottom' => 0,
                    'left' => 0,
                ];
            }

            $currentDateColumns->push($column);
            $currentDateWidth += $columnWidth;
        }

        if ($currentDateColumns->isNotEmpty() || $dateColumnGroups->isEmpty()) {
            $dateColumnGroups->push($currentDateColumns);
        }

        $studentRowGroups = collect($studentRows)->chunk(14);

        if ($studentRowGroups->isEmpty()) {
            $studentRowGroups->push(collect());
        }

        $tablePages = $dateColumnGroups
            ->flatMap(function (Collection $dateColumns, int $pageIndex) use ($availableDateWidth, $studentColumn, $studentRowGroups): array {
                $dateColumns = $dateColumns->values();
                $usedDateWidth = $dateColumns->sum(
                    fn (array $column): float => (float) Str::before($column['width'], 'mm'),
                );
                $blankColumnNumber = 1;

                while ($usedDateWidth + 23.0 <= $availableDateWidth) {
                    $dateColumns->push([
                        'key' => "course_date_blank_{$pageIndex}_{$blankColumnNumber}",
                        'label' => '',
                        'width' => '23mm',
                        'align' => 'center',
                        'cell_padding' => [
                            'top' => 1.2,
                            'right' => 1.2,
                            'bottom' => 1.2,
                            'left' => 1.2,
                        ],
                    ]);
                    $usedDateWidth += 23.0;
                    $blankColumnNumber++;
                }

                $remainingDateWidth = $availableDateWidth - $usedDateWidth;

                if ($remainingDateWidth > 0.001) {
                    $dateColumns->push([
                        'key' => "course_date_spacer_{$pageIndex}",
                        'label' => '',
                        'width' => number_format($remainingDateWidth, 4, '.', '').'mm',
                        'is_spacer' => true,
                        'cell_padding' => [
                            'top' => 0,
                            'right' => 0,
                            'bottom' => 0,
                            'left' => 0,
                        ],
                    ]);
                }

                if ($dateColumns->isNotEmpty()) {
                    $lastColumnIndex = $dateColumns->count() - 1;
                    $lastColumn = $dateColumns->get($lastColumnIndex);
                    $lastColumn['cell_padding']['right'] = 0;
                    $dateColumns->put($lastColumnIndex, $lastColumn);
                }

                $emptyDateCells = $dateColumns
                    ->mapWithKeys(fn (array $column): array => [$column['key'] => ''])
                    ->all();

                return $studentRowGroups
                    ->map(function (Collection $studentRowGroup) use ($dateColumns, $studentColumn, $emptyDateCells): array {
                        $pageDateColumns = $dateColumns->map(function (array $column) use ($studentRowGroup): array {
                            if (! isset($column['row_span_value'])) {
                                return $column;
                            }

                            $reason = (string) $column['row_span_value'];
                            $fontSize = (float) ($column['row_span_font_size'] ?? 6);
                            $estimatedTextHeight = mb_strlen($reason) * $fontSize * 0.19;
                            $availableCellHeight = max(1, $studentRowGroup->count()) * 10.5;

                            if ($estimatedTextHeight > $availableCellHeight) {
                                $column['row_span_value'] = 'Frei';
                            }

                            return $column;
                        });

                        return [
                            'columns' => [$studentColumn, ...$pageDateColumns->all()],
                            'rows' => $studentRowGroup
                                ->map(fn (array $studentRow): array => [...$studentRow, ...$emptyDateCells])
                                ->values()
                                ->all(),
                        ];
                    })
                    ->all();
            })
            ->all();

        return $pdfTableGenerator->generate([
            'title' => "Übersicht Unterricht . {$course->title}",
            'subtitle' => $schoolName,
            'user_name' => $authUser->full_name,
            'print_date_time' => now()->format('d.m.Y · H:i'),
            'orientation' => 'landscape',
            'table_pages' => $tablePages,
            'empty_message' => 'Keine Schüler:innen im Kurs.',
            'title_page' => [
                'label' => $schoolName,
                'title' => $course->title,
                'subtitle' => 'Übersicht Unterricht',
            ],
        ])->name(Str::slug($course->title).'-kursuebersicht.pdf');
    }

    public function courseGradesPdf(
        Request $request,
        TeachingCourse $course,
        TeachingStudentPerformancePdfService $service
    ): Responsable {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        Gate::authorize('view', $course);

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

        if (! $course->teachingCourseStudents()->whereNull('canceled_at')->exists()) {
            abort(422, 'Keine Schüler:innen für den Druck vorhanden.');
        }

        return $service->downloadGrades($course, $semesters);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(
        Request $request,
        TeachingCourseService $service,
        TeachingCourseWorkEntrySyncService $entrySyncService,
        TeachingClassHeadEmailService $classHeadEmailService
    ) {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $classes = Import116::where('school_id', $auth_user->school_id)
            ->where('schoolyear_id', $auth_user->schoolyear_id)
            ->distinct()
            ->orderBy('class')
            ->pluck('class');
        $usesEntryAreasForGradingSchema = $this->usesEntryAreasForSchoolyear($auth_user->schoolyear_id);
        $teachingService = new TeachingService;

        if ($usesEntryAreasForGradingSchema) {
            $teachingService->ensureDefaultSchema($auth_user, $auth_user->schoolyear_id);
        }

        $schemaIds = $teachingService->schemaIdsForUser($auth_user, $auth_user->schoolyear_id);
        $entryAreaRule = $this->entryAreaRuleForUser($auth_user, $auth_user->schoolyear_id);
        $curriculumRule = Rule::exists(TeachingCurriculum::query()->getModel()->getTable(), 'id')
            ->where(fn (Builder $query) => $query
                ->where('school_id', $auth_user->school_id)
                ->where('user_id', $auth_user->id));
        $selectedClasses = array_values(array_filter(
            (array) $request->input('classes', []),
            fn (mixed $className): bool => is_string($className)
        ));

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'classes' => 'required|array|min:1',
            'classes.*' => ['required', 'string', Rule::in($classes)],
            'class_head_emails' => ['sometimes', 'array'],
            'class_head_emails.*.class_name' => ['required', 'string', 'max:255', 'distinct:strict', Rule::in($selectedClasses)],
            'class_head_emails.*.email_1' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'class_head_emails.*.email_2' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'students' => 'nullable|array',
            'students.*.import116_id' => ['nullable', 'integer', $this->import116RuleForSchoolyear((int) $auth_user->school_id, $auth_user->schoolyear_id)],
            'students.*.stars' => 'nullable|array',
            'students.*.stars.*.id' => 'nullable|string|max:64',
            'students.*.stars.*.value' => 'nullable|integer|in:1',
            'students.*.stars.*.comment' => 'nullable|string|max:1024',
            'students.*.stars.*.date' => 'nullable|date',
            'students.*.canceled_at' => 'nullable|date',
            'students_info' => 'nullable|array',
            'students_info.*.import116_id' => ['nullable', 'integer', $this->import116RuleForSchoolyear((int) $auth_user->school_id, $auth_user->schoolyear_id)],
            'students_info.*.canceled_at' => 'nullable|date',
            'students_deleted' => 'nullable|array',
            'students_deleted.*.import116_id' => ['nullable', 'integer', $this->import116RuleForSchoolyear((int) $auth_user->school_id, $auth_user->schoolyear_id)],
            'students_deleted_info' => 'nullable|array',
            'students_deleted_info.*.import116_id' => ['nullable', 'integer', $this->import116RuleForSchoolyear((int) $auth_user->school_id, $auth_user->schoolyear_id)],
            'students_deleted_info.*.canceled_at' => 'nullable|date',
            'teaching_schema_id' => [Rule::excludeIf($usesEntryAreasForGradingSchema), 'required', 'string', 'max:36', Rule::in($schemaIds)],
            'teaching_entry_area_id' => [Rule::excludeIf(! $usesEntryAreasForGradingSchema), 'required', 'integer', $entryAreaRule],
            'teaching_curriculum_id' => ['nullable', 'integer', $curriculumRule],
            'teaching_show_student_age' => ['sometimes', 'boolean'],
            'teaching_show_student_last_login' => ['sometimes', 'boolean'],
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

        $course = DB::transaction(function () use (
            $auth_user,
            $validated,
            $sortedClasses,
            $schemaIds,
            $service,
            $studentsPayload,
            $studentsDeletedPayload,
            $entrySyncService,
            $classHeadEmailService
        ): TeachingCourse {
            $course = TeachingCourse::create([
                'school_id' => $auth_user->school_id,
                'schoolyear_id' => $auth_user->schoolyear_id,
                'user_id' => $auth_user->id,
                'title' => $validated['title'],
                'classes' => $sortedClasses,
                'teaching_schema_id' => $validated['teaching_schema_id'] ?? $schemaIds->first(),
                'teaching_entry_area_id' => $validated['teaching_entry_area_id'] ?? null,
                'teaching_curriculum_id' => $validated['teaching_curriculum_id'] ?? null,
                'teaching_show_student_age' => $validated['teaching_show_student_age'] ?? false,
                'teaching_show_student_last_login' => $validated['teaching_show_student_last_login'] ?? false,
            ]);

            if (array_key_exists('class_head_emails', $validated)) {
                $classHeadEmailService->syncForUser($auth_user, $validated['class_head_emails']);
            }

            $service->syncCourseStudents($course, $studentsPayload, $studentsDeletedPayload);
            $entrySyncService->syncNonGroupWorksForCourse($course);

            return $course;
        });

        return response()->json(new CourseResource($course), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(
        Request $request,
        TeachingCourse $course,
        TeachingCourseService $service
    ): CourseResource {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        Gate::authorize('view', $course);

        $course->load([
            'user:id,first_name,last_name,short,email,teaching_behaviour_by_schoolyear,teaching_notifications_by_schoolyear,teaching_show_behaviour,teaching_student_grade_columns_by_schoolyear',
            'teachingCurriculum:id,school_id,schoolyear_id,user_id,title,description,semester_count',
            'teachingEntryArea:id,name',
            'teachingEntryArea.entryDefinitions' => fn ($query) => $query
                ->orderBy('category')
                ->orderBy('short_name'),
            'teachingCourseDates' => fn ($query) => $query
                ->orderBy('date')
                ->orderByRaw('JSON_EXTRACT(hours, "$[0]")'),
            'teachingCourseDates.materials.attachments',
            'teachingCourseStudents',
            'teachingCourseStudentsWithTrashed',
        ]);

        $course->teachingCourseDates->each(
            fn (TeachingCourseDate $courseDate) => $courseDate->setRelation('teachingCourse', $course)
        );

        $courseStudents = $course->teachingCourseStudentsWithTrashed;
        $userIds = $courseStudents
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();
        $importIds = $courseStudents
            ->pluck('import116_id')
            ->filter()
            ->unique()
            ->values();

        $studentsById = $userIds->isEmpty()
            ? collect()
            : User::whereIn('id', $userIds)->get()->keyBy('id');
        $importsById = $importIds->isEmpty()
            ? collect()
            : Import116::query()
                ->where('school_id', $auth_user->school_id)
                ->where('schoolyear_id', $auth_user->schoolyear_id)
                ->whereIn('id', $importIds)
                ->get()
                ->keyBy('id');

        $removalReasons = $service->removalReasonsForCourses(collect([$course]))[(int) $course->id] ?? [];
        $courseActor = $this->teachingCourseActor($auth_user, $course);
        $teachingService = new TeachingService;
        $schemaCache = [];
        $entryAreaCache = [];

        $activeStudents = [];
        foreach ($course->teachingCourseStudents as $courseStudent) {
            $payload = $this->serializeCourseStudent(
                $courseStudent,
                $studentsById,
                $importsById,
                $request,
                $removalReasons,
                (bool) $course->teaching_show_student_age,
                (bool) $course->teaching_show_student_last_login,
            );

            if ($payload) {
                $activeStudents[] = $payload;
            }
        }

        $deletedStudents = [];
        foreach ($course->teachingCourseStudentsWithTrashed as $courseStudent) {
            if (! $courseStudent->trashed()) {
                continue;
            }

            $payload = $this->serializeCourseStudent(
                $courseStudent,
                $studentsById,
                $importsById,
                $request,
                $removalReasons,
                (bool) $course->teaching_show_student_age,
                (bool) $course->teaching_show_student_last_login,
            );

            if ($payload) {
                $deletedStudents[] = $payload;
            }
        }

        $course->setAttribute('students', $activeStudents);
        $course->setAttribute('students_deleted', $deletedStudents);
        $course->setAttribute(
            'teacher_teaching_schema',
            $this->teachingSchemaForCourse($courseActor, $course, $teachingService, $schemaCache)
        );
        $course->setAttribute(
            'teacher_teaching_entry_areas',
            $this->teachingEntryAreasForCourse($courseActor, $course, $entryAreaCache)
        );
        $course->setAttribute(
            'teacher_teaching_behaviour',
            $this->teachingBehaviourForSchoolyear($courseActor, $course->schoolyear_id)
        );
        $course->setAttribute(
            'teacher_teaching_notifications',
            $this->teachingNotificationsForSchoolyear($courseActor, $course->schoolyear_id)
        );
        $course->setAttribute(
            'teacher_teaching_show_behaviour',
            (bool) ($courseActor->teaching_show_behaviour ?? true)
        );
        $course->setAttribute(
            'teaching_student_grade_columns',
            $this->teachingStudentGradeColumnsForCourse($course, $courseActor)
        );
        $course->setAttribute('details_loaded', true);

        return new CourseResource($course);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        Request $request,
        TeachingCourse $course,
        TeachingCourseService $service,
        TeachingCourseWorkEntrySyncService $entrySyncService,
        TeachingClassHeadEmailService $classHeadEmailService
    ) {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        Gate::authorize('update', $course);

        $classes = Import116::where('school_id', $auth_user->school_id)
            ->where('schoolyear_id', $course->schoolyear_id)
            ->distinct()
            ->orderBy('class')
            ->pluck('class');
        $courseActor = $this->teachingCourseActor($auth_user, $course);
        $usesEntryAreasForGradingSchema = $this->usesEntryAreasForSchoolyear($course->schoolyear_id);
        $teachingService = new TeachingService;

        if ($usesEntryAreasForGradingSchema) {
            $teachingService->ensureDefaultSchema($courseActor, $course->schoolyear_id);
        }

        $schemaIds = $teachingService->schemaIdsForUser($courseActor, $course->schoolyear_id);
        $entryAreaRule = $this->entryAreaRuleForUser($courseActor, $course->schoolyear_id);
        $curriculumRule = Rule::exists(TeachingCurriculum::query()->getModel()->getTable(), 'id')
            ->where(fn (Builder $query) => $query
                ->where('school_id', $course->school_id)
                ->where('user_id', $courseActor->id));
        $selectedClasses = array_values(array_filter(
            (array) $request->input('classes', []),
            fn (mixed $className): bool => is_string($className)
        ));

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:4096',
            'classes' => 'required|array|min:1',
            'classes.*' => ['required', 'string', Rule::in($classes)],
            'class_head_emails' => ['sometimes', 'array'],
            'class_head_emails.*.class_name' => ['required', 'string', 'max:255', 'distinct:strict', Rule::in($selectedClasses)],
            'class_head_emails.*.email_1' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'class_head_emails.*.email_2' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'students' => 'nullable|array',
            'students.*.import116_id' => ['nullable', 'integer', $this->import116RuleForSchoolyear((int) $course->school_id, $course->schoolyear_id)],
            'students.*.stars' => 'nullable|array',
            'students.*.stars.*.id' => 'nullable|string|max:64',
            'students.*.stars.*.value' => 'nullable|integer|in:1',
            'students.*.stars.*.comment' => 'nullable|string|max:1024',
            'students.*.stars.*.date' => 'nullable|date',
            'students.*.canceled_at' => 'nullable|date',
            'students_info' => 'nullable|array',
            'students_info.*.import116_id' => ['nullable', 'integer', $this->import116RuleForSchoolyear((int) $course->school_id, $course->schoolyear_id)],
            'students_info.*.canceled_at' => 'nullable|date',
            'students_deleted' => 'nullable|array',
            'students_deleted.*.import116_id' => ['nullable', 'integer', $this->import116RuleForSchoolyear((int) $course->school_id, $course->schoolyear_id)],
            'students_deleted_info' => 'nullable|array',
            'students_deleted_info.*.import116_id' => ['nullable', 'integer', $this->import116RuleForSchoolyear((int) $course->school_id, $course->schoolyear_id)],
            'students_deleted_info.*.canceled_at' => 'nullable|date',
            'teaching_schema_id' => [Rule::excludeIf($usesEntryAreasForGradingSchema), 'required', 'string', 'max:36', Rule::in($schemaIds)],
            'teaching_entry_area_id' => [Rule::excludeIf(! $usesEntryAreasForGradingSchema), 'required', 'integer', $entryAreaRule],
            'teaching_curriculum_id' => ['nullable', 'integer', $curriculumRule],
            'teaching_show_student_age' => ['sometimes', 'boolean'],
            'teaching_show_student_last_login' => ['sometimes', 'boolean'],
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

        DB::transaction(function () use (
            $course,
            $validated,
            $sortedClasses,
            $schemaIds,
            $auth_user,
            $classHeadEmailService,
            $service,
            $studentsPayload,
            $studentsDeletedPayload,
            $entrySyncService
        ): void {
            $course->update([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'classes' => $sortedClasses,
                'teaching_schema_id' => $validated['teaching_schema_id'] ?? $course->teaching_schema_id ?? $schemaIds->first(),
                'teaching_entry_area_id' => array_key_exists('teaching_entry_area_id', $validated)
                    ? $validated['teaching_entry_area_id']
                    : $course->teaching_entry_area_id,
                'teaching_curriculum_id' => $validated['teaching_curriculum_id'] ?? null,
                'teaching_show_student_age' => $validated['teaching_show_student_age'] ?? $course->teaching_show_student_age,
                'teaching_show_student_last_login' => $validated['teaching_show_student_last_login'] ?? $course->teaching_show_student_last_login,
            ]);

            if (array_key_exists('class_head_emails', $validated)) {
                $classHeadEmailService->syncForUser($auth_user, $validated['class_head_emails']);
            }

            $service->syncCourseStudents($course, $studentsPayload, $studentsDeletedPayload);
            $entrySyncService->syncNonGroupWorksForCourse($course);
        });

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

        Gate::authorize('delete', $course);

        DB::transaction(function () use ($course): void {
            $lockedCourse = TeachingCourse::query()
                ->lockForUpdate()
                ->findOrFail($course->getKey());

            if ($lockedCourse->hasDependencies()) {
                abort(409, 'Der Kurs hat noch Abhängigkeiten und kann nicht gelöscht werden');
            }

            $lockedCourse->teachingCourseStudents()->onlyTrashed()->forceDelete();
            $lockedCourse->delete();
        });

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
     * @param  array<string, array<int, array{id: int, name: string}>>  $entryAreaCache
     * @return array<int, array{id: int, name: string}>
     */
    private function teachingEntryAreasForCourse(User $courseActor, TeachingCourse $course, array &$entryAreaCache): array
    {
        $cacheKey = implode(':', [
            (string) $courseActor->id,
            (string) $course->school_id,
            (string) $course->schoolyear_id,
        ]);

        if (! array_key_exists($cacheKey, $entryAreaCache)) {
            $entryAreaCache[$cacheKey] = $this->teachingEntryAreasForUser($courseActor, $course->schoolyear_id, $course->school_id);
        }

        return $entryAreaCache[$cacheKey];
    }

    /** @return array<int, array{id: int, name: string}> */
    private function teachingEntryAreasForUser(User $user, ?int $schoolyearId, ?int $schoolId): array
    {
        if (! $schoolyearId || ! $schoolId) {
            return [];
        }

        return TeachingEntryArea::query()
            ->whereBelongsTo($user)
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (TeachingEntryArea $entryArea): array => [
                'id' => (int) $entryArea->id,
                'name' => $entryArea->name,
            ])
            ->all();
    }

    private function entryAreaRuleForUser(User $user, ?int $schoolyearId): Exists
    {
        return Rule::exists((new TeachingEntryArea)->getTable(), 'id')
            ->where(fn (Builder $query) => $query
                ->where('school_id', $user->school_id)
                ->where('schoolyear_id', $schoolyearId)
                ->where('user_id', $user->id));
    }

    private function import116RuleForSchoolyear(int $schoolId, ?int $schoolyearId): Exists
    {
        return Rule::exists((new Import116)->getTable(), 'id')
            ->where(fn (Builder $query) => $query
                ->where('school_id', $schoolId)
                ->where('schoolyear_id', $schoolyearId));
    }

    private function usesEntryAreasForSchoolyear(?int $schoolyearId): bool
    {
        if (! $schoolyearId) {
            return false;
        }

        $schoolyear = Schoolyear::query()->find($schoolyearId, ['concerns', 'name', 'from']);

        if (! $schoolyear) {
            return false;
        }

        foreach ([$schoolyear->concerns, $schoolyear->name, $schoolyear->from] as $schoolyearLabel) {
            if (preg_match('/(?:19|20)\d{2}/', (string) $schoolyearLabel, $matches) === 1) {
                return (int) $matches[0] >= 2026;
            }
        }

        return false;
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
        array $removalReasons = [],
        bool $showStudentAge = false,
        bool $showStudentLastLogin = false,
    ): ?array {
        if ($courseStudent->import116_id && ! $importsById->has((int) $courseStudent->import116_id)) {
            return null;
        }

        $source = $this->courseStudentPayloadSource($courseStudent, $studentsById, $importsById);
        $import = $courseStudent->import116_id
            ? $importsById->get((int) $courseStudent->import116_id)
            : null;

        if ($source === 'user') {
            $student = $studentsById->get((int) $courseStudent->user_id);
            if (! $student) {
                return null;
            }

            $payload = (new StudentResource($student))->toArray($request);
        } else {
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

        $payload['sex'] = $payload['sex'] ?? $import?->sex;

        if ($showStudentAge) {
            $payload['birth_date'] = $import?->birth_date?->format('Y-m-d');
            $payload['age'] = $import?->birth_date?->age;
        }

        if (! $showStudentLastLogin) {
            unset($payload['login_at']);
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
        if ((int) ($import->user_id ?? 0) === (int) $student->id) {
            return true;
        }

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
