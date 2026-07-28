<?php

namespace App\Services;

use App\Models\School;
use App\Models\SchoolTool;
use App\Models\User;
use App\Notifications\StandardEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class TutoringService
{
    public function checkEmail(array $data): array
    {
        $user = User::where('school_id', $data['school_id'])
            ->where('email', $data['email'])
            ->first();

        if (! $user) {
            $data['status'] = 'NEW_USER';
        } else {
            $data['status'] = 'USER_FOUND';
            $data['user_id'] = $user->id;
        }

        return $data;
    }

    public function createUser(array $data): User
    {
        $data = $this->checkEmail($data);

        if ($data['status'] !== 'NEW_USER') {
            abort(409, 'Der Benutzer existiert bereits.');
        }

        unset($data['status']);
        $data['password'] = Hash::make(now());
        $data['tutoring_filter'] = [
            'schools' => [],
            'only_boys' => false,
            'only_girls' => false,
            'only_in_my_school' => true,
        ];

        return User::create($data);
    }

    public function assignTutoringRole(int $userId): User
    {
        $user = User::findOrFail($userId);
        $user->assignRole('tutoring_user');

        return $user;
    }

    public function sendCodeToUser($user): void
    {
        $token2fa = random_int(100000, 999999);
        $user->token_2fa = $token2fa;
        $user->token_2fa_expires_at = now()->addMinutes(config('schooltool.token_expire_time'));
        $user->save();

        $school = School::findOrFail($user->school_id);

        $mail = [
            'from_address' => config('schooltool.noreply_email'),
            'from_name' => $school->long_name,
            'logo' => asset('/storage/images/logos/'.$school->logo),
            'subject' => 'E-Mail bestätigen',
            'markdown' => 'mails.homepage.sendCode',
            'token_2fa' => $token2fa,
            'token-expire-time' => config('schooltool.token_expire_time'),
        ];

        Notification::route('mail', EmailAliasResolver::resolveConfigured($user->email))->notify(new StandardEmail($mail));
    }

    public function confirmEmail(array $data): array
    {
        $user = User::findOrFail($data['user_id']);

        $tokenValid = $user->consumeToken2Fa($data['token_2fa']);

        if ($tokenValid) {
            $user->email_verified_at = now();
            $user->save();
            $data['status'] = 'EMAIL_VERIFIED';
            $data = $this->checkUserConfirmation($data);
        } else {
            $this->sendCodeToUser($user);
            $data['status'] = 'CONFIRM_EMAIL_AGAIN';
        }

        return $data;
    }

    public function checkUserConfirmation(array $data): array
    {
        $user = User::findOrFail($data['user_id']);

        if (! $user->confirmed_at) {
            $schoolTool = SchoolTool::where('school_id', $user->school_id)->firstOrFail();

            if ($schoolTool->tutoring_student_must_be_confirmed) {
                $data['status'] = 'USER_MUST_BE_CONFIRMED';

                if ($schoolTool->tutoring_confirmer_email) {
                    $this->sendConfirmerNotification($user, $schoolTool->tutoring_confirmer_email);
                }
            }
        } else {
            $user->confirmed_at = now();
            $user->save();
        }

        return $data;
    }

    private function sendConfirmerNotification($user, string $confirmerEmail): void
    {
        $token = Str::uuid()->toString();
        $user->token_2fa_2 = $token;
        $user->token_2fa_2_expires_at = now()->addMinutes(30);
        $user->save();

        $school = School::findOrFail($user->school_id);

        $mail = [
            'from_address' => config('schooltool.noreply_email'),
            'from_name' => $school->long_name,
            'logo' => asset('/storage/images/logos/'.$school->logo),
            'subject' => 'Benutzer Nachhilfetool bestätigen',
            'markdown' => 'mails.admin.confirmTutoringUser',
            'full_name' => "{$user->last_name} {$user->first_name}",
            'email' => $user->email,
            'confirmation_url' => URL::temporarySignedRoute('homepage.tutoring.confirm-user', now()->addMinutes(30), [
                'user_id' => $user->id,
                'token' => $token,
            ]),
            'refuse_url' => URL::temporarySignedRoute('homepage.tutoring.refuse-user', now()->addMinutes(30), [
                'user_id' => $user->id,
                'token' => $token,
            ]),
        ];

        Notification::route('mail', EmailAliasResolver::resolveConfigured($confirmerEmail))->notify(new StandardEmail($mail));
    }

    public function confirmUser(int $userId, string $uuid): bool
    {
        $user = User::findOrFail($userId);

        if ($user->confirmed_at
            || ! $user->consumeToken2Fa2($uuid)) {
            return false;
        }

        $user->confirmed_at = now();
        $user->save();

        $this->sendConfirmationEmail($user);

        return true;
    }

    public function refuseUser(int $userId, string $uuid): bool
    {
        $user = User::findOrFail($userId);

        if ($user->hasDependencies() || ! $user->consumeToken2Fa2($uuid)) {
            return false;
        }

        $userService = new UserService;
        $userService->deleteTutoringUsers([$userId], (int) $user->school_id);

        return true;
    }

    public function sendConfirmationEmail($user): void
    {
        $school = School::findOrFail($user->school_id);

        $mail = [
            'from_address' => config('schooltool.noreply_email'),
            'from_name' => $school->long_name,
            'logo' => asset('/storage/images/logos/'.$school->logo),
            'subject' => 'Nachhilfetool bestätigt',
            'markdown' => 'mails.admin.informTutoringUserIsConfirmed',
            'full_name' => "{$user->last_name} {$user->first_name}",
            'email' => $user->email,
            'login_url' => url('/homepage/tutoring_overview?school='.$school->short_name),
        ];

        Notification::route('mail', EmailAliasResolver::resolveConfigured($user->email))->notify(new StandardEmail($mail));
    }

    public function checkLoginRequirement(array $data): array
    {
        $user = User::findOrFail($data['user_id']);

        if (! $user->is_active) {
            $data['status'] = 'USER_INACTIVE';

            return $data;
        }

        if (! $user->email_verified_at) {
            $this->sendCodeToUser($user);
            $data['status'] = 'CONFIRM_EMAIL';

            return $data;
        }

        return $this->checkUserConfirmation($data);
    }

    public function unknownPassword(array $data): array
    {
        $user = User::findOrFail($data['user_id']);
        $school = School::findOrFail($user->school_id);

        $token2fa = random_int(100000, 999999);
        $user->token_2fa = $token2fa;
        $user->token_2fa_expires_at = now()->addMinutes(config('schooltool.token_expire_time'));
        $user->save();

        $mail = [
            'from_address' => config('schooltool.noreply_email'),
            'from_name' => $school->long_name,
            'logo' => asset('/storage/images/logos/'.$school->logo),
            'subject' => 'Login mit Code',
            'markdown' => 'mails.homepage.sendCode',
            'token_2fa' => $token2fa,
            'token-expire-time' => config('schooltool.token_expire_time'),
        ];

        Notification::route('mail', EmailAliasResolver::resolveConfigured($user->email))->notify(new StandardEmail($mail));

        $data['status'] = 'LOGIN_WITH_TOKEN';

        return $data;
    }

    public function loginWithToken(array $data): array
    {
        $user = User::findOrFail($data['user_id']);

        $tokenValid = $user->consumeToken2Fa($data['token_2fa']);

        if ($tokenValid) {
            $this->performLogin($user);
            $data['status'] = 'LOGGED_IN';
        } else {
            $data = $this->unknownPassword($data);
            $data['status'] = 'RETRY_LOGIN_WITH_TOKEN';
        }

        return $data;
    }

    public function loginWithPassword(array $data): array
    {
        $user = User::findOrFail($data['user_id']);

        $passwordValid = Hash::check($data['password'], $user->password);

        if ($passwordValid) {
            if ($user->hasEnabledTwoFactorAuthentication()) {
                abort(423, 'Dieses Benutzerkonto ist mit Zwei-Faktor-Authentifizierung geschützt. Bitte verwenden Sie die Admin-Anmeldung.');
            }

            $this->performLogin($user);
            $data['status'] = 'LOGGED_IN';
        } else {
            $data['status'] = 'RETRY_PASSWORD';
        }

        return $data;
    }

    private function performLogin($user): void
    {
        $user->login_at = now();
        $user->login_ip = request()->ip();
        $user->save();

        Auth::guard('web')->login($user, true);
        session()->regenerate();
    }
}
