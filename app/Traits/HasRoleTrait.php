<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait HasRoleTrait
{
    /**
     * @param array|string $par_roles
     * @return \App\Models\User|false
     */
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

        return $user;
    }
}
