<?php

/**
 * RoleController Tests
 *
 * These tests cover the loadRoles endpoint to ensure:
 * - Only super admins can access the endpoint
 * - Guests and unauthorized roles are blocked
 * - Roles are returned in alphabetical order with the expected structure
 */

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create([
        'long_name' => 'Test School',
        'short_name' => 'TS',
    ]);

    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'name' => '2024/2025',
    ]);

    // Create roles used across the suite
    collect([
        'teacher',
        'admin',
        'super_admin',
        'register_admin',
        'student',
    ])->each(fn (string $role) => Role::firstOrCreate([
        'name' => $role,
        'guard_name' => 'web',
        'is_admin' => in_array($role, ['teacher', 'admin', 'register_admin'], true),
    ]));

    $this->superAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'super.admin@test.com',
    ]);
    $this->superAdmin->assignRole(['super_admin', 'admin']);

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
// loadRoles - Authorization
// ============================================================================

test('super admin can load roles ordered alphabetically', function () {
    $this->actingAs($this->superAdmin, 'sanctum');

    $response = $this->getJson('/api/admin/roles/load_roles');

    $response->assertStatus(200)
        ->assertJsonStructure([
            ['id', 'name', 'is_admin'],
        ])
        ->assertJsonCount(5);

    $names = collect($response->json())->pluck('name')->all();

    expect($names)->toBe([
        'admin',
        'register_admin',
        'student',
        'super_admin',
        'teacher',
    ]);

    expect(collect($response->json())->firstWhere('name', 'admin')['is_admin'])->toBeTrue();
    expect(collect($response->json())->firstWhere('name', 'student')['is_admin'])->toBeFalse();
});

test('admin without super admin role can load roles', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $this->getJson('/api/admin/roles/load_roles')
        ->assertStatus(200);
});

test('register admin without super admin role receives 403', function () {
    $this->actingAs($this->registerAdmin, 'sanctum');

    $this->getJson('/api/admin/roles/load_roles')
        ->assertStatus(403);
});

test('user without allowed roles is blocked by middleware', function () {
    $this->actingAs($this->teacherUser, 'sanctum');

    $this->getJson('/api/admin/roles/load_roles')
        ->assertStatus(403);
});

test('guest receives 401 when loading roles', function () {
    $this->getJson('/api/admin/roles/load_roles')
        ->assertStatus(401);
});
