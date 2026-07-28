<?php

use App\Models\Licence;
use App\Models\RestaurantSepaMandate;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\User;
use App\Services\RestaurantSepaMandatePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    collect(['admin', 'lunch_admin', 'lunch_user'])->each(function (string $role): void {
        Role::firstOrCreate([
            'name' => $role,
            'guard_name' => 'web',
        ]);
    });

    $this->school = School::factory()->create();
    $licence = Licence::query()->create([
        'name' => 'Restaurant',
        'long_name' => 'Restaurant',
    ]);
    SchoolLicence::query()->create([
        'school_id' => $this->school->id,
        'licence_id' => $licence->id,
        'valid_until' => now()->addYear(),
    ]);
    SchoolTool::query()->create([
        'school_id' => $this->school->id,
        'restaurant_visible_admin' => true,
    ]);

    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
    ]);
    $this->admin->assignRole('admin');
});

test('restaurant sepa mandate print downloads the stored pdf for the current school', function (): void {
    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
        'first_name' => 'Anna',
        'last_name' => 'Mittag',
        'email' => 'anna.mittag@example.test',
        'sepa_at' => now(),
    ]);
    $user->assignRole('lunch_user');

    $mandate = RestaurantSepaMandate::query()->create([
        'user_id' => $user->id,
        'school_id' => $this->school->id,
        'flow_uuid' => 'flow-register-uuid-002',
        'status' => 'completed',
        'entry_point' => 'register',
        'account_holder_name' => 'Anna Mittag',
        'address_line' => 'Musterweg 1, 5020 Salzburg',
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
        'confirmed_at' => now()->subDay(),
        'completed_at' => now(),
    ]);

    $tempPath = storage_path('framework/testing/sepa-mandate-test.pdf');
    File::ensureDirectoryExists(dirname($tempPath));
    File::put($tempPath, 'pdf-test');

    $service = Mockery::mock(RestaurantSepaMandatePdfService::class);
    $service->shouldReceive('createPdf')
        ->once()
        ->withArgs(function (RestaurantSepaMandate $actual) use ($mandate): bool {
            return (int) $actual->id === (int) $mandate->id
                && $actual->flow_uuid === $mandate->flow_uuid;
        })
        ->andReturn($tempPath);

    $this->app->instance(RestaurantSepaMandatePdfService::class, $service);

    $this->actingAs($this->admin, 'sanctum')
        ->get("/api/admin/restaurant/sepa-users/{$mandate->flow_uuid}/print")
        ->assertSuccessful()
        ->assertDownload('sepa-mandate-test.pdf')
        ->assertHeader('content-type', 'application/pdf');
});

test('restaurant sepa users endpoint paginates completed mandates', function (): void {
    collect(range(1, 31))->each(function (int $index): void {
        $suffix = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => null,
            'first_name' => "Vorname {$suffix}",
            'last_name' => "Nachname {$suffix}",
            'email' => "sepa{$suffix}@example.test",
            'sepa_at' => now(),
        ]);
        $user->assignRole('lunch_user');

        RestaurantSepaMandate::query()->create([
            'user_id' => $user->id,
            'school_id' => $this->school->id,
            'flow_uuid' => "flow-uuid-{$suffix}",
            'status' => 'completed',
            'entry_point' => $index % 2 === 0 ? 'login' : 'register',
            'account_holder_name' => "Vorname {$suffix} Nachname {$suffix}",
            'address_line' => 'Musterweg 1, 5020 Salzburg',
            'iban' => 'AT611904300234573201',
            'bic' => 'BKAUATWW',
            'child_entries' => [],
            'sepa_payee_snapshot' => '<p>Zahlungsempfänger</p>',
            'sepa_mandate_text_snapshot' => '<p>Mandatstext</p>',
            'accepted_at' => now()->subMinutes($index + 2),
            'confirmed_at' => now()->subMinutes($index + 1),
            'completed_at' => now()->subMinutes($index),
        ]);
    });

    $this->actingAs($this->admin, 'sanctum')
        ->get('/api/admin/restaurant/sepa-users')
        ->assertSuccessful()
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.per_page', config('schooltool.pagination'))
        ->assertJsonPath('meta.total', 31)
        ->assertJsonPath('meta.from', 1)
        ->assertJsonPath('meta.to', 30)
        ->assertJsonCount(30, 'data')
        ->assertJsonPath('data.0.flow_uuid', 'flow-uuid-01');

    $this->actingAs($this->admin, 'sanctum')
        ->get('/api/admin/restaurant/sepa-users?page=2')
        ->assertSuccessful()
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.per_page', config('schooltool.pagination'))
        ->assertJsonPath('meta.total', 31)
        ->assertJsonPath('meta.from', 31)
        ->assertJsonPath('meta.to', 31)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.flow_uuid', 'flow-uuid-31');
});
