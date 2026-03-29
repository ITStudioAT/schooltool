<?php

use App\Models\RestaurantCategory;
use App\Models\RestaurantFood;
use App\Models\RestaurantIngredientIcon;
use App\Models\RestaurantMenu;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect(['super_admin', 'admin', 'lunch_admin', 'teacher'])->each(function (string $role): void {
        Role::firstOrCreate([
            'name' => $role,
            'guard_name' => 'web',
        ]);
    });

    $this->school = School::factory()->create();
    $this->otherSchool = School::factory()->create();

    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
    ]);
    $this->admin->assignRole('admin');

    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
    ]);
    $this->teacher->assignRole('teacher');

    $this->category = RestaurantCategory::factory()->forSchool($this->school)->create([
        'title' => 'Hauptspeise',
    ]);

    $this->foodOne = RestaurantFood::factory()
        ->forUser($this->admin)
        ->forCategory($this->category)
        ->create(['title' => 'Suppe']);

    $this->foodTwo = RestaurantFood::factory()
        ->forUser($this->admin)
        ->forCategory($this->category)
        ->create(['title' => 'Pasta']);

    $otherSchoolAdmin = User::factory()->create([
        'school_id' => $this->otherSchool->id,
        'schoolyear_id' => null,
    ]);
    $otherSchoolAdmin->assignRole('admin');

    $otherCategory = RestaurantCategory::factory()->forSchool($this->otherSchool)->create();
    $this->otherSchoolFood = RestaurantFood::factory()
        ->forUser($otherSchoolAdmin)
        ->forCategory($otherCategory)
        ->create();
});

test('returns 401 when restaurant menu index is unauthenticated', function () {
    $this->getJson('/api/admin/restaurant/menus')->assertStatus(401);
});

test('returns 403 when role has no restaurant menu access', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $this->getJson('/api/admin/restaurant/menus')->assertForbidden();
});

test('index returns only menus from current school', function () {
    $this->actingAs($this->admin, 'sanctum');

    $this->foodOne->update([
        'description' => 'Mit Schnittlauch',
        'allergens' => ['A', 'G'],
        'food_image_path' => 'restaurant/foods/suppe.jpg',
    ]);
    $icon = RestaurantIngredientIcon::factory()->forSchool($this->school)->create([
        'title' => 'Fisch',
        'image_path' => 'restaurant/ingredient-icons/fisch.svg',
    ]);
    $this->foodOne->ingredientIcons()->sync([$icon->id]);

    $menu = RestaurantMenu::factory()->forUser($this->admin)->create(['title' => 'Mittagsmenü']);
    $menu->foods()->sync([
        $this->foodOne->id => ['course_number' => 1],
    ]);

    $otherSchoolMenu = RestaurantMenu::factory()->create(['school_id' => $this->otherSchool->id]);
    $otherSchoolMenu->foods()->sync([
        $this->otherSchoolFood->id => ['course_number' => 1],
    ]);

    $response = $this->getJson('/api/admin/restaurant/menus');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.title'))->toBe('Mittagsmenü')
        ->and($response->json('data.0.foods.0.title'))->toBe('Suppe')
        ->and($response->json('data.0.foods.0.description'))->toBe('Mit Schnittlauch')
        ->and($response->json('data.0.foods.0.allergens'))->toBe(['A', 'G'])
        ->and($response->json('data.0.foods.0.ingredient_icons.0.title'))->toBe('Fisch')
        ->and($response->json('data.0.foods.0.course_number'))->toBe(1)
        ->and($response->json('data.0.foods.0.food_image_url'))->toContain('/storage/restaurant/foods/suppe.jpg');
});

test('store creates menu with ordered foods', function () {
    $this->actingAs($this->admin, 'sanctum');

    $response = $this->postJson('/api/admin/restaurant/menus', [
        'title' => 'Tagesmenü',
        'food_ids' => [$this->foodTwo->id, $this->foodOne->id],
        'price' => '9.5',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Tagesmenü')
        ->assertJsonPath('data.price', '9.50')
        ->assertJsonPath('data.foods.0.title', 'Pasta')
        ->assertJsonPath('data.foods.0.course_number', 1)
        ->assertJsonPath('data.foods.1.title', 'Suppe')
        ->assertJsonPath('data.foods.1.course_number', 2);

    $menu = RestaurantMenu::query()->firstOrFail();

    $this->assertDatabaseHas('restaurant_menus', [
        'id' => $menu->id,
        'school_id' => $this->school->id,
        'title' => 'Tagesmenü',
        'price' => '9.50',
    ]);
    $this->assertDatabaseHas('restaurant_food_restaurant_menu', [
        'restaurant_menu_id' => $menu->id,
        'restaurant_food_id' => $this->foodTwo->id,
        'course_number' => 1,
    ]);
    $this->assertDatabaseHas('restaurant_food_restaurant_menu', [
        'restaurant_menu_id' => $menu->id,
        'restaurant_food_id' => $this->foodOne->id,
        'course_number' => 2,
    ]);
});

test('store creates menu with title only', function () {
    $this->actingAs($this->admin, 'sanctum');

    $response = $this->postJson('/api/admin/restaurant/menus', [
        'title' => 'Nur Titel',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Nur Titel')
        ->assertJsonPath('data.price', null)
        ->assertJsonPath('data.foods', [])
        ->assertJsonPath('data.courses_count', 0);

    $menu = RestaurantMenu::query()->firstOrFail();

    $this->assertDatabaseHas('restaurant_menus', [
        'id' => $menu->id,
        'school_id' => $this->school->id,
        'title' => 'Nur Titel',
        'price' => null,
    ]);
    $this->assertDatabaseMissing('restaurant_food_restaurant_menu', [
        'restaurant_menu_id' => $menu->id,
    ]);
});

test('update changes menu title, price, and course order', function () {
    $this->actingAs($this->admin, 'sanctum');

    $menu = RestaurantMenu::factory()->forUser($this->admin)->create([
        'title' => 'Alt',
        'price' => '7.50',
    ]);
    $menu->foods()->sync([
        $this->foodOne->id => ['course_number' => 1],
        $this->foodTwo->id => ['course_number' => 2],
    ]);

    $response = $this->putJson("/api/admin/restaurant/menus/{$menu->id}", [
        'title' => 'Neu',
        'food_ids' => [$this->foodTwo->id],
        'price' => '11.5',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.title', 'Neu')
        ->assertJsonPath('data.price', '11.50')
        ->assertJsonPath('data.foods.0.title', 'Pasta');

    $this->assertDatabaseHas('restaurant_menus', [
        'id' => $menu->id,
        'title' => 'Neu',
        'price' => '11.50',
    ]);
    $this->assertDatabaseMissing('restaurant_food_restaurant_menu', [
        'restaurant_menu_id' => $menu->id,
        'restaurant_food_id' => $this->foodOne->id,
    ]);
});

test('destroy deletes menu and detaches foods', function () {
    $this->actingAs($this->admin, 'sanctum');

    $menu = RestaurantMenu::factory()->forUser($this->admin)->create();
    $menu->foods()->sync([
        $this->foodOne->id => ['course_number' => 1],
    ]);

    $this->deleteJson("/api/admin/restaurant/menus/{$menu->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('restaurant_menus', [
        'id' => $menu->id,
    ]);
    $this->assertDatabaseMissing('restaurant_food_restaurant_menu', [
        'restaurant_menu_id' => $menu->id,
    ]);
});

test('store rejects food from another school', function () {
    $this->actingAs($this->admin, 'sanctum');

    $this->postJson('/api/admin/restaurant/menus', [
        'title' => 'Ungültig',
        'food_ids' => [$this->otherSchoolFood->id],
        'price' => '8.5',
    ])->assertStatus(422);
});
