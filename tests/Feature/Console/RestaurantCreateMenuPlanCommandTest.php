<?php

use App\Models\RestaurantEatingTime;
use App\Models\RestaurantMenu;
use App\Models\RestaurantMenuPlan;
use App\Models\RestaurantMenuPlanEntry;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate([
        'name' => 'super_admin',
        'guard_name' => 'web',
    ]);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function createRestaurantCreateMenuPlanFixture(int $menuCount): array
{
    $school = School::factory()->create();
    $superAdmin = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => null,
    ]);
    $superAdmin->assignRole('super_admin');

    $menus = RestaurantMenu::factory()
        ->forSchool($school)
        ->count($menuCount)
        ->create();

    RestaurantEatingTime::factory()->create([
        'school_id' => $school->id,
        'eating_time' => '11:30:00',
    ]);

    return [$school, $menus];
}

it('rotates the generated menus between weeks', function (): void {
    Carbon::setTestNow('2026-04-05 12:00:00');

    [$school, $menus] = createRestaurantCreateMenuPlanFixture(8);

    $this->artisan('restaurant:create-menu-plan', [
        'kw' => 17,
        '--year' => 2026,
    ])->assertExitCode(0);

    $this->artisan('restaurant:create-menu-plan', [
        'kw' => 18,
        '--year' => 2026,
    ])->assertExitCode(0);

    $monday17 = Carbon::now()->setISODate(2026, 17, 1)->startOfDay()->toDateString();
    $thursday17 = Carbon::now()->setISODate(2026, 17, 1)->startOfDay()->addDays(3)->toDateString();
    $monday18 = Carbon::now()->setISODate(2026, 18, 1)->startOfDay()->toDateString();
    $thursday18 = Carbon::now()->setISODate(2026, 18, 1)->startOfDay()->addDays(3)->toDateString();

    $plan17 = RestaurantMenuPlan::query()
        ->where('school_id', $school->id)
        ->where('start_date', $monday17)
        ->where('end_date', $thursday17)
        ->with('entries')
        ->firstOrFail();
    $plan18 = RestaurantMenuPlan::query()
        ->where('school_id', $school->id)
        ->where('start_date', $monday18)
        ->where('end_date', $thursday18)
        ->with('entries')
        ->firstOrFail();

    $menuIds17 = RestaurantMenuPlanEntry::query()
        ->where('restaurant_menu_plan_id', $plan17->id)
        ->orderBy('plan_date')
        ->orderBy('id')
        ->pluck('restaurant_menu_id')
        ->all();
    $menuIds18 = RestaurantMenuPlanEntry::query()
        ->where('restaurant_menu_plan_id', $plan18->id)
        ->orderBy('plan_date')
        ->orderBy('id')
        ->pluck('restaurant_menu_id')
        ->all();

    expect($menuIds17)->toHaveCount(8)
        ->and(array_unique($menuIds17))->toHaveCount(8)
        ->and($menuIds18)->toHaveCount(8)
        ->and(array_unique($menuIds18))->toHaveCount(8)
        ->and($menuIds17)->not->toBe($menuIds18)
        ->and($menuIds17[0])->not->toBe($menuIds18[0]);

    expect($menuIds17)->toBe($menus->pluck('id')->all());
});

it('refuses to create a plan when fewer than eight unique menus exist', function (): void {
    createRestaurantCreateMenuPlanFixture(7);

    $this->artisan('restaurant:create-menu-plan', [
        'kw' => 17,
        '--year' => 2026,
    ])
        ->expectsOutputToContain('Not enough menus (need at least 8 unique menus).')
        ->assertExitCode(1);

    expect(RestaurantMenuPlan::query()->count())->toBe(0);
});
