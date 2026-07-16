<?php

namespace App\Services;

use App\Models\Import116;
use App\Models\SchoolTool;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class StudentService
{
    public function isEmailValidForSchool(string $email, int $school_id): ?User
    {

        $user = User::where('email', $email)
            ->where('school_id', $school_id)
            ->first();

        return $user;
    }

    public function isStudentInImport116(string $email, int $school_id, int $schoolyear_id): ?Import116
    {

        $student = Import116::where('email', $email)
            ->where('school_id', $school_id)
            ->where('schoolyear_id', $schoolyear_id)
            ->first();

        return $student;
    }

    public function createUserFromImport116(Import116 $import116User): User
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
        $user->import116_id = (int) $import116User->id;
        $user->email_verified_at = $user->email_verified_at ?? now();
        $user->save();

        if ((int) ($import116User->user_id ?? 0) !== (int) $user->id) {
            $import116User->user_id = (int) $user->id;
            $import116User->save();
        }

        if (! $user->hasRole('student')) {
            $user->assignRole('student');
        }

        return $user;
    }

    public function isTokenValid($user, $token)
    {
        if (! $user->token_2fa || ! $user->token_2fa_expires_at) {
            return false;
        }

        if ($user->token_2fa !== $token) {
            return false;
        }

        if ($user->token_2fa_expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function isPasswordValid($user, $password)
    {
        return Hash::check($password, $user->password);
    }

    public function performLogin($user): void
    {
        // Synchronize user data from Import116 before login
        $this->syncUserDataFromImport116($user);

        // Synchronize schoolyear_id with active schoolyear from SchoolTool
        $schoolTool = SchoolTool::where('school_id', $user->school_id)->first();
        if ($schoolTool && $schoolTool->active_schoolyear_id && $user->schoolyear_id !== $schoolTool->active_schoolyear_id) {
            $user->schoolyear_id = $schoolTool->active_schoolyear_id;
        }

        $user->login_at = now();
        $user->login_ip = request()->ip();
        $user->save();

        Auth::guard('web')->login($user, true);
        session()->regenerate();
    }

    /**
     * Synchronize user data from Import116 to User
     * Updates user information if corresponding Import116 record exists
     */
    public function syncUserDataFromImport116(User $user): User
    {
        // Find Import116 record by email, school_id and schoolyear_id
        $import116Query = Import116::where('email', $user->email)
            ->where('school_id', $user->school_id);

        if ($user->schoolyear_id) {
            $import116Query->where('schoolyear_id', $user->schoolyear_id);
        }

        $import116 = $import116Query->first();

        // If no Import116 record found, return user unchanged
        if (! $import116) {
            return $user;
        }

        // Track if any changes were made
        $hasChanges = false;

        // Synchronize basic fields
        if ($import116->first_name && $user->first_name !== $import116->first_name) {
            $user->first_name = $import116->first_name;
            $hasChanges = true;
        }

        if ($import116->last_name && $user->last_name !== $import116->last_name) {
            $user->last_name = $import116->last_name;
            $hasChanges = true;
        }

        // Synchronize phone (prefer phone_1, fallback to phone_2)
        $import116Phone = $import116->phone_1 ?? $import116->phone_2;
        if ($import116Phone && $user->phone !== $import116Phone) {
            $user->phone = $import116Phone;
            $hasChanges = true;
        }

        // Synchronize sex
        if ($import116->sex && $user->sex !== $import116->sex) {
            $user->sex = $import116->sex;
            $hasChanges = true;
        }

        // Synchronize class
        if ($import116->class && $user->schoolclass !== $import116->class) {
            $user->schoolclass = $import116->class;
            $hasChanges = true;
        }

        // Synchronize schoolyear_id
        if ($import116->schoolyear_id && $user->schoolyear_id !== $import116->schoolyear_id) {
            $user->schoolyear_id = $import116->schoolyear_id;
            $hasChanges = true;
        }

        // Update import116_id if not set
        if ($user->import116_id !== $import116->id) {
            $user->import116_id = $import116->id;
            $hasChanges = true;
        }

        // Save only if changes were made
        if ($hasChanges) {
            $user->save();
        }

        return $user;
    }

    private function resolveExistingImportUser(Import116 $import116User): ?User
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
