<?php

use App\Models\School;
use App\Models\SchoolTool;
use App\Models\User;
use App\Services\LegacyRestaurantStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    collect(['admin', 'lunch_admin'])->each(function (string $role): void {
        Role::firstOrCreate([
            'name' => $role,
            'guard_name' => 'web',
        ]);
    });
});

test('cdgym restaurant admin can load live legacy stats', function (): void {
    $school = School::factory()->create([
        'long_name' => 'Christian-Doppler-Gymnasium Salzburg',
    ]);

    SchoolTool::factory()->create([
        'school_id' => $school->id,
        'restaurant_visible_admin' => true,
    ]);

    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => null,
    ]);
    $user->assignRole('lunch_admin');

    $this->mock(LegacyRestaurantStatsService::class, function ($mock) use ($school): void {
        $mock->shouldReceive('remoteStats')
            ->withArgs(fn (int $schoolId): bool => $schoolId === (int) $school->id)
            ->once()
            ->andReturn([
                'foods_count' => 14,
                'menus_count' => 8,
                'menu_plans_count' => 96,
                'bookings_count' => 321,
                'lunch_users_count' => 240,
                'lunch_admins_count' => 12,
                'update_preview' => [
                    'foods' => [
                        'total' => 3,
                        'to_create' => 1,
                        'to_update' => 2,
                        'source_count' => 14,
                    ],
                    'menus' => [
                        'total' => 4,
                        'to_create' => 1,
                        'to_update' => 3,
                        'source_count' => 8,
                    ],
                    'menu_plans' => [
                        'total' => 12,
                        'to_create' => 10,
                        'to_update' => 2,
                        'source_count' => 96,
                    ],
                    'bookings' => [
                        'total' => 7,
                        'to_create' => 6,
                        'to_update' => 1,
                        'source_count' => 321,
                    ],
                    'lunch_users' => [
                        'total' => 5,
                        'to_create' => 2,
                        'existing_users_to_assign' => 3,
                        'source_count' => 240,
                        'skipped' => 0,
                    ],
                    'lunch_admins' => [
                        'total' => 2,
                        'to_create' => 1,
                        'existing_users_to_assign' => 1,
                        'source_count' => 12,
                        'skipped' => 0,
                    ],
                ],
                'source' => 'remote-db/cdgym',
                'loaded_at' => '2026-05-15T12:30:00+02:00',
            ]);
    });

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/admin/restaurant/cdgym/legacy-stats')
        ->assertOk()
        ->assertJsonPath('data.foods_count', 14)
        ->assertJsonPath('data.menus_count', 8)
        ->assertJsonPath('data.menu_plans_count', 96)
        ->assertJsonPath('data.bookings_count', 321)
        ->assertJsonPath('data.lunch_users_count', 240)
        ->assertJsonPath('data.lunch_admins_count', 12)
        ->assertJsonPath('data.update_preview.foods.total', 3)
        ->assertJsonPath('data.update_preview.menus.total', 4)
        ->assertJsonPath('data.update_preview.menu_plans.total', 12)
        ->assertJsonPath('data.update_preview.menu_plans.to_create', 10)
        ->assertJsonPath('data.update_preview.menu_plans.to_update', 2)
        ->assertJsonPath('data.update_preview.bookings.total', 7)
        ->assertJsonPath('data.update_preview.bookings.to_create', 6)
        ->assertJsonPath('data.update_preview.bookings.to_update', 1)
        ->assertJsonPath('data.update_preview.lunch_users.total', 5)
        ->assertJsonPath('data.update_preview.lunch_admins.total', 2);
});

test('legacy stats are only available for cdgym school', function (): void {
    $school = School::factory()->create([
        'long_name' => 'Andere Schule',
    ]);

    SchoolTool::factory()->create([
        'school_id' => $school->id,
        'restaurant_visible_admin' => true,
    ]);

    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => null,
    ]);
    $user->assignRole('lunch_admin');

    $this->mock(LegacyRestaurantStatsService::class, function ($mock): void {
        $mock->shouldNotReceive('remoteStats');
    });

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/admin/restaurant/cdgym/legacy-stats')
        ->assertForbidden();
});

test('cdgym restaurant admin can import selected legacy data', function (): void {
    $school = School::factory()->create([
        'long_name' => 'Christian-Doppler-Gymnasium Salzburg',
    ]);

    SchoolTool::factory()->create([
        'school_id' => $school->id,
        'restaurant_visible_admin' => true,
    ]);

    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => null,
    ]);
    $user->assignRole('lunch_admin');

    $this->mock(LegacyRestaurantStatsService::class, function ($mock) use ($school): void {
        $mock->shouldReceive('importSelected')
            ->withArgs(fn (int $schoolId, array $items): bool => $schoolId === (int) $school->id
                && $items === ['foods', 'menu_plans'])
            ->once()
            ->andReturn([
                'items' => ['foods', 'menu_plans'],
                'summary' => [
                    'restaurant' => [
                        'foods_created' => 2,
                    ],
                    'menu_plans' => [
                        'weeks_imported' => 1,
                    ],
                ],
            ]);
    });

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/admin/restaurant/cdgym/legacy-import', [
            'items' => ['foods', 'menu_plans'],
        ])
        ->assertOk()
        ->assertJsonPath('data.items.0', 'foods')
        ->assertJsonPath('data.items.1', 'menu_plans')
        ->assertJsonPath('data.summary.restaurant.foods_created', 2)
        ->assertJsonPath('data.summary.menu_plans.weeks_imported', 1);
});

test('legacy import validates selected items', function (): void {
    $school = School::factory()->create([
        'long_name' => 'Christian-Doppler-Gymnasium Salzburg',
    ]);

    SchoolTool::factory()->create([
        'school_id' => $school->id,
        'restaurant_visible_admin' => true,
    ]);

    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => null,
    ]);
    $user->assignRole('lunch_admin');

    $this->mock(LegacyRestaurantStatsService::class, function ($mock): void {
        $mock->shouldNotReceive('importSelected');
    });

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/admin/restaurant/cdgym/legacy-import', [
            'items' => ['invalid'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items.0');
});
