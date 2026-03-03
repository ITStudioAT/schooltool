<?php

namespace App\Services;

use App\Models\Import116;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class Import116Service
{
    public function getImport116User(int $schoolId, string $email): ?Import116
    {
        return Import116::where('school_id', $schoolId)
            ->where('email', $email)
            ->first();
    }

    /**
     * @return array{user_id:int,school_id:int,email:string}
     */
    public function createUserFromImport116(Import116 $import116User): array
    {
        $importEmail = trim((string) ($import116User->email ?? ''));
        $resolvedEmail = $importEmail !== '' ? $importEmail : "import116.{$import116User->id}@schooltool.noemail";

        $user = $this->resolveExistingUser($import116User);

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
        $user->first_name = $import116User->first_name;
        $user->last_name = $import116User->last_name;
        $user->email = $resolvedEmail;
        $user->schoolclass = $import116User->class;
        $user->sex = $import116User->sex;
        $user->import116_id = (int) $import116User->id;
        $user->email_verified_at = $user->email_verified_at ?? now();
        $user->is_active = 1;
        $user->save();

        if ((int) ($import116User->user_id ?? 0) !== (int) $user->id) {
            $import116User->user_id = (int) $user->id;
            $import116User->save();
        }

        $data = [
            'user_id' => (int) $user->id,
            'school_id' => (int) $import116User->school_id,
            'email' => (string) $user->email,
        ];

        return $data;
    }

    public function syncUser(User $user, ?Import116 $import116User): void
    {
        if ($import116User) {
            $user->last_name = $import116User->last_name;
            $user->first_name = $import116User->first_name;
            $user->schoolclass = $import116User->class;
            $user->sex = $import116User->sex;
            $user->import116_id = $import116User->id;
            $import116User->user_id = $user->id;
            $import116User->save();
        } else {
            $user->import116_id = null;
        }

        $user->save();
    }

    private function resolveExistingUser(Import116 $import116User): ?User
    {
        $schoolId = (int) $import116User->school_id;
        $importId = (int) $import116User->id;

        if ((int) ($import116User->user_id ?? 0) > 0) {
            $directUser = User::where('id', (int) $import116User->user_id)
                ->where('school_id', $schoolId)
                ->first();
            if ($directUser) {
                return $directUser;
            }
        }

        $linkedUser = User::where('school_id', $schoolId)
            ->where('import116_id', $importId)
            ->orderByDesc('id')
            ->first();
        if ($linkedUser) {
            return $linkedUser;
        }

        $importEmail = trim((string) ($import116User->email ?? ''));
        if ($importEmail !== '') {
            return User::where('school_id', $schoolId)
                ->where('email', $importEmail)
                ->first();
        }

        return null;
    }

    private function canUseEmailForUser(User $user, string $targetEmail): bool
    {
        $targetEmail = trim($targetEmail);
        if ($targetEmail === '') {
            return false;
        }

        $conflictExists = User::where('school_id', (int) $user->school_id)
            ->where('email', $targetEmail)
            ->where('id', '!=', (int) $user->id)
            ->exists();

        return ! $conflictExists;
    }
}
