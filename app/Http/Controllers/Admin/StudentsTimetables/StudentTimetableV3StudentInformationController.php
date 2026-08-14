<?php

namespace App\Http\Controllers\Admin\StudentsTimetables;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SchoolyearService;
use App\Services\StudentsTimetables\StudentTimetableV3StudentInformationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentTimetableV3StudentInformationController extends Controller
{
    private const MODERATOR_ROLES = ['super_admin', 'admin', 'studentstimetables_admin', 'studentstimetables_moderator'];

    public function show(
        Request $request,
        StudentTimetableV3StudentInformationService $service,
    ): JsonResponse {
        $authUser = $this->studentsTimetablesUser();
        $validated = $request->validate([
            'student_code' => ['nullable', 'string', 'max:255'],
            'selection' => ['nullable', 'array:religion,language,branch,arts_subject'],
            'selection.religion' => ['nullable', 'string', 'max:50'],
            'selection.language' => ['nullable', 'string', 'max:50'],
            'selection.branch' => ['nullable', 'string', 'max:50'],
            'selection.arts_subject' => ['nullable', 'string', 'max:50'],
        ]);

        return response()->json([
            'data' => $service->informationForStudent(
                $authUser,
                isset($validated['student_code']) ? (string) $validated['student_code'] : null,
                (array) ($validated['selection'] ?? []),
            ),
        ]);
    }

    public function updateSchoolLevel(
        Request $request,
        StudentTimetableV3StudentInformationService $service,
    ): JsonResponse {
        $authUser = $this->studentsTimetablesUser();
        $validated = $request->validate([
            'student_code' => ['required', 'string', 'max:255'],
            'school_level' => ['required', 'string', 'max:255'],
            'selection' => ['nullable', 'array:religion,language,branch,arts_subject'],
            'selection.religion' => ['nullable', 'string', 'max:50'],
            'selection.language' => ['nullable', 'string', 'max:50'],
            'selection.branch' => ['nullable', 'string', 'max:50'],
            'selection.arts_subject' => ['nullable', 'string', 'max:50'],
        ]);

        return response()->json([
            'data' => $service->updateSchoolLevelForStudent(
                $authUser,
                (string) $validated['student_code'],
                (string) $validated['school_level'],
                (array) ($validated['selection'] ?? []),
            ),
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
