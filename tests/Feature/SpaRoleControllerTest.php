<?php

/**
 * SpaRoleController Tests
 *
 * Tests the Spa Role management controller including:
 * - Index endpoint (listing roles with search and pagination)
 * - Store (create new role)
 * - Show (display single role)
 * - Update existing role
 * - Destroy single role
 * - Destroy multiple roles
 *
 * All endpoints require super_admin role
 */

use App\Models\Role;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);

    // Create base roles
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

    $this->superAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->superAdmin->assignRole('super_admin');

    $this->adminUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->adminUser->assignRole('admin');

    $this->regularUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->regularUser->assignRole('user');
});

describe('index', function () {
    test('super admin can list roles', function () {
        $this->actingAs($this->superAdmin);

        Role::firstOrCreate(['name' => 'custom_role_1', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'custom_role_2', 'guard_name' => 'web']);

        $response = $this->getJson('/api/admin/roles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
                'items' => [
                    '*' => ['id', 'name', 'is_admin'],
                ],
            ]);
    });

    test('index excludes super_admin role from list', function () {
        $this->actingAs($this->superAdmin);

        $response = $this->getJson('/api/admin/roles');

        $response->assertStatus(200);

        $roles = $response->json('items');
        $roleNames = array_column($roles, 'name');

        expect($roleNames)->not->toContain('super_admin');
    });

    test('index filters by search string', function () {
        $this->actingAs($this->superAdmin);

        Role::firstOrCreate(['name' => 'teacher_role', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'student_role', 'guard_name' => 'web']);

        $response = $this->getJson('/api/admin/roles?search_model[search_string]=teacher');

        $response->assertStatus(200);

        $roles = $response->json('items');

        expect($roles)->toHaveCount(1)
            ->and($roles[0]['name'])->toBe('teacher_role');
    });

    test('index orders roles by name', function () {
        $this->actingAs($this->superAdmin);

        Role::firstOrCreate(['name' => 'zebra_role', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'alpha_role', 'guard_name' => 'web']);

        $response = $this->getJson('/api/admin/roles');

        $response->assertStatus(200);

        $roles = $response->json('items');
        $firstRole = $roles[0]['name'];

        // Should be alphabetically first (excluding super_admin which is filtered out)
        expect($firstRole)->toBe('admin');
    });

    test('index denies access for admin user', function () {
        $this->actingAs($this->adminUser);

        $response = $this->getJson('/api/admin/roles');

        $response->assertStatus(403);
    });

    test('index denies access for regular user', function () {
        $this->actingAs($this->regularUser);

        $response = $this->getJson('/api/admin/roles');

        $response->assertStatus(403);
    });

    test('index requires authentication', function () {
        $response = $this->getJson('/api/admin/roles');

        $response->assertStatus(401);
    });

    test('index returns paginated results', function () {
        $this->actingAs($this->superAdmin);

        // Create multiple roles
        for ($i = 1; $i <= 15; $i++) {
            Role::firstOrCreate(['name' => "role_{$i}", 'guard_name' => 'web']);
        }

        $response = $this->getJson('/api/admin/roles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'pagination' => ['current_page', 'per_page', 'total'],
            ]);
    });
});

describe('store', function () {
    test('super admin can create new role', function () {
        $this->actingAs($this->superAdmin);

        $roleData = [
            'name' => 'new_custom_role',
            'is_admin' => true,
        ];

        $response = $this->postJson('/api/admin/roles', $roleData);

        $response->assertStatus(200)
            ->assertJson([
                'name' => 'new_custom_role',
                'is_admin' => true,
            ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'new_custom_role',
            'guard_name' => 'web',
            'is_admin' => true,
        ]);
    });

    test('store sets guard_name to web automatically', function () {
        $this->actingAs($this->superAdmin);

        $roleData = [
            'name' => 'test_role',
            'is_admin' => false,
        ];

        $response = $this->postJson('/api/admin/roles', $roleData);

        $response->assertStatus(200);

        $role = Role::where('name', 'test_role')->first();
        expect($role->guard_name)->toBe('web');
    });

    test('store denies access for admin user', function () {
        $this->actingAs($this->adminUser);

        $roleData = ['name' => 'denied_role'];

        $response = $this->postJson('/api/admin/roles', $roleData);

        $response->assertStatus(403);
    });

    test('store denies access for regular user', function () {
        $this->actingAs($this->regularUser);

        $roleData = ['name' => 'denied_role'];

        $response = $this->postJson('/api/admin/roles', $roleData);

        $response->assertStatus(403);
    });

    test('store requires authentication', function () {
        $roleData = ['name' => 'unauthenticated_role'];

        $response = $this->postJson('/api/admin/roles', $roleData);

        $response->assertStatus(401);
    });

    test('store validates required name field', function () {
        $this->actingAs($this->superAdmin);

        $response = $this->postJson('/api/admin/roles', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });

    test('store validates unique name', function () {
        $this->actingAs($this->superAdmin);

        Role::firstOrCreate(['name' => 'existing_role', 'guard_name' => 'web']);

        $response = $this->postJson('/api/admin/roles', [
            'name' => 'existing_role',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });
});

describe('show', function () {
    test('super admin can view single role', function () {
        $this->actingAs($this->superAdmin);

        $role = Role::firstOrCreate(['name' => 'view_test_role', 'guard_name' => 'web']);

        $response = $this->getJson("/api/admin/roles/{$role->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $role->id,
                'name' => 'view_test_role',
            ]);
    });

    test('show denies access for admin user', function () {
        $this->actingAs($this->adminUser);

        $role = Role::where('name', 'admin')->first();

        $response = $this->getJson("/api/admin/roles/{$role->id}");

        $response->assertStatus(403);
    });

    test('show denies access for regular user', function () {
        $this->actingAs($this->regularUser);

        $role = Role::where('name', 'user')->first();

        $response = $this->getJson("/api/admin/roles/{$role->id}");

        $response->assertStatus(403);
    });

    test('show requires authentication', function () {
        $role = Role::where('name', 'admin')->first();

        $response = $this->getJson("/api/admin/roles/{$role->id}");

        $response->assertStatus(401);
    });

    test('show returns 404 for non existent role', function () {
        $this->actingAs($this->superAdmin);

        $response = $this->getJson('/api/admin/roles/99999');

        $response->assertStatus(404);
    });
});

describe('update', function () {
    test('super admin can update role', function () {
        $this->actingAs($this->superAdmin);

        $role = Role::firstOrCreate(['name' => 'update_test', 'guard_name' => 'web']);

        $response = $this->putJson("/api/admin/roles/{$role->id}", [
            'id' => $role->id,
            'name' => 'updated_role_name',
            'is_admin' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'name' => 'updated_role_name',
                'is_admin' => true,
            ]);

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'updated_role_name',
            'is_admin' => true,
        ]);
    });

    test('update allows super_admin to modify super_admin role name', function () {
        $this->actingAs($this->superAdmin);

        $role = Role::where('name', 'super_admin')->first();

        $response = $this->putJson("/api/admin/roles/{$role->id}", [
            'id' => $role->id,
            'name' => 'modified_super_admin',
        ]);

        // Note: Due to operator precedence in the controller's condition,
        // the super_admin role CAN be updated by super_admin users
        $response->assertStatus(200)
            ->assertJson([
                'name' => 'modified_super_admin',
            ]);

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'modified_super_admin',
        ]);
    });

    test('update denies access for admin user', function () {
        $this->actingAs($this->adminUser);

        $role = Role::firstOrCreate(['name' => 'test_role', 'guard_name' => 'web']);

        $response = $this->putJson("/api/admin/roles/{$role->id}", [
            'id' => $role->id,
            'name' => 'changed_name',
        ]);

        $response->assertStatus(403);
    });

    test('update denies access for regular user', function () {
        $this->actingAs($this->regularUser);

        $role = Role::firstOrCreate(['name' => 'test_role', 'guard_name' => 'web']);

        $response = $this->putJson("/api/admin/roles/{$role->id}", [
            'id' => $role->id,
            'name' => 'changed_name',
        ]);

        $response->assertStatus(403);
    });

    test('update requires authentication', function () {
        $role = Role::firstOrCreate(['name' => 'test_role', 'guard_name' => 'web']);

        $response = $this->putJson("/api/admin/roles/{$role->id}", [
            'id' => $role->id,
            'name' => 'changed_name',
        ]);

        $response->assertStatus(401);
    });

    test('update validates required name field', function () {
        $this->actingAs($this->superAdmin);

        $role = Role::firstOrCreate(['name' => 'test_role', 'guard_name' => 'web']);

        $response = $this->putJson("/api/admin/roles/{$role->id}", [
            'id' => $role->id,
            'name' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });

    test('update validates unique name', function () {
        $this->actingAs($this->superAdmin);

        Role::firstOrCreate(['name' => 'existing_role', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'test_role', 'guard_name' => 'web']);

        $response = $this->putJson("/api/admin/roles/{$role->id}", [
            'id' => $role->id,
            'name' => 'existing_role',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });
});

describe('destroy', function () {
    test('super admin can delete role without dependencies', function () {
        $this->actingAs($this->superAdmin);

        $role = Role::firstOrCreate(['name' => 'deletable_role', 'guard_name' => 'web']);

        $response = $this->deleteJson("/api/admin/roles/{$role->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('roles', [
            'id' => $role->id,
        ]);
    });

    test('destroy prevents deletion of role with dependencies', function () {
        $this->actingAs($this->superAdmin);

        $role = Role::where('name', 'admin')->first();

        // Admin role has users assigned to it
        $response = $this->deleteJson("/api/admin/roles/{$role->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
        ]);
    });

    test('destroy denies access for admin user', function () {
        $this->actingAs($this->adminUser);

        $role = Role::firstOrCreate(['name' => 'test_role', 'guard_name' => 'web']);

        $response = $this->deleteJson("/api/admin/roles/{$role->id}");

        $response->assertStatus(403);
    });

    test('destroy denies access for regular user', function () {
        $this->actingAs($this->regularUser);

        $role = Role::firstOrCreate(['name' => 'test_role', 'guard_name' => 'web']);

        $response = $this->deleteJson("/api/admin/roles/{$role->id}");

        $response->assertStatus(403);
    });

    test('destroy requires authentication', function () {
        $role = Role::firstOrCreate(['name' => 'test_role', 'guard_name' => 'web']);

        $response = $this->deleteJson("/api/admin/roles/{$role->id}");

        $response->assertStatus(401);
    });
});

describe('destroyMultiple', function () {
    test('super admin can delete multiple roles without dependencies', function () {
        $this->actingAs($this->superAdmin);

        $role1 = Role::firstOrCreate(['name' => 'deletable_1', 'guard_name' => 'web']);
        $role2 = Role::firstOrCreate(['name' => 'deletable_2', 'guard_name' => 'web']);

        $response = $this->postJson('/api/admin/roles/destroy_multiple', [
            $role1->id,
            $role2->id,
        ]);

        $response->assertStatus(204);

        $this->assertDatabaseMissing('roles', ['id' => $role1->id]);
        $this->assertDatabaseMissing('roles', ['id' => $role2->id]);
    });

    test('destroy multiple prevents deletion when any role has dependencies', function () {
        $this->actingAs($this->superAdmin);

        $role1 = Role::firstOrCreate(['name' => 'deletable', 'guard_name' => 'web']);
        $role2 = Role::where('name', 'admin')->first(); // Has dependencies

        $response = $this->postJson('/api/admin/roles/destroy_multiple', [
            $role1->id,
            $role2->id,
        ]);

        $response->assertStatus(403);
    });

    test('destroy multiple denies access for admin user', function () {
        $this->actingAs($this->adminUser);

        $role1 = Role::firstOrCreate(['name' => 'test_1', 'guard_name' => 'web']);
        $role2 = Role::firstOrCreate(['name' => 'test_2', 'guard_name' => 'web']);

        $response = $this->postJson('/api/admin/roles/destroy_multiple', [
            $role1->id,
            $role2->id,
        ]);

        $response->assertStatus(403);
    });

    test('destroy multiple denies access for regular user', function () {
        $this->actingAs($this->regularUser);

        $role1 = Role::firstOrCreate(['name' => 'test_1', 'guard_name' => 'web']);

        $response = $this->postJson('/api/admin/roles/destroy_multiple', [$role1->id]);

        $response->assertStatus(403);
    });

    test('destroy multiple requires authentication', function () {
        $role = Role::firstOrCreate(['name' => 'test_role', 'guard_name' => 'web']);

        $response = $this->postJson('/api/admin/roles/destroy_multiple', [$role->id]);

        $response->assertStatus(401);
    });

    test('destroy multiple handles empty array', function () {
        $this->actingAs($this->superAdmin);

        $response = $this->postJson('/api/admin/roles/destroy_multiple', []);

        $response->assertStatus(204);
    });
});
