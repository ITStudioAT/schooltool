<?php

use App\Models\RestaurantCategory;
use App\Models\RestaurantFood;
use App\Models\RestaurantIngredientIcon;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        ->and($response->json('general_settings.service_email'))
        ->toBe('')
        ->and($response->json('general_settings.new_users_must_confirm_email'))
        ->toBeFalse()
        ->and($response->json('general_settings.new_users_confirmer_email'))
        ->toBe('')
        ->and($response->json('general_settings.user_information_intro_html'))
        ->toBe('')
        ->and($response->json('can_manage_general_settings'))
        ->toBeTrue()
        ->and($response->json('user_settings.restaurant_foods_pagination_number'))
        ->toBe((int) config('schooltool.pagination'))
        ->and($response->json('can_manage_user_settings'))
        ->toBeTrue()
        ->and($response->json('can_manage_online_settings'))
        ->toBeTrue()
        ->and($response->json('online_settings.visibility_start_mode'))
        ->toBe('when_available')
        ->and($response->json('online_settings.visibility_start_week_offset'))
        ->toBe(2)
        ->and($response->json('online_settings.order_start_mode'))
        ->toBe('when_available')
        ->and($response->json('online_settings.order_start_week_offset'))
        ->toBe(2)
        ->and($response->json('online_settings.order_end_day_of_week'))
        ->toBe(5)
        ->and($response->json('online_settings.visibility_end_mode'))
        ->toBe('plan_end')
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
            'visibility_start_mode' => 'scheduled',
            'visibility_start_week_offset' => 2,
            'visibility_start_day_of_week' => 0,
            'visibility_start_time' => '14:00',
            'order_start_mode' => 'scheduled',
            'order_start_week_offset' => 2,
            'order_start_day_of_week' => 0,
            'order_start_time' => '15:00',
            'order_end_week_offset' => 1,
            'order_end_day_of_week' => 5,
            'order_end_time' => '17:00',
            'visibility_end_mode' => 'week_end',
        ],
    ])->assertOk()
        ->assertJsonPath('data.visibility_start_mode', 'scheduled')
        ->assertJsonPath('data.visibility_start_time', '14:00')
        ->assertJsonPath('data.order_start_mode', 'scheduled')
        ->assertJsonPath('data.order_end_time', '17:00')
        ->assertJsonPath('data.visibility_end_mode', 'week_end');

    $this->assertDatabaseHas('school_tools', [
        'school_id' => $this->school->id,
        'restaurant_menu_visibility_start_mode' => 'scheduled',
        'restaurant_menu_visibility_start_week_offset' => 2,
        'restaurant_menu_visibility_start_day_of_week' => 0,
        'restaurant_menu_visibility_start_time' => '14:00:00',
        'restaurant_menu_order_start_mode' => 'scheduled',
        'restaurant_menu_order_start_week_offset' => 2,
        'restaurant_menu_order_start_day_of_week' => 0,
        'restaurant_menu_order_start_time' => '15:00:00',
        'restaurant_menu_order_end_week_offset' => 1,
        'restaurant_menu_order_end_day_of_week' => 5,
        'restaurant_menu_order_end_time' => '17:00:00',
        'restaurant_menu_visibility_end_mode' => 'week_end',
    ]);
});

test('restaurant admin can update school wide general settings', function () {
    $this->actingAs($this->admin, 'sanctum');

    $this->putJson('/api/admin/restaurant/general-settings', [
        'data' => [
            'restaurant_service_email' => 'restaurant@example.test',
            'restaurant_new_users_must_confirm_email' => true,
            'restaurant_new_users_confirmer_email' => 'freigabe@example.test',
            'restaurant_user_information_intro_html' => '<p><strong>Willkommen</strong> im Restaurant.</p>',
        ],
    ])->assertOk()
        ->assertJsonPath('data.service_email', 'restaurant@example.test')
        ->assertJsonPath('data.new_users_must_confirm_email', true)
        ->assertJsonPath('data.new_users_confirmer_email', 'freigabe@example.test')
        ->assertJsonPath('data.user_information_intro_html', '<p><strong>Willkommen</strong> im Restaurant.</p>');

    $this->assertDatabaseHas('school_tools', [
        'school_id' => $this->school->id,
        'restaurant_service_email' => 'restaurant@example.test',
        'restaurant_new_users_must_confirm_email' => true,
        'restaurant_new_users_confirmer_email' => 'freigabe@example.test',
        'restaurant_user_information_intro_html' => '<p><strong>Willkommen</strong> im Restaurant.</p>',
    ]);
});

test('restaurant general settings validate the service email address', function () {
    $this->actingAs($this->admin, 'sanctum');

    $this->putJson('/api/admin/restaurant/general-settings', [
        'data' => [
            'restaurant_service_email' => 'ungueltig',
            'restaurant_new_users_must_confirm_email' => true,
            'restaurant_new_users_confirmer_email' => 'freigabe@example.test',
            'restaurant_user_information_intro_html' => '<p>Info</p>',
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['data.restaurant_service_email']);
});

test('restaurant general settings require a confirmation email when new users must be confirmed', function () {
    $this->actingAs($this->admin, 'sanctum');

    $this->putJson('/api/admin/restaurant/general-settings', [
        'data' => [
            'restaurant_service_email' => 'restaurant@example.test',
            'restaurant_new_users_must_confirm_email' => true,
            'restaurant_new_users_confirmer_email' => '',
            'restaurant_user_information_intro_html' => '<p>Info</p>',
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['data.restaurant_new_users_confirmer_email']);
});

test('restaurant online settings validate the required end time', function () {
    $this->actingAs($this->admin, 'sanctum');

    $this->putJson('/api/admin/restaurant/online-settings', [
        'data' => [
            'visibility_start_mode' => 'scheduled',
            'visibility_start_week_offset' => 2,
            'visibility_start_day_of_week' => 0,
            'visibility_start_time' => '',
            'order_start_mode' => 'scheduled',
            'order_start_week_offset' => 2,
            'order_start_day_of_week' => 0,
            'order_start_time' => '15:00',
            'order_end_week_offset' => 1,
            'order_end_day_of_week' => 5,
            'order_end_time' => '',
            'visibility_end_mode' => 'plan_end',
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['data.visibility_start_time', 'data.order_end_time']);
});

test('restaurant online settings reject a visibility start after the order start', function () {
    $this->actingAs($this->admin, 'sanctum');

    $this->putJson('/api/admin/restaurant/online-settings', [
        'data' => [
            'visibility_start_mode' => 'scheduled',
            'visibility_start_week_offset' => 1,
            'visibility_start_day_of_week' => 1,
            'visibility_start_time' => '09:00',
            'order_start_mode' => 'scheduled',
            'order_start_week_offset' => 2,
            'order_start_day_of_week' => 0,
            'order_start_time' => '15:00',
            'order_end_week_offset' => 1,
            'order_end_day_of_week' => 5,
            'order_end_time' => '17:00',
            'visibility_end_mode' => 'plan_end',
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['data.visibility_start_time']);
});

test('restaurant online settings allow a scheduled visibility start with automatic order start', function () {
    $this->actingAs($this->admin, 'sanctum');

    $this->putJson('/api/admin/restaurant/online-settings', [
        'data' => [
            'visibility_start_mode' => 'scheduled',
            'visibility_start_week_offset' => 2,
            'visibility_start_day_of_week' => 5,
            'visibility_start_time' => '12:00',
            'order_start_mode' => 'when_available',
            'order_start_week_offset' => 2,
            'order_start_day_of_week' => 0,
            'order_start_time' => '15:00',
            'order_end_week_offset' => 1,
            'order_end_day_of_week' => 5,
            'order_end_time' => '17:00',
            'visibility_end_mode' => 'plan_end',
        ],
    ])->assertOk()
        ->assertJsonPath('data.visibility_start_mode', 'scheduled')
        ->assertJsonPath('data.order_start_mode', 'when_available');
});

test('restaurant admin can update visibility start to when orderable', function () {
    $this->actingAs($this->admin, 'sanctum');

    $this->putJson('/api/admin/restaurant/online-settings', [
        'data' => [
            'visibility_start_mode' => 'when_orderable',
            'visibility_start_week_offset' => 2,
            'visibility_start_day_of_week' => 0,
            'visibility_start_time' => '14:00',
            'order_start_mode' => 'scheduled',
            'order_start_week_offset' => 2,
            'order_start_day_of_week' => 0,
            'order_start_time' => '15:00',
            'order_end_week_offset' => 1,
            'order_end_day_of_week' => 5,
            'order_end_time' => '17:00',
            'visibility_end_mode' => 'plan_end',
        ],
    ])->assertOk()
        ->assertJsonPath('data.visibility_start_mode', 'when_orderable');

    $this->assertDatabaseHas('school_tools', [
        'school_id' => $this->school->id,
        'restaurant_menu_visibility_start_mode' => 'when_orderable',
    ]);
});

test('repair migration restores missing online visibility start columns', function () {
    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'restaurant_menu_order_start_mode' => 'scheduled',
        'restaurant_menu_order_start_week_offset' => 2,
        'restaurant_menu_order_start_day_of_week' => 1,
        'restaurant_menu_order_start_time' => '13:30:00',
        'restaurant_menu_order_end_week_offset' => 1,
        'restaurant_menu_order_end_day_of_week' => 5,
        'restaurant_menu_order_end_time' => '17:00:00',
        'restaurant_menu_visibility_end_mode' => 'plan_end',
    ]);

    Schema::table('school_tools', function (Blueprint $table) {
        $table->dropColumn([
            'restaurant_menu_visibility_start_mode',
            'restaurant_menu_visibility_start_week_offset',
            'restaurant_menu_visibility_start_day_of_week',
            'restaurant_menu_visibility_start_time',
        ]);
    });

    expect(Schema::hasColumn('school_tools', 'restaurant_menu_visibility_start_mode'))->toBeFalse();

    $migration = require database_path('migrations/2026_03_26_112507_repair_restaurant_menu_visibility_start_settings_on_school_tools_table.php');
    $migration->up();

    expect(Schema::hasColumn('school_tools', 'restaurant_menu_visibility_start_mode'))->toBeTrue()
        ->and(Schema::hasColumn('school_tools', 'restaurant_menu_visibility_start_week_offset'))->toBeTrue()
        ->and(Schema::hasColumn('school_tools', 'restaurant_menu_visibility_start_day_of_week'))->toBeTrue()
        ->and(Schema::hasColumn('school_tools', 'restaurant_menu_visibility_start_time'))->toBeTrue();

    $settings = DB::table('school_tools')
        ->where('school_id', $this->school->id)
        ->first();

    expect($settings?->restaurant_menu_visibility_start_mode)->toBe('scheduled')
        ->and((int) $settings?->restaurant_menu_visibility_start_week_offset)->toBe(2)
        ->and((int) $settings?->restaurant_menu_visibility_start_day_of_week)->toBe(1)
        ->and($settings?->restaurant_menu_visibility_start_time)->toStartWith('13:30');
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
