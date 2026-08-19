<?php

namespace App\Http\Controllers\Homepage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Homepage\UpdateStudentTimetableV3StateRequest;
use App\Models\User;
use App\Services\SchoolyearService;
use App\Services\StudentsTimetables\StudentTimetablesStudentOverviewService;
use App\Services\StudentsTimetables\StudentTimetableV3StateService;
use App\Services\StudentsTimetablesStudentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentTimetableV3StateController extends Controller
{
    public function __construct(private SchoolyearService $schoolyearService) {}

    public function show(
        Request $request,
        StudentTimetableV3StateService $stateService,
        StudentTimetablesStudentOverviewService $overviewService,
    ): JsonResponse {
        $authUser = $this->studentTimetablesUser();
        $validated = $request->validate([
            'workspace_id' => ['required', 'uuid'],
        ]);
        $context = $this->stateContext($authUser, $overviewService, $validated['workspace_id']);
        $state = $stateService->stateForUser($authUser, $context);

        return response()->json([
            'data' => [
                'manual_timetable_draft' => $this->manualTimetableDraft($state),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(
        UpdateStudentTimetableV3StateRequest $request,
        StudentTimetableV3StateService $stateService,
        StudentTimetablesStudentOverviewService $overviewService,
    ): JsonResponse {
        $authUser = $this->studentTimetablesUser();
        $validated = $request->validated();
        $context = $this->stateContext($authUser, $overviewService, $validated['workspace_id']);
        $currentState = $stateService->stateForUser($authUser, $context) ?? [];
        $manualTimetableDraft = $this->normalizedManualTimetableDraft($validated['manual_timetable_draft']);
        $state = [
            ...$currentState,
            'entrySelection' => [
                'mode' => 'with_student',
                'student' => ['student_code' => $context['student_code']],
            ],
            'manualTimetableDraft' => $manualTimetableDraft,
        ];

        $stateService->updateForUser($authUser, $context, $state);

        return response()->json([
            'message' => 'Manueller Entwurf wurde gespeichert.',
            'data' => [
                'manual_timetable_draft' => $manualTimetableDraft,
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    /** @return array{workspace_id: string, planning_mode: string, student_code: string} */
    private function stateContext(
        User $authUser,
        StudentTimetablesStudentOverviewService $overviewService,
        string $workspaceId,
    ): array {
        $summary = $overviewService->summaryForUser($authUser);
        $studentCode = trim((string) data_get($summary, 'student.student_code', ''));

        if ($studentCode === '') {
            abort(422, 'Der Studierendencode konnte nicht ermittelt werden.');
        }

        return [
            'workspace_id' => $workspaceId,
            'planning_mode' => 'with_student',
            'student_code' => $studentCode,
        ];
    }

    /** @param array<string, mixed>|null $state */
    private function manualTimetableDraft(?array $state): ?array
    {
        $manualTimetableDraft = $state['manualTimetableDraft'] ?? null;

        return is_array($manualTimetableDraft) ? $manualTimetableDraft : null;
    }

    /**
     * @param  array<string, mixed>  $manualTimetableDraft
     * @return array<string, mixed>
     */
    private function normalizedManualTimetableDraft(array $manualTimetableDraft): array
    {
        return [
            'source' => 'automatic',
            'fingerprint' => $manualTimetableDraft['fingerprint'],
            'timetableKey' => $manualTimetableDraft['timetable_key'],
            'timetableIndex' => $manualTimetableDraft['timetable_index'],
            'selectedCourseKeys' => array_values($manualTimetableDraft['selected_course_keys']),
            'removedCourseKeys' => array_values($manualTimetableDraft['removed_course_keys']),
        ];
    }

    private function studentTimetablesUser(): User
    {
        if (! $authUser = $this->userHasRole([StudentsTimetablesStudentService::ROLE_NAME])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $this->schoolyearService->ensureActualSchoolyearForUser($authUser);

        return $authUser;
    }
}
