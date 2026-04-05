<?php

use App\Models\RestaurantEatingTime;
use App\Models\RestaurantFood;
use App\Models\RestaurantFreeDay;
use App\Models\RestaurantMenu;
use App\Models\RestaurantMenuPlan;
use App\Models\RestaurantMenuPlanBooking;
use App\Models\RestaurantMenuPlanEntry;
use App\Models\School;
use App\Models\User;
use App\Services\RestaurantMenuPlanPdfService;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Barryvdh\DomPDF\PDF;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('builds a menu plan pdf with free days and entry details', function () {
    $school = School::factory()->create(['long_name' => 'Testschule']);
    $plan = RestaurantMenuPlan::factory()->create([
        'school_id' => $school->id,
        'title' => "Fr\u{fc}hlingswoche",
        'start_date' => '2026-03-23',
        'end_date' => '2026-03-25',
    ]);

    RestaurantFreeDay::factory()->forSchool($school)->create([
        'free_date' => '2026-03-24',
    ]);

    $food = RestaurantFood::factory()->forSchool($school)->create([
        'title' => 'Backerbsensuppe',
        'description' => "Klare Suppe mit Gem\u{fc}se und Backerbsen.",
    ]);
    $menu = RestaurantMenu::factory()->create([
        'school_id' => $school->id,
        'title' => 'Suppe Spezial',
        'price' => '8.90',
    ]);
    $menu->foods()->sync([$food->id => ['course_number' => 1]]);

    $entry = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $plan->id,
        'plan_date' => '2026-03-23',
        'restaurant_menu_id' => $menu->id,
        'menu_title' => "Montagsmen\u{fc}",
        'price' => '10.20',
        'comments' => 'Bitte ohne Sellerie.',
    ]);

    $eatingTime = RestaurantEatingTime::factory()->create([
        'school_id' => $school->id,
        'eating_time' => '11:30:00',
    ]);
    $entry->eatingTimes()->attach($eatingTime->id);

    $wrapper = Mockery::mock(PDF::class);
    $wrapper->shouldReceive('setPaper')
        ->once()
        ->with('a4', 'landscape')
        ->andReturnSelf();
    $wrapper->shouldReceive('save')
        ->once()
        ->withArgs(function (string $path): bool {
            expect($path)->toContain('app/private/pdf')
                ->and($path)->toContain('fruhlingswoche_')
                ->and($path)->toEndWith('.pdf');

            return true;
        });

    DomPdf::shouldReceive('loadView')
        ->once()
        ->withArgs(function (string $view, array $data): bool {
            expect($view)->toBe('pdfs.restaurantMenuPlan');
            expect($data['plan']['title'])->toBe("Fr\u{fc}hlingswoche");
            expect($data['plan']['school_name'])->toBe('Testschule');
            expect($data['days'])->toHaveCount(3);
            expect($data['days'][0]['entries'][0]['menu_title'])->toBe("Montagsmen\u{fc}");
            expect($data['days'][0]['entries'][0]['price'])->toBe("10,20 \u{20AC}");
            expect($data['days'][0]['entries'][0]['base_price'])->toBe("8,90 \u{20AC}");
            expect($data['days'][0]['entries'][0]['comments'])->toBe('Bitte ohne Sellerie.');
            expect($data['days'][0]['entries'][0]['eating_times'])->toBe(['11:30 Uhr']);
            expect($data['days'][0]['entries'][0]['foods'][0]['course_label'])->toBe('Gang 1');
            expect($data['days'][1]['is_free_day'])->toBeTrue();

            return true;
        })
        ->andReturn($wrapper);

    $path = app(RestaurantMenuPlanPdfService::class)->createPdf($plan->fresh());

    expect($path)->toContain('fruhlingswoche_')
        ->and($path)->toEndWith('.pdf');
});

it('builds a booking print pdf with separate pages per day and eating time', function () {
    $school = School::factory()->create(['long_name' => 'Testschule']);
    $plan = RestaurantMenuPlan::factory()->create([
        'school_id' => $school->id,
        'title' => "Fr\u{fc}hlingswoche",
        'start_date' => '2026-03-23',
        'end_date' => '2026-03-24',
    ]);

    $menuA = RestaurantMenu::factory()->create([
        'school_id' => $school->id,
        'title' => 'Pasta',
    ]);
    $menuB = RestaurantMenu::factory()->create([
        'school_id' => $school->id,
        'title' => 'Suppe',
    ]);

    $entryA = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $plan->id,
        'plan_date' => '2026-03-23',
        'restaurant_menu_id' => $menuA->id,
        'menu_title' => 'Pasta',
    ]);
    $entryB = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $plan->id,
        'plan_date' => '2026-03-23',
        'restaurant_menu_id' => $menuB->id,
        'menu_title' => 'Suppe',
    ]);

    $timeA = RestaurantEatingTime::factory()->create([
        'school_id' => $school->id,
        'eating_time' => '11:30:00',
    ]);
    $timeB = RestaurantEatingTime::factory()->create([
        'school_id' => $school->id,
        'eating_time' => '12:45:00',
    ]);
    $entryA->eatingTimes()->attach($timeA->id);
    $entryB->eatingTimes()->attach($timeB->id);

    $user = User::factory()->create([
        'school_id' => $school->id,
        'first_name' => 'Erika',
        'last_name' => 'Muster',
    ]);

    RestaurantMenuPlanBooking::query()->create([
        'school_id' => $school->id,
        'user_id' => $user->id,
        'restaurant_menu_plan_entry_id' => $entryA->id,
        'restaurant_eating_time_id' => $timeA->id,
        'price' => 8.50,
        'quantity' => 2,
        'booked_at' => now(),
        'metadata' => [
            'recipients' => [
                ['name' => 'Anna Beispiel', 'type' => 'child', 'import116_id' => null],
                ['name' => 'Ben Beispiel', 'type' => 'child', 'import116_id' => null],
            ],
        ],
    ]);

    RestaurantMenuPlanBooking::query()->create([
        'school_id' => $school->id,
        'user_id' => $user->id,
        'restaurant_menu_plan_entry_id' => $entryB->id,
        'restaurant_eating_time_id' => $timeB->id,
        'price' => 7.20,
        'quantity' => 1,
        'booked_at' => now(),
        'metadata' => [
            'recipients' => [
                ['name' => 'Clara Beispiel', 'type' => 'other_person', 'import116_id' => null],
            ],
        ],
    ]);

    $wrapper = Mockery::mock(PDF::class);
    $wrapper->shouldReceive('setPaper')
        ->once()
        ->with('a4', 'portrait')
        ->andReturnSelf();
    $wrapper->shouldReceive('save')
        ->once()
        ->withArgs(function (string $path): bool {
            expect($path)->toContain('app/private/pdf')
                ->and($path)->toContain('fruhlingswoche_bestellungen_')
                ->and($path)->toEndWith('.pdf');

            return true;
        });

    DomPdf::shouldReceive('loadView')
        ->once()
        ->withArgs(function (string $view, array $data): bool {
            expect($view)->toBe('pdfs.restaurantMenuPlanBookings');
            expect($data['plan']['title'])->toBe("Fr\u{fc}hlingswoche");
            expect($data['plan']['school_name'])->toBe('Testschule');
            expect($data['pages'])->toHaveCount(2);
            expect($data['pages'][0]['date_label'])->toBe('23.03.2026');
            expect($data['pages'][0]['time_label'])->toBe('11:30 Uhr');
            expect($data['pages'][0]['rows'])->toHaveCount(2);
            expect($data['pages'][0]['rows'][0]['customer_name'])->toBe('Beispiel - Anna');
            expect($data['pages'][0]['rows'][0]['menu_title'])->toBe('Pasta');
            expect($data['pages'][1]['time_label'])->toBe('12:45 Uhr');
            expect($data['pages'][1]['rows'][0]['customer_name'])->toBe('Beispiel - Clara');

            return true;
        })
        ->andReturn($wrapper);

    $path = app(RestaurantMenuPlanPdfService::class)->createBookingsPdf($plan->fresh());

    expect($path)->toContain('fruhlingswoche_bestellungen_')
        ->and($path)->toEndWith('.pdf');
});

it('builds an order summary pdf with all days and menu booking counts', function () {
    $school = School::factory()->create(['long_name' => 'Testschule']);
    $plan = RestaurantMenuPlan::factory()->create([
        'school_id' => $school->id,
        'title' => "Fr\u{fc}hlingswoche",
        'start_date' => '2026-03-23',
        'end_date' => '2026-03-25',
    ]);

    $menuA = RestaurantMenu::factory()->create([
        'school_id' => $school->id,
        'title' => 'Pasta',
    ]);
    $menuB = RestaurantMenu::factory()->create([
        'school_id' => $school->id,
        'title' => 'Suppe',
    ]);

    $entryA = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $plan->id,
        'plan_date' => '2026-03-23',
        'restaurant_menu_id' => $menuA->id,
        'menu_title' => 'Pasta',
    ]);
    $entryB = RestaurantMenuPlanEntry::factory()->create([
        'restaurant_menu_plan_id' => $plan->id,
        'plan_date' => '2026-03-24',
        'restaurant_menu_id' => $menuB->id,
        'menu_title' => 'Suppe',
    ]);

    $user = User::factory()->create([
        'school_id' => $school->id,
        'first_name' => 'Erika',
        'last_name' => 'Muster',
    ]);

    RestaurantMenuPlanBooking::query()->create([
        'school_id' => $school->id,
        'user_id' => $user->id,
        'restaurant_menu_plan_entry_id' => $entryA->id,
        'price' => 8.50,
        'quantity' => 3,
        'booked_at' => now(),
    ]);

    RestaurantMenuPlanBooking::query()->create([
        'school_id' => $school->id,
        'user_id' => $user->id,
        'restaurant_menu_plan_entry_id' => $entryB->id,
        'price' => 7.20,
        'quantity' => 1,
        'booked_at' => now(),
    ]);

    $wrapper = Mockery::mock(PDF::class);
    $wrapper->shouldReceive('setPaper')
        ->once()
        ->with('a4', 'portrait')
        ->andReturnSelf();
    $wrapper->shouldReceive('save')
        ->once()
        ->withArgs(function (string $path): bool {
            expect($path)->toContain('app/private/pdf')
                ->and($path)->toContain('fruhlingswoche_menusummen_')
                ->and($path)->toEndWith('.pdf');

            return true;
        });

    DomPdf::shouldReceive('loadView')
        ->once()
        ->withArgs(function (string $view, array $data): bool {
            expect($view)->toBe('pdfs.restaurantMenuPlanOrderSummary');
            expect($data['plan']['title'])->toBe("Fr\u{fc}hlingswoche");
            expect($data['plan']['school_name'])->toBe('Testschule');
            expect($data['totalOrders'])->toBe(4);
            expect($data['rows'])->toHaveCount(3);
            expect($data['rows'][0]['date_label'])->toBe('23.03.2026');
            expect($data['rows'][0]['menu_title'])->toBe('Pasta');
            expect($data['rows'][0]['orders_count'])->toBe(3);
            expect($data['rows'][1]['menu_title'])->toBe('Suppe');
            expect($data['rows'][1]['orders_count'])->toBe(1);
            expect($data['rows'][2]['date_label'])->toBe('25.03.2026');
            expect($data['rows'][2]['menu_title'])->toBe('Kein Menü eingetragen');
            expect($data['rows'][2]['orders_count'])->toBe(0);
            expect($data['rows'][2]['is_placeholder'])->toBeTrue();

            return true;
        })
        ->andReturn($wrapper);

    $path = app(RestaurantMenuPlanPdfService::class)->createOrderSummaryPdf($plan->fresh());

    expect($path)->toContain('fruhlingswoche_menusummen_')
        ->and($path)->toEndWith('.pdf');
});

it('renders the compact weekly layout for the menu plan pdf', function () {
    $html = view('pdfs.restaurantMenuPlan', [
        'plan' => [
            'title' => "Men\u{fc}plan",
            'range_label' => '23.03.2026 - 27.03.2026',
            'school_name' => 'Testschule',
            'generated_at' => '25.03.2026 09:15',
        ],
        'days' => collect(range(0, 6))->map(function (int $offset): array {
            return [
                'weekday_label' => 'Tag '.$offset,
                'date_label' => sprintf('%02d.03.2026', 23 + $offset),
                'is_free_day' => false,
                'entries' => [
                    [
                        'menu_title' => "Montagsmen\u{fc}",
                        'price' => "10,20 \u{20AC}",
                        'base_price' => "8,90 \u{20AC}",
                        'comments' => 'Kommentar',
                        'eating_times' => ['11:30 Uhr'],
                        'foods' => [
                            [
                                'course_label' => 'Gang 1',
                                'title' => 'Backerbsensuppe',
                                'category' => 'Suppe',
                                'description' => 'Klare Suppe',
                            ],
                        ],
                    ],
                ],
            ];
        })->all(),
    ])->render();

    expect($html)->toContain('margin: 6mm 6mm;')
        ->and($html)->toContain('size: A4 landscape;')
        ->and($html)->toContain('page-break-inside: avoid;')
        ->and($html)->toContain('border-spacing: 2px;')
        ->and($html)->toContain('class="week-table"')
        ->and(substr_count($html, 'class="week-day"'))->toBe(7)
        ->and($html)->toContain('entry-subline--comment')
        ->and($html)->toContain('Backerbsensuppe');
});

it('renders the bookings print layout with separate print pages', function () {
    $html = view('pdfs.restaurantMenuPlanBookings', [
        'plan' => [
            'title' => "Men\u{fc}plan",
            'range_label' => '23.03.2026 - 24.03.2026',
            'school_name' => 'Testschule',
            'generated_at' => '25.03.2026 09:15',
        ],
        'pages' => [
            [
                'weekday_label' => 'Montag',
                'date_label' => '23.03.2026',
                'time_label' => '11:30 Uhr',
                'rows' => [
                    [
                        'customer_name' => 'Beispiel - Anna',
                        'menu_title' => "Montagsmen\u{fc}",
                    ],
                ],
            ],
        ],
    ])->render();

    expect($html)->toContain('page-break-after: always;')
        ->and($html)->toContain('Restaurant Bestellungen')
        ->and($html)->toContain('font-size: 32px;')
        ->and($html)->toContain('Tag:</span> Montag, 23.03.2026')
        ->and($html)->toContain('width: 10mm;')
        ->and($html)->toContain('booking-table__spacer-head')
        ->and($html)->toContain('booking-table__spacer-cell')
        ->and($html)->toContain('Beispiel - Anna')
        ->and($html)->toContain('Montagsmen')
        ->and($html)->not->toContain('Keine Bestellungen');
});

it('renders the order summary print layout as a compact single table', function () {
    $html = view('pdfs.restaurantMenuPlanOrderSummary', [
        'plan' => [
            'title' => 'Menüsummen',
            'range_label' => '23.03.2026 - 25.03.2026',
            'school_name' => 'Testschule',
            'generated_at' => '25.03.2026 09:15',
        ],
        'totalOrders' => 4,
        'rows' => [
            [
                'weekday_label' => 'Montag',
                'date_label' => '23.03.2026',
                'menu_title' => 'Pasta',
                'orders_count' => 3,
                'is_placeholder' => false,
            ],
            [
                'weekday_label' => 'Dienstag',
                'date_label' => '24.03.2026',
                'menu_title' => 'Suppe',
                'orders_count' => 1,
                'is_placeholder' => false,
            ],
            [
                'weekday_label' => 'Mittwoch',
                'date_label' => '25.03.2026',
                'menu_title' => 'Kein Menü eingetragen',
                'orders_count' => 0,
                'is_placeholder' => true,
            ],
        ],
    ])->render();

    expect($html)->toContain('size: A4 portrait;')
        ->and($html)->toContain('Restaurant Menüsummen')
        ->and($html)->toContain('Gesamtbestellungen: 4')
        ->and($html)->toContain('Summe aller Bestellungen')
        ->and($html)->toContain('text-align: right;')
        ->and($html)->toContain('class="summary-table"')
        ->and($html)->toContain('Pasta')
        ->and($html)->toContain('Kein Men')
        ->and($html)->toContain('summary-table__menu is-placeholder');
});
