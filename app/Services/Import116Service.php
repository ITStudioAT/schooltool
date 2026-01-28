<?php

namespace App\Services;

use App\Models\Import116;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class Import116Service
{

    public function getImport116User($schoolId, $email)
    {
        return Import116::where('school_id', $schoolId)
            ->where('email', $email)
            ->first();
    }

    public function createUserFromImport116($import116User)
    {
        $user_data = [
            'school_id' => $import116User->school_id,
            'first_name' => $import116User->first_name,
            'last_name' => $import116User->last_name,
            'email' => $import116User->email,
            'schoolclass' => $import116User->class,
            'sex' => $import116User->sex,
            'password' => Hash::make(now()),
        ];
        $user = User::create($user_data);
        $user->email_verified_at = now();
        $user->is_active = 1;
        $user->save();

        $data = [
            'user_id' => $user->id,
            'school_id' => $import116User->school_id,
            'email' => $import116User->email,
        ];

        return $data;
    }

    public function syncUser($user, $import116User)
    {
        if ($import116User) {
            // $import116User existiert, User-Daten synchronisieren
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
}
