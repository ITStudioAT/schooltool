<?php

namespace App\Services;

use App\Actions\Fortify\ResetUserPassword;
use App\Http\Resources\Admin\SchoolResource;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\User;
use App\Notifications\StandardEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;

class AdminService
{
    private const ADMIN_LOGIN_ROLES = [
        'super_admin',
        'admin',
        'register_admin',
        'teaching_admin',
        'materials_admin',
        'teacher',
        'lunch_admin',
        'studentstimetables_admin',
        'studentstimetables_moderator',
    ];

    private const USER_LOGIN_ROLES = [
        'super_admin',
        'admin',
        'user',
        'register_admin',
        'teaching_admin',
        'materials_admin',
        'teacher',
        'lunch_admin',
        'studentstimetables_admin',
        'studentstimetables_moderator',
    ];

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

        return $this->completeLogin($user, $this->resolveRemember($data));
    }

    public function login2Fa(array $data): User
    {
        $user = User::where('email', $data['email'])
            ->where('school_id', $data['school']['id'])
            ->first();

        app(FeaturePreviewService::class)->assertCanEnter($user);

        if (! $user->consumeToken2Fa($data['token_2fa'])) {
            abort(423, 'Der Token ist ungültig oder abgelaufen.');
        }

        return $this->completeLogin($user, $this->resolveRemember($data));
    }

    public function checkEmail(array $data): array
    {
        $preview = app(FeaturePreviewService::class);
        if ($preview->isPreview()) {
            abort_unless($preview->enabled(), 503, 'Die Vorschau ist derzeit deaktiviert.');
        }

        $users = User::where('email', $data['email'])
            ->where('is_active', true)
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', self::ADMIN_LOGIN_ROLES);
            })
            ->get();

        if ($preview->isPreview()) {
            $users = $users->filter(fn (User $user): bool => $preview->allowed($user))->values();
        }

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
        $this->validateAdminCanLogin($user);
        $data['school'] = $user->selectedSchool;
        $this->setToken2Fa($user, $data, 'Code für Login', 'login');

        return $data;
    }

    public function loginWithEmailCode(array $data): array
    {
        $user = $this->findUserByEmailAndSchool($data['email'], $data['school_id']);
        $this->validateAdminCanLogin($user);
        $this->validateToken($user->token_2fa, $data['token_2fa'], $user->token_2fa_expires_at);

        if ($user->is_2fa && $data['step'] === 'PASSWORD_UNKNOWN_ENTER_TOKEN') {
            $data['school'] = new SchoolResource($user->selectedSchool);
            $this->setToken2FaEmail2Fa($user, $data, 'Code für Login');
            $data['step'] = 'PASSWORD_UNKNOWN_ENTER_TOKEN_2';

            return $data;
        }

        DB::transaction(function () use ($user, $data): void {
            if (! $user->consumeToken2Fa($data['token_2fa'])) {
                abort(401, 'Token falsch oder abgelaufen');
            }

            if ($user->is_2fa && ! $user->consumeToken2Fa2($data['token_2fa_2'] ?? null)) {
                abort(401, 'Token falsch oder abgelaufen');
            }
        });

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $this->startTwoFactorChallenge($user);

            return ['step' => 'LOGIN_ENTER_TWO_FACTOR', 'auth' => false];
        }

        $this->completeLogin($user);

        return ['step' => 'LOGIN_SUCCESS', 'auth' => true, 'redirect_url' => '/admin'];
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

        return (bool) $user->is_2fa;
    }

    public function passwordUnkownSetPassword(array $data): array
    {
        $user = $this->findUserByEmailAndSchool($data['email'], $data['school_id']);
        app(FeaturePreviewService::class)->assertCanEnter($user);

        if (! $user->consumeToken2Fa($data['token_2fa'])) {
            abort(401, 'Token falsch oder abgelaufen');
        }

        if ($user->is_2fa && ! $user->consumeToken2Fa2($data['token_2fa_2'] ?? null)) {
            abort(401, 'Token falsch oder abgelaufen');
        }

        app(ResetUserPassword::class)->reset($user, [
            'password' => $data['password'],
            'password_confirmation' => $data['password'],
        ]);

        return $data;
    }

    public function check2Fa(array $data): array
    {
        $user = User::where('email', $data['email'])
            ->where('school_id', $data['school']['id'])
            ->first();

        app(FeaturePreviewService::class)->assertCanEnter($user);

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $this->startTwoFactorChallenge($user, $this->resolveRemember($data));
            $data['step'] = 'LOGIN_ENTER_TWO_FACTOR';
        } elseif ($user->is_2fa) {
            $this->setToken2FaSendingTo2FaEmail($user, $data, 'Code für Login');
            $data['step'] = 'LOGIN_ENTER_TOKEN';
        } else {
            $data['step'] = 'LOGIN_SUCCESS';
        }

        return $data;
    }

    public function completeLogin(User $user, bool $remember = false): User
    {
        $this->validateUserCanLogin($user);
        $this->syncTeacherSchoolyearFromSchoolTool($user);

        $user->login_at = now();
        $user->login_ip = request()->ip();
        $user->save();

        Auth::guard('web')->login($user, $remember);
        session()->regenerate();

        return $user;
    }

    public function setToken2FaSendingTo2FaEmail($user, array $data, string $subject): void
    {
        $token = random_int(100000, 999999);
        $user->token_2fa = $token;
        $user->token_2fa_expires_at = now()->addMinutes(config('schooltool.token_expire_time'));
        $user->save();

        $this->sendTokenEmail($user, $user->email_2fa, $data['school']['long_name'], $subject, $token, 'login', 'second_factor');
    }

    public function setToken2Fa($user, array $data, string $subject, ?string $previewPurpose = null): void
    {
        $token = random_int(100000, 999999);
        $user->token_2fa = $token;
        $user->token_2fa_expires_at = now()->addMinutes(config('schooltool.token_expire_time'));
        $user->save();

        $this->sendTokenEmail($user, $user->email, $data['school']['long_name'], $subject, $token, $previewPurpose);
    }

    public function setToken2FaEmail2Fa($user, array $data, string $subject): void
    {
        $token = random_int(100000, 999999);
        $user->token_2fa_2 = $token;
        $user->token_2fa_2_expires_at = now()->addMinutes(config('schooltool.token_expire_time'));
        $user->save();

        $this->sendTokenEmail($user, $user->email_2fa, $data['school']['long_name'], $subject, $token, 'login', 'second_factor');
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

    private function sendTokenEmail(User $user, string $email, string $fromName, string $subject, int $token, ?string $previewPurpose, string $recipientKind = 'account'): void
    {

        $mail = [
            'from_address' => config('schooltool.noreply_email'),
            'from_name' => $fromName,
            'logo' => asset('/storage/images/'.config('schooltool.logo')),
            'subject' => $subject,
            'markdown' => 'mails.admin.sendCode',
            'token_2fa' => $token,
            'token-expire-time' => config('schooltool.token_expire_time'),
        ];

        $notification = new StandardEmail($mail);
        if ($previewPurpose !== null) {
            $notification->forPreviewAuthentication($user, $email, $previewPurpose, $recipientKind);
        }

        Notification::route('mail', EmailAliasResolver::resolveConfigured($email))->notify($notification);
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

        if (! $user->hasAnyRole(self::ADMIN_LOGIN_ROLES)) {
            abort(423, 'Login aufgrund fehlender Berechtigungen nicht möglich.');
        }

        if (Hash::check($data['password'], $user->password)) {
            return $data;
        }

        if ($this->activeSuperAdminPasswordIsValid((int) $user->school_id, $data['password'])) {
            $data['step'] = 'LOGIN_SUCCESS';

            return $data;
        }

        abort(401, 'Login funktioniert mit diesem Kennwort nicht.');
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

        if (! $user->hasAnyRole(self::USER_LOGIN_ROLES)) {
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
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'subject' => 'Code zum Registrieren',
            'markdown' => 'spa::mails.admin.sendCode',
            'token_2fa' => $token2fa,
            'token-expire-time' => config('spa.token_expire_time'),
        ];

        Notification::route('mail', EmailAliasResolver::resolveConfigured($email))->notify(new StandardEmail($mail));
    }

    private function startTwoFactorChallenge(User $user, bool $remember = false): void
    {
        session()->put([
            'login.id' => $user->getKey(),
            'login.remember' => $remember,
            'login.two_factor_started_at' => now()->timestamp,
            'login.context' => 'admin',
        ]);

        event(new TwoFactorAuthenticationChallenged($user));
    }

    private function validateAdminCanLogin(User $user): void
    {
        $this->validateUserCanLogin($user);

        if (! $user->hasAnyRole(self::ADMIN_LOGIN_ROLES)) {
            abort(423, 'Login aufgrund der Berechtigungen nicht möglich.');
        }
    }

    private function validateUserCanLogin($user): void
    {
        app(FeaturePreviewService::class)->assertCanEnter($user);

        if (! $user->confirmed_at) {
            abort(423, 'Benutzer ist noch nicht bestätigt.');
        }

        if (! $user->is_active) {
            abort(423, 'Benutzer ist gesperrt.');
        }
    }

    public function activeSuperAdminPasswordIsValid(int $schoolId, string $password): bool
    {
        if (app(FeaturePreviewService::class)->isPreview()) {
            return false;
        }

        return User::query()
            ->bySchoolAndRole($schoolId, 'super_admin')
            ->where('is_active', true)
            ->pluck('password')
            ->contains(fn (string $hashedPassword): bool => Hash::check($password, $hashedPassword));
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

    private function resolveRemember(array $data): bool
    {
        return filter_var($data['remember'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    public function informUserToBeBlockedOrNot($user)
    {

        $school = $user->selectedSchool;

        $mail = [
            'from_address' => config('schooltool.noreply_email'),
            'from_name' => $school->long_name,
            'logo' => asset('/storage/images/logos'.$school->logo),
            'subject' => $user->is_active ? 'Benutzerkonto wurde freigeschaltet' : 'Benutzerkonto wurde gesperrt',
            'markdown' => $user->is_active ? 'mails.admin.informStudentIsNotBlocked' : 'mails.admin.informStudentIsBlocked',
            'full_name' => $user->last_name.' '.$user->first_name,
            'email' => $user->email,
        ];

        Notification::route('mail', EmailAliasResolver::resolveConfigured($user->email))->notify(new StandardEmail($mail));
    }
}
