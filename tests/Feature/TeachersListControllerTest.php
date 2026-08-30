<?php

/**
 * TeachersListController Tests
 *
 * Tests the Teachers List (Excel import) controller including:
 * - index (list teachers from Teacher model)
 * - upload (initiate chunked file upload)
 * - uploadNext (handle chunked upload and trigger import job)
 *
 * All endpoints require admin role
 */

use App\Jobs\ImportTeachersListJob;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Storage::fake('local');

    // Create test school and schoolyear
    $this->school = School::factory()->create([
        'long_name' => 'Test School',
        'short_name' => 'TS',
    ]);

    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'name' => '2024/2025',
    ]);

    // Create roles
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

    // Create admin user
    $this->adminUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Admin',
        'last_name' => 'User',
        'email' => 'admin@test.com',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
    ]);
    $this->adminUser->assignRole('admin');

    // Create non-admin user
    $this->normalUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Normal',
        'last_name' => 'User',
        'email' => 'user@test.com',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
    ]);
    $this->normalUser->assignRole('user');

    Cache::forget(ImportTeachersListJob::statusCacheKey($this->school->id, $this->adminUser->id));
});

// ============================================================================
// Index Tests - Authorization
// ============================================================================

test('admin can access index endpoint', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $response = $this->getJson('/api/admin/teachers_list');

    $response->assertStatus(200);
});

test('non-admin cannot access index endpoint', function () {
    $this->actingAs($this->normalUser, 'sanctum');

    $response = $this->getJson('/api/admin/teachers_list');

    $response->assertStatus(403);
});

test('guest cannot access index endpoint', function () {
    $response = $this->getJson('/api/admin/teachers_list');

    $response->assertStatus(401);
});

test('admin can poll the current teacher list import status', function () {
    ImportTeachersListJob::markRunning($this->school->id, $this->adminUser->id);

    $this->actingAs($this->adminUser, 'sanctum')
        ->getJson('/api/admin/teachers_list_import_status')
        ->assertSuccessful()
        ->assertJsonPath('state', 'running')
        ->assertJsonPath('status', null);

    ImportTeachersListJob::markFinished(
        $this->school->id,
        $this->adminUser->id,
        200,
        'Lehrerliste importiert.',
        ['created' => 2],
    );

    $this->actingAs($this->adminUser, 'sanctum')
        ->getJson('/api/admin/teachers_list_import_status')
        ->assertSuccessful()
        ->assertJsonPath('state', 'finished')
        ->assertJsonPath('status', 200)
        ->assertJsonPath('message', 'Lehrerliste importiert.')
        ->assertJsonPath('data.created', 2);
});

test('non-admin cannot poll the teacher list import status', function () {
    $this->actingAs($this->normalUser, 'sanctum')
        ->getJson('/api/admin/teachers_list_import_status')
        ->assertForbidden();
});

// ============================================================================
// Index Tests - Functionality
// ============================================================================

test('index returns teachers from Teacher model', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    // Create teachers in Teacher model
    Teacher::create([
        'school_id' => $this->school->id,
        'short' => 'KRO',
        'first_name' => 'Max',
        'last_name' => 'Mustermann',
        'email' => 'max@test.com',
    ]);

    Teacher::create([
        'school_id' => $this->school->id,
        'short' => 'MUS',
        'first_name' => 'Maria',
        'last_name' => 'Mueller',
        'email' => 'maria@test.com',
    ]);

    $response = $this->getJson('/api/admin/teachers_list');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'items');

    $teachers = $response->json('items');
    expect($teachers[0]['short'])->toBe('MUS')
        ->and($teachers[1]['short'])->toBe('KRO');
});

test('index filters by school_id', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $otherSchool = School::factory()->create();

    // Create teacher in current school
    Teacher::create([
        'school_id' => $this->school->id,
        'short' => 'KRO',
        'first_name' => 'Max',
        'last_name' => 'Mustermann',
        'email' => 'max@test.com',
    ]);

    // Create teacher in other school
    Teacher::create([
        'school_id' => $otherSchool->id,
        'short' => 'MUS',
        'first_name' => 'Maria',
        'last_name' => 'Mueller',
        'email' => 'maria@test.com',
    ]);

    $response = $this->getJson('/api/admin/teachers_list');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'items');

    $teachers = $response->json('items');
    expect($teachers[0]['short'])->toBe('KRO');
});

test('index excludes teacher list entries that already have a registered school user', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    Teacher::create([
        'school_id' => $this->school->id,
        'short' => 'REG',
        'first_name' => 'Registered',
        'last_name' => 'Teacher',
        'email' => 'registered@example.test',
    ]);
    User::factory()->create([
        'school_id' => $this->school->id,
        'email' => 'registered@example.test',
    ]);
    Teacher::create([
        'school_id' => $this->school->id,
        'short' => 'NEW',
        'first_name' => 'New',
        'last_name' => 'Teacher',
        'email' => 'new@example.test',
    ]);

    $response = $this->getJson('/api/admin/teachers_list');

    $response->assertOk()
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('items.0.email', 'new@example.test');
});

test('index orders alphabetically by last name first name and short', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    Teacher::create([
        'school_id' => $this->school->id,
        'short' => 'ZZZ',
        'first_name' => 'Anna',
        'last_name' => 'Abele',
        'email' => 'anna.abele@test.com',
    ]);

    Teacher::create([
        'school_id' => $this->school->id,
        'short' => 'MMM',
        'first_name' => 'Michaela',
        'last_name' => 'Mayr',
        'email' => 'michaela.mayr@test.com',
    ]);

    Teacher::create([
        'school_id' => $this->school->id,
        'short' => 'AAA',
        'first_name' => 'Zoe',
        'last_name' => 'Zeller',
        'email' => 'zoe.zeller@test.com',
    ]);

    $response = $this->getJson('/api/admin/teachers_list');

    $teachers = $response->json('items');
    expect(array_column($teachers, 'last_name'))->toBe(['Abele', 'Mayr', 'Zeller']);
});
