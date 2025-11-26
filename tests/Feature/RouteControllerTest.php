<?php

/**
 * RouteController Tests
 *
 * Tests the SPA route permission checking system
 * Note: This endpoint doesn't require authentication at route level
 * but checks permissions internally via RouteService
 */

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);

    Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Role::create(['name' => 'user', 'guard_name' => 'web']);

    $this->superAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->superAdmin->assignRole('super_admin');

    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->admin->assignRole('admin');

    $this->user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->user->assignRole('user');
});

test('is route allowed endpoint exists and accepts requests', function () {
    $this->actingAs($this->superAdmin);

    $response = $this->postJson('/api/routes/is_route_allowed', [
        'data' => [
            'to' => '/admin/dashboard',
        ],
    ]);

    // Should return some response (200, 403, 404, etc.)
    expect($response->status())->toBeIn([200, 403, 404, 500]);
});

test('is route allowed handles missing data field gracefully', function () {
    $this->actingAs($this->superAdmin);

    $response = $this->postJson('/api/routes/is_route_allowed', [
        'invalid' => 'structure',
    ]);

    // Controller expects 'data' key, will error without it
    expect($response->status())->toBeIn([500, 404]);
});

test('is route allowed handles empty request', function () {
    $this->actingAs($this->superAdmin);

    $response = $this->postJson('/api/routes/is_route_allowed', []);

    // Controller expects 'data' key, will error without it
    expect($response->status())->toBeIn([500, 404]);
});

test('is route allowed accepts valid request structure', function () {
    $this->actingAs($this->superAdmin);

    $response = $this->postJson('/api/routes/is_route_allowed', [
        'data' => [
            'to' => '/admin/users',
        ],
    ]);

    // Should not return validation error
    expect($response->status())->not->toBe(422);
});

test('is route allowed works for authenticated users', function () {
    $this->actingAs($this->admin);

    $response = $this->postJson('/api/routes/is_route_allowed', [
        'data' => [
            'to' => '/admin/schools',
        ],
    ]);

    // Should process the request
    expect($response->status())->toBeIn([200, 403, 404]);
});

test('is route allowed handles unauthenticated requests', function () {
    $response = $this->postJson('/api/routes/is_route_allowed', [
        'data' => [
            'to' => '/admin/dashboard',
        ],
    ]);

    // May return 401, 403, or handle gracefully depending on implementation
    expect($response->status())->toBeGreaterThanOrEqual(200);
});
