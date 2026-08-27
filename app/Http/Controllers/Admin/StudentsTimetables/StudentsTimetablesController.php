<?php

namespace App\Http\Controllers\Admin\StudentsTimetables;

use App\Enums\StudentTimetableStudyProgram;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Teaching\SchoolHourResource;
use App\Models\Import116;
use App\Models\StudentTimetablePublishedTimetable;
use App\Models\StudentTimetableSubjectRow;
use App\Models\User;
use App\Services\SchoolHourService;
use App\Services\SchoolyearService;
use App\Services\StudentsTimetables\RobotTimetableBackendSetupService;
use App\Services\StudentsTimetables\RobotTimetableGeneratorService;
use App\Services\StudentsTimetables\StudentsTimetablesService;
use App\Services\StudentsTimetables\StudentTimetableCalculationSettingsService;
use App\Services\StudentsTimetables\StudentTimetableCompletedCourseHistoryService;
use App\Services\StudentsTimetables\StudentTimetableEvaluationSettingsService;
use App\Services\StudentsTimetables\StudentTimetableExpectedModulesService;
use App\Services\StudentsTimetables\StudentTimetableOverviewService;
use App\Services\StudentsTimetables\StudentTimetablePublishedTimetableService;
use App\Services\StudentsTimetables\StudentTimetableRememberedTtEntryService;
use App\Services\StudentsTimetables\StudentTimetablesStudentOverviewService;
use App\Services\StudentsTimetables\StudentTimetableV2StateService;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\LaravelPdf\Enums\Format;

use function Spatie\LaravelPdf\Support\pdf;

class StudentsTimetablesController extends Controller
{
    private const ADMIN_ROLES = ['super_admin', 'admin', 'studentstimetables_admin'];

    private const MODERATOR_ROLES = ['super_admin', 'admin', 'studentstimetables_admin', 'studentstimetables_moderator'];

    public function index(StudentsTimetablesService $service): JsonResponse
    {
        $authUser = $this->studentsTimetablesUser();

        return response()->json([
            'data' => $service->dashboardForUser($authUser),
        ]);
    }

    public function schoolHours(SchoolHourService $service): JsonResponse
    {
        $authUser = $this->studentsTimetablesUser();

        return response()->json([
            'data' => SchoolHourResource::collection($service->listForUser($authUser)),
        ]);
    }

    public function courseGroups(
        StudentTimetableOverviewService $service,
        StudentTimetableRememberedTtEntryService $rememberedTtEntryService,
    ): JsonResponse {
        $authUser = $this->studentsTimetablesUser();

        return response()->json([
            'data' => $rememberedTtEntryService->courseGroupsWithInactiveDatesForUser(
                $authUser,
                $service->courseGroupsForUser($authUser),
            ),
        ]);
    }

    public function robotStudents(
        StudentTimetablesStudentOverviewService $studentOverviewService,
        StudentTimetableExpectedModulesService $expectedModulesService,
    ): JsonResponse {
        $authUser = $this->studentsTimetablesUser();

        $students = Import116::query()
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->whereNotNull('exists_date')
            ->orderBy('class')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'class', 'school_level', 'attendance_year', 'religion', 'student_code', 'last_name', 'first_name', 'email', 'sex', 'study_selection', 'course_results']);

        $publishedTimetables = StudentTimetablePublishedTimetable::query()
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->whereIn('student_code', $students->pluck('student_code')->filter()->values())
            ->get(['id', 'student_code', 'published_at'])
            ->keyBy(fn (StudentTimetablePublishedTimetable $publishedTimetable): string => (string) $publishedTimetable->student_code);
        $students = $students
            ->map(function (Import116 $student) use ($authUser, $expectedModulesService, $publishedTimetables, $studentOverviewService): array {
                $instructionType = $studentOverviewService->instructionTypeForStudent($student);
                $studyProgram = $instructionType === 'Kompaktunterricht'
                    ? StudentTimetableStudyProgram::Kompaktstudium
                    : StudentTimetableStudyProgram::Normalstudium;
                $semester = $studentOverviewService->semesterForStudent($student, $studyProgram);
                $studySelection = $this->storedStudySelection($student);
                $courseResults = $this->storedCourseResults($student);

                return [
                    'id' => (int) $student->id,
                    'class' => (string) $student->class,
                    'school_level' => $student->school_level,
                    'attendance_year' => $student->attendance_year,
                    'religion' => $student->religion,
                    'student_code' => (string) $student->student_code,
                    'last_name' => (string) $student->last_name,
                    'first_name' => (string) $student->first_name,
                    'email' => (string) $student->email,
                    'sex' => (string) $student->sex,
                    'study_program' => $studyProgram->value,
                    'data_quality_issues' => $studentOverviewService->dataQualityIssuesForStudent(
                        $student,
                        $studyProgram,
                    ),
                    'study_selection' => $studySelection,
                    'course_results' => $courseResults,
                    'expected_modules' => $expectedModulesService->forStudent(
                        $authUser,
                        $studyProgram,
                        $semester,
                        [
                            ...$studySelection,
                            'student_religion' => $this->emptyStringToNull($student->religion),
                        ],
                        $courseResults,
                    ),
                    'expected_additional_modules' => $expectedModulesService->additionalForStudent(
                        $authUser,
                        $studyProgram,
                        [
                            ...$studySelection,
                            'student_religion' => $this->emptyStringToNull($student->religion),
                        ],
                        $courseResults,
                    ),
                    'instruction_type' => $instructionType,
                    'semester' => $semester,
                    'title' => trim("{$student->class} · {$student->last_name} {$student->first_name}"),
                    'has_published_timetable' => $publishedTimetables->has((string) $student->student_code),
                    'published_timetable_id' => $publishedTimetables->get((string) $student->student_code)?->id,
                    'published_timetable_at' => optional($publishedTimetables->get((string) $student->student_code)?->published_at)->toIso8601String(),
                ];
            })
            ->values();

        return response()->json([
            'data' => $students,
        ]);
    }

    public function robotStudentCompletedCourses(
        Request $request,
        StudentTimetableCompletedCourseHistoryService $completedCourseHistoryService,
    ): JsonResponse {
        $authUser = $this->studentsTimetablesUser();

        $validated = $request->validate([
            'student_code' => ['required', 'string', 'max:255'],
        ]);

        $courses = collect($completedCourseHistoryService->coursesForStudentCode(
            $authUser,
            (int) $authUser->schoolyear_id,
            (string) $validated['student_code'],
        ))
            ->map(fn (array $course): array => [
                'subject' => (string) ($course['subject'] ?? $course['code'] ?? ''),
                'grade' => (string) ($course['grade'] ?? ''),
            ])
            ->values();

        return response()->json([
            'data' => $courses,
            'total' => $courses->count(),
        ]);
    }

    public function robotStudentOverview(Request $request, StudentTimetablesStudentOverviewService $service): JsonResponse
    {
        $authUser = $this->studentsTimetablesUser();

        $validated = $request->validate([
            'student_code' => ['required', 'string', 'max:255'],
            'selection' => ['sometimes', 'array'],
            'selection.semester' => ['nullable', 'integer', 'between:1,20'],
            'selection.religion' => ['nullable', 'string', 'max:20'],
            'selection.branch' => ['nullable', 'string', 'max:80'],
            'selection.artsSubject' => ['nullable', 'string', 'max:20'],
            'selection.arts_subject' => ['nullable', 'string', 'max:20'],
            'selection.language' => ['nullable', 'string', 'max:20'],
            'strict_selection' => ['sometimes', 'boolean'],
            'payload' => ['sometimes', 'string', Rule::in(['full', 'course_history'])],
        ]);

        $payload = (string) ($validated['payload'] ?? 'full');
        $data = $payload === 'course_history'
            ? $service->courseHistoryForStudentCode(
                $authUser,
                (string) $validated['student_code'],
                $validated['selection'] ?? [],
                (bool) ($validated['strict_selection'] ?? false),
            )
            : $service->summaryForStudentCode(
                $authUser,
                (string) $validated['student_code'],
                $validated['selection'] ?? [],
                (bool) ($validated['strict_selection'] ?? false),
            );

        return response()->json([
            'data' => $data,
        ]);
    }

    public function timetableV2SelectionBootstrap(
        Request $request,
        StudentTimetableV2StateService $stateService,
        StudentTimetableOverviewService $overviewService,
        StudentTimetableRememberedTtEntryService $rememberedTtEntryService,
        StudentTimetablesStudentOverviewService $studentOverviewService,
    ): JsonResponse {
        $authUser = $this->studentsTimetablesUser();

        $validated = $request->validate([
            'student_code' => ['nullable', 'string', 'max:255'],
            'selection' => ['sometimes', 'array'],
            'selection.semester' => ['nullable', 'integer', 'between:1,20'],
            'selection.religion' => ['nullable', 'string', 'max:20'],
            'selection.branch' => ['nullable', 'string', 'max:80'],
            'selection.artsSubject' => ['nullable', 'string', 'max:20'],
            'selection.arts_subject' => ['nullable', 'string', 'max:20'],
            'selection.language' => ['nullable', 'string', 'max:20'],
            'strict_selection' => ['sometimes', 'boolean'],
        ]);

        $state = $stateService->stateForUser($authUser);
        $studentCode = trim((string) ($validated['student_code'] ?? data_get($state, 'transferredStudentContext.student.studentCode', '')));
        $selection = $validated['selection'] ?? data_get($state, 'timetableV2Selection', []);
        $selection = is_array($selection) ? $selection : [];
        $data = [
            'state' => $state,
            'course_groups' => $rememberedTtEntryService->courseGroupsWithInactiveDatesForUser(
                $authUser,
                $overviewService->courseGroupsForUser($authUser),
            ),
            'subjects' => $this->timetableV2SubjectRows($authUser),
        ];

        if ($studentCode !== '') {
            $data['student_overview'] = $studentOverviewService->courseHistoryForStudentCode(
                $authUser,
                $studentCode,
                $selection,
                (bool) ($validated['strict_selection'] ?? $selection !== []),
            );
        }

        return response()->json([
            'data' => $data,
        ]);
    }

    public function publishedStudentTimetable(Request $request): JsonResponse
    {
        $authUser = $this->studentsTimetablesUser();

        $validated = $request->validate([
            'student_code' => ['required', 'string', 'max:255'],
        ]);

        $student = Import116::query()
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->where('student_code', $validated['student_code'])
            ->whereNotNull('exists_date')
            ->first();

        if (! $student) {
            abort(422, 'Der ausgewählte Schüler wurde nicht gefunden.');
        }

        $publishedTimetable = StudentTimetablePublishedTimetable::query()
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->where('student_code', $validated['student_code'])
            ->first();

        if (! $publishedTimetable) {
            abort(404, 'Für diesen Schüler ist kein gespeicherter Stundenplan vorhanden.');
        }

        return response()->json([
            'data' => [
                'id' => $publishedTimetable->id,
                'student_code' => $publishedTimetable->student_code,
                'student_label' => $publishedTimetable->student_label,
                'name' => $publishedTimetable->name,
                'timetable' => is_array($publishedTimetable->timetable) ? $publishedTimetable->timetable : [],
                'state' => is_array($publishedTimetable->state) ? $publishedTimetable->state : [],
                'published_at' => optional($publishedTimetable->published_at)->toIso8601String(),
            ],
        ]);
    }

    public function robotFullGreenCount(
        Request $request,
        RobotTimetableGeneratorService $generatorService,
        StudentTimetableOverviewService $overviewService,
        StudentTimetableEvaluationSettingsService $evaluationSettingsService,
        StudentTimetableCalculationSettingsService $calculationSettingsService,
    ): JsonResponse {
        $authUser = $this->studentsTimetablesUser();

        $validated = $request->validate($calculationSettingsService->qualityCounterRules($evaluationSettingsService));

        $evaluationCriteria = array_key_exists('evaluation_criteria', $validated)
            ? $evaluationSettingsService->activeCriteriaForRun($validated['evaluation_criteria'])
            : $evaluationSettingsService->activeCriteriaForUser($authUser);

        return response()->json([
            'data' => $generatorService->countFullGreenTimetablesForUser(
                $authUser,
                $validated,
                $overviewService,
                $evaluationCriteria,
                $validated['selected_timetable_type'] ?? null,
                (int) ($validated['selected_timetable_number'] ?? 1),
                $request->boolean('selected_additional_courses_required'),
            ),
        ]);
    }

    public function robotBackendTimetable(
        Request $request,
        RobotTimetableBackendSetupService $backendSetupService,
        StudentTimetableOverviewService $overviewService,
        StudentTimetableEvaluationSettingsService $evaluationSettingsService,
        StudentTimetableCalculationSettingsService $calculationSettingsService,
    ): JsonResponse {
        $authUser = $this->studentsTimetablesUser();

        $validated = $request->validate($calculationSettingsService->backendTimetableRules(
            $evaluationSettingsService,
            includeQualityCounterFlags: true,
        ));

        $usesQualityCriteria = ($request->boolean('include_quality_counters') || $request->boolean('selected_quality_criteria_required'))
            && array_key_exists('evaluation_criteria', $validated);

        $evaluationCriteria = $usesQualityCriteria
            ? $evaluationSettingsService->activeCriteriaForRun($validated['evaluation_criteria'])
            : [];

        return response()->json([
            'data' => $backendSetupService->createInitialBackendTimetable(
                $authUser,
                $validated,
                $overviewService,
                $evaluationCriteria,
                $validated['selected_quality_criterion_keys'] ?? [],
            ),
        ]);
    }

    public function robotBackendTimetableAvailability(
        Request $request,
        RobotTimetableBackendSetupService $backendSetupService,
        StudentTimetableOverviewService $overviewService,
        StudentTimetableEvaluationSettingsService $evaluationSettingsService,
        StudentTimetableCalculationSettingsService $calculationSettingsService,
    ): JsonResponse {
        $authUser = $this->studentsTimetablesUser();

        $validated = $request->validate([
            ...$calculationSettingsService->backendTimetableRules(
                $evaluationSettingsService,
                includeQualityCounterFlags: true,
            ),
            'availability_only' => ['sometimes', 'boolean'],
            'candidate_courses' => ['required', 'array', 'min:1', 'max:500'],
            'candidate_courses.*.availability_key' => ['required', 'string', 'max:255', 'distinct:strict'],
            'candidate_courses.*.course_key' => ['required', 'string', 'max:255'],
            'candidate_courses.*.course_group' => ['required', 'string', 'in:missing,planned,additional'],
            'candidate_courses.*.deselected_course_group_keys' => ['sometimes', 'array', 'max:100'],
            'candidate_courses.*.deselected_course_group_keys.*' => ['string', 'max:255'],
        ]);

        $usesQualityCriteria = $request->boolean('selected_quality_criteria_required')
            && array_key_exists('evaluation_criteria', $validated);

        $evaluationCriteria = $usesQualityCriteria
            ? $evaluationSettingsService->activeCriteriaForRun($validated['evaluation_criteria'])
            : [];

        return response()->json([
            'data' => [
                'availability' => $backendSetupService->courseAvailabilityForUser(
                    $authUser,
                    $validated,
                    $validated['candidate_courses'],
                    $overviewService,
                    $evaluationCriteria,
                    $validated['selected_quality_criterion_keys'] ?? [],
                    ($validated['availability_only'] ?? false) === true,
                ),
            ],
        ]);
    }

    public function robotQualityCounters(
        Request $request,
        RobotTimetableBackendSetupService $backendSetupService,
        StudentTimetableOverviewService $overviewService,
        StudentTimetableEvaluationSettingsService $evaluationSettingsService,
        StudentTimetableCalculationSettingsService $calculationSettingsService,
    ): JsonResponse {
        $authUser = $this->studentsTimetablesUser();

        $validated = $request->validate($calculationSettingsService->qualityCounterRules($evaluationSettingsService));

        $evaluationCriteria = array_key_exists('evaluation_criteria', $validated)
            ? $evaluationSettingsService->activeCriteriaForRun($validated['evaluation_criteria'])
            : $evaluationSettingsService->activeCriteriaForUser($authUser);

        return response()->json([
            'data' => $backendSetupService->qualityCountersForUser(
                $authUser,
                $validated,
                $overviewService,
                $evaluationCriteria,
                $validated['selected_quality_criterion_keys'] ?? [],
            ),
        ]);
    }

    public function overviewSelections(StudentTimetableOverviewService $service): JsonResponse
    {
        $authUser = $this->studentsTimetablesUser();

        return response()->json([
            'data' => $service->selectedCourseGroupsForUser($authUser),
        ]);
    }

    public function updateOverviewSelections(Request $request, StudentTimetableOverviewService $service): JsonResponse
    {
        $authUser = $this->studentsTimetablesUser();

        $validated = $request->validate([
            'course_group_keys' => ['array'],
            'course_group_keys.*' => ['string', 'max:64'],
        ]);

        return response()->json([
            'message' => 'Modulauswahl wurde gespeichert.',
            'data' => $service->updateSelectedCourseGroupsForUser(
                $authUser,
                $validated['course_group_keys'] ?? [],
            ),
        ]);
    }

    public function ttEntryRememberedOffers(StudentTimetableRememberedTtEntryService $service): JsonResponse
    {
        $authUser = $this->studentsTimetablesUser();

        return response()->json([
            'data' => [
                'offers' => $service->offersForUser($authUser),
            ],
        ]);
    }

    public function updateTtEntryRememberedOffers(Request $request, StudentTimetableRememberedTtEntryService $service): JsonResponse
    {
        $authUser = $this->studentsTimetablesUser();

        $validated = $request->validate([
            'offers' => ['array', 'max:100'],
            'offers.*.key' => ['required', 'string', 'max:1000'],
            'offers.*.name' => ['required', 'string', 'max:255'],
            'offers.*.scheduleLabel' => ['nullable', 'string', 'max:255'],
            'offers.*.entries' => ['required', 'array', 'max:500'],
            'offers.*.entries.*.key' => ['required', 'string', 'max:1000'],
            'offers.*.entries.*.dateLabel' => ['nullable', 'string', 'max:50'],
            'offers.*.entries.*.dateValue' => ['nullable', 'date_format:Y-m-d'],
            'offers.*.entries.*.active' => ['sometimes', 'boolean'],
            'offers.*.entries.*.scheduleLabel' => ['nullable', 'string', 'max:255'],
            'offers.*.entries.*.timeFrom' => ['nullable', 'date_format:H:i'],
            'offers.*.entries.*.timeUntil' => ['nullable', 'date_format:H:i'],
        ]);

        return response()->json([
            'message' => 'Gemerkte Module wurden gespeichert.',
            'data' => [
                'offers' => $service->updateForUser($authUser, $validated['offers'] ?? []),
            ],
        ]);
    }

    public function timetableV2State(StudentTimetableV2StateService $service): JsonResponse
    {
        $authUser = $this->studentsTimetablesUser();

        return response()->json([
            'data' => [
                'state' => $service->stateForUser($authUser),
            ],
        ]);
    }

    public function updateTimetableV2State(Request $request, StudentTimetableV2StateService $service): JsonResponse
    {
        $authUser = $this->studentsTimetablesUser();

        $validated = $request->validate([
            'state' => ['required', 'array'],
            'state.selection' => ['nullable', 'array'],
            'state.timetableV2Adoption' => ['nullable', 'array'],
            'state.timetableV2Selection' => ['nullable', 'array'],
            'state.timetableV2Options' => ['nullable', 'array'],
            'state.transferredStudentContext' => ['nullable', 'array'],
        ]);

        return response()->json([
            'message' => 'Stundenplan-Auswahl wurde gespeichert.',
            'data' => [
                'state' => $service->updateForUser($authUser, $validated['state']),
            ],
        ]);
    }

    public function publishStudentTimetable(
        Request $request,
        StudentTimetablePublishedTimetableService $publishedTimetableService,
    ): JsonResponse {
        $authUser = $this->studentsTimetablesUser();

        $validated = $request->validate([
            'student_code' => ['required', 'string', 'max:255'],
            'state' => ['nullable', 'array'],
            'timetable' => ['required', 'array'],
            ...$this->timetableOverviewPayloadRules('timetable'),
        ]);

        $student = Import116::query()
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->where('student_code', $validated['student_code'])
            ->whereNotNull('exists_date')
            ->first();

        if (! $student) {
            abort(422, 'Der ausgewählte Schüler wurde nicht gefunden.');
        }

        $timetable = $this->normalizeTimetableOverviewPdfLabels($validated['timetable']);
        $publishedTimetable = $publishedTimetableService->publish(
            $authUser,
            $student,
            $timetable,
            $validated['state'] ?? null,
        );

        return response()->json([
            'message' => "Stundenplan für {$publishedTimetable->student_label} wurde gespeichert.",
            'data' => [
                'id' => $publishedTimetable->id,
                'student_code' => $publishedTimetable->student_code,
                'student_label' => $publishedTimetable->student_label,
                'name' => $publishedTimetable->name,
                'published_at' => optional($publishedTimetable->published_at)->toIso8601String(),
            ],
        ]);
    }

    public function overviewPdf(Request $request): Responsable
    {
        $authUser = $this->studentsTimetablesUser();

        $validated = $request->validate($this->timetableOverviewPayloadRules());

        $validated = $this->normalizeTimetableOverviewPdfLabels($validated);
        $validated['school_name'] = trim((string) ($authUser->selectedSchool?->long_name ?: $authUser->selectedSchool?->short_name));

        return pdf()
            ->view('pdfs.students-timetable-overview', ['data' => $validated])
            ->format(Format::A4)
            ->landscape()
            ->margins(top: 8, right: 20, bottom: 8, left: 20, unit: 'mm')
            ->name('stundenplan.pdf')
            ->download();
    }

    /**
     * @return array<string, mixed>
     */
    private function timetableOverviewPayloadRules(string $prefix = ''): array
    {
        $key = fn (string $field): string => $prefix === '' ? $field : "{$prefix}.{$field}";

        return [
            $key('manual_cover') => ['nullable', 'boolean'],
            $key('title') => ['nullable', 'string', 'max:120'],
            $key('subtitle') => ['nullable', 'string', 'max:255'],
            $key('student') => ['nullable', 'string', 'max:255'],
            $key('schoolyear') => ['nullable', 'string', 'max:120'],
            $key('generated_at') => ['nullable', 'string', 'max:120'],
            $key('study_selections') => ['nullable', 'array', 'max:12'],
            $key('study_selections.*.label') => ['required', 'string', 'max:80'],
            $key('study_selections.*.value') => ['required', 'string', 'max:120'],
            $key('print_options') => ['nullable', 'array'],
            $key('print_options.single_weeks') => ['nullable', 'boolean'],
            $key('print_options.course_list') => ['nullable', 'boolean'],
            $key('print_options.course_overview') => ['nullable', 'boolean'],
            $key('weekdays') => ['required', 'array', 'min:1', 'max:6'],
            $key('weekdays.*.label') => ['required', 'string', 'max:12'],
            $key('semesters') => ['required', 'array', 'min:1', 'max:2'],
            $key('semesters.*.label') => ['required', 'string', 'max:80'],
            $key('semesters.*.date_range') => ['nullable', 'string', 'max:80'],
            $key('semesters.*.weeks') => ['required', 'array', 'min:1', 'max:8'],
            $key('semesters.*.weeks.*.label') => ['nullable', 'string', 'max:80'],
            $key('semesters.*.weeks.*.hours') => ['required', 'array', 'max:20'],
            $key('semesters.*.weeks.*.hours.*.hour') => ['required', 'integer', 'min:1', 'max:30'],
            $key('semesters.*.weeks.*.hours.*.from') => ['nullable', 'string', 'max:20'],
            $key('semesters.*.weeks.*.hours.*.until') => ['nullable', 'string', 'max:20'],
            $key('semesters.*.weeks.*.hours.*.cells') => ['required', 'array', 'min:1', 'max:6'],
            $key('semesters.*.weeks.*.hours.*.cells.*.status') => ['nullable', 'string', Rule::in(['empty', 'filled', 'warning', 'conflict', 'related'])],
            $key('semesters.*.weeks.*.hours.*.cells.*.courses') => ['array', 'max:10'],
            $key('semesters.*.weeks.*.hours.*.cells.*.courses.*.label') => ['required', 'string', 'max:160'],
            $key('semesters.*.weeks.*.hours.*.cells.*.courses.*.identifier') => ['nullable', 'string', 'max:160'],
            $key('semesters.*.weeks.*.hours.*.cells.*.courses.*.details') => ['nullable', 'string', 'max:160'],
            $key('semesters.*.weeks.*.hours.*.cells.*.courses.*.dates') => ['nullable', 'array', 'max:120'],
            $key('semesters.*.weeks.*.hours.*.cells.*.courses.*.dates.*') => ['string', 'max:20'],
            $key('semesters.*.weeks.*.hours.*.cells.*.courses.*.overlap_dates') => ['nullable', 'array', 'max:120'],
            $key('semesters.*.weeks.*.hours.*.cells.*.courses.*.overlap_dates.*') => ['string', 'max:20'],
            $key('semesters.*.weeks.*.hours.*.cells.*.courses.*.time_from') => ['nullable', 'string', 'max:20'],
            $key('semesters.*.weeks.*.hours.*.cells.*.courses.*.time_until') => ['nullable', 'string', 'max:20'],
            $key('semesters.*.weeks.*.hours.*.cells.*.courses.*.is_fu') => ['nullable', 'boolean'],
            $key('semesters.*.weeks.*.hours.*.cells.*.courses.*.is_kompaktunterricht') => ['nullable', 'boolean'],
            $key('semesters.*.weeks.*.hours.*.cells.*.courses.*.is_block') => ['nullable', 'boolean'],
            $key('semesters.*.weeks.*.hours.*.cells.*.courses.*.recurrence_label') => ['nullable', 'string', 'max:80'],
            $key('semesters.*.weeks.*.hours.*.cells.*.courses.*.recurrence_interval') => ['nullable', 'integer', 'min:1', 'max:12'],
            $key('semesters.*.weeks.*.hours.*.cells.*.markers') => ['array', 'max:10'],
            $key('semesters.*.weeks.*.hours.*.cells.*.courses.*.student_course_type') => ['nullable', 'string', Rule::in(['', 'missing', 'additional'])],
            $key('semesters.*.weeks.*.hours.*.cells.*.courses.*.student_course_badge') => ['nullable', 'string', 'max:40'],
            $key('semesters.*.weeks.*.hours.*.cells.*.markers.*.label') => ['required', 'string', 'max:40'],
            $key('semesters.*.weeks.*.hours.*.cells.*.markers.*.title') => ['nullable', 'string', 'max:160'],
        ];
    }

    private function normalizeTimetableOverviewPdfLabels(array $data): array
    {
        foreach ($data['semesters'] as $semesterIndex => $semester) {
            foreach ($semester['weeks'] as $weekIndex => $week) {
                foreach ($week['hours'] as $hourIndex => $hour) {
                    foreach ($hour['cells'] as $cellIndex => $cell) {
                        $cellData = &$data['semesters'][$semesterIndex]['weeks'][$weekIndex]['hours'][$hourIndex]['cells'][$cellIndex];

                        foreach ($cell['courses'] ?? [] as $courseIndex => $course) {
                            $cellData['courses'][$courseIndex]['label'] = $this->normalizedTimetableCourseDisplayLabel($course['label'] ?? '');
                            $cellData['courses'][$courseIndex]['identifier'] = $this->normalizedTimetableCourseDisplayLabel($course['identifier'] ?? '');
                        }

                        foreach ($cell['markers'] ?? [] as $markerIndex => $marker) {
                            $cellData['markers'][$markerIndex]['label'] = $this->normalizedTimetableCourseDisplayLabel($marker['label'] ?? '');
                            $cellData['markers'][$markerIndex]['title'] = $this->normalizedTimetableCourseDisplayLabel($marker['title'] ?? '');
                        }

                        unset($cellData);
                    }
                }
            }
        }

        return $data;
    }

    private function normalizedTimetableCourseDisplayLabel(string $label): string
    {
        return preg_replace('/^LET(?=\d|\s|-|$)/iu', 'LPT', $label) ?? $label;
    }

    public function evaluationSettings(StudentTimetableEvaluationSettingsService $service): JsonResponse
    {
        $authUser = $this->studentsTimetablesUser();

        $this->ensureEvaluationSettingsScope($authUser);

        return response()->json([
            'data' => $service->settingsForUser($authUser),
        ]);
    }

    public function updateEvaluationSettings(
        Request $request,
        StudentTimetableEvaluationSettingsService $service,
    ): JsonResponse {
        $authUser = $this->studentsTimetablesUser();

        $this->ensureEvaluationSettingsScope($authUser);

        $validated = $request->validate([
            'criteria' => ['required', 'array', 'size:'.count($service->criterionKeys())],
            'criteria.*.key' => ['required', 'string', Rule::in($service->criterionKeys()), 'distinct'],
            'criteria.*.enabled' => ['required', 'boolean'],
            'criteria.*.priority' => ['required', 'integer', 'between:1,'.count($service->criterionKeys()), 'distinct'],
            'criteria.*.option' => ['nullable', 'string', Rule::in($service->optionValues())],
        ]);

        return response()->json([
            'message' => 'Bewertungseinstellungen wurden gespeichert.',
            'data' => $service->updateForUser($authUser, $validated['criteria']),
        ]);
    }

    private function ensureEvaluationSettingsScope(User $authUser): void
    {
        if (! $authUser->school_id) {
            abort(422, 'Bitte wählen Sie zuerst eine Schule aus.');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function timetableV2SubjectRows(User $authUser): array
    {
        return StudentTimetableSubjectRow::query()
            ->forStudyProgram(StudentTimetableStudyProgram::Normalstudium)
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->orderBy('sort_order')
            ->orderBy('semester')
            ->orderBy('branch')
            ->get([
                'id',
                'semester',
                'branch',
                'json_code',
                'json_subject',
                'name',
                'hours_per_week',
                'is_active',
                'source',
            ])
            ->map(fn (StudentTimetableSubjectRow $row): array => [
                'id' => $row->id,
                'semester' => $row->semester,
                'branch' => $row->branch === 'common' ? null : $row->branch,
                'json_code' => $row->json_code,
                'json_subject' => $row->json_subject,
                'name' => $this->canonicalTimetableV2SubjectName($row->name, $row->json_code, $row->json_subject),
                'hours_per_week' => $row->hours_per_week,
                'is_active' => $row->is_active,
                'source' => $row->source,
            ])
            ->values()
            ->all();
    }

    private function canonicalTimetableV2SubjectName(mixed $name, mixed $jsonCode = null, mixed $jsonSubject = null): ?string
    {
        $subjectKey = $this->subjectCodeWithoutModule($this->emptyStringToNull($jsonSubject))
            ?: $this->subjectCodeWithoutModule($this->emptyStringToNull($jsonCode));

        if ($subjectKey && mb_strtoupper($subjectKey, 'UTF-8') === 'LPT') {
            return 'Lern- und Präsentationstechniken';
        }

        return $this->emptyStringToNull($name);
    }

    private function subjectCodeWithoutModule(?string $code): ?string
    {
        if (! $code) {
            return null;
        }

        return preg_replace('/\d+$/', '', $code) ?: $code;
    }

    private function emptyStringToNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** @return array{religion: ?string, language: ?string, branch: ?string, arts_subject: ?string} */
    private function storedStudySelection(Import116 $student): array
    {
        $studySelection = is_array($student->study_selection) ? $student->study_selection : [];

        return [
            'religion' => $this->emptyStringToNull($studySelection['religion'] ?? null),
            'language' => $this->emptyStringToNull($studySelection['language'] ?? null),
            'branch' => $this->emptyStringToNull($studySelection['branch'] ?? null),
            'arts_subject' => $this->emptyStringToNull($studySelection['arts_subject'] ?? null),
        ];
    }

    /**
     * @return array{
     *     completed: list<array{code: string, grade: string, status: string}>,
     *     negative: list<array{code: string, grade: string, status: string}>
     * }
     */
    private function storedCourseResults(Import116 $student): array
    {
        $courseResults = is_array($student->course_results) ? $student->course_results : [];

        return [
            'completed' => $this->storedCourseResultItems($courseResults['completed'] ?? []),
            'negative' => $this->storedCourseResultItems($courseResults['negative'] ?? []),
        ];
    }

    /** @return list<array{code: string, grade: string, status: string}> */
    private function storedCourseResultItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'code' => trim((string) ($item['code'] ?? '')),
                'grade' => trim((string) ($item['grade'] ?? '')),
                'status' => trim((string) ($item['status'] ?? '')),
            ])
            ->filter(fn (array $item): bool => $item['code'] !== '' && $item['grade'] !== '')
            ->values()
            ->all();
    }

    private function studentsTimetablesUser(): User
    {
        if (! $authUser = $this->userHasRole(self::MODERATOR_ROLES)) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        app(SchoolyearService::class)->ensureActualSchoolyearForUser($authUser);

        return $authUser;
    }
}
