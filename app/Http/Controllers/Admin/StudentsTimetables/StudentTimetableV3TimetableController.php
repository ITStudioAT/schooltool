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
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class StudentTimetableV3TimetableController extends Controller
{
    private const MODERATOR_ROLES = ['super_admin', 'admin', 'studentstimetables_admin', 'studentstimetables_moderator'];

    public function show(
        Request $request,
        StudentTimetableV3TimetableService $service,
    ): JsonResponse {
        $authUser = $this->studentsTimetablesUser();
        $validated = $request->validate([
            'workspace_id' => ['required', 'uuid'],
            'planning_mode' => ['required', 'string', Rule::in(['with_student', 'without_student'])],
            'page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:'.StudentTimetableV3TimetableService::MAX_TIMETABLE_PAGE,
            ],
            'fingerprint' => [
                Rule::requiredIf(fn (): bool => (int) $request->query('page', 1) > 1),
                'string',
                'size:64',
                'regex:/\A[a-f0-9]{64}\z/',
            ],
            'per_page' => ['prohibited'],
            'filters' => ['sometimes', 'array:include_saturday'],
            'filters.include_saturday' => ['sometimes', 'boolean'],
            'student_code' => [
                Rule::requiredIf(fn (): bool => $request->query('planning_mode') === 'with_student'),
                Rule::prohibitedIf(fn (): bool => $request->query('planning_mode') === 'without_student'),
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        return response()->json([
            'data' => $service->resultForUser(
                $authUser,
                [
                    'workspace_id' => $validated['workspace_id'],
                    'planning_mode' => $validated['planning_mode'],
                    'student_code' => $validated['student_code'] ?? null,
                ],
                (int) ($validated['page'] ?? 1),
                $validated['fingerprint'] ?? null,
                [
                    'include_saturday' => (bool) ($validated['filters']['include_saturday'] ?? true),
                ],
            ),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(
        UpdateStudentTimetableV3TimetableRequest $request,
        StudentTimetableV3TimetableService $service,
    ): JsonResponse|StreamedResponse {
        $authUser = $this->studentsTimetablesUser();
        $validated = $request->validated();
        $parameters = $validated['parameters'];
        $parameters['constraints'] = [
            'availableWeekdays' => StudentTimetableV3TimetableService::CALCULATION_WEEKDAYS,
        ];

        if ($request->header('X-Timetable-Progress') === 'stream') {
            return $this->streamedCalculationResponse(
                $service,
                $authUser,
                $validated['modules'],
                $parameters,
            );
        }

        return response()->json([
            'message' => 'Die möglichen Stundenpläne wurden berechnet.',
            'data' => $service->createOrUpdateForUser(
                $authUser,
                $validated['modules'],
                $parameters,
            ),
        ]);
    }

    /**
     * @param  list<string|array<string, mixed>>  $modules
     * @param  array<string, mixed>  $parameters
     */
    private function streamedCalculationResponse(
        StudentTimetableV3TimetableService $service,
        User $authUser,
        array $modules,
        array $parameters,
    ): StreamedResponse {
        return response()->stream(function () use ($service, $authUser, $modules, $parameters): void {
            $emit = function (array $event): void {
                echo json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE).PHP_EOL;

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            };

            try {
                $result = $service->createOrUpdateForUser(
                    $authUser,
                    $modules,
                    $parameters,
                    function (
                        int $progressPercent,
                        int $combinationCount,
                        string $phase = 'checking',
                        int $checkedCombinationCount = 0,
                    ) use ($emit): void {
                        $emit([
                            'type' => 'progress',
                            'progress_percent' => $progressPercent,
                            'combination_count' => $combinationCount,
                            'checked_combination_count' => $checkedCombinationCount,
                            'phase' => $phase,
                        ]);
                    },
                );

                $emit([
                    'type' => 'complete',
                    'message' => 'Die möglichen Stundenpläne wurden berechnet.',
                    'data' => $result,
                ]);
            } catch (ValidationException $exception) {
                $emit([
                    'type' => 'error',
                    'message' => $exception->getMessage(),
                    'errors' => $exception->errors(),
                ]);
            } catch (Throwable $exception) {
                report($exception);
                $emit([
                    'type' => 'error',
                    'message' => 'Bitte versuchen Sie die Berechnung erneut.',
                ]);
            }
        }, headers: [
            'Cache-Control' => 'private, no-store, no-cache, no-transform',
            'Content-Type' => 'application/x-ndjson; charset=UTF-8',
            'X-Accel-Buffering' => 'no',
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
