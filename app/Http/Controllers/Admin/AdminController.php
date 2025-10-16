<?php

namespace App\Http\Controllers\Admin;

use Composer\InstalledVersions;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Admin\LoginStep1Request;
use App\Http\Requests\Admin\LoginStep2Request;
use App\Http\Requests\Admin\LoginStep3Request;
use App\Http\Requests\Admin\PasswordUnknownStep1Request;
use App\Http\Requests\Admin\PasswordUnknownStep2Request;
use App\Http\Requests\Admin\PasswordUnknownStep3Request;
use App\Http\Requests\Admin\PasswordUnknownStep4Request;
use App\Http\Requests\Admin\RegisterStep1Request;
use App\Http\Requests\Admin\RegisterStep2Request;
use App\Http\Requests\Admin\RegisterStep3Request;
use App\Http\Resources\Admin\UserResource;
use App\Services\AdminNavigationService;
use App\Services\AdminService;

class AdminController extends Controller
{
    public function config(Request $request)
    {
        $navigationService = new AdminNavigationService();


        $data = [
            'logo' => config('spa.logo', ''),
            'copyright' => config('spa.copyright', ''),
            'title' => config('spa.title', 'Fresh Laravel'),
            'company' => config('spa.company', 'ItStudio.at'),
            'version' => InstalledVersions::getPrettyVersion('itstudioat/spa'),
            'register_admin_allowed' => config('spa.register_admin_allowed', false),
            'timeout' => config('spa.timeout', 3000),
            'is_auth' => auth()->check(),
            'user' => auth()->check() ? new UserResource(auth()->user()) : null,
            'menu' => $navigationService->dashboardMenu(),
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

    public function loginStep1(LoginStep1Request $request)
    {
        $adminService = new AdminService();
        $validated = $request->validated();

        $user = $adminService->checkUserLogin($validated['data']);
        $data = ['step' => 'LOGIN_ENTER_PASSWORD'];

        return response()->json($data, 200);
    }

    public function loginStep2(LoginStep2Request $request)
    {
        $adminService = new AdminService();
        $validated = $request->validated();

        $user = $adminService->checkUserLogin($validated['data']);

        if ($user->is_2fa) {
            // Bei Benutzer ist 2-Faktoren-Authentifizierung aktiviert => wir brauchen einen Code
            $adminService->continueLoginFor2FaUser($user);
            $data = ['step' => 'LOGIN_ENTER_TOKEN'];

            return response()->json($data, 200);
        } else {
            // Keine 2-Faktoren-Authentifizierung ==> Login fertig
            auth('web')->login($user);
            session()->regenerate();
            $data = [
                'step' => 'LOGIN_SUCCESS',
                'auth' => true,
                'user' => $user,
            ];
            $user->rememberLogin();

            return response()->json($data, 200);
        }
    }

    public function loginStep3(LoginStep3Request $request)
    {
        $adminService = new AdminService();
        $validated = $request->validated();
        $user = $adminService->checkUserLogin($validated['data']);
        auth()->login($user);

        session()->regenerate();

        $data = [
            'step' => 'LOGIN_SUCCESS',
            'auth' => true,
            'user' => $user,
        ];
        $user->rememberLogin();

        return response()->json($data, 200);
    }

    public function executeLogout(Request $request)
    {

        if (! auth()->check()) {
            abort(400, 'Sie sind gar nicht eingeloggt.');
        }

        auth('web')->logout();
        session()->invalidate();
        session()->regenerateToken();

        return response()->json(['message' => 'Logout successful'], 200);
    }
}
