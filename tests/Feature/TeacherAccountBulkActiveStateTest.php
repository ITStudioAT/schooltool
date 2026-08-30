<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('sets every teacher roster entry active or inactive within the managers school', function () {
    $manager = createTeacherRosterManager();
    $teacherRole = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $activeTeacher = User::factory()->create([
        'school_id' => $manager->school_id,
        'is_active' => true,
        'students_timetables_teacher_listed' => true,
    ]);
    $activeTeacher->assignRole($teacherRole);

    $inactiveTeacher = User::factory()->create([
        'school_id' => $manager->school_id,
        'is_active' => false,
        'students_timetables_teacher_listed' => true,
    ]);
    $inactiveTeacher->assignRole($teacherRole);

    $protectedAdmin = User::factory()->create([
        'school_id' => $manager->school_id,
        'is_active' => true,
        'students_timetables_teacher_listed' => true,
    ]);
    $protectedAdmin->assignRole([$teacherRole, $adminRole]);

    $protectedSuperAdmin = User::factory()->create([
        'school_id' => $manager->school_id,
        'is_active' => true,
        'students_timetables_teacher_listed' => true,
    ]);
    $protectedSuperAdmin->assignRole([$teacherRole, $superAdminRole]);

    $importedTeacher = Teacher::query()->create([
        'school_id' => $manager->school_id,
        'short' => 'IMP',
        'last_name' => 'Importiert',
        'first_name' => 'Ina',
        'email' => 'imported.teacher@example.test',
    ]);

    $otherSchool = School::factory()->create();
    $otherSchoolTeacher = User::factory()->create([
        'school_id' => $otherSchool->id,
        'is_active' => true,
        'students_timetables_teacher_listed' => true,
    ]);
    $otherSchoolTeacher->assignRole($teacherRole);
    $otherSchoolImportedTeacher = Teacher::query()->create([
        'school_id' => $otherSchool->id,
        'short' => 'OTH',
        'last_name' => 'Andere',
        'first_name' => 'Schule',
        'email' => 'other.school@example.test',
    ]);

    $this->actingAs($manager)
        ->putJson('/api/admin/students-timetables/teacher-list/active-state', ['is_active' => false])
        ->assertSuccessful()
        ->assertJsonPath('data.is_active', false);

    expect((bool) $activeTeacher->fresh()->is_active)->toBeFalse()
        ->and((bool) $inactiveTeacher->fresh()->is_active)->toBeFalse()
        ->and((bool) $importedTeacher->fresh()->is_active)->toBeFalse()
        ->and((bool) $manager->fresh()->is_active)->toBeTrue()
        ->and((bool) $protectedAdmin->fresh()->is_active)->toBeTrue()
        ->and((bool) $protectedSuperAdmin->fresh()->is_active)->toBeTrue()
        ->and((bool) $otherSchoolTeacher->fresh()->is_active)->toBeTrue()
        ->and((bool) $otherSchoolImportedTeacher->fresh()->is_active)->toBeTrue();

    $this->actingAs($manager)
        ->putJson('/api/admin/students-timetables/teacher-list/active-state', ['is_active' => true])
        ->assertSuccessful()
        ->assertJsonPath('data.is_active', true);

    expect((bool) $activeTeacher->fresh()->is_active)->toBeTrue()
        ->and((bool) $inactiveTeacher->fresh()->is_active)->toBeTrue()
        ->and((bool) $importedTeacher->fresh()->is_active)->toBeTrue()
        ->and((bool) $otherSchoolTeacher->fresh()->is_active)->toBeTrue()
        ->and((bool) $otherSchoolImportedTeacher->fresh()->is_active)->toBeTrue();
});

it('validates the requested bulk active state', function () {
    $manager = createTeacherRosterManager();

    $this->actingAs($manager)
        ->putJson('/api/admin/students-timetables/teacher-list/active-state')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('is_active');
});

it('forbids teachers without a manager role from changing every active state', function () {
    $teacher = createTeacherRosterManager('teacher');

    $this->actingAs($teacher)
        ->putJson('/api/admin/students-timetables/teacher-list/active-state', ['is_active' => false])
        ->assertForbidden();
});

function createTeacherRosterManager(string $roleName = 'studentstimetables_admin'): User
{
    $school = School::factory()->create();

    SchoolTool::factory()->create([
        'school_id' => $school->id,
        'students_timetables_visible_admin' => true,
        'students_timetables_visible_user' => true,
        'students_timetables_user_test_mode' => false,
        'students_timetables_user_comming_soon' => false,
    ]);

    $licence = Licence::query()->create([
        'name' => 'StudentsTimetables',
        'long_name' => 'Tool zum Verwalten von Schülerstundenplänen',
        'price_per_year' => 200,
    ]);

    SchoolLicence::query()->create([
        'school_id' => $school->id,
        'licence_id' => $licence->id,
        'valid_until' => now()->addMonth()->toDateString(),
    ]);

    Role::firstOrCreate([
        'name' => $roleName,
        'guard_name' => 'web',
    ]);

    $manager = User::factory()->create([
        'school_id' => $school->id,
        'is_active' => true,
    ]);
    $manager->assignRole($roleName);

    return $manager;
}
