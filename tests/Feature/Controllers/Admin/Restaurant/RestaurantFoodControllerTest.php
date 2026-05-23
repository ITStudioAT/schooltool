<?php

use App\Models\Licence;
use App\Models\RestaurantCategory;
use App\Models\RestaurantFood;
use App\Models\RestaurantIngredientIcon;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect(['super_admin', 'admin', 'lunch_admin', 'teacher'])->each(function (string $role): void {
        Role::firstOrCreate([
            'name' => $role,
            'guard_name' => 'web',
        ]);
    });

    Storage::fake('local');
    Storage::fake('public');
    Storage::disk('local')->put('restaurant/ingredient_icons/Rindfleisch.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

    $this->school = School::factory()->create();
    $this->otherSchool = School::factory()->create();
    $licence = Licence::query()->create([
        'name' => 'Restaurant',
        'long_name' => 'Restaurant',
    ]);
    SchoolLicence::query()->create([
        'school_id' => $this->school->id,
        'licence_id' => $licence->id,
        'valid_until' => now()->addYear(),
    ]);
    SchoolTool::query()->create([
        'school_id' => $this->school->id,
        'restaurant_visible_admin' => true,
    ]);

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
        'sort_order' => 20,
    ]);

    $this->icon = RestaurantIngredientIcon::factory()->forSchool($this->school)->create([
        'title' => 'Rind',
    ]);

    $this->otherCategory = RestaurantCategory::factory()->forSchool($this->otherSchool)->create();
});

test('returns 401 when restaurant food index is unauthenticated', function () {
    $this->getJson('/api/admin/restaurant/foods')->assertStatus(401);
});

test('returns 403 when role has no restaurant access', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $this->getJson('/api/admin/restaurant/foods')->assertStatus(403);
});

test('returns 403 when school has no restaurant licence', function () {
    SchoolLicence::query()
        ->where('school_id', $this->school->id)
        ->delete();

    $this->actingAs($this->admin, 'sanctum');

    $this->getJson('/api/admin/restaurant/foods')->assertStatus(403);
});

test('index returns only foods from current school', function () {
    $this->actingAs($this->admin, 'sanctum');

    RestaurantFood::factory()
        ->forUser($this->admin)
        ->forCategory($this->category)
        ->create(['title' => 'Lasagne']);

    $otherSchoolAdmin = User::factory()->create([
        'school_id' => $this->otherSchool->id,
        'schoolyear_id' => null,
    ]);
    $otherSchoolAdmin->assignRole('admin');

    $otherCategory = RestaurantCategory::factory()->forSchool($this->otherSchool)->create();
    RestaurantFood::factory()
        ->forUser($otherSchoolAdmin)
        ->forCategory($otherCategory)
        ->create(['title' => 'Fischplatte']);

    $response = $this->getJson('/api/admin/restaurant/foods');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.title'))->toBe('Lasagne');
});

test('settings no longer sync ingredient icons from the private directory automatically', function () {
    $this->actingAs($this->admin, 'sanctum');

    $response = $this->getJson('/api/admin/restaurant/settings');

    $response->assertOk()
        ->assertJsonPath('ingredient_icons.0.title', 'Rind');

    $this->icon->refresh();
    expect($this->icon->image_path)->toBeNull();
});

test('store creates food with new category and image', function () {
    $this->actingAs($this->admin, 'sanctum');

    $response = $this->post('/api/admin/restaurant/foods', [
        'title' => 'Apfelstrudel',
        'description' => 'Warm serviert',
        'category_title' => 'Suesses',
        'allergens' => ['Gluten', 'Milch'],
        'ingredient_icon_ids' => [$this->icon->id],
        'price' => '4.9',
        'food_image' => UploadedFile::fake()->image('dessert.jpg'),
    ], ['Accept' => 'application/json']);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Apfelstrudel')
        ->assertJsonPath('data.category.title', 'Suesses')
        ->assertJsonPath('data.ingredient_icons.0.title', 'Rind');

    $food = RestaurantFood::query()->firstOrFail();

    $this->assertDatabaseHas('restaurant_foods', [
        'id' => $food->id,
        'school_id' => $this->school->id,
        'title' => 'Apfelstrudel',
        'price' => '4.9',
    ]);
    $this->assertDatabaseHas('restaurant_categories', [
        'school_id' => $this->school->id,
        'title' => 'Suesses',
    ]);
    Storage::disk('public')->assertExists($food->food_image_path);
    expect($food->ingredientIcons()->pluck('restaurant_ingredient_icons.id')->all())->toEqual([$this->icon->id]);
});

test('update changes food and replaces image', function () {
    $this->actingAs($this->admin, 'sanctum');

    $food = RestaurantFood::factory()
        ->forUser($this->admin)
        ->forCategory($this->category)
        ->create([
            'title' => 'Burger',
            'food_image_path' => 'restaurant/foods/old-image.jpg',
        ]);

    Storage::disk('public')->put('restaurant/foods/old-image.jpg', 'old');

    $response = $this->post("/api/admin/restaurant/foods/{$food->id}", [
        '_method' => 'PUT',
        'title' => 'Veggie Burger',
        'description' => 'Mit Salat',
        'category_title' => 'Hauptspeise',
        'allergens' => ['Sesam'],
        'ingredient_icon_ids' => [$this->icon->id],
        'price' => '8.5',
        'food_image' => UploadedFile::fake()->image('new-image.jpg'),
    ], ['Accept' => 'application/json']);

    $response->assertOk()
        ->assertJsonPath('data.title', 'Veggie Burger')
        ->assertJsonPath('data.allergens.0', 'Sesam');

    $food->refresh();

    $this->assertDatabaseHas('restaurant_foods', [
        'id' => $food->id,
        'title' => 'Veggie Burger',
        'price' => '8.5',
    ]);
    Storage::disk('public')->assertMissing('restaurant/foods/old-image.jpg');
    Storage::disk('public')->assertExists($food->food_image_path);
});

test('destroy deletes food and stored image', function () {
    $this->actingAs($this->admin, 'sanctum');

    $food = RestaurantFood::factory()
        ->forUser($this->admin)
        ->forCategory($this->category)
        ->create([
            'food_image_path' => 'restaurant/foods/delete-me.jpg',
        ]);

    Storage::disk('public')->put('restaurant/foods/delete-me.jpg', 'old');

    $this->deleteJson("/api/admin/restaurant/foods/{$food->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('restaurant_foods', [
        'id' => $food->id,
    ]);
    Storage::disk('public')->assertMissing('restaurant/foods/delete-me.jpg');
});

test('store rejects category from another school', function () {
    $this->actingAs($this->admin, 'sanctum');

    $this->postJson('/api/admin/restaurant/foods', [
        'title' => 'Fremde Kategorie',
        'category_id' => $this->otherCategory->id,
        'allergens' => [],
        'ingredient_icon_ids' => [],
    ])->assertStatus(403);
});
