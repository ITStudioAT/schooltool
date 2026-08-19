<?php

namespace App\Http\Controllers\Homepage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Homepage\UpdateStudentTimetableV3TimetableRequest;
use App\Models\User;
use App\Services\SchoolyearService;
use App\Services\StudentsTimetables\StudentTimetablesStudentOverviewService;
use App\Services\StudentsTimetables\StudentTimetableV3TimetableFilterService;
use App\Services\StudentsTimetables\StudentTimetableV3TimetableService;
use App\Services\StudentsTimetablesStudentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class StudentTimetableV3TimetableController extends Controller
{
    public function __construct(private SchoolyearService $schoolyearService) {}

    public function show(
        Request $request,
        StudentTimetableV3TimetableService $timetableService,
        StudentTimetablesStudentOverviewService $overviewService,
    ): JsonResponse {
        $authUser = $this->studentTimetablesUser();
        $validated = $request->validate([
            'workspace_id' => ['required', 'uuid'],
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
            'filters' => ['sometimes', 'array:include_saturday,free_days'],
            'filters.include_saturday' => ['sometimes', 'boolean'],
            'filters.free_days' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                'max:'.StudentTimetableV3TimetableFilterService::MAX_FREE_DAYS,
            ],
        ]);
        $studentContext = $this->studentContext($authUser, $overviewService);

        return response()->json([
            'data' => $timetableService->resultForUser(
                $authUser,
                [
                    'workspace_id' => $validated['workspace_id'],
                    'planning_mode' => 'with_student',
                    'student_code' => $studentContext['student_code'],
                ],
                (int) ($validated['page'] ?? 1),
                $validated['fingerprint'] ?? null,
                [
                    'include_saturday' => (bool) ($validated['filters']['include_saturday'] ?? true),
                    'free_days' => isset($validated['filters']['free_days'])
                        ? (int) $validated['filters']['free_days']
                        : null,
                ],
            ),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(
        UpdateStudentTimetableV3TimetableRequest $request,
        StudentTimetableV3TimetableService $timetableService,
        StudentTimetablesStudentOverviewService $overviewService,
    ): JsonResponse|StreamedResponse {
        $authUser = $this->studentTimetablesUser();
        $validated = $request->validated();
        $studentContext = $this->studentContext($authUser, $overviewService);
        $parameters = [
            'workspace_id' => $validated['workspace_id'],
            'planning_mode' => 'with_student',
            'student_code' => $studentContext['student_code'],
            'selection' => $studentContext['selection'],
            'selected_course_keys' => $validated['selected_course_keys'],
            'constraints' => [
                'availableWeekdays' => StudentTimetableV3TimetableService::CALCULATION_WEEKDAYS,
            ],
        ];

        if ($request->header('X-Timetable-Progress') === 'stream') {
            return $this->streamedCalculationResponse(
                $timetableService,
                $authUser,
                $validated['modules'],
                $parameters,
            );
        }

        return response()->json([
            'message' => 'Die möglichen Stundenpläne wurden berechnet.',
            'data' => $timetableService->createOrUpdateForUser(
                $authUser,
                $validated['modules'],
                $parameters,
            ),
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * @param  list<string|array<string, mixed>>  $modules
     * @param  array<string, mixed>  $parameters
     */
    private function streamedCalculationResponse(
        StudentTimetableV3TimetableService $timetableService,
        User $authUser,
        array $modules,
        array $parameters,
    ): StreamedResponse {
        return response()->stream(function () use ($timetableService, $authUser, $modules, $parameters): void {
            $emit = function (array $event): void {
                echo json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE).PHP_EOL;

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            };

            try {
                $result = $timetableService->createOrUpdateForUser(
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

    /** @return array{student_code: string, selection: array<string, mixed>} */
    private function studentContext(
        User $authUser,
        StudentTimetablesStudentOverviewService $overviewService,
    ): array {
        $summary = $overviewService->summaryForUser($authUser);
        $studentCode = trim((string) data_get($summary, 'student.student_code', ''));

        if ($studentCode === '') {
            abort(422, 'Der Studierendencode konnte nicht ermittelt werden.');
        }

        return [
            'student_code' => $studentCode,
            'selection' => (array) ($summary['selection'] ?? []),
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
