<?php

use App\Http\Middleware\ToolLicensed;
use App\Models\Import116;
use App\Models\RestaurantEatingTime;
use App\Models\RestaurantMenu;
use App\Models\RestaurantMenuPlan;
use App\Models\RestaurantMenuPlanBooking;
use App\Models\RestaurantMenuPlanEntry;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\LaravelPdf\Facades\Pdf;

uses(RefreshDatabase::class);

afterEach(function () {
    Carbon::setTestNow();
});

it('includes cancellation metadata for current and upcoming restaurant bookings', function () {
    Carbon::setTestNow('2026-04-03 12:00:00');

    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);
    $menu = RestaurantMenu::factory()->forSchool($school)->create();
    $eatingTime = RestaurantEatingTime::factory()->create(['school_id' => $school->id]);

    SchoolTool::factory()->create([
        'school_id' => $school->id,
        'restaurant_menu_order_start_mode' => 'when_available',
        'restaurant_menu_order_end_week_offset' => 0,
        'restaurant_menu_order_end_day_of_week' => 5,
        'restaurant_menu_order_end_time' => '17:00:00',
    ]);

    $cancellablePlan = RestaurantMenuPlan::factory()->create([
        'school_id' => $school->id,
        'is_available' => true,
        'use_individual_schedule_values' => true,
        'order_start_at' => '2026-04-03 08:00:00',
        'order_end_at' => '2026-04-03 17:00:00',
    ]);
    $cancellableEntry = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $cancellablePlan->id,
        'restaurant_menu_id' => $menu->id,
        'menu_title' => 'Pasta',
    ]);

    $futurePlan = RestaurantMenuPlan::factory()->create([
        'school_id' => $school->id,
        'is_available' => true,
        'use_individual_schedule_values' => true,
        'order_start_at' => '2026-04-04 08:00:00',
        'order_end_at' => '2026-04-04 17:00:00',
    ]);
    $futureEntry = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $futurePlan->id,
        'restaurant_menu_id' => $menu->id,
        'menu_title' => 'Reis',
        'plan_date' => '2026-04-04',
    ]);

    RestaurantMenuPlanBooking::query()->create([
        'school_id' => $school->id,
        'user_id' => $user->id,
        'restaurant_menu_plan_entry_id' => $cancellableEntry->id,
        'restaurant_eating_time_id' => $eatingTime->id,
        'price' => 6.50,
        'quantity' => 3,
        'metadata' => [
            'recipients' => [
                ['name' => 'Anna Beispiel', 'type' => 'child', 'import116_id' => 11],
                ['name' => 'Ben Beispiel', 'type' => 'child', 'import116_id' => 12],
                ['name' => 'Clara Beispiel', 'type' => 'child', 'import116_id' => 13],
            ],
        ],
        'booked_at' => now()->subHour(),
    ]);

    RestaurantMenuPlanBooking::query()->create([
        'school_id' => $school->id,
        'user_id' => $user->id,
        'restaurant_menu_plan_entry_id' => $futureEntry->id,
        'restaurant_eating_time_id' => $eatingTime->id,
        'price' => 5.50,
        'quantity' => 1,
        'booked_at' => now()->subHours(2),
    ]);

    $this->withoutMiddleware(ToolLicensed::class);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/homepage/restaurant/bookings')
        ->assertSuccessful()
        ->assertJsonFragment([
            'menu_plan_entry_id' => $cancellableEntry->id,
            'quantity' => 3,
            'can_cancel' => true,
        ])
        ->assertJsonPath('bookings.0.recipients.0.name', 'Anna Beispiel')
        ->assertJsonFragment([
            'menu_plan_entry_id' => $futureEntry->id,
            'quantity' => 1,
            'can_cancel' => false,
        ]);
});

it('allows the authenticated user to cancel a cancellable restaurant booking', function () {
    Carbon::setTestNow('2026-04-03 12:00:00');

    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);
    $menu = RestaurantMenu::factory()->forSchool($school)->create();
    $eatingTime = RestaurantEatingTime::factory()->create(['school_id' => $school->id]);

    SchoolTool::factory()->create([
        'school_id' => $school->id,
        'restaurant_menu_order_start_mode' => 'when_available',
        'restaurant_menu_order_end_week_offset' => 0,
        'restaurant_menu_order_end_day_of_week' => 5,
        'restaurant_menu_order_end_time' => '17:00:00',
    ]);

    $plan = RestaurantMenuPlan::factory()->create([
        'school_id' => $school->id,
        'is_available' => true,
        'use_individual_schedule_values' => true,
        'order_start_at' => '2026-04-03 08:00:00',
        'order_end_at' => '2026-04-03 17:00:00',
    ]);
    $entry = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $plan->id,
        'restaurant_menu_id' => $menu->id,
    ]);

    $booking = RestaurantMenuPlanBooking::query()->create([
        'school_id' => $school->id,
        'user_id' => $user->id,
        'restaurant_menu_plan_entry_id' => $entry->id,
        'restaurant_eating_time_id' => $eatingTime->id,
        'price' => 6.50,
        'quantity' => 2,
        'booked_at' => now()->subHour(),
    ]);

    $this->withoutMiddleware(ToolLicensed::class);

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/homepage/restaurant/bookings/{$booking->id}")
        ->assertSuccessful()
        ->assertJsonPath('message', 'Buchung erfolgreich storniert.');

    $this->assertDatabaseMissing('restaurant_menu_plan_bookings', [
        'id' => $booking->id,
    ]);
});

it('does not allow cancellation when the menu plan is not currently bookable yet', function () {
    Carbon::setTestNow('2026-04-03 12:00:00');

    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);
    $menu = RestaurantMenu::factory()->forSchool($school)->create();
    $eatingTime = RestaurantEatingTime::factory()->create(['school_id' => $school->id]);

    SchoolTool::factory()->create([
        'school_id' => $school->id,
        'restaurant_menu_order_start_mode' => 'when_available',
        'restaurant_menu_order_end_week_offset' => 0,
        'restaurant_menu_order_end_day_of_week' => 5,
        'restaurant_menu_order_end_time' => '17:00:00',
    ]);

    $futurePlan = RestaurantMenuPlan::factory()->create([
        'school_id' => $school->id,
        'is_available' => true,
        'use_individual_schedule_values' => true,
        'order_start_at' => '2026-04-04 08:00:00',
        'order_end_at' => '2026-04-04 17:00:00',
    ]);
    $futureEntry = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $futurePlan->id,
        'restaurant_menu_id' => $menu->id,
        'menu_title' => 'Gemüse Curry',
    ]);

    $futureBooking = RestaurantMenuPlanBooking::query()->create([
        'school_id' => $school->id,
        'user_id' => $user->id,
        'restaurant_menu_plan_entry_id' => $futureEntry->id,
        'restaurant_eating_time_id' => $eatingTime->id,
        'price' => 6.50,
        'quantity' => 1,
        'booked_at' => now()->subHour(),
    ]);

    $this->withoutMiddleware(ToolLicensed::class);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/homepage/restaurant/bookings')
        ->assertSuccessful()
        ->assertJsonFragment([
            'menu_plan_entry_id' => $futureEntry->id,
            'quantity' => 1,
            'can_cancel' => false,
        ]);

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/homepage/restaurant/bookings/{$futureBooking->id}")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Diese Buchung ist nicht mehr stornierbar.');

    $this->assertDatabaseHas('restaurant_menu_plan_bookings', [
        'id' => $futureBooking->id,
    ]);
});

it('does not include restaurant bookings whose menu date is already in the past', function () {
    Carbon::setTestNow('2026-04-03 12:00:00');

    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);
    $menu = RestaurantMenu::factory()->forSchool($school)->create();
    $eatingTime = RestaurantEatingTime::factory()->create(['school_id' => $school->id]);

    SchoolTool::factory()->create([
        'school_id' => $school->id,
        'restaurant_menu_order_start_mode' => 'when_available',
        'restaurant_menu_order_end_week_offset' => 0,
        'restaurant_menu_order_end_day_of_week' => 5,
        'restaurant_menu_order_end_time' => '17:00:00',
    ]);

    $pastPlan = RestaurantMenuPlan::factory()->create([
        'school_id' => $school->id,
        'is_available' => true,
        'use_individual_schedule_values' => true,
        'order_start_at' => '2026-04-01 08:00:00',
        'order_end_at' => '2026-04-01 17:00:00',
    ]);
    $pastEntry = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $pastPlan->id,
        'restaurant_menu_id' => $menu->id,
        'menu_title' => 'Vergangenes Menü',
        'plan_date' => '2026-04-01',
    ]);

    RestaurantMenuPlanBooking::query()->create([
        'school_id' => $school->id,
        'user_id' => $user->id,
        'restaurant_menu_plan_entry_id' => $pastEntry->id,
        'restaurant_eating_time_id' => $eatingTime->id,
        'price' => 5.50,
        'quantity' => 1,
        'booked_at' => now()->subDays(2),
    ]);

    $this->withoutMiddleware(ToolLicensed::class);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/homepage/restaurant/bookings')
        ->assertSuccessful()
        ->assertJsonCount(0, 'bookings');
});

it('returns a homepage restaurant overview pdf for the authenticated user', function () {
    Pdf::fake();
    Carbon::setTestNow('2026-04-03 12:00:00');

    $school = School::factory()->create(['short_name' => 'CDGym', 'long_name' => 'CDGym']);
    $user = User::factory()->create(['school_id' => $school->id]);
    $otherUser = User::factory()->create(['school_id' => $school->id]);
    $menu = RestaurantMenu::factory()->forSchool($school)->create();
    $eatingTime = RestaurantEatingTime::factory()->create(['school_id' => $school->id]);

    SchoolTool::factory()->create([
        'school_id' => $school->id,
        'restaurant_menu_order_start_mode' => 'when_available',
        'restaurant_menu_order_end_week_offset' => 0,
        'restaurant_menu_order_end_day_of_week' => 5,
        'restaurant_menu_order_end_time' => '17:00:00',
    ]);

    $plan = RestaurantMenuPlan::factory()->create([
        'school_id' => $school->id,
        'title' => 'Menüplan',
        'is_available' => true,
        'use_individual_schedule_values' => true,
        'order_start_at' => '2026-04-03 08:00:00',
        'order_end_at' => '2026-04-09 17:00:00',
        'start_date' => '2026-04-03',
        'end_date' => '2026-04-04',
    ]);

    $entry = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $plan->id,
        'restaurant_menu_id' => $menu->id,
        'menu_title' => 'Aloo Gobi',
        'plan_date' => '2026-04-03',
    ]);

    RestaurantMenuPlanBooking::query()->create([
        'school_id' => $school->id,
        'user_id' => $user->id,
        'restaurant_menu_plan_entry_id' => $entry->id,
        'restaurant_eating_time_id' => $eatingTime->id,
        'price' => 6.50,
        'quantity' => 2,
        'metadata' => [
            'recipients' => [
                ['name' => 'Elmina Beispiel', 'type' => 'child', 'import116_id' => 11],
                ['name' => 'Allen Beispiel', 'type' => 'child', 'import116_id' => 12],
            ],
        ],
        'booked_at' => now()->subHour(),
    ]);

    RestaurantMenuPlanBooking::query()->create([
        'school_id' => $school->id,
        'user_id' => $otherUser->id,
        'restaurant_menu_plan_entry_id' => $entry->id,
        'restaurant_eating_time_id' => $eatingTime->id,
        'price' => 5.50,
        'quantity' => 1,
        'metadata' => [
            'recipients' => [
                ['name' => 'Fremde Buchung', 'type' => 'child', 'import116_id' => 13],
            ],
        ],
        'booked_at' => now()->subMinutes(30),
    ]);

    $this->withoutMiddleware(ToolLicensed::class);

    $this->actingAs($user, 'sanctum')
        ->get('/api/homepage/restaurant/print?school=CDGym')
        ->assertSuccessful();

    Pdf::assertRespondedWithPdf(function ($pdf) use ($user) {
        return $pdf->viewName === 'pdfs.restaurantHomepageOverview'
            && $pdf->isDownload()
            && $pdf->viewData['user']['email'] === $user->email
            && count($pdf->viewData['bookings']) === 1
            && ! array_key_exists('plans', $pdf->viewData)
            && $pdf->contains(['Elmina Beispiel', 'Allen Beispiel'])
            && ! $pdf->contains('Fremde Buchung');
    });
});

it('stores booking recipients in metadata and remembers them as defaults for the user', function () {
    Carbon::setTestNow('2026-04-03 12:00:00');

    $school = School::factory()->create();
    $user = User::factory()->create([
        'school_id' => $school->id,
        'first_name' => 'Erika',
        'last_name' => 'Muster',
    ]);
    $menu = RestaurantMenu::factory()->forSchool($school)->create();
    $eatingTime = RestaurantEatingTime::factory()->create(['school_id' => $school->id]);

    SchoolTool::factory()->create([
        'school_id' => $school->id,
        'restaurant_menu_order_start_mode' => 'when_available',
        'restaurant_menu_order_end_week_offset' => 0,
        'restaurant_menu_order_end_day_of_week' => 5,
        'restaurant_menu_order_end_time' => '17:00:00',
    ]);

    $plan = RestaurantMenuPlan::factory()->create([
        'school_id' => $school->id,
        'is_available' => true,
        'use_individual_schedule_values' => true,
        'order_start_at' => '2026-04-03 08:00:00',
        'order_end_at' => '2026-04-03 17:00:00',
    ]);
    $entry = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $plan->id,
        'restaurant_menu_id' => $menu->id,
        'menu_title' => 'Lasagne',
    ]);
    $entry->eatingTimes()->attach($eatingTime->id);

    $this->withoutMiddleware(ToolLicensed::class);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/homepage/restaurant/bookings', [
            'data' => [
                'restaurant_menu_plan_entry_id' => $entry->id,
                'restaurant_eating_time_id' => $eatingTime->id,
                'quantity' => 2,
                'recipients' => [
                    ['name' => 'Erika Muster', 'type' => 'self'],
                    ['name' => 'Paul Beispiel', 'type' => 'other_person'],
                ],
                'single_recipient_customized' => false,
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('booking.recipients.1.name', 'Paul Beispiel');

    $booking = RestaurantMenuPlanBooking::query()->firstOrFail();

    expect($booking->metadata['recipients'][0]['name'] ?? null)->toBe('Erika Muster')
        ->and($booking->metadata['recipients'][1]['name'] ?? null)->toBe('Paul Beispiel')
        ->and($user->fresh()->restaurant_booking_defaults)->toMatchArray([
            'single_recipient_customized' => false,
        ])
        ->and($user->fresh()->restaurant_booking_defaults['recipients'][1]['name'] ?? null)->toBe('Paul Beispiel');
});

it('does not allow users to book a menu entry from another school', function () {
    Carbon::setTestNow('2026-04-03 12:00:00');

    $userSchool = School::factory()->create();
    $otherSchool = School::factory()->create();
    $user = User::factory()->create(['school_id' => $userSchool->id]);
    $otherMenu = RestaurantMenu::factory()->forSchool($otherSchool)->create();
    $otherPlan = RestaurantMenuPlan::factory()->create([
        'school_id' => $otherSchool->id,
        'is_available' => true,
        'use_individual_schedule_values' => true,
        'order_start_at' => now()->subHour(),
        'order_end_at' => now()->addHour(),
    ]);
    $otherEntry = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $otherPlan->id,
        'restaurant_menu_id' => $otherMenu->id,
    ]);

    $this->withoutMiddleware(ToolLicensed::class);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/homepage/restaurant/bookings', [
            'data' => [
                'restaurant_menu_plan_entry_id' => $otherEntry->id,
                'quantity' => 1,
            ],
        ])
        ->assertNotFound();

    expect(RestaurantMenuPlanBooking::query()->count())->toBe(0);
});

it('uses the server-side menu price instead of a client supplied price', function () {
    Carbon::setTestNow('2026-04-03 12:00:00');

    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);
    $menu = RestaurantMenu::factory()->forSchool($school)->create();
    $plan = RestaurantMenuPlan::factory()->create([
        'school_id' => $school->id,
        'is_available' => true,
        'use_individual_schedule_values' => true,
        'order_start_at' => now()->subHour(),
        'order_end_at' => now()->addHour(),
    ]);
    $entry = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $plan->id,
        'restaurant_menu_id' => $menu->id,
        'price' => 8.75,
    ]);

    $this->withoutMiddleware(ToolLicensed::class);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/homepage/restaurant/bookings', [
            'data' => [
                'restaurant_menu_plan_entry_id' => $entry->id,
                'quantity' => 1,
                'price' => 0.01,
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('booking.price', '8.75');

    $this->assertDatabaseHas('restaurant_menu_plan_bookings', [
        'restaurant_menu_plan_entry_id' => $entry->id,
        'price' => 8.75,
        'total_price' => 8.75,
    ]);
});

it('rejects an eating time that is not attached to the selected menu entry', function () {
    Carbon::setTestNow('2026-04-03 12:00:00');

    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);
    $menu = RestaurantMenu::factory()->forSchool($school)->create();
    $plan = RestaurantMenuPlan::factory()->create([
        'school_id' => $school->id,
        'is_available' => true,
        'use_individual_schedule_values' => true,
        'order_start_at' => now()->subHour(),
        'order_end_at' => now()->addHour(),
    ]);
    $entry = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $plan->id,
        'restaurant_menu_id' => $menu->id,
    ]);
    $allowedEatingTime = RestaurantEatingTime::factory()->create([
        'school_id' => $school->id,
        'eating_time' => '12:00:00',
    ]);
    $otherEatingTime = RestaurantEatingTime::factory()->create([
        'school_id' => $school->id,
        'eating_time' => '12:30:00',
    ]);
    $entry->eatingTimes()->attach($allowedEatingTime->id);

    $this->withoutMiddleware(ToolLicensed::class);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/homepage/restaurant/bookings', [
            'data' => [
                'restaurant_menu_plan_entry_id' => $entry->id,
                'restaurant_eating_time_id' => $otherEatingTime->id,
                'quantity' => 1,
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonFragment(['Die ausgewählte Speisezeit gehört nicht zu diesem Menü.']);

    expect(RestaurantMenuPlanBooking::query()->count())->toBe(0);
});

it('returns child options and remembered booking defaults for import116 parents', function () {
    $school = School::factory()->create();
    $user = User::factory()->create([
        'school_id' => $school->id,
        'email' => 'parent@example.test',
        'first_name' => 'Eva',
        'last_name' => 'Muster',
        'restaurant_booking_defaults' => [
            'recipients' => [
                ['name' => 'Anna Muster', 'type' => 'child', 'import116_id' => 0],
                ['name' => 'Oma Muster', 'type' => 'other_person', 'import116_id' => null],
            ],
            'single_recipient_customized' => true,
        ],
    ]);

    $firstChild = Import116::factory()->create([
        'school_id' => $school->id,
        'class' => '1A',
        'first_name' => 'Anna',
        'last_name' => 'Muster',
        'mother_email' => 'parent@example.test',
    ]);

    $secondChild = Import116::factory()->create([
        'school_id' => $school->id,
        'class' => '1A',
        'first_name' => 'Ben',
        'last_name' => 'Muster',
        'father_email' => 'parent@example.test',
    ]);

    $this->withoutMiddleware(ToolLicensed::class);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/homepage/restaurant/child-options')
        ->assertSuccessful()
        ->assertJsonPath('is_import116_parent', true)
        ->assertJsonPath('self_name', 'Eva Muster')
        ->assertJsonPath('options.0.id', $firstChild->id)
        ->assertJsonPath('options.1.id', $secondChild->id)
        ->assertJsonPath('booking_defaults.single_recipient_customized', true)
        ->assertJsonPath('booking_defaults.recipients.1.name', 'Oma Muster');
});
