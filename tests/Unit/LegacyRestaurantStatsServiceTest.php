<?php

use App\Models\RestaurantCategory;
use App\Models\RestaurantFood;
use App\Models\RestaurantMenu;
use App\Models\RestaurantMenuPlan;
use App\Models\RestaurantMenuPlanEntry;
use App\Models\School;
use App\Services\LegacyRestaurantStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('menu plan preview compares complete menu weeks', function (): void {
    $school = School::factory()->create();
    $service = app(LegacyRestaurantStatsService::class);

    createRestaurantMenuPlanEntry($school, '2026-05-04', [101, 102, 103]);
    createRestaurantMenuPlanEntry($school, '2026-05-11', [201, 202, 203]);

    $legacyMenuPlans = collect([
        legacyMenuPlan('2026-05-04', 101, 102, 103),
        legacyMenuPlan('2026-05-11', 301, 302, 303),
        legacyMenuPlan('2026-05-18', 401, 402, 403),
    ]);

    $reflection = new ReflectionMethod($service, 'menuPlansPreview');
    $reflection->setAccessible(true);

    $preview = $reflection->invoke($service, (int) $school->id, $legacyMenuPlans);

    expect($preview)->toBe([
        'total' => 2,
        'to_create' => 1,
        'to_update' => 1,
        'source_count' => 3,
    ]);
});

/**
 * @param  array<int, int>  $legacyFoodIds
 */
function createRestaurantMenuPlanEntry(School $school, string $date, array $legacyFoodIds): RestaurantMenuPlanEntry
{
    $menu = RestaurantMenu::factory()->create([
        'school_id' => $school->id,
    ]);
    $category = RestaurantCategory::query()->firstOrCreate([
        'school_id' => $school->id,
        'title' => 'Test',
    ], [
        'sort_order' => 1,
    ]);

    $foods = collect($legacyFoodIds)->map(fn (int $legacyFoodId): RestaurantFood => RestaurantFood::factory()->create([
        'school_id' => $school->id,
        'restaurant_category_id' => $category->id,
        'legacy_food_id' => $legacyFoodId,
    ]));

    $menu->foods()->sync(
        $foods
            ->values()
            ->mapWithKeys(fn (RestaurantFood $food, int $index): array => [
                $food->id => ['course_number' => $index + 1],
            ])
            ->all()
    );

    $menuPlan = RestaurantMenuPlan::factory()->create([
        'school_id' => $school->id,
        'start_date' => $date,
        'end_date' => $date,
    ]);

    return RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $menuPlan->id,
        'restaurant_menu_id' => $menu->id,
        'plan_date' => $date,
    ]);
}

function legacyMenuPlan(string $date, int $starterFoodId, int $mainFoodId, int $dessertFoodId): object
{
    return (object) [
        'date' => $date,
        'starter_food_id' => $starterFoodId,
        'main_food_id' => $mainFoodId,
        'dessert_food_id' => $dessertFoodId,
    ];
}
