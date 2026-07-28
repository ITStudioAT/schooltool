<?php

use App\Models\RestaurantFreeDay;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect(['super_admin', 'admin', 'lunch_admin', 'teacher'])->each(function (string $role): void {
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

    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => null,
    ]);
    $this->teacher->assignRole('teacher');
});

test('returns 401 when restaurant free days index is unauthenticated', function () {
    $this->getJson('/api/admin/restaurant/free-days')->assertStatus(401);
});

test('returns 403 when role has no restaurant free day access', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $this->getJson('/api/admin/restaurant/free-days')->assertForbidden();
});

test('index returns only free days from current school and selected year', function () {
    RestaurantFreeDay::factory()->forSchool($this->school)->create(['free_date' => '2026-01-06']);
    RestaurantFreeDay::factory()->forSchool($this->school)->create(['free_date' => '2026-12-24']);
    RestaurantFreeDay::factory()->forSchool($this->school)->create(['free_date' => '2027-01-02']);
    RestaurantFreeDay::factory()->forSchool($this->otherSchool)->create(['free_date' => '2026-02-10']);

    $this->actingAs($this->admin, 'sanctum');

    $response = $this->getJson('/api/admin/restaurant/free-days?year=2026');

    $response->assertOk()
        ->assertJsonPath('meta.year', 2026)
        ->assertJsonPath('meta.count', 2);

    expect(collect($response->json('data'))->pluck('free_date')->all())
        ->toEqual(['2026-01-06', '2026-12-24']);
});

test('store set mode creates an inclusive range and ignores duplicates', function () {
    RestaurantFreeDay::factory()->forSchool($this->school)->create(['free_date' => '2026-05-02']);

    $this->actingAs($this->admin, 'sanctum');

    $response = $this->postJson('/api/admin/restaurant/free-days', [
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-03',
        'mode' => 'set',
    ]);

    $response->assertCreated()
        ->assertJsonPath('meta.mode', 'set')
        ->assertJsonPath('meta.affected_count', 2)
        ->assertJsonPath('meta.years.0', 2026);

    expect(RestaurantFreeDay::query()
        ->where('school_id', $this->school->id)
        ->orderBy('free_date')
        ->pluck('free_date')
        ->map(fn ($date): string => $date->format('Y-m-d'))
        ->all())->toEqual([
            '2026-05-01',
            '2026-05-02',
            '2026-05-03',
        ]);
});

test('store unset mode removes only free days from the current school', function () {
    RestaurantFreeDay::factory()->forSchool($this->school)->create(['free_date' => '2026-07-07']);
    RestaurantFreeDay::factory()->forSchool($this->school)->create(['free_date' => '2026-07-08']);
    RestaurantFreeDay::factory()->forSchool($this->school)->create(['free_date' => '2026-07-10']);
    RestaurantFreeDay::factory()->forSchool($this->otherSchool)->create(['free_date' => '2026-07-08']);

    $this->actingAs($this->admin, 'sanctum');

    $this->postJson('/api/admin/restaurant/free-days', [
        'start_date' => '2026-07-07',
        'end_date' => '2026-07-08',
        'mode' => 'unset',
    ])->assertOk()
        ->assertJsonPath('meta.mode', 'unset')
        ->assertJsonPath('meta.affected_count', 2);

    $this->assertDatabaseMissing('restaurant_free_days', [
        'school_id' => $this->school->id,
        'free_date' => '2026-07-07',
    ]);
    $this->assertDatabaseMissing('restaurant_free_days', [
        'school_id' => $this->school->id,
        'free_date' => '2026-07-08',
    ]);
    $this->assertDatabaseHas('restaurant_free_days', [
        'school_id' => $this->school->id,
        'free_date' => '2026-07-10',
    ]);
    $this->assertDatabaseHas('restaurant_free_days', [
        'school_id' => $this->otherSchool->id,
        'free_date' => '2026-07-08',
    ]);
});

test('store accepts staged set and unset dates in one request', function () {
    RestaurantFreeDay::factory()->forSchool($this->school)->create(['free_date' => '2026-08-12']);
    RestaurantFreeDay::factory()->forSchool($this->school)->create(['free_date' => '2026-08-14']);

    $this->actingAs($this->admin, 'sanctum');

    $response = $this->postJson('/api/admin/restaurant/free-days', [
        'set_dates' => ['2026-08-15', '2026-08-16'],
        'unset_dates' => ['2026-08-12'],
    ]);

    $response->assertOk()
        ->assertJsonPath('meta.mode', 'bulk')
        ->assertJsonPath('meta.set_count', 2)
        ->assertJsonPath('meta.unset_count', 1)
        ->assertJsonPath('meta.affected_count', 3);

    $this->assertDatabaseMissing('restaurant_free_days', [
        'school_id' => $this->school->id,
        'free_date' => '2026-08-12',
    ]);
    $this->assertDatabaseHas('restaurant_free_days', [
        'school_id' => $this->school->id,
        'free_date' => '2026-08-14',
    ]);
    $this->assertDatabaseHas('restaurant_free_days', [
        'school_id' => $this->school->id,
        'free_date' => '2026-08-15',
    ]);
    $this->assertDatabaseHas('restaurant_free_days', [
        'school_id' => $this->school->id,
        'free_date' => '2026-08-16',
    ]);
});
