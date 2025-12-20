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

use App\Events\TeachersListImportFinishedEvent;
use App\Jobs\ImportTeachersListJob;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\Teacher;
use App\Models\User;
use App\Services\FileUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
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
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Role::create(['name' => 'user', 'guard_name' => 'web']);

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
        ->assertJsonCount(2);

    $teachers = $response->json();
    expect($teachers[0]['short'])->toBe('KRO')
        ->and($teachers[1]['short'])->toBe('MUS');
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
        ->assertJsonCount(1);

    $teachers = $response->json();
    expect($teachers[0]['short'])->toBe('KRO');
});

test('index orders by short and last_name', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    Teacher::create([
        'school_id' => $this->school->id,
        'short' => 'MUS',
        'first_name' => 'Maria',
        'last_name' => 'Mueller',
        'email' => 'maria@test.com',
    ]);

    Teacher::create([
        'school_id' => $this->school->id,
        'short' => 'KRO',
        'first_name' => 'Max',
        'last_name' => 'Mustermann',
        'email' => 'max@test.com',
    ]);

    $response = $this->getJson('/api/admin/teachers_list');

    $teachers = $response->json();
    expect($teachers[0]['short'])->toBe('KRO')
        ->and($teachers[1]['short'])->toBe('MUS');
});

// ============================================================================
// Upload Tests - Authorization
// ============================================================================

test('admin can initiate upload', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $mock = $this->mock(FileUploadService::class, function ($mock) {
        $mock->shouldReceive('upload')
            ->once()
            ->andReturn('upload_id_123');
    });

    $response = $this->postJson('/api/admin/teachers_list/upload');

    $response->assertStatus(200);
    expect($response->getContent())->toBe('upload_id_123');
});

test('non-admin cannot initiate upload', function () {
    $this->actingAs($this->normalUser, 'sanctum');

    $response = $this->postJson('/api/admin/teachers_list/upload');

    $response->assertStatus(403);
});

// ============================================================================
// UploadNext Tests - Authorization
// ============================================================================

test('admin can upload chunks', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $mock = $this->mock(FileUploadService::class, function ($mock) {
        $mock->shouldReceive('uploadNext')
            ->once()
            ->andReturn(response('OK', 200));
    });

    $response = $this->postJson('/api/admin/teachers_list/upload_next', [
        'chunk' => 'chunk_data',
    ]);

    $response->assertStatus(200);
});

test('non-admin cannot upload chunks', function () {
    $this->actingAs($this->normalUser, 'sanctum');

    $response = $this->postJson('/api/admin/teachers_list/upload_next', [
        'chunk' => 'chunk_data',
    ]);

    $response->assertStatus(403);
});

// ============================================================================
// UploadNext Tests - Functionality
// ============================================================================

test('uploadNext dispatches import job when upload completes', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $filename = 'teachers_list.xlsx';

    $mock = $this->mock(FileUploadService::class, function ($mock) use ($filename) {
        $mock->shouldReceive('uploadNext')
            ->once()
            ->with(
                \Mockery::any(),
                "app/private/{$this->adminUser->school_id}/excel",
                "teachers_list"
            )
            ->andReturn($filename);
    });

    $response = $this->postJson('/api/admin/teachers_list/upload_next', [
        'chunk' => 'final_chunk',
    ]);

    $response->assertStatus(200);
    expect($response->getContent())->toBe($filename);

    Queue::assertPushed(ImportTeachersListJob::class, function ($job) use ($filename) {
        return $job->user->id === $this->adminUser->id
            && $job->path === "app/private/{$this->adminUser->school_id}/excel/{$filename}";
    });
});

test('uploadNext returns response for partial chunks', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $mock = $this->mock(FileUploadService::class, function ($mock) {
        $mock->shouldReceive('uploadNext')
            ->once()
            ->andReturn(response('OK', 200));
    });

    $response = $this->postJson('/api/admin/teachers_list/upload_next', [
        'chunk' => 'partial_chunk',
    ]);

    $response->assertStatus(200);
    expect($response->getContent())->toBe('OK');

    Queue::assertNotPushed(ImportTeachersListJob::class);
});

test('uploadNext uses correct directory path', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $expectedDirectory = "app/private/{$this->adminUser->school_id}/excel";

    $mock = $this->mock(FileUploadService::class, function ($mock) use ($expectedDirectory) {
        $mock->shouldReceive('uploadNext')
            ->once()
            ->with(
                \Mockery::any(),
                $expectedDirectory,
                "teachers_list"
            )
            ->andReturn('teachers_list.xlsx');
    });

    $response = $this->postJson('/api/admin/teachers_list/upload_next', [
        'chunk' => 'chunk_data',
    ]);

    $response->assertStatus(200);
});

test('uploadNext returns filename as plain text', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $filename = 'teachers_list_12345.xlsx';

    $mock = $this->mock(FileUploadService::class, function ($mock) use ($filename) {
        $mock->shouldReceive('uploadNext')
            ->once()
            ->andReturn($filename);
    });

    $response = $this->postJson('/api/admin/teachers_list/upload_next');

    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    expect($response->getContent())->toBe($filename);
});
