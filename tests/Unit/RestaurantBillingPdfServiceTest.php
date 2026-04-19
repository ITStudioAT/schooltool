<?php

use App\Models\RestaurantBilling;
use App\Models\School;
use App\Services\RestaurantBillingPdfService;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Barryvdh\DomPDF\PDF;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('builds a restaurant billing pdf from the stored snapshot', function () {
    $school = School::factory()->create(['long_name' => 'Testschule']);
    $billing = RestaurantBilling::factory()->create([
        'school_id' => $school->id,
        'start_date' => '2026-03-23',
        'end_date' => '2026-04-05',
        'weeks_count' => 2,
        'bookings_count' => 6,
        'total_amount' => '32.10',
        'snapshot' => [
            'rows' => [
                [
                    'user_name' => 'Buffet Berta',
                    'price_lines' => [
                        [
                            'quantity' => 3,
                            'price_label' => '5,20 €',
                            'line_total_label' => '15,60 €',
                        ],
                    ],
                    'total_quantity' => 3,
                    'total_amount_label' => '15,60 €',
                ],
            ],
            'overall_total_amount' => '32.10',
            'overall_total_amount_label' => '32,10 €',
            'overall_total_quantity' => 6,
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
                ->and($path)->toContain('abrechnung_')
                ->and($path)->toEndWith('.pdf');

            return true;
        });

    DomPdf::shouldReceive('loadView')
        ->once()
        ->withArgs(function (string $view, array $data): bool {
            expect($view)->toBe('pdfs.restaurantBilling');
            expect($data['billing']['school_name'])->toBe('Testschule');
            expect($data['billing']['bookings_count'])->toBe(6);
            expect($data['rows'])->toHaveCount(1);
            expect($data['rows'][0]['user_name'])->toBe('Buffet Berta');
            expect($data['overallTotalLabel'])->toBe('32,10 €');
            expect($data['overallQuantity'])->toBe(6);

            return true;
        })
        ->andReturn($wrapper);

    $path = app(RestaurantBillingPdfService::class)->createPdf($billing->fresh());

    expect($path)->toContain('abrechnung_')
        ->and($path)->toEndWith('.pdf');
});

it('renders the restaurant billing print layout with grouped user totals', function () {
    $html = view('pdfs.restaurantBilling', [
        'billing' => [
            'title' => 'Abrechnung',
            'period_label' => 'KW 13-14/2026',
            'range_label' => '23.03.2026 - 05.04.2026',
            'school_name' => 'Testschule',
            'created_at' => '05.04.2026 12:15',
            'bookings_count' => 6,
        ],
        'rows' => [
            [
                'user_name' => 'Buffet Berta',
                'price_lines' => [
                    [
                        'quantity' => 3,
                        'price_label' => '5,20 €',
                        'line_total_label' => '15,60 €',
                    ],
                ],
                'total_quantity' => 3,
                'total_amount_label' => '15,60 €',
            ],
            [
                'user_name' => 'Muster Erika',
                'price_lines' => [
                    [
                        'quantity' => 2,
                        'price_label' => '5,20 €',
                        'line_total_label' => '10,40 €',
                    ],
                    [
                        'quantity' => 1,
                        'price_label' => '6,10 €',
                        'line_total_label' => '6,10 €',
                    ],
                ],
                'total_quantity' => 3,
                'total_amount_label' => '16,50 €',
            ],
        ],
        'overallTotalLabel' => '32,10 €',
        'overallQuantity' => 6,
    ])->render();

    expect($html)->toContain('Restaurant Abrechnung')
        ->and($html)->toContain('KW 13-14/2026')
        ->and($html)->toContain('3 x 5,20 € = 15,60 €')
        ->and(substr_count($html, 'nicht endgültig'))->toBe(3)
        ->and($html)->toContain('Gesamtsumme aller Kunden')
        ->and($html)->toContain('32,10 €');
});
