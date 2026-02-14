<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect(['student', 'teacher', 'super_admin'])->each(function (string $role) {
        Role::firstOrCreate([
            'name' => $role,
            'guard_name' => 'web',
        ]);
    });

    $this->school = School::factory()->create([
        'short_name' => 'E2E',
        'long_name' => 'E2E School',
        'is_selectable' => true,
    ]);

    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'is_active' => true,
    ]);

    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'active_schoolyear_id' => $this->schoolyear->id,
    ]);

    $licence = Licence::create([
        'name' => 'Lehrertool',
        'long_name' => 'Lehrertool',
        'is_selectable' => true,
    ]);

    $this->school->licences()->attach($licence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);

    $this->student = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'student@example.test',
        'password' => Hash::make('password123'),
        'first_name' => 'Max',
        'last_name' => 'Student',
    ]);
    $this->student->assignRole('student');

    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'teacher@example.test',
    ]);
    $this->teacher->assignRole('teacher');
});

test('config returns only selectable schools with valid Lehrertool licence', function () {
    $schoolWithoutLicence = School::factory()->create([
        'is_selectable' => true,
    ]);

    $schoolNotSelectable = School::factory()->create([
        'is_selectable' => false,
    ]);
    $licence = Licence::where('name', 'Lehrertool')->firstOrFail();
    $schoolNotSelectable->licences()->attach($licence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);

    $response = $this->getJson('/api/homepage/student/config');

    $response->assertOk()
        ->assertJsonStructure([
            'schools',
            'config' => ['schooltool' => ['teaching_max_schools_shown']],
        ]);

    $schools = $response->json('schools');
    expect($schools)->toHaveCount(1)
        ->and($schools[0]['id'])->toBe($this->school->id)
        ->and((int) $response->json('config.schooltool.teaching_max_schools_shown'))->toBeGreaterThan(0);

    expect(collect($schools)->pluck('id'))->not->toContain($schoolWithoutLicence->id)
        ->not->toContain($schoolNotSelectable->id);
});

test('login step email returns enter_password for existing student', function () {
    $response = $this->postJson('/api/homepage/student/login_step_email', [
        'type' => 'login_with_password',
        'school_id' => $this->school->id,
        'email' => $this->student->email,
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'enter_password')
        ->assertJsonPath('schoolyear_id', $this->schoolyear->id);
});

test('login step password authenticates with valid password', function () {
    $response = $this->postJson('/api/homepage/student/login_step_password', [
        'type' => 'login_with_password',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => $this->student->email,
        'password' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'login_ok')
        ->assertJsonPath('user.email', $this->student->email);

    $this->assertAuthenticatedAs($this->student);
});

test('login step password returns password_not_valid for wrong password', function () {
    $response = $this->postJson('/api/homepage/student/login_step_password', [
        'type' => 'login_with_password',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => $this->student->email,
        'password' => 'wrong-password',
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'password_not_valid');
});

test('user endpoint returns user only for authenticated students', function () {
    $this->getJson('/api/homepage/student/user')
        ->assertOk()
        ->assertJsonPath('user', null);

    $this->actingAs($this->teacher)
        ->getJson('/api/homepage/student/user')
        ->assertOk()
        ->assertJsonPath('user', null);

    $this->actingAs($this->student)
        ->getJson('/api/homepage/student/user')
        ->assertOk()
        ->assertJsonPath('user.id', $this->student->id);
});

test('change password updates password for student and blocks other roles', function () {
    $this->actingAs($this->student)
        ->postJson('/api/homepage/student/change_password', [
            'new_password' => 'new-password-123',
            'confirm_password' => 'new-password-123',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    $this->student->refresh();
    expect(Hash::check('new-password-123', $this->student->password))->toBeTrue();

    $this->actingAs($this->teacher)
        ->postJson('/api/homepage/student/change_password', [
            'new_password' => 'another-password-123',
            'confirm_password' => 'another-password-123',
        ])
        ->assertStatus(403);
});
