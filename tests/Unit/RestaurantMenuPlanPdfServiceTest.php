<?php

use App\Models\RestaurantEatingTime;
use App\Models\RestaurantFood;
use App\Models\RestaurantFreeDay;
use App\Models\RestaurantMenu;
use App\Models\RestaurantMenuPlan;
use App\Models\RestaurantMenuPlanEntry;
use App\Models\School;
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
        ->with('a4', 'portrait')
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

it('renders the compact weekly layout for the menu plan pdf', function () {
    $html = view('pdfs.restaurantMenuPlan', [
        'plan' => [
            'title' => "Men\u{fc}plan",
            'range_label' => '23.03.2026 - 27.03.2026',
            'school_name' => 'Testschule',
            'generated_at' => '25.03.2026 09:15',
        ],
        'days' => [
            [
                'weekday_label' => 'Montag',
                'date_label' => '23.03.2026',
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
            ],
        ],
    ])->render();

    expect($html)->toContain('margin: 6mm 6mm;')
        ->and($html)->toContain('page-break-inside: avoid;')
        ->and($html)->toContain('border-spacing: 2px;')
        ->and($html)->toContain('class="week-table"')
        ->and($html)->toContain('entry-subline--comment')
        ->and($html)->toContain('Backerbsensuppe');
});
