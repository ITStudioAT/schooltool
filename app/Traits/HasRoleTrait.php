<?php

namespace App\Traits;

use App\Models\User;
use App\Services\AccessScopeService;
use Illuminate\Support\Facades\Auth;

trait HasRoleTrait
{
    /**
     * @param  array<int, string>|string|null  $parRoles
     */
    public function userHasRole(array|string|null $parRoles): User|false
    {
        $roles = app(AccessScopeService::class)->resolveRoleNames($parRoles);

        if (! Auth::check()) {
            return false;
        }
        if (! $user = Auth::user()) {
            return false;
        }

        // Wenn super_admin in der Konfiguration gesetzt ist, füge ihn zu den erforderlichen Rollen hinzu
        $roles[] = 'super_admin';
        $roles = array_values(array_unique($roles));

        if (! $user->hasAnyRole($roles)) {
            return false;
        }

        return $user;
    }

    public function userHasAtLeastOneRole(): User|false
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
