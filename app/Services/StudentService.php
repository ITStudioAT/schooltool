<?php

namespace App\Services;

use App\Models\User;


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

    public function  existsEmailInImport116() {}
}
