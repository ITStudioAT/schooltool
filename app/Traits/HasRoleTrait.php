<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

trait HasRoleTrait
{
    public function userHasRole($par_roles)
    {
        if (! is_array($par_roles)) {
            $roles[] = $par_roles;
        } else {
            $roles = $par_roles;
        }

        if (! Auth::check()) {
            return false;
        }
        if (! $user = Auth::user()) {
            return false;
        }

        // Wenn super_admin in der Konfiguration gesetzt ist, füge ihn zu den erforderlichen Rollen hinzu
        $roles[] = 'super_admin';

        if (! $user->hasAnyRole($roles)) {
            return false;
        }


        $user = User::find($user->id);
        return $user;
    }

    public function userHasAtLeastOneRole()
    {

        if (! Auth::check()) {
            return false;
        }
        if (! $user = Auth::user()) {
            return false;
        }

        if (! $user->roles()->exists()) {
            return false;
        }

        $user = User::find($user->id);

        return $user;
    }
}
