<?php

namespace App\Http\Controllers\Homepage;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Teaching\SchoolHourResource;
use App\Http\Resources\Homepage\SchoolWithLicenceRecource;
use App\Http\Resources\Homepage\StudentsTimetablesUserResource;
use App\Models\SchoolTool;
use App\Models\User;
use App\Services\LicenceService;
use App\Services\SchoolHourService;
use App\Services\StudentsTimetables\RobotTimetableBackendSetupService;
use App\Services\StudentsTimetables\StudentTimetableEvaluationSettingsService;
use App\Services\StudentsTimetables\StudentTimetableOverviewService;
use App\Services\StudentsTimetables\StudentTimetablesStudentOverviewService;
use App\Services\StudentsTimetablesStudentService;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class StudentsTimetablesStudentController extends Controller
{
    public function config(LicenceService $licenceService)
    {
        $schools = $licenceService->selectableSchoolsForTool('StudentsTimetables')['schools'];

        return response()->json([
            'schools' => SchoolWithLicenceRecource::collection($schools),
            'config' => [
                'schooltool' => [
                    'students_timetables_max_schools_shown' => (int) config('schooltool.students_timetables_max_schools_shown', 20),
                ],
            ],
            'health' => [
                'queue_working' => $this->isQueueWorking(),
            ],
        ]);
    }

    public function loginStepEmail(Request $request, StudentsTimetablesStudentService $service)
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:login_with_password,login_without_password'],
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'email' => ['required', 'email'],
        ]);

        $schoolId = (int) $validated['school_id'];
        $email = $validated['email'];
        $schoolyearId = SchoolTool::query()
            ->where('school_id', $schoolId)
            ->value('active_schoolyear_id');

        if (! $schoolyearId) {
            abort(404, 'Kein aktives Schuljahr für die Schule gefunden. Wenden Sie sich an den Administrator.');
        }

        $user = $service->userForSchool($email, $schoolId);
        $import116User = $service->importStudentForSchoolyear($email, $schoolId, (int) $schoolyearId);

        if (! $import116User) {
            abort(403, 'Die E-Mail-Adresse ist nicht für diese Schule registriert. Bitte wenden Sie sich an Ihre Schule oder einen Administrator.');
        }

        $user = $user && $user->hasRole(StudentsTimetablesStudentService::ROLE_NAME)
            ? $user
            : $service->createOrUpdateUserFromImport116($import116User);

        if (! $user->hasRole(StudentsTimetablesStudentService::ROLE_NAME)) {
            $service->assignRole($user);
        }

        $data = $validated;
        $data['schoolyear_id'] = (int) $schoolyearId;

        if ($validated['type'] === 'login_with_password') {
            $data['status'] = 'enter_password';

            return response()->json($data);
        }

        (new UserService)->sendCode($user, 'Ihr Login-Code für die Schülerstundenpläne', $email);
        $data['status'] = 'code_sent';

        return response()->json($data);
    }

    public function loginStepCode(Request $request, StudentsTimetablesStudentService $service)
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:login_with_password,login_without_password'],
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'schoolyear_id' => ['required', 'integer', 'exists:schoolyears,id'],
            'email' => ['required', 'email'],
            'login_code' => ['required', 'string', 'size:6'],
        ]);

        $data = $validated;
        $user = $this->validatedLoginUser($service, $validated['email'], (int) $validated['school_id']);

        if (! $service->tokenIsValid($user, $validated['login_code'])) {
            $data['status'] = 'code_not_valid';

            return response()->json($data);
        }

        $service->performLogin($user);
        $data['user'] = new StudentsTimetablesUserResource($user);
        $data['status'] = 'login_ok';

        return response()->json($data);
    }

    public function loginStepPassword(Request $request, StudentsTimetablesStudentService $service)
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:login_with_password,login_without_password'],
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'schoolyear_id' => ['required', 'integer', 'exists:schoolyears,id'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        $data = $validated;
        $user = $this->validatedLoginUser($service, $validated['email'], (int) $validated['school_id']);

        if (! $service->passwordIsValid($user, $validated['password'])) {
            $data['status'] = 'password_not_valid';

            return response()->json($data);
        }

        $service->performLogin($user);
        $data['user'] = new StudentsTimetablesUserResource($user);
        $data['status'] = 'login_ok';

        return response()->json($data);
    }

    public function user()
    {
        if (Auth::check() && Auth::user()->hasRole(StudentsTimetablesStudentService::ROLE_NAME)) {
            return response()->json([
                'user' => new StudentsTimetablesUserResource(Auth::user()),
            ]);
        }

        return response()->json([
            'user' => null,
        ]);
    }

    public function overview(Request $request, StudentTimetablesStudentOverviewService $overviewService)
    {
        if (! Auth::check() || ! Auth::user()->hasRole(StudentsTimetablesStudentService::ROLE_NAME)) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate($this->studentOverviewSelectionRules());

        return response()->json([
            'data' => $overviewService->summaryForUser(Auth::user(), $validated['selection'] ?? []),
        ]);
    }

    public function updateProfileSelection(Request $request, StudentTimetablesStudentOverviewService $overviewService): JsonResponse
    {
        if (! $authUser = $this->userHasRole([StudentsTimetablesStudentService::ROLE_NAME])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate($this->studentOverviewProfileSelectionRules());
        $overviewService->updateProfileSelectionForUser($authUser, $validated['selection']);

        return response()->json([
            'message' => 'Auswahl wurde gespeichert.',
            'data' => $overviewService->summaryForUser($authUser),
        ]);
    }

    public function restoreProfileSelection(StudentTimetablesStudentOverviewService $overviewService): JsonResponse
    {
        if (! $authUser = $this->userHasRole([StudentsTimetablesStudentService::ROLE_NAME])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $overviewService->restoreProfileSelectionForUser($authUser);

        return response()->json([
            'message' => 'Auswahl wurde wiederhergestellt.',
            'data' => $overviewService->summaryForUser($authUser),
        ]);
    }

    public function evaluationSettings(StudentTimetableEvaluationSettingsService $service)
    {
        if (! $authUser = $this->userHasRole([StudentsTimetablesStudentService::ROLE_NAME])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return response()->json([
            'data' => $service->settingsForUser($authUser),
        ]);
    }

    public function updateEvaluationSettings(Request $request, StudentTimetableEvaluationSettingsService $service)
    {
        if (! $authUser = $this->userHasRole([StudentsTimetablesStudentService::ROLE_NAME])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'criteria' => ['required', 'array', 'size:'.count($service->criterionKeys())],
            'criteria.*.key' => ['required', 'string', Rule::in($service->criterionKeys()), 'distinct'],
            'criteria.*.enabled' => ['required', 'boolean'],
            'criteria.*.priority' => ['required', 'integer', 'between:1,'.count($service->criterionKeys()), 'distinct'],
            'criteria.*.option' => ['nullable', 'string', Rule::in($service->optionValues())],
        ]);

        return response()->json([
            'message' => 'Bewertungskriterien wurden gespeichert.',
            'data' => $service->updateForUser($authUser, $validated['criteria']),
        ]);
    }

    public function automaticTimetable(
        Request $request,
        StudentTimetablesStudentOverviewService $studentOverviewService,
        StudentTimetableOverviewService $overviewService,
        RobotTimetableBackendSetupService $backendSetupService,
        StudentTimetableEvaluationSettingsService $evaluationSettingsService,
        SchoolHourService $schoolHourService,
    ): JsonResponse {
        if (! $authUser = $this->userHasRole([StudentsTimetablesStudentService::ROLE_NAME])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'selected_course_keys' => ['required', 'array', 'min:1'],
            'selected_course_keys.*' => ['required', 'string', 'max:255'],
            'selected_additional_course_keys' => ['sometimes', 'array'],
            'selected_additional_course_keys.*' => ['string', 'max:255', 'distinct'],
            'selected_additional_courses_required' => ['sometimes', 'boolean'],
            'selected_quality_criterion_keys' => ['sometimes', 'array'],
            'selected_quality_criterion_keys.*' => ['string', Rule::in($evaluationSettingsService->criterionKeys()), 'distinct'],
            'selected_timetable_type' => ['nullable', 'string', Rule::in(['full_green', 'green', 'conflict'])],
            'selected_timetable_number' => ['nullable', 'integer', 'min:1'],
            ...$this->studentOverviewSelectionRules(),
        ]);

        $this->ensureSchoolyearForUser($authUser);

        $summary = $studentOverviewService->summaryForUser($authUser, $validated['selection'] ?? []);
        $selectedCourseKeys = $this->selectedAutomaticCourseKeys(
            $validated['selected_course_keys'],
            $summary['automatic_course_selection']['courses'] ?? [],
        );
        $selectedAdditionalCourseKeys = $this->selectedAutomaticCourseKeys(
            $validated['selected_additional_course_keys'] ?? [],
            $summary['additional_courses'] ?? [],
        );
        $selectedAdditionalCoursesRequired = $selectedAdditionalCourseKeys !== []
            && ($validated['selected_additional_courses_required'] ?? false) === true;

        if ($selectedCourseKeys === []) {
            abort(422, 'Bitte wählen Sie mindestens einen Kurs aus.');
        }

        $evaluationCriteria = $evaluationSettingsService->activeCriteriaForUser($authUser);
        $settings = $this->automaticTimetableSettings(
            $summary,
            $selectedCourseKeys,
            $this->availableTimesForAutomaticTimetable($overviewService->courseGroupsForUser($authUser)),
            $validated['selected_quality_criterion_keys'] ?? [],
            $selectedAdditionalCourseKeys,
            $selectedAdditionalCoursesRequired,
            $validated['selected_timetable_type'] ?? null,
            (int) ($validated['selected_timetable_number'] ?? 1),
        );
        $result = $this->firstAutomaticTimetableResult(
            $authUser,
            $settings,
            $overviewService,
            $backendSetupService,
            $evaluationCriteria,
            $validated['selected_quality_criterion_keys'] ?? [],
            $validated['selected_timetable_type'] ?? null,
        );

        return response()->json([
            'data' => [
                ...$result,
                'school_hours' => SchoolHourResource::collection($schoolHourService->listForUser($authUser)),
            ],
        ]);
    }

    public function changePassword(Request $request)
    {
        if (! $authUser = $this->userHasRole([StudentsTimetablesStudentService::ROLE_NAME])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'new_password' => ['required', 'string', 'min:8', 'max:255'],
            'confirm_password' => ['required', 'string', 'min:8', 'max:255', 'same:new_password'],
        ]);

        $authUser->password = Hash::make($validated['new_password']);
        $authUser->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Passwort erfolgreich geändert',
        ]);
    }

    private function validatedLoginUser(StudentsTimetablesStudentService $service, string $email, int $schoolId): User
    {
        $user = $service->userForSchool($email, $schoolId);

        if (! $user || ! $user->hasRole(StudentsTimetablesStudentService::ROLE_NAME)) {
            abort(403, 'Die E-Mail-Adresse ist nicht für diese Schule registriert. Bitte wenden Sie sich an Ihre Schule oder einen Administrator.');
        }

        return $user;
    }

    private function ensureSchoolyearForUser(User $user): void
    {
        if ($user->schoolyear_id) {
            return;
        }

        $schoolyearId = SchoolTool::query()
            ->where('school_id', $user->school_id)
            ->value('active_schoolyear_id');

        if (! $schoolyearId) {
            abort(422, 'Kein aktives Schuljahr gefunden.');
        }

        $user->schoolyear_id = (int) $schoolyearId;
    }

    /**
     * @return array<string, mixed>
     */
    private function studentOverviewSelectionRules(): array
    {
        return [
            'selection' => ['sometimes', 'array:semester,religion,language,branch,arts_subject,artsSubject'],
            'selection.semester' => ['sometimes', 'nullable', 'integer', 'between:1,8'],
            'selection.religion' => ['sometimes', 'nullable', 'string', Rule::in(['ETH', 'Rev', 'Ris', 'Rk', 'Ror'])],
            'selection.language' => ['sometimes', 'nullable', 'string', Rule::in(['L', 'F', 'S'])],
            'selection.branch' => ['sometimes', 'nullable', 'string', Rule::in(['wirtschaftskundlich', 'gymnasial'])],
            'selection.arts_subject' => ['sometimes', 'nullable', 'string', Rule::in(['ME', 'BE'])],
            'selection.artsSubject' => ['sometimes', 'nullable', 'string', Rule::in(['ME', 'BE'])],
        ];
    }

    private function studentOverviewProfileSelectionRules(): array
    {
        return [
            'selection' => ['required', 'array:religion,language,branch,arts_subject,artsSubject'],
            'selection.religion' => ['sometimes', 'nullable', 'string', Rule::in(['ETH', 'Rev', 'Ris', 'Rk', 'Ror'])],
            'selection.language' => ['sometimes', 'nullable', 'string', Rule::in(['L', 'F', 'S'])],
            'selection.branch' => ['sometimes', 'nullable', 'string', Rule::in(['wirtschaftskundlich', 'gymnasial'])],
            'selection.arts_subject' => ['sometimes', 'nullable', 'string', Rule::in(['ME', 'BE'])],
            'selection.artsSubject' => ['sometimes', 'nullable', 'string', Rule::in(['ME', 'BE'])],
        ];
    }

    /**
     * @param  list<string>  $selectedCourseKeys
     * @param  list<array<string, mixed>>  $automaticCourses
     * @return list<string>
     */
    private function selectedAutomaticCourseKeys(array $selectedCourseKeys, array $automaticCourses): array
    {
        $allowedCourseKeys = collect($automaticCourses)
            ->pluck('key')
            ->map(fn (mixed $courseKey): string => (string) $courseKey)
            ->filter()
            ->flip();

        return collect($selectedCourseKeys)
            ->map(fn (mixed $courseKey): string => (string) $courseKey)
            ->filter(fn (string $courseKey): bool => $allowedCourseKeys->has($courseKey))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  list<string>  $selectedCourseKeys
     * @param  list<int>  $availableTimes
     * @param  list<string>  $selectedQualityCriterionKeys
     * @param  list<string>  $selectedAdditionalCourseKeys
     * @return array<string, mixed>
     */
    private function automaticTimetableSettings(
        array $summary,
        array $selectedCourseKeys,
        array $availableTimes,
        array $selectedQualityCriterionKeys,
        array $selectedAdditionalCourseKeys,
        bool $selectedAdditionalCoursesRequired,
        ?string $selectedTimetableType,
        int $selectedTimetableNumber,
    ): array {
        $selection = $summary['selection'] ?? [];

        return [
            'selection' => [
                'semester' => (int) ($selection['semester'] ?? 1),
                'religion' => $selection['religion'] ?? 'ETH',
                'branch' => $selection['branch'] ?? null,
                'artsSubject' => $selection['arts_subject'] ?? 'ME',
                'language' => $selection['language'] ?? 'L',
            ],
            'constraints' => [
                'availableWeekdays' => [1, 2, 3, 4, 5, 6],
                'availableTimes' => $availableTimes,
                'excludedWeekdayTimes' => [],
            ],
            'student' => [
                'studentCode' => $summary['student']['student_code'] ?? null,
            ],
            'selected_course_keys' => $selectedCourseKeys,
            'deselected_course_keys' => [],
            'deselected_course_group_keys' => [],
            'selected_additional_course_keys' => $selectedAdditionalCourseKeys,
            'selected_additional_courses_required' => $selectedAdditionalCoursesRequired,
            'selected_timetable_type' => $selectedTimetableType ?? 'full_green',
            'selected_timetable_number' => $selectedTimetableNumber,
            'include_quality_counters' => true,
            'selected_quality_criteria_required' => $selectedQualityCriterionKeys !== [],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $courseGroups
     * @return list<int>
     */
    private function availableTimesForAutomaticTimetable(array $courseGroups): array
    {
        $availableTimes = collect($courseGroups)
            ->pluck('hour')
            ->map(fn (mixed $hour): int => (int) $hour)
            ->filter(fn (int $hour): bool => $hour > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $availableTimes === []
            ? [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]
            : $availableTimes;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $evaluationCriteria
     * @param  list<string>  $selectedQualityCriterionKeys
     * @return array<string, mixed>
     */
    private function firstAutomaticTimetableResult(
        User $authUser,
        array $settings,
        StudentTimetableOverviewService $overviewService,
        RobotTimetableBackendSetupService $backendSetupService,
        array $evaluationCriteria,
        array $selectedQualityCriterionKeys = [],
        ?string $selectedTimetableType = null,
    ): array {
        if ($selectedTimetableType) {
            return $backendSetupService->createInitialBackendTimetable(
                $authUser,
                $settings,
                $overviewService,
                $evaluationCriteria,
                $selectedQualityCriterionKeys,
            );
        }

        $result = [];

        foreach (['full_green', 'green', 'conflict'] as $timetableType) {
            $settings['selected_timetable_type'] = $timetableType;
            $result = $backendSetupService->createInitialBackendTimetable(
                $authUser,
                $settings,
                $overviewService,
                $evaluationCriteria,
                $selectedQualityCriterionKeys,
            );

            if ($result['selected_timetable'] !== null) {
                break;
            }
        }

        return $result;
    }

    private function isQueueWorking(): bool
    {
        $workerAt = Cache::get('health:worker');

        if (! $workerAt) {
            return true;
        }

        return Carbon::parse($workerAt)->greaterThan(now()->subMinutes(2));
    }
}
