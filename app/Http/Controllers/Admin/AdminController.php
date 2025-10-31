<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\AdminPasswordUnknownStepSchoolRequest;
use App\Http\Requests\Admin\AdminPasswordUnkownStepPasswordRequest;
use App\Http\Requests\Admin\AdminPasswordUnkownStepTokenRequest;
use App\Http\Requests\Admin\LoginStep2Request;
use App\Http\Requests\Admin\LoginStep3Request;
use App\Http\Requests\Admin\LoginStepEmailRequest;
use App\Http\Requests\Admin\PasswordUnknownStep1Request;
use App\Http\Requests\Admin\PasswordUnknownStep2Request;
use App\Http\Requests\Admin\PasswordUnknownStep3Request;
use App\Http\Requests\Admin\PasswordUnknownStep4Request;
use App\Http\Requests\Admin\RegisterStep1Request;
use App\Http\Requests\Admin\RegisterStep2Request;
use App\Http\Requests\Admin\RegisterStep3Request;
use App\Http\Resources\Admin\RegisterResource;
use App\Http\Resources\Admin\SchoolResource;
use App\Http\Resources\Admin\SchoolyearResource;
use App\Http\Resources\Admin\UserResource;
use App\Http\Resources\Admin\UserWithRoleResource;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Services\AdminNavigationService;
use App\Services\AdminService;
use App\Services\LicenceService;
use App\Traits\HasRoleTrait;
use Composer\InstalledVersions;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    use HasRoleTrait;
    public function config(Request $request, LicenceService $licenceService)
    {
        $navigationService = new AdminNavigationService();

        // Laden aller auswählbaren Schulen
        $schools = School::selectables()->get();

        $viaRemember = Auth::viaRemember();

        $user = Auth::check() ? Auth::user() : null;

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
            'selected_register' =>  $user && $user->selectedRegister ? new RegisterResource($user->selectedRegister) : null,
            'menu' => $user ? $navigationService->dashboardMenu() : [],
            'roles' => $user ? $user->getRoleNames() : [],
        ];

        return response()->json($data, 200);
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
        $adminService->sendRegisterToken(1, $user, $validated['data']['email']);
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


    public function passwordUnknownStepSchool(AdminPasswordUnknownStepSchoolRequest $request, AdminService $service)
    {
        $validated = $request->validated();
        $data = $service->passwordUnkownSendToken($validated['data']);

        return response()->json($data, 200);
    }

    public function passwordUnknownStepToken(AdminPasswordUnkownStepTokenRequest $request, AdminService $service)
    {
        $validated = $request->validated();
        $data = $service->passwordUnkownCheckToken($validated['data']);

        return response()->json($data, 200);
    }

    public function passwordUnknownStepPassword(AdminPasswordUnkownStepPasswordRequest $request, AdminService $service)
    {
        $validated = $request->validated();

        $data = $service->passwordUnkownSetPassword($validated['data']);

        return response()->json($validated["data"], 200);
    }

    public function passwordUnknownStep1(PasswordUnknownStep1Request $request)
    {
        $adminService = new AdminService();
        $validated = $request->validated();

        $user = $adminService->checkPasswordUnknown($validated['data']);
        // Token zusenden
        $adminService->sendPasswordResetToken(1, $user, $user->email);
        $data = ['step' => 'PASSWORD_UNKNOWN_ENTER_TOKEN'];

        return response()->json($data, 200);
    }

    public function passwordUnknownStep2(PasswordUnknownStep2Request $request)
    {
        $adminService = new AdminService();
        $validated = $request->validated();

        $user = $adminService->checkPasswordUnknown($validated['data']);
        if (! $user->is_2fa) {
            $data = ['step' => 'PASSWORD_UNKNOWN_SUCCESS'];

            return response()->json($data, 200);
        }

        // User hat 2-Faktoren-Authentifizierung, es existiert jedoch keine 2. E-Mail
        if (! $user->email_2fa) {
            abort(401, 'Kennwort zurücksetzen funktioniert nicht. Sie haben keine weitere E-Mail-Adresse.');
        }

        // Token 2 zusenden
        $adminService->sendPasswordResetToken(2, $user, $user->email_2fa);

        $data = ['step' => 'PASSWORD_UNKNOWN_ENTER_TOKEN_2'];

        return response()->json($data, 200);
    }

    public function passwordUnknownStep3(PasswordUnknownStep3Request $request)
    {
        $adminService = new AdminService();
        $validated = $request->validated();

        $user = $adminService->checkPasswordUnknown($validated['data']);
        if (! $user->is_2fa) {
            abort(401, 'Kennwort zurücksetzen funktioniert nicht. 2-Faktoren-Authentifizierung ist nicht aktiviert.');
        }

        $data = ['step' => 'PASSWORD_UNKNOWN_ENTER_PASSWORD'];

        return response()->json($data, 200);
    }

    public function passwordUnknownStep4(PasswordUnknownStep4Request $request)
    {
        $adminService = new AdminService();
        $validated = $request->validated();

        $user = $adminService->checkPasswordUnknown($validated['data']);
        $user->setPassword($validated['data']['password']);

        $data = ['step' => 'PASSWORD_UNKNOWN_FINISHED'];

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
            session()->regenerateToken();
        }

        return response()->json(['message' => 'Logout successful'], 200);
    }

    public function loadRoles(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $roles = DB::table('roles')->orderBy('name')->get();

        return response()->json($roles, 200);
    }
}
