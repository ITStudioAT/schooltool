<?php

namespace App\Services;

use App\Enums\TwoFaResult;
use App\Models\RegisterDateBooking;
use App\Models\Role;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TutoringOffer;
use App\Models\User;
use App\Notifications\StandardEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

class UserService
{
    private const PROTECTED_SUPER_ADMIN_EMAIL = 'kron@naturwelt.at';

    public function delete(int $meId, array $data): void
    {
        foreach ($data as $id) {
            $user = User::findOrFail($id);
            if (! $user->hasDependencies() && $user->id !== $meId) {
                $user->syncRoles([]);
                $user->delete();
            }
        }
    }

    public function store(int $schoolId, array $data): User
    {
        $emailExists = User::where('school_id', $schoolId)
            ->where('email', $data['email'])
            ->exists();

        if ($emailExists) {
            abort(409, 'E-Mail existiert bereits');
        }

        $userRoles = $data['roles'] ?? [];
        unset($data['roles']);

        $data['school_id'] = $schoolId;
        $data['password'] = Hash::make(now());

        $user = User::create($data);
        $user->email_verified_at = now();
        $user->is_active = true;
        $user->confirmed_at = now();
        $user->save();

        foreach ($userRoles as $role) {
            if ($role['name'] === 'super_admin') {
                continue;
            }
            if ($role['checked'] ?? false) {
                $user->assignRole($role['name']);
            }
        }

        return $user;
    }

    public function update(array $data, ?User $actingUser = null): User
    {
        $user = User::findOrFail($data['id']);
        $isProtectedSuperAdminUser = $this->isProtectedSuperAdminUser($user, $data);
        $canManageSuperAdminRole = $this->canManageSuperAdminRole($actingUser);

        $emailTaken = User::whereNot('id', $user->id)
            ->where('school_id', $user->school_id)
            ->where('email', $data['email'])
            ->exists();

        if ($emailTaken) {
            abort(409, 'E-Mail existiert bereits');
        }

        $userRoles = $data['roles'] ?? [];
        unset($data['roles']);

        $user->update($data);

        foreach ($userRoles as $role) {
            if ($role['name'] === 'super_admin') {
                if ($isProtectedSuperAdminUser) {
                    $user->assignRole('super_admin');
                    continue;
                }
                if (! $canManageSuperAdminRole) {
                    continue;
                }
                if ($role['checked']) {
                    $user->assignRole('super_admin');
                } else {
                    $user->removeRole('super_admin');
                }
                continue;
            }

            if ($role['checked']) {
                $user->assignRole($role['name']);
            } else {
                if ($role['name'] === 'register_user' && RegisterDateBooking::where('user_id', $user->id)->exists()) {
                    continue;
                }
                if ($role['name'] === 'tutoring_user' && TutoringOffer::where('user_id', $user->id)->exists()) {
                    continue;
                }
                $user->removeRole($role['name']);
            }
        }

        if ($isProtectedSuperAdminUser) {
            $user->assignRole('super_admin');
        }

        return $user;
    }


    public function setSchoolyearToNull(Schoolyear $schoolyear): bool
    {
        User::where('school_id', $schoolyear->school_id)
            ->where('schoolyear_id', $schoolyear->id)
            ->update([
                'schoolyear_id' => null,
                'register_id' => null,
            ]);

        return true;
    }

    public function setNewSchoolyear($user, Schoolyear $schoolyear): void
    {
        $user->schoolyear_id = $schoolyear->id;
        $user->register_id = null;
        $user->save();
    }

    public function allUsersInfos(): array
    {
        return [
            ['title' => 'Gesamt', 'content' => User::count()],
            ['title' => 'Aktiv', 'content' => User::where('is_active', 1)->count()],
            ['title' => 'Mit 2-FA-Authentifizierung', 'content' => User::where('is_2fa', 1)->count()],
            ['title' => 'Mit bestätigter E-Mail', 'content' => User::whereNotNull('email_verified_at')->count()],
            ['title' => 'Bestätigte', 'content' => User::whereNotNull('confirmed_at')->count()],
            ['title' => 'Nicht bestätigte', 'content' => User::whereNull('confirmed_at')->count()],
        ];
    }

    public function sendVerificationEmail(array|int $parIds): void
    {
        $ids = is_array($parIds) ? $parIds : [$parIds];

        foreach ($ids as $id) {
            $user = User::findOrFail($id);
            $user->sendVerificationEmail();
        }
    }

    public function confirm(array|int $parIds): void
    {
        $ids = is_array($parIds) ? $parIds : [$parIds];

        $unconfirmedCount = User::whereIn('id', $ids)
            ->whereNull('confirmed_at')
            ->count();

        if ($unconfirmedCount === 0) {
            abort(422, 'Alle Benutzer sind bereits bestätigt!');
        }

        foreach ($ids as $id) {
            $user = User::findOrFail($id);
            $user->sendVerificationEmail();
        }
    }

    public function setNewUserRoles(array $userIds, array $roleIds, ?User $actingUser = null): void
    {
        $canManageSuperAdminRole = $this->canManageSuperAdminRole($actingUser);

        foreach ($roleIds as &$roleId) {
            $role = Role::findOrFail($roleId['id']);
            $roleId['name'] = $role->name;
        }
        unset($roleId);

        foreach ($userIds as $id) {
            $user = User::findOrFail($id);
            $isProtectedSuperAdminUser = $this->isProtectedSuperAdminUser($user);

            foreach ($roleIds as $roleId) {
                if ($roleId['name'] === 'super_admin') {
                    if ($isProtectedSuperAdminUser) {
                        $user->assignRole('super_admin');
                        continue;
                    }
                    if (! $canManageSuperAdminRole) {
                        continue;
                    }
                }

                if ($roleId['role_check'] === 1) {
                    $user->assignRole($roleId['name']);
                } elseif ($roleId['role_check'] === 2) {
                    $user->removeRole($roleId['name']);
                }
            }

            if ($isProtectedSuperAdminUser) {
                $user->assignRole('super_admin');
            }
        }
    }

    private function canManageSuperAdminRole(?User $actingUser): bool
    {
        return (bool) $actingUser?->hasRole('super_admin');
    }

    private function isProtectedSuperAdminUser(User $user, array $incomingData = []): bool
    {
        return $this->isProtectedSuperAdminEmail($user->email)
            || $this->isProtectedSuperAdminEmail($incomingData['email'] ?? null);
    }

    private function isProtectedSuperAdminEmail(?string $email): bool
    {
        return mb_strtolower(trim((string) $email)) === self::PROTECTED_SUPER_ADMIN_EMAIL;
    }

    public function check2Fa($user, bool $is2fa, ?string $email2fa): TwoFaResult
    {
        if (! $is2fa) {
            return TwoFaResult::TWO_FA_DELETE;
        }

        if ($email2fa === $user->email) {
            return TwoFaResult::TWO_FA_EMAIL_AND_2FA_EMAIL_MUST_NOT_BE_EQUAL;
        }

        if ($user->email_2fa === $email2fa && $user->email_2fa_verified_at) {
            return TwoFaResult::TWO_FA_OK;
        }

        if ($user->email_2fa === $email2fa && ! $user->email_2fa_verified_at) {
            return TwoFaResult::TWO_FA_EMAIL_MUST_BE_VERIFIED;
        }

        if (! $email2fa) {
            return TwoFaResult::TWO_FA_ERROR;
        }

        return TwoFaResult::TWO_FA_EMAIL_IS_NEW;
    }

    public function check2FaStep2(TwoFaResult $result, $user, ?string $email2fa): void
    {
        match ($result) {
            TwoFaResult::TWO_FA_DELETE => User::where('email', $user->email)->update([
                'is_2fa' => false,
                'email_2fa' => null,
                'email_2fa_verified_at' => null,
            ]),
            TwoFaResult::TWO_FA_OK => User::where('email', $user->email)->update([
                'is_2fa' => true,
                'email_2fa' => $user->email_2fa,
            ]),
            TwoFaResult::TWO_FA_EMAIL_MUST_BE_VERIFIED,
            TwoFaResult::TWO_FA_EMAIL_IS_NEW => $this->send2FaCode($user, $email2fa),
            default => null,
        };
    }

    public function update2Fa($user, string $email2fa): TwoFaResult
    {
        User::where('email', $user->email)->update([
            'is_2fa' => true,
            'email_2fa' => $email2fa,
            'email_2fa_verified_at' => now(),
        ]);

        return TwoFaResult::TWO_FA_SET;
    }

    private function send2FaCode($user, string $email2fa): void
    {
        $token2fa = $user->setToken2Fa(config('spa.token_expire_time'), 1);

        $mail = [
            'from_address' => env('MAIL_FROM_ADDRESS'),
            'from_name' => env('MAIL_FROM_NAME'),
            'logo' => asset('/storage/images/' . config('schooltool.logo')),
            'subject' => 'Code zum Bestätigen der E-Mail',
            'markdown' => 'mails.admin.sendCode',
            'token_2fa' => $token2fa,
            'token-expire-time' => config('spa.token_expire_time'),
        ];

        Notification::route('mail', $email2fa)->notify(new StandardEmail($mail));
    }

    public function isEmailInSchoolAvailable(int $schoolId, string $email): bool
    {
        return User::where('school_id', $schoolId)->where('email', $email)->doesntExist();
    }

    public function sendEmailVerification($user, string $email): void
    {
        $this->sendCode($user, 'E-Mail-Adresse bestätigen', $email);
    }

    public function checkEmailVerification($user, string $token): bool
    {
        return $user->token_2fa === $token
            && $user->token_2fa_expires_at
            && $user->token_2fa_expires_at->isFuture();
    }

    public function setPasswordOrSendCode($user, array $data): array
    {
        $status = $data['status'] ?? null;
        $isConfirmingPassword = in_array($status, ['CONFIRM_PASSWORD', 'RE_CONFIRM_PASSWORD'], true);

        if ($isConfirmingPassword) {
            $tokenValid = $user->token_2fa === $data['token_2fa']
                && $user->token_2fa_expires_at
                && $user->token_2fa_expires_at->isFuture();

            if ($tokenValid) {
                $user->password = Hash::make($data['password']);
                $user->save();
                Auth::guard('web')->login($user, true);
                session()->regenerate();
                $data['status'] = 'OK';
            } else {
                $this->sendCode($user, 'Code für neues Kennwort', $user->email);
                unset($data['token_2fa']);
                $data['status'] = 'RE_CONFIRM_PASSWORD';
            }
        } else {
            $this->sendCode($user, 'Code für neues Kennwort', $user->email);
            $data['status'] = 'CONFIRM_PASSWORD';
        }

        return $data;
    }

    public function sendCode($user, string $subject, string $email): void
    {
        $token2fa = random_int(100000, 999999);
        $user->token_2fa = $token2fa;
        $user->token_2fa_expires_at = now()->addMinutes(config('schooltool.token_expire_time'));
        $user->save();

        $school = School::findOrFail($user->school_id);

        $mail = [
            'from_address' => config('schooltool.noreply_email'),
            'from_name' => $school->long_name,
            'logo' => asset('/storage/images/' . $school->logo),
            'subject' => $subject,
            'markdown' => 'mails.homepage.sendCode',
            'token_2fa' => $token2fa,
            'token-expire-time' => config('schooltool.token_expire_time'),
        ];

        Notification::route('mail', $email)->notify(new StandardEmail($mail));
    }

    public function deleteTutoringUsers(array $data): void
    {
        foreach ($data as $id) {
            $user = User::findOrFail($id);
            $canDelete = ! $user->hasDependencies()
                && $user->roles->count() === 1
                && $user->hasRole('tutoring_user');

            if ($canDelete) {
                $user->syncRoles([]);
                $user->delete();
            }
        }
    }

    public function confirmTutoringUsers(array $data): void
    {
        $tutoringService = new TutoringService();

        foreach ($data as $id) {
            $user = User::findOrFail($id);
            $canConfirm = $user->hasRole('tutoring_user')
                && $user->email_verified_at
                && ! $user->confirmed_at;

            if ($canConfirm) {
                $user->confirmed_at = now();
                $user->save();
                $tutoringService->sendConfirmationEmail($user);
            }
        }
    }

    public function cleanTutoringUsers(int $schoolId): void
    {
        User::bySchoolAndRole($schoolId, 'tutoring_user')
            ->whereNull('email_verified_at')
            ->whereHas('roles', function ($query) {
                $query->havingRaw('COUNT(*) = 1');
            }, '=', 1)
            ->get()
            ->each(function ($user) {
                DB::table('queue_tests')->where('user_id', $user->id)->delete();
                $user->removeRole('tutoring_user');
                $user->delete();
            });
    }

    public static function logout(): void
    {
        if (Auth::check()) {
            Auth::guard('web')->logout();
            session()->invalidate();
            session()->regenerateToken();
        }
    }
}
