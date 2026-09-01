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

it('creates a normalized teacher in the managers school', function () {
    $manager = createTeacherRosterCreateManager();

    $response = $this->actingAs($manager)
        ->postJson('/api/admin/students-timetables/teacher-list', [
            'short' => ' neu ',
            'last_name' => ' Neumann ',
            'first_name' => ' Nora ',
            'email' => ' NORA.NEUMANN@EXAMPLE.TEST ',
        ])
        ->assertCreated()
        ->assertJsonPath('short', 'NEU')
        ->assertJsonPath('last_name', 'Neumann')
        ->assertJsonPath('first_name', 'Nora')
        ->assertJsonPath('email', 'nora.neumann@example.test')
        ->assertJsonPath('is_active', true)
        ->assertJsonPath('user_id', null);

    $teacher = Teacher::query()->findOrFail($response->json('teacher_id'));

    expect($teacher)
        ->school_id->toBe($manager->school_id)
        ->short->toBe('NEU')
        ->email->toBe('nora.neumann@example.test')
        ->is_active->toBeTrue();
});

it('rejects duplicate emails and unauthorized roster creation', function () {
    $manager = createTeacherRosterCreateManager();
    $existingUser = User::factory()->create([
        'school_id' => $manager->school_id,
        'email' => 'existing@example.test',
    ]);

    $this->actingAs($manager)
        ->postJson('/api/admin/students-timetables/teacher-list', [
            'short' => 'DUP',
            'last_name' => 'Doppelt',
            'first_name' => null,
            'email' => strtoupper($existingUser->email),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $teacherUser = createTeacherRosterCreateManager('teacher');

    $this->actingAs($teacherUser)
        ->postJson('/api/admin/students-timetables/teacher-list', [
            'short' => 'NEW',
            'last_name' => 'Nicht erlaubt',
            'first_name' => null,
            'email' => 'forbidden@example.test',
        ])
        ->assertForbidden();

    expect(Teacher::query()->where('email', 'forbidden@example.test')->exists())->toBeFalse();
});

function createTeacherRosterCreateManager(string $roleName = 'studentstimetables_admin'): User
{
    $school = School::factory()->create();

    SchoolTool::factory()->create([
        'school_id' => $school->id,
        'students_timetables_visible_admin' => true,
        'students_timetables_visible_user' => true,
        'students_timetables_user_test_mode' => false,
        'students_timetables_user_comming_soon' => false,
    ]);

    $licence = Licence::query()->firstOrCreate(
        ['name' => 'StudentsTimetables'],
        [
            'long_name' => 'Tool zum Verwalten von Schülerstundenplänen',
            'price_per_year' => 200,
        ],
    );

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
