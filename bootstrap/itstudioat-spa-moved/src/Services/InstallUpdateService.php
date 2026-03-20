<?php

namespace App\Services;

use Spatie\Permission\Models\Role;

class InstallUpdateService
{
    public function createRoles($roles)
    {
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
    }
}
