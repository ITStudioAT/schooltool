<?php

/**
 * RegisterController Tests
 *
 * These tests cover the core functionality of the RegisterController including:
 * - Index endpoint (listing registers)
 * - Get active registers
 * - Store (create new register)
 * - Update existing register
 * - Delete register
 * - Set active register for user
 * - Toggle register active status
 *
 * All endpoints require appropriate role permissions (admin or register_admin)
 */

use App\Models\Licence;
use App\Models\Register;
use App\Models\RegisterDate;
use App\Models\RegisterDateBooking;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test school and schoolyear
    $this->school = School::factory()->create([
        'long_name' => 'Test School',
        'short_name' => 'TS',
        'logo' => 'test-logo.png',
    ]);

    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'name' => '2023/2024',
    ]);

    $this->otherSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'name' => '2024/2025',
    ]);

    $registerLicence = Licence::firstOrCreate(
        ['name' => 'Anmeldetool'],
        ['long_name' => 'Anmeldetool', 'is_selectable' => true]
    );
    $this->school->licences()->attach($registerLicence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);

    // Create roles
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'register_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

    // Create admin user
    $this->adminUser = User::factory()->create([
        'email' => 'admin@example.com',
        'first_name' => 'Admin',
        'last_name' => 'User',
        'password' => Hash::make('password123'),
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'confirmed_at' => now(),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);
    $this->adminUser->assignRole('admin');

    // Create register_admin user
    $this->registerAdmin = User::factory()->create([
        'email' => 'register_admin@example.com',
        'first_name' => 'Register',
        'last_name' => 'Admin',
        'password' => Hash::make('password123'),
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'confirmed_at' => now(),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);
    $this->registerAdmin->assignRole('register_admin');

    // Create regular user
    $this->regularUser = User::factory()->create([
        'email' => 'user@example.com',
        'first_name' => 'Regular',
        'last_name' => 'User',
        'password' => Hash::make('password123'),
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'confirmed_at' => now(),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);
    $this->regularUser->assignRole('user');
});

// Index Tests
test('index returns list of registers for current schoolyear for admin', function () {
    $register1 = Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'name' => 'Register 1',
    ]);

    $register2 = Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'name' => 'Register 2',
    ]);

    // Create register for different schoolyear (should not be included)
    Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
        'name' => 'Other Schoolyear Register',
    ]);

    $response = $this->actingAs($this->adminUser)
        ->getJson('/api/admin/registers');

    $response->assertStatus(200)
        ->assertJsonCount(2)
        ->assertJsonStructure([
            '*' => [
                'id',
                'name',
                'school_id',
                'schoolyear_id',
                'is_active',
                'max_registrations',
                'bookings_count',
                'dates_count',
                'different_dates_count',
            ],
        ]);
});

test('index returns list of registers for register_admin', function () {
    Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'name' => 'Test Register',
    ]);

    $response = $this->actingAs($this->registerAdmin)
        ->getJson('/api/admin/registers');

    $response->assertStatus(200)
        ->assertJsonCount(1);
});

test('index returns 403 for regular user', function () {
    $response = $this->actingAs($this->regularUser)
        ->getJson('/api/admin/registers');

    $response->assertStatus(403);
});

test('index returns 401 for unauthenticated user', function () {
    $response = $this->getJson('/api/admin/registers');

    $response->assertStatus(401);
});

test('index includes correct counts for bookings and dates', function () {
    $register = Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'name' => 'Test Register',
    ]);

    // Create register dates
    $date1 = RegisterDate::factory()->create([
        'register_id' => $register->id,
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'date' => '2024-01-10',
    ]);

    $date2 = RegisterDate::factory()->create([
        'register_id' => $register->id,
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'date' => '2024-01-11',
    ]);

    // Create bookings
    RegisterDateBooking::factory()->create([
        'register_id' => $register->id,
        'register_date_id' => $date1->id,
        'user_id' => $this->regularUser->id,
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);

    RegisterDateBooking::factory()->create([
        'register_id' => $register->id,
        'register_date_id' => $date2->id,
        'user_id' => $this->regularUser->id,
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);

    $response = $this->actingAs($this->adminUser)
        ->getJson('/api/admin/registers');

    $response->assertStatus(200)
        ->assertJsonPath('0.bookings_count', 2)
        ->assertJsonPath('0.dates_count', 2)
        ->assertJsonPath('0.different_dates_count', 2);
});

// Get Active Registers Tests
test('getActiveRegisters returns only active registers across all schoolyears', function () {
    $activeRegister1 = Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'name' => 'Active Register 1',
        'is_active' => true,
    ]);

    $activeRegister2 = Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
        'name' => 'Active Register 2',
        'is_active' => true,
    ]);

    $inactiveRegister = Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'name' => 'Inactive Register',
        'is_active' => false,
    ]);

    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/registers/get_active');

    $response->assertStatus(200)
        ->assertJsonCount(2);

    $responseData = $response->json();
    foreach ($responseData as $register) {
        expect($register['is_active'])->toBe(true);
    }
});

test('getActiveRegisters orders by schoolyear desc then name', function () {
    Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'name' => 'B Register',
        'is_active' => true,
    ]);

    Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
        'name' => 'A Register',
        'is_active' => true,
    ]);

    Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'name' => 'A Register',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/registers/get_active');

    $response->assertStatus(200);

    $names = collect($response->json())->pluck('name')->toArray();
    // Should be ordered by schoolyear desc (2024/2025 first), then by name
    expect($names)->toBe(['A Register', 'A Register', 'B Register']);
});

test('getActiveRegisters returns 403 for regular user', function () {
    $response = $this->actingAs($this->regularUser)
        ->postJson('/api/admin/registers/get_active');

    $response->assertStatus(403);
});

// Store Tests
test('store creates new register for admin', function () {
    $data = [
        'name' => 'New Register',
        'description_on_website' => 'Test description',
        'max_registrations' => 100,
        'is_active' => true,
        'show_phone' => true,
        'must_phone' => false,
        'show_student_last_name' => true,
        'must_student_last_name' => true,
        'show_student_first_name' => true,
        'must_student_first_name' => false,
        'show_student_birthdate' => false,
        'must_student_birthdate' => false,
        'show_note' => true,
        'must_note' => false,
        'show_booked' => true,
        'show_end_time' => true,
        'show_supervisor' => false,
    ];

    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/registers', $data);

    $response->assertStatus(200)
        ->assertJsonPath('name', 'New Register')
        ->assertJsonPath('school_id', $this->school->id)
        ->assertJsonPath('schoolyear_id', $this->schoolyear->id);

    $this->assertDatabaseHas('registers', [
        'name' => 'New Register',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
});

test('store creates register with minimal required fields', function () {
    $data = [
        'name' => 'Minimal Register',
        'max_registrations' => 0,
    ];

    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/registers', $data);

    $response->assertStatus(200)
        ->assertJsonPath('name', 'Minimal Register');

    $this->assertDatabaseHas('registers', [
        'name' => 'Minimal Register',
    ]);
});

test('store validates required fields', function () {
    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/registers', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'max_registrations']);
});

test('store validates max_registrations is integer', function () {
    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/registers', [
            'name' => 'Test',
            'max_registrations' => 'not-a-number',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['max_registrations']);
});

test('store validates max_registrations minimum value', function () {
    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/registers', [
            'name' => 'Test',
            'max_registrations' => -1,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['max_registrations']);
});

test('store returns 403 for regular user', function () {
    $response = $this->actingAs($this->regularUser)
        ->postJson('/api/admin/registers', [
            'name' => 'Test',
            'max_registrations' => 100,
        ]);

    $response->assertStatus(403);
});

test('store works for register_admin', function () {
    $response = $this->actingAs($this->registerAdmin)
        ->postJson('/api/admin/registers', [
            'name' => 'Register Admin Test',
            'max_registrations' => 50,
        ]);

    $response->assertStatus(200);
});

// Update Tests
test('update modifies existing register', function () {
    $register = Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'name' => 'Original Name',
        'max_registrations' => 50,
    ]);

    $data = [
        'id' => $register->id,
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'name' => 'Updated Name',
        'max_registrations' => 100,
        'description_on_website' => 'Updated description',
        'is_active' => false,
    ];

    $response = $this->actingAs($this->adminUser)
        ->putJson("/api/admin/registers/{$register->id}", $data);

    $response->assertStatus(200)
        ->assertJsonPath('name', 'Updated Name')
        ->assertJsonPath('max_registrations', 100);

    $this->assertDatabaseHas('registers', [
        'id' => $register->id,
        'name' => 'Updated Name',
        'max_registrations' => 100,
    ]);
});

test('update validates required fields', function () {
    $register = Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);

    $response = $this->actingAs($this->adminUser)
        ->putJson("/api/admin/registers/{$register->id}", [
            'id' => $register->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['school_id', 'schoolyear_id', 'name', 'max_registrations']);
});

test('update returns 403 for regular user', function () {
    $register = Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);

    $response = $this->actingAs($this->regularUser)
        ->putJson("/api/admin/registers/{$register->id}", [
            'id' => $register->id,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'name' => 'Hacked',
            'max_registrations' => 1,
        ]);

    $response->assertStatus(403);
});

// Destroy Tests
test('destroy deletes register without dependencies', function () {
    $register = Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'name' => 'To Delete',
    ]);

    $registerId = $register->id;

    $response = $this->actingAs($this->adminUser)
        ->deleteJson("/api/admin/registers/{$registerId}");

    $response->assertStatus(204);

    $this->assertDatabaseMissing('registers', [
        'id' => $registerId,
    ]);
});

test('destroy clears user register_id before deleting', function () {
    $register = Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);

    $this->adminUser->register_id = $register->id;
    $this->adminUser->save();

    $response = $this->actingAs($this->adminUser)
        ->deleteJson("/api/admin/registers/{$register->id}");

    $response->assertStatus(204);

    $this->adminUser->refresh();
    expect($this->adminUser->register_id)->toBeNull();
});

test('destroy returns 403 for regular user', function () {
    $register = Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);

    $response = $this->actingAs($this->regularUser)
        ->deleteJson("/api/admin/registers/{$register->id}");

    $response->assertStatus(403);
});

// Set Active Register Tests
test('setActiveRegister sets register for user', function () {
    $register = Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'name' => 'Selected Register',
    ]);

    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/registers/set_active', [
            'register_id' => $register->id,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('id', $register->id)
        ->assertJsonPath('name', 'Selected Register');

    $this->adminUser->refresh();
    expect($this->adminUser->register_id)->toBe($register->id);
});

test('setActiveRegister validates register_id exists', function () {
    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/registers/set_active', [
            'register_id' => 99999,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['register_id']);
});

test('setActiveRegister requires register_id', function () {
    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/registers/set_active', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['register_id']);
});

test('setActiveRegister returns 403 for regular user', function () {
    $register = Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);

    $response = $this->actingAs($this->regularUser)
        ->postJson('/api/admin/registers/set_active', [
            'register_id' => $register->id,
        ]);

    $response->assertStatus(403);
});

// Toggle Register Tests
test('toggleRegister activates inactive register', function () {
    $register = Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'is_active' => false,
    ]);

    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/registers/toggle', [
            'register_id' => $register->id,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('is_active', true);

    $this->assertDatabaseHas('registers', [
        'id' => $register->id,
        'is_active' => true,
    ]);
});

test('toggleRegister deactivates active register', function () {
    $register = Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/registers/toggle', [
            'register_id' => $register->id,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('is_active', false);

    $this->assertDatabaseHas('registers', [
        'id' => $register->id,
        'is_active' => false,
    ]);
});

test('toggleRegister validates register_id', function () {
    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/registers/toggle', [
            'register_id' => 99999,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['register_id']);
});

test('toggleRegister returns 403 for regular user', function () {
    $register = Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);

    $response = $this->actingAs($this->regularUser)
        ->postJson('/api/admin/registers/toggle', [
            'register_id' => $register->id,
        ]);

    $response->assertStatus(403);
});
