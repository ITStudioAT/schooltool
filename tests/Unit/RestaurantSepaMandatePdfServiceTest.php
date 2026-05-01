<?php

use App\Models\RestaurantSepaMandate;
use App\Models\School;
use App\Models\User;
use App\Services\RestaurantSepaMandatePdfService;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Barryvdh\DomPDF\PDF;
use Dompdf\Dompdf as BaseDompdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('builds a sepa mandate pdf from the stored mandate snapshot', function (): void {
    $school = School::factory()->create(['long_name' => 'Testschule']);
    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => null,
        'first_name' => 'Anna',
        'last_name' => 'Mittag',
        'email' => 'anna.mittag@example.test',
    ]);

    $mandate = RestaurantSepaMandate::query()->create([
        'user_id' => $user->id,
        'school_id' => $school->id,
        'flow_uuid' => 'flow-register-uuid-002',
        'status' => 'completed',
        'entry_point' => 'register',
        'account_holder_name' => 'Anna Mittag',
        'address_line' => 'Musterweg 1',
        'postal_code' => '5020',
        'city' => 'Salzburg',
        'country' => 'Österreich',
        'iban' => 'AT611904300234573201',
        'bic' => 'BKAUATWW',
        'child_entries' => [
            [
                'name' => 'Lena Mittag',
                'schoolclass' => '3A',
            ],
        ],
        'sepa_payee_snapshot' => '<p>Zahlungsempfänger</p>',
        'sepa_mandate_text_snapshot' => '<p>Mandatstext</p>',
        'accepted_at' => now()->subDay(),
        'confirmed_at' => now()->setDate(2026, 4, 5)->setTime(9, 15),
        'completed_at' => now(),
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
                ->and($path)->toContain('sepa_lastschriftmandat_')
                ->and($path)->toEndWith('.pdf');

            return true;
        });

    DomPdf::shouldReceive('loadView')
        ->once()
        ->withArgs(function (string $view, array $data): bool {
            expect($view)->toBe('pdfs.restaurantSepaMandate');
            expect($data['mandate']['title'])->toBe('SEPA-Lastschriftmandat');
            expect($data['mandate']['school_name'])->toBe('Testschule');
            expect($data['mandate']['email'])->toBe('anna.mittag@example.test');
            expect($data['mandate']['account_holder_name'])->toBe('Anna Mittag');
            expect($data['mandate']['address_line'])->toBe('Musterweg 1');
            expect($data['mandate']['postal_code'])->toBe('5020');
            expect($data['mandate']['city'])->toBe('Salzburg');
            expect($data['mandate']['country'])->toBe('Österreich');
            expect($data['mandate']['signature_location'])->toBe('Salzburg');
            expect($data['mandate']['iban'])->toBe('AT611904300234573201');
            expect($data['mandate']['bic'])->toBe('BKAUATWW');
            expect($data['mandate']['child_entries'])->toHaveCount(1);
            expect($data['mandate']['child_entries'][0]['name'])->toBe('Lena Mittag');
            expect($data['mandate']['sepa_payee'])->toBe('<p>Zahlungsempfänger</p>');
            expect($data['mandate']['sepa_mandate_text'])->toBe('<p>Mandatstext</p>');
            expect($data['mandate']['confirmed_at_label'])->toBe('05.04.2026');
            expect($data['mandate']['signature_uuid'])->toBe('flow-register-uuid-002');

            return true;
        })
        ->andReturn($wrapper);

    $path = app(RestaurantSepaMandatePdfService::class)->createPdf($mandate->fresh());

    expect($path)->toContain('sepa_lastschriftmandat_')
        ->and($path)->toEndWith('.pdf');
});

it('builds a sepa mandate preview pdf from current settings', function (): void {
    $school = School::factory()->create(['long_name' => 'Vorschule']);

    $wrapper = Mockery::mock(PDF::class);
    $wrapper->shouldReceive('setPaper')
        ->once()
        ->with('a4', 'portrait')
        ->andReturnSelf();
    $wrapper->shouldReceive('save')
        ->once()
        ->withArgs(function (string $path): bool {
            expect($path)->toContain('app/private/pdf')
                ->and($path)->toEndWith('sepa_lastschriftmandat_vorschau.pdf');

            return true;
        });

    DomPdf::shouldReceive('loadView')
        ->once()
        ->withArgs(function (string $view, array $data): bool {
            expect($view)->toBe('pdfs.restaurantSepaMandate');
            expect($data['mandate']['school_name'])->toBe('Vorschule');
            expect($data['mandate']['account_holder_name'])->toBe('Max Mustermann');
            expect($data['mandate']['child_entries'][0]['name'])->toBe('Maria Mustermann');
            expect($data['mandate']['sepa_payee'])->toBe('<p>Zahlungsempfänger Vorschau</p>');
            expect($data['mandate']['sepa_mandate_text'])->toBe('<p>Mandat Vorschau</p>');
            expect($data['mandate']['signature_uuid'])->toBe('VORSCHAU');

            return true;
        })
        ->andReturn($wrapper);

    $path = app(RestaurantSepaMandatePdfService::class)->createPreviewPdf($school, [
        'sepa_payee' => '<p>Zahlungsempfänger Vorschau</p>',
        'sepa_mandate_text' => '<p>Mandat Vorschau</p>',
    ]);

    expect($path)->toEndWith('sepa_lastschriftmandat_vorschau.pdf');
});

it('renders the sepa mandate preview on a single pdf page', function (): void {
    $html = view('pdfs.restaurantSepaMandate', [
        'mandate' => [
            'title' => 'SEPA-Lastschriftmandat',
            'school_name' => 'Christian-Doppler-Gymnasium Salzburg',
            'email' => 'vorschau@example.test',
            'account_holder_name' => 'Max Mustermann',
            'address_line' => 'Musterstraße 1',
            'postal_code' => '5020',
            'city' => 'Salzburg',
            'country' => 'Österreich',
            'signature_location' => 'Salzburg',
            'iban' => 'AT611904300234573201',
            'bic' => 'BKAUATWW',
            'child_entries' => [
                ['name' => 'Maria Mustermann', 'schoolclass' => '1A'],
            ],
            'sepa_payee' => '<p>Christian-Doppler-Gymnasium Salzburg</p><p>Franz-Josef-Kai 41, 5020 Salzburg</p>',
            'sepa_mandate_text' => '<p>Ich ermächtige den Zahlungsempfänger, Zahlungen von meinem Konto mittels SEPA-Lastschrift einzuziehen.</p><p>Zugleich weise ich mein Kreditinstitut an, die vom Zahlungsempfänger auf mein Konto gezogenen Lastschriften einzulösen.</p><p>Ich kann innerhalb von acht Wochen, beginnend mit dem Belastungsdatum, die Erstattung des belasteten Betrages verlangen.</p>',
            'confirmed_at_label' => '01.05.2026',
            'signature_uuid' => 'VORSCHAU',
            'flow_uuid' => 'VORSCHAU',
        ],
    ])->render();

    $dompdf = new BaseDompdf;
    $dompdf->loadHtml($html);
    $dompdf->setPaper('a4', 'portrait');
    $dompdf->render();

    expect($dompdf->getCanvas()->get_page_count())->toBe(1);
});
