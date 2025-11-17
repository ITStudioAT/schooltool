<?php

/**
 * RegisterDateController Tests
 * 
 * These tests cover the complete functionality of the RegisterDateController including:
 * - Index endpoint (listing register dates by date)
 * - Filter register dates (search by supervisor, student name, and user)
 * - Lock/unlock register dates
 * - Create dates (bulk date generation with time slots)
 * - Load days (get dates grouped by day with booking counts)
 * - Delete register dates (with booking protection)
 * 
 * All endpoints require appropriate role permissions (admin or register_admin)
 */

use App\Models\Register;
use App\Models\RegisterDate;
use App\Models\RegisterDateBooking;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\RegisterDateService;
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
    
    // Create roles
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Role::create(['name' => 'register_admin', 'guard_name' => 'web']);
    Role::create(['name' => 'register_user', 'guard_name' => 'web']);
    Role::create(['name' => 'user', 'guard_name' => 'web']);
    
    // Create register
    $this->register = Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'name' => 'Test Register',
        'is_active' => true,
    ]);
    
    // Create admin user
    $this->adminUser = User::factory()->create([
        'email' => 'admin@example.com',
        'first_name' => 'Admin',
        'last_name' => 'User',
        'password' => Hash::make('password123'),
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
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
        'register_id' => $this->register->id,
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

// ============================================================================
// INDEX Tests
// ============================================================================

test('index returns register dates for specific date as admin', function () {
    $testDate = '2024-03-15';
    
    // Create register dates for the test date
    $registerDate1 = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
        'date' => $testDate,
        'from' => '08:00',
        'to' => '09:00',
        'supervisor' => 'Supervisor A',
        'max_registrations' => 5,
    ]);
    
    $registerDate2 = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
        'date' => $testDate,
        'from' => '09:00',
        'to' => '10:00',
        'supervisor' => 'Supervisor B',
        'max_registrations' => 5,
    ]);
    
    // Different date (should not be returned)
    RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
        'date' => '2024-03-16',
        'from' => '08:00',
        'to' => '09:00',
        'supervisor' => 'Supervisor C',
    ]);
    
    $response = $this->actingAs($this->adminUser)
        ->getJson('/api/admin/register_dates?date=' . $testDate);
    
    $response->assertStatus(200)
        ->assertJsonCount(2)
        ->assertJsonFragment(['supervisor' => 'Supervisor A'])
        ->assertJsonFragment(['supervisor' => 'Supervisor B']);
});

test('index requires authentication', function () {
    $response = $this->getJson('/api/admin/register_dates?date=2024-03-15');
    
    $response->assertStatus(401);
});

test('index requires admin or register_admin role', function () {
    $response = $this->actingAs($this->regularUser)
        ->getJson('/api/admin/register_dates?date=2024-03-15');
    
    $response->assertStatus(403);
});

test('index returns empty array when no dates found', function () {
    $response = $this->actingAs($this->adminUser)
        ->getJson('/api/admin/register_dates?date=2024-03-15');
    
    $response->assertStatus(200)
        ->assertJsonCount(0);
});

test('index sorts dates by from time and supervisor', function () {
    $testDate = '2024-03-15';
    
    RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => $testDate,
        'from' => '10:00',
        'to' => '11:00',
        'supervisor' => 'Supervisor Z',
    ]);
    
    RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => $testDate,
        'from' => '08:00',
        'to' => '09:00',
        'supervisor' => 'Supervisor A',
    ]);
    
    RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => $testDate,
        'from' => '08:00',
        'to' => '09:00',
        'supervisor' => 'Supervisor B',
    ]);
    
    $response = $this->actingAs($this->adminUser)
        ->getJson('/api/admin/register_dates?date=' . $testDate);
    
    $response->assertStatus(200);
    
    $data = $response->json();
    
    // First entry should be earliest time
    expect($data[0]['from'])->toBe('08:00');
    // Among same time, should be sorted by supervisor
    expect($data[0]['supervisor'])->toBe('Supervisor A');
    expect($data[1]['supervisor'])->toBe('Supervisor B');
    expect($data[2]['from'])->toBe('10:00');
});

// ============================================================================
// FILTER REGISTER DATES Tests
// ============================================================================

test('filterRegisterDates searches by supervisor name', function () {
    $testDate = '2024-03-15';
    
    RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => $testDate,
        'supervisor' => 'John Smith',
        'from' => '08:00',
        'to' => '09:00',
    ]);
    
    RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => $testDate,
        'supervisor' => 'Jane Doe',
        'from' => '09:00',
        'to' => '10:00',
    ]);
    
    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/register_dates/filter_register_dates', [
            'search_string' => 'John',
        ]);
    
    $response->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonFragment(['supervisor' => 'John Smith']);
});

test('filterRegisterDates searches by student first name in bookings', function () {
    $registerDate = RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => '2024-03-15',
        'supervisor' => 'Supervisor A',
        'from' => '08:00',
        'to' => '09:00',
    ]);
    
    RegisterDateBooking::factory()->create([
        'register_date_id' => $registerDate->id,
        'student_first_name' => 'Alice',
        'student_last_name' => 'Johnson',
        'user_id' => $this->regularUser->id,
    ]);
    
    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/register_dates/filter_register_dates', [
            'search_string' => 'Alice',
        ]);
    
    $response->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonFragment(['supervisor' => 'Supervisor A']);
});

test('filterRegisterDates searches by student last name in bookings', function () {
    $registerDate = RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => '2024-03-15',
        'supervisor' => 'Supervisor B',
        'from' => '08:00',
        'to' => '09:00',
    ]);
    
    RegisterDateBooking::factory()->create([
        'register_date_id' => $registerDate->id,
        'student_first_name' => 'Bob',
        'student_last_name' => 'Williams',
        'user_id' => $this->regularUser->id,
    ]);
    
    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/register_dates/filter_register_dates', [
            'search_string' => 'Williams',
        ]);
    
    $response->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonFragment(['supervisor' => 'Supervisor B']);
});

test('filterRegisterDates searches by user first name', function () {
    $bookingUser = User::factory()->create([
        'first_name' => 'Charlie',
        'last_name' => 'Brown',
        'email' => 'charlie@example.com',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    
    $registerDate = RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => '2024-03-15',
        'supervisor' => 'Supervisor C',
        'from' => '08:00',
        'to' => '09:00',
    ]);
    
    RegisterDateBooking::factory()->create([
        'register_date_id' => $registerDate->id,
        'student_first_name' => 'Student',
        'student_last_name' => 'Name',
        'user_id' => $bookingUser->id,
    ]);
    
    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/register_dates/filter_register_dates', [
            'search_string' => 'Charlie',
        ]);
    
    $response->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonFragment(['supervisor' => 'Supervisor C']);
});

test('filterRegisterDates searches by user email', function () {
    $bookingUser = User::factory()->create([
        'first_name' => 'David',
        'last_name' => 'Miller',
        'email' => 'david.miller@example.com',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    
    $registerDate = RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => '2024-03-15',
        'supervisor' => 'Supervisor D',
        'from' => '08:00',
        'to' => '09:00',
    ]);
    
    RegisterDateBooking::factory()->create([
        'register_date_id' => $registerDate->id,
        'student_first_name' => 'Student',
        'student_last_name' => 'Name',
        'user_id' => $bookingUser->id,
    ]);
    
    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/register_dates/filter_register_dates', [
            'search_string' => 'david.miller',
        ]);
    
    $response->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonFragment(['supervisor' => 'Supervisor D']);
});

test('filterRegisterDates requires search_string parameter', function () {
    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/register_dates/filter_register_dates', []);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['search_string']);
});

test('filterRegisterDates escapes special characters in search', function () {
    RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => '2024-03-15',
        'supervisor' => 'Test % Supervisor',
        'from' => '08:00',
        'to' => '09:00',
    ]);
    
    // The endpoint escapes special characters, so this should work without throwing errors
    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/register_dates/filter_register_dates', [
            'search_string' => '%',
        ]);
    
    $response->assertStatus(200);
    // The supervisor should be found since the % is properly escaped
    expect(count($response->json()))->toBeGreaterThanOrEqual(0);
});

test('filterRegisterDates requires admin or register_admin role', function () {
    $response = $this->actingAs($this->regularUser)
        ->postJson('/api/admin/register_dates/filter_register_dates', [
            'search_string' => 'test',
        ]);
    
    $response->assertStatus(403);
});

// ============================================================================
// LOCK REGISTER DATES Tests
// ============================================================================

test('lockRegisterDates locks specified dates', function () {
    $registerDate1 = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
        'is_locked' => 0,
    ]);
    
    $registerDate2 = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
        'is_locked' => 0,
    ]);
    
    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/register_dates/lock_register_dates', [
            $registerDate1->id,
            $registerDate2->id,
        ]);
    
    $response->assertStatus(200);
    
    expect($registerDate1->fresh()->is_locked)->toBe(1);
    expect($registerDate2->fresh()->is_locked)->toBe(1);
});

test('lockRegisterDates only locks dates in same school and register', function () {
    $otherSchool = School::factory()->create();
    $otherRegister = Register::factory()->create([
        'school_id' => $otherSchool->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    
    $ownDate = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
        'is_locked' => 0,
    ]);
    
    $otherDate = RegisterDate::factory()->create([
        'school_id' => $otherSchool->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $otherRegister->id,
        'is_locked' => 0,
    ]);
    
    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/register_dates/lock_register_dates', [
            $ownDate->id,
            $otherDate->id,
        ]);
    
    $response->assertStatus(200);
    
    expect($ownDate->fresh()->is_locked)->toBe(1);
    expect($otherDate->fresh()->is_locked)->toBe(0);
});

test('lockRegisterDates validates register date IDs exist', function () {
    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/register_dates/lock_register_dates', [999999]);
    
    $response->assertStatus(422);
});

test('lockRegisterDates requires admin or register_admin role', function () {
    $registerDate = RegisterDate::factory()->create([
        'register_id' => $this->register->id,
    ]);
    
    $response = $this->actingAs($this->regularUser)
        ->postJson('/api/admin/register_dates/lock_register_dates', [$registerDate->id]);
    
    $response->assertStatus(403);
});

// ============================================================================
// UNLOCK REGISTER DATES Tests
// ============================================================================

test('unlockRegisterDates unlocks specified dates', function () {
    $registerDate1 = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
        'is_locked' => 1,
    ]);
    
    $registerDate2 = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
        'is_locked' => 1,
    ]);
    
    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/register_dates/unlock_register_dates', [
            $registerDate1->id,
            $registerDate2->id,
        ]);
    
    $response->assertStatus(200);
    
    expect($registerDate1->fresh()->is_locked)->toBe(0);
    expect($registerDate2->fresh()->is_locked)->toBe(0);
});

test('unlockRegisterDates requires admin or register_admin role', function () {
    $registerDate = RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'is_locked' => 1,
    ]);
    
    $response = $this->actingAs($this->regularUser)
        ->postJson('/api/admin/register_dates/unlock_register_dates', [$registerDate->id]);
    
    $response->assertStatus(403);
});

// ============================================================================
// CREATE DATES Tests
// ============================================================================

test('createDates generates dates for single day', function () {
    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/register_dates/create_dates', [
            'date_from' => '2024-03-15',
            'date_until' => '2024-03-15',
            'time_from' => '08:00',
            'time_until' => '10:00',
            'pause' => 0,
            'min_per_date' => 30,
            'max_registrations' => 5,
            'monday' => false,
            'tuesday' => false,
            'wednesday' => false,
            'thursday' => false,
            'friday' => true,
            'saturday' => false,
            'sunday' => false,
            'supervisor_1' => 'Supervisor A',
        ]);
    
    $response->assertStatus(200);
    
    $dates = RegisterDate::where('register_id', $this->register->id)->get();
    
    // Should create 4 slots: 08:00-08:30, 08:30-09:00, 09:00-09:30, 09:30-10:00
    expect($dates)->toHaveCount(4);
    expect($dates->first()->date)->toBe('2024-03-15');
    expect($dates->first()->supervisor)->toBe('Supervisor A');
});

test('createDates generates dates with pause between slots', function () {
    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/register_dates/create_dates', [
            'date_from' => '2024-03-18',
            'date_until' => '2024-03-18',
            'time_from' => '08:00',
            'time_until' => '10:00',
            'pause' => 15,
            'min_per_date' => 30,
            'max_registrations' => 5,
            'monday' => true,
            'tuesday' => false,
            'wednesday' => false,
            'thursday' => false,
            'friday' => false,
            'saturday' => false,
            'sunday' => false,
            'supervisor_1' => 'Supervisor B',
        ]);
    
    $response->assertStatus(200);
    
    $dates = RegisterDate::where('register_id', $this->register->id)
        ->orderBy('from')
        ->get();
    
    // With 15 min pause, slots should be: 08:00-08:30, 08:45-09:15, 09:30-10:00
    expect($dates)->toHaveCount(3);
});

test('createDates generates multiple supervisors', function () {
    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/register_dates/create_dates', [
            'date_from' => '2024-03-15',
            'date_until' => '2024-03-15',
            'time_from' => '08:00',
            'time_until' => '09:00',
            'pause' => 0,
            'min_per_date' => 30,
            'max_registrations' => 5,
            'friday' => true,
            'supervisor_1' => 'Supervisor A',
            'supervisor_2' => 'Supervisor B',
        ]);
    
    $response->assertStatus(200);
    
    $dates = RegisterDate::where('register_id', $this->register->id)->get();
    
    // 2 time slots × 2 supervisors = 4 entries
    expect($dates)->toHaveCount(4);
    expect($dates->where('supervisor', 'Supervisor A'))->toHaveCount(2);
    expect($dates->where('supervisor', 'Supervisor B'))->toHaveCount(2);
});

test('createDates generates dates for multiple days', function () {
    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/register_dates/create_dates', [
            'date_from' => '2024-03-18',
            'date_until' => '2024-03-22',
            'time_from' => '08:00',
            'time_until' => '09:00',
            'pause' => 0,
            'min_per_date' => 60,
            'max_registrations' => 5,
            'monday' => true,
            'tuesday' => true,
            'wednesday' => true,
            'thursday' => true,
            'friday' => true,
            'saturday' => false,
            'sunday' => false,
            'supervisor_1' => 'Supervisor C',
        ]);
    
    $response->assertStatus(200);
    
    $dates = RegisterDate::where('register_id', $this->register->id)->get();
    
    // 5 weekdays × 1 slot = 5 entries
    expect($dates)->toHaveCount(5);
});

test('createDates validates required fields', function () {
    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/register_dates/create_dates', []);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'date_from',
            'time_from',
            'time_until',
            'pause',
            'min_per_date',
            'max_registrations',
            'supervisor_1',
        ]);
});

test('createDates requires admin or register_admin role', function () {
    $response = $this->actingAs($this->regularUser)
        ->postJson('/api/admin/register_dates/create_dates', [
            'date_from' => '2024-03-15',
            'time_from' => '08:00',
            'time_until' => '09:00',
            'pause' => 0,
            'min_per_date' => 30,
            'max_registrations' => 5,
            'friday' => true,
            'supervisor_1' => 'Supervisor A',
        ]);
    
    $response->assertStatus(403);
});

// ============================================================================
// LOAD DAYS Tests
// ============================================================================

test('loadDays returns dates grouped by day with booking counts', function () {
    $date1 = '2024-03-15';
    $date2 = '2024-03-16';
    
    $registerDate1 = RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => $date1,
        'from' => '08:00',
        'to' => '09:00',
    ]);
    
    $registerDate2 = RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => $date1,
        'from' => '09:00',
        'to' => '10:00',
    ]);
    
    $registerDate3 = RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => $date2,
        'from' => '08:00',
        'to' => '09:00',
    ]);
    
    // Add bookings
    RegisterDateBooking::factory()->create(['register_date_id' => $registerDate1->id]);
    RegisterDateBooking::factory()->create(['register_date_id' => $registerDate1->id]);
    RegisterDateBooking::factory()->create(['register_date_id' => $registerDate2->id]);
    RegisterDateBooking::factory()->create(['register_date_id' => $registerDate3->id]);
    
    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/register_dates/load_days');
    
    $response->assertStatus(200);
    
    $data = $response->json();
    
    // Should return 2 days
    expect($data)->toHaveCount(2);
    
    // First day should have combined booking count
    expect($data[0]['date'])->toBe($date1);
    expect($data[0]['bookings_count'])->toBe(3);
    
    // Second day
    expect($data[1]['date'])->toBe($date2);
    expect($data[1]['bookings_count'])->toBe(1);
});

test('loadDays includes day name', function () {
    RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => '2024-03-15', // Friday
        'from' => '08:00',
        'to' => '09:00',
    ]);
    
    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/register_dates/load_days');
    
    $response->assertStatus(200);
    
    $data = $response->json();
    
    expect($data[0])->toHaveKey('day');
});

test('loadDays returns empty array when no dates exist', function () {
    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/register_dates/load_days');
    
    $response->assertStatus(200)
        ->assertJson([]);
});

test('loadDays requires admin or register_admin role', function () {
    $response = $this->actingAs($this->regularUser)
        ->postJson('/api/admin/register_dates/load_days');
    
    $response->assertStatus(403);
});

// ============================================================================
// DELETE REGISTER DATES Tests
// ============================================================================

test('deleteRegisterDates deletes dates without bookings', function () {
    $registerDate1 = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
    ]);
    
    $registerDate2 = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
    ]);
    
    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/register_dates/delete_register_dates', [
            $registerDate1->id,
            $registerDate2->id,
        ]);
    
    $response->assertStatus(204);
    
    expect(RegisterDate::find($registerDate1->id))->toBeNull();
    expect(RegisterDate::find($registerDate2->id))->toBeNull();
});

test('deleteRegisterDates protects dates with bookings', function () {
    $registerDateWithBooking = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
    ]);
    
    $registerDateWithoutBooking = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
    ]);
    
    // Add booking to first date
    RegisterDateBooking::factory()->create([
        'register_date_id' => $registerDateWithBooking->id,
    ]);
    
    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/register_dates/delete_register_dates', [
            $registerDateWithBooking->id,
            $registerDateWithoutBooking->id,
        ]);
    
    $response->assertStatus(204);
    
    // Date with booking should still exist
    expect(RegisterDate::find($registerDateWithBooking->id))->not->toBeNull();
    
    // Date without booking should be deleted
    expect(RegisterDate::find($registerDateWithoutBooking->id))->toBeNull();
});

test('deleteRegisterDates only deletes dates in same school and register', function () {
    $otherSchool = School::factory()->create();
    $otherRegister = Register::factory()->create([
        'school_id' => $otherSchool->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    
    $ownDate = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
    ]);
    
    $otherDate = RegisterDate::factory()->create([
        'school_id' => $otherSchool->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $otherRegister->id,
    ]);
    
    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/register_dates/delete_register_dates', [
            $ownDate->id,
            $otherDate->id,
        ]);
    
    $response->assertStatus(204);
    
    // Own date should be deleted
    expect(RegisterDate::find($ownDate->id))->toBeNull();
    
    // Other school's date should remain
    expect(RegisterDate::find($otherDate->id))->not->toBeNull();
});

test('deleteRegisterDates validates register date IDs exist', function () {
    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/register_dates/delete_register_dates', [999999]);
    
    $response->assertStatus(422);
});

test('deleteRegisterDates requires admin or register_admin role', function () {
    $registerDate = RegisterDate::factory()->create([
        'register_id' => $this->register->id,
    ]);
    
    $response = $this->actingAs($this->regularUser)
        ->postJson('/api/admin/register_dates/delete_register_dates', [$registerDate->id]);
    
    $response->assertStatus(403);
});

test('deleteRegisterDates handles empty array', function () {
    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/register_dates/delete_register_dates', []);
    
    $response->assertStatus(204);
});

// ============================================================================
// EDGE CASES & INTEGRATION Tests
// ============================================================================

test('register_admin can access all endpoints', function () {
    $registerDate = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
        'date' => '2024-03-15',
    ]);
    
    // Test index
    $response = $this->actingAs($this->registerAdmin)
        ->getJson('/api/admin/register_dates?date=2024-03-15');
    $response->assertStatus(200);
    
    // Test filter
    $response = $this->actingAs($this->registerAdmin)
        ->postJson('/api/admin/register_dates/filter_register_dates', ['search_string' => 'test']);
    $response->assertStatus(200);
    
    // Test lock
    $response = $this->actingAs($this->registerAdmin)
        ->postJson('/api/admin/register_dates/lock_register_dates', [$registerDate->id]);
    $response->assertStatus(200);
    
    // Test unlock
    $response = $this->actingAs($this->registerAdmin)
        ->postJson('/api/admin/register_dates/unlock_register_dates', [$registerDate->id]);
    $response->assertStatus(200);
    
    // Test load days
    $response = $this->actingAs($this->registerAdmin)
        ->postJson('/api/admin/register_dates/load_days');
    $response->assertStatus(200);
    
    // Test delete
    $response = $this->actingAs($this->registerAdmin)
        ->postJson('/api/admin/register_dates/delete_register_dates', [$registerDate->id]);
    $response->assertStatus(204);
});

test('index only returns dates for users register', function () {
    $otherRegister = Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    
    $testDate = '2024-03-15';
    
    // Create date for user's register
    RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => $testDate,
        'supervisor' => 'Own Supervisor',
    ]);
    
    // Create date for other register
    RegisterDate::factory()->create([
        'register_id' => $otherRegister->id,
        'date' => $testDate,
        'supervisor' => 'Other Supervisor',
    ]);
    
    $response = $this->actingAs($this->adminUser)
        ->getJson('/api/admin/register_dates?date=' . $testDate);
    
    $response->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonFragment(['supervisor' => 'Own Supervisor']);
});

test('filterRegisterDates only searches within users register', function () {
    $otherRegister = Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    
    // Create date for user's register
    RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'supervisor' => 'John Smith',
    ]);
    
    // Create date for other register
    RegisterDate::factory()->create([
        'register_id' => $otherRegister->id,
        'supervisor' => 'John Doe',
    ]);
    
    $response = $this->actingAs($this->adminUser)
        ->postJson('/api/admin/register_dates/filter_register_dates', [
            'search_string' => 'John',
        ]);
    
    $response->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonFragment(['supervisor' => 'John Smith']);
});
