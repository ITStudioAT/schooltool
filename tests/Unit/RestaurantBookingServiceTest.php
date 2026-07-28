<?php

use App\Models\RestaurantEatingTime;
use App\Models\RestaurantMenu;
use App\Models\RestaurantMenuPlan;
use App\Models\RestaurantMenuPlanEntry;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\User;
use App\Services\RestaurantBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

afterEach(function () {
    Carbon::setTestNow();
});

it('stores the selected restaurant eating time from the booking payload', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);
    $menu = RestaurantMenu::factory()->forSchool($school)->create();
    $plan = RestaurantMenuPlan::factory()->create(['school_id' => $school->id]);
    $entry = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $plan->id,
        'restaurant_menu_id' => $menu->id,
    ]);
    $eatingTime = RestaurantEatingTime::factory()->create(['school_id' => $school->id]);

    $booking = app(RestaurantBookingService::class)->createBooking($user, $entry, [
        'restaurant_eating_time_id' => $eatingTime->id,
        'quantity' => 2,
    ]);

    expect($booking->restaurant_eating_time_id)->toBe($eatingTime->id);

    $this->assertDatabaseHas('restaurant_menu_plan_bookings', [
        'id' => $booking->id,
        'restaurant_eating_time_id' => $eatingTime->id,
    ]);
});

it('rejects a duplicate booking for the same user entry and eating-time slot', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);
    $menu = RestaurantMenu::factory()->forSchool($school)->create();
    $plan = RestaurantMenuPlan::factory()->create(['school_id' => $school->id]);
    $entry = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $plan->id,
        'restaurant_menu_id' => $menu->id,
    ]);

    $service = app(RestaurantBookingService::class);
    $service->createBooking($user, $entry, ['quantity' => 1]);

    expect(fn () => $service->createBooking($user, $entry, ['quantity' => 1]))
        ->toThrow(ValidationException::class);
});

it('accepts bookings while the global menu plan ordering window is open', function () {
    Carbon::setTestNow('2026-04-08 12:00:00');

    $school = School::factory()->create();
    SchoolTool::factory()->create([
        'school_id' => $school->id,
        'restaurant_menu_order_start_mode' => 'when_available',
        'restaurant_menu_order_end_week_offset' => 1,
        'restaurant_menu_order_end_day_of_week' => 5,
        'restaurant_menu_order_end_time' => '17:00:00',
    ]);

    $user = User::factory()->create(['school_id' => $school->id]);
    $menu = RestaurantMenu::factory()->forSchool($school)->create();
    $plan = RestaurantMenuPlan::factory()->create([
        'school_id' => $school->id,
        'start_date' => '2026-04-13',
        'end_date' => '2026-04-17',
        'is_available' => true,
        'use_individual_schedule_values' => false,
    ]);
    $entry = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $plan->id,
        'restaurant_menu_id' => $menu->id,
    ]);
    $eatingTime = RestaurantEatingTime::factory()->create(['school_id' => $school->id]);

    $entry->eatingTimes()->attach($eatingTime->id);
    $entry->load('eatingTimes', 'menuPlan');

    $errors = app(RestaurantBookingService::class)->validateBookingData([
        'restaurant_eating_time_id' => $eatingTime->id,
        'quantity' => 1,
    ], $entry, $user);

    expect($errors)->toBe([]);
});

it('accepts bookings while an individual menu plan ordering window is open', function () {
    Carbon::setTestNow('2026-04-02 12:00:00');

    $school = School::factory()->create();
    SchoolTool::factory()->create([
        'school_id' => $school->id,
        'restaurant_menu_order_start_mode' => 'scheduled',
        'restaurant_menu_order_start_week_offset' => 0,
        'restaurant_menu_order_start_day_of_week' => 6,
        'restaurant_menu_order_start_time' => '23:00:00',
        'restaurant_menu_order_end_week_offset' => 0,
        'restaurant_menu_order_end_day_of_week' => 6,
        'restaurant_menu_order_end_time' => '23:30:00',
    ]);

    $user = User::factory()->create(['school_id' => $school->id]);
    $menu = RestaurantMenu::factory()->forSchool($school)->create();
    $plan = RestaurantMenuPlan::factory()->create([
        'school_id' => $school->id,
        'start_date' => '2026-04-13',
        'end_date' => '2026-04-17',
        'is_available' => true,
        'use_individual_schedule_values' => true,
        'order_start_at' => '2026-04-02 08:00:00',
        'order_end_at' => '2026-04-02 17:00:00',
    ]);
    $entry = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $plan->id,
        'restaurant_menu_id' => $menu->id,
    ]);
    $eatingTime = RestaurantEatingTime::factory()->create(['school_id' => $school->id]);

    $entry->eatingTimes()->attach($eatingTime->id);
    $entry->load('eatingTimes', 'menuPlan');

    $errors = app(RestaurantBookingService::class)->validateBookingData([
        'restaurant_eating_time_id' => $eatingTime->id,
        'quantity' => 1,
    ], $entry, $user);

    expect($errors)->toBe([]);
});
