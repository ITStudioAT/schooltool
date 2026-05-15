<?php

use App\Models\RestaurantCategory;
use App\Models\RestaurantFood;
use App\Models\RestaurantIngredientIcon;
use App\Models\RestaurantMenu;
use App\Models\School;
use App\Services\LegacyRestaurantImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('it imports legacy foods and menus idempotently', function () {
    $school = School::factory()->create();
    $service = app(LegacyRestaurantImportService::class);

    $legacyFoods = [
        [
            'id' => 13,
            'title' => 'Frittatensuppe',
            'description' => 'Klare Gemuesesuppe mit Frittaten',
            'category' => 'Vorspeise',
            'allergens' => null,
            'price' => null,
        ],
        [
            'id' => 14,
            'title' => 'Ofenkartoffel vom🐖',
            'description' => 'Mit Sauerrahmdip aus 🇦🇹',
            'category' => 'Hauptspeise',
            'allergens' => 'a,g,h,z',
            'price' => '8.90',
        ],
        [
            'id' => 161,
            'title' => 'Apfelkuchen',
            'description' => 'Mit Zimt',
            'category' => 'Dessert',
            'allergens' => 'A,C,G',
            'price' => '2.50',
        ],
    ];

    $legacyMenus = [
        [
            'id' => 5,
            'title' => 'Ofenkartoffel',
            'starter_food_id' => 13,
            'main_food_id' => 14,
            'dessert_food_id' => 161,
            'price' => '8.90',
        ],
    ];

    $firstSummary = $service->import((int) $school->id, $legacyFoods, $legacyMenus);

    expect($firstSummary['categories_created'])->toBe(3)
        ->and($firstSummary['foods_created'])->toBe(3)
        ->and($firstSummary['foods_updated'])->toBe(0)
        ->and($firstSummary['menus_created'])->toBe(1)
        ->and($firstSummary['menus_updated'])->toBe(0)
        ->and($firstSummary['menu_food_links_synced'])->toBe(3);

    expect(RestaurantCategory::query()->where('school_id', $school->id)->count())->toBe(3)
        ->and(RestaurantFood::query()->where('school_id', $school->id)->count())->toBe(3)
        ->and(RestaurantMenu::query()->where('school_id', $school->id)->count())->toBe(1);

    $mainFood = RestaurantFood::query()
        ->where('school_id', $school->id)
        ->where('legacy_food_id', 14)
        ->firstOrFail();

    expect($mainFood->allergens)->toBe(['A', 'G', 'H'])
        ->and($mainFood->title)->toBe('Ofenkartoffel vom Schwein')
        ->and($mainFood->description)->toBe("Mit Sauerrahmdip aus \u{00D6}sterreich")
        ->and($mainFood->price)->toBe('8.90')
        ->and($mainFood->category?->title)->toBe('Hauptspeise');
    expect($mainFood->ingredientIcons()->pluck('restaurant_ingredient_icons.title')->all())
        ->toBeEmpty();
    expect(RestaurantIngredientIcon::query()->where('school_id', $school->id)->count())->toBe(0);

    $menu = RestaurantMenu::query()
        ->where('school_id', $school->id)
        ->where('legacy_menu_id', 5)
        ->firstOrFail();

    expect($menu->foods()->orderByPivot('course_number')->pluck('restaurant_foods.legacy_food_id')->all())
        ->toEqual([13, 14, 161]);

    $secondSummary = $service->import((int) $school->id, [
        [
            'id' => 13,
            'title' => 'Frittatensuppe',
            'description' => 'Klare Gemuesesuppe mit Frittaten',
            'category' => 'Vorspeise',
            'allergens' => null,
            'price' => null,
        ],
        [
            'id' => 14,
            'title' => 'Ofenkartoffel Deluxe',
            'description' => 'Mit Ofengemuese',
            'category' => 'Hauptspeise',
            'allergens' => 'a,g,x',
            'price' => '9.50',
        ],
        [
            'id' => 161,
            'title' => 'Apfelkuchen',
            'description' => 'Mit Zimt',
            'category' => 'Dessert',
            'allergens' => 'A,C,G',
            'price' => '2.50',
        ],
    ], $legacyMenus);

    expect($secondSummary['foods_created'])->toBe(0)
        ->and($secondSummary['foods_updated'])->toBe(3)
        ->and(RestaurantFood::query()->where('school_id', $school->id)->count())->toBe(3)
        ->and(RestaurantMenu::query()->where('school_id', $school->id)->count())->toBe(1);

    $mainFood->refresh();

    expect($mainFood->title)->toBe('Ofenkartoffel Deluxe')
        ->and($mainFood->description)->toBe('Mit Ofengemuese')
        ->and($mainFood->allergens)->toBe(['A', 'G'])
        ->and($mainFood->price)->toBe('9.50');
    expect($mainFood->ingredientIcons()->pluck('restaurant_ingredient_icons.title')->all())->toBe([]);
});

test('it previews legacy food and menu changes without writing', function () {
    $school = School::factory()->create();
    $starterCategory = RestaurantCategory::factory()->create([
        'school_id' => $school->id,
        'title' => 'Vorspeise',
    ]);
    $mainCategory = RestaurantCategory::factory()->create([
        'school_id' => $school->id,
        'title' => 'Hauptspeise',
    ]);

    $matchingFood = RestaurantFood::factory()->create([
        'school_id' => $school->id,
        'legacy_food_id' => 13,
        'restaurant_category_id' => $starterCategory->id,
        'title' => 'Frittatensuppe',
        'description' => 'Klare Gemuesesuppe mit Frittaten',
        'allergens' => [],
        'price' => null,
    ]);
    $changedFood = RestaurantFood::factory()->create([
        'school_id' => $school->id,
        'legacy_food_id' => 14,
        'restaurant_category_id' => $mainCategory->id,
        'title' => 'Ofenkartoffel alt',
        'description' => 'Mit Dip',
        'allergens' => ['A'],
        'price' => '8.90',
    ]);

    $matchingMenu = RestaurantMenu::factory()->create([
        'school_id' => $school->id,
        'legacy_menu_id' => 5,
        'title' => 'Suppe',
        'price' => '3.50',
    ]);
    $matchingMenu->foods()->attach($matchingFood->id, ['course_number' => 1]);

    $changedMenu = RestaurantMenu::factory()->create([
        'school_id' => $school->id,
        'legacy_menu_id' => 6,
        'title' => 'Altes Menü',
        'price' => '8.90',
    ]);
    $changedMenu->foods()->attach($changedFood->id, ['course_number' => 1]);

    $summary = app(LegacyRestaurantImportService::class)->previewChanges($school->id, [
        [
            'id' => 13,
            'title' => 'Frittatensuppe',
            'description' => 'Klare Gemuesesuppe mit Frittaten',
            'category' => 'Vorspeise',
            'allergens' => null,
            'price' => null,
        ],
        [
            'id' => 14,
            'title' => 'Ofenkartoffel neu',
            'description' => 'Mit Dip',
            'category' => 'Hauptspeise',
            'allergens' => 'A',
            'price' => '8.90',
        ],
        [
            'id' => 15,
            'title' => 'Apfelkuchen',
            'description' => 'Mit Zimt',
            'category' => 'Dessert',
            'allergens' => 'A,C,G',
            'price' => '2.50',
        ],
    ], [
        [
            'id' => 5,
            'title' => 'Suppe',
            'starter_food_id' => 13,
            'main_food_id' => null,
            'dessert_food_id' => null,
            'price' => '3.50',
        ],
        [
            'id' => 6,
            'title' => 'Neues Menü',
            'starter_food_id' => 14,
            'main_food_id' => null,
            'dessert_food_id' => null,
            'price' => '8.90',
        ],
        [
            'id' => 7,
            'title' => 'Kuchen',
            'starter_food_id' => null,
            'main_food_id' => null,
            'dessert_food_id' => 15,
            'price' => '2.50',
        ],
    ]);

    expect($summary['foods_to_create'])->toBe(1)
        ->and($summary['foods_to_update'])->toBe(1)
        ->and($summary['menus_to_create'])->toBe(1)
        ->and($summary['menus_to_update'])->toBe(1)
        ->and($summary['missing_menu_food_references'])->toBe(0)
        ->and(RestaurantFood::query()->where('school_id', $school->id)->count())->toBe(2)
        ->and(RestaurantMenu::query()->where('school_id', $school->id)->count())->toBe(2);
});

test('it can import menus without updating existing foods', function () {
    $school = School::factory()->create();
    $food = RestaurantFood::factory()->create([
        'school_id' => $school->id,
        'legacy_food_id' => 14,
        'title' => 'Bestehendes Gericht',
    ]);

    $summary = app(LegacyRestaurantImportService::class)->importSelected(
        (int) $school->id,
        [
            [
                'id' => 14,
                'title' => 'Remote Gericht',
                'description' => null,
                'category' => null,
                'allergens' => null,
                'price' => null,
            ],
        ],
        [
            [
                'id' => 5,
                'title' => 'Remote Menü',
                'starter_food_id' => null,
                'main_food_id' => 14,
                'dessert_food_id' => null,
                'price' => '8.90',
            ],
        ],
        false,
        true
    );

    expect($summary['foods_updated'])->toBe(0)
        ->and($summary['menus_created'])->toBe(1)
        ->and($food->fresh()->title)->toBe('Bestehendes Gericht');

    $menu = RestaurantMenu::query()
        ->where('school_id', $school->id)
        ->where('legacy_menu_id', 5)
        ->firstOrFail();

    expect($menu->foods()->pluck('restaurant_foods.id')->all())->toBe([(int) $food->id]);
});
