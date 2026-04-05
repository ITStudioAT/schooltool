<?php

use App\Models\Licence;
use App\Models\RestaurantSepaMandate;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use App\Notifications\StandardEmail;
use App\Services\RestaurantSepaMandatePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
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
        ->assertJsonPath('status', 'CODE_SENT');

    $mandate->refresh();
    $firstConfirmationCode = (string) $mandate->confirmation_code;

    $this->postJson('/api/homepage/restaurant/sepa/resend_code', [
        'data' => [
            'flow_uuid' => $mandate->flow_uuid,
        ],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'CODE_SENT')
        ->assertJsonPath('flow.flow_uuid', $mandate->flow_uuid);

    $mandate->refresh();

    expect($mandate->confirmation_code)->not->toBe($firstConfirmationCode)
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

    $tempPath = storage_path('framework/testing/sepa-mandate-mail.pdf');
    File::ensureDirectoryExists(dirname($tempPath));
    File::put($tempPath, 'pdf-test');

    $pdfService = Mockery::mock(RestaurantSepaMandatePdfService::class);
    $pdfService->shouldReceive('createPdf')
        ->once()
        ->withArgs(function (RestaurantSepaMandate $actual) use ($mandate): bool {
            return (int) $actual->id === (int) $mandate->id
                && $actual->flow_uuid === $mandate->flow_uuid
                && (string) $actual->confirmed_at !== '';
        })
        ->andReturn($tempPath);

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

    $confirmationCode = (string) $mandate->fresh()->confirmation_code;

    Notification::fake();

    $this->postJson('/api/homepage/restaurant/sepa/confirm_code', [
        'data' => [
            'flow_uuid' => $mandate->flow_uuid,
            'code' => $confirmationCode,
        ],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'CONFIRMED');

    Notification::assertSentOnDemand(StandardEmail::class, 2);
    Notification::assertSentOnDemand(StandardEmail::class, function (StandardEmail $notification, array $channels, object $notifiable) use ($tempPath): bool {
        return ($notifiable->routes['mail'] ?? null) === 'customer@test.local'
            && ($notification->data['subject'] ?? null) === 'SEPA-Lastschriftmandat als PDF'
            && ($notification->data['markdown'] ?? null) === 'spa::mails.homepage.sendSepaMandate'
            && $notification->attachments === $tempPath;
    });
    Notification::assertSentOnDemand(StandardEmail::class, function (StandardEmail $notification, array $channels, object $notifiable) use ($tempPath): bool {
        return ($notifiable->routes['mail'] ?? null) === 'service@test.local'
            && ($notification->data['subject'] ?? null) === 'SEPA-Lastschriftmandat als PDF'
            && ($notification->data['markdown'] ?? null) === 'spa::mails.homepage.sendSepaMandate'
            && $notification->attachments === $tempPath;
    });

    File::delete($tempPath);
});
