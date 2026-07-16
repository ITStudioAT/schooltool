<?php

/**
 * UserController Tests
 *
 * Focused coverage on:
 * - Role-protected listing and updates (loadUsers, updateUser, index)
 * - Role assignment
 * - Public verification email trigger
 */

use App\Enums\TwoFaResult;
use App\Enums\VerificationResult;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create([
        'long_name' => 'Main School',
        'short_name' => 'MAIN',
        'email' => 'main@example.com',
    ]);

    $this->otherSchool = School::factory()->create([
        'long_name' => 'Other School',
        'short_name' => 'OTH',
        'email' => 'other@example.com',
    ]);

    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'name' => '2024/2025',
    ]);

    collect(['super_admin', 'admin', 'user', 'teacher', 'register_admin', 'lunch_admin'])->each(
        fn (string $role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])
    );

    $this->superAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'last_name' => 'Super',
        'first_name' => 'Admin',
        'email' => 'super@test.com',
    ]);
    $this->superAdmin->assignRole('super_admin');

    $this->adminUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'last_name' => 'Admin',
        'first_name' => 'User',
        'email' => 'admin@test.com',
    ]);
    $this->adminUser->assignRole('admin');

    $this->standardUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'last_name' => 'Standard',
        'first_name' => 'User',
        'email' => 'standard@test.com',
    ]);
    $this->standardUser->assignRole('user');

    $this->lunchAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'last_name' => 'Lunch',
        'first_name' => 'Admin',
        'email' => 'lunch@test.com',
    ]);
    $this->lunchAdmin->assignRole('lunch_admin');
});

// ============================================================================
// storeUser (super admin and admin)
// ============================================================================

test('super admin can store user via UserService', function () {
    $payload = [
        'last_name' => 'Newbie',
        'first_name' => 'Test',
        'email' => 'newbie@test.com',
        'roles' => [
            ['name' => 'admin', 'checked' => true],
        ],
    ];

    $createdUser = User::factory()->make([
        'id' => 999,
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'last_name' => 'Newbie',
        'first_name' => 'Test',
        'email' => $payload['email'],
    ]);

    $this->mock(UserService::class, function ($mock) use ($createdUser, $payload) {
        $mock->shouldReceive('store')
            ->once()
            ->with($this->superAdmin->school_id, $payload)
            ->andReturn($createdUser);
    });

    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/users20/store', $payload)
        ->assertStatus(200)
        ->assertJsonFragment([
            'email' => $payload['email'],
            'last_name' => 'Newbie',
        ]);
});

test('admin can store user with regular roles but not super_admin', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $this->postJson('/api/admin/users20/store', [
        'last_name' => 'Nope',
        'email' => 'nope@test.com',
        'roles' => [
            ['name' => 'teacher', 'checked' => true],
            ['name' => 'super_admin', 'checked' => true],
        ],
    ])->assertStatus(200);

    $createdUser = User::where('email', 'nope@test.com')->firstOrFail();

    expect($createdUser->hasRole('teacher'))->toBeTrue()
        ->and($createdUser->hasRole('super_admin'))->toBeFalse();
});

// ============================================================================
// deleteUsers (super admin and admin)
// ============================================================================

test('super admin can delete users through service', function () {
    $target = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);

    $this->mock(UserService::class, function ($mock) use ($target) {
        $mock->shouldReceive('delete')
            ->once()
            ->with($this->superAdmin->id, [$target->id], $this->superAdmin->school_id);
    });

    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/users20/delete_users', ['data' => [$target->id]])
        ->assertStatus(204);
});

test('deleteUsers is allowed for admin', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $this->postJson('/api/admin/users20/delete_users', ['data' => [$this->standardUser->id]])
        ->assertStatus(204);
});

// ============================================================================
// show / update (admin / register_admin)
// ============================================================================

test('register admin can show a user', function () {
    $registerAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'regadmin@test.com',
    ]);
    $registerAdmin->assignRole('register_admin');

    $this->actingAs($registerAdmin, 'sanctum');

    $this->getJson('/api/admin/users/'.$this->standardUser->id)
        ->assertStatus(200)
        ->assertJsonFragment(['email' => $this->standardUser->email]);
});

test('admin cannot read a user from another school', function () {
    $otherSchoolUser = User::factory()->create([
        'school_id' => $this->otherSchool->id,
        'email' => 'other-school@test.com',
    ]);

    $this->actingAs($this->adminUser, 'sanctum');

    $this->getJson('/api/admin/users/'.$otherSchoolUser->id)
        ->assertNotFound();
});

// ============================================================================
// destroy / destroyMultiple (admin)
// ============================================================================

test('admin can destroy another user', function () {
    $target = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);

    $this->actingAs($this->adminUser, 'sanctum');

    $this->deleteJson('/api/admin/users/'.$target->id)
        ->assertStatus(204);

    expect(User::find($target->id))->toBeNull();
});

test('admin cannot destroy themselves', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $this->deleteJson('/api/admin/users/'.$this->adminUser->id)
        ->assertStatus(403);
});

test('admin can destroy multiple users and not self', function () {
    $targets = User::factory()->count(2)->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ])->pluck('id')->all();

    $this->actingAs($this->adminUser, 'sanctum');

    $this->postJson('/api/admin/users/destroy_multiple', $targets)
        ->assertStatus(204);

    expect(User::whereIn('id', $targets)->count())->toBe(0);
});

test('admin cannot destroy users from another school', function () {
    $otherSchoolUser = User::factory()->create([
        'school_id' => $this->otherSchool->id,
        'email' => 'other-delete@test.com',
    ]);

    $this->actingAs($this->adminUser, 'sanctum');

    $this->deleteJson('/api/admin/users/'.$otherSchoolUser->id)
        ->assertNotFound();

    expect($otherSchoolUser->fresh())->not->toBeNull();
});

test('profile update cannot target a different route user', function () {
    $this->actingAs($this->lunchAdmin, 'sanctum');

    $this->putJson('/api/admin/users/update_profile/'.$this->standardUser->id, [
        'id' => $this->lunchAdmin->id,
        'last_name' => 'Changed',
        'first_name' => 'Lunch',
        'email' => $this->lunchAdmin->email,
        'is_2fa' => false,
    ])->assertForbidden();

    expect($this->standardUser->fresh()->last_name)->toBe('Standard')
        ->and($this->lunchAdmin->fresh()->last_name)->toBe('Lunch');
});

// ============================================================================
// sendVerificationEmail / confirm (admin)
// ============================================================================

test('admin can trigger verification email for ids', function () {
    Notification::fake();

    $this->actingAs($this->adminUser, 'sanctum');

    $this->postJson('/api/admin/users/send_verification_email', [
        'ids' => [$this->standardUser->id],
    ])->assertStatus(200)
        ->assertSee(VerificationResult::EMAIL_SENT->value);
});

test('admin can confirm users', function () {
    Notification::fake();

    $unconfirmed = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'confirmed_at' => null,
    ]);

    $this->actingAs($this->adminUser, 'sanctum');

    $this->postJson('/api/admin/users/confirm', [
        'ids' => [$unconfirmed->id],
    ])->assertStatus(200)
        ->assertSee(VerificationResult::EMAIL_SENT->value);

    expect(User::find($unconfirmed->id)->uuid)->not->toBeNull();
});

// ============================================================================
// loadUsers (super admin and admin)
// ============================================================================

test('super admin can load users for their school with pagination and sorting', function () {
    $alpha = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'last_name' => 'Alpha',
        'first_name' => 'User',
        'email' => 'alpha@test.com',
        'email_verified_at' => '2026-04-01 00:00:00',
        'confirmed_at' => '2026-04-02 00:00:00',
        'login_at' => '2026-04-03 08:15:00',
        'login_ip' => '127.0.0.1',
    ]);
    $beta = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'last_name' => 'Beta',
        'first_name' => 'User',
        'email' => 'beta@test.com',
    ]);
    $otherSchoolUser = User::factory()->create([
        'school_id' => $this->otherSchool->id,
        'schoolyear_id' => $this->schoolyear->id,
        'last_name' => 'Other',
        'first_name' => 'User',
        'email' => 'other@test.com',
    ]);

    $this->actingAs($this->superAdmin, 'sanctum');

    $response = $this->getJson('/api/admin/users20/load_users');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                ['id', 'first_name', 'last_name', 'email', 'email_verified_at', 'confirmed_at', 'login_at', 'login_ip', 'roles'],
            ],
            'meta' => ['current_page', 'per_page', 'total', 'last_page', 'from', 'to'],
        ])
        ->assertJsonFragment([
            'email' => 'alpha@test.com',
            'email_verified_at' => '01.04.2026',
            'confirmed_at' => '02.04.2026',
            'login_at' => '03.04.2026  08:15',
            'login_ip' => '127.0.0.1',
        ])
        ->assertJsonMissing(['email' => $otherSchoolUser->email]);

    $lastNames = collect($response->json('data'))->pluck('last_name')->all();
    $sorted = collect($lastNames)->sort()->values()->all();
    expect($lastNames)->toEqual($sorted);
});

test('admin can also load users', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $this->getJson('/api/admin/users20/load_users')
        ->assertStatus(200);
});

test('users20 load_users supports searching by school class', function () {
    $classUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'last_name' => 'Class',
        'first_name' => 'Search',
        'email' => 'class-search@test.com',
        'schoolclass' => '7B',
    ]);
    $otherClassUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'last_name' => 'Other',
        'first_name' => 'Class',
        'email' => 'other-class@test.com',
        'schoolclass' => '8C',
    ]);

    $this->actingAs($this->superAdmin, 'sanctum');

    $this->getJson('/api/admin/users20/load_users?search_string=7B')
        ->assertStatus(200)
        ->assertJsonFragment(['id' => $classUser->id])
        ->assertJsonMissing(['id' => $otherClassUser->id]);
});

test('admin can mark missing users20 account status fields', function () {
    $this->travelTo(Carbon::parse('2026-04-04 10:30:00'));

    $target = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email_verified_at' => null,
        'confirmed_at' => null,
    ]);
    $secondTarget = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email_verified_at' => null,
        'confirmed_at' => null,
    ]);

    $this->actingAs($this->adminUser, 'sanctum');

    $this->postJson('/api/admin/users20/mark_account_status', [
        'user_ids' => [$target->id, $secondTarget->id],
        'field' => 'email_verified_at',
    ])
        ->assertSuccessful()
        ->assertJsonFragment([
            'id' => $target->id,
            'email_verified_at' => '04.04.2026',
            'confirmed_at' => null,
        ]);

    $this->postJson('/api/admin/users20/mark_account_status', [
        'user_ids' => [$target->id],
        'field' => 'confirmed_at',
    ])
        ->assertSuccessful()
        ->assertJsonFragment([
            'id' => $target->id,
            'email_verified_at' => '04.04.2026',
            'confirmed_at' => '04.04.2026',
        ]);

    $target->refresh();
    $secondTarget->refresh();

    expect($target->email_verified_at?->toDateTimeString())->toBe('2026-04-04 10:30:00')
        ->and(Carbon::parse($target->confirmed_at)->toDateTimeString())->toBe('2026-04-04 10:30:00')
        ->and($secondTarget->email_verified_at?->toDateTimeString())->toBe('2026-04-04 10:30:00')
        ->and($secondTarget->confirmed_at)->toBeNull();
});

test('admin cannot mark users20 account status for another school', function () {
    $foreignUser = User::factory()->create([
        'school_id' => $this->otherSchool->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email_verified_at' => null,
    ]);

    $this->actingAs($this->adminUser, 'sanctum');

    $this->postJson('/api/admin/users20/mark_account_status', [
        'user_ids' => [$foreignUser->id],
        'field' => 'email_verified_at',
    ])->assertNotFound();

    expect($foreignUser->fresh()->email_verified_at)->toBeNull();
});

test('guest is unauthorized from loading users', function () {
    $this->getJson('/api/admin/users20/load_users')
        ->assertStatus(401);
});

// ============================================================================
// toggleIsActive (users20 endpoint)
// ============================================================================

test('super admin can toggle a single user active state via users20 endpoint', function () {
    $target = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'is_active' => true,
    ]);

    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/users20/toggle_is_active', [
        'user_id' => $target->id,
    ])->assertStatus(200)
        ->assertJsonFragment([
            'id' => $target->id,
            'is_active' => false,
        ]);

    expect((bool) $target->fresh()->is_active)->toBeFalse();
});

test('super admin can force active state for multiple users via users20 endpoint', function () {
    $first = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'is_active' => false,
    ]);
    $second = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'is_active' => false,
    ]);

    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/users20/toggle_is_active', [
        'user_ids' => [$first->id, $second->id],
        'is_active' => true,
    ])->assertStatus(200)
        ->assertJsonFragment([
            'updated_count' => 2,
            'selected_count' => 2,
        ]);

    expect((bool) $first->fresh()->is_active)->toBeTrue();
    expect((bool) $second->fresh()->is_active)->toBeTrue();
});

test('admin cannot toggle a user from another school via users20 endpoint', function () {
    $foreignUser = User::factory()->create([
        'school_id' => $this->otherSchool->id,
        'schoolyear_id' => $this->schoolyear->id,
        'is_active' => true,
    ]);

    $this->actingAs($this->adminUser, 'sanctum');

    $this->postJson('/api/admin/users20/toggle_is_active', [
        'user_id' => $foreignUser->id,
    ])->assertStatus(403);
});

test('users20 toggle_is_active blocks deactivating protected admin roles', function () {
    $protectedAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'is_active' => true,
    ]);
    $protectedAdmin->assignRole('admin');

    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/users20/toggle_is_active', [
        'user_id' => $protectedAdmin->id,
        'is_active' => false,
    ])->assertStatus(403);

    expect((bool) $protectedAdmin->fresh()->is_active)->toBeTrue();
});

test('guest is unauthorized from toggling users20 active state', function () {
    $this->postJson('/api/admin/users20/toggle_is_active', [
        'user_id' => $this->standardUser->id,
    ])->assertStatus(401);
});

// ============================================================================
// updateUser (super admin and admin)
// ============================================================================

test('super admin updates user through service', function () {
    $target = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'target@test.com',
    ]);

    $payload = [
        'id' => $target->id,
        'last_name' => 'Updated',
        'first_name' => 'Name',
        'phone' => '123456789',
        'email' => 'updated@test.com',
    ];

    $updatedUser = clone $target;
    $updatedUser->last_name = 'Updated';
    $updatedUser->first_name = 'Name';
    $updatedUser->phone = '123456789';
    $updatedUser->email = 'updated@test.com';
    $superAdminId = $this->superAdmin->id;

    $this->mock(UserService::class, function ($mock) use ($payload, $updatedUser, $superAdminId) {
        $mock->shouldReceive('update')
            ->once()
            ->withArgs(function ($data, $actingUser) use ($payload, $superAdminId) {
                return $data === $payload
                    && $actingUser instanceof User
                    && $actingUser->id === $superAdminId;
            })
            ->andReturn($updatedUser);
    });

    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/users20/update', $payload)
        ->assertStatus(200)
        ->assertJsonFragment([
            'id' => $target->id,
            'last_name' => 'Updated',
            'first_name' => 'Name',
            'email' => 'updated@test.com',
        ]);
});

test('updateUser is allowed for admin', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $this->postJson('/api/admin/users20/update', [
        'id' => $this->standardUser->id,
        'last_name' => 'Blocked',
        'email' => 'blocked@test.com',
    ])->assertStatus(200);
});

test('super admin can assign super_admin role via users20 update', function () {
    $target = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'assignable@test.com',
        'last_name' => 'Assignable',
    ]);

    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/users20/update', [
        'id' => $target->id,
        'last_name' => 'Assignable',
        'first_name' => $target->first_name,
        'email' => $target->email,
        'roles' => [
            ['name' => 'super_admin', 'checked' => true],
        ],
    ])->assertStatus(200);

    expect($target->fresh()->hasRole('super_admin'))->toBeTrue();
});

test('admin cannot assign super_admin role via users20 update', function () {
    $target = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'noescalation@test.com',
        'last_name' => 'NoEscalation',
    ]);

    $this->actingAs($this->adminUser, 'sanctum');

    $this->postJson('/api/admin/users20/update', [
        'id' => $target->id,
        'last_name' => 'NoEscalation',
        'first_name' => $target->first_name,
        'email' => $target->email,
        'roles' => [
            ['name' => 'super_admin', 'checked' => true],
        ],
    ])->assertStatus(200);

    expect($target->fresh()->hasRole('super_admin'))->toBeFalse();
});

test('admin cannot remove super_admin role via users20 update', function () {
    $target = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'no-demotion@test.com',
        'last_name' => 'NoDemotion',
    ]);
    $target->assignRole('super_admin');

    $this->actingAs($this->adminUser, 'sanctum');

    $this->postJson('/api/admin/users20/update', [
        'id' => $target->id,
        'last_name' => 'NoDemotion',
        'first_name' => $target->first_name,
        'email' => $target->email,
        'roles' => [
            ['name' => 'super_admin', 'checked' => false],
        ],
    ])->assertStatus(200);

    expect($target->fresh()->hasRole('super_admin'))->toBeTrue();
});

test('admin can assign regular roles via users20 update', function () {
    $target = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'roleblock@test.com',
        'last_name' => 'RoleBlock',
    ]);

    $this->actingAs($this->adminUser, 'sanctum');

    $this->postJson('/api/admin/users20/update', [
        'id' => $target->id,
        'last_name' => 'RoleBlock',
        'first_name' => $target->first_name,
        'email' => $target->email,
        'roles' => [
            ['name' => 'teacher', 'checked' => true],
        ],
    ])->assertStatus(200);

    expect($target->fresh()->hasRole('teacher'))->toBeTrue();
});

test('kron@naturwelt.at remains super_admin even when unchecked in users20 update', function () {
    $target = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'kron@naturwelt.at',
        'last_name' => 'Kron',
    ]);
    $target->assignRole('super_admin');

    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/users20/update', [
        'id' => $target->id,
        'last_name' => 'Kron',
        'first_name' => $target->first_name,
        'email' => 'kron@naturwelt.at',
        'roles' => [
            ['name' => 'super_admin', 'checked' => false],
        ],
    ])->assertStatus(200);

    expect($target->fresh()->hasRole('super_admin'))->toBeTrue();
});

test('admin cannot remove admin role from themselves via users20 update', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $this->postJson('/api/admin/users20/update', [
        'id' => $this->adminUser->id,
        'last_name' => $this->adminUser->last_name,
        'first_name' => $this->adminUser->first_name,
        'email' => $this->adminUser->email,
        'roles' => [
            ['name' => 'admin', 'checked' => false],
        ],
    ])->assertStatus(200);

    expect($this->adminUser->fresh()->hasRole('admin'))->toBeTrue();
});

test('super_admin cannot remove super_admin role from themselves via users20 update', function () {
    $this->superAdmin->assignRole('admin');
    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/users20/update', [
        'id' => $this->superAdmin->id,
        'last_name' => $this->superAdmin->last_name,
        'first_name' => $this->superAdmin->first_name,
        'email' => $this->superAdmin->email,
        'roles' => [
            ['name' => 'super_admin', 'checked' => false],
            ['name' => 'admin', 'checked' => false],
        ],
    ])->assertStatus(200);

    expect($this->superAdmin->fresh()->hasRole('super_admin'))->toBeTrue();
    expect($this->superAdmin->fresh()->hasRole('admin'))->toBeTrue();
});

// ============================================================================
// index (admin/user) with filters
// ============================================================================

test('admin can filter users by active flag', function () {
    $activeUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'active@test.com',
        'is_active' => true,
    ]);
    $inactiveUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'inactive@test.com',
        'is_active' => false,
    ]);

    $this->actingAs($this->adminUser, 'sanctum');

    $response = $this->getJson('/api/admin/users?'.http_build_query([
        'search_model' => ['is_active' => '1'],
    ]));

    $response->assertStatus(200);

    $payload = $response->json('data') ?? $response->json();
    $users = collect($payload);

    expect($users)->not->toBeEmpty();

    $usersWithFlag = $users->filter(fn ($user) => data_get($user, 'is_active') !== null);
    expect($usersWithFlag->every(fn ($user) => data_get($user, 'is_active') === true))->toBeTrue();
    expect($users->contains(fn ($user) => data_get($user, 'email') === 'inactive@test.com'))->toBeFalse();
});

test('standard user can access index endpoint', function () {
    $this->actingAs($this->standardUser, 'sanctum');

    $this->getJson('/api/admin/users')
        ->assertStatus(200);
});

// ============================================================================
// saveUserRoles (super admin only)
// ============================================================================

test('super admin can save user roles', function () {
    $target = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'roles@test.com',
    ]);
    $target->assignRole('register_admin'); // will be removed

    $assignRole = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    $removeRole = Role::firstOrCreate(['name' => 'register_admin', 'guard_name' => 'web']);

    $this->actingAs($this->superAdmin, 'sanctum');

    $this->postJson('/api/admin/users/save_user_roles', [
        'user_ids' => [$target->id],
        'role_ids' => [
            ['id' => $assignRole->id, 'role_check' => 1],
            ['id' => $removeRole->id, 'role_check' => 2],
        ],
    ])->assertStatus(204);

    expect($target->fresh()->hasRole('teacher'))->toBeTrue();
    expect($target->fresh()->hasRole('register_admin'))->toBeFalse();
});

test('admin cannot save user roles', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $this->postJson('/api/admin/users/save_user_roles', [
        'user_ids' => [$this->standardUser->id],
        'role_ids' => [],
    ])->assertStatus(403);
});

test('save user roles requires super admin role', function () {
    $this->actingAs($this->standardUser, 'sanctum');

    $this->postJson('/api/admin/users/save_user_roles', [
        'user_ids' => [$this->standardUser->id],
        'role_ids' => [],
    ])->assertStatus(403);
});

// ============================================================================
// sendVerificationEmailInitializedFromUser (public)
// ============================================================================

test('user can request verification email without authentication', function () {
    Notification::fake();

    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'verifyme@test.com',
    ]);

    $this->postJson('/api/admin/users/send_verification_email_initialized_from_user', [
        'email' => $user->email,
    ])->assertStatus(200)
        ->assertSee(VerificationResult::EMAIL_SENT->value);
});

// ============================================================================
// emailVerification (public)
// ============================================================================

test('email verification succeeds with valid uuid', function () {
    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'verify@test.com',
        'email_verified_at' => null,
    ]);
    $uuid = $user->generateUuid();

    $this->postJson('/api/admin/users/email_verification', [
        'email' => $user->email,
        'uuid' => $uuid,
    ])->assertStatus(200)
        ->assertSee(VerificationResult::VERIFICATION_SUCCESS->value);

    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

// ============================================================================
// save2Fa / save2FaWithCode (admin)
// ============================================================================

test('admin can start 2FA setup and receive result', function () {
    Notification::fake();

    $this->actingAs($this->adminUser, 'sanctum');

    $this->postJson('/api/admin/users/save_2fa', [
        'id' => $this->adminUser->id,
        'is_2fa' => true,
        'email_2fa' => '2fa@example.com',
    ])->assertStatus(200)
        ->assertJsonFragment(['result' => TwoFaResult::TWO_FA_EMAIL_IS_NEW]);
});

test('admin can complete 2FA with code', function () {
    $this->actingAs($this->adminUser, 'sanctum');

    $token = $this->adminUser->setToken2Fa(5);

    $this->postJson('/api/admin/users/save_2fa_with_code', [
        'id' => $this->adminUser->id,
        'is_2fa' => true,
        'email_2fa' => 'secure@test.com',
        'token_2fa' => $token,
    ])->assertStatus(200)
        ->assertJsonFragment(['result' => TwoFaResult::TWO_FA_SET]);

    $refreshed = $this->adminUser->fresh();
    expect((bool) $refreshed->is_2fa)->toBeTrue();
    expect($refreshed->email_2fa)->toBe('secure@test.com');
});

test('lunch admin can load own profile through admin profile endpoint', function () {
    $this->actingAs($this->lunchAdmin, 'sanctum');

    $this->getJson("/api/admin/users/{$this->lunchAdmin->id}")
        ->assertStatus(200)
        ->assertJsonFragment([
            'email' => 'lunch@test.com',
        ]);
});

test('lunch admin can start 2FA setup for own profile', function () {
    Notification::fake();

    $this->actingAs($this->lunchAdmin, 'sanctum');

    $this->postJson('/api/admin/users/save_2fa', [
        'id' => $this->lunchAdmin->id,
        'is_2fa' => true,
        'email_2fa' => 'lunch-2fa@example.com',
    ])->assertStatus(200)
        ->assertJsonFragment(['result' => TwoFaResult::TWO_FA_EMAIL_IS_NEW]);
});
