<?php

namespace App\Http\Controllers\Admin\StudentsTimetables;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Teaching\SchoolHourResource;
use App\Models\User;
use App\Services\SchoolHourService;
use App\Services\StudentsTimetables\RobotTimetableGeneratorService;
use App\Services\StudentsTimetables\StudentsTimetablesService;
use App\Services\StudentsTimetables\StudentTimetableEvaluationSettingsService;
use App\Services\StudentsTimetables\StudentTimetableOverviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentsTimetablesController extends Controller
{
    public function index(StudentsTimetablesService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'studentstimetables_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        return response()->json([
            'data' => $service->dashboardForUser($authUser),
        ]);
    }

    public function schoolHours(SchoolHourService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'studentstimetables_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        return response()->json([
            'data' => SchoolHourResource::collection($service->listForUser($authUser)),
        ]);
    }

    public function courseGroups(StudentTimetableOverviewService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'studentstimetables_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        return response()->json([
            'data' => $service->courseGroupsForUser($authUser),
        ]);
    }

    public function robotFullGreenCount(
        Request $request,
        RobotTimetableGeneratorService $generatorService,
        StudentTimetableOverviewService $overviewService,
        StudentTimetableEvaluationSettingsService $evaluationSettingsService,
    ): JsonResponse {
        if (! $authUser = $this->userHasRole(['admin', 'studentstimetables_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

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
            'deselected_course_keys' => ['array'],
            'deselected_course_keys.*' => ['string', 'max:255'],
            'deselected_course_group_keys' => ['array'],
            'deselected_course_group_keys.*' => ['string', 'max:255'],
            'selected_timetable_type' => ['nullable', 'string', 'in:full_green,green'],
            'selected_timetable_number' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);

        return response()->json([
            'data' => $generatorService->countFullGreenTimetablesForUser(
                $authUser,
                $validated,
                $overviewService,
                $evaluationSettingsService->activeCriteriaForUser($authUser),
                $validated['selected_timetable_type'] ?? null,
                (int) ($validated['selected_timetable_number'] ?? 1),
            ),
        ]);
    }

    public function overviewSelections(StudentTimetableOverviewService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'studentstimetables_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        return response()->json([
            'data' => $service->selectedCourseGroupsForUser($authUser),
        ]);
    }

    public function updateOverviewSelections(Request $request, StudentTimetableOverviewService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'studentstimetables_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

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
        if (! $authUser = $this->userHasRole(['admin', 'studentstimetables_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $this->ensureEvaluationSettingsScope($authUser);

        return response()->json([
            'data' => $service->settingsForUser($authUser),
        ]);
    }

    public function updateEvaluationSettings(
        Request $request,
        StudentTimetableEvaluationSettingsService $service,
    ): JsonResponse {
        if (! $authUser = $this->userHasRole(['admin', 'studentstimetables_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

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
        if (! $authUser->school_id || ! $authUser->schoolyear_id) {
            abort(422, 'Bitte wählen Sie zuerst eine Schule und ein Schuljahr aus.');
        }
    }
}
