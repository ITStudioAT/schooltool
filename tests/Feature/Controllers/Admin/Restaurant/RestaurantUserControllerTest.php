<?php

use App\Models\Import116;
use App\Models\RestaurantSepaMandate;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\StandardEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();

    collect(['admin', 'lunch_admin', 'lunch_candidate', 'lunch_user'])->each(function (string $role): void {
        Role::firstOrCreate([
            'name' => $role,
            'guard_name' => 'web',
        ]);
    });

    $this->school = School::factory()->create();
    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
    ]);
    $this->admin->assignRole('admin');
});

test('restaurant users endpoint returns paginated lunch users for the current school', function () {
    $importRow = Import116::factory()
        ->forSchool($this->school)
        ->importedBy($this->admin)
        ->create();

    Import116::factory()
        ->forSchool($this->school)
        ->importedBy($this->admin)
        ->create([
            'first_name' => 'Lena',
            'last_name' => 'Mittag',
            'email' => 'lena.schueler@example.test',
            'mother_email' => 'anna@example.test',
        ]);

    Import116::factory()
        ->forSchool($this->school)
        ->importedBy($this->admin)
        ->create([
            'first_name' => 'Paul',
            'last_name' => 'Mittag',
            'email' => 'paul.schueler@example.test',
            'father_email' => 'anna@example.test',
        ]);

    $matchingUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
        'first_name' => 'Anna',
        'last_name' => 'Mittag',
        'email' => 'anna@example.test',
        'schoolclass' => '3A',
        'import116_id' => $importRow->id,
        'sepa_at' => now(),
        'confirmed_at' => now(),
        'restaurant_confirmed_at' => now(),
        'email_verified_at' => now(),
    ]);
    $matchingUser->assignRole('lunch_user');

    $secondUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
        'first_name' => 'Berta',
        'last_name' => 'Buffet',
        'email' => 'berta@example.test',
        'restaurant_confirmed_at' => now(),
    ]);
    $secondUser->assignRole('lunch_user');
    $secondUser->assignRole('lunch_admin');

    $otherSchool = School::factory()->create();
    $otherSchoolUser = User::factory()->create([
        'school_id' => $otherSchool->id,
        'schoolyear_id' => null,
        'first_name' => 'Clara',
        'last_name' => 'Extern',
        'email' => 'clara@example.test',
    ]);
    $otherSchoolUser->assignRole('lunch_user');

    $teacherOnly = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
        'first_name' => 'David',
        'last_name' => 'OhneLunch',
        'email' => 'david@example.test',
    ]);
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    $teacherOnly->assignRole('teacher');

    $this->actingAs($this->admin, 'sanctum');

    $response = $this->getJson('/api/admin/restaurant/users?search_string=Mitt');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', 'anna@example.test')
        ->assertJsonPath('data.0.schoolclass', '3A')
        ->assertJsonPath('data.0.import116_id', $importRow->id)
        ->assertJsonPath('data.0.has_sepa', true)
        ->assertJsonPath('data.0.is_verified', true)
        ->assertJsonPath('data.0.is_confirmed', true)
        ->assertJsonPath('data.0.is_restaurant_confirmed', true)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('meta.pending_confirmation_total', 0)
        ->assertJsonPath('meta.only_pending_confirmation', false)
        ->assertJsonPath('meta.current_page', 1);

    expect(collect($response->json('data.0.import116_children')))
        ->toHaveCount(2)
        ->toContain([
            'name' => 'Lena Mittag',
            'email' => 'lena.schueler@example.test',
        ])
        ->toContain([
            'name' => 'Paul Mittag',
            'email' => 'paul.schueler@example.test',
        ]);
});

test('restaurant users endpoint exposes origin labels for teacher list, import116, parents, and extern users', function () {
    $studentImport = Import116::factory()
        ->forSchool($this->school)
        ->importedBy($this->admin)
        ->create([
            'first_name' => 'Ida',
            'last_name' => 'Import',
            'email' => 'ida.import@example.test',
        ]);

    Import116::factory()
        ->forSchool($this->school)
        ->importedBy($this->admin)
        ->create([
            'first_name' => 'Kind',
            'last_name' => 'Eltern',
            'email' => 'kind.eltern@example.test',
            'mother_email' => 'mama@example.test',
        ]);

    Teacher::query()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Theo',
        'last_name' => 'Lehrer',
        'short' => 'TLH',
        'email' => 'theo.lehrer@example.test',
    ]);

    $teacherUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
        'first_name' => 'Theo',
        'last_name' => 'Lehrer',
        'email' => 'theo.lehrer@example.test',
    ]);
    $teacherUser->assignRole('lunch_user');

    $studentUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
        'first_name' => 'Ida',
        'last_name' => 'Import',
        'email' => 'ida.import@example.test',
        'import116_id' => $studentImport->id,
    ]);
    $studentUser->assignRole('lunch_user');

    $parentUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
        'first_name' => 'Marta',
        'last_name' => 'Mutter',
        'email' => 'mama@example.test',
    ]);
    $parentUser->assignRole('lunch_user');

    $externalUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
        'first_name' => 'Erik',
        'last_name' => 'Extern',
        'email' => 'extern@example.test',
    ]);
    $externalUser->assignRole('lunch_user');

    $this->actingAs($this->admin, 'sanctum');

    $response = $this->getJson('/api/admin/restaurant/users');

    $response->assertOk();

    $usersByEmail = collect($response->json('data'))->keyBy('email');

    expect($usersByEmail['theo.lehrer@example.test']['origin_labels'])->toBe(['Lehrerliste'])
        ->and($usersByEmail['ida.import@example.test']['origin_labels'])->toBe(['Import116'])
        ->and($usersByEmail['mama@example.test']['origin_labels'])->toBe(['Eltern'])
        ->and($usersByEmail['extern@example.test']['origin_labels'])->toBe(['Extern']);
});

test('restaurant users endpoint can filter only users that must be confirmed', function () {
    $confirmedUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
        'first_name' => 'Klara',
        'last_name' => 'Klar',
        'email' => 'klar@example.test',
        'confirmed_at' => now(),
        'restaurant_confirmed_at' => now(),
    ]);
    $confirmedUser->assignRole('lunch_user');

    $pendingUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
        'first_name' => 'Otto',
        'last_name' => 'Offen',
        'email' => 'offen@example.test',
        'confirmed_at' => null,
        'restaurant_confirmed_at' => null,
    ]);
    $pendingUser->assignRole('lunch_candidate');

    $this->actingAs($this->admin, 'sanctum');

    $response = $this->getJson('/api/admin/restaurant/users?only_pending_confirmation=1');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('meta.pending_confirmation_total', 1)
        ->assertJsonPath('meta.only_pending_confirmation', true)
        ->assertJsonPath('data.0.email', 'offen@example.test');
});

test('restaurant users endpoint can filter only users without sepa', function () {
    $withSepaUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
        'first_name' => 'Sina',
        'last_name' => 'Sepa',
        'email' => 'sina@example.test',
        'sepa_at' => now(),
    ]);
    $withSepaUser->assignRole('lunch_user');

    $withoutSepaUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
        'first_name' => 'Noah',
        'last_name' => 'Ohne',
        'email' => 'noah@example.test',
        'sepa_at' => null,
    ]);
    $withoutSepaUser->assignRole('lunch_user');

    $this->actingAs($this->admin, 'sanctum');

    $response = $this->getJson('/api/admin/restaurant/users?only_without_sepa=1');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('meta.only_without_sepa', true)
        ->assertJsonPath('data.0.email', 'noah@example.test');
});

test('restaurant users endpoint includes lunch candidates in the default listing', function () {
    $candidateUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
        'first_name' => 'Kira',
        'last_name' => 'Kandidat',
        'email' => 'candidate@example.test',
        'confirmed_at' => null,
        'restaurant_confirmed_at' => null,
    ]);
    $candidateUser->assignRole('lunch_candidate');

    $confirmedUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
        'first_name' => 'Lina',
        'last_name' => 'Lunch',
        'email' => 'lunch@example.test',
        'confirmed_at' => now(),
        'restaurant_confirmed_at' => now(),
    ]);
    $confirmedUser->assignRole('lunch_user');

    $this->actingAs($this->admin, 'sanctum');

    $response = $this->getJson('/api/admin/restaurant/users');

    $response->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.pending_confirmation_total', 1);

    expect(collect($response->json('data'))->pluck('email')->all())
        ->toContain('candidate@example.test', 'lunch@example.test');
});

test('restaurant users endpoint accepts false string for the pending confirmation filter', function () {
    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
        'email' => 'test@example.test',
    ]);
    $user->assignRole('lunch_user');

    $this->actingAs($this->admin, 'sanctum');

    $this->getJson('/api/admin/restaurant/users?only_pending_confirmation=false')
        ->assertOk()
        ->assertJsonPath('meta.only_pending_confirmation', false)
        ->assertJsonPath('meta.total', 1);
});

test('restaurant users endpoint also allows lunch admins', function () {
    $lunchAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
    ]);
    $lunchAdmin->assignRole('lunch_admin');

    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
    ]);
    $user->assignRole('lunch_user');

    $this->actingAs($lunchAdmin, 'sanctum');

    $this->getJson('/api/admin/restaurant/users')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});

test('restaurant user sepa can be confirmed for a lunch user in the same school', function () {
    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
        'sepa_at' => null,
    ]);
    $user->assignRole('lunch_user');

    $this->actingAs($this->admin, 'sanctum');

    $this->putJson("/api/admin/restaurant/users/{$user->id}/sepa", [
        'data' => [
            'has_sepa' => true,
        ],
    ])->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.has_sepa', true);

    expect($user->fresh()->sepa_at)->not->toBeNull();
});

test('restaurant user sepa can be refused for a lunch user in the same school', function () {
    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
        'sepa_at' => now()->subDay(),
    ]);
    $user->assignRole('lunch_user');

    $this->actingAs($this->admin, 'sanctum');

    $this->putJson("/api/admin/restaurant/users/{$user->id}/sepa", [
        'data' => [
            'has_sepa' => false,
        ],
    ])->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.has_sepa', false);

    expect($user->fresh()->sepa_at)->toBeNull();
});

test('restaurant user sepa removal deletes stored mandates for the same school', function () {
    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
        'sepa_at' => now()->subDay(),
    ]);
    $user->assignRole('lunch_user');

    RestaurantSepaMandate::query()->create([
        'user_id' => $user->id,
        'school_id' => $this->school->id,
        'flow_uuid' => (string) Str::uuid(),
        'status' => 'completed',
        'entry_point' => 'login',
        'account_holder_name' => 'Anna Mittag',
        'address_line' => 'Musterweg 1, 5020 Salzburg',
        'iban' => 'AT611904300234573201',
        'bic' => 'BKAUATWW',
        'child_entries' => [],
        'sepa_payee_snapshot' => '<p>Zahlungsempfänger</p>',
        'sepa_mandate_text_snapshot' => '<p>Mandatstext</p>',
        'accepted_at' => now()->subDay(),
        'confirmed_at' => now()->subDay(),
        'completed_at' => now(),
    ]);

    $this->actingAs($this->admin, 'sanctum');

    $this->putJson("/api/admin/restaurant/users/{$user->id}/sepa", [
        'data' => [
            'has_sepa' => false,
        ],
    ])->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.has_sepa', false);

    expect($user->fresh()->sepa_at)->toBeNull()
        ->and(RestaurantSepaMandate::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

test('restaurant lunch candidate can be confirmed for the same school after email verification', function () {
    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
        'email_verified_at' => now(),
        'confirmed_at' => null,
        'restaurant_confirmed_at' => null,
    ]);
    $user->assignRole('lunch_candidate');

    $this->actingAs($this->admin, 'sanctum');

    $this->putJson("/api/admin/restaurant/users/{$user->id}/confirm")
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.is_confirmed', true)
        ->assertJsonPath('data.is_restaurant_confirmed', true);

    expect($user->fresh()->hasRole('lunch_user'))->toBeTrue()
        ->and($user->fresh()->hasRole('lunch_candidate'))->toBeFalse()
        ->and($user->fresh()->confirmed_at)->not->toBeNull()
        ->and($user->fresh()->restaurant_confirmed_at)->not->toBeNull();

    Notification::assertSentOnDemand(StandardEmail::class, function (StandardEmail $notification, array $channels, object $notifiable) use ($user): bool {
        return ($notifiable->routes['mail'] ?? null) === (string) $user->email
            && ($notification->data['markdown'] ?? null) === 'mails.homepage.restaurantRegistrationConfirmed'
            && ($notification->data['subject'] ?? null) === 'Restaurantanmeldung bestätigt'
            && str_contains((string) ($notification->data['restaurant_url'] ?? ''), '/homepage/restaurant?school=');
    });
});

test('restaurant lunch candidate confirmation requires a verified email', function () {
    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
        'email_verified_at' => null,
        'confirmed_at' => null,
        'restaurant_confirmed_at' => null,
    ]);
    $user->assignRole('lunch_candidate');

    $this->actingAs($this->admin, 'sanctum');

    $this->putJson("/api/admin/restaurant/users/{$user->id}/confirm")
        ->assertStatus(422);

    expect($user->fresh()->hasRole('lunch_candidate'))->toBeTrue()
        ->and($user->fresh()->hasRole('lunch_user'))->toBeFalse()
        ->and($user->fresh()->confirmed_at)->toBeNull()
        ->and($user->fresh()->restaurant_confirmed_at)->toBeNull();
});

test('restaurant lunch candidate can be deleted for the same school', function () {
    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
        'email' => 'candidate@example.test',
    ]);
    $user->assignRole('lunch_candidate');

    $this->actingAs($this->admin, 'sanctum');

    $this->deleteJson("/api/admin/restaurant/users/{$user->id}")
        ->assertNoContent();

    expect(User::find($user->id))->toBeNull();
});

test('restaurant lunch candidate deletion keeps the user when additional roles exist', function () {
    Role::firstOrCreate([
        'name' => 'teacher',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
        'email' => 'teacher.candidate@example.test',
    ]);
    $user->assignRole('lunch_candidate');
    $user->assignRole('teacher');

    $this->actingAs($this->admin, 'sanctum');

    $this->deleteJson("/api/admin/restaurant/users/{$user->id}")
        ->assertNoContent();

    expect($user->fresh())->not->toBeNull()
        ->and($user->fresh()->hasRole('lunch_candidate'))->toBeFalse()
        ->and($user->fresh()->hasRole('teacher'))->toBeTrue();
});
