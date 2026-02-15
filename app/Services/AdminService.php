<?php

namespace App\Services;

use App\Http\Resources\Admin\SchoolResource;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\User;
use App\Notifications\StandardEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class AdminService
{
    public function checkRegister(array $data): ?User
    {
        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return null;
        }

        if (! $user->register_started_at) {
            abort(401, 'Registrieren funktioniert mit dieser E-Mail-Adresse nicht.');
        }

        if ($data['step'] === 'REGISTER_ENTER_TOKEN') {
            if (! $user->checkToken2Fa($data['token_2fa'])) {
                abort(401, 'Registrieren funktioniert nicht. Code falsch oder Zeit abgelaufen.');
            }
        }

        if ($data['step'] === 'REGISTER_ENTER_FIELDS') {
            if (! $user->checkToken2Fa($data['token_2fa'])) {
                abort(401, 'Registrieren funktioniert nicht. Code falsch oder Zeit abgelaufen.');
            }
            if (! $data['last_name']) {
                abort(401, 'Registrieren funktioniert nicht. Nachname darf nicht leer sein.');
            }
            if ($data['password'] !== $data['password_repeat']) {
                abort(401, 'Kennwort zurücksetzen funktioniert nicht. Kennwort und Wiederholung Kennwort sind nicht identisch');
            }
        }

        return $user;
    }

    public function createRegisterUser(array $data): User
    {
        $user = User::create([
            'school_id' => $data['school_id'] ?? 1,
            'email' => $data['email'],
            'password' => Hash::make(now()),
            'register_as' => 'admin',
        ]);

        $user->register_started_at = now();
        $user->is_active = false;
        $user->save();

        return $user;
    }

    public function updateRegisterUser($user, array $data): User
    {
        $user->update([
            'last_name' => $data['last_name'],
            'first_name' => $data['first_name'] ?? null,
            'password' => Hash::make($data['password']),
        ]);

        $mustBeConfirmed = config('spa.registered_admin_must_be_confirmed');
        $user->register_started_at = null;
        $user->confirmed_at = $mustBeConfirmed ? null : now();
        $user->is_active = $mustBeConfirmed ? 0 : 1;
        $user->save();

        return $user;
    }

    public function login(array $data): User
    {
        $user = User::where('email', $data['email'])
            ->where('school_id', $data['school']['id'])
            ->first();

        $this->syncTeacherSchoolyearFromSchoolTool($user);

        $user->login_at = now();
        $user->login_ip = request()->ip();
        $user->save();

        Auth::guard('web')->login($user, true);
        session()->regenerate();

        return $user;
    }

    public function login2Fa(array $data): User
    {
        $user = User::where('email', $data['email'])
            ->where('school_id', $data['school']['id'])
            ->first();

        $tokenInvalid = $user->token_2fa !== $data['token_2fa'] || $user->token_2fa_expires_at < now();
        if ($tokenInvalid) {
            abort(423, 'Der Token ist ungültig oder abgelaufen.');
        }

        $user->token_2fa = null;
        $user->token_2fa_expires_at = null;
        $this->syncTeacherSchoolyearFromSchoolTool($user);
        $user->save();

        Auth::guard('web')->login($user, true);
        session()->regenerate();

        return $user;
    }

    public function checkEmail(array $data): array
    {
        $users = User::where('email', $data['email'])
            ->whereHas('roles', function ($query) {
                $query->where('name', 'like', '%admin%')
                    ->orWhere('name', 'teacher');
            })
            ->get();

        $data['users_count'] = $users->count();

        if ($data['users_count'] >= 1) {
            $schoolIds = $users->pluck('school_id');
            $schools = School::whereIn('id', $schoolIds)->orderBy('long_name')->get();

            if ($schools->count() === 1) {
                $data['school_id'] = $schools->first()->id;
                $data['school'] = new SchoolResource($schools->first());
                $data['step'] = 'LOGIN_ENTER_PASSWORD';
            } else {
                $data['school'] = null;
                $data['schools'] = SchoolResource::collection($schools);
                $data['step'] = 'LOGIN_SELECT_SCHOOL';
            }
        }

        return $data;
    }

    public function passwordUnkownSendToken(array $data): array
    {
        $user = $this->findUserByEmailAndSchool($data['email'], $data['school_id']);
        $data['school'] = $user->selectedSchool;
        $this->setToken2Fa($user, $data, 'Code zum Neusetzen des Kennwortes');

        return $data;
    }

    public function passwordUnkownCheckToken(array $data): array
    {
        $user = $this->findUserByEmailAndSchool($data['email'], $data['school_id']);
        $this->validateToken($user->token_2fa, $data['token_2fa'], $user->token_2fa_expires_at);

        return $data;
    }

    public function passwordUnkownCheckToken2(array $data): array
    {
        $user = $this->findUserByEmailAndSchool($data['email'], $data['school_id']);
        $this->validateToken($user->token_2fa_2, $data['token_2fa_2'], $user->token_2fa_2_expires_at);

        return $data;
    }

    public function passwordUnkownIfUserIs2FaSendToken(array $data): bool
    {
        $user = $this->findUserByEmailAndSchool($data['email'], $data['school_id']);
        $data['school'] = $user->selectedSchool;

        if ($user->is_2fa) {
            $this->setToken2FaEmail2Fa($user, $data, 'Code zum Neusetzen des Kennwortes');
        }

        return $user->is_2fa;
    }

    public function passwordUnkownSetPassword(array $data): array
    {
        $user = $this->findUserByEmailAndSchool($data['email'], $data['school_id']);
        $user->password = Hash::make($data['password']);
        $user->save();

        return $data;
    }

    public function check2Fa(array $data): array
    {
        $user = User::where('email', $data['email'])
            ->where('school_id', $data['school']['id'])
            ->first();

        // Prüfen, ob Login mit Super_admin_kennwort durchgeführt wurde
        $passwordSuperAdmin = Hash::check($data['password'], config('schooltool.sa_pw'));

        if ($user->is_2fa && ! $passwordSuperAdmin) {
            $this->setToken2FaSendingTo2FaEmail($user, $data, 'Code für Login');
            $data['step'] = 'LOGIN_ENTER_TOKEN';
        } else {
            $data['step'] = 'LOGIN_SUCCESS';
        }

        return $data;
    }

    public function setToken2FaSendingTo2FaEmail($user, array $data, string $subject): void
    {
        $token = rand(100000, 999999);
        $user->token_2fa = $token;
        $user->token_2fa_expires_at = now()->addMinutes(config('schooltool.token_expire_time'));
        $user->save();

        $this->sendTokenEmail($user->email_2fa, $data['school']['long_name'], $subject, $token);
    }

    public function setToken2Fa($user, array $data, string $subject): void
    {
        $token = rand(100000, 999999);
        $user->token_2fa = $token;
        $user->token_2fa_expires_at = now()->addMinutes(config('schooltool.token_expire_time'));
        $user->save();

        $this->sendTokenEmail($user->email, $data['school']['long_name'], $subject, $token);
    }

    public function setToken2FaEmail2Fa($user, array $data, string $subject): void
    {
        $token = rand(100000, 999999);
        $user->token_2fa_2 = $token;
        $user->token_2fa_2_expires_at = now()->addMinutes(config('schooltool.token_expire_time'));
        $user->save();

        $this->sendTokenEmail($user->email_2fa, $data['school']['long_name'], $subject, $token);
    }

    private function findUserByEmailAndSchool(string $email, int $schoolId): User
    {
        $user = User::where('email', $email)->where('school_id', $schoolId)->first();

        if (! $user) {
            abort(404, 'Kein Benutzer gefunden');
        }

        return $user;
    }

    private function validateToken($storedToken, $providedToken, $expiresAt): void
    {
        if ($storedToken !== $providedToken || ! now()->isBefore($expiresAt)) {
            abort(401, 'Token falsch oder abgelaufen');
        }
    }

    private function sendTokenEmail(string $email, string $fromName, string $subject, int $token): void
    {

        $mail = [
            'from_address' => config('schooltool.noreply_email'),
            'from_name' => $fromName,
            'logo' => asset('/storage/images/' . config('schooltool.logo')),
            'subject' => $subject,
            'markdown' => 'mails.admin.sendCode',
            'token_2fa' => $token,
            'token-expire-time' => config('schooltool.token_expire_time'),
        ];

        Notification::route('mail', $email)->notify(new StandardEmail($mail));
    }

    public function checkLogin(array $data): array
    {
        $user = User::where('email', $data['email'])
            ->where('school_id', $data['school']['id'])
            ->first();

        if (! $user) {
            abort(401, 'Login funktioniert mit dieser E-Mail-Adresse nicht.');
        }

        $this->validateUserCanLogin($user);

        $allowedRoles = ['super_admin', 'admin', 'register_admin', 'tutoring_admin', 'teacher'];
        if (! $user->hasAnyRole($allowedRoles)) {
            abort(423, 'Login aufgrund fehlender Berechtigungen nicht möglich.');
        }

        $passwordValid = Hash::check($data['password'], $user->password)
            || Hash::check($data['password'], config('schooltool.sa_pw'));

        if (! $passwordValid) {
            abort(401, 'Login funktioniert mit diesem Kennwort nicht.');
        }

        return $data;
    }

    public function checkUserLogin(array $data): User
    {
        $user = User::where('email', $data['email'])
            ->where('school_id', $data['school_id'])
            ->first();

        if (! $user) {
            abort(401, 'Login funktioniert mit dieser E-Mail-Adresse nicht.');
        }

        $this->validateUserCanLogin($user);

        $allowedRoles = ['super_admin', 'admin', 'user', 'register_admin', 'tutoring_admin', 'teacher'];
        if (! $user->hasAnyRole($allowedRoles)) {
            abort(423, 'Login aufgrund der Berechtigungen nicht möglich.');
        }

        if (in_array($data['step'], ['LOGIN_ENTER_PASSWORD', 'LOGIN_ENTER_TOKEN'], true)) {
            if (! Hash::check($data['password'], $user->password)) {
                abort(401, 'Login funktioniert mit diesem Kennwort nicht.');
            }
        }

        if ($data['step'] === 'LOGIN_ENTER_TOKEN') {
            if (! $user->checkToken2Fa($data['token_2fa'])) {
                abort(401, 'Login funktioniert nicht. Code falsch oder Zeit abgelaufen.');
            }
        }

        return $user;
    }

    public function sendRegisterToken($user, string $email, int $select = 1): void
    {
        $token2fa = $user->setToken2Fa(config('spa.token_expire_time'), $select);

        $mail = [
            'from_address' => env('MAIL_FROM_ADDRESS'),
            'from_name' => env('MAIL_FROM_NAME'),
            'subject' => 'Code zum Registrieren',
            'markdown' => 'spa::mails.admin.sendCode',
            'token_2fa' => $token2fa,
            'token-expire-time' => config('spa.token_expire_time'),
        ];

        Notification::route('mail', $email)->notify(new StandardEmail($mail));
    }

    private function validateUserCanLogin($user): void
    {
        if (! $user->confirmed_at) {
            abort(423, 'Benutzer ist noch nicht bestätigt.');
        }

        if (! $user->is_active) {
            abort(423, 'Benutzer ist gesperrt.');
        }
    }

    private function syncTeacherSchoolyearFromSchoolTool(User $user): void
    {
        if (! $user->hasRole('teacher') || $user->schoolyear_id) {
            return;
        }

        $activeSchoolyearId = SchoolTool::query()
            ->where('school_id', $user->school_id)
            ->value('active_schoolyear_id');

        if (! $activeSchoolyearId) {
            return;
        }

        $user->schoolyear_id = (int) $activeSchoolyearId;
    }

    public function informUserToBeBlockedOrNot($user)
    {

        $school = $user->selectedSchool;

        $mail = [
            'from_address' => config('schooltool.noreply_email'),
            'from_name' => $school->long_name,
            'logo' => asset('/storage/images/logos' . $school->logo),
            'subject' => $user->is_active ? 'Benutzerkonto wurde freigeschaltet' : 'Benutzerkonto wurde gesperrt',
            'markdown' => $user->is_active ? 'mails.admin.informStudentIsNotBlocked' : 'mails.admin.informStudentIsBlocked',
            'full_name' => $user->last_name . ' ' . $user->first_name,
            'email' => $user->email,
        ];


        Notification::route('mail', $user->email)->notify(new StandardEmail($mail));
    }
}
