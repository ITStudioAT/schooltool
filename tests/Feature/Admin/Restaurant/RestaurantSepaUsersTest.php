<?php

use App\Models\RestaurantSepaMandate;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    collect(['admin', 'lunch_admin', 'lunch_user', 'lunch_candidate', 'teacher'])->each(function (string $role): void {
        Role::firstOrCreate([
            'name' => $role,
            'guard_name' => 'web',
        ]);
    });

    $this->school = School::factory()->create();
    enableSchoolToolModuleForTests($this->school, 'restaurant');
    grantSchoolToolLicenceForTests($this->school, 'Restaurant');
    $this->otherSchool = School::factory()->create();

    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
    ]);
    $this->admin->assignRole('admin');
});

test('restaurant sepa users endpoint returns completed login and register sepa users for the current school', function (): void {
    $loginUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
        'first_name' => 'Anna',
        'last_name' => 'Login',
        'email' => 'anna.login@example.test',
        'schoolclass' => '2A',
        'sepa_at' => now()->subDays(3),
    ]);
    $loginUser->assignRole('lunch_user');

    $registerUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
        'first_name' => 'Berta',
        'last_name' => 'Register',
        'email' => 'berta.register@example.test',
        'schoolclass' => '4B',
        'sepa_at' => now()->subDays(2),
    ]);
    $registerUser->assignRole('lunch_user');

    $otherSchoolUser = User::factory()->create([
        'school_id' => $this->otherSchool->id,
        'schoolyear_id' => null,
        'first_name' => 'Clara',
        'last_name' => 'Extern',
        'email' => 'clara.extern@example.test',
        'sepa_at' => now()->subDay(),
    ]);
    $otherSchoolUser->assignRole('lunch_user');

    $draftUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
        'first_name' => 'David',
        'last_name' => 'Draft',
        'email' => 'david.draft@example.test',
        'sepa_at' => now()->subDay(),
    ]);
    $draftUser->assignRole('lunch_user');

    $candidateUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
        'first_name' => 'Eva',
        'last_name' => 'Candidate',
        'email' => 'eva.candidate@example.test',
        'sepa_at' => now()->subDay(),
    ]);
    $candidateUser->assignRole('lunch_candidate');

    RestaurantSepaMandate::query()->create([
        'user_id' => $loginUser->id,
        'school_id' => $this->school->id,
        'flow_uuid' => 'flow-login-uuid-001',
        'status' => 'completed',
        'entry_point' => 'login',
        'completed_at' => now()->subDays(3)->setTime(8, 15),
    ]);
    RestaurantSepaMandate::query()->create([
        'user_id' => $registerUser->id,
        'school_id' => $this->school->id,
        'flow_uuid' => 'flow-register-uuid-002',
        'status' => 'completed',
        'entry_point' => 'register',
        'completed_at' => now()->subDays(2)->setTime(9, 45),
    ]);
    RestaurantSepaMandate::query()->create([
        'user_id' => $draftUser->id,
        'school_id' => $this->school->id,
        'flow_uuid' => 'flow-draft-uuid-003',
        'status' => 'pending_code',
        'entry_point' => 'login',
        'completed_at' => null,
    ]);
    RestaurantSepaMandate::query()->create([
        'user_id' => $candidateUser->id,
        'school_id' => $this->school->id,
        'flow_uuid' => 'flow-candidate-uuid-004',
        'status' => 'completed',
        'entry_point' => 'login',
        'completed_at' => now()->subDay()->setTime(10, 0),
    ]);
    RestaurantSepaMandate::query()->create([
        'user_id' => $otherSchoolUser->id,
        'school_id' => $this->otherSchool->id,
        'flow_uuid' => 'flow-other-school-uuid-005',
        'status' => 'completed',
        'entry_point' => 'login',
        'completed_at' => now()->subDay()->setTime(10, 30),
    ]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/admin/restaurant/sepa-users');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2);

    expect(collect($response->json('data'))->keyBy('email'))
        ->toHaveCount(2)
        ->and($response->json('data.0.email'))->toBe('anna.login@example.test')
        ->and($response->json('data.0.entry_point_label'))->toBe('Login')
        ->and($response->json('data.0.flow_uuid'))->toBe('flow-login-uuid-001')
        ->and($response->json('data.1.email'))->toBe('berta.register@example.test')
        ->and($response->json('data.1.entry_point_label'))->toBe('Registrierung')
        ->and($response->json('data.1.flow_uuid'))->toBe('flow-register-uuid-002');
});
