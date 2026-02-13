<?php

namespace App\Services;

use App\Models\Import116;
use App\Models\User;
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
        if (!$user->login_code || !$user->login_code_expires_at) {
            return false;
        }

        if ($user->login_code !== $token) {
            return false;
        }

        if ($user->login_code_expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function isPasswordValid($user, $password)
    {
        return Hash::check($password, $user->password);
    }
}
