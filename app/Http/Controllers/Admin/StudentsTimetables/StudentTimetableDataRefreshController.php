<?php

namespace App\Http\Controllers\Admin\StudentsTimetables;

use App\Http\Controllers\Controller;
use App\Jobs\StudentsTimetables\RefreshStudentTimetableDataJob;
use App\Models\Import116;
use App\Models\Schoolyear;
use App\Models\StudentTimetableDataRefresh;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StudentTimetableDataRefreshController extends Controller
{
    private const ADMIN_ROLES = ['super_admin', 'admin', 'studentstimetables_admin'];

    public function index(): JsonResponse
    {
        $authUser = $this->dataRefreshUser();
        $schoolyear = $this->personalSchoolyear($authUser);
        $dataRefresh = $this->latestDataRefresh($authUser, $schoolyear);

        return response()->json([
            'data' => $dataRefresh ? $this->dataRefreshPayload($dataRefresh, $schoolyear) : null,
        ]);
    }

    public function store(): JsonResponse
    {
        $authUser = $this->dataRefreshUser();
        $schoolyear = $this->personalSchoolyear($authUser);

        [$dataRefresh, $wasCreated] = DB::transaction(function () use ($authUser, $schoolyear): array {
            Schoolyear::query()
                ->whereKey($schoolyear->id)
                ->where('school_id', $authUser->school_id)
                ->lockForUpdate()
                ->firstOrFail();

            $activeDataRefresh = StudentTimetableDataRefresh::query()
                ->where('school_id', $authUser->school_id)
                ->where('schoolyear_id', $schoolyear->id)
                ->whereIn('status', ['queued', 'running'])
                ->latest('id')
                ->first();

            if ($activeDataRefresh) {
                return [$activeDataRefresh, false];
            }

            $totalStudents = Import116::query()
                ->where('school_id', $authUser->school_id)
                ->where('schoolyear_id', $schoolyear->id)
                ->whereNotNull('exists_date')
                ->count();
            $dataRefresh = StudentTimetableDataRefresh::query()->create([
                'school_id' => $authUser->school_id,
                'schoolyear_id' => $schoolyear->id,
                'user_id' => $authUser->id,
                'status' => 'queued',
                'total_students' => $totalStudents,
                'processed_students' => 0,
                'study_selections_updated' => 0,
                'course_results_updated' => 0,
            ]);

            RefreshStudentTimetableDataJob::dispatch((int) $dataRefresh->id)->afterCommit();

            return [$dataRefresh, true];
        });

        return response()->json([
            'data' => $this->dataRefreshPayload($dataRefresh, $schoolyear),
        ], $wasCreated ? 202 : 200);
    }

    private function dataRefreshUser(): User
    {
        if (! $authUser = $this->userHasRole(self::ADMIN_ROLES)) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        return $authUser;
    }

    private function personalSchoolyear(User $authUser): Schoolyear
    {
        if (! $authUser->schoolyear_id) {
            abort(422, 'Kein persönliches Schuljahr ausgewählt.');
        }

        $schoolyear = Schoolyear::query()
            ->whereKey($authUser->schoolyear_id)
            ->where('school_id', $authUser->school_id)
            ->first();

        if (! $schoolyear) {
            abort(422, 'Das persönliche Schuljahr ist ungültig.');
        }

        return $schoolyear;
    }

    private function latestDataRefresh(User $authUser, Schoolyear $schoolyear): ?StudentTimetableDataRefresh
    {
        return StudentTimetableDataRefresh::query()
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $schoolyear->id)
            ->latest('id')
            ->first();
    }

    /** @return array<string, mixed> */
    private function dataRefreshPayload(
        StudentTimetableDataRefresh $dataRefresh,
        Schoolyear $schoolyear,
    ): array {
        $progressPercent = $dataRefresh->total_students > 0
            ? (int) round(($dataRefresh->processed_students / $dataRefresh->total_students) * 100)
            : ($dataRefresh->status === 'completed' ? 100 : 0);

        return [
            'id' => (int) $dataRefresh->id,
            'status' => $dataRefresh->status,
            'progress_percent' => min(100, max(0, $progressPercent)),
            'total_students' => $dataRefresh->total_students,
            'processed_students' => $dataRefresh->processed_students,
            'study_selections_updated' => $dataRefresh->study_selections_updated,
            'course_results_updated' => $dataRefresh->course_results_updated,
            'error_message' => $dataRefresh->error_message,
            'schoolyear' => [
                'id' => (int) $schoolyear->id,
                'label' => $schoolyear->concerns ?: $schoolyear->name,
            ],
            'started_at' => $dataRefresh->started_at?->toIso8601String(),
            'finished_at' => $dataRefresh->finished_at?->toIso8601String(),
        ];
    }
}
