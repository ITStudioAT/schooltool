<?php

namespace App\Services;

use App\Models\User;

class RegisterUserService
{
    public function deleteRegisterUsers(int $school_id, int $register_id): int
    {
        $count = 0;
        $users = User::where('school_id', $school_id)
            ->with([
                'roles',
                'registerDateBookings' => fn ($q) => $q->where('register_id', $register_id),
            ])
            ->get();

        foreach ($users as $user) {
            // User hat nicht die Rolle register_user => continue
            if (! $user->hasRole('register_user')) {
                continue;
            }

            // User hat mindestens eine Buchung in diesem Register => continue
            if ($user->registerDateBookings->count() > 0) {
                continue;
            }

            $count++;

            if ($user->roles->count() === 1) {
                // Nur register_user Rolle => Rolle entfernen + User löschen
                $user->removeRole('register_user');
                $user->delete();
            } else {
                // Weitere Rollen vorhanden => nur register_user Rolle entfernen
                $user->removeRole('register_user');
            }
        }

        return $count;
    }
}
