<?php

namespace App\Http\Controllers\Admin\StudentsTimetables;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SchoolyearService;
use App\Services\StudentsTimetables\StudentTimetablesStudentOverviewService;
use App\Services\StudentsTimetables\StudentTimetableV3TestReadinessService;
use Illuminate\Http\JsonResponse;

class StudentTimetableV3TestReadinessController extends Controller
{
    private const ADMIN_ROLES = ['super_admin', 'admin', 'studentstimetables_admin'];

    public function __invoke(
        StudentTimetableV3TestReadinessService $readinessService,
        StudentTimetablesStudentOverviewService $studentOverviewService,
    ): JsonResponse {
        $authUser = $this->studentsTimetablesUser();

        return response()->json([
            'data' => $readinessService->forUser($authUser, $studentOverviewService),
        ]);
    }

    private function studentsTimetablesUser(): User
    {
        if (! $authUser = $this->userHasRole(self::ADMIN_ROLES)) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        app(SchoolyearService::class)->ensureActualSchoolyearForUser($authUser);

        return $authUser;
    }
}
