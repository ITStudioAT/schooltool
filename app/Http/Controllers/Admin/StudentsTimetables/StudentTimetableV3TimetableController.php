<?php

namespace App\Http\Controllers\Admin\StudentsTimetables;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateStudentTimetableV3TimetableRequest;
use App\Models\User;
use App\Services\SchoolyearService;
use App\Services\StudentsTimetables\StudentTimetableV3TimetableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentTimetableV3TimetableController extends Controller
{
    private const MODERATOR_ROLES = ['super_admin', 'admin', 'studentstimetables_admin', 'studentstimetables_moderator'];

    public function show(
        Request $request,
        StudentTimetableV3TimetableService $service,
    ): JsonResponse {
        $authUser = $this->studentsTimetablesUser();
        $validated = $request->validate([
            'planning_mode' => ['required', 'string', Rule::in(['with_student', 'without_student'])],
            'student_code' => [
                Rule::requiredIf(fn (): bool => $request->query('planning_mode') === 'with_student'),
                Rule::prohibitedIf(fn (): bool => $request->query('planning_mode') === 'without_student'),
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        return response()->json([
            'data' => $service->resultForUser($authUser, [
                'planning_mode' => $validated['planning_mode'],
                'student_code' => $validated['student_code'] ?? null,
            ]),
        ]);
    }

    public function update(
        UpdateStudentTimetableV3TimetableRequest $request,
        StudentTimetableV3TimetableService $service,
    ): JsonResponse {
        $authUser = $this->studentsTimetablesUser();
        $validated = $request->validated();
        $parameters = $validated['parameters'];
        $allowSaturdayLessons = (bool) $validated['options']['allow_saturday_lessons'];
        $parameters['constraints'] = [
            'availableWeekdays' => $allowSaturdayLessons
                ? [1, 2, 3, 4, 5, 6]
                : [1, 2, 3, 4, 5],
        ];

        return response()->json([
            'message' => 'Die möglichen Stundenpläne wurden berechnet.',
            'data' => $service->createOrUpdateForUser(
                $authUser,
                $validated['modules'],
                $parameters,
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
