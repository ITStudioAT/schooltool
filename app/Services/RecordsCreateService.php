<?php

namespace App\Services;

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use Spatie\Permission\Models\Role;

class RecordsCreateService
{
    public function initRecords(): void
    {
        $school = $this->firstOrCreateSchool();
        $this->firstOrCreateSchoolyear($school);
        $this->checkOrCreateAdminRoles();
        $this->checkOrCreateLicences();

        foreach (School::all() as $school) {
            $this->checkOrCreateAdmins($school);
            $this->checkOrCreateSchoolyears($school);
            $this->checkOrCreateSchoolTool($school);
        }
    }

    private function checkOrCreateLicences(): void
    {
        Licence::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'Anmeldetool',
                'long_name' => 'Tool zum Verwalten von Anmeldungen',
                'price_per_year' => 200,
            ]
        );

        Licence::firstOrCreate(
            ['id' => 2],
            [
                'name' => 'Nachhilfetool',
                'long_name' => 'Tool zum Verwalten von Nachhilfe',
                'price_per_year' => 200,
            ]
        );
    }

    private function firstOrCreateSchool(): School
    {
        $school = School::first();

        if ($school) {
            return $school;
        }

        $school = School::create([
            'long_name' => 'Christian-Doppler-Gymnasium Salzburg',
            'short_name' => 'CDGym',
            'logo' => 'logo_1.png',
            'is_selectable' => 1,
        ]);

        $this->copyDefaultLogo();

        return $school;
    }

    private function copyDefaultLogo(): void
    {
        $sourceLogo = storage_path('app/public/images/logo.png');
        $destLogo = storage_path('app/public/images/logos/logo_1.png');

        if (! file_exists($sourceLogo)) {
            return;
        }

        $destDir = dirname($destLogo);
        if (! is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        copy($sourceLogo, $destLogo);
    }

    private function checkOrCreateSchoolyears(School $school): void
    {
        $schoolyears = config('schooltool.schoolyears', []);

        foreach ($schoolyears as $schoolyear) {
            Schoolyear::firstOrCreate(
                [
                    'school_id' => $school->id,
                    'concerns' => $schoolyear['concerns'],
                ],
                [
                    'name' => $schoolyear['name'],
                    'from' => $schoolyear['from'],
                    'until' => $schoolyear['to'],
                    'sem_2_start' => $schoolyear['sem_2_start'],
                ]
            );
        }
    }

    private function firstOrCreateSchoolyear(School $school): Schoolyear
    {
        return Schoolyear::firstOrCreate(
            ['school_id' => $school->id],
            [
                'name' => 'Schuljahr 2025/26',
                'from' => '2025-09-08',
                'until' => '2026-07-10',
                'sem_2_start' => '2026-02-16',
                'concerns' => '2025/26',
            ]
        );
    }

    private function firstOrCreateSchoolTool(School $school): SchoolTool
    {
        return SchoolTool::firstOrCreate(
            ['school_id' => $school->id],
            []
        );
    }

    private function checkOrCreateAdminRoles(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    private function checkOrCreateAdmins(School $school): void
    {
        $schoolyear = Schoolyear::where('school_id', $school->id)->first();
        $this->checkOrCreateAdmin($school, $schoolyear, 'kron@naturwelt.at', 'super_admin');
    }

    private function checkOrCreateAdmin(School $school, ?Schoolyear $schoolyear, string $email, string $role): User
    {
        $user = User::where('school_id', $school->id)->where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'school_id' => $school->id,
                'schoolyear_id' => $schoolyear?->id,
                'email' => $email,
                'password' => env('SA_PW'),
                'first_name' => 'Günther',
                'last_name' => 'Kron',
            ]);

            $user->email_verified_at = now();
            $user->confirmed_at = now();
            $user->is_active = 1;
            $user->save();
        }

        $user->assignRole($role);

        return $user;
    }

    public function checkOrCreateSchoolTool(School $school): SchoolTool
    {
        return SchoolTool::firstOrCreate(
            ['school_id' => $school->id],
            [
                'tutoring_student_must_be_confirmed' => false,
                'tutoring_confirmer_email' => '',
            ]
        );
    }
}
