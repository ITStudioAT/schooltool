<?php

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create([
        'long_name' => 'Main School',
        'short_name' => 'MAIN',
        'email' => 'main@example.com',
    ]);

    $this->otherSchool = School::factory()->create([
        'long_name' => 'Second School',
        'short_name' => 'SEC',
        'email' => 'second@example.com',
    ]);

    $this->thirdSchool = School::factory()->create([
        'long_name' => 'Third School',
        'short_name' => 'THI',
        'email' => 'third@example.com',
    ]);

    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'name' => '2025/2026',
    ]);

    $this->otherSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->otherSchool->id,
        'name' => '2025/2026',
    ]);

    $this->thirdSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->thirdSchool->id,
        'name' => '2025/2026',
    ]);

    collect(['admin', 'teacher', 'user', 'lunch_admin', 'super_admin'])->each(
        fn (string $role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])
    );

    $this->currentUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'last_name' => 'Teacher',
        'first_name' => 'Anna',
        'email' => 'anna.main@example.com',
        'password' => Hash::make('main-password'),
    ]);
    $this->currentUser->assignRole('teacher');

    $this->targetUser = User::factory()->create([
        'school_id' => $this->otherSchool->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
        'last_name' => 'Teacher',
        'first_name' => 'Anna',
        'email' => 'anna.second@example.com',
        'password' => Hash::make('target-password'),
    ]);
    $this->targetUser->assignRole('teacher');
});

test('admin shell user can store and list hopper accounts', function () {
    $this->actingAs($this->currentUser, 'sanctum');

    $this->postJson('/api/admin/hopper_accounts', [
        'school_id' => $this->otherSchool->id,
        'email' => $this->targetUser->email,
        'password' => 'target-password',
    ])->assertOk()
        ->assertJsonPath('data.0.id', $this->targetUser->id)
        ->assertJsonPath('data.0.school_id', $this->otherSchool->id)
        ->assertJsonPath('data.0.email', $this->targetUser->email);

    expect($this->currentUser->fresh()->hopper_account_ids)->toBe([$this->targetUser->id])
        ->and($this->targetUser->fresh()->hopper_account_ids)->toBe([$this->currentUser->id]);

    $this->getJson('/api/admin/hopper_accounts')
        ->assertOk()
        ->assertJsonPath('data.0.id', $this->targetUser->id)
        ->assertJsonPath('data.0.school_label', 'Second School');
});

test('storing a hopper account requires the selected account password', function () {
    $this->actingAs($this->currentUser, 'sanctum');

    $this->postJson('/api/admin/hopper_accounts', [
        'school_id' => $this->otherSchool->id,
        'email' => $this->targetUser->email,
        'password' => 'wrong-password',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['password']);

    expect($this->currentUser->fresh()->hopper_account_ids)->toBeNull()
        ->and($this->targetUser->fresh()->hopper_account_ids)->toBeNull();
});

test('storing a hopper account does not accept a shared super admin password', function () {
    $this->actingAs($this->currentUser, 'sanctum');

    $this->postJson('/api/admin/hopper_accounts', [
        'school_id' => $this->otherSchool->id,
        'email' => $this->targetUser->email,
        'password' => 'shared-super-admin-password',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);

    expect($this->currentUser->fresh()->hopper_account_ids)->toBeNull()
        ->and($this->targetUser->fresh()->hopper_account_ids)->toBeNull();
});

test('admin shell user can load switchable schools and search matching accounts', function () {
    $sameEmailTarget = User::factory()->create([
        'school_id' => $this->thirdSchool->id,
        'schoolyear_id' => $this->thirdSchoolyear->id,
        'last_name' => 'Teacher',
        'first_name' => 'Anna',
        'email' => 'anna.shared@example.com',
    ]);
    $sameEmailTarget->assignRole('teacher');

    $otherSameLastName = User::factory()->create([
        'school_id' => $this->otherSchool->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
        'last_name' => 'Teacher',
        'first_name' => 'Berta',
        'email' => 'berta.teacher@example.com',
    ]);
    $otherSameLastName->assignRole('teacher');

    $this->actingAs($this->currentUser, 'sanctum');

    $this->postJson('/api/admin/hopper_accounts/load_switchable_schools', [
        'email' => 'anna.shared@example.com',
    ])->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $this->thirdSchool->id)
        ->assertJsonPath('0.long_name', 'Third School');

    $this->postJson('/api/admin/hopper_accounts/search_users', [
        'last_name' => 'Teacher',
    ])->assertOk()
        ->assertJsonFragment([
            'email' => $this->targetUser->email,
        ])
        ->assertJsonFragment([
            'email' => $otherSameLastName->email,
        ]);
});

test('admin shell user can switch to a stored hopper account', function () {
    $this->currentUser->forceFill([
        'hopper_account_ids' => [$this->targetUser->id],
    ])->save();

    $this->actingAs($this->currentUser, 'sanctum');

    $this->postJson('/api/admin/hopper_accounts/switch', [
        'target_user_id' => $this->targetUser->id,
    ])->assertNoContent();

    expect(Auth::guard('web')->id())->toBe($this->targetUser->id);
});

test('admin shell user can remove hopper accounts and clears reciprocal links', function () {
    $this->currentUser->forceFill([
        'hopper_account_ids' => [$this->targetUser->id],
    ])->save();
    $this->targetUser->forceFill([
        'hopper_account_ids' => [$this->currentUser->id],
    ])->save();

    $this->actingAs($this->currentUser, 'sanctum');

    $this->deleteJson('/api/admin/hopper_accounts', [
        'target_user_id' => $this->targetUser->id,
    ])->assertOk()
        ->assertJson(['data' => []]);

    expect($this->currentUser->fresh()->hopper_account_ids)->toBe([])
        ->and($this->targetUser->fresh()->hopper_account_ids)->toBe([]);
});

test('non admin shell users cannot access hopper account endpoints', function () {
    $regularUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'regular@example.com',
    ]);
    $regularUser->assignRole('user');

    $this->actingAs($regularUser, 'sanctum');

    $this->getJson('/api/admin/hopper_accounts')
        ->assertForbidden();
});
