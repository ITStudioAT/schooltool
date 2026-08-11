<?php

namespace App\Http\Controllers\Admin\StudentsTimetables;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateStudentTimetableV3StateRequest;
use App\Models\User;
use App\Services\SchoolyearService;
use App\Services\StudentsTimetables\StudentTimetableV3StateService;
use Illuminate\Http\JsonResponse;

class StudentTimetableV3StateController extends Controller
{
    private const MODERATOR_ROLES = ['super_admin', 'admin', 'studentstimetables_admin', 'studentstimetables_moderator'];

    public function show(StudentTimetableV3StateService $service): JsonResponse
    {
        $authUser = $this->studentsTimetablesUser();

        return response()->json([
            'data' => [
                'state' => $service->stateForUser($authUser),
            ],
        ]);
    }

    public function update(
        UpdateStudentTimetableV3StateRequest $request,
        StudentTimetableV3StateService $service,
    ): JsonResponse {
        $authUser = $this->studentsTimetablesUser();

        return response()->json([
            'message' => 'V3-Arbeitsstand wurde gespeichert.',
            'data' => [
                'state' => $service->updateForUser($authUser, $request->validated('state')),
            ],
        ]);
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
