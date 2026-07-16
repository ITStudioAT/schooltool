<?php

namespace App\Services;

use App\Models\Import116;
use App\Models\SchoolTool;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class StudentsTimetablesStudentService
{
    public const ROLE_NAME = 'studentstimetables_user';

    public function userForSchool(string $email, int $schoolId): ?User
    {
        return User::query()
            ->where('email', $email)
            ->where('school_id', $schoolId)
            ->first();
    }

    public function importStudentForSchoolyear(string $email, int $schoolId, int $schoolyearId): ?Import116
    {
        return Import116::query()
            ->where('email', $email)
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->first();
    }

    public function createOrUpdateUserFromImport116(Import116 $import116User): User
    {
        $importEmail = trim((string) ($import116User->email ?? ''));
        $resolvedEmail = $importEmail !== '' ? $importEmail : "import116.{$import116User->id}@schooltool.noemail";

        $user = $this->resolveExistingImportUser($import116User);

        if (! $user) {
            $user = new User;
            $user->password = Hash::make(now());
        }

        if ($importEmail !== '' && $this->canUseEmailForUser($user, $importEmail)) {
            $resolvedEmail = $importEmail;
        } elseif (trim((string) $user->email) !== '') {
            $resolvedEmail = trim((string) $user->email);
        }

        $user->school_id = (int) $import116User->school_id;
        $user->schoolyear_id = $import116User->schoolyear_id ? (int) $import116User->schoolyear_id : null;
        $user->email = $resolvedEmail;
        $user->last_name = $import116User->last_name;
        $user->first_name = $import116User->first_name;
        $user->phone = $import116User->phone_1 ?? $import116User->phone_2;
        $user->is_active = 1;
        $user->sex = $import116User->sex;
        $user->schoolclass = $import116User->class;
        $user->import116_id = (int) $import116User->id;
        $user->email_verified_at = $user->email_verified_at ?? now();
        $user->save();

        if ((int) ($import116User->user_id ?? 0) !== (int) $user->id) {
            $import116User->user_id = (int) $user->id;
            $import116User->save();
        }

        $this->assignRole($user);

        return $user;
    }

    public function assignRole(User $user): void
    {
        Role::firstOrCreate([
            'name' => self::ROLE_NAME,
            'guard_name' => 'web',
        ]);

        if (! $user->hasRole(self::ROLE_NAME)) {
            $user->assignRole(self::ROLE_NAME);
        }
    }

    public function tokenIsValid(User $user, string $token): bool
    {
        if (! $user->token_2fa || ! $user->token_2fa_expires_at) {
            return false;
        }

        if ($user->token_2fa !== $token) {
            return false;
        }

        return ! $user->token_2fa_expires_at->isPast();
    }

    public function passwordIsValid(User $user, string $password): bool
    {
        return Hash::check($password, $user->password);
    }

    public function performLogin(User $user): void
    {
        $this->syncUserDataFromImport116($user);

        $schoolTool = SchoolTool::query()
            ->where('school_id', $user->school_id)
            ->first();

        if ($schoolTool && $schoolTool->active_schoolyear_id && (int) $user->schoolyear_id !== (int) $schoolTool->active_schoolyear_id) {
            $user->schoolyear_id = (int) $schoolTool->active_schoolyear_id;
        }

        $user->login_at = now();
        $user->login_ip = request()->ip();
        $user->save();

        Auth::guard('web')->login($user, true);
        session()->regenerate();
    }

    public function syncUserDataFromImport116(User $user): User
    {
        $import116Query = Import116::query()
            ->where('email', $user->email)
            ->where('school_id', $user->school_id);

        if ($user->schoolyear_id) {
            $import116Query->where('schoolyear_id', $user->schoolyear_id);
        }

        $import116 = $import116Query->first();

        if (! $import116) {
            return $user;
        }

        $user->forceFill([
            'first_name' => $import116->first_name ?: $user->first_name,
            'last_name' => $import116->last_name ?: $user->last_name,
            'phone' => $import116->phone_1 ?? $import116->phone_2 ?? $user->phone,
            'sex' => $import116->sex ?: $user->sex,
            'schoolclass' => $import116->class ?: $user->schoolclass,
            'schoolyear_id' => $import116->schoolyear_id ?: $user->schoolyear_id,
            'import116_id' => $import116->id,
        ])->save();

        if ((int) ($import116->user_id ?? 0) !== (int) $user->id) {
            $import116->user_id = (int) $user->id;
            $import116->save();
        }

        return $user;
    }

    private function resolveExistingImportUser(Import116 $import116User): ?User
    {
        $schoolId = (int) $import116User->school_id;
        $importId = (int) $import116User->id;

        if ((int) ($import116User->user_id ?? 0) > 0) {
            $directUser = User::query()
                ->where('id', (int) $import116User->user_id)
                ->where('school_id', $schoolId)
                ->first();

            if ($directUser) {
                return $directUser;
            }
        }

        $linkedUser = User::query()
            ->where('school_id', $schoolId)
            ->where('import116_id', $importId)
            ->orderByDesc('id')
            ->first();

        if ($linkedUser) {
            return $linkedUser;
        }

        $importEmail = trim((string) ($import116User->email ?? ''));

        if ($importEmail === '') {
            return null;
        }

        return User::query()
            ->where('school_id', $schoolId)
            ->where('email', $importEmail)
            ->first();
    }

    private function canUseEmailForUser(User $user, string $targetEmail): bool
    {
        $targetEmail = trim($targetEmail);

        if ($targetEmail === '') {
            return false;
        }

        return ! User::query()
            ->where('school_id', (int) $user->school_id)
            ->where('email', $targetEmail)
            ->where('id', '!=', (int) $user->id)
            ->exists();
    }
}
