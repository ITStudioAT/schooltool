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

it('updates an imported teacher without changing the active state', function () {
    $manager = createTeacherRosterUpdateManager();
    $teacher = Teacher::query()->create([
        'school_id' => $manager->school_id,
        'short' => 'ALT',
        'last_name' => 'Alt',
        'first_name' => 'Anna',
        'email' => 'alt@example.test',
        'is_active' => false,
    ]);

    $this->actingAs($manager)
        ->putJson("/api/admin/students-timetables/teacher-list/{$teacher->id}", [
            'short' => ' neu ',
            'last_name' => ' Neuername ',
            'first_name' => ' Nina ',
            'email' => ' NEU@EXAMPLE.TEST ',
        ])
        ->assertSuccessful()
        ->assertJsonPath('short', 'NEU')
        ->assertJsonPath('last_name', 'Neuername')
        ->assertJsonPath('first_name', 'Nina')
        ->assertJsonPath('email', 'neu@example.test')
        ->assertJsonPath('is_active', false);

    expect($teacher->fresh())
        ->short->toBe('NEU')
        ->last_name->toBe('Neuername')
        ->first_name->toBe('Nina')
        ->email->toBe('neu@example.test')
        ->is_active->toBeFalse();
});

it('updates a registered teacher while preserving active state and timetable role', function () {
    $manager = createTeacherRosterUpdateManager();
    $teacherRole = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    $moderatorRole = Role::firstOrCreate(['name' => 'studentstimetables_moderator', 'guard_name' => 'web']);
    $teacher = User::factory()->create([
        'school_id' => $manager->school_id,
        'short' => 'ALT',
        'email' => 'registered.old@example.test',
        'is_active' => false,
        'students_timetables_teacher_listed' => false,
    ]);
    $teacher->assignRole([$teacherRole, $moderatorRole]);
    $matchingImportedTeacher = Teacher::query()->create([
        'school_id' => $manager->school_id,
        'short' => 'ALT',
        'last_name' => $teacher->last_name,
        'first_name' => $teacher->first_name,
        'email' => $teacher->email,
        'is_active' => true,
    ]);

    $this->actingAs($manager)
        ->putJson("/api/admin/students-timetables/teacher-list/users/{$teacher->id}", [
            'short' => 'reg',
            'last_name' => 'Registriert',
            'first_name' => '',
            'email' => 'registered.new@example.test',
        ])
        ->assertSuccessful()
        ->assertJsonPath('short', 'REG')
        ->assertJsonPath('first_name', null)
        ->assertJsonPath('is_active', false)
        ->assertJsonFragment(['studentstimetables_moderator']);

    $teacher->refresh();

    expect($teacher->email)->toBe('registered.new@example.test')
        ->and((bool) $teacher->is_active)->toBeFalse()
        ->and((bool) $teacher->students_timetables_teacher_listed)->toBeTrue()
        ->and($teacher->hasRole('teacher'))->toBeTrue()
        ->and($teacher->hasRole('studentstimetables_moderator'))->toBeTrue()
        ->and($matchingImportedTeacher->fresh()->email)->toBe('registered.new@example.test')
        ->and($matchingImportedTeacher->fresh()->short)->toBe('REG');
});

it('rejects teacher emails already used by another roster entry in the school', function () {
    $manager = createTeacherRosterUpdateManager();
    $existingUser = User::factory()->create([
        'school_id' => $manager->school_id,
        'email' => 'used.by.user@example.test',
    ]);
    $importedTeacher = Teacher::query()->create([
        'school_id' => $manager->school_id,
        'short' => 'IMP',
        'last_name' => 'Importiert',
        'first_name' => null,
        'email' => 'imported@example.test',
    ]);

    $this->actingAs($manager)
        ->putJson("/api/admin/students-timetables/teacher-list/{$importedTeacher->id}", [
            'short' => 'IMP',
            'last_name' => 'Importiert',
            'first_name' => null,
            'email' => strtoupper($existingUser->email),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $registeredTeacher = User::factory()->create([
        'school_id' => $manager->school_id,
        'email' => 'registered@example.test',
        'students_timetables_teacher_listed' => true,
    ]);
    $registeredTeacher->assignRole(Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']));

    $this->actingAs($manager)
        ->putJson("/api/admin/students-timetables/teacher-list/users/{$registeredTeacher->id}", [
            'short' => 'REG',
            'last_name' => 'Registriert',
            'first_name' => null,
            'email' => strtoupper($importedTeacher->email),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('forbids updating teachers from another school', function () {
    $manager = createTeacherRosterUpdateManager();
    $otherSchool = School::factory()->create();
    $teacher = Teacher::query()->create([
        'school_id' => $otherSchool->id,
        'short' => 'OTH',
        'last_name' => 'Andere',
        'first_name' => 'Schule',
        'email' => 'other.school@example.test',
    ]);

    $this->actingAs($manager)
        ->putJson("/api/admin/students-timetables/teacher-list/{$teacher->id}", [
            'short' => 'NEW',
            'last_name' => 'Geändert',
            'first_name' => 'Nicht',
            'email' => 'changed@example.test',
        ])
        ->assertForbidden();

    expect($teacher->fresh()->email)->toBe('other.school@example.test');
});

it('forbids teachers without a manager role from editing the roster', function () {
    $teacherUser = createTeacherRosterUpdateManager('teacher');
    $teacher = Teacher::query()->create([
        'school_id' => $teacherUser->school_id,
        'short' => 'IMP',
        'last_name' => 'Importiert',
        'first_name' => null,
        'email' => 'imported@example.test',
    ]);

    $this->actingAs($teacherUser)
        ->putJson("/api/admin/students-timetables/teacher-list/{$teacher->id}", [
            'short' => 'NEW',
            'last_name' => 'Geändert',
            'first_name' => null,
            'email' => 'changed@example.test',
        ])
        ->assertForbidden();
});

function createTeacherRosterUpdateManager(string $roleName = 'studentstimetables_admin'): User
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
