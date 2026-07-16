<?php

namespace Tests\Feature;

use App\Http\Middleware\ToolLicensed;
use App\Http\Requests\Homepage\RestaurantCreateBookingRequest;
use App\Models\RestaurantEatingTime;
use App\Models\RestaurantMenu;
use App\Models\RestaurantMenuPlan;
use App\Models\RestaurantMenuPlanBooking;
use App\Models\RestaurantMenuPlanEntry;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\User;
use App\Services\RestaurantBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RestaurantBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_can_be_created(): void
    {
        // Create test data
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);
        $menuPlanEntry = RestaurantMenuPlanEntry::factory()->create(['price' => 7.25]);
        $eatingTime = RestaurantEatingTime::factory()->create(['school_id' => $school->id]);

        // Authenticate user
        $this->actingAs($user);

        // Create booking - test direct service call instead of API
        $bookingData = [
            'restaurant_menu_plan_entry_id' => $menuPlanEntry->id,
            'restaurant_eating_time_id' => $eatingTime->id,
            'quantity' => 2,
            'price' => 1.00,
            'child_name' => 'Test Child',
            'child_type' => 'child',
            'notes' => 'Test notes',
        ];

        // Test service directly
        $bookingService = app(RestaurantBookingService::class);
        $booking = $bookingService->createBooking($user, $menuPlanEntry, $bookingData);

        // Verify booking was created in database
        $this->assertDatabaseHas('restaurant_menu_plan_bookings', [
            'user_id' => $user->id,
            'restaurant_menu_plan_entry_id' => $menuPlanEntry->id,
            'quantity' => 2,
            'price' => 7.25,
            'total_price' => 14.50,
            'child_name' => 'Test Child',
            'child_type' => 'child',
        ]);

        // Verify booking object
        $this->assertNotNull($booking);
        $this->assertEquals($user->id, $booking->user_id);
        $this->assertEquals($menuPlanEntry->id, $booking->restaurant_menu_plan_entry_id);
        $this->assertEquals(2, $booking->quantity);
        $this->assertEquals(7.25, $booking->price);
        $this->assertEquals(14.50, $booking->total_price);
        $this->assertEquals('Test Child', $booking->child_name);
        $this->assertEquals('child', $booking->child_type);
    }

    public function test_booking_requires_authentication(): void
    {
        $response = $this->postJson('/api/homepage/restaurant/bookings', [
            'data' => [
                'restaurant_menu_plan_entry_id' => 1,
                'quantity' => 1,
            ],
        ]);

        $response->assertStatus(401);
    }

    public function test_booking_requires_valid_data(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create a menu plan entry for exists validation
        $menuPlanEntry = RestaurantMenuPlanEntry::factory()->create();

        // Test validation rules directly
        $request = new RestaurantCreateBookingRequest;

        // Test empty data - only restaurant_menu_plan_entry_id is required
        $validator = Validator::make(['data' => []], $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('data.restaurant_menu_plan_entry_id', $validator->errors()->toArray());
        // quantity is nullable, so it shouldn't fail when missing

        // Test invalid quantity (when provided)
        $validator = Validator::make([
            'data' => [
                'restaurant_menu_plan_entry_id' => $menuPlanEntry->id,
                'quantity' => 0, // Invalid: must be at least 1
            ],
        ], $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('data.quantity', $validator->errors()->toArray());

        // Test valid data
        $validator = Validator::make([
            'data' => [
                'restaurant_menu_plan_entry_id' => $menuPlanEntry->id,
                'quantity' => 1, // Valid
            ],
        ], $request->rules());
        $this->assertFalse($validator->fails());
    }

    public function test_user_can_view_their_bookings(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);
        $menuPlanEntry = RestaurantMenuPlanEntry::factory()->create();
        $eatingTime = RestaurantEatingTime::factory()->create(['school_id' => $school->id]);

        // Create a booking
        $booking = RestaurantMenuPlanBooking::create([
            'school_id' => $school->id,
            'user_id' => $user->id,
            'restaurant_menu_plan_entry_id' => $menuPlanEntry->id,
            'restaurant_eating_time_id' => $eatingTime->id,
            'price' => 5.50,
            'quantity' => 1,
            'total_price' => 5.50,
            'child_name' => 'Test Child',
            'child_type' => 'child',
            'notes' => 'Test notes',
        ]);

        // Test service directly
        $bookingService = app(RestaurantBookingService::class);
        $bookings = $bookingService->getUserBookings($user);

        // Verify bookings were retrieved
        $this->assertCount(1, $bookings);
        $this->assertEquals($booking->id, $bookings->first()->id);
        $this->assertEquals($user->id, $bookings->first()->user_id);
        $this->assertEquals($menuPlanEntry->id, $bookings->first()->restaurant_menu_plan_entry_id);
    }

    public function test_user_can_delete_their_booking(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);
        $menuPlanEntry = RestaurantMenuPlanEntry::factory()->create();
        $eatingTime = RestaurantEatingTime::factory()->create(['school_id' => $school->id]);

        // Create a booking
        $booking = RestaurantMenuPlanBooking::create([
            'school_id' => $school->id,
            'user_id' => $user->id,
            'restaurant_menu_plan_entry_id' => $menuPlanEntry->id,
            'restaurant_eating_time_id' => $eatingTime->id,
            'price' => 5.50,
            'quantity' => 1,
            'total_price' => 5.50,
            'child_name' => 'Test Child',
            'child_type' => 'child',
            'notes' => 'Test notes',
        ]);

        // Test service directly
        $bookingService = app(RestaurantBookingService::class);
        $bookingService->cancelBooking($booking);

        // Verify booking was deleted
        $this->assertDatabaseMissing('restaurant_menu_plan_bookings', ['id' => $booking->id]);
    }

    public function test_booking_api_accepts_menu_plan_with_individual_order_schedule(): void
    {
        Carbon::setTestNow('2026-04-02 12:00:00');

        $school = School::factory()->create(['short_name' => 'CDG']);
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

        $this->withoutMiddleware(ToolLicensed::class);
        $this->actingAs($user);

        $response = $this->postJson('/api/homepage/restaurant/bookings', [
            'data' => [
                'restaurant_menu_plan_entry_id' => $entry->id,
                'restaurant_eating_time_id' => $eatingTime->id,
                'quantity' => 1,
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('booking.quantity', 1);

        $this->assertDatabaseHas('restaurant_menu_plan_bookings', [
            'user_id' => $user->id,
            'restaurant_menu_plan_entry_id' => $entry->id,
            'restaurant_eating_time_id' => $eatingTime->id,
            'quantity' => 1,
        ]);

        Carbon::setTestNow();
    }
}
