<?php

use App\Models\Licence;
use App\Models\RestaurantSepaMandate;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use App\Notifications\StandardEmail;
use App\Services\RestaurantSepaMandatePdfService;
use App\Services\RestaurantSepaMandateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->withoutMiddleware();

    $this->school = School::factory()->create([
        'short_name' => 'REST',
        'is_selectable' => true,
    ]);
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'is_active' => true,
    ]);

    $restaurantLicence = Licence::create(['name' => 'Restaurant']);
    $this->school->licences()->attach($restaurantLicence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);

    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'restaurant_visible_user' => true,
        'restaurant_visible_admin' => true,
        'restaurant_sepa_online_enabled' => true,
        'active_schoolyear_id' => $this->schoolyear->id,
    ]);

    Role::firstOrCreate(['name' => 'lunch_candidate', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'lunch_user', 'guard_name' => 'web']);
});

test('resends the restaurant sepa confirmation code for an active pending flow', function () {
    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'sepa-login@test.local',
    ]);
    $user->assignRole('lunch_user');

    $mandate = RestaurantSepaMandate::query()->create([
        'user_id' => $user->id,
        'school_id' => $this->school->id,
        'flow_uuid' => (string) Str::uuid(),
        'status' => 'draft',
        'entry_point' => 'login',
        'child_entries' => [
            [
                'name' => 'Kind Eins',
                'schoolclass' => '2A',
            ],
        ],
    ]);
    app(RestaurantSepaMandateService::class)->bootstrapFlow($user);

    $submitResponse = $this->postJson('/api/homepage/restaurant/sepa/store', [
        'data' => [
            'flow_uuid' => $mandate->flow_uuid,
            'account_holder_name' => 'Max Muster',
            'address_line' => 'Musterstraße 1',
            'postal_code' => '5020',
            'city' => 'Salzburg',
            'country' => 'Österreich',
            'iban' => 'AT611904300234573201',
            'bic' => null,
            'child_entries' => [
                [
                    'name' => 'Kind Eins',
                    'schoolclass' => '2A',
                ],
            ],
            'accepted' => true,
        ],
    ]);

    $submitResponse
        ->assertOk()
        ->assertJsonPath('status', 'CODE_SENT')
        ->assertJsonPath('flow.iban', 'AT61************3201');

    $mandate->refresh();
    $firstConfirmationCodeHash = (string) $mandate->confirmation_code;

    expect($firstConfirmationCodeHash)->not->toMatch('/^\d{6}$/');

    $storedMandate = DB::table('restaurant_sepa_mandates')->where('id', $mandate->id)->first();

    expect($storedMandate->iban)->not->toBe('AT611904300234573201')
        ->and($storedMandate->account_holder_name)->not->toBe('Max Muster')
        ->and($storedMandate->address_line)->not->toBe('Musterstraße 1');

    $this->postJson('/api/homepage/restaurant/sepa/resend_code', [
        'data' => [
            'flow_uuid' => $mandate->flow_uuid,
        ],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'CODE_SENT')
        ->assertJsonPath('flow.flow_uuid', $mandate->flow_uuid);

    $mandate->refresh();

    expect($mandate->confirmation_code)->not->toBe($firstConfirmationCodeHash)
        ->and($mandate->status)->toBe('pending_code')
        ->and($mandate->code_sent_at)->not->toBeNull()
        ->and($mandate->postal_code)->toBe('5020')
        ->and($mandate->city)->toBe('Salzburg')
        ->and($mandate->country)->toBe('Österreich');

    Notification::assertSentOnDemand(StandardEmail::class, 2);
});

test('emails the confirmed sepa mandate pdf to the customer and restaurant service email', function () {
    $this->school->schoolTool->update([
        'restaurant_service_email' => 'service@test.local',
    ]);

    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'customer@test.local',
    ]);
    $user->assignRole('lunch_user');

    $mandate = RestaurantSepaMandate::query()->create([
        'user_id' => $user->id,
        'school_id' => $this->school->id,
        'flow_uuid' => (string) Str::uuid(),
        'status' => 'draft',
        'entry_point' => 'login',
        'child_entries' => [
            [
                'name' => 'Kind Eins',
                'schoolclass' => '2A',
            ],
        ],
    ]);
    app(RestaurantSepaMandateService::class)->bootstrapFlow($user);

    $pdfService = Mockery::mock(RestaurantSepaMandatePdfService::class);
    $pdfService->shouldReceive('createPdfContent')
        ->once()
        ->withArgs(function (RestaurantSepaMandate $actual) use ($mandate): bool {
            return (int) $actual->id === (int) $mandate->id
                && $actual->flow_uuid === $mandate->flow_uuid
                && (string) $actual->confirmed_at !== '';
        })
        ->andReturn('pdf-test');

    $this->app->instance(RestaurantSepaMandatePdfService::class, $pdfService);

    $this->postJson('/api/homepage/restaurant/sepa/store', [
        'data' => [
            'flow_uuid' => $mandate->flow_uuid,
            'account_holder_name' => 'Max Muster',
            'address_line' => 'Musterstraße 1',
            'postal_code' => '5020',
            'city' => 'Salzburg',
            'country' => 'Österreich',
            'iban' => 'AT611904300234573201',
            'bic' => null,
            'child_entries' => [
                [
                    'name' => 'Kind Eins',
                    'schoolclass' => '2A',
                ],
            ],
            'accepted' => true,
        ],
    ])->assertOk();

    $confirmationCode = null;
    Notification::assertSentOnDemand(StandardEmail::class, function (StandardEmail $notification) use (&$confirmationCode): bool {
        $confirmationCode = $notification->data['token_2fa'] ?? null;

        return is_string($confirmationCode);
    });

    Notification::fake();

    expect($confirmationCode)->toMatch('/^\d{6}$/');

    $this->postJson('/api/homepage/restaurant/sepa/confirm_code', [
        'data' => [
            'flow_uuid' => $mandate->flow_uuid,
            'code' => $confirmationCode,
        ],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'CONFIRMED');

    Notification::assertSentOnDemand(StandardEmail::class, 2);
    Notification::assertSentOnDemand(StandardEmail::class, function (StandardEmail $notification, array $channels, object $notifiable): bool {
        return ($notifiable->routes['mail'] ?? null) === 'customer@test.local'
            && ($notification->data['subject'] ?? null) === 'SEPA-Lastschriftmandat als PDF'
            && ($notification->data['markdown'] ?? null) === 'spa::mails.homepage.sendSepaMandate'
            && ($notification->attachments['data'] ?? null) === 'pdf-test'
            && ($notification->attachments['options']['mime'] ?? null) === 'application/pdf';
    });
    Notification::assertSentOnDemand(StandardEmail::class, function (StandardEmail $notification, array $channels, object $notifiable): bool {
        return ($notifiable->routes['mail'] ?? null) === 'service@test.local'
            && ($notification->data['subject'] ?? null) === 'SEPA-Lastschriftmandat als PDF'
            && ($notification->data['markdown'] ?? null) === 'spa::mails.homepage.sendSepaMandate'
            && ($notification->attachments['data'] ?? null) === 'pdf-test'
            && ($notification->attachments['options']['mime'] ?? null) === 'application/pdf';
    });

    $this->postJson('/api/homepage/restaurant/sepa/complete', [
        'data' => [
            'flow_uuid' => $mandate->flow_uuid,
        ],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'COMPLETED');

    $this->postJson('/api/homepage/restaurant/sepa/complete', [
        'data' => [
            'flow_uuid' => $mandate->flow_uuid,
        ],
    ])->assertUnprocessable();
});

test('rejects a sepa flow UUID that is not bound to the current session', function () {
    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $mandate = RestaurantSepaMandate::query()->create([
        'user_id' => $user->id,
        'school_id' => $this->school->id,
        'flow_uuid' => (string) Str::uuid(),
        'status' => 'pending_code',
        'entry_point' => 'login',
    ]);

    $this->postJson('/api/homepage/restaurant/sepa/resend_code', [
        'data' => [
            'flow_uuid' => $mandate->flow_uuid,
        ],
    ])->assertUnauthorized();
});
