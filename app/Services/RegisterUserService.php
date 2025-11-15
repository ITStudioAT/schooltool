<?php

namespace App\Services;

use App\Models\Register;
use App\Models\User;


class RegisterUserService
{

    public function deleteRegisterUsers($school_id): int
    {

        $count = 0;
        $users = User::where('school_id', $school_id)->get();

        foreach ($users as $user) {
            // User hat mehr als eine Rolle => continue
            if ($user->roles->count() > 1) continue;

            // User hat mindestens eine Buchung => continue
            if ($user->registerDateBookings->count() > 0) continue;

            // User hat nicht die Rolle register_user => continue
            if (! $user->hasRole('register_user')) continue;

            // User entfernen
            $count++;
            $user->removeRole('register_user');
            $user->delete();
        }

        return $count;
    }
}
