<?php

use App\Models\RestaurantBilling;
use App\Models\RestaurantEatingTime;
use App\Models\RestaurantMenu;
use App\Models\RestaurantMenuPlan;
use App\Models\RestaurantMenuPlanBooking;
use App\Models\RestaurantMenuPlanEntry;
use App\Models\School;
use App\Models\User;
use App\Services\RestaurantBillingPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect(['admin', 'lunch_admin', 'teacher'])->each(function (string $role): void {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    });

    $this->school = School::factory()->create();
    enableSchoolToolModuleForTests($this->school, 'restaurant');
    grantSchoolToolLicenceForTests($this->school, 'Restaurant');
    $this->otherSchool = School::factory()->create();

    $this->admin = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => null]);
    $this->admin->assignRole('admin');

    $this->teacher = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => null]);
    $this->teacher->assignRole('teacher');

    $this->menu = RestaurantMenu::factory()->create(['school_id' => $this->school->id, 'title' => 'Pasta']);
    $this->eatingTime = RestaurantEatingTime::factory()->create(['school_id' => $this->school->id, 'eating_time' => '11:30:00']);
});

test('restaurant billings index returns recent billings and week availability for current school', function () {
    $firstPlan = RestaurantMenuPlan::factory()->create([
        'school_id' => $this->school->id,
        'start_date' => '2026-03-23',
        'end_date' => '2026-03-27',
    ]);
    $secondPlan = RestaurantMenuPlan::factory()->create([
        'school_id' => $this->school->id,
        'start_date' => '2026-03-30',
        'end_date' => '2026-04-03',
    ]);

    $firstEntry = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $firstPlan->id,
        'plan_date' => '2026-03-24',
        'restaurant_menu_id' => $this->menu->id,
        'price' => '5.20',
    ]);
    $secondEntry = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $secondPlan->id,
        'plan_date' => '2026-03-31',
        'restaurant_menu_id' => $this->menu->id,
        'price' => '5.20',
    ]);

    RestaurantMenuPlanBooking::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->admin->id,
        'restaurant_menu_plan_entry_id' => $firstEntry->id,
        'restaurant_eating_time_id' => $this->eatingTime->id,
        'price' => 5.20,
        'quantity' => 2,
        'booked_at' => now(),
    ]);
    RestaurantMenuPlanBooking::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->admin->id,
        'restaurant_menu_plan_entry_id' => $secondEntry->id,
        'restaurant_eating_time_id' => $this->eatingTime->id,
        'price' => 5.20,
        'quantity' => 1,
        'booked_at' => now(),
    ]);

    RestaurantBilling::factory()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $this->admin->id,
        'start_date' => '2026-03-23',
        'end_date' => '2026-03-29',
        'weeks_count' => 1,
        'bookings_count' => 2,
        'total_amount' => '10.40',
        'snapshot' => [
            'rows' => [],
            'overall_total_amount' => '10.40',
            'overall_total_amount_label' => '10,40 €',
            'overall_total_quantity' => 2,
        ],
    ]);

    RestaurantBilling::factory()->create([
        'school_id' => $this->otherSchool->id,
        'start_date' => '2026-03-23',
        'end_date' => '2026-03-29',
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/admin/restaurant/billings')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.period_label', 'KW 13/2026')
        ->assertJsonCount(2, 'weeks')
        ->assertJsonPath('weeks.0.week_start', '2026-03-23')
        ->assertJsonPath('weeks.0.is_billed', true)
        ->assertJsonPath('weeks.1.week_start', '2026-03-30')
        ->assertJsonPath('weeks.1.is_billed', false);
});

test('restaurant billing store creates a billing for connected weeks with grouped totals', function () {
    $secondUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
        'first_name' => 'Berta',
        'last_name' => 'Buffet',
    ]);

    $firstPlan = RestaurantMenuPlan::factory()->create([
        'school_id' => $this->school->id,
        'start_date' => '2026-03-23',
        'end_date' => '2026-03-27',
    ]);
    $secondPlan = RestaurantMenuPlan::factory()->create([
        'school_id' => $this->school->id,
        'start_date' => '2026-03-30',
        'end_date' => '2026-04-03',
    ]);

    $weekOneEntry = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $firstPlan->id,
        'plan_date' => '2026-03-24',
        'restaurant_menu_id' => $this->menu->id,
        'price' => '5.20',
    ]);
    $weekTwoEntry = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $secondPlan->id,
        'plan_date' => '2026-03-31',
        'restaurant_menu_id' => $this->menu->id,
        'price' => '6.10',
    ]);

    RestaurantMenuPlanBooking::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->admin->id,
        'restaurant_menu_plan_entry_id' => $weekOneEntry->id,
        'restaurant_eating_time_id' => $this->eatingTime->id,
        'price' => 5.20,
        'quantity' => 2,
        'booked_at' => now(),
    ]);
    RestaurantMenuPlanBooking::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->admin->id,
        'restaurant_menu_plan_entry_id' => $weekTwoEntry->id,
        'restaurant_eating_time_id' => $this->eatingTime->id,
        'price' => 6.10,
        'quantity' => 1,
        'booked_at' => now(),
    ]);
    RestaurantMenuPlanBooking::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $secondUser->id,
        'restaurant_menu_plan_entry_id' => $weekOneEntry->id,
        'restaurant_eating_time_id' => $this->eatingTime->id,
        'price' => 5.20,
        'quantity' => 3,
        'booked_at' => now(),
    ]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/admin/restaurant/billings', [
            'weeks' => ['2026-03-23', '2026-03-30'],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.start_date', '2026-03-23')
        ->assertJsonPath('data.end_date', '2026-04-05')
        ->assertJsonPath('data.weeks_count', 2)
        ->assertJsonPath('data.bookings_count', 6)
        ->assertJsonPath('data.total_amount', '32.10');

    $billing = RestaurantBilling::query()->firstOrFail();

    expect($billing->weeks_count)->toBe(2)
        ->and($billing->bookings_count)->toBe(6)
        ->and((string) $billing->total_amount)->toBe('32.10')
        ->and($billing->snapshot['overall_total_amount'])->toBe('32.10')
        ->and($billing->snapshot['rows'])->toHaveCount(2)
        ->and(
            collect($billing->snapshot['rows'])
                ->map(fn (array $row): int => count($row['price_lines']))
                ->sort()
                ->values()
                ->all()
        )->toBe([1, 2]);
});

test('restaurant billing creation rejects overlapping billed periods', function () {
    RestaurantBilling::factory()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $this->admin->id,
        'start_date' => '2026-03-23',
        'end_date' => '2026-04-05',
        'weeks_count' => 2,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/admin/restaurant/billings', [
            'weeks' => ['2026-03-30'],
        ])
        ->assertConflict();
});

test('restaurant billing print downloads a pdf for the current school', function () {
    $billing = RestaurantBilling::factory()->create([
        'school_id' => $this->school->id,
        'created_by_user_id' => $this->admin->id,
        'start_date' => '2026-03-23',
        'end_date' => '2026-03-29',
    ]);

    $tempPath = storage_path('framework/testing/restaurant-billing-test.pdf');

    File::ensureDirectoryExists(dirname($tempPath));
    File::put($tempPath, 'pdf-test');

    $mock = Mockery::mock(RestaurantBillingPdfService::class);
    $mock->shouldReceive('createPdf')
        ->once()
        ->andReturn($tempPath);

    $this->app->instance(RestaurantBillingPdfService::class, $mock);

    $this->actingAs($this->admin, 'sanctum')
        ->get("/api/admin/restaurant/billings/{$billing->id}/print")
        ->assertSuccessful()
        ->assertDownload('restaurant-billing-test.pdf')
        ->assertHeader('content-type', 'application/pdf');
});

test('restaurant billing preview renders a pdf without creating a billing', function () {
    $plan = RestaurantMenuPlan::factory()->create([
        'school_id' => $this->school->id,
        'start_date' => '2026-03-23',
        'end_date' => '2026-03-27',
    ]);

    $entry = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $plan->id,
        'plan_date' => '2026-03-24',
        'restaurant_menu_id' => $this->menu->id,
        'price' => '5.20',
    ]);

    RestaurantMenuPlanBooking::query()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->admin->id,
        'restaurant_menu_plan_entry_id' => $entry->id,
        'restaurant_eating_time_id' => $this->eatingTime->id,
        'price' => 5.20,
        'quantity' => 2,
        'booked_at' => now(),
    ]);

    $tempPath = storage_path('framework/testing/restaurant-billing-preview-test.pdf');

    File::ensureDirectoryExists(dirname($tempPath));
    File::put($tempPath, 'pdf-test');

    $mock = Mockery::mock(RestaurantBillingPdfService::class);
    $mock->shouldReceive('createPdf')
        ->once()
        ->andReturn($tempPath);

    $this->app->instance(RestaurantBillingPdfService::class, $mock);

    $this->actingAs($this->admin, 'sanctum')
        ->get('/api/admin/restaurant/billings/preview?weeks%5B0%5D=2026-03-23')
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'inline; filename="restaurant-billing-preview-test.pdf"');

    expect(RestaurantBilling::query()->count())->toBe(0);
});
