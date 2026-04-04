<?php

use App\Models\RestaurantEatingTime;
use App\Models\RestaurantFood;
use App\Models\RestaurantIngredientIcon;
use App\Models\RestaurantMenu;
use App\Models\RestaurantMenuPlan;
use App\Models\RestaurantMenuPlanBooking;
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

test('index returns booked menu counters for each entry day', function () {
    $plan = RestaurantMenuPlan::factory()->create([
        'school_id' => $this->school->id,
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-05',
    ]);

    $entry = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $plan->id,
        'plan_date' => '2026-04-03',
        'restaurant_menu_id' => $this->menu->id,
        'menu_title' => 'Freitagsmenue',
    ]);

    RestaurantMenuPlanBooking::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->admin->id,
        'restaurant_menu_plan_entry_id' => $entry->id,
        'restaurant_eating_time_id' => $this->eatingTime->id,
        'price' => 8.50,
        'quantity' => 3,
        'booked_at' => now(),
    ]);

    $secondBookedUser = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => null]);

    RestaurantMenuPlanBooking::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $secondBookedUser->id,
        'restaurant_menu_plan_entry_id' => $entry->id,
        'restaurant_eating_time_id' => $this->eatingTime->id,
        'price' => 8.50,
        'quantity' => 2,
        'booked_at' => now(),
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/admin/restaurant/menu-plans')
        ->assertOk()
        ->assertJsonPath('data.0.entries.0.plan_date', '2026-04-03')
        ->assertJsonPath('data.0.entries.0.booked_menu_count', 5);
});

test('store creates menu plan with entries and eating times', function () {
    $this->actingAs($this->admin, 'sanctum');

    $response = $this->postJson('/api/admin/restaurant/menu-plans', [
        'title' => 'Testwoche',
        'start_date' => '2026-04-07',
        'end_date' => '2026-04-11',
        'is_available' => true,
        'visibility_start_mode' => 'scheduled',
        'visibility_start_week_offset' => 1,
        'visibility_start_day_of_week' => 2,
        'visibility_start_time' => '09:15',
        'order_start_mode' => 'scheduled',
        'order_start_week_offset' => 1,
        'order_start_day_of_week' => 2,
        'order_start_time' => '10:00',
        'order_end_week_offset' => 0,
        'order_end_day_of_week' => 5,
        'order_end_time' => '17:00',
        'visibility_end_mode' => 'week_end',
        'use_individual_schedule_values' => true,
        'visible_start_at' => '2026-04-01T09:15',
        'visible_end_at' => '2026-04-12T23:59',
        'order_start_at' => '2026-04-01T10:00',
        'order_end_at' => '2026-04-10T17:00',
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
        ->assertJsonPath('data.visibility_start_mode', 'scheduled')
        ->assertJsonPath('data.order_start_mode', 'scheduled')
        ->assertJsonPath('data.visibility_end_mode', 'week_end')
        ->assertJsonPath('data.use_individual_schedule_values', true)
        ->assertJsonPath('data.visible_start_at', '2026-04-01T09:15')
        ->assertJsonPath('data.visible_end_at', '2026-04-12T23:59')
        ->assertJsonPath('data.order_start_at', '2026-04-01T10:00')
        ->assertJsonPath('data.order_end_at', '2026-04-10T17:00')
        ->assertJsonCount(1, 'data.entries')
        ->assertJsonPath('data.entries.0.menu_title', 'Montagsmenue')
        ->assertJsonPath('data.entries.0.price', '8.50')
        ->assertJsonPath('data.entries.0.comments', 'Ohne Sellerie servieren.');

    expect(RestaurantMenuPlan::query()->where('school_id', $this->school->id)->count())->toBe(1);
    expect(RestaurantMenuPlan::query()->first()?->is_available)->toBeTrue();
    expect(RestaurantMenuPlan::query()->first()?->use_individual_schedule_values)->toBeTrue();
    expect(RestaurantMenuPlan::query()->first()?->visible_start_at?->format('Y-m-d\TH:i'))->toBe('2026-04-01T09:15');
    expect(RestaurantMenuPlanEntry::query()->count())->toBe(1);
});

test('show returns plan with entries and eating time details', function () {
    $plan = RestaurantMenuPlan::factory()->create([
        'school_id' => $this->school->id,
        'start_date' => '2026-04-07',
        'end_date' => '2026-04-11',
        'is_available' => true,
        'visibility_start_mode' => 'scheduled',
        'visibility_start_week_offset' => 1,
        'visibility_start_day_of_week' => 2,
        'visibility_start_time' => '09:15:00',
        'order_start_mode' => 'scheduled',
        'order_start_week_offset' => 1,
        'order_start_day_of_week' => 2,
        'order_start_time' => '10:00:00',
        'order_end_week_offset' => 0,
        'order_end_day_of_week' => 5,
        'order_end_time' => '17:00:00',
        'visibility_end_mode' => 'week_end',
        'visible_start_at' => '2026-04-01 09:15:00',
        'visible_end_at' => '2026-04-12 23:59:00',
        'order_start_at' => '2026-04-01 10:00:00',
        'order_end_at' => '2026-04-10 17:00:00',
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
        ->assertJsonPath('data.visibility_start_mode', 'scheduled')
        ->assertJsonPath('data.order_start_mode', 'scheduled')
        ->assertJsonPath('data.visibility_end_mode', 'week_end')
        ->assertJsonPath('data.use_individual_schedule_values', false)
        ->assertJsonPath('data.visible_start_at', '2026-04-01T09:15')
        ->assertJsonPath('data.visible_end_at', '2026-04-12T23:59')
        ->assertJsonPath('data.order_start_at', '2026-04-01T10:00')
        ->assertJsonPath('data.order_end_at', '2026-04-10T17:00')
        ->assertJsonPath('data.has_bookings', false)
        ->assertJsonPath('data.can_delete', true)
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
            'visibility_start_mode' => 'scheduled',
            'visibility_start_week_offset' => 1,
            'visibility_start_day_of_week' => 2,
            'visibility_start_time' => '09:15',
            'order_start_mode' => 'scheduled',
            'order_start_week_offset' => 1,
            'order_start_day_of_week' => 2,
            'order_start_time' => '10:00',
            'order_end_week_offset' => 0,
            'order_end_day_of_week' => 5,
            'order_end_time' => '17:00',
            'visibility_end_mode' => 'week_end',
            'use_individual_schedule_values' => true,
            'visible_start_at' => '2026-04-01T09:15',
            'visible_end_at' => '2026-04-12T23:59',
            'order_start_at' => '2026-04-01T10:00',
            'order_end_at' => '2026-04-10T17:00',
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
        ->assertJsonPath('data.visibility_start_mode', 'scheduled')
        ->assertJsonPath('data.use_individual_schedule_values', true)
        ->assertJsonPath('data.visible_start_at', '2026-04-01T09:15')
        ->assertJsonPath('data.order_end_at', '2026-04-10T17:00')
        ->assertJsonCount(1, 'data.entries')
        ->assertJsonPath('data.entries.0.plan_date', '2026-04-08')
        ->assertJsonPath('data.entries.0.menu_title', 'Dienstagsmenue')
        ->assertJsonPath('data.entries.0.price', '12.10')
        ->assertJsonPath('data.entries.0.comments', 'Mit Salat.');

    expect($plan->fresh()?->is_available)->toBeTrue();
    expect($plan->fresh()?->use_individual_schedule_values)->toBeTrue();
    expect($plan->fresh()?->order_end_at?->format('Y-m-d\TH:i'))->toBe('2026-04-10T17:00');
    expect(RestaurantMenuPlanEntry::query()->where('restaurant_menu_plan_id', $plan->id)->count())->toBe(1);
});

test('update preserves booked entries while allowing unlocked entries to change', function () {
    $plan = RestaurantMenuPlan::factory()->create([
        'school_id' => $this->school->id,
        'start_date' => '2026-04-07',
        'end_date' => '2026-04-11',
    ]);

    $lockedEntry = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $plan->id,
        'plan_date' => '2026-04-08',
        'restaurant_menu_id' => $this->menu->id,
        'menu_title' => 'Gebucht',
        'price' => '8.50',
        'comments' => 'Bitte warm halten.',
    ]);
    $lockedEntry->eatingTimes()->sync([$this->eatingTime->id]);

    $editableEntry = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $plan->id,
        'plan_date' => '2026-04-09',
        'restaurant_menu_id' => $this->menu->id,
        'menu_title' => 'Bearbeitbar',
        'price' => '7.90',
        'comments' => 'Alt',
    ]);
    $editableEntry->eatingTimes()->sync([$this->eatingTime->id]);

    RestaurantMenuPlanBooking::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->admin->id,
        'restaurant_menu_plan_entry_id' => $lockedEntry->id,
        'restaurant_eating_time_id' => $this->eatingTime->id,
        'price' => 8.50,
        'quantity' => 1,
        'booked_at' => now(),
    ]);

    $menu2 = RestaurantMenu::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/admin/restaurant/menu-plans/{$plan->id}", [
            'title' => 'Aktualisiert mit Sperre',
            'start_date' => '2026-04-07',
            'end_date' => '2026-04-11',
            'is_available' => false,
            'visibility_start_mode' => 'when_available',
            'order_start_mode' => 'when_available',
            'order_end_week_offset' => 1,
            'order_end_day_of_week' => 5,
            'order_end_time' => '17:00',
            'visibility_end_mode' => 'plan_end',
            'entries' => [
                [
                    'id' => $lockedEntry->id,
                    'plan_date' => '2026-04-08',
                    'menu_id' => $this->menu->id,
                    'menu_title' => 'Gebucht',
                    'price' => '8.50',
                    'comments' => 'Bitte warm halten.',
                    'eating_time_ids' => [$this->eatingTime->id],
                ],
                [
                    'id' => $editableEntry->id,
                    'plan_date' => '2026-04-09',
                    'menu_id' => $menu2->id,
                    'menu_title' => 'Bearbeitbar neu',
                    'price' => '12.10',
                    'comments' => 'Mit Salat.',
                    'eating_time_ids' => [],
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Aktualisiert mit Sperre')
        ->assertJsonCount(2, 'data.entries');

    expect($lockedEntry->fresh()?->restaurant_menu_id)->toBe($this->menu->id);
    expect($lockedEntry->fresh()?->menu_title)->toBe('Gebucht');
    expect($lockedEntry->fresh()?->comments)->toBe('Bitte warm halten.');
    expect($lockedEntry->fresh()?->eatingTimes()->pluck('restaurant_eating_times.id')->all())->toBe([$this->eatingTime->id]);

    expect($editableEntry->fresh()?->restaurant_menu_id)->toBe($menu2->id);
    expect($editableEntry->fresh()?->menu_title)->toBe('Bearbeitbar neu');
    expect($editableEntry->fresh()?->price)->toBe('12.10');
    expect($editableEntry->fresh()?->comments)->toBe('Mit Salat.');
    expect($editableEntry->fresh()?->eatingTimes()->count())->toBe(0);

    expect(RestaurantMenuPlanBooking::query()->where('restaurant_menu_plan_entry_id', $lockedEntry->id)->count())->toBe(1);
    expect(RestaurantMenuPlanEntry::query()->where('restaurant_menu_plan_id', $plan->id)->count())->toBe(2);
});

test('update rejects changes to booked entries', function () {
    $plan = RestaurantMenuPlan::factory()->create([
        'school_id' => $this->school->id,
        'start_date' => '2026-04-07',
        'end_date' => '2026-04-11',
    ]);

    $lockedEntry = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $plan->id,
        'plan_date' => '2026-04-08',
        'restaurant_menu_id' => $this->menu->id,
        'menu_title' => 'Gebucht',
        'price' => '8.50',
        'comments' => 'Bitte warm halten.',
    ]);
    $lockedEntry->eatingTimes()->sync([$this->eatingTime->id]);

    RestaurantMenuPlanBooking::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->admin->id,
        'restaurant_menu_plan_entry_id' => $lockedEntry->id,
        'restaurant_eating_time_id' => $this->eatingTime->id,
        'price' => 8.50,
        'quantity' => 1,
        'booked_at' => now(),
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/admin/restaurant/menu-plans/{$plan->id}", [
            'title' => 'Konflikt',
            'start_date' => '2026-04-07',
            'end_date' => '2026-04-11',
            'is_available' => false,
            'visibility_start_mode' => 'when_available',
            'order_start_mode' => 'when_available',
            'order_end_week_offset' => 1,
            'order_end_day_of_week' => 5,
            'order_end_time' => '17:00',
            'visibility_end_mode' => 'plan_end',
            'entries' => [
                [
                    'id' => $lockedEntry->id,
                    'plan_date' => '2026-04-08',
                    'menu_id' => $this->menu->id,
                    'menu_title' => 'Gebucht veraendert',
                    'price' => '8.50',
                    'comments' => 'Bitte warm halten.',
                    'eating_time_ids' => [$this->eatingTime->id],
                ],
            ],
        ])
        ->assertStatus(409)
        ->assertJsonPath('message', 'Gebuchte Menüs sind gesperrt und können nicht geändert, verschoben oder gelöscht werden.');

    expect($lockedEntry->fresh()?->menu_title)->toBe('Gebucht');
    expect(RestaurantMenuPlanBooking::query()->where('restaurant_menu_plan_entry_id', $lockedEntry->id)->count())->toBe(1);
});

test('update can shrink the menu plan range to remove the first day', function () {
    $plan = RestaurantMenuPlan::factory()->create([
        'school_id' => $this->school->id,
        'start_date' => '2026-04-07',
        'end_date' => '2026-04-11',
    ]);

    RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $plan->id,
        'plan_date' => '2026-04-07',
        'restaurant_menu_id' => $this->menu->id,
        'menu_title' => 'Montagsmenue',
    ]);
    RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $plan->id,
        'plan_date' => '2026-04-08',
        'restaurant_menu_id' => $this->menu->id,
        'menu_title' => 'Dienstagsmenue',
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/admin/restaurant/menu-plans/{$plan->id}", [
            'title' => 'Gekuerzte Woche',
            'start_date' => '2026-04-08',
            'end_date' => '2026-04-11',
            'is_available' => false,
            'visibility_start_mode' => 'when_available',
            'order_start_mode' => 'when_available',
            'order_end_week_offset' => 1,
            'order_end_day_of_week' => 5,
            'order_end_time' => '17:00',
            'visibility_end_mode' => 'plan_end',
            'entries' => [
                [
                    'plan_date' => '2026-04-08',
                    'menu_id' => $this->menu->id,
                    'menu_title' => 'Dienstagsmenue',
                    'price' => '8.50',
                    'comments' => null,
                    'eating_time_ids' => [$this->eatingTime->id],
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.start_date', '2026-04-08')
        ->assertJsonPath('data.end_date', '2026-04-11')
        ->assertJsonCount(1, 'data.entries')
        ->assertJsonPath('data.entries.0.plan_date', '2026-04-08');

    expect($plan->fresh()?->start_date?->toDateString())->toBe('2026-04-08');
    expect(
        RestaurantMenuPlanEntry::query()
            ->where('restaurant_menu_plan_id', $plan->id)
            ->pluck('plan_date')
            ->map(fn ($date) => $date->toDateString())
            ->all()
    )->toBe(['2026-04-08']);
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

test('destroy returns conflict when menu plan has bookings', function () {
    $plan = RestaurantMenuPlan::factory()->create([
        'school_id' => $this->school->id,
        'start_date' => '2026-04-07',
        'end_date' => '2026-04-11',
    ]);

    $entry = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $plan->id,
        'plan_date' => '2026-04-08',
        'restaurant_menu_id' => $this->menu->id,
    ]);

    RestaurantMenuPlanBooking::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->admin->id,
        'restaurant_menu_plan_entry_id' => $entry->id,
        'restaurant_eating_time_id' => $this->eatingTime->id,
        'price' => 8.50,
        'quantity' => 1,
        'booked_at' => now(),
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/admin/restaurant/menu-plans/{$plan->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'Menüplan kann nicht gelöscht werden, da bereits Buchungen vorhanden sind.');

    expect(RestaurantMenuPlan::query()->find($plan->id))->not->toBeNull();
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

test('print downloads booking pdf for current school when requested', function () {
    $plan = RestaurantMenuPlan::factory()->create(['school_id' => $this->school->id, 'start_date' => '2026-04-07', 'end_date' => '2026-04-11']);
    $tempPath = storage_path('framework/testing/menu-plan-bookings-test.pdf');

    File::ensureDirectoryExists(dirname($tempPath));
    File::put($tempPath, 'pdf-test');

    $mock = Mockery::mock(RestaurantMenuPlanPdfService::class);
    $mock->shouldReceive('createBookingsPdf')
        ->once()
        ->andReturn($tempPath);

    $this->app->instance(RestaurantMenuPlanPdfService::class, $mock);

    $this->actingAs($this->admin, 'sanctum')
        ->get("/api/admin/restaurant/menu-plans/{$plan->id}/print?type=bookings")
        ->assertSuccessful()
        ->assertDownload('menu-plan-bookings-test.pdf')
        ->assertHeader('content-type', 'application/pdf');
});

test('print downloads order summary pdf for current school when requested', function () {
    $plan = RestaurantMenuPlan::factory()->create(['school_id' => $this->school->id, 'start_date' => '2026-04-07', 'end_date' => '2026-04-11']);
    $tempPath = storage_path('framework/testing/menu-plan-summary-test.pdf');

    File::ensureDirectoryExists(dirname($tempPath));
    File::put($tempPath, 'pdf-test');

    $mock = Mockery::mock(RestaurantMenuPlanPdfService::class);
    $mock->shouldReceive('createOrderSummaryPdf')
        ->once()
        ->andReturn($tempPath);

    $this->app->instance(RestaurantMenuPlanPdfService::class, $mock);

    $this->actingAs($this->admin, 'sanctum')
        ->get("/api/admin/restaurant/menu-plans/{$plan->id}/print?type=summary")
        ->assertSuccessful()
        ->assertDownload('menu-plan-summary-test.pdf')
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
