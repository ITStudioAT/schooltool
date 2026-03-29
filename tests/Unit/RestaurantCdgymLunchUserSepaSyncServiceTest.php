<?php

use App\Models\School;
use App\Models\User;
use App\Services\RestaurantCdgymLunchUserSepaSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'lunch_user', 'guard_name' => 'web']);
});

test('dry run summarizes legacy sepa rows without writing', function () {
    $school = School::factory()->create(['id' => 1]);

    $existingUser = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => null,
        'email' => 'anna@example.test',
        'sepa_at' => null,
    ]);
    $existingUser->assignRole('lunch_user');

    $service = app(RestaurantCdgymLunchUserSepaSyncService::class);

    $summary = $service->sync(1, collect([
        ['email' => 'anna@example.test', 'sepa' => 1],
        ['email' => 'berta@example.test', 'sepa' => 0],
        ['email' => 'ungueltig', 'sepa' => 1],
    ]));

    expect($summary['source_rows_seen'])->toBe(3)
        ->and($summary['source_users_seen'])->toBe(2)
        ->and($summary['source_users_skipped'])->toBe(1)
        ->and($summary['local_users_matched'])->toBe(1)
        ->and($summary['local_users_missing'])->toBe(1)
        ->and($summary['sepa_true_seen'])->toBe(1)
        ->and($summary['sepa_false_seen'])->toBe(1)
        ->and($summary['users_to_mark_sepa'])->toBe(1)
        ->and($summary['users_marked_sepa'])->toBe(0)
        ->and($existingUser->fresh()->sepa_at)->toBeNull();
});

test('live sync marks sepa on matched lunch users', function () {
    $school = School::factory()->create(['id' => 1]);

    $existingUser = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => null,
        'email' => 'anna@example.test',
        'sepa_at' => null,
    ]);
    $existingUser->assignRole('lunch_user');

    $service = app(RestaurantCdgymLunchUserSepaSyncService::class);

    $summary = $service->sync(1, collect([
        ['email' => 'anna@example.test', 'sepa' => 1],
    ]), true);

    expect($summary['users_to_mark_sepa'])->toBe(1)
        ->and($summary['users_marked_sepa'])->toBe(1)
        ->and($existingUser->fresh()->sepa_at)->not->toBeNull();
});

test('live sync clears sepa when the legacy source says false', function () {
    $school = School::factory()->create(['id' => 1]);

    $existingUser = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => null,
        'email' => 'anna@example.test',
        'sepa_at' => now()->subDay(),
    ]);
    $existingUser->assignRole('lunch_user');

    $service = app(RestaurantCdgymLunchUserSepaSyncService::class);

    $summary = $service->sync(1, collect([
        ['email' => 'anna@example.test', 'sepa' => 0],
    ]), true);

    expect($summary['users_to_clear_sepa'])->toBe(1)
        ->and($summary['users_cleared_sepa'])->toBe(1)
        ->and($existingUser->fresh()->sepa_at)->toBeNull();
});

test('live sync ignores matching users without the lunch user role', function () {
    $school = School::factory()->create(['id' => 1]);

    $existingUser = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => null,
        'email' => 'anna@example.test',
        'sepa_at' => null,
    ]);
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    $existingUser->assignRole('teacher');

    $service = app(RestaurantCdgymLunchUserSepaSyncService::class);

    $summary = $service->sync(1, collect([
        ['email' => 'anna@example.test', 'sepa' => 1],
    ]), true);

    expect($summary['local_users_matched'])->toBe(0)
        ->and($summary['local_users_missing'])->toBe(1)
        ->and($existingUser->fresh()->sepa_at)->toBeNull();
});
