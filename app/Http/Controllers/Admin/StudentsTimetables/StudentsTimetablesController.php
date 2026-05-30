<?php

namespace App\Http\Controllers\Admin\StudentsTimetables;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Teaching\SchoolHourResource;
use App\Models\Import116;
use App\Models\StudentTimetableRecognitionRow;
use App\Models\StudentTimetableSubjectRow;
use App\Models\User;
use App\Services\SchoolHourService;
use App\Services\SchoolyearService;
use App\Services\StudentsTimetables\RobotTimetableBackendSetupService;
use App\Services\StudentsTimetables\RobotTimetableGeneratorService;
use App\Services\StudentsTimetables\StudentsTimetablesService;
use App\Services\StudentsTimetables\StudentTimetableEvaluationSettingsService;
use App\Services\StudentsTimetables\StudentTimetableOverviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

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

    public function courseGroups(StudentTimetableOverviewService $service): JsonResponse
    {
        $authUser = $this->studentsTimetablesUser();

        return response()->json([
            'data' => $service->courseGroupsForUser($authUser),
        ]);
    }

    public function robotStudents(): JsonResponse
    {
        $authUser = $this->studentsTimetablesUser();

        $students = Import116::query()
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->whereNotNull('exists_date')
            ->orderBy('class')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'class', 'school_level', 'attendance_year', 'student_code', 'last_name', 'first_name'])
            ->map(fn (Import116 $student): array => [
                'id' => (int) $student->id,
                'class' => (string) $student->class,
                'school_level' => $student->school_level,
                'attendance_year' => $student->attendance_year,
                'student_code' => (string) $student->student_code,
                'last_name' => (string) $student->last_name,
                'first_name' => (string) $student->first_name,
                'title' => trim("{$student->class} · {$student->last_name} {$student->first_name}"),
            ])
            ->values();

        return response()->json([
            'data' => $students,
        ]);
    }

    public function robotStudentCompletedCourses(Request $request): JsonResponse
    {
        $authUser = $this->studentsTimetablesUser();

        $validated = $request->validate([
            'student_code' => ['required', 'string', 'max:255'],
        ]);

        $subjectRows = StudentTimetableSubjectRow::query()
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->where('is_active', true)
            ->get(['semester', 'json_code', 'json_subject']);

        $recognitionRows = StudentTimetableRecognitionRow::query()
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->where('student_code', $validated['student_code'])
            ->orderBy('subject')
            ->orderBy('row_number')
            ->get(['id', 'subject', 'grade', 'note', 'raw_data']);

        $sequentialSubjectLabels = $this->sequentialRecognitionSubjectLabels($recognitionRows, $subjectRows);

        $courses = $recognitionRows
            ->map(fn (StudentTimetableRecognitionRow $row): array => [
                'subject' => $sequentialSubjectLabels[$row->id]
                    ?? $this->recognitionCompletedCourseSubjectLabel($row, $subjectRows),
                'grade' => $this->recognitionCompletedCourseGrade($row),
            ])
            ->filter(fn (array $row): bool => $row['subject'] !== '' && $row['grade'] !== '')
            ->unique(fn (array $row): string => "{$row['subject']}|{$row['grade']}")
            ->sortBy([
                ['subject', 'asc'],
                ['grade', 'asc'],
            ], SORT_NATURAL)
            ->map(fn (array $row): array => [
                'subject' => (string) $row['subject'],
                'grade' => (string) $row['grade'],
            ])
            ->values();

        return response()->json([
            'data' => $courses,
            'total' => $courses->count(),
        ]);
    }

    /**
     * @param  Collection<int, StudentTimetableRecognitionRow>  $recognitionRows
     * @param  Collection<int, StudentTimetableSubjectRow>  $subjectRows
     * @return array<int, string>
     */
    private function sequentialRecognitionSubjectLabels(Collection $recognitionRows, Collection $subjectRows): array
    {
        $labels = [];

        $recognitionRows
            ->groupBy(fn (StudentTimetableRecognitionRow $row): string => $this->normalizedTimetableCourseCode(
                $this->recognitionTimetableCourseCode((string) $row->subject),
            ))
            ->each(function (Collection $rows, string $subject) use ($subjectRows, &$labels): void {
                if ($subject === '' || $this->timetableCourseModuleNumber($subject) !== '') {
                    return;
                }

                $exactLabels = $rows
                    ->map(fn (StudentTimetableRecognitionRow $row): string => $this->resolvedRecognitionSubjectFromSubjectRows(
                        $subject,
                        trim((string) data_get($row->raw_data, 'semester', '')),
                        $subjectRows,
                    ));

                if ($exactLabels->every(fn (string $label): bool => $label !== '')) {
                    return;
                }

                $candidateLabels = $this->subjectPlanModuleLabelsForRecognitionSubject($subject, $subjectRows);
                if ($candidateLabels->count() < $rows->count()) {
                    return;
                }

                $rows
                    ->values()
                    ->each(function (StudentTimetableRecognitionRow $row, int $index) use ($candidateLabels, &$labels): void {
                        $labels[$row->id] = $candidateLabels[$index];
                    });
            });

        return $labels;
    }

    /**
     * @param  Collection<int, StudentTimetableSubjectRow>  $subjectRows
     */
    private function recognitionCompletedCourseSubjectLabel(StudentTimetableRecognitionRow $row, Collection $subjectRows): string
    {
        $subject = $this->recognitionTimetableCourseCode((string) $row->subject);
        $semester = trim((string) data_get($row->raw_data, 'semester', ''));
        $normalizedSubject = $this->normalizedTimetableCourseCode($subject);

        if ($normalizedSubject !== '') {
            $resolvedSubject = $this->resolvedRecognitionSubjectFromSubjectRows($normalizedSubject, $semester, $subjectRows);

            if ($resolvedSubject !== '') {
                return $resolvedSubject;
            }
        }

        if ($subject === '' || $semester === '' || preg_match('/\d+$/u', $subject) === 1) {
            return $subject;
        }

        return "{$subject}{$semester}";
    }

    /**
     * @param  Collection<int, StudentTimetableSubjectRow>  $subjectRows
     */
    private function resolvedRecognitionSubjectFromSubjectRows(string $subject, string $semester, Collection $subjectRows): string
    {
        $subjectCodeAliases = $this->timetableCourseCodeAliases($subject);

        foreach ($subjectRows as $subjectRow) {
            $jsonCode = $this->normalizedTimetableCourseCode((string) $subjectRow->json_code);
            if ($jsonCode === '') {
                continue;
            }

            if (array_intersect($subjectCodeAliases, $this->timetableCourseCodeAliases($jsonCode)) !== []) {
                return $jsonCode;
            }
        }

        $subjectBaseAliases = $this->timetableCourseBaseAliases($this->timetableCourseCodeWithoutModule($subject));
        $semesterCandidates = collect([
            $semester,
            $this->timetableCourseModuleNumber($subject),
        ])
            ->map(fn (mixed $value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->unique()
            ->values();

        if ($semesterCandidates->isEmpty()) {
            return '';
        }

        foreach ($subjectRows as $subjectRow) {
            if (! $semesterCandidates->contains((int) $subjectRow->semester)) {
                continue;
            }

            $jsonCode = $this->normalizedTimetableCourseCode((string) $subjectRow->json_code);
            $jsonSubject = $this->normalizedTimetableCourseCode((string) $subjectRow->json_subject);
            $rowBaseAliases = array_values(array_unique([
                ...$this->timetableCourseBaseAliases($this->timetableCourseCodeWithoutModule($jsonCode)),
                ...$this->timetableCourseBaseAliases($jsonSubject),
            ]));

            if (array_intersect($subjectBaseAliases, $rowBaseAliases) !== []) {
                return $jsonCode;
            }
        }

        return '';
    }

    /**
     * @param  Collection<int, StudentTimetableSubjectRow>  $subjectRows
     * @return Collection<int, string>
     */
    private function subjectPlanModuleLabelsForRecognitionSubject(string $subject, Collection $subjectRows): Collection
    {
        $subjectBaseAliases = $this->timetableCourseBaseAliases($this->timetableCourseCodeWithoutModule($subject));

        return $subjectRows
            ->map(function (StudentTimetableSubjectRow $subjectRow) use ($subjectBaseAliases): ?array {
                $jsonCode = $this->normalizedTimetableCourseCode((string) $subjectRow->json_code);
                if ($jsonCode === '' || $this->timetableCourseModuleNumber($jsonCode) === '') {
                    return null;
                }

                $jsonSubject = $this->normalizedTimetableCourseCode((string) $subjectRow->json_subject);
                $rowBaseAliases = array_values(array_unique([
                    ...$this->timetableCourseBaseAliases($this->timetableCourseCodeWithoutModule($jsonCode)),
                    ...$this->timetableCourseBaseAliases($jsonSubject),
                ]));

                if (array_intersect($subjectBaseAliases, $rowBaseAliases) === []) {
                    return null;
                }

                return [
                    'semester' => (int) $subjectRow->semester,
                    'module' => (int) $this->timetableCourseModuleNumber($jsonCode),
                    'json_code' => $jsonCode,
                ];
            })
            ->filter()
            ->sortBy([
                ['semester', 'asc'],
                ['module', 'asc'],
                ['json_code', 'asc'],
            ], SORT_NATURAL)
            ->pluck('json_code')
            ->unique()
            ->values();
    }

    /**
     * @return list<string>
     */
    private function timetableCourseCodeAliases(string $code): array
    {
        $normalizedCode = $this->normalizedTimetableCourseCode($code);
        $moduleNumber = $this->timetableCourseModuleNumber($normalizedCode);

        return collect($this->timetableCourseBaseAliases($this->timetableCourseCodeWithoutModule($normalizedCode)))
            ->map(fn (string $base): string => "{$base}{$moduleNumber}")
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function timetableCourseBaseAliases(string $base): array
    {
        $normalizedBase = $this->normalizedTimetableCourseCode($base);
        $aliases = [$normalizedBase];

        $mappedAliases = [
            'GS' => ['GPB'],
            'GPB' => ['GS'],
            'GW' => ['GWB'],
            'GWB' => ['GW'],
            'ME' => ['MU'],
            'MU' => ['ME'],
            'S' => ['SPA'],
            'SPA' => ['S'],
            'LPT' => ['LET'],
            'LET' => ['LPT'],
        ];

        return collect([
            ...$aliases,
            ...($mappedAliases[$normalizedBase] ?? []),
        ])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function timetableCourseCodeWithoutModule(string $code): string
    {
        return preg_replace('/\d+$/u', '', $code) ?: $code;
    }

    private function timetableCourseModuleNumber(string $code): string
    {
        preg_match('/(\d+)$/u', $code, $matches);

        return $matches[1] ?? '';
    }

    private function normalizedTimetableCourseCode(string $code): string
    {
        return preg_replace('/\s+/u', '', mb_strtoupper(trim($code), 'UTF-8')) ?: '';
    }

    private function recognitionTimetableCourseCode(string $subject): string
    {
        $code = $this->normalizedTimetableCourseCode($subject);

        if (! str_contains($code, '_')) {
            return $code;
        }

        return collect(explode('_', $code))
            ->map(fn (string $part): string => trim($part))
            ->filter()
            ->last() ?: $code;
    }

    private function recognitionCompletedCourseGrade(StudentTimetableRecognitionRow $row): string
    {
        $note = trim((string) $row->note);

        if ($note !== '') {
            return $note;
        }

        return trim((string) $row->grade);
    }

    public function robotFullGreenCount(
        Request $request,
        RobotTimetableGeneratorService $generatorService,
        StudentTimetableOverviewService $overviewService,
        StudentTimetableEvaluationSettingsService $evaluationSettingsService,
    ): JsonResponse {
        $authUser = $this->studentsTimetablesUser();

        $validated = $request->validate([
            'selection' => ['required', 'array'],
            'selection.semester' => ['required', 'integer', 'between:1,20'],
            'selection.religion' => ['nullable', 'string', 'max:20'],
            'selection.branch' => ['nullable', 'string', 'max:80'],
            'selection.artsSubject' => ['nullable', 'string', 'max:20'],
            'selection.language' => ['nullable', 'string', 'max:20'],
            'constraints' => ['required', 'array'],
            'constraints.availableWeekdays' => ['array'],
            'constraints.availableWeekdays.*' => ['integer', 'between:1,7'],
            'constraints.availableTimes' => ['array'],
            'constraints.availableTimes.*' => ['integer', 'between:1,20'],
            'constraints.excludedWeekdayTimes' => ['array'],
            'constraints.excludedWeekdayTimes.*' => ['string', 'max:20'],
            'student' => ['nullable', 'array'],
            'student.studentCode' => ['nullable', 'string', 'max:255'],
            'selected_course_keys' => ['array'],
            'selected_course_keys.*' => ['string', 'max:255'],
            'deselected_course_keys' => ['array'],
            'deselected_course_keys.*' => ['string', 'max:255'],
            'deselected_course_group_keys' => ['array'],
            'deselected_course_group_keys.*' => ['string', 'max:255'],
            'selected_additional_course_keys' => ['array'],
            'selected_additional_course_keys.*' => ['string', 'max:255'],
            'selected_additional_courses_required' => ['sometimes', 'boolean'],
            'selected_timetable_type' => ['nullable', 'string', 'in:full_green,green,conflict'],
            'selected_timetable_number' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'selected_quality_criterion_keys' => ['sometimes', 'array'],
            'selected_quality_criterion_keys.*' => ['string', Rule::in($evaluationSettingsService->criterionKeys()), 'distinct'],
            'evaluation_criteria' => ['sometimes', 'array'],
            'evaluation_criteria.*.key' => ['required_with:evaluation_criteria', 'string', Rule::in($evaluationSettingsService->criterionKeys()), 'distinct'],
            'evaluation_criteria.*.enabled' => ['required_with:evaluation_criteria', 'boolean'],
            'evaluation_criteria.*.priority' => ['required_with:evaluation_criteria', 'integer', 'between:1,'.count($evaluationSettingsService->criterionKeys()), 'distinct'],
            'evaluation_criteria.*.option' => ['nullable', 'string', Rule::in($evaluationSettingsService->optionValues())],
        ]);

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
    ): JsonResponse {
        $authUser = $this->studentsTimetablesUser();

        $validated = $request->validate([
            'selection' => ['required', 'array'],
            'selection.semester' => ['required', 'integer', 'between:1,20'],
            'selection.religion' => ['nullable', 'string', 'max:20'],
            'selection.branch' => ['nullable', 'string', 'max:80'],
            'selection.artsSubject' => ['nullable', 'string', 'max:20'],
            'selection.language' => ['nullable', 'string', 'max:20'],
            'constraints' => ['required', 'array'],
            'constraints.availableWeekdays' => ['array'],
            'constraints.availableWeekdays.*' => ['integer', 'between:1,7'],
            'constraints.availableTimes' => ['array'],
            'constraints.availableTimes.*' => ['integer', 'between:1,20'],
            'constraints.excludedWeekdayTimes' => ['array'],
            'constraints.excludedWeekdayTimes.*' => ['string', 'max:20'],
            'student' => ['nullable', 'array'],
            'student.studentCode' => ['nullable', 'string', 'max:255'],
            'selected_course_keys' => ['array'],
            'selected_course_keys.*' => ['string', 'max:255'],
            'deselected_course_keys' => ['array'],
            'deselected_course_keys.*' => ['string', 'max:255'],
            'deselected_course_group_keys' => ['array'],
            'deselected_course_group_keys.*' => ['string', 'max:255'],
            'selected_additional_course_keys' => ['array'],
            'selected_additional_course_keys.*' => ['string', 'max:255'],
            'selected_additional_courses_required' => ['sometimes', 'boolean'],
            'selected_timetable_type' => ['nullable', 'string', 'in:full_green,green,conflict'],
            'selected_timetable_number' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'include_quality_counters' => ['sometimes', 'boolean'],
            'selected_quality_criterion_keys' => ['sometimes', 'array'],
            'selected_quality_criterion_keys.*' => ['string', Rule::in($evaluationSettingsService->criterionKeys()), 'distinct'],
            'selected_quality_criteria_required' => ['sometimes', 'boolean'],
            'evaluation_criteria' => ['sometimes', 'array'],
            'evaluation_criteria.*.key' => ['required_with:evaluation_criteria', 'string', Rule::in($evaluationSettingsService->criterionKeys()), 'distinct'],
            'evaluation_criteria.*.enabled' => ['required_with:evaluation_criteria', 'boolean'],
            'evaluation_criteria.*.priority' => ['required_with:evaluation_criteria', 'integer', 'between:1,'.count($evaluationSettingsService->criterionKeys()), 'distinct'],
            'evaluation_criteria.*.option' => ['nullable', 'string', Rule::in($evaluationSettingsService->optionValues())],
        ]);

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

    public function robotQualityCounters(
        Request $request,
        RobotTimetableBackendSetupService $backendSetupService,
        StudentTimetableOverviewService $overviewService,
        StudentTimetableEvaluationSettingsService $evaluationSettingsService,
    ): JsonResponse {
        $authUser = $this->studentsTimetablesUser();

        $validated = $request->validate($this->robotQualityCounterRules($evaluationSettingsService));

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

    /**
     * @return array<string, mixed>
     */
    private function robotQualityCounterRules(StudentTimetableEvaluationSettingsService $evaluationSettingsService): array
    {
        return [
            'selection' => ['required', 'array'],
            'selection.semester' => ['required', 'integer', 'between:1,20'],
            'selection.religion' => ['nullable', 'string', 'max:20'],
            'selection.branch' => ['nullable', 'string', 'max:80'],
            'selection.artsSubject' => ['nullable', 'string', 'max:20'],
            'selection.language' => ['nullable', 'string', 'max:20'],
            'constraints' => ['required', 'array'],
            'constraints.availableWeekdays' => ['array'],
            'constraints.availableWeekdays.*' => ['integer', 'between:1,7'],
            'constraints.availableTimes' => ['array'],
            'constraints.availableTimes.*' => ['integer', 'between:1,20'],
            'constraints.excludedWeekdayTimes' => ['array'],
            'constraints.excludedWeekdayTimes.*' => ['string', 'max:20'],
            'student' => ['nullable', 'array'],
            'student.studentCode' => ['nullable', 'string', 'max:255'],
            'selected_course_keys' => ['array'],
            'selected_course_keys.*' => ['string', 'max:255'],
            'deselected_course_keys' => ['array'],
            'deselected_course_keys.*' => ['string', 'max:255'],
            'deselected_course_group_keys' => ['array'],
            'deselected_course_group_keys.*' => ['string', 'max:255'],
            'selected_additional_course_keys' => ['array'],
            'selected_additional_course_keys.*' => ['string', 'max:255'],
            'selected_additional_courses_required' => ['sometimes', 'boolean'],
            'selected_timetable_type' => ['nullable', 'string', 'in:full_green,green,conflict'],
            'selected_timetable_number' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'selected_quality_criterion_keys' => ['sometimes', 'array'],
            'selected_quality_criterion_keys.*' => ['string', Rule::in($evaluationSettingsService->criterionKeys()), 'distinct'],
            'evaluation_criteria' => ['sometimes', 'array'],
            'evaluation_criteria.*.key' => ['required_with:evaluation_criteria', 'string', Rule::in($evaluationSettingsService->criterionKeys()), 'distinct'],
            'evaluation_criteria.*.enabled' => ['required_with:evaluation_criteria', 'boolean'],
            'evaluation_criteria.*.priority' => ['required_with:evaluation_criteria', 'integer', 'between:1,'.count($evaluationSettingsService->criterionKeys()), 'distinct'],
            'evaluation_criteria.*.option' => ['nullable', 'string', Rule::in($evaluationSettingsService->optionValues())],
        ];
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
            'message' => 'Kursauswahl wurde gespeichert.',
            'data' => $service->updateSelectedCourseGroupsForUser(
                $authUser,
                $validated['course_group_keys'] ?? [],
            ),
        ]);
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

    private function studentsTimetablesUser(): User
    {
        if (! $authUser = $this->userHasRole(self::MODERATOR_ROLES)) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        app(SchoolyearService::class)->ensureActualSchoolyearForUser($authUser);

        return $authUser;
    }
}
