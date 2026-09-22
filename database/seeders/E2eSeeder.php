<?php

namespace Database\Seeders;

use App\Models\Licence;
use App\Models\Register;
use App\Models\RegisterDate;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseDate;
use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingCourseWork;
use App\Models\TeachingHoliday;
use App\Models\TeachingSchema;
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
        $registerLicence = Licence::query()->firstOrCreate(
            ['name' => 'Anmeldetool'],
            [
                'long_name' => 'Anmeldetool',
                'price_per_year' => 0,
                'is_selectable' => true,
            ],
        );

        $school->licences()->syncWithoutDetaching([
            $licence->id => ['valid_until' => now()->addYear()->toDateString()],
            $registerLicence->id => ['valid_until' => now()->addYear()->toDateString()],
        ]);

        SchoolTool::query()->updateOrCreate(
            ['school_id' => $school->id],
            [
                'active_schoolyear_id' => $schoolyear->id,
                'register_visible_admin' => true,
                'register_visible_user' => true,
                'teaching_visible_admin' => true,
                'teaching_visible_user' => true,
            ],
        );

        $studentRole = Role::query()->firstOrCreate([
            'name' => 'student',
            'guard_name' => 'web',
        ]);
        $teacherRole = Role::query()->firstOrCreate([
            'name' => 'teacher',
            'guard_name' => 'web',
        ]);
        $adminRole = Role::query()->firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);
        $registerUserRole = Role::query()->firstOrCreate([
            'name' => 'register_user',
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

        $peer = User::query()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'email' => 'e2e.peer@example.test',
            'password' => Hash::make('password123'),
            'first_name' => 'E2E',
            'last_name' => 'Peer',
            'schoolclass' => '1A',
        ]);
        $peer->email_verified_at = now();
        $peer->confirmed_at = now();
        $peer->is_active = true;
        $peer->save();
        $peer->assignRole($studentRole);

        $passwordFlowStudent = User::query()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'email' => 'e2e.student.password@example.test',
            'password' => Hash::make('password123'),
            'first_name' => 'E2E',
            'last_name' => 'StudentPassword',
            'schoolclass' => '1A',
        ]);
        $passwordFlowStudent->email_verified_at = now();
        $passwordFlowStudent->confirmed_at = now();
        $passwordFlowStudent->is_active = true;
        $passwordFlowStudent->save();
        $passwordFlowStudent->assignRole($studentRole);

        $teacher = User::query()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'email' => 'e2e.teacher@example.test',
            'password' => Hash::make('password123'),
            'first_name' => 'E2E',
            'last_name' => 'Teacher',
            'short' => 'E2T',
        ]);
        $teacher->email_verified_at = now();
        $teacher->confirmed_at = now();
        $teacher->is_active = true;
        $teacher->save();
        $teacher->assignRole($teacherRole);

        TeachingSchema::query()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'user_id' => $teacher->id,
            'schema_id' => '100',
            'name' => 'Standard',
            'works' => [
                ['short_name' => 'TW', 'name' => 'Testarbeit'],
            ],
            'grading' => [],
        ]);

        $admin = User::query()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'email' => 'e2e.admin@example.test',
            'password' => Hash::make('password123'),
            'first_name' => 'E2E',
            'last_name' => 'Admin',
            'is_2fa' => false,
        ]);
        $admin->email_verified_at = now();
        $admin->confirmed_at = now();
        $admin->is_active = true;
        $admin->save();
        $admin->assignRole($adminRole);

        $admin2fa = User::query()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'email' => 'e2e.admin2fa@example.test',
            'password' => Hash::make('password123'),
            'first_name' => 'E2E',
            'last_name' => 'Admin2FA',
        ]);
        $admin2fa->email_verified_at = now();
        $admin2fa->confirmed_at = now();
        $admin2fa->is_active = true;
        $admin2fa->is_2fa = true;
        $admin2fa->email_2fa = 'e2e.admin2fa@example.test';
        $admin2fa->save();
        $admin2fa->assignRole($adminRole);

        $registerUser = User::query()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'email' => 'e2e.register@example.test',
            'password' => Hash::make('password123'),
            'first_name' => 'E2E',
            'last_name' => 'Register',
            'phone' => '06641234567',
        ]);
        $registerUser->email_verified_at = now();
        $registerUser->confirmed_at = now();
        $registerUser->is_active = true;
        $registerUser->save();
        $registerUser->assignRole($registerUserRole);

        $register = Register::query()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'name' => 'E2E Elternsprechtag',
            'description_on_website' => 'E2E Registrierung für Browser-Tests',
            'max_registrations' => 0,
            'show_phone' => true,
            'must_phone' => false,
            'show_student_last_name' => true,
            'must_student_last_name' => true,
            'show_student_first_name' => true,
            'must_student_first_name' => true,
            'show_student_birthdate' => true,
            'must_student_birthdate' => false,
            'show_note' => true,
            'must_note' => false,
            'show_booked' => true,
            'show_end_time' => true,
            'show_supervisor' => false,
            'is_active' => true,
        ]);

        RegisterDate::query()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'register_id' => $register->id,
            'supervisor' => 'E2E Lehrer',
            'date' => '2026-05-10',
            'from' => '14:00',
            'to' => '14:10',
            'max_registrations' => 3,
            'is_locked' => false,
        ]);

        $registerUser->register_id = $register->id;
        $registerUser->save();

        $homepageUser = User::query()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'email' => 'e2e.user@example.test',
            'password' => Hash::make('password123'),
            'first_name' => 'E2E',
            'last_name' => 'User',
            'schoolclass' => '2A',
            'sex' => 'm',
        ]);
        $homepageUser->email_verified_at = now();
        $homepageUser->confirmed_at = now();
        $homepageUser->is_active = true;
        $homepageUser->save();

        $course = TeachingCourse::query()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'user_id' => $teacher->id,
            'title' => 'E2E Mathematik',
            'description' => 'E2E Kurs für Browser-Tests',
            'classes' => ['1A'],
            'students' => [
                ['id' => $user->id],
                ['id' => $peer->id],
            ],
            'teaching_schema_id' => 100,
        ]);

        TeachingHoliday::query()->create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'user_id' => $teacher->id,
            'scope' => 'teacher',
            'date' => '2026-04-10',
            'reason' => 'E2E Lehrerfortbildung',
        ]);

        TeachingCourseDate::query()->create([
            'teaching_course_id' => $course->id,
            'date' => '2026-04-10',
            'hours' => [1, 2],
            'content' => null,
            'status' => ['free'],
        ]);
        TeachingCourseDate::query()->create([
            'teaching_course_id' => $course->id,
            'date' => '2026-04-15',
            'hours' => [3],
            'content' => 'Bruchrechnung',
            'status' => [],
        ]);

        $work = TeachingCourseWork::query()->create([
            'teaching_course_id' => $course->id,
            'type' => 'TW',
            'title' => 'Kapitel 1',
            'description' => 'Rechenaufgaben',
            'is_group_work' => true,
            'group_size' => 2,
            'groups' => [
                [
                    'student_ids' => [$user->id, $peer->id],
                    'comments' => [
                        ['student_id' => $user->id, 'comment' => 'Meine Ausarbeitung'],
                    ],
                ],
            ],
        ]);

        TeachingCourseStudentEntry::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $user->id,
            'teaching_course_work_id' => $work->id,
            'date' => '2026-04-20',
            'type' => 'TW',
            'grade' => null,
            'status' => [],
            'source' => 'manual',
        ]);
        TeachingCourseStudentEntry::query()->create([
            'teaching_course_id' => $course->id,
            'user_id' => $user->id,
            'date' => '2026-04-25',
            'description' => 'Kurzer Test',
            'type' => 'TW',
            'grade' => '2',
            'status' => [],
            'source' => 'manual',
        ]);
    }
}
