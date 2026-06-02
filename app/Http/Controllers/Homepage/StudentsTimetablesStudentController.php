<?php

namespace App\Http\Controllers\Homepage;

use App\Http\Controllers\Controller;
use App\Http\Resources\Homepage\SchoolWithLicenceRecource;
use App\Http\Resources\Homepage\StudentsTimetablesUserResource;
use App\Models\SchoolTool;
use App\Models\User;
use App\Services\LicenceService;
use App\Services\StudentsTimetables\StudentTimetableEvaluationSettingsService;
use App\Services\StudentsTimetables\StudentTimetablesStudentOverviewService;
use App\Services\StudentsTimetablesStudentService;
use App\Services\UserService;
use Carbon\Carbon;
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

    public function overview(StudentTimetablesStudentOverviewService $overviewService)
    {
        if (! Auth::check() || ! Auth::user()->hasRole(StudentsTimetablesStudentService::ROLE_NAME)) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return response()->json([
            'data' => $overviewService->summaryForUser(Auth::user()),
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

    private function isQueueWorking(): bool
    {
        $workerAt = Cache::get('health:worker');

        if (! $workerAt) {
            return true;
        }

        return Carbon::parse($workerAt)->greaterThan(now()->subMinutes(2));
    }
}
