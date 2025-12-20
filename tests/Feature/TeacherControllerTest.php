<?php

/**
 * TeacherController Tests
 *
 * Tests the Teacher management controller including:
 * - index (list teachers with pagination and search)
 * - store (create new teacher)
 * - update (update existing teacher)
 * - deleteTeachers (bulk delete teachers)
 *
 * All endpoints require admin role
 */

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\TeacherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
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
    Role::create(['name' => 'teacher', 'guard_name' => 'web']);
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

    $response = $this->getJson('/api/admin/teachers');

    $response->assertStatus(200);
});

test('non-admin cannot access index endpoint', function () {
    $this->actingAs($this->normalUser, 'sanctum');

    $response = $this->getJson('/api/admin/teachers');

    $response->assertStatus(403);
});

test('guest cannot access index endpoint', function () {
    $response = $this->getJson('/api/admin/teachers');

    $response->assertStatus(401);
});

// ============================================================================
// Index Tests - Functionality
// ============================================================================

test('index returns paginated teachers', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    // Create 3 teachers
    for ($i = 1; $i <= 3; $i++) {
        $teacher = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'short' => "T{$i}",
            'first_name' => "Teacher{$i}",
            'last_name' => "Last{$i}",
            'email' => "teacher{$i}@test.com",
        ]);
        $teacher->assignRole('teacher');
    }

    $response = $this->getJson('/api/admin/teachers');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'short', 'first_name', 'last_name', 'email'],
            ],
            'meta' => ['current_page', 'per_page', 'total'],
        ]);

    expect($response->json('data'))->toHaveCount(3);
});

test('index filters teachers by school_id', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $otherSchool = School::factory()->create();
    $otherSchoolyear = Schoolyear::factory()->create(['school_id' => $otherSchool->id]);

    // Create teacher in current school
    $teacher1 = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'short' => 'T1',
        'email' => 'teacher1@test.com',
    ]);
    $teacher1->assignRole('teacher');

    // Create teacher in other school
    $teacher2 = User::factory()->create([
        'school_id' => $otherSchool->id,
        'schoolyear_id' => $otherSchoolyear->id,
        'short' => 'T2',
        'email' => 'teacher2@test.com',
    ]);
    $teacher2->assignRole('teacher');

    $response = $this->getJson('/api/admin/teachers');

    $response->assertStatus(200);
    $teachers = $response->json('data');

    expect($teachers)->toHaveCount(1)
        ->and($teachers[0]['email'])->toBe('teacher1@test.com');
});

test('index searches by short', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $teacher1 = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'short' => 'KRO',
        'last_name' => 'Kron',
        'email' => 'kron@test.com',
    ]);
    $teacher1->assignRole('teacher');

    $teacher2 = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'short' => 'MUS',
        'last_name' => 'Mueller',
        'email' => 'mueller@test.com',
    ]);
    $teacher2->assignRole('teacher');

    $response = $this->getJson('/api/admin/teachers?search_string=KRO');

    $response->assertStatus(200);
    $teachers = $response->json('data');

    expect($teachers)->toHaveCount(1)
        ->and($teachers[0]['short'])->toBe('KRO');
});

test('index searches by last_name', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $teacher1 = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'short' => 'KRO',
        'last_name' => 'Kron',
        'email' => 'kron@test.com',
    ]);
    $teacher1->assignRole('teacher');

    $teacher2 = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'short' => 'MUS',
        'last_name' => 'Mueller',
        'email' => 'mueller@test.com',
    ]);
    $teacher2->assignRole('teacher');

    $response = $this->getJson('/api/admin/teachers?search_string=Kron');

    $response->assertStatus(200);
    $teachers = $response->json('data');

    expect($teachers)->toHaveCount(1)
        ->and($teachers[0]['last_name'])->toBe('Kron');
});

test('index orders by short and last_name', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $teacher1 = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'short' => 'MUS',
        'last_name' => 'Mueller',
        'email' => 'mueller@test.com',
    ]);
    $teacher1->assignRole('teacher');

    $teacher2 = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'short' => 'KRO',
        'last_name' => 'Kron',
        'email' => 'kron@test.com',
    ]);
    $teacher2->assignRole('teacher');

    $response = $this->getJson('/api/admin/teachers');

    $response->assertStatus(200);
    $teachers = $response->json('data');

    expect($teachers[0]['short'])->toBe('KRO')
        ->and($teachers[1]['short'])->toBe('MUS');
});

// ============================================================================
// Store Tests - Authorization
// ============================================================================

test('admin can create teacher', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $data = [
        'short' => 'KRO',
        'first_name' => 'Max',
        'last_name' => 'Mustermann',
        'email' => 'max@test.com',
    ];

    $response = $this->postJson('/api/admin/teachers', $data);

    $response->assertStatus(200)
        ->assertJsonStructure(['id', 'short', 'first_name', 'last_name', 'email']);

    expect(User::where('email', 'max@test.com')->exists())->toBeTrue();
});

test('non-admin cannot create teacher', function () {
    $this->actingAs($this->normalUser, 'sanctum');

    $data = [
        'short' => 'KRO',
        'first_name' => 'Max',
        'last_name' => 'Mustermann',
        'email' => 'max@test.com',
    ];

    $response = $this->postJson('/api/admin/teachers', $data);

    $response->assertStatus(403);
});

// ============================================================================
// Store Tests - Validation
// ============================================================================

test('store validates required fields', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $response = $this->postJson('/api/admin/teachers', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['short', 'first_name', 'last_name', 'email']);
});

test('store validates email format', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $data = [
        'short' => 'KRO',
        'first_name' => 'Max',
        'last_name' => 'Mustermann',
        'email' => 'invalid-email',
    ];

    $response = $this->postJson('/api/admin/teachers', $data);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

// ============================================================================
// Update Tests - Authorization
// ============================================================================

test('admin can update teacher', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'short' => 'KRO',
        'first_name' => 'Max',
        'last_name' => 'Mustermann',
        'email' => 'max@test.com',
    ]);
    $teacher->assignRole('teacher');

    $data = [
        'id' => $teacher->id,
        'short' => 'MUS',
        'first_name' => 'Maria',
        'last_name' => 'Mueller',
        'email' => 'maria@test.com',
    ];

    $response = $this->putJson("/api/admin/teachers/{$teacher->id}", $data);

    $response->assertStatus(200);

    $teacher->refresh();
    expect($teacher->short)->toBe('MUS')
        ->and($teacher->first_name)->toBe('Maria')
        ->and($teacher->email)->toBe('maria@test.com');
});

test('non-admin cannot update teacher', function () {
    $this->actingAs($this->normalUser, 'sanctum');

    $teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'short' => 'KRO',
        'email' => 'max@test.com',
    ]);
    $teacher->assignRole('teacher');

    $data = [
        'id' => $teacher->id,
        'short' => 'MUS',
        'first_name' => 'Maria',
        'last_name' => 'Mueller',
        'email' => 'maria@test.com',
    ];

    $response = $this->putJson("/api/admin/teachers/{$teacher->id}", $data);

    $response->assertStatus(403);
});

// ============================================================================
// DeleteTeachers Tests - Authorization
// ============================================================================

test('admin can delete teachers', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $teacher1 = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'short' => 'T1',
        'email' => 'teacher1@test.com',
    ]);
    $teacher1->assignRole('teacher');

    $teacher2 = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'short' => 'T2',
        'email' => 'teacher2@test.com',
    ]);
    $teacher2->assignRole('teacher');

    $response = $this->postJson('/api/admin/teachers/delete_teachers', [
        'data' => [$teacher1->id, $teacher2->id],
    ]);

    $response->assertStatus(204);

    expect(User::find($teacher1->id))->toBeNull()
        ->and(User::find($teacher2->id))->toBeNull();
});

test('non-admin cannot delete teachers', function () {
    $this->actingAs($this->normalUser, 'sanctum');

    $teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'short' => 'T1',
        'email' => 'teacher1@test.com',
    ]);
    $teacher->assignRole('teacher');

    $response = $this->postJson('/api/admin/teachers/delete_teachers', [
        'data' => [$teacher->id],
    ]);

    $response->assertStatus(403);
});

// ============================================================================
// DeleteTeachers Tests - Functionality
// ============================================================================

test('deleteTeachers calls service with correct parameters', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $teacher1 = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'short' => 'T1',
        'email' => 'teacher1@test.com',
    ]);
    $teacher1->assignRole('teacher');

    $teacher2 = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'short' => 'T2',
        'email' => 'teacher2@test.com',
    ]);
    $teacher2->assignRole('teacher');

    $mock = $this->mock(TeacherService::class, function ($mock) use ($teacher1, $teacher2) {
        $mock->shouldReceive('deleteTeachers')
            ->once()
            ->with($this->school->id, [$teacher1->id, $teacher2->id]);
    });

    $response = $this->postJson('/api/admin/teachers/delete_teachers', [
        'data' => [$teacher1->id, $teacher2->id],
    ]);

    $response->assertStatus(204);
});
