<?php

/**
 * UserWithRoleController Tests
 *
 * Covers:
 * - Role listing and assignment
 * - Filtering lists by role and search
 * - CRUD operations with role guards and self-protection
 */

use App\Models\Role;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

    $school = $this->school;
    $schoolyear = $this->schoolyear;
    User::creating(function ($user) use ($school, $schoolyear) {
        $user->school_id ??= $school->id;
        $user->schoolyear_id ??= $schoolyear->id;
    });

    collect(['super_admin', 'admin', 'teacher'])->each(
        fn (string $role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])
    );

    $this->superAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'super@test.com',
    ]);
    $this->superAdmin->assignRole('super_admin');

    $this->adminUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'admin@test.com',
    ]);
    $this->adminUser->assignRole('admin');
});

// ============================================================================
// roles
// ============================================================================

test('admin can load roles excluding super_admin', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $response = $this->getJson('/api/admin/users_with_roles/roles');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'roles' => [
                ['id', 'name'],
            ],
        ]);

    $names = collect($response->json('roles'))->pluck('name')->all();
    expect($names)->toEqualCanonicalizing(['admin', 'teacher']);
});

test('non admin receives empty roles list', function () {
    $this->actingAs($this->superAdmin, 'sanctum'); // lacks admin role

    $this->getJson('/api/admin/users_with_roles/roles')
        ->assertStatus(200)
        ->assertJson(['roles' => []]);
});

// ============================================================================
// saveUserRoles
// ============================================================================

test('super admin can sync user roles and keeps super_admin intact', function () {
    $target = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'target@test.com',
    ]);
    $target->assignRole('super_admin');

    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/users_with_roles/roles', [
        'id' => $target->id,
        'roles' => ['admin'], // super_admin should persist
    ])->assertStatus(200);

    expect($target->fresh()->hasRole('admin'))->toBeTrue();
    expect($target->fresh()->hasRole('super_admin'))->toBeTrue();
});

test('super admin cannot sync roles for a user from another school', function () {
    $otherSchool = School::factory()->create();
    $otherUser = User::factory()->create([
        'school_id' => $otherSchool->id,
        'email' => 'other-roles@test.com',
    ]);

    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/users_with_roles/roles', [
        'id' => $otherUser->id,
        'roles' => ['teacher'],
    ])->assertNotFound();

    expect($otherUser->fresh()->roles)->toBeEmpty();
});

test('non super admin cannot sync user roles', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $this->postJson('/api/admin/users_with_roles/roles', [
        'id' => $this->adminUser->id,
        'roles' => ['teacher'],
    ])->assertStatus(403);
});

// ============================================================================
// index
// ============================================================================

test('admin can list users filtered by role', function () {
    $teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'teacher@test.com',
    ]);
    $teacher->assignRole('teacher');

    $this->actingAs($this->adminUser, 'sanctum');

    $response = $this->getJson('/api/admin/users_with_roles?'.http_build_query([
        'search_model' => ['role' => 'teacher'],
    ]));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'pagination' => ['current_page', 'last_page', 'next_page', 'prev_page', 'per_page', 'total'],
            'items' => [
                ['id', 'first_name', 'last_name', 'email', 'roles'],
            ],
        ]);

    $items = collect($response->json('items'));
    expect($items->count())->toBe(1);
    expect($items->first()['email'])->toBe('teacher@test.com');
});

test('admin user listing excludes users from other schools', function () {
    $otherSchool = School::factory()->create();
    User::factory()->create([
        'school_id' => $otherSchool->id,
        'email' => 'hidden-user@test.com',
    ]);

    $this->actingAs($this->adminUser, 'sanctum');

    $response = $this->getJson('/api/admin/users_with_roles')->assertOk();

    expect(collect($response->json('items'))->pluck('email'))
        ->not->toContain('hidden-user@test.com');
});

test('user without admin role cannot list users with roles', function () {
    $regular = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'regular@test.com',
    ]);
    $regular->assignRole('teacher');

    $this->actingAs($regular, 'sanctum');

    $this->getJson('/api/admin/users_with_roles')
        ->assertStatus(403);
});

// ============================================================================
// store
// ============================================================================

test('admin can create user and flags are set', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $this->postJson('/api/admin/users_with_roles', [
        'last_name' => 'New',
        'first_name' => 'User',
        'email' => 'new@test.com',
        'is_active' => true,
        'is_confirmed' => true,
        'is_verified' => true,
        'is_2fa' => false,
    ])->assertStatus(200)
        ->assertJsonFragment([
            'last_name' => 'New',
            'first_name' => 'User',
            'email' => 'new@test.com',
            'is_active' => true,
            'is_confirmed' => true,
            'is_verified' => true,
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'new@test.com',
    ]);
});

test('store requires admin role', function () {
    $regular = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $regular->assignRole('teacher');
    $this->actingAs($regular, 'sanctum');

    $this->postJson('/api/admin/users_with_roles', [
        'last_name' => 'Blocked',
        'email' => 'blocked@test.com',
    ])->assertStatus(403);
});

// ============================================================================
// update
// ============================================================================

test('admin can update user details', function () {
    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'update@test.com',
    ]);

    $this->actingAs($this->adminUser, 'sanctum');

    $response = $this->putJson("/api/admin/users_with_roles/{$user->id}", [
        'id' => $user->id,
        'last_name' => 'Updated',
        'first_name' => 'Person',
        'email' => 'updated@test.com',
        'is_active' => true,
        'is_confirmed' => true,
        'is_verified' => true,
        'is_2fa' => false,
    ]);

    $response->assertStatus(200);
});

test('admin cannot deactivate a super admin', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $this->putJson("/api/admin/users_with_roles/{$this->superAdmin->id}", [
        'id' => $this->superAdmin->id,
        'last_name' => $this->superAdmin->last_name,
        'first_name' => $this->superAdmin->first_name,
        'email' => $this->superAdmin->email,
        'is_active' => false,
        'is_confirmed' => true,
        'is_verified' => true,
        'is_2fa' => false,
    ])->assertForbidden();

    expect((bool) $this->superAdmin->fresh()->is_active)->toBeTrue();
});

test('admin cannot update a user from another school', function () {
    $otherSchool = School::factory()->create();
    $otherUser = User::factory()->create([
        'school_id' => $otherSchool->id,
        'email' => 'other-update@test.com',
    ]);

    $this->actingAs($this->adminUser, 'sanctum');

    $this->putJson("/api/admin/users_with_roles/{$otherUser->id}", [
        'id' => $otherUser->id,
        'last_name' => 'Blocked',
        'first_name' => 'Person',
        'email' => 'blocked-update@test.com',
        'is_active' => true,
        'is_confirmed' => true,
        'is_verified' => true,
        'is_2fa' => false,
    ])->assertForbidden();

    expect($otherUser->fresh()->email)->toBe('other-update@test.com');
});

// ============================================================================
// show
// ============================================================================

test('super admin can show user; admin cannot', function () {
    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'show@test.com',
    ]);

    $this->actingAs($this->adminUser, 'sanctum');
    $this->getJson("/api/admin/users_with_roles/{$user->id}")
        ->assertStatus(403);

    $this->actingAs($this->superAdmin, 'sanctum');
    $response = $this->getJson("/api/admin/users_with_roles/{$user->id}");
    $response
        ->assertStatus(200);
});
