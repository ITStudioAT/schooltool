<?php

namespace App\Services;

use App\Models\Import116;
use App\Models\SchoolTool;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Sabberworm\CSS\Property\Import;

class StudentService
{

    public function isEmailValidForSchool($email, $school_id): User|null
    {

        // Beispiel: Überprüfen, ob die E-Mail-Adresse in der Tabelle "students" mit der Schule verknüpft ist
        $user = User::where('email', $email)
            ->where('school_id', $school_id)
            ->first();


        return $user;
    }

    public function  isStudentInImport116($email, $school_id, $schoolyear_id)
    {

        $student = Import116::where('email', $email)
            ->where('school_id', $school_id)
            ->where('schoolyear_id', $schoolyear_id)
            ->first();

        return $student;
    }

    public function createUserFromImport116($import116User)
    {
        $user_data = [
            'school_id' => $import116User->school_id,
            'schoolyear_id' => $import116User->schoolyear_id,
            'email' => $import116User->email,
            'password' => Hash::make(now()),
            'last_name' => $import116User->last_name,
            'first_name' => $import116User->first_name,
            'phone' => $import116User->phone_1 ?? $import116User->phone_2,
            'is_active' => 1,
            'sex' => $import116User->sex,
            'import116_id' => $import116User->id,
        ];

        $user = User::create($user_data);
        $user->email_verified_at = now();
        $user->save();
        $user->assignRole('student');
        return $user;
    }

    public function isTokenValid($user, $token)
    {
        if (!$user->token_2fa || !$user->token_2fa_expires_at) {
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
        return Hash::check($password, $user->password) || Hash::check($password, config('schooltool.sa_pw'));
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
     *
     * @param User $user
     * @return User
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
        if (!$import116) {
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
}
