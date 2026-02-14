<?php

namespace Database\Seeders;

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class E2eSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::query()->create([
            'short_name' => 'E2E',
            'long_name' => 'E2E School',
            'email' => 'e2e-school@example.test',
            'logo' => null,
            'is_selectable' => true,
        ]);

        $schoolyear = Schoolyear::query()->create([
            'school_id' => $school->id,
            'name' => '2025/2026',
            'is_active' => true,
        ]);

        $licence = Licence::query()->firstOrCreate(
            ['name' => 'Lehrertool'],
            [
                'long_name' => 'Lehrertool',
                'price_per_year' => 0,
                'is_selectable' => true,
            ],
        );

        $school->licences()->syncWithoutDetaching([
            $licence->id => ['valid_until' => now()->addYear()->toDateString()],
        ]);

        SchoolTool::query()->updateOrCreate(
            ['school_id' => $school->id],
            [
                'active_schoolyear_id' => $schoolyear->id,
                'tutoring_student_must_be_confirmed' => false,
                'tutoring_confirmer_email' => null,
                'tutoring_max_offers_per_student' => 0,
            ],
        );

        $studentRole = Role::query()->firstOrCreate([
            'name' => 'student',
            'guard_name' => 'web',
        ]);

        $user = User::query()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'email' => 'e2e.student@example.test',
            'password' => Hash::make('password123'),
            'first_name' => 'E2E',
            'last_name' => 'Student',
            'schoolclass' => '1A',
        ]);

        $user->email_verified_at = now();
        $user->confirmed_at = now();
        $user->is_active = true;
        $user->save();
        $user->assignRole($studentRole);
    }
}
