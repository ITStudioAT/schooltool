<?php

use App\Models\Import116;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect(['admin', 'lunch_admin', 'lunch_user'])->each(function (string $role): void {
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
        'email_verified_at' => now(),
    ]);
    $matchingUser->assignRole('lunch_user');

    $secondUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
        'first_name' => 'Berta',
        'last_name' => 'Buffet',
        'email' => 'berta@example.test',
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
        ->assertJsonPath('meta.total', 1)
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
