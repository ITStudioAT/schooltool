<?php

namespace App\Services;

use App\Models\QueueTest;
use App\Models\School;
use Illuminate\Support\Facades\File;
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

    public function clearModels()
    {
        //
        QueueTest::query()->delete();
    }

    public function clearDebugbar()
    {
        $debugbarPath = storage_path('debugbar');

        if (File::exists($debugbarPath)) {
            File::cleanDirectory($debugbarPath);  // empty everything inside
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
                $pw = env('SA_PW');
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

        $schools = School::query()->get();

        foreach ($schools as $school) {
            $path = $school->id . '/temp';
            $this->createOrCleanDirectory($path);
            $path =  $school->id . '/excel';
            $this->createOrCleanDirectory($path);
            $path =  $school->id . '/pdf';
            $this->createOrCleanDirectory($path);
        }



        $path = 'images'; // relative to storage/app/public
        if (!Storage::disk('public')->exists($path)) {
            Storage::disk('public')->makeDirectory($path);
        }

        $path = 'images/logos'; // relative to storage/app/public
        if (!Storage::disk('public')->exists($path)) {
            Storage::disk('public')->makeDirectory($path);
        }
    }

    private function createOrCleanDirectory($path)
    {
        if (!Storage::directoryExists($path)) {
            Storage::makeDirectory($path);
        } else {
            $files = Storage::allFiles($path);
            $directories = Storage::allDirectories($path);

            Storage::delete($files); // delete all files
            foreach ($directories as $directory) {
                Storage::deleteDirectory($directory); // delete subfolders
            }
        }
    }
}
