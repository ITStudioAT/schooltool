<?php

use App\Models\RestaurantEatingTime;
use App\Models\RestaurantFood;
use App\Models\RestaurantIngredientIcon;
use App\Models\RestaurantMenu;
use App\Models\RestaurantMenuPlan;
use App\Models\RestaurantMenuPlanEntry;
use App\Models\School;
use App\Models\User;
use App\Services\RestaurantMenuPlanPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect(['super_admin', 'admin', 'lunch_admin', 'teacher'])->each(function (string $role): void {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    });

    $this->school = School::factory()->create();
    $this->otherSchool = School::factory()->create();

    $this->admin = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => null]);
    $this->admin->assignRole('admin');

    $this->teacher = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => null]);
    $this->teacher->assignRole('teacher');

    $this->icon = RestaurantIngredientIcon::factory()->forSchool($this->school)->create([
        'title' => 'Fisch',
        'image_path' => 'restaurant/ingredient-icons/fisch.svg',
    ]);
    $food = RestaurantFood::factory()->forSchool($this->school)->create([
        'title' => 'Gemuesesuppe',
        'description' => 'Mit Kraeutern',
        'allergens' => ['A', 'G'],
        'food_image_path' => 'restaurant/foods/suppe.jpg',
    ]);
    $food->ingredientIcons()->sync([$this->icon->id]);

    $this->menu = RestaurantMenu::factory()->create(['school_id' => $this->school->id]);
    $this->menu->foods()->sync([
        $food->id => ['course_number' => 1],
    ]);
    $this->eatingTime = RestaurantEatingTime::factory()->create(['school_id' => $this->school->id, 'eating_time' => '11:30:00']);
});

test('returns 401 when menu plans index is unauthenticated', function () {
    $this->getJson('/api/admin/restaurant/menu-plans')->assertUnauthorized();
});

test('returns 403 when role has no menu plan access', function () {
    $this->actingAs($this->teacher, 'sanctum')
        ->getJson('/api/admin/restaurant/menu-plans')
        ->assertForbidden();
});

test('index returns only plans from current school', function () {
    RestaurantMenuPlan::factory()->create(['school_id' => $this->school->id, 'start_date' => '2026-04-01', 'end_date' => '2026-04-05']);
    RestaurantMenuPlan::factory()->create(['school_id' => $this->otherSchool->id, 'start_date' => '2026-04-01', 'end_date' => '2026-04-05']);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/admin/restaurant/menu-plans')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('store creates menu plan with entries and eating times', function () {
    $this->actingAs($this->admin, 'sanctum');

    $response = $this->postJson('/api/admin/restaurant/menu-plans', [
        'title' => 'Testwoche',
        'start_date' => '2026-04-07',
        'end_date' => '2026-04-11',
        'is_available' => true,
        'entries' => [
            [
                'plan_date' => '2026-04-07',
                'menu_id' => $this->menu->id,
                'menu_title' => 'Montagsmenue',
                'price' => '8.50',
                'comments' => 'Ohne Sellerie servieren.',
                'eating_time_ids' => [$this->eatingTime->id],
            ],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Testwoche')
        ->assertJsonPath('data.start_date', '2026-04-07')
        ->assertJsonPath('data.is_available', true)
        ->assertJsonCount(1, 'data.entries')
        ->assertJsonPath('data.entries.0.menu_title', 'Montagsmenue')
        ->assertJsonPath('data.entries.0.price', '8.50')
        ->assertJsonPath('data.entries.0.comments', 'Ohne Sellerie servieren.');

    expect(RestaurantMenuPlan::query()->where('school_id', $this->school->id)->count())->toBe(1);
    expect(RestaurantMenuPlan::query()->first()?->is_available)->toBeTrue();
    expect(RestaurantMenuPlanEntry::query()->count())->toBe(1);
});

test('show returns plan with entries and eating time details', function () {
    $plan = RestaurantMenuPlan::factory()->create([
        'school_id' => $this->school->id,
        'start_date' => '2026-04-07',
        'end_date' => '2026-04-11',
        'is_available' => true,
    ]);
    $entry = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $plan->id,
        'plan_date' => '2026-04-07',
        'restaurant_menu_id' => $this->menu->id,
        'menu_title' => 'Gemuesesuppe Spezial',
        'price' => '7.80',
        'comments' => 'Mit extra Brot.',
    ]);
    $entry->eatingTimes()->attach($this->eatingTime->id);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/admin/restaurant/menu-plans/{$plan->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $plan->id)
        ->assertJsonPath('data.is_available', true)
        ->assertJsonCount(1, 'data.entries')
        ->assertJsonPath('data.entries.0.eating_time_ids.0', $this->eatingTime->id)
        ->assertJsonPath('data.entries.0.eating_times.0.id', $this->eatingTime->id)
        ->assertJsonPath('data.entries.0.eating_times.0.eating_time', '11:30:00')
        ->assertJsonPath('data.entries.0.menu_title', 'Gemuesesuppe Spezial')
        ->assertJsonPath('data.entries.0.price', '7.80')
        ->assertJsonPath('data.entries.0.comments', 'Mit extra Brot.')
        ->assertJsonPath('data.entries.0.menu.foods.0.title', 'Gemuesesuppe')
        ->assertJsonPath('data.entries.0.menu.foods.0.description', 'Mit Kraeutern')
        ->assertJsonPath('data.entries.0.menu.foods.0.allergens.0', 'A')
        ->assertJsonPath('data.entries.0.menu.foods.0.ingredient_icons.0.title', 'Fisch')
        ->assertJsonPath('data.entries.0.menu.foods.0.food_image_url', rtrim((string) config('app.url'), '/').'/storage/restaurant/foods/suppe.jpg');
});

test('update replaces entries', function () {
    $plan = RestaurantMenuPlan::factory()->create(['school_id' => $this->school->id, 'start_date' => '2026-04-07', 'end_date' => '2026-04-11']);
    RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $plan->id,
        'plan_date' => '2026-04-07',
        'restaurant_menu_id' => $this->menu->id,
    ]);

    $menu2 = RestaurantMenu::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/admin/restaurant/menu-plans/{$plan->id}", [
            'title' => 'Aktualisiert',
            'start_date' => '2026-04-07',
            'end_date' => '2026-04-11',
            'is_available' => true,
            'entries' => [
                [
                    'plan_date' => '2026-04-08',
                    'menu_id' => $menu2->id,
                    'menu_title' => 'Dienstagsmenue',
                    'price' => '12.10',
                    'comments' => 'Mit Salat.',
                    'eating_time_ids' => [],
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Aktualisiert')
        ->assertJsonPath('data.is_available', true)
        ->assertJsonCount(1, 'data.entries')
        ->assertJsonPath('data.entries.0.plan_date', '2026-04-08')
        ->assertJsonPath('data.entries.0.menu_title', 'Dienstagsmenue')
        ->assertJsonPath('data.entries.0.price', '12.10')
        ->assertJsonPath('data.entries.0.comments', 'Mit Salat.');

    expect($plan->fresh()?->is_available)->toBeTrue();
    expect(RestaurantMenuPlanEntry::query()->where('restaurant_menu_plan_id', $plan->id)->count())->toBe(1);
});

test('destroy deletes plan and cascades entries', function () {
    $plan = RestaurantMenuPlan::factory()->create(['school_id' => $this->school->id, 'start_date' => '2026-04-07', 'end_date' => '2026-04-11']);
    RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $plan->id,
        'plan_date' => '2026-04-07',
        'restaurant_menu_id' => $this->menu->id,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/admin/restaurant/menu-plans/{$plan->id}")
        ->assertOk();

    expect(RestaurantMenuPlan::query()->find($plan->id))->toBeNull();
    expect(RestaurantMenuPlanEntry::query()->where('restaurant_menu_plan_id', $plan->id)->count())->toBe(0);
});

test('print downloads menu plan pdf for current school', function () {
    $plan = RestaurantMenuPlan::factory()->create(['school_id' => $this->school->id, 'start_date' => '2026-04-07', 'end_date' => '2026-04-11']);
    $tempPath = storage_path('framework/testing/menu-plan-test.pdf');

    File::ensureDirectoryExists(dirname($tempPath));
    File::put($tempPath, 'pdf-test');

    $mock = Mockery::mock(RestaurantMenuPlanPdfService::class);
    $mock->shouldReceive('createPdf')
        ->once()
        ->andReturn($tempPath);

    $this->app->instance(RestaurantMenuPlanPdfService::class, $mock);

    $this->actingAs($this->admin, 'sanctum')
        ->get("/api/admin/restaurant/menu-plans/{$plan->id}/print")
        ->assertSuccessful()
        ->assertDownload('menu-plan-test.pdf')
        ->assertHeader('content-type', 'application/pdf');
});

test('cannot access plan from another school', function () {
    $plan = RestaurantMenuPlan::factory()->create(['school_id' => $this->otherSchool->id, 'start_date' => '2026-04-07', 'end_date' => '2026-04-11']);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/admin/restaurant/menu-plans/{$plan->id}")
        ->assertNotFound();
});

test('cannot print plan from another school', function () {
    $plan = RestaurantMenuPlan::factory()->create(['school_id' => $this->otherSchool->id, 'start_date' => '2026-04-07', 'end_date' => '2026-04-11']);

    $this->actingAs($this->admin, 'sanctum')
        ->get("/api/admin/restaurant/menu-plans/{$plan->id}/print")
        ->assertNotFound();
});
