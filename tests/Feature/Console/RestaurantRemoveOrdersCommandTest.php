<?php

use App\Models\RestaurantMenu;
use App\Models\RestaurantMenuPlan;
use App\Models\RestaurantMenuPlanBooking;
use App\Models\RestaurantMenuPlanEntry;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('removes all restaurant orders from the database', function (): void {
    $school = School::factory()->create();
    SchoolTool::factory()->create(['school_id' => $school->id]);
    $menu = RestaurantMenu::factory()->forSchool($school)->create();
    $plan = RestaurantMenuPlan::factory()->create([
        'school_id' => $school->id,
        'is_available' => true,
        'use_individual_schedule_values' => true,
        'visible_start_at' => now()->subDay(),
        'visible_end_at' => now()->addDay(),
        'order_start_at' => now()->subDay(),
        'order_end_at' => now()->addDay(),
    ]);
    $entry = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $plan->id,
        'restaurant_menu_id' => $menu->id,
    ]);
    $user = User::factory()->create(['school_id' => $school->id]);

    RestaurantMenuPlanBooking::create([
        'school_id' => $school->id,
        'user_id' => $user->id,
        'restaurant_menu_plan_entry_id' => $entry->id,
        'restaurant_eating_time_id' => null,
        'price' => 7.50,
        'quantity' => 1,
        'child_name' => 'Child One',
        'child_type' => 'child',
        'booked_at' => now(),
    ]);

    RestaurantMenuPlanBooking::create([
        'school_id' => $school->id,
        'user_id' => $user->id,
        'restaurant_menu_plan_entry_id' => $entry->id,
        'restaurant_eating_time_id' => null,
        'price' => 7.50,
        'quantity' => 1,
        'child_name' => 'Child Two',
        'child_type' => 'child',
        'booked_at' => now()->addSecond(),
    ]);

    $this->artisan('restaurant:remove-orders')
        ->expectsOutput('Removed 2 orders.')
        ->assertExitCode(0);

    $this->assertDatabaseCount('restaurant_menu_plan_bookings', 0);
});
