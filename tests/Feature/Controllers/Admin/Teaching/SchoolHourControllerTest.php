<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\TeachingSchoolHour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect([
        'super_admin',
        'admin',
        'teaching_admin',
        'teacher',
        'user',
        'student',
    ])->each(fn (string $role) => Role::firstOrCreate([
        'name' => $role,
        'guard_name' => 'web',
    ]));

    $this->school = School::factory()->create();
    $otherSchool = School::factory()->create();

    $teachingLicence = Licence::firstOrCreate(
        ['name' => 'Lehrertool'],
        ['long_name' => 'Lehrertool', 'is_selectable' => true]
    );
    $this->school->licences()->attach($teachingLicence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);
    $otherSchool->licences()->attach($teachingLicence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);

    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
    ]);
    $this->admin->assignRole('admin');

    $this->teachingAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
    ]);
    $this->teachingAdmin->assignRole('teaching_admin');

    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
    ]);
    $this->teacher->assignRole('teacher');

    $this->otherSchoolHour = TeachingSchoolHour::query()->create([
        'school_id' => $otherSchool->id,
        'hour' => 1,
        'from' => '08:00:00',
        'until' => '08:50:00',
    ]);
});

describe('authorization', function () {
    test('returns 401 when unauthenticated', function () {
        $this->getJson('/api/admin/teaching/school_hours')->assertStatus(401);
    });

    test('returns 403 for teacher on school hour index', function () {
        $this->actingAs($this->teacher, 'sanctum');

        $this->getJson('/api/admin/teaching/school_hours')->assertStatus(403);
    });
});

test('index returns only school hours of current school sorted by hour', function () {
    $this->actingAs($this->admin, 'sanctum');

    TeachingSchoolHour::query()->create([
        'school_id' => $this->school->id,
        'hour' => 3,
        'from' => '09:50:00',
        'until' => '10:40:00',
    ]);
    TeachingSchoolHour::query()->create([
        'school_id' => $this->school->id,
        'hour' => 1,
        'from' => '08:00:00',
        'until' => '08:50:00',
    ]);

    $response = $this->getJson('/api/admin/teaching/school_hours');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('data.0.hour'))->toBe(1)
        ->and($response->json('data.1.hour'))->toBe(3);
});

test('store creates school hour for current school', function () {
    $this->actingAs($this->admin, 'sanctum');

    $response = $this->postJson('/api/admin/teaching/school_hours', [
        'hour' => 2,
        'from' => '08:55',
        'until' => '09:45',
    ]);

    $response->assertCreated()
        ->assertJsonPath('created', 1)
        ->assertJsonPath('data.0.hour', 2)
        ->assertJsonPath('data.0.from', '08:55')
        ->assertJsonPath('data.0.until', '09:45');

    $this->assertDatabaseHas('teaching_school_hours', [
        'school_id' => $this->school->id,
        'hour' => 2,
        'from' => '08:55:00',
        'until' => '09:45:00',
    ]);
});

test('store creates multiple school hours at once', function () {
    $this->actingAs($this->admin, 'sanctum');

    $response = $this->postJson('/api/admin/teaching/school_hours', [
        'entries' => [
            ['hour' => 1, 'from' => '08:00', 'until' => '08:50'],
            ['hour' => 2, 'from' => '08:55', 'until' => '09:45'],
            ['hour' => 3, 'from' => '09:50', 'until' => '10:40'],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('created', 3);
    expect($response->json('data'))->toHaveCount(3);

    $this->assertDatabaseHas('teaching_school_hours', [
        'school_id' => $this->school->id,
        'hour' => 1,
        'from' => '08:00:00',
        'until' => '08:50:00',
    ]);
    $this->assertDatabaseHas('teaching_school_hours', [
        'school_id' => $this->school->id,
        'hour' => 2,
        'from' => '08:55:00',
        'until' => '09:45:00',
    ]);
    $this->assertDatabaseHas('teaching_school_hours', [
        'school_id' => $this->school->id,
        'hour' => 3,
        'from' => '09:50:00',
        'until' => '10:40:00',
    ]);
});

test('store validates unique hour per school', function () {
    $this->actingAs($this->admin, 'sanctum');

    TeachingSchoolHour::query()->create([
        'school_id' => $this->school->id,
        'hour' => 4,
        'from' => '10:45:00',
        'until' => '11:35:00',
    ]);

    $this->postJson('/api/admin/teaching/school_hours', [
        'entries' => [
            ['hour' => 4, 'from' => '10:50', 'until' => '11:40'],
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['entries.0.hour']);
});

test('update edits school hour', function () {
    $this->actingAs($this->admin, 'sanctum');

    $schoolHour = TeachingSchoolHour::query()->create([
        'school_id' => $this->school->id,
        'hour' => 5,
        'from' => '11:40:00',
        'until' => '12:30:00',
    ]);

    $response = $this->putJson("/api/admin/teaching/school_hours/{$schoolHour->id}", [
        'hour' => 6,
        'from' => '12:35',
        'until' => '13:25',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.hour', 6)
        ->assertJsonPath('data.from', '12:35')
        ->assertJsonPath('data.until', '13:25');

    $this->assertDatabaseHas('teaching_school_hours', [
        'id' => $schoolHour->id,
        'hour' => 6,
        'from' => '12:35:00',
        'until' => '13:25:00',
    ]);
});

test('destroy deletes school hour', function () {
    $this->actingAs($this->teachingAdmin, 'sanctum');

    $schoolHour = TeachingSchoolHour::query()->create([
        'school_id' => $this->school->id,
        'hour' => 7,
        'from' => '13:30:00',
        'until' => '14:20:00',
    ]);

    $this->deleteJson("/api/admin/teaching/school_hours/{$schoolHour->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('teaching_school_hours', [
        'id' => $schoolHour->id,
    ]);
});

test('destroy rejects deleting school hour from another school', function () {
    $this->actingAs($this->admin, 'sanctum');

    $this->deleteJson("/api/admin/teaching/school_hours/{$this->otherSchoolHour->id}")
        ->assertStatus(403);

    $this->assertDatabaseHas('teaching_school_hours', [
        'id' => $this->otherSchoolHour->id,
    ]);
});
