<?php

use App\Models\Import116;
use App\Models\RestaurantEatingTime;
use App\Models\RestaurantMenu;
use App\Models\RestaurantMenuPlan;
use App\Models\RestaurantMenuPlanBooking;
use App\Models\RestaurantMenuPlanEntry;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Carbon::setTestNow();
});

it('creates mostly single-recipient restaurant orders and occasional multi-child orders', function (): void {
    Carbon::setTestNow('2026-04-05 12:00:00');

    $school = School::factory()->create();
    Role::firstOrCreate([
        'name' => 'lunch_user',
        'guard_name' => 'web',
    ]);
    Role::firstOrCreate([
        'name' => 'super_admin',
        'guard_name' => 'web',
    ]);

    $menu = RestaurantMenu::factory()->forSchool($school)->create();
    $secondMenu = RestaurantMenu::factory()->forSchool($school)->create();
    $thirdMenu = RestaurantMenu::factory()->forSchool($school)->create();
    $plan = RestaurantMenuPlan::factory()->create([
        'school_id' => $school->id,
        'start_date' => '2026-03-30',
        'end_date' => '2026-04-02',
        'is_available' => true,
        'use_individual_schedule_values' => true,
        'visible_start_at' => '2026-04-01 08:00:00',
        'visible_end_at' => '2026-04-30 18:00:00',
        'order_start_at' => '2026-04-01 08:00:00',
        'order_end_at' => '2026-04-30 18:00:00',
    ]);
    $entry = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $plan->id,
        'restaurant_menu_id' => $menu->id,
    ]);
    $secondEntry = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $plan->id,
        'restaurant_menu_id' => $secondMenu->id,
    ]);
    $thirdEntry = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $plan->id,
        'restaurant_menu_id' => $thirdMenu->id,
    ]);
    $eatingTime = RestaurantEatingTime::factory()->create([
        'school_id' => $school->id,
        'eating_time' => '11:30:00',
    ]);
    $secondEatingTime = RestaurantEatingTime::factory()->create([
        'school_id' => $school->id,
        'eating_time' => '12:30:00',
    ]);
    $entry->eatingTimes()->attach($eatingTime->id);
    $entry->eatingTimes()->attach($secondEatingTime->id);
    $secondEntry->eatingTimes()->attach($eatingTime->id);
    $secondEntry->eatingTimes()->attach($secondEatingTime->id);
    $thirdEntry->eatingTimes()->attach($eatingTime->id);
    $thirdEntry->eatingTimes()->attach($secondEatingTime->id);

    $singleParent = User::factory()->create([
        'school_id' => $school->id,
        'first_name' => 'Anna',
        'last_name' => 'Einfach',
        'email' => 'anna.einfach@example.test',
    ]);
    $multiParent = User::factory()->create([
        'school_id' => $school->id,
        'first_name' => 'Ben',
        'last_name' => 'Mehrfach',
        'email' => 'ben.mehrfach@example.test',
    ]);

    $singleParent->assignRole('lunch_user');
    $singleParent->assignRole('super_admin');
    $multiParent->assignRole('lunch_user');

    Import116::factory()->forSchool($school)->create([
        'class' => '1A',
        'first_name' => 'Solo',
        'last_name' => 'Kind',
        'mother_email' => $singleParent->email,
    ]);
    Import116::factory()->forSchool($school)->create([
        'class' => '1B',
        'first_name' => 'Clara',
        'last_name' => 'Musterkind',
        'mother_email' => $multiParent->email,
    ]);
    Import116::factory()->forSchool($school)->create([
        'class' => '1B',
        'first_name' => 'David',
        'last_name' => 'Musterkind',
        'mother_email' => $multiParent->email,
    ]);

    $existingBooking = RestaurantMenuPlanBooking::create([
        'school_id' => $school->id,
        'user_id' => $singleParent->id,
        'restaurant_menu_plan_entry_id' => $entry->id,
        'restaurant_eating_time_id' => $eatingTime->id,
        'price' => 7.50,
        'quantity' => 2,
        'child_name' => 'Old Child',
        'child_type' => 'child',
        'notes' => 'Old order that should be removed',
        'booked_at' => now()->subDay(),
    ]);

    $this->artisan('restaurant:create-orders', [
        'kw' => 14,
        'n' => 10,
        '--year' => 2026,
    ])
        ->expectsOutputToContain('Created 10 orders for KW 14.')
        ->expectsOutputToContain('Parent orders with more than 1 child:')
        ->expectsOutputToContain('- Order 10: Ben Mehrfach <ben.mehrfach@example.test> [#')
        ->assertExitCode(0);

    $this->assertDatabaseMissing('restaurant_menu_plan_bookings', [
        'id' => $existingBooking->id,
    ]);
    $this->assertDatabaseCount('restaurant_menu_plan_bookings', 10);

    $bookings = RestaurantMenuPlanBooking::query()
        ->with('user')
        ->orderBy('id')
        ->get();

    expect($bookings)->toHaveCount(10);

    $eatingTimeIds = $bookings->pluck('restaurant_eating_time_id')->unique()->values()->all();
    expect($eatingTimeIds)->toHaveCount(2)
        ->and($eatingTimeIds)->toContain($eatingTime->id, $secondEatingTime->id);

    $bookings->take(9)->each(function (RestaurantMenuPlanBooking $booking): void {
        expect($booking->quantity)->toBe(1)
            ->and($booking->metadata['recipients'])->toHaveCount(1);
    });

    $bookingsByTime = $bookings->groupBy('restaurant_eating_time_id');

    $bookingsByTime->each(function ($timeBookings): void {
        expect($timeBookings->pluck('restaurant_menu_plan_entry_id')->unique()->count())->toBeGreaterThan(1);
    });

    expect($bookings->first()->metadata['recipients'][0]['name'])->toBe('Solo Kind')
        ->and($bookings->last()->user_id)->toBe($multiParent->id)
        ->and($bookings->last()->quantity)->toBe(2)
        ->and($bookings->last()->metadata['recipients'])->toHaveCount(2)
        ->and($bookings->last()->metadata['recipients'][0]['name'])->toBe('Clara Musterkind')
        ->and($bookings->last()->metadata['recipients'][1]['name'])->toBe('David Musterkind');
});
