<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\AdminNewTeacherStepCodeRequest;
use App\Http\Requests\Admin\AdminNewTeacherStepEmailRequest;
use App\Http\Requests\Admin\AdminNewTeacherStepSchoolRequest;
use App\Http\Requests\Admin\AdminPasswordUnknownStepPasswordRequest;
use App\Http\Requests\Admin\AdminPasswordUnknownStepSchoolRequest;
use App\Http\Requests\Admin\AdminPasswordUnknownStepToken2Request;
use App\Http\Requests\Admin\AdminPasswordUnknownStepTokenRequest;
use App\Http\Requests\Admin\AdminPasswordUnkownStepPasswordRequest;
use App\Http\Requests\Admin\AdminPasswordUnkownStepTokenRequest;
use App\Http\Requests\Admin\LoginStep2Request;
use App\Http\Requests\Admin\LoginStep3Request;
use App\Http\Requests\Admin\LoginStepEmailRequest;
use App\Http\Requests\Admin\PasswordUnknownStep1Request;
use App\Http\Requests\Admin\PasswordUnknownStep2Request;
use App\Http\Requests\Admin\PasswordUnknownStep3Request;
use App\Http\Requests\Admin\PasswordUnknownStep4Request;
use App\Http\Requests\Admin\PasswordUnknownStepEmailRequest;
use App\Http\Requests\Admin\RegisterStep1Request;
use App\Http\Requests\Admin\RegisterStep2Request;
use App\Http\Requests\Admin\RegisterStep3Request;
use App\Http\Resources\Admin\RegisterResource;
use App\Http\Resources\Admin\SchoolResource;
use App\Http\Resources\Admin\SchoolyearResource;
use App\Http\Resources\Admin\UserResource;
use App\Http\Resources\Admin\UserWithRoleResource;
use App\Models\QueueTest;
use App\Models\Role;
use App\Models\School;
use App\Models\Teacher;
use App\Models\SchoolTool;
use App\Models\User;
use App\Services\AdminNavigationService;
use App\Services\AdminService;
use App\Services\LicenceService;
use App\Services\TeacherListService;
use App\Traits\HasRoleTrait;
use Barryvdh\Debugbar\Facades\Debugbar;
use Composer\InstalledVersions;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Lab404\Impersonate\Services\ImpersonateManager;

class AdminController extends Controller
{
    use HasRoleTrait;
    public function config(Request $request)
    {
        // Laden aller auswählbaren Schulen
        // $schools = School::selectables()->get();

        // $viaRemember = Auth::viaRemember();

        $data = $this->getConfigData();

        return response()->json($data, 200);
    }



    private function getConfigData()
    {
        $navigationService = new AdminNavigationService();

        /** @var \App\Models\User|null $user */
        $user = Auth::check() ? Auth::user() : null;

        /** @var ImpersonateManager $impersonateManager */
        $impersonateManager = app(ImpersonateManager::class);
        $isImpersonating = $user ? $impersonateManager->isImpersonating() : false;
        $impersonator = null;
        if ($isImpersonating) {
            $impersonatorId = $impersonateManager->getImpersonatorId();
            if ($impersonatorId) {
                $impersonator = User::query()
                    ->with('selectedSchool:id,long_name,short_name')
                    ->find($impersonatorId);
            }
        }

        $lastImport116At = null;
        if ($user) {
            $lastImport116At = SchoolTool::where('school_id', $user->school_id)->value('import_166_at');
        }

        $data = [
            'logo' => config('schooltool.logo', ''),
            'copyright' => config('schooltool.copyright', ''),
            'title' => 'SchoolTool',
            'company' => 'ITStudio Dipl.-Ing. Günther Kron',
            'version' => config('schooltool.version', 'x.x.x'),
            'timeout' => config('spa.timeout', 3000),
            'is_auth' => Auth::check(),
            'user' => $user ? new UserWithRoleResource($user) : null,
            'selected_school' =>  $user && $user->selectedSchool ? new SchoolResource($user->selectedSchool) : null,
            'selected_schoolyear' =>  $user && $user->selectedSchoolyear ? new SchoolyearResource($user->selectedSchoolyear) : null,
            'selected_register' =>  $user && $user->selectedRegister ? new RegisterResource($user->selectedRegister->loadCount([
                'bookings',
                'dates',
                'dates as different_dates_count' => function ($q) {
                    $q->select(DB::raw('COUNT(DISTINCT date)'));
                },
            ])) : null,
            'menu' => $user ? $navigationService->dashboardMenu() : [],
            'roles' => $user ? $user->getRoleNames() : [],
            'impersonation' => [
                'is_impersonating' => $isImpersonating,
                'impersonator' => $impersonator ? [
                    'id' => $impersonator->id,
                    'last_name' => $impersonator->last_name,
                    'first_name' => $impersonator->first_name,
                    'email' => $impersonator->email,
                    'school_id' => $impersonator->school_id,
                    'school_name' => trim((string) ($impersonator->selectedSchool?->long_name ?: $impersonator->selectedSchool?->short_name)),
                ] : null,
            ],
            'teaching' => [
                'last_import_116_at' => $lastImport116At,
            ],
        ];

        $data['health']['queue_working'] = true;

        return $data;
    }



    public function registerStep1(RegisterStep1Request $request)
    {
        $adminService = new AdminService();
        $validated = $request->validated();

        $user = $adminService->checkRegister($validated['data']);
        if (! $user) {
            $user = $adminService->createRegisterUser($validated['data']);
        }

        // Token zusenden
        $adminService->sendRegisterToken($user, $validated['data']['email'], 1);
        $data = ['step' => 'REGISTER_ENTER_TOKEN'];

        return response()->json($data, 200);
    }

    public function registerStep2(RegisterStep2Request $request)
    {
        $adminService = new AdminService();
        $validated = $request->validated();

        $user = $adminService->checkRegister($validated['data']);

        if (!$user) abort(401, 'Registrieren funktioniert mit dieser E-Mail-Adresse nicht.');

        // E-Mail ist somit verifiziert!
        $user->email_verified_at = now();
        $user->save();

        $data = ['step' => 'REGISTER_ENTER_FIELDS'];

        return response()->json($data, 200);
    }

    public function registerStep3(RegisterStep3Request $request)
    {
        $adminService = new AdminService();

        $validated = $request->validated();

        $user = $adminService->checkRegister($validated['data']);
        $user = $adminService->updateRegisterUser($user, $validated['data']);

        $data = ['step' => $user->confirmed_at ? 'REGISTER_FINISHED' : 'REGISTER_MUST_BE_CONFIRMED'];

        return response()->json($data, 200);
    }

    public function passwordUnknownStepEmail(PasswordUnknownStepEmailRequest $request, AdminService $service)
    {
        $adminService = new AdminService();
        $validated = $request->validated();

        $data = $adminService->checkEmail($validated['data']);

        if ($data['users_count'] == 0) {
            abort(401, 'Login funktioniert mit dieser E-Mail-Adresse nicht.');
        }

        if ($data['step'] == 'LOGIN_ENTER_PASSWORD') {
            $user = User::where('school_id', $data['school_id'])->where('email', $data['email'])->first();
            $service->passwordUnkownSendToken($data);
            $data['step'] = 'PASSWORD_UNKNOWN_ENTER_TOKEN';
        }
        if ($data['step'] == 'LOGIN_SELECT_SCHOOL') {
            $data['step'] = 'PASSWORD_UNKNOWN_SELECT_SCHOOL';
        }

        return response()->json($data, 200);
    }


    public function passwordUnknownStepSchool(AdminPasswordUnknownStepSchoolRequest $request, AdminService $service)
    {
        $validated = $request->validated();
        $data = $service->passwordUnkownSendToken($validated['data']);
        $data['step'] = 'PASSWORD_UNKNOWN_ENTER_TOKEN';
        return response()->json($data, 200);
    }


    public function passwordUnknownStepToken(AdminPasswordUnknownStepTokenRequest $request, AdminService $service)
    {

        $validated = $request->validated();

        $data = $validated['data'];
        $data = $service->passwordUnkownCheckToken($data);

        if ($service->passwordUnkownIfUserIs2FaSendToken($data)) {
            // Ist 2-FA-USER: Code wurde zur 2. E-Mail versandt
            $data['step'] = 'PASSWORD_UNKNOWN_ENTER_TOKEN_2';
        } else {
            $data['step'] = 'PASSWORD_UNKNOWN_ENTER_PASSWORD';
        }

        return response()->json($data, 200);
    }

    public function passwordUnknownStepToken2(AdminPasswordUnknownStepToken2Request $request, AdminService $service)
    {

        $validated = $request->validated();

        $data = $validated['data'];
        $data = $service->passwordUnkownCheckToken($data);
        $data = $service->passwordUnkownCheckToken2($data);
        $data['step'] = 'PASSWORD_UNKNOWN_ENTER_PASSWORD';
        return response()->json($data, 200);
    }



    public function passwordUnknownStepPassword(AdminPasswordUnknownStepPasswordRequest $request, AdminService $service)
    {

        $validated = $request->validated();

        $data = $validated['data'];
        $data = $service->passwordUnkownCheckToken($data);

        if (!$user = User::where('email', $data['email'])->where('school_id', $data['school_id'])->first()) abort(404, "Kein Benutzer gefunden");
        if ($user->is_2fa) $data = $service->passwordUnkownCheckToken2($data);
        $data = $service->passwordUnkownSetPassword($data);
        $data['step'] = 'PASSWORD_UNKNOWN_FINISHED';
        return response()->json($data, 200);
    }

    public function loginStepEmail(LoginStepEmailRequest $request)
    {
        $adminService = new AdminService();
        $validated = $request->validated();

        $data = $adminService->checkEmail($validated['data']);

        if ($data['users_count'] == 0) {
            abort(401, 'Login funktioniert mit dieser E-Mail-Adresse nicht.');
        }

        return response()->json($data, 200);
    }




    public function loginStep2(LoginStep2Request $request)
    // 
    {
        $adminService = new AdminService();
        $validated = $request->validated();

        $data = $adminService->checkLogin($validated['data']);

        $data = $adminService->check2Fa($data);

        if ($data['step'] == 'LOGIN_SUCCESS') {
            $user = $adminService->login($data);
            unset($data['password']);
        }
        return response()->json($data, 200);
    }

    public function loginStep3(LoginStep3Request $request)
    {
        $adminService = new AdminService();
        $validated = $request->validated();

        $data = $adminService->checkLogin($validated['data']);
        $user = $adminService->login2Fa($data);
        unset($data['password']);
        unset($data['token_2_fa']);

        $data = [
            'step' => 'LOGIN_SUCCESS',
            'auth' => true,
            'user' => $user,
        ];

        return response()->json($data, 200);
    }

    public function executeLogout(Request $request)
    {

        if (Auth::check()) {
            Auth::guard('web')->logout();
            session()->invalidate();
            // session()->regenerateToken();
        }

        $data = $this->getConfigData();

        return response()->json($data, 200);
    }

    public function loadRoles(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $roles = DB::table('roles')->orderBy('name')->get();

        return response()->json($roles, 200);
    }

    public function newTeacherStepEmail(AdminNewTeacherStepEmailRequest $request, TeacherListService $service)
    {
        $validated = $request->validated();
        $email = $validated['email'];

        $data = $service->getAllTeachersNotInUsers($email);

        // EMail ist berechtigt, sich als Lehrer anzumelden
        if ($data['step'] == 'NEW_TEACHER_NO_TEACHER') abort(404, "Eine Anmeldung als neue:r Lehrer:in ist mit dieser E-Mail nicht möglich");
        if ($data['step'] == 'NEW_TEACHER_INPUT_CODE')  $service->sendCode($data['school']['id'], $data['email']);

        return response()->json($data, 200);
    }

    public function newTeacherStepSchool(AdminNewTeacherStepSchoolRequest $request, TeacherListService $service)
    {
        $validated = $request->validated();
        $email = $validated['email'];
        $school_id = $validated['school_id'];
        $school = School::findOrFail($school_id);
        $validated['school'] = new SchoolResource($school);

        $service->sendCode($school_id, $email);

        $validated['step'] = 'NEW_TEACHER_INPUT_CODE';
        return response()->json($validated, 200);
    }

    public function newTeacherStepCode(AdminNewTeacherStepCodeRequest $request, TeacherListService $service)
    {

        $validated = $request->validated();
        $email = $validated['email'];
        $school_id = $validated['school_id'];
        $code = $validated['token'];

        if (!$teacher = Teacher::where('school_id', $school_id)->where('email', $email)->first()) abort(404, "Kein passender Lehrer in der Liste gefunden.");

        if (!$service->checkToken($teacher, $code)) {
            // Token hat nicht gestimmt oder ist abgelaufen
            $validated['step'] = 'NEW_TEACHER_TOKEN_WRONG';
        } else {
            // Alles ok, User erzeugen und login
            $user = $service->createUserFromTeacher($validated);
            $user->assignRole('teacher');
            Auth::guard('web')->login($user, true);
            session()->regenerate();
            $validated['step'] = 'NEW_TEACHER_OK';
        }

        return response()->json($validated, 200);
    }
}
