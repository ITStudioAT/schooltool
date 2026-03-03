<?php

use App\Models\Import116;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'name' => '2025/2026',
    ]);

    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

    $this->superAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'super.admin@test.com',
    ]);
    $this->superAdmin->assignRole('super_admin');

    $this->targetUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'teacher.user@test.com',
    ]);
    $this->targetUser->assignRole('teacher');
});

test('super admin can impersonate and leave impersonation', function () {
    $this->actingAs($this->superAdmin);

    $this->postJson('/api/admin/impersonation/start', [
        'user_id' => $this->targetUser->id,
    ])->assertStatus(200);

    $configDuring = $this->getJson('/api/admin/config')
        ->assertStatus(200)
        ->json();

    $superAdminFromDb = User::with('selectedSchool')->findOrFail($this->superAdmin->id);
    $superAdminSchoolName = trim((string) ($superAdminFromDb->selectedSchool?->long_name ?: $superAdminFromDb->selectedSchool?->short_name));

    expect((bool) data_get($configDuring, 'impersonation.is_impersonating'))->toBeTrue()
        ->and((int) data_get($configDuring, 'impersonation.impersonator.id'))->toBe((int) $this->superAdmin->id)
        ->and((string) data_get($configDuring, 'impersonation.impersonator.email'))->toBe((string) $this->superAdmin->email)
        ->and((string) data_get($configDuring, 'impersonation.impersonator.school_name'))->toBe($superAdminSchoolName);

    $this->postJson('/api/admin/impersonation/stop')
        ->assertStatus(200);

    $configAfter = $this->getJson('/api/admin/config')
        ->assertStatus(200)
        ->json();

    expect((bool) data_get($configAfter, 'impersonation.is_impersonating'))->toBeFalse()
        ->and(data_get($configAfter, 'impersonation.impersonator'))->toBeNull();
});

test('non super admin cannot start impersonation', function () {
    $this->actingAs($this->targetUser);

    $this->postJson('/api/admin/impersonation/start', [
        'user_id' => $this->superAdmin->id,
    ])->assertStatus(403);
});

test('super admin can find and impersonate users from other schools', function () {
    $otherSchool = School::factory()->create([
        'long_name' => 'Andere Schule',
    ]);
    $otherSchoolyear = Schoolyear::factory()->create([
        'school_id' => $otherSchool->id,
        'name' => '2025/2026',
    ]);
    $otherUser = User::factory()->create([
        'school_id' => $otherSchool->id,
        'schoolyear_id' => $otherSchoolyear->id,
        'email' => 'other.school.user@test.com',
        'last_name' => 'Fremd',
        'first_name' => 'Schule',
    ]);
    $otherUser->assignRole('teacher');

    $this->actingAs($this->superAdmin);

    $schoolsResponse = $this->getJson('/api/admin/impersonation/schools')
        ->assertStatus(200)
        ->json('data');
    expect(collect($schoolsResponse)->pluck('id')->contains($otherSchool->id))->toBeTrue();

    $usersResponse = $this->getJson('/api/admin/impersonation/users?search_string=Andere Schule')
        ->assertStatus(200)
        ->json('data');

    expect(collect($usersResponse)->pluck('id')->contains($otherUser->id))->toBeTrue();

    $filteredForCurrentSchool = $this->getJson('/api/admin/impersonation/users?school_id='.$this->school->id)
        ->assertStatus(200)
        ->json('data');
    expect(collect($filteredForCurrentSchool)->pluck('id')->contains($otherUser->id))->toBeFalse();

    $filteredForOtherSchool = $this->getJson('/api/admin/impersonation/users?school_id='.$otherSchool->id)
        ->assertStatus(200)
        ->json('data');
    expect(collect($filteredForOtherSchool)->pluck('id')->contains($otherUser->id))->toBeTrue();

    $this->postJson('/api/admin/impersonation/start', [
        'user_id' => $otherUser->id,
    ])->assertStatus(200);
});

test('impersonation status returns current user email and school while impersonating', function () {
    $this->actingAs($this->superAdmin);

    $this->postJson('/api/admin/impersonation/start', [
        'user_id' => $this->targetUser->id,
    ])->assertStatus(200);

    $status = $this->getJson('/api/admin/impersonation/status')
        ->assertStatus(200)
        ->json();

    $currentUserId = (int) data_get($status, 'current_user.id');
    $currentUser = User::with('selectedSchool')->findOrFail($currentUserId);
    $expectedSchoolName = trim((string) ($currentUser->selectedSchool?->long_name ?: $currentUser->selectedSchool?->short_name));
    $impersonatorId = (int) data_get($status, 'impersonator.id');
    $impersonator = User::with('selectedSchool')->findOrFail($impersonatorId);
    $expectedImpersonatorSchoolName = trim((string) ($impersonator->selectedSchool?->long_name ?: $impersonator->selectedSchool?->short_name));

    expect((bool) data_get($status, 'is_impersonating'))->toBeTrue()
        ->and((string) data_get($status, 'current_user.email'))->toBe((string) $currentUser->email)
        ->and((int) data_get($status, 'current_user.school_id'))->toBe((int) $currentUser->school_id)
        ->and((string) data_get($status, 'current_user.school_name'))->toBe($expectedSchoolName)
        ->and((string) data_get($status, 'impersonator.email'))->toBe((string) $impersonator->email)
        ->and((int) data_get($status, 'impersonator.school_id'))->toBe((int) $impersonator->school_id)
        ->and((string) data_get($status, 'impersonator.school_name'))->toBe($expectedImpersonatorSchoolName);
});

test('impersonation users list returns only the latest user per school and import116 record', function () {
    $importRow = Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'latest.email@test.com',
    ]);

    $olderDuplicate = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Elena',
        'last_name' => 'Pabinger',
        'email' => 'old.email@test.com',
        'import116_id' => $importRow->id,
    ]);
    $olderDuplicate->assignRole('teacher');

    $newerDuplicate = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Elena',
        'last_name' => 'Pabinger',
        'email' => 'latest.email@test.com',
        'import116_id' => $importRow->id,
    ]);
    $newerDuplicate->assignRole('teacher');

    $this->actingAs($this->superAdmin);

    $responseIds = collect(
        $this->getJson('/api/admin/impersonation/users?school_id='.$this->school->id)
            ->assertOk()
            ->json('data')
    )->pluck('id');

    expect($responseIds->contains($newerDuplicate->id))->toBeTrue()
        ->and($responseIds->contains($olderDuplicate->id))->toBeFalse();
});
