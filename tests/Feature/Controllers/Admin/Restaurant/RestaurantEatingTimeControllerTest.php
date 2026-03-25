<?php

use App\Models\RestaurantEatingTime;
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

test('returns 401 when eating times index is unauthenticated', function () {
    $this->getJson('/api/admin/restaurant/eating-times')->assertUnauthorized();
});

test('returns 403 when role has no eating time access', function () {
    $this->actingAs($this->teacher, 'sanctum');

    $this->getJson('/api/admin/restaurant/eating-times')->assertForbidden();
});

test('index returns only eating times from current school ordered by time', function () {
    RestaurantEatingTime::factory()->create(['school_id' => $this->school->id, 'eating_time' => '12:45:00']);
    RestaurantEatingTime::factory()->create(['school_id' => $this->school->id, 'eating_time' => '11:30:00']);
    RestaurantEatingTime::factory()->create(['school_id' => $this->otherSchool->id, 'eating_time' => '11:30:00']);

    $this->actingAs($this->admin, 'sanctum');

    $response = $this->getJson('/api/admin/restaurant/eating-times');

    $response->assertOk()
        ->assertJsonCount(2, 'data');

    expect(collect($response->json('data'))->pluck('eating_time')->all())
        ->toEqual(['11:30', '12:45']);
});

test('store creates a new eating time', function () {
    $this->actingAs($this->admin, 'sanctum');

    $response = $this->postJson('/api/admin/restaurant/eating-times', [
        'eating_time' => '11:30',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.eating_time', '11:30');

    expect(RestaurantEatingTime::query()
        ->where('school_id', $this->school->id)
        ->where('eating_time', '11:30:00')
        ->exists()
    )->toBeTrue();
});

test('store rejects duplicate eating time for same school', function () {
    RestaurantEatingTime::factory()->create(['school_id' => $this->school->id, 'eating_time' => '11:30:00']);

    $this->actingAs($this->admin, 'sanctum');

    $this->postJson('/api/admin/restaurant/eating-times', [
        'eating_time' => '11:30',
    ])->assertUnprocessable();
});

test('store rejects invalid time format', function () {
    $this->actingAs($this->admin, 'sanctum');

    $this->postJson('/api/admin/restaurant/eating-times', [
        'eating_time' => 'abc',
    ])->assertUnprocessable();
});

test('destroy removes eating time from school', function () {
    $eatingTime = RestaurantEatingTime::factory()->create([
        'school_id' => $this->school->id,
        'eating_time' => '11:30:00',
    ]);

    $this->actingAs($this->admin, 'sanctum');

    $this->deleteJson("/api/admin/restaurant/eating-times/{$eatingTime->id}")
        ->assertOk();

    expect(RestaurantEatingTime::query()->find($eatingTime->id))->toBeNull();
});

test('destroy cannot remove eating time from another school', function () {
    $eatingTime = RestaurantEatingTime::factory()->create([
        'school_id' => $this->otherSchool->id,
        'eating_time' => '11:30:00',
    ]);

    $this->actingAs($this->admin, 'sanctum');

    $this->deleteJson("/api/admin/restaurant/eating-times/{$eatingTime->id}")
        ->assertNotFound();
});

test('update changes the eating time', function () {
    $eatingTime = RestaurantEatingTime::factory()->create([
        'school_id' => $this->school->id,
        'eating_time' => '11:30:00',
    ]);

    $this->actingAs($this->admin, 'sanctum');

    $response = $this->putJson("/api/admin/restaurant/eating-times/{$eatingTime->id}", [
        'eating_time' => '12:45',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.eating_time', '12:45');

    expect($eatingTime->fresh()->eating_time)->toBe('12:45:00');
});

test('update rejects duplicate eating time for same school', function () {
    RestaurantEatingTime::factory()->create(['school_id' => $this->school->id, 'eating_time' => '12:45:00']);
    $eatingTime = RestaurantEatingTime::factory()->create(['school_id' => $this->school->id, 'eating_time' => '11:30:00']);

    $this->actingAs($this->admin, 'sanctum');

    $this->putJson("/api/admin/restaurant/eating-times/{$eatingTime->id}", [
        'eating_time' => '12:45',
    ])->assertUnprocessable();
});

test('update cannot modify eating time from another school', function () {
    $eatingTime = RestaurantEatingTime::factory()->create([
        'school_id' => $this->otherSchool->id,
        'eating_time' => '11:30:00',
    ]);

    $this->actingAs($this->admin, 'sanctum');

    $this->putJson("/api/admin/restaurant/eating-times/{$eatingTime->id}", [
        'eating_time' => '12:45',
    ])->assertNotFound();
});
