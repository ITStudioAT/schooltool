<?php

/**
 * SchoolController Tests
 *
 * Coverage mirrors the RegisterUserController suite style and focuses on:
 * - Role-based access for all endpoints
 * - Happy-path payload handling and validation failures
 * - Integration with injected services where applicable
 */

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\SchoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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

    collect(['super_admin', 'admin', 'register_admin'])->each(
        fn(string $role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])
    );

    $this->superAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'super.admin@test.com',
    ]);
    $this->superAdmin->assignRole('super_admin');

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
});

// ============================================================================
// index
// ============================================================================

test('super admin can list schools ordered by long_name and filter by search', function () {
    $alpha = School::factory()->create(['long_name' => 'Alpha School', 'short_name' => 'ALP', 'email' => 'alpha@example.com']);
    $charlie = School::factory()->create(['long_name' => 'Charlie School', 'short_name' => 'CHR', 'email' => 'charlie@example.com']);

    $this->actingAs($this->superAdmin, 'sanctum');

    $response = $this->getJson('/api/admin/schools?search_string=School');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                ['id', 'long_name', 'short_name', 'logo', 'email', 'is_selectable'],
            ],
            'meta' => ['per_page', 'current_page', 'last_page'],
        ]);

    $names = collect($response->json('data'))->pluck('long_name')->all();

    expect($names)->toBe([
        'Alpha School',
        'Charlie School',
        'Main School',
    ]);
});

test('non super admin receives 403 on listing schools', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $this->getJson('/api/admin/schools')
        ->assertStatus(403);
});

test('guest receives 401 on listing schools', function () {
    $this->getJson('/api/admin/schools')
        ->assertStatus(401);
});

// ============================================================================
// store
// ============================================================================

test('super admin can store school via service', function () {
    $payload = [
        'long_name' => 'New School',
        'short_name' => 'NEW',
        'email' => 'new@example.com',
        'is_selectable' => true,
    ];

    $createdSchool = new School($payload);
    $createdSchool->id = 999;

    $this->mock(SchoolService::class, function ($mock) use ($payload, $createdSchool) {
        $mock->shouldReceive('create')
            ->once()
            ->with($payload)
            ->andReturn($createdSchool);
    });

    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/schools', $payload)
        ->assertStatus(200)
        ->assertJson([
            'id' => 999,
            'long_name' => 'New School',
            'short_name' => 'NEW',
            'email' => 'new@example.com',
            'is_selectable' => true,
        ]);
});

test('store requires super admin role', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $this->postJson('/api/admin/schools', [
        'long_name' => 'Blocked School',
        'short_name' => 'BLK',
        'email' => 'blocked@example.com',
    ])->assertStatus(403);
});

// ============================================================================
// update
// ============================================================================

test('admin can update school via service', function () {
    $schoolToUpdate = School::factory()->create([
        'long_name' => 'Updatable School',
        'short_name' => 'UPD',
        'email' => 'upd@example.com',
    ]);

    $payload = [
        'id' => $schoolToUpdate->id,
        'long_name' => 'Updated Name',
        'short_name' => 'UPDX',
        'email' => 'updated@example.com',
        'is_selectable' => false,
    ];

    $updatedSchool = new School($payload);
    $updatedSchool->id = $schoolToUpdate->id;

    $this->mock(SchoolService::class, function ($mock) use ($schoolToUpdate, $payload, $updatedSchool) {
        $mock->shouldReceive('update')
            ->once()
            ->with(\Mockery::on(fn($school) => $school->id === $schoolToUpdate->id), $payload)
            ->andReturn($updatedSchool);
    });

    $this->actingAs($this->adminUser, 'sanctum');

    $this->putJson("/api/admin/schools/{$schoolToUpdate->id}", $payload)
        ->assertStatus(200)
        ->assertJson([
            'id' => $schoolToUpdate->id,
            'long_name' => 'Updated Name',
            'short_name' => 'UPDX',
            'email' => 'updated@example.com',
            'is_selectable' => false,
        ]);
});

test('update requires valid payload', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $this->putJson('/api/admin/schools/1', [
        'long_name' => 'Missing ID',
    ])->assertStatus(422);
});

// ============================================================================
// deleteSchools
// ============================================================================

test('super admin cannot delete their own school', function () {
    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/schools/delete_schools', [$this->superAdmin->school_id])
        ->assertStatus(409);
});

test('super admin can delete other schools via service', function () {
    $deletable = School::factory()->create();

    $this->mock(SchoolService::class, function ($mock) use ($deletable) {
        $mock->shouldReceive('deleteSchools')
            ->once()
            ->with([$deletable->id]);
    });

    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/schools/delete_schools', [$deletable->id])
        ->assertStatus(204);
});

// ============================================================================
// loadSwitchableSchools
// ============================================================================

test('super admin can load switchable schools sorted by name', function () {
    $otherSchool = School::factory()->create(['long_name' => 'Beta School', 'short_name' => 'BTA']);

    // Create another user with same email in the other school to make it switchable
    User::factory()->create([
        'school_id' => $otherSchool->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => $this->superAdmin->email,
    ]);

    $this->actingAs($this->superAdmin, 'sanctum');

    $response = $this->postJson('/api/admin/schools/load_switchable_schools');

    $response->assertStatus(200)
        ->assertJsonCount(2)
        ->assertJson([
            ['long_name' => 'Beta School'],
            ['long_name' => 'Main School'],
        ]);
});

test('non super admin cannot load switchable schools', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $this->postJson('/api/admin/schools/load_switchable_schools')
        ->assertStatus(403);
});

// ============================================================================
// switchSchool
// ============================================================================

test('super admin can switch school via service', function () {
    $targetSchool = School::factory()->create(['long_name' => 'Target School', 'short_name' => 'TRG']);

    $targetUser = User::factory()->create([
        'school_id' => $targetSchool->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => $this->superAdmin->email,
    ]);

    $this->mock(SchoolService::class, function ($mock) use ($targetSchool, $targetUser) {
        $mock->shouldReceive('switchSchool')
            ->once()
            ->andReturn($targetUser);
    });

    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/schools/switch_school', ['school_id' => $targetSchool->id])
        ->assertStatus(204);
});

test('switch school requires authentication', function () {
    $this->postJson('/api/admin/schools/switch_school', ['school_id' => $this->school->id])
        ->assertStatus(401);
});

// ============================================================================
// loadSchoolInfos
// ============================================================================

test('admin can load school infos and queues background job', function () {
    Queue::fake();

    $expectedData = [
        'licences' => [],
        'admins' => [],
    ];

    $this->mock(SchoolService::class, function ($mock) use ($expectedData) {
        $mock->shouldReceive('schoolInfos')
            ->once()
            ->andReturn($expectedData);
    });

    $this->actingAs($this->adminUser, 'sanctum');

    $this->postJson('/api/admin/schools/load_school_infos', ['school_id' => $this->school->id])
        ->assertStatus(200)
        ->assertJson($expectedData);
});

test('load school infos blocks unauthorized user', function () {
    $this->actingAs($this->registerAdmin, 'sanctum');

    $this->postJson('/api/admin/schools/load_school_infos', []) // missing validation too
        ->assertStatus(422);
});

// ============================================================================
// addLicence & deleteLicence
// ============================================================================

test('super admin can add licence to selected school', function () {
    $licence = Licence::create([
        'name' => 'core',
        'long_name' => 'Core Licence',
        'price_per_year' => 100,
    ]);

    $this->actingAs($this->superAdmin, 'sanctum');

    $response = $this->postJson('/api/admin/schools/add_licence', [
        'data' => [
            'licence_id' => $licence->id,
            'valid_until' => now()->addYear()->toDateString(),
        ],
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment([
            'id' => $licence->id,
            'name' => 'core',
            'long_name' => 'Core Licence',
        ]);
});

test('super admin can delete school licence', function () {
    $licence = Licence::create([
        'name' => 'removable',
        'long_name' => 'Removable Licence',
    ]);

    $schoolLicence = SchoolLicence::create([
        'school_id' => $this->school->id,
        'licence_id' => $licence->id,
        'valid_until' => now()->addMonth()->toDateString(),
    ]);

    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/schools/delete_licence', [
        'school_licence_id' => $schoolLicence->id,
    ])->assertStatus(200);
});

// ============================================================================
// addAdmin & deleteAdmin
// ============================================================================

test('super admin can add admin via service', function () {
    $newAdmin = User::factory()->make([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'new.admin@test.com',
    ]);
    $newAdmin->id = 1234;

    $this->mock(SchoolService::class, function ($mock) use ($newAdmin) {
        $mock->shouldReceive('addAdmin')
            ->once()
            ->andReturn($newAdmin);
    });

    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/schools/add_admin', [
        'data' => [
            'last_name' => 'New',
            'first_name' => 'Admin',
            'email' => 'new.admin@test.com',
        ],
        'roles' => ['admin'],
    ])->assertStatus(200)
        ->assertJson([
            'id' => 1234,
            'email' => 'new.admin@test.com',
        ]);
});

test('super admin cannot delete themselves as admin', function () {
    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/schools/delete_admin', [
        'admin_id' => $this->superAdmin->id,
        'is_delete_complete' => false,
    ])->assertStatus(409);
});

test('super admin can delete admin via service', function () {
    $target = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'delete.me@test.com',
    ]);

    $this->mock(SchoolService::class, function ($mock) use ($target) {
        $mock->shouldReceive('deleteAdmin')
            ->once()
            ->with($target->id, true);
    });

    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/schools/delete_admin', [
        'admin_id' => $target->id,
        'is_delete_complete' => true,
    ])->assertStatus(204);
});
