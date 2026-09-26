<?php

/**
 * SchoolyearController Tests
 *
 * Covers role-based access, validation, and core behaviors for:
 * - Listing schoolyears
 * - Creating, updating, and deleting schoolyears
 * - Setting the active schoolyear for a user
 */

use App\Models\ClassRepresentativeElection;
use App\Models\Import116;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\SchoolyearService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create([
        'long_name' => 'Main School',
        'short_name' => 'MAIN',
        'email' => 'main@example.com',
    ]);

    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'name' => '2024/2025',
    ]);

    collect(['super_admin', 'admin', 'register_admin', 'teacher'])->each(
        fn (string $role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])
    );

    $this->adminUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'admin@test.com',
    ]);
    $this->adminUser->assignRole('admin');

    $this->registerAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'register.admin@test.com',
    ]);
    $this->registerAdmin->assignRole('register_admin');

    $this->teacherUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'teacher@test.com',
    ]);
    $this->teacherUser->assignRole('teacher');
});

// ============================================================================
// index
// ============================================================================

test('admin can list schoolyears sorted by name', function () {
    $alpha = Schoolyear::factory()->create(['school_id' => $this->school->id, 'name' => '2023/2024']);
    $charlie = Schoolyear::factory()->create(['school_id' => $this->school->id, 'name' => '2025/2026']);

    $this->actingAs($this->adminUser, 'sanctum');

    $response = $this->getJson('/api/admin/schoolyears');

    $response->assertStatus(200)
        ->assertJsonStructure([
            ['id', 'name', 'from', 'until', 'sem_2_start'],
        ])
        ->assertJsonCount(3);

    $names = collect($response->json())->pluck('name')->all();

    expect($names)->toBe([
        '2023/2024',
        '2024/2025',
        '2025/2026',
    ]);
});

test('register admin can list schoolyears', function () {
    $this->actingAs($this->registerAdmin, 'sanctum');

    $this->getJson('/api/admin/schoolyears')
        ->assertStatus(200);
});

test('teacher can list schoolyears', function () {
    $this->actingAs($this->teacherUser, 'sanctum');

    $this->getJson('/api/admin/schoolyears')
        ->assertStatus(200);
});

test('guest cannot list schoolyears', function () {
    $this->getJson('/api/admin/schoolyears')
        ->assertStatus(401);
});

test('helpers overview lists distinct classes from the personally selected schoolyear', function () {
    Import116::factory()->forSchool($this->school)->importedBy($this->adminUser)->create([
        'schoolyear_id' => $this->schoolyear->id,
        'class' => '2B',
    ]);
    Import116::factory()->forSchool($this->school)->importedBy($this->adminUser)->create([
        'schoolyear_id' => $this->schoolyear->id,
        'class' => '1A',
    ]);

    ClassRepresentativeElection::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'class_name' => '8A',
        'announced_at' => now(),
    ]);
    Import116::factory()->forSchool($this->school)->importedBy($this->adminUser)->deleted()->create([
        'schoolyear_id' => $this->schoolyear->id,
        'class' => '6C',
    ]);
    foreach (['5L1', '5L2', '7A-M', '7A-R', '8A-BU', '8A-DG', '8M-BU', '8M-DG'] as $class) {
        Import116::factory()->forSchool($this->school)->importedBy($this->adminUser)->create([
            'schoolyear_id' => $this->schoolyear->id,
            'class' => $class,
        ]);
    }
    Import116::factory()->forSchool($this->school)->importedBy($this->adminUser)->create([
        'schoolyear_id' => $this->schoolyear->id,
        'class' => '1A',
    ]);

    $otherSchoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    Import116::factory()->forSchool($this->school)->importedBy($this->adminUser)->create([
        'schoolyear_id' => $otherSchoolyear->id,
        'class' => '3C',
    ]);

    $otherSchool = School::factory()->create();
    Import116::factory()->forSchool($otherSchool)->importedBy($this->adminUser)->create([
        'schoolyear_id' => $this->schoolyear->id,
        'class' => '4D',
    ]);

    $this->actingAs($this->adminUser, 'sanctum')
        ->getJson("/api/admin/helpers/classes?schoolyear_id={$this->schoolyear->id}")
        ->assertSuccessful()
        ->assertExactJson(['data' => [
            ['name' => '1A', 'variants' => ['1A'], 'student_count' => 2, 'announced' => false],
            ['name' => '2B', 'variants' => ['2B'], 'student_count' => 1, 'announced' => false],
            ['name' => '5L1', 'variants' => ['5L1'], 'student_count' => 1, 'announced' => false],
            ['name' => '5L2', 'variants' => ['5L2'], 'student_count' => 1, 'announced' => false],
            ['name' => '7A', 'variants' => ['7A-M', '7A-R'], 'student_count' => 2, 'announced' => false],
            ['name' => '8A', 'variants' => ['8A-BU', '8A-DG'], 'student_count' => 2, 'announced' => true],
            ['name' => '8M', 'variants' => ['8M-BU', '8M-DG'], 'student_count' => 2, 'announced' => false],
        ]]);
});

test('helpers classes require admin shell access and a schoolyear from the own school', function () {
    $this->getJson("/api/admin/helpers/classes?schoolyear_id={$this->schoolyear->id}")->assertUnauthorized();

    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
    $student = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $student->assignRole('student');

    $this->actingAs($student, 'sanctum')
        ->getJson("/api/admin/helpers/classes?schoolyear_id={$this->schoolyear->id}")
        ->assertForbidden();

    $otherSchool = School::factory()->create();
    $otherSchoolyear = Schoolyear::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->adminUser, 'sanctum')
        ->getJson('/api/admin/helpers/classes')
        ->assertUnprocessable();

    $this->getJson("/api/admin/helpers/classes?schoolyear_id={$otherSchoolyear->id}")
        ->assertNotFound();
});

// ============================================================================
// store
// ============================================================================

test('admin can create schoolyear and triggers user service update', function () {
    $payload = [
        'name' => '2026/2027',
        'from' => '2026-09-01',
        'until' => '2027-07-10',
        'sem_2_start' => '2027-02-15',
    ];

    $this->mock(UserService::class, function ($mock) {
        $mock->shouldReceive('setNewSchoolyear')
            ->once()
            ->withArgs(function ($user, $schoolyear) {
                return $user->email === 'admin@test.com' && $schoolyear instanceof Schoolyear;
            });
    });

    $this->actingAs($this->adminUser, 'sanctum');

    $this->postJson('/api/admin/schoolyears', $payload)
        ->assertStatus(200)
        ->assertJsonFragment([
            'name' => '2026/2027',
            'from' => '2026-09-01',
            'until' => '2027-07-10',
            'sem_2_start' => '2027-02-15',
        ]);

    $this->assertDatabaseHas('schoolyears', [
        'name' => '2026/2027',
        'school_id' => $this->school->id,
    ]);
});

test('register admin cannot create schoolyear', function () {
    $this->actingAs($this->registerAdmin, 'sanctum');

    $this->postJson('/api/admin/schoolyears', [
        'name' => 'Blocked',
    ])->assertStatus(403);
});

// ============================================================================
// update
// ============================================================================

test('admin can update schoolyear', function () {
    $target = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'name' => '2026/2027',
    ]);

    $payload = [
        'id' => $target->id,
        'name' => '2027/2028',
        'from' => '2027-09-01',
        'until' => '2028-07-10',
        'sem_2_start' => '2028-02-10',
    ];

    $this->actingAs($this->adminUser, 'sanctum');

    $this->putJson("/api/admin/schoolyears/{$target->id}", $payload)
        ->assertStatus(200)
        ->assertJsonFragment([
            'id' => $target->id,
            'name' => '2027/2028',
        ]);

    $this->assertDatabaseHas('schoolyears', [
        'id' => $target->id,
        'name' => '2027/2028',
    ]);
});

test('update requires valid payload', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $this->putJson("/api/admin/schoolyears/{$this->schoolyear->id}", [
        'name' => 'Missing ID',
    ])->assertStatus(422);
});

test('register admin cannot update schoolyear', function () {
    $target = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'name' => '2026/2027',
    ]);

    $this->actingAs($this->registerAdmin, 'sanctum');

    $this->putJson("/api/admin/schoolyears/{$target->id}", [
        'id' => $target->id,
        'name' => 'Blocked',
    ])->assertStatus(403);
});

test('admin cannot update a schoolyear from another school', function () {
    $otherSchool = School::factory()->create();
    $foreignSchoolyear = Schoolyear::factory()->create([
        'school_id' => $otherSchool->id,
        'name' => 'Foreign',
    ]);

    $this->actingAs($this->adminUser, 'sanctum');

    $this->putJson("/api/admin/schoolyears/{$foreignSchoolyear->id}", [
        'id' => $foreignSchoolyear->id,
        'name' => 'Compromised',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['id']);

    expect($foreignSchoolyear->fresh()->name)->toBe('Foreign');
});

// ============================================================================
// destroy
// ============================================================================

test('admin can delete schoolyear without dependencies', function () {
    $deletable = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'name' => 'Deletable',
    ]);

    $this->mock(UserService::class, function ($mock) use ($deletable) {
        $mock->shouldReceive('setSchoolyearToNull')
            ->once()
            ->withArgs(function ($schoolyear) use ($deletable) {
                return $schoolyear->id === $deletable->id;
            });
    });

    $this->actingAs($this->adminUser, 'sanctum');

    $this->deleteJson("/api/admin/schoolyears/{$deletable->id}")
        ->assertStatus(204);

    $this->assertDatabaseMissing('schoolyears', ['id' => $deletable->id]);
});

test('destroy is blocked when dependencies exist', function () {
    $dependentYear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'name' => 'With Users',
    ]);

    User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $dependentYear->id,
        'email' => 'dependent@test.com',
    ]);
    User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $dependentYear->id,
        'email' => 'dependent2@test.com',
    ]);

    $this->actingAs($this->adminUser, 'sanctum');

    $this->deleteJson("/api/admin/schoolyears/{$dependentYear->id}")
        ->assertStatus(409);
});

test('register admin cannot delete schoolyear', function () {
    $deletable = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'name' => 'NoDelete',
    ]);

    $this->actingAs($this->registerAdmin, 'sanctum');

    $this->deleteJson("/api/admin/schoolyears/{$deletable->id}")
        ->assertStatus(403);
});

test('guest cannot delete schoolyear', function () {
    $deletable = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'name' => 'GuestNoDelete',
    ]);

    $this->deleteJson("/api/admin/schoolyears/{$deletable->id}")
        ->assertStatus(401);
});

test('admin cannot delete a schoolyear from another school', function () {
    $otherSchool = School::factory()->create();
    $foreignSchoolyear = Schoolyear::factory()->create([
        'school_id' => $otherSchool->id,
        'name' => 'Foreign',
    ]);

    $this->actingAs($this->adminUser, 'sanctum');

    $this->deleteJson("/api/admin/schoolyears/{$foreignSchoolyear->id}")
        ->assertNotFound();

    $this->assertModelExists($foreignSchoolyear);
});

// ============================================================================
// setActiveSchoolyear
// ============================================================================

test('admin can set active schoolyear via service', function () {
    $newYear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'name' => '2028/2029',
    ]);

    $this->mock(SchoolyearService::class, function ($mock) use ($newYear) {
        $mock->shouldReceive('setToUser')
            ->once()
            ->andReturn($newYear);
    });

    $this->actingAs($this->adminUser, 'sanctum');

    $this->postJson('/api/admin/schoolyears/set_active', [
        'schoolyear_id' => $newYear->id,
    ])->assertStatus(200)
        ->assertJsonFragment([
            'id' => $newYear->id,
            'name' => '2028/2029',
        ]);
});

test('register admin can set active schoolyear', function () {
    $newYear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'name' => '2029/2030',
    ]);

    $this->mock(SchoolyearService::class, function ($mock) use ($newYear) {
        $mock->shouldReceive('setToUser')
            ->once()
            ->andReturn($newYear);
    });

    $this->actingAs($this->registerAdmin, 'sanctum');

    $this->postJson('/api/admin/schoolyears/set_active', [
        'schoolyear_id' => $newYear->id,
    ])->assertStatus(200);
});

test('teacher can set active schoolyear', function () {
    $newYear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'name' => '2030/2031',
    ]);

    $this->mock(SchoolyearService::class, function ($mock) use ($newYear) {
        $mock->shouldReceive('setToUser')
            ->once()
            ->andReturn($newYear);
    });

    $this->actingAs($this->teacherUser, 'sanctum');

    $this->postJson('/api/admin/schoolyears/set_active', [
        'schoolyear_id' => $newYear->id,
    ])->assertStatus(200);
});

test('set active schoolyear requires authentication and valid payload', function () {
    $this->postJson('/api/admin/schoolyears/set_active', [])
        ->assertStatus(401);

    $this->actingAs($this->adminUser, 'sanctum');

    $this->postJson('/api/admin/schoolyears/set_active', [])
        ->assertStatus(422);
});

test('user cannot select a schoolyear from another school', function () {
    $otherSchool = School::factory()->create();
    $foreignSchoolyear = Schoolyear::factory()->create([
        'school_id' => $otherSchool->id,
    ]);

    $this->actingAs($this->adminUser, 'sanctum');

    $this->postJson('/api/admin/schoolyears/set_active', [
        'schoolyear_id' => $foreignSchoolyear->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['schoolyear_id']);
});
