<?php

use App\Models\RestaurantEatingTime;
use App\Models\RestaurantFood;
use App\Models\RestaurantMenu;
use App\Models\RestaurantMenuPlan;
use App\Models\RestaurantMenuPlanBooking;
use App\Models\RestaurantMenuPlanEntry;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Carbon::setTestNow();
});

function createRestaurantImportWeekLegacyDatabase(string $connectionName, string $path): void
{
    if (file_exists($path)) {
        unlink($path);
    }

    touch($path);

    config([
        "database.connections.$connectionName" => [
            'driver' => 'sqlite',
            'database' => $path,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
        'schooltool.legacy_restaurant' => [
            'driver' => 'sqlite',
            'database' => $path,
        ],
    ]);

    Schema::connection($connectionName)->create('food', function (Blueprint $table): void {
        $table->id();
        $table->string('title')->nullable();
        $table->decimal('price', 7, 2)->nullable();
        $table->timestamps();
    });

    Schema::connection($connectionName)->create('menus', function (Blueprint $table): void {
        $table->id();
        $table->string('title')->nullable();
        $table->foreignId('starter_food_id')->nullable();
        $table->foreignId('main_food_id')->nullable();
        $table->foreignId('dessert_food_id')->nullable();
        $table->decimal('price', 7, 2)->nullable();
        $table->timestamps();
    });

    Schema::connection($connectionName)->create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('email')->nullable();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->timestamps();
    });

    Schema::connection($connectionName)->create('menu_plans', function (Blueprint $table): void {
        $table->id();
        $table->date('date')->nullable();
        $table->time('time')->nullable();
        $table->unsignedTinyInteger('order');
        $table->foreignId('starter_food_id')->nullable();
        $table->foreignId('main_food_id')->nullable();
        $table->foreignId('dessert_food_id')->nullable();
        $table->decimal('price', 7, 2)->nullable();
        $table->timestamps();
    });

    Schema::connection($connectionName)->create('menu_plan_bookings', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('user_id');
        $table->foreignId('menu_plan_id');
        $table->boolean('billed')->default(false);
        $table->timestamp('billed_at')->nullable();
        $table->timestamps();
    });
}

function createLocalRestaurantWeekPlan(School $school, int $week, int $year, array $menuIds): void
{
    $monday = Carbon::now()->setISODate($year, $week, 1)->startOfDay();
    $thursday = $monday->copy()->addDays(3)->toDateString();

    $plan = RestaurantMenuPlan::create([
        'school_id' => $school->id,
        'title' => 'KW '.$week,
        'start_date' => $monday->toDateString(),
        'end_date' => $thursday,
        'is_available' => true,
        'visible_start_at' => null,
        'visible_end_at' => null,
        'order_start_at' => null,
        'order_end_at' => null,
        'use_individual_schedule_values' => false,
        'visibility_start_mode' => 'when_available',
        'visibility_start_week_offset' => null,
        'visibility_start_day_of_week' => null,
        'visibility_start_time' => null,
        'order_start_mode' => 'when_available',
        'order_start_week_offset' => null,
        'order_start_day_of_week' => null,
        'order_start_time' => null,
        'order_end_week_offset' => 1,
        'order_end_day_of_week' => 5,
        'order_end_time' => '17:00:00',
        'visibility_end_mode' => 'plan_end',
    ]);

    $menuSequence = array_values($menuIds);
    $menuIndex = 0;

    for ($dayOffset = 0; $dayOffset <= 3; $dayOffset++) {
        $planDate = $monday->copy()->addDays($dayOffset)->toDateString();

        for ($slot = 0; $slot < 2; $slot++) {
            $menuId = $menuSequence[$menuIndex % count($menuSequence)];
            $menuIndex++;

            RestaurantMenuPlanEntry::create([
                'restaurant_menu_plan_id' => $plan->id,
                'plan_date' => $planDate,
                'restaurant_menu_id' => $menuId,
                'menu_title' => 'Menu '.$menuId,
                'price' => 8.50,
                'comments' => null,
            ]);
        }
    }
}

function seedLegacyRestaurantWeekData(string $connectionName, array $foodRows, array $menuRows, array $userRows, array $menuPlanRows, array $bookingRows): void
{
    $connection = DB::connection($connectionName);

    $connection->table('food')->insert($foodRows);
    $connection->table('menus')->insert($menuRows);
    $connection->table('users')->insert($userRows);
    $connection->table('menu_plans')->insert($menuPlanRows);
    $connection->table('menu_plan_bookings')->insert($bookingRows);
}

it('reports that the requested weeks are ready for overtaking when the data exists', function (): void {
    Carbon::setTestNow('2026-04-06 12:00:00');

    $school = School::factory()->create();
    $superAdmin = User::factory()->create([
        'school_id' => $school->id,
    ]);
    $superAdmin->assignRole(Role::firstOrCreate([
        'name' => 'super_admin',
        'guard_name' => 'web',
    ]));

    $legacyUsers = [
        ['id' => 20, 'email' => 'anna@example.test', 'first_name' => 'Anna', 'last_name' => 'A', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 21, 'email' => 'ben@example.test', 'first_name' => 'Ben', 'last_name' => 'B', 'created_at' => now(), 'updated_at' => now()],
    ];

    $localUsers = [
        User::factory()->create([
            'school_id' => $school->id,
            'email' => 'anna@example.test',
            'first_name' => 'Anna',
            'last_name' => 'A',
        ]),
        User::factory()->create([
            'school_id' => $school->id,
            'email' => 'ben@example.test',
            'first_name' => 'Ben',
            'last_name' => 'B',
        ]),
    ];

    $localFoods = [
        RestaurantFood::query()->create([
            'school_id' => $school->id,
            'legacy_food_id' => 1,
            'title' => 'Food 1',
            'description' => null,
            'allergens' => [],
            'price' => 1.00,
            'food_image_path' => null,
        ]),
        RestaurantFood::query()->create([
            'school_id' => $school->id,
            'legacy_food_id' => 2,
            'title' => 'Food 2',
            'description' => null,
            'allergens' => [],
            'price' => 2.00,
            'food_image_path' => null,
        ]),
        RestaurantFood::query()->create([
            'school_id' => $school->id,
            'legacy_food_id' => 3,
            'title' => 'Food 3',
            'description' => null,
            'allergens' => [],
            'price' => 3.00,
            'food_image_path' => null,
        ]),
    ];

    RestaurantEatingTime::factory()->create([
        'school_id' => $school->id,
        'eating_time' => '11:30:00',
    ]);

    $localMenus = [
        RestaurantMenu::query()->create([
            'school_id' => $school->id,
            'legacy_menu_id' => 10,
            'title' => 'Menu 10',
            'price' => 8.50,
        ]),
        RestaurantMenu::query()->create([
            'school_id' => $school->id,
            'legacy_menu_id' => 11,
            'title' => 'Menu 11',
            'price' => 9.50,
        ]),
    ];

    $legacyConnectionName = 'legacy_restaurant_week_check';
    $legacyPath = database_path('testing-legacy-restaurant-week-ready.sqlite');
    createRestaurantImportWeekLegacyDatabase($legacyConnectionName, $legacyPath);

    $foodRows = [
        ['id' => 1, 'title' => 'Food 1', 'price' => 1.00, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'title' => 'Food 2', 'price' => 2.00, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 3, 'title' => 'Food 3', 'price' => 3.00, 'created_at' => now(), 'updated_at' => now()],
    ];

    $menuRows = [
        ['id' => 10, 'title' => 'Menu 10', 'starter_food_id' => 1, 'main_food_id' => 2, 'dessert_food_id' => 3, 'price' => 8.50, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 11, 'title' => 'Menu 11', 'starter_food_id' => 2, 'main_food_id' => 3, 'dessert_food_id' => null, 'price' => 9.50, 'created_at' => now(), 'updated_at' => now()],
    ];

    $menuPlanRows = [];
    $bookingRows = [];

    foreach ([12, 13, 14] as $week) {
        $monday = Carbon::now()->setISODate(2026, $week, 1)->startOfDay();

        for ($dayOffset = 0; $dayOffset <= 3; $dayOffset++) {
            $planDate = $monday->copy()->addDays($dayOffset)->toDateString();
            $menuPlanRows[] = [
                'id' => $week * 100 + $dayOffset * 2 + 1,
                'date' => $planDate,
                'time' => '12:30:00',
                'order' => 1,
                'starter_food_id' => 1,
                'main_food_id' => 2,
                'dessert_food_id' => 3,
                'price' => 8.50,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $menuPlanRows[] = [
                'id' => $week * 100 + $dayOffset * 2 + 2,
                'date' => $planDate,
                'time' => '13:25:00',
                'order' => 2,
                'starter_food_id' => 2,
                'main_food_id' => 3,
                'dessert_food_id' => null,
                'price' => 8.50,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $bookingRows[] = [
                'id' => $week * 1000 + $dayOffset * 2 + 1,
                'user_id' => 20,
                'menu_plan_id' => $week * 100 + $dayOffset * 2 + 1,
                'billed' => 1,
                'billed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $bookingRows[] = [
                'id' => $week * 1000 + $dayOffset * 2 + 2,
                'user_id' => 21,
                'menu_plan_id' => $week * 100 + $dayOffset * 2 + 2,
                'billed' => 1,
                'billed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        createLocalRestaurantWeekPlan($school, $week, 2026, [$localMenus[0]->id, $localMenus[1]->id]);
    }

    seedLegacyRestaurantWeekData($legacyConnectionName, $foodRows, $menuRows, $legacyUsers, $menuPlanRows, $bookingRows);

    $this->artisan('restaurant:import-week', [
        'week_range' => '12-14',
        '--school-id' => $school->id,
        '--year' => 2026,
    ])
        ->expectsOutputToContain('Restaurant week import readiness check')
        ->expectsOutputToContain('Weeks: KW 12-14')
        ->expectsOutputToContain('Menus matched: 2/2')
        ->expectsOutputToContain('Foods matched: 3/3')
        ->expectsOutputToContain('Users matched: 2/2')
        ->expectsOutputToContain('Week plans ready to overtake: 3/3')
        ->expectsOutputToContain('Ready for overtaking: yes')
        ->assertExitCode(0);
});

it('creates the requested week plans when run in live mode', function (): void {
    Carbon::setTestNow('2026-04-06 12:00:00');

    $school = School::factory()->create();
    $superAdmin = User::factory()->create([
        'school_id' => $school->id,
    ]);
    $superAdmin->assignRole(Role::firstOrCreate([
        'name' => 'super_admin',
        'guard_name' => 'web',
    ]));

    $legacyUsers = [
        ['id' => 20, 'email' => 'anna@example.test', 'first_name' => 'Anna', 'last_name' => 'A', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 21, 'email' => 'ben@example.test', 'first_name' => 'Ben', 'last_name' => 'B', 'created_at' => now(), 'updated_at' => now()],
    ];

    User::factory()->create([
        'school_id' => $school->id,
        'email' => 'anna@example.test',
        'first_name' => 'Anna',
        'last_name' => 'A',
    ]);
    User::factory()->create([
        'school_id' => $school->id,
        'email' => 'ben@example.test',
        'first_name' => 'Ben',
        'last_name' => 'B',
    ]);

    $localFoods = [
        RestaurantFood::query()->create([
            'school_id' => $school->id,
            'legacy_food_id' => 1,
            'title' => 'Food 1',
            'description' => null,
            'allergens' => [],
            'price' => 1.00,
            'food_image_path' => null,
        ]),
        RestaurantFood::query()->create([
            'school_id' => $school->id,
            'legacy_food_id' => 2,
            'title' => 'Food 2',
            'description' => null,
            'allergens' => [],
            'price' => 2.00,
            'food_image_path' => null,
        ]),
        RestaurantFood::query()->create([
            'school_id' => $school->id,
            'legacy_food_id' => 3,
            'title' => 'Food 3',
            'description' => null,
            'allergens' => [],
            'price' => 3.00,
            'food_image_path' => null,
        ]),
    ];

    RestaurantEatingTime::factory()->create([
        'school_id' => $school->id,
        'eating_time' => '11:30:00',
    ]);
    RestaurantEatingTime::factory()->create([
        'school_id' => $school->id,
        'eating_time' => '12:30:00',
    ]);

    $localMenuA = RestaurantMenu::query()->create([
        'school_id' => $school->id,
        'legacy_menu_id' => 10,
        'title' => 'Menu 10',
        'price' => 8.50,
    ]);
    $localMenuA->foods()->sync([
        $localFoods[0]->id => ['course_number' => 1],
        $localFoods[1]->id => ['course_number' => 2],
        $localFoods[2]->id => ['course_number' => 3],
    ]);

    $localMenuB = RestaurantMenu::query()->create([
        'school_id' => $school->id,
        'legacy_menu_id' => 11,
        'title' => 'Menu 11',
        'price' => 9.50,
    ]);
    $localMenuB->foods()->sync([
        $localFoods[1]->id => ['course_number' => 1],
        $localFoods[2]->id => ['course_number' => 2],
    ]);

    $legacyConnectionName = 'legacy_restaurant_week_check';
    $legacyPath = database_path('testing-legacy-restaurant-week-live.sqlite');
    createRestaurantImportWeekLegacyDatabase($legacyConnectionName, $legacyPath);

    $foodRows = [
        ['id' => 1, 'title' => 'Food 1', 'price' => 1.00, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'title' => 'Food 2', 'price' => 2.00, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 3, 'title' => 'Food 3', 'price' => 3.00, 'created_at' => now(), 'updated_at' => now()],
    ];

    $menuRows = [
        ['id' => 10, 'title' => 'Menu 10', 'starter_food_id' => 1, 'main_food_id' => 2, 'dessert_food_id' => 3, 'price' => 8.50, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 11, 'title' => 'Menu 11', 'starter_food_id' => 2, 'main_food_id' => 3, 'dessert_food_id' => null, 'price' => 9.50, 'created_at' => now(), 'updated_at' => now()],
    ];

    $menuPlanRows = [];
    $bookingRows = [];

    foreach ([15, 16] as $week) {
        $monday = Carbon::now()->setISODate(2026, $week, 1)->startOfDay();

        for ($dayOffset = 0; $dayOffset <= 2; $dayOffset++) {
            $planDate = $monday->copy()->addDays($dayOffset)->toDateString();
            $menuPlanRows[] = [
                'id' => $week * 100 + $dayOffset * 2 + 1,
                'date' => $planDate,
                'time' => '11:30:00',
                'order' => 1,
                'starter_food_id' => 1,
                'main_food_id' => 2,
                'dessert_food_id' => 3,
                'price' => 8.50,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $bookingRows[] = [
                'id' => $week * 1000 + $dayOffset * 2 + 1,
                'user_id' => 20,
                'menu_plan_id' => $week * 100 + $dayOffset * 2 + 1,
                'billed' => 1,
                'billed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $menuPlanRows[] = [
                'id' => $week * 100 + $dayOffset * 2 + 2,
                'date' => $planDate,
                'time' => '12:30:00',
                'order' => 2,
                'starter_food_id' => 2,
                'main_food_id' => 3,
                'dessert_food_id' => null,
                'price' => 9.50,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $bookingRows[] = [
                'id' => $week * 1000 + $dayOffset * 2 + 2,
                'user_id' => 21,
                'menu_plan_id' => $week * 100 + $dayOffset * 2 + 2,
                'billed' => 1,
                'billed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
    }

    seedLegacyRestaurantWeekData($legacyConnectionName, $foodRows, $menuRows, $legacyUsers, $menuPlanRows, $bookingRows);

    $this->artisan('restaurant:import-week', [
        'week_range' => '15-16',
        '--school-id' => $school->id,
        '--year' => 2026,
        '--live' => true,
    ])
        ->expectsOutputToContain('Ready for overtaking: yes')
        ->expectsOutputToContain('Live import executed')
        ->expectsOutputToContain('Plans created: 2')
        ->expectsOutputToContain('Entries created: 12')
        ->expectsOutputToContain('Bookings created: 12')
        ->assertExitCode(0);

    expect(RestaurantMenuPlan::query()->where('school_id', $school->id)->count())->toBe(2);
    expect(RestaurantMenuPlanBooking::query()->where('school_id', $school->id)->count())->toBe(12);

    $plans = RestaurantMenuPlan::query()
        ->where('school_id', $school->id)
        ->orderBy('start_date')
        ->with('entries.eatingTimes', 'entries.bookings')
        ->get();

    expect($plans->first()?->entries)->toHaveCount(6)
        ->and($plans->last()?->entries)->toHaveCount(6);
    expect($plans->first()?->entries->first()?->bookings)->toHaveCount(1);
});

it('assigns imported bookings to the correct eating time when the same menu appears in multiple slots', function (): void {
    Carbon::setTestNow('2026-04-06 12:00:00');

    $school = School::factory()->create();
    $superAdmin = User::factory()->create([
        'school_id' => $school->id,
    ]);
    $superAdmin->assignRole(Role::firstOrCreate([
        'name' => 'super_admin',
        'guard_name' => 'web',
    ]));

    User::factory()->create([
        'school_id' => $school->id,
        'email' => 'anna@example.test',
        'first_name' => 'Anna',
        'last_name' => 'A',
    ]);
    User::factory()->create([
        'school_id' => $school->id,
        'email' => 'ben@example.test',
        'first_name' => 'Ben',
        'last_name' => 'B',
    ]);

    $foodOne = RestaurantFood::query()->create([
        'school_id' => $school->id,
        'legacy_food_id' => 1,
        'title' => 'Food 1',
        'description' => null,
        'allergens' => [],
        'price' => 1.00,
        'food_image_path' => null,
    ]);
    $foodTwo = RestaurantFood::query()->create([
        'school_id' => $school->id,
        'legacy_food_id' => 2,
        'title' => 'Food 2',
        'description' => null,
        'allergens' => [],
        'price' => 2.00,
        'food_image_path' => null,
    ]);
    $foodThree = RestaurantFood::query()->create([
        'school_id' => $school->id,
        'legacy_food_id' => 3,
        'title' => 'Food 3',
        'description' => null,
        'allergens' => [],
        'price' => 3.00,
        'food_image_path' => null,
    ]);

    $firstEatingTime = RestaurantEatingTime::factory()->create([
        'school_id' => $school->id,
        'eating_time' => '11:30:00',
    ]);
    $secondEatingTime = RestaurantEatingTime::factory()->create([
        'school_id' => $school->id,
        'eating_time' => '12:30:00',
    ]);

    $localMenu = RestaurantMenu::query()->create([
        'school_id' => $school->id,
        'legacy_menu_id' => 10,
        'title' => 'Menu 10',
        'price' => 8.50,
    ]);
    $localMenu->foods()->sync([
        $foodOne->id => ['course_number' => 1],
        $foodTwo->id => ['course_number' => 2],
        $foodThree->id => ['course_number' => 3],
    ]);

    $legacyConnectionName = 'legacy_restaurant_week_check';
    $legacyPath = database_path('testing-legacy-restaurant-week-eating-times.sqlite');
    createRestaurantImportWeekLegacyDatabase($legacyConnectionName, $legacyPath);

    $legacyUsers = [
        ['id' => 20, 'email' => 'anna@example.test', 'first_name' => 'Anna', 'last_name' => 'A', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 21, 'email' => 'ben@example.test', 'first_name' => 'Ben', 'last_name' => 'B', 'created_at' => now(), 'updated_at' => now()],
    ];
    $foodRows = [
        ['id' => 1, 'title' => 'Food 1', 'price' => 1.00, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'title' => 'Food 2', 'price' => 2.00, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 3, 'title' => 'Food 3', 'price' => 3.00, 'created_at' => now(), 'updated_at' => now()],
    ];
    $menuRows = [
        ['id' => 10, 'title' => 'Menu 10', 'starter_food_id' => 1, 'main_food_id' => 2, 'dessert_food_id' => 3, 'price' => 8.50, 'created_at' => now(), 'updated_at' => now()],
    ];

    $week = 15;
    $monday = Carbon::now()->setISODate(2026, $week, 1)->startOfDay();
    $menuPlanRows = [];
    $bookingRows = [];

    for ($dayOffset = 0; $dayOffset <= 3; $dayOffset++) {
        $planDate = $monday->copy()->addDays($dayOffset)->toDateString();
        $firstPlanId = $week * 100 + $dayOffset * 2 + 1;
        $secondPlanId = $week * 100 + $dayOffset * 2 + 2;

        $menuPlanRows[] = [
            'id' => $firstPlanId,
            'date' => $planDate,
            'time' => '11:30:00',
            'order' => 1,
            'starter_food_id' => 1,
            'main_food_id' => 2,
            'dessert_food_id' => 3,
            'price' => 8.50,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $menuPlanRows[] = [
            'id' => $secondPlanId,
            'date' => $planDate,
            'time' => '12:30:00',
            'order' => 2,
            'starter_food_id' => 1,
            'main_food_id' => 2,
            'dessert_food_id' => 3,
            'price' => 8.50,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $bookingRows[] = [
            'id' => $week * 1000 + $dayOffset * 2 + 1,
            'user_id' => 20,
            'menu_plan_id' => $firstPlanId,
            'billed' => 1,
            'billed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $bookingRows[] = [
            'id' => $week * 1000 + $dayOffset * 2 + 2,
            'user_id' => 21,
            'menu_plan_id' => $secondPlanId,
            'billed' => 1,
            'billed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    seedLegacyRestaurantWeekData($legacyConnectionName, $foodRows, $menuRows, $legacyUsers, $menuPlanRows, $bookingRows);

    $this->artisan('restaurant:import-week', [
        'week_range' => '15',
        '--school-id' => $school->id,
        '--year' => 2026,
        '--live' => true,
    ])
        ->expectsOutputToContain('Ready for overtaking: yes')
        ->expectsOutputToContain('Live import executed')
        ->expectsOutputToContain('Plans created: 1')
        ->expectsOutputToContain('Entries created: 4')
        ->expectsOutputToContain('Bookings created: 8')
        ->assertExitCode(0);

    $plan = RestaurantMenuPlan::query()
        ->where('school_id', $school->id)
        ->with('entries.eatingTimes', 'entries.bookings.user', 'entries.bookings.eatingTime')
        ->sole();

    $firstDayEntry = $plan->entries
        ->filter(fn (RestaurantMenuPlanEntry $entry): bool => $entry->plan_date?->toDateString() === $monday->toDateString())
        ->sole();

    expect($firstDayEntry->eatingTimes->pluck('eating_time')->sort()->values()->all())
        ->toBe([$firstEatingTime->eating_time, $secondEatingTime->eating_time]);

    $bookingsByEmail = $firstDayEntry->bookings
        ->keyBy(fn (RestaurantMenuPlanBooking $booking): string => (string) $booking->user?->email);

    expect($bookingsByEmail->get('anna@example.test')?->eatingTime?->eating_time)->toBe('11:30:00')
        ->and($bookingsByEmail->get('ben@example.test')?->eatingTime?->eating_time)->toBe('12:30:00');
});

it('uses the legacy booking created_at as booked_at during import', function (): void {
    Carbon::setTestNow('2026-04-06 12:00:00');

    $school = School::factory()->create();
    $superAdmin = User::factory()->create([
        'school_id' => $school->id,
    ]);
    $superAdmin->assignRole(Role::firstOrCreate([
        'name' => 'super_admin',
        'guard_name' => 'web',
    ]));

    User::factory()->create([
        'school_id' => $school->id,
        'email' => 'anna@example.test',
        'first_name' => 'Anna',
        'last_name' => 'A',
    ]);

    RestaurantFood::query()->create([
        'school_id' => $school->id,
        'legacy_food_id' => 1,
        'title' => 'Food 1',
        'description' => null,
        'allergens' => [],
        'price' => 1.00,
        'food_image_path' => null,
    ]);
    RestaurantFood::query()->create([
        'school_id' => $school->id,
        'legacy_food_id' => 2,
        'title' => 'Food 2',
        'description' => null,
        'allergens' => [],
        'price' => 2.00,
        'food_image_path' => null,
    ]);
    RestaurantFood::query()->create([
        'school_id' => $school->id,
        'legacy_food_id' => 3,
        'title' => 'Food 3',
        'description' => null,
        'allergens' => [],
        'price' => 3.00,
        'food_image_path' => null,
    ]);

    RestaurantEatingTime::factory()->create([
        'school_id' => $school->id,
        'eating_time' => '11:30:00',
    ]);

    $localMenu = RestaurantMenu::query()->create([
        'school_id' => $school->id,
        'legacy_menu_id' => 10,
        'title' => 'Menu 10',
        'price' => 8.50,
    ]);
    $localMenu->foods()->sync(
        RestaurantFood::query()
            ->where('school_id', $school->id)
            ->pluck('id')
            ->values()
            ->mapWithKeys(fn (int $id, int $index): array => [$id => ['course_number' => $index + 1]])
            ->all()
    );

    $legacyConnectionName = 'legacy_restaurant_week_check';
    $legacyPath = database_path('testing-legacy-restaurant-week-booked-at.sqlite');
    createRestaurantImportWeekLegacyDatabase($legacyConnectionName, $legacyPath);

    $legacyUsers = [
        ['id' => 20, 'email' => 'anna@example.test', 'first_name' => 'Anna', 'last_name' => 'A', 'created_at' => now(), 'updated_at' => now()],
    ];
    $foodRows = [
        ['id' => 1, 'title' => 'Food 1', 'price' => 1.00, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'title' => 'Food 2', 'price' => 2.00, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 3, 'title' => 'Food 3', 'price' => 3.00, 'created_at' => now(), 'updated_at' => now()],
    ];
    $menuRows = [
        ['id' => 10, 'title' => 'Menu 10', 'starter_food_id' => 1, 'main_food_id' => 2, 'dessert_food_id' => 3, 'price' => 8.50, 'created_at' => now(), 'updated_at' => now()],
    ];

    $monday = Carbon::now()->setISODate(2026, 15, 1)->startOfDay();
    $legacyCreatedAt = '2026-04-10 07:14:33';
    $legacyBilledAt = '2026-04-16 19:39:20';

    $menuPlanRows = [[
        'id' => 1501,
        'date' => $monday->toDateString(),
        'time' => '11:30:00',
        'order' => 1,
        'starter_food_id' => 1,
        'main_food_id' => 2,
        'dessert_food_id' => 3,
        'price' => 8.50,
        'created_at' => now(),
        'updated_at' => now(),
    ]];
    $bookingRows = [[
        'id' => 15001,
        'user_id' => 20,
        'menu_plan_id' => 1501,
        'billed' => 1,
        'billed_at' => $legacyBilledAt,
        'created_at' => $legacyCreatedAt,
        'updated_at' => '2026-04-17 09:00:00',
    ]];

    seedLegacyRestaurantWeekData($legacyConnectionName, $foodRows, $menuRows, $legacyUsers, $menuPlanRows, $bookingRows);

    $this->artisan('restaurant:import-week', [
        'week_range' => '15',
        '--school-id' => $school->id,
        '--year' => 2026,
        '--live' => true,
    ])
        ->expectsOutputToContain('Ready for overtaking: yes')
        ->expectsOutputToContain('Live import executed')
        ->assertExitCode(0);

    $booking = RestaurantMenuPlanBooking::query()
        ->where('school_id', $school->id)
        ->sole();

    expect($booking->booked_at?->format('Y-m-d H:i:s'))->toBe($legacyCreatedAt);
});

it('reports missing legacy data when the selected weeks are incomplete', function (): void {
    Carbon::setTestNow('2026-04-06 12:00:00');

    $school = School::factory()->create();
    $superAdmin = User::factory()->create([
        'school_id' => $school->id,
    ]);
    $superAdmin->assignRole(Role::firstOrCreate([
        'name' => 'super_admin',
        'guard_name' => 'web',
    ]));

    $legacyUsers = [
        ['id' => 20, 'email' => 'anna@example.test', 'first_name' => 'Anna', 'last_name' => 'A', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 21, 'email' => 'ben@example.test', 'first_name' => 'Ben', 'last_name' => 'B', 'created_at' => now(), 'updated_at' => now()],
    ];

    $localUsers = [
        User::factory()->create([
            'school_id' => $school->id,
            'email' => 'anna@example.test',
            'first_name' => 'Anna',
            'last_name' => 'A',
        ]),
    ];

    $localFoods = [
        RestaurantFood::query()->create([
            'school_id' => $school->id,
            'legacy_food_id' => 1,
            'title' => 'Food 1',
            'description' => null,
            'allergens' => [],
            'price' => 1.00,
            'food_image_path' => null,
        ]),
        RestaurantFood::query()->create([
            'school_id' => $school->id,
            'legacy_food_id' => 2,
            'title' => 'Food 2',
            'description' => null,
            'allergens' => [],
            'price' => 2.00,
            'food_image_path' => null,
        ]),
    ];

    $localMenu = RestaurantMenu::query()->create([
        'school_id' => $school->id,
        'legacy_menu_id' => 10,
        'title' => 'Menu 10',
        'price' => 8.50,
    ]);

    RestaurantEatingTime::factory()->create([
        'school_id' => $school->id,
        'eating_time' => '11:30:00',
    ]);

    $legacyConnectionName = 'legacy_restaurant_week_check';
    $legacyPath = database_path('testing-legacy-restaurant-week-missing.sqlite');
    createRestaurantImportWeekLegacyDatabase($legacyConnectionName, $legacyPath);

    $foodRows = [
        ['id' => 1, 'title' => 'Food 1', 'price' => 1.00, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'title' => 'Food 2', 'price' => 2.00, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 3, 'title' => 'Food 3', 'price' => 3.00, 'created_at' => now(), 'updated_at' => now()],
    ];

    $menuRows = [
        ['id' => 10, 'title' => 'Menu 10', 'starter_food_id' => 1, 'main_food_id' => 2, 'dessert_food_id' => 3, 'price' => 8.50, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 11, 'title' => 'Menu 11', 'starter_food_id' => 2, 'main_food_id' => 3, 'dessert_food_id' => null, 'price' => 9.50, 'created_at' => now(), 'updated_at' => now()],
    ];

    $menuPlanRows = [];
    $bookingRows = [];

    foreach ([12, 13] as $week) {
        $monday = Carbon::now()->setISODate(2026, $week, 1)->startOfDay();

        for ($dayOffset = 0; $dayOffset <= 3; $dayOffset++) {
            $planDate = $monday->copy()->addDays($dayOffset)->toDateString();
            $menuPlanRows[] = [
                'id' => $week * 100 + $dayOffset * 2 + 1,
                'date' => $planDate,
                'time' => '12:30:00',
                'order' => 1,
                'starter_food_id' => 1,
                'main_food_id' => 2,
                'dessert_food_id' => 3,
                'price' => 8.50,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $bookingRows[] = [
                'id' => $week * 1000 + $dayOffset * 2 + 1,
                'user_id' => 20,
                'menu_plan_id' => $week * 100 + $dayOffset * 2 + 1,
                'billed' => 1,
                'billed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $bookingRows[] = [
                'id' => $week * 1000 + $dayOffset * 2 + 2,
                'user_id' => 21,
                'menu_plan_id' => $week * 100 + $dayOffset * 2 + 1,
                'billed' => 1,
                'billed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($week === 12) {
            createLocalRestaurantWeekPlan($school, $week, 2026, [$localMenu->id, $localMenu->id]);
        }
    }

    seedLegacyRestaurantWeekData($legacyConnectionName, $foodRows, $menuRows, $legacyUsers, $menuPlanRows, $bookingRows);

    $this->artisan('restaurant:import-week', [
        'week_range' => '12-13',
        '--school-id' => $school->id,
        '--year' => 2026,
    ])
        ->expectsOutputToContain('Menus matched: 1/2')
        ->expectsOutputToContain('Foods matched: 2/3')
        ->expectsOutputToContain('Users matched: 1/2')
        ->expectsOutputToContain('Week plans ready to overtake: 2/2')
        ->expectsOutputToContain('Missing users:')
        ->expectsOutputToContain('ben@example.test')
        ->expectsOutputToContain('Ready for overtaking: no')
        ->assertExitCode(0);
});
