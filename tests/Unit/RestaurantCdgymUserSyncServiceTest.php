<?php

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\RestaurantCdgymUserSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'lunch_user', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'lunch_admin', 'guard_name' => 'web']);
});

test('dry run summarizes users and roles without writing', function () {
    $school = School::factory()->create(['id' => 1]);
    Schoolyear::factory()->create([
        'school_id' => $school->id,
        'is_active' => true,
    ]);

    $service = app(RestaurantCdgymUserSyncService::class);
    $userCountBeforeSync = User::query()->count();

    $summary = $service->sync(1, collect([
        [
            'email' => 'LunchUser@example.test',
            'first_name' => 'Lena',
            'last_name' => 'Mittag',
            'email_verified_at' => '2026-03-01 10:00:00',
            'role_name' => 'lunch_user',
        ],
        [
            'email' => 'LunchUser@example.test',
            'first_name' => 'Lena',
            'last_name' => 'Mittag',
            'email_verified_at' => '2026-03-01 10:00:00',
            'role_name' => 'lunch_admin',
        ],
        [
            'email' => 'not-an-email',
            'role_name' => 'lunch_user',
        ],
    ]));

    expect($summary['source_rows_seen'])->toBe(3)
        ->and($summary['source_users_seen'])->toBe(1)
        ->and($summary['source_users_skipped'])->toBe(1)
        ->and($summary['users_to_create'])->toBe(1)
        ->and($summary['users_created'])->toBe(0)
        ->and($summary['roles_to_assign'])->toBe(2)
        ->and($summary['roles_assigned'])->toBe(0)
        ->and(User::query()->count())->toBe($userCountBeforeSync)
        ->and(User::query()->where('email', 'lunchuser@example.test')->exists())->toBeFalse();
});

test('live sync creates a new local user in the target school and assigns imported roles', function () {
    $school = School::factory()->create(['id' => 1]);
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $school->id,
        'is_active' => true,
    ]);

    $service = app(RestaurantCdgymUserSyncService::class);

    $summary = $service->sync(1, collect([
        [
            'email' => 'lunch.user@example.test',
            'first_name' => 'Lena',
            'last_name' => 'Mittag',
            'email_verified_at' => '2026-03-01 10:00:00',
            'role_name' => 'lunch_user',
        ],
        [
            'email' => 'lunch.user@example.test',
            'first_name' => 'Lena',
            'last_name' => 'Mittag',
            'email_verified_at' => '2026-03-01 10:00:00',
            'role_name' => 'lunch_admin',
        ],
    ]), true);

    $user = User::query()
        ->where('school_id', 1)
        ->where('email', 'lunch.user@example.test')
        ->first();

    expect($summary['users_created'])->toBe(1)
        ->and($summary['roles_assigned'])->toBe(2)
        ->and($summary['lunch_user_roles_assigned'])->toBe(1)
        ->and($summary['lunch_admin_roles_assigned'])->toBe(1)
        ->and($user)->not->toBeNull()
        ->and((int) $user->schoolyear_id)->toBe($schoolyear->id)
        ->and($user->first_name)->toBe('Lena')
        ->and($user->last_name)->toBe('Mittag')
        ->and($user->email_verified_at?->format('Y-m-d H:i:s'))->toBe('2026-03-01 10:00:00')
        ->and((string) $user->confirmed_at)->toBe('2026-03-01 10:00:00')
        ->and($user->hasRole('lunch_user'))->toBeTrue()
        ->and($user->hasRole('lunch_admin'))->toBeTrue();
});

test('live sync only assigns missing imported roles when the email already exists in the target school', function () {
    $school = School::factory()->create(['id' => 1]);
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $school->id,
        'is_active' => true,
    ]);

    $existingUser = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'email' => 'existing@example.test',
    ]);
    $existingUser->assignRole('lunch_user');

    $service = app(RestaurantCdgymUserSyncService::class);

    $summary = $service->sync(1, collect([
        [
            'email' => 'EXISTING@example.test',
            'first_name' => 'Should',
            'last_name' => 'Stay',
            'role_name' => 'lunch_user',
        ],
        [
            'email' => 'EXISTING@example.test',
            'first_name' => 'Should',
            'last_name' => 'Stay',
            'role_name' => 'lunch_admin',
        ],
    ]), true);

    $existingUser->refresh();

    expect($summary['users_matched'])->toBe(1)
        ->and($summary['users_to_create'])->toBe(0)
        ->and($summary['users_created'])->toBe(0)
        ->and($summary['roles_to_assign'])->toBe(1)
        ->and($summary['roles_assigned'])->toBe(1)
        ->and(User::query()
            ->where('school_id', $school->id)
            ->where('email', 'existing@example.test')
            ->count())->toBe(1)
        ->and($existingUser->hasRole('lunch_user'))->toBeTrue()
        ->and($existingUser->hasRole('lunch_admin'))->toBeTrue()
        ->and($existingUser->first_name)->not->toBe('Should');
});

test('live sync creates a new user when the email exists only in another school', function () {
    $targetSchool = School::factory()->create(['id' => 1]);
    $targetSchoolyear = Schoolyear::factory()->create([
        'school_id' => $targetSchool->id,
        'is_active' => true,
    ]);

    $otherSchool = School::factory()->create();
    $otherSchoolyear = Schoolyear::factory()->create([
        'school_id' => $otherSchool->id,
        'is_active' => true,
    ]);

    $otherSchoolUser = User::factory()->create([
        'school_id' => $otherSchool->id,
        'schoolyear_id' => $otherSchoolyear->id,
        'email' => 'shared@example.test',
    ]);

    $service = app(RestaurantCdgymUserSyncService::class);

    $summary = $service->sync(1, collect([
        [
            'email' => 'shared@example.test',
            'first_name' => 'New',
            'last_name' => 'School',
            'role_name' => 'lunch_user',
        ],
    ]), true);

    $targetSchoolUser = User::query()
        ->where('school_id', $targetSchool->id)
        ->where('email', 'shared@example.test')
        ->first();

    expect($summary['users_created'])->toBe(1)
        ->and($targetSchoolUser)->not->toBeNull()
        ->and(User::query()
            ->whereIn('school_id', [$targetSchool->id, $otherSchool->id])
            ->where('email', 'shared@example.test')
            ->count())->toBe(2)
        ->and((int) $targetSchoolUser->schoolyear_id)->toBe($targetSchoolyear->id)
        ->and((int) $otherSchoolUser->school_id)->toBe($otherSchool->id);
});
