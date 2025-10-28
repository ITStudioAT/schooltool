<?php

namespace App\Services;

use App\Models\School;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class InstallUpdateService
{
    public function createRoles($roles)
    {
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function checkSuperAdmins()
    {
        // Super-Admins must exists for each school
        $schools = School::all();

        foreach ($schools as $school) {
            $users = $school->users()->whereHas('roles', function ($q) {
                $q->where('name', 'super_admin');
            })->count();
            if ($users === 0) {
                // Create one super-admin for this school
                $pw = env('SA_PW', 'ChangeMe123!');
                $user = $school->users()->create([
                    'last_name' => 'Kron',
                    'first_name' => 'Günther',
                    'email' => 'kron@naturwelt.at',
                    'password' => $pw,
                ]);
                $user->assignRole('super_admin');
            }
        }
    }

    public function findOrCreateFolders()
    {

        $path = 'temp';
        info($path);
        info(!Storage::directoryExists($path));
        if (!Storage::directoryExists($path)) {
            Storage::makeDirectory($path);
        }
    }
}
