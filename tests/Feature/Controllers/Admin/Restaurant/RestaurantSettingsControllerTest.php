<?php

use App\Models\RestaurantCategory;
use App\Models\RestaurantFood;
use App\Models\RestaurantIngredientIcon;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect(['super_admin', 'admin', 'lunch_admin'])->each(function (string $role): void {
        Role::firstOrCreate([
            'name' => $role,
            'guard_name' => 'web',
        ]);
    });

    Storage::fake('local');
    Storage::fake('public');
    Storage::disk('local')->put('restaurant/svgs/pig-svgrepo-com.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
    Storage::disk('local')->put('restaurant/svgs/fish-svgrepo-com.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

    $this->school = School::factory()->create();
    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
    ]);
    $this->admin->assignRole('admin');
});

test('settings creates default categories for empty school', function () {
    $this->actingAs($this->admin, 'sanctum');

    $response = $this->getJson('/api/admin/restaurant/settings');

    $response->assertOk();
    expect($response->json('categories'))->toHaveCount(3)
        ->and(collect($response->json('categories'))->pluck('title')->all())
        ->toEqual(['Vorspeise', 'Hauptspeise', 'Nachspeise'])
        ->and($response->json('allergen_options'))
        ->toEqual(config('schooltool.eu_allergens'))
        ->and($response->json('user_settings.restaurant_foods_pagination_number'))
        ->toBe((int) config('schooltool.pagination'))
        ->and($response->json('can_manage_user_settings'))
        ->toBeTrue()
        ->and($response->json('can_manage_online_settings'))
        ->toBeTrue()
        ->and($response->json('online_settings.order_start_mode'))
        ->toBe('when_available')
        ->and($response->json('online_settings.order_start_week_offset'))
        ->toBe(2)
        ->and($response->json('online_settings.order_end_day_of_week'))
        ->toBe(5)
        ->and(collect($response->json('ingredient_icons'))->pluck('title')->all())
        ->toEqual(['Fisch', 'Schwein'])
        ->and($response->json('ingredient_icons.0.image_url'))
        ->toStartWith('data:image/svg+xml;base64,');
});

test('restaurant user can update own foods pagination setting', function () {
    $this->actingAs($this->admin, 'sanctum');

    $this->putJson('/api/admin/restaurant/user-settings', [
        'data' => [
            'restaurant_foods_pagination_number' => 18,
        ],
    ])->assertOk()
        ->assertJsonPath('data.restaurant_foods_pagination_number', 18);

    expect((int) $this->admin->fresh()->restaurant_foods_pagination_number)->toBe(18);
});

test('restaurant admin can update school wide online ordering settings', function () {
    $this->actingAs($this->admin, 'sanctum');

    $this->putJson('/api/admin/restaurant/online-settings', [
        'data' => [
            'order_start_mode' => 'scheduled',
            'order_start_week_offset' => 2,
            'order_start_day_of_week' => 0,
            'order_start_time' => '15:00',
            'order_end_week_offset' => 1,
            'order_end_day_of_week' => 5,
            'order_end_time' => '17:00',
        ],
    ])->assertOk()
        ->assertJsonPath('data.order_start_mode', 'scheduled')
        ->assertJsonPath('data.order_end_time', '17:00');

    $this->assertDatabaseHas('school_tools', [
        'school_id' => $this->school->id,
        'restaurant_menu_order_start_mode' => 'scheduled',
        'restaurant_menu_order_start_week_offset' => 2,
        'restaurant_menu_order_start_day_of_week' => 0,
        'restaurant_menu_order_start_time' => '15:00:00',
        'restaurant_menu_order_end_week_offset' => 1,
        'restaurant_menu_order_end_day_of_week' => 5,
        'restaurant_menu_order_end_time' => '17:00:00',
    ]);
});

test('restaurant online settings validate the required end time', function () {
    $this->actingAs($this->admin, 'sanctum');

    $this->putJson('/api/admin/restaurant/online-settings', [
        'data' => [
            'order_start_mode' => 'scheduled',
            'order_start_week_offset' => 2,
            'order_start_day_of_week' => 0,
            'order_start_time' => '15:00',
            'order_end_week_offset' => 1,
            'order_end_day_of_week' => 5,
            'order_end_time' => '',
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['data.order_end_time']);
});

test('category CRUD works for restaurant settings', function () {
    $this->actingAs($this->admin, 'sanctum');

    $created = $this->postJson('/api/admin/restaurant/categories', [
        'title' => 'Getraenke',
        'sort_order' => 40,
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.title', 'Getraenke');

    $categoryId = $created->json('data.id');

    $this->putJson("/api/admin/restaurant/categories/{$categoryId}", [
        'title' => 'Dessert Spezial',
        'sort_order' => 60,
    ])->assertOk()
        ->assertJsonPath('data.title', 'Dessert Spezial');

    $this->deleteJson("/api/admin/restaurant/categories/{$categoryId}")
        ->assertNoContent();

    $this->assertDatabaseMissing('restaurant_categories', [
        'id' => $categoryId,
    ]);
});

test('ingredient icon CRUD works with svg upload and title ordering', function () {
    $this->actingAs($this->admin, 'sanctum');

    $created = $this->post('/api/admin/restaurant/ingredient_icons', [
        'title' => 'Schwein',
        'image' => UploadedFile::fake()->create('pork.svg', 10, 'image/svg+xml'),
    ], ['Accept' => 'application/json']);

    $created->assertCreated()
        ->assertJsonPath('data.title', 'Schwein');

    $iconId = $created->json('data.id');
    $icon = RestaurantIngredientIcon::query()->findOrFail($iconId);
    Storage::disk('public')->assertExists($icon->image_path);

    $updated = $this->post("/api/admin/restaurant/ingredient_icons/{$iconId}", [
        '_method' => 'PUT',
        'title' => 'Schwein deutlich',
        'remove_image' => '1',
    ], ['Accept' => 'application/json']);

    $updated->assertOk()
        ->assertJsonPath('data.title', 'Schwein deutlich');

    $icon->refresh();
    expect($icon->image_path)->toBeNull();

    $this->deleteJson("/api/admin/restaurant/ingredient_icons/{$iconId}")
        ->assertNoContent();
});

test('ingredient icon upload only accepts svg files', function () {
    $this->actingAs($this->admin, 'sanctum');

    $this->post('/api/admin/restaurant/ingredient_icons', [
        'title' => 'Kein SVG',
        'image' => UploadedFile::fake()->image('pork.png'),
    ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['image']);
});

test('cannot delete category or ingredient icon while in use', function () {
    $this->actingAs($this->admin, 'sanctum');

    $category = RestaurantCategory::factory()->forSchool($this->school)->create([
        'title' => 'Hauptspeise',
    ]);
    $icon = RestaurantIngredientIcon::factory()->forSchool($this->school)->create([
        'title' => 'Fisch',
    ]);
    $food = RestaurantFood::factory()
        ->forUser($this->admin)
        ->forCategory($category)
        ->create();
    $food->ingredientIcons()->sync([$icon->id]);

    $this->deleteJson("/api/admin/restaurant/categories/{$category->id}")
        ->assertStatus(409);

    $this->deleteJson("/api/admin/restaurant/ingredient_icons/{$icon->id}")
        ->assertStatus(409);
});

test('settings merges legacy Oesterreich ingredient icon into Österreich', function () {
    Storage::disk('local')->put('restaurant/svgs/flag-for-flag-austria-svgrepo-com.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

    RestaurantIngredientIcon::factory()->forSchool($this->school)->create([
        'title' => 'Oesterreich',
        'image_path' => 'restaurant/svgs/flag-for-flag-austria-svgrepo-com.svg',
        'sort_order' => 10,
    ]);

    RestaurantIngredientIcon::factory()->forSchool($this->school)->create([
        'title' => 'Österreich',
        'image_path' => 'restaurant/svgs/flag-for-flag-austria-svgrepo-com.svg',
        'sort_order' => 20,
    ]);

    $this->actingAs($this->admin, 'sanctum');

    $response = $this->getJson('/api/admin/restaurant/settings');

    $response->assertOk();
    expect(collect($response->json('ingredient_icons'))->pluck('title')->filter(fn (string $title): bool => $title === 'Österreich'))
        ->toHaveCount(1);

    expect(RestaurantIngredientIcon::query()
        ->where('school_id', $this->school->id)
        ->whereIn('title', ['Oesterreich', 'Österreich'])
        ->count())->toBe(1);
});
