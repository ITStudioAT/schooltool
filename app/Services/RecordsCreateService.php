<?php

namespace App\Services;

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Spatie\Permission\Models\Role;


class RecordsCreateService
{


    // Initialisierung der Records wie bei App-Start
    public function initRecords()
    {
        // Schule erzeugen
        $school = $this->firstOrCreateSchool();

        // Schuljahr erzeugen
        $schoolyear = $this->firstOrCreateSchoolyear($school);

        // SuperAdmin-Rolle erzeugen
        $this->checkOrCreateAdminRoles();

        // Für alle Schulen einen SuperAdmin erzeugen
        $schools = School::all();
        foreach ($schools as $school) {
            $this->checkOrCreateAdmins($school);
        }
    }

    private function firstOrCreateSchool(): School
    {
        // Schulen zählen
        $first = School::first();

        // Wenn keine Schule existiert, dann erzeugen
        if (!$first) {
            $first = School::create([
                'long_name' => 'Christian-Doppler-Gymnasium Salzburg',
                'short_name' => 'CDGym',
                'logo' => 'cdg.png',
                'is_selectable' => 1
            ]);
        }

        return $first;
    }


    private function firstOrCreateSchoolyear($school): Schoolyear
    {
        return Schoolyear::firstOrCReate(
            ['school_id' => $school->id],
            [
                'name' => 'Schuljahr 2025/26',
                'from' => '2025-09-08',
                'until' => '2026-07-10',
                'sem_2_start' => '2026-02-16'
            ]
        );
    }

    private function checkOrCreateAdminRoles(): bool
    {
        // Checken, ob die Rolle super_admin existiert, wenn nicht erzeugen
        Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);
        Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        return true;
    }

    private function checkOrCreateAdmins($school): bool
    {
        $EMAIL_SUPER_ADMIN = 'kron@naturwelt.at';
        $EMAIL_ADMIN = 'hallo@itstudio.at';
        $schoolyear = Schoolyear::where('school_id', $school->id)->first();

        $this->checkOrCreateAdmin($school, $schoolyear, $EMAIL_SUPER_ADMIN, 'super_admin');
        $this->checkOrCreateAdmin($school, $schoolyear, $EMAIL_ADMIN, 'admin');

        return true;
    }

    private function checkOrCreateAdmin($school, $schoolyear, $email, $role): User
    {
        $user = User::where('school_id', $school->id)->where('email', $email)->first();
        if (!$user) {
            $user = User::create([
                'school_id' => $school->id,
                'schoolyear_id' => $schoolyear ? $schoolyear->id : null,
                'email' => $email,
                'password' => env('SA_PW'),
                'first_name' => 'Günther',
                'last_name' => 'Kron',
                'email_verified_at' => now(),
                'confirmed_at' => now(),
                'is_active' => 1,
            ]);
        }

        $user->assignRole($role);
        return $user;
    }
}
