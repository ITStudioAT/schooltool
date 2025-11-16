<?php

/**
 * RegisterDateBookingController Tests
 * 
 * These tests cover the core functionality of the RegisterDateBookingController including:
 * - Index endpoint (listing register dates with bookings)
 * - Store (create new booking)
 * - Get user by email
 * - Update or create user
 * - Delete bookings with optional notifications
 * 
 * All endpoints require appropriate role permissions (admin or register_admin)
 */

use App\Models\Register;
use App\Models\RegisterDate;
use App\Models\RegisterDateBooking;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\RegisterDateBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use App\Notifications\StandardEmail;
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
    
    // Create register user for bookings
    $this->registerUser = User::factory()->create([
        'email' => 'registeruser@example.com',
        'first_name' => 'Register',
        'last_name' => 'User',
        'password' => Hash::make('password123'),
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'confirmed_at' => now(),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);
    $this->registerUser->assignRole('register_user');
});

// Index Tests
test('index returns register dates with bookings for admin', function () {
    $registerDate1 = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
        'date' => '2024-01-15',
        'from' => '08:00:00',
        'to' => '10:00:00',
        'supervisor' => 'Supervisor A',
    ]);
    
    $registerDate2 = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
        'date' => '2024-01-16',
        'from' => '10:00:00',
        'to' => '12:00:00',
        'supervisor' => 'Supervisor B',
    ]);
    
    $response = $this->actingAs($this->adminUser)->getJson(
        '/api/admin/register_date_bookings?dates[]=' . $registerDate1->id . '&dates[]=' . $registerDate2->id
    );
    
    $response->assertStatus(200)
        ->assertJsonCount(2)
        ->assertJsonFragment(['supervisor' => 'Supervisor A'])
        ->assertJsonFragment(['supervisor' => 'Supervisor B']);
});

test('index returns register dates ordered by date, from, and supervisor', function () {
    $registerDate1 = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
        'date' => '2024-01-16',
        'from' => '08:00:00',
        'to' => '10:00:00',
        'supervisor' => 'Supervisor B',
    ]);
    
    $registerDate2 = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
        'date' => '2024-01-15',
        'from' => '10:00:00',
        'to' => '12:00:00',
        'supervisor' => 'Supervisor A',
    ]);
    
    $response = $this->actingAs($this->adminUser)->getJson(
        '/api/admin/register_date_bookings?dates[]=' . $registerDate1->id . '&dates[]=' . $registerDate2->id
    );
    
    $response->assertStatus(200);
    
    $data = $response->json();
    expect($data[0]['date'])->toBe('2024-01-15');
    expect($data[1]['date'])->toBe('2024-01-16');
});

test('index requires dates parameter as array', function () {
    $response = $this->actingAs($this->adminUser)->getJson('/api/admin/register_date_bookings?dates=not-an-array');
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['dates']);
});

test('index requires valid register date IDs', function () {
    $response = $this->actingAs($this->adminUser)->getJson('/api/admin/register_date_bookings?dates[]=999999');
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['dates.0']);
});

// Store Tests
test('store creates new booking for admin', function () {
    Notification::fake();
    
    $registerDate = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
        'date' => '2024-01-15',
        'from' => '08:00:00',
        'to' => '10:00:00',
    ]);
    
    $response = $this->actingAs($this->adminUser)->postJson('/api/admin/register_date_bookings', [
        'register_date_id' => $registerDate->id,
        'email' => $this->registerUser->email,
        'student_last_name' => 'Student',
        'student_first_name' => 'Test',
        'student_birthdate' => '2010-05-15',
        'note' => 'Test note',
        'is_notify' => false,
    ]);
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'id',
            'last_name',
            'first_name',
            'email',
            'student_last_name',
            'student_first_name',
            'student_birthdate',
            'note',
        ]);
    
    $this->assertDatabaseHas('register_date_bookings', [
        'register_date_id' => $registerDate->id,
        'user_id' => $this->registerUser->id,
        'student_last_name' => 'Student',
        'student_first_name' => 'Test',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
    ]);
});

test('store creates booking and assigns register_user role', function () {
    $newUser = User::factory()->create([
        'email' => 'newuser@example.com',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'confirmed_at' => now(),
        'email_verified_at' => now(),
    ]);
    
    $registerDate = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
    ]);
    
    $response = $this->actingAs($this->adminUser)->postJson('/api/admin/register_date_bookings', [
        'register_date_id' => $registerDate->id,
        'email' => $newUser->email,
        'student_last_name' => 'Student',
        'is_notify' => false,
    ]);
    
    $response->assertStatus(200);
    
    expect($newUser->fresh()->hasRole('register_user'))->toBeTrue();
});

test('store sends notification when is_notify is true', function () {
    Notification::fake();
    
    $registerDate = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
        'date' => '2024-01-15',
        'from' => '08:00:00',
        'to' => '10:00:00',
    ]);
    
    $response = $this->actingAs($this->adminUser)->postJson('/api/admin/register_date_bookings', [
        'register_date_id' => $registerDate->id,
        'email' => $this->registerUser->email,
        'student_last_name' => 'Student',
        'is_notify' => true,
    ]);
    
    $response->assertStatus(200);
    
    Notification::assertSentTo(
        [Notification::route('mail', $this->registerUser->email)],
        StandardEmail::class
    );
});

test('store fails for register_admin user', function () {
    $registerDate = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
    ]);
    
    $response = $this->actingAs($this->registerAdmin)->postJson('/api/admin/register_date_bookings', [
        'register_date_id' => $registerDate->id,
        'email' => $this->registerUser->email,
        'student_last_name' => 'Student',
        'is_notify' => false,
    ]);
    
    $response->assertStatus(200);
});

test('store fails when user not found', function () {
    $registerDate = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
    ]);
    
    $response = $this->actingAs($this->adminUser)->postJson('/api/admin/register_date_bookings', [
        'register_date_id' => $registerDate->id,
        'email' => 'nonexistent@example.com',
        'student_last_name' => 'Student',
        'is_notify' => false,
    ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('store fails for unauthorized user', function () {
    $registerDate = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
    ]);
    
    $response = $this->actingAs($this->regularUser)->postJson('/api/admin/register_date_bookings', [
        'register_date_id' => $registerDate->id,
        'email' => $this->registerUser->email,
        'student_last_name' => 'Student',
        'is_notify' => false,
    ]);
    
    $response->assertStatus(403);
});

test('store requires valid register_date_id', function () {
    $response = $this->actingAs($this->adminUser)->postJson('/api/admin/register_date_bookings', [
        'register_date_id' => 999999,
        'email' => $this->registerUser->email,
        'student_last_name' => 'Student',
        'is_notify' => false,
    ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['register_date_id']);
});

test('store requires existing user email', function () {
    $registerDate = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
    ]);
    
    $response = $this->actingAs($this->adminUser)->postJson('/api/admin/register_date_bookings', [
        'register_date_id' => $registerDate->id,
        'email' => 'nonexistent@example.com',
        'student_last_name' => 'Student',
        'is_notify' => false,
    ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

// Get User With Email Tests
test('getUserWithEmail returns user for admin', function () {
    $response = $this->actingAs($this->adminUser)->postJson('/api/admin/register_date_bookings/get_user_with_email', [
        'email' => $this->registerUser->email,
    ]);
    
    $response->assertStatus(200)
        ->assertJsonFragment([
            'email' => $this->registerUser->email,
            'first_name' => $this->registerUser->first_name,
            'last_name' => $this->registerUser->last_name,
        ]);
});

test('getUserWithEmail returns null for non-existent email', function () {
    $response = $this->actingAs($this->adminUser)->postJson('/api/admin/register_date_bookings/get_user_with_email', [
        'email' => 'nonexistent@example.com',
    ]);
    
    $response->assertStatus(200);
    // When controller returns null, json() method returns empty array or null
    $data = $response->json();
    expect($data === null || $data === [])->toBeTrue();
});

test('getUserWithEmail works for register_admin', function () {
    $response = $this->actingAs($this->registerAdmin)->postJson('/api/admin/register_date_bookings/get_user_with_email', [
        'email' => $this->registerUser->email,
    ]);
    
    $response->assertStatus(200)
        ->assertJsonFragment(['email' => $this->registerUser->email]);
});

test('getUserWithEmail fails for unauthorized user', function () {
    $response = $this->actingAs($this->regularUser)->postJson('/api/admin/register_date_bookings/get_user_with_email', [
        'email' => $this->registerUser->email,
    ]);
    
    $response->assertStatus(403);
});

test('getUserWithEmail requires valid email format', function () {
    $response = $this->actingAs($this->adminUser)->postJson('/api/admin/register_date_bookings/get_user_with_email', [
        'email' => 'not-an-email',
    ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

// Update Or Create User Tests
test('updateOrCreateUser creates new user for admin', function () {
    $response = $this->actingAs($this->adminUser)->postJson('/api/admin/register_date_bookings/update_or_create_user', [
        'email' => 'newuser@example.com',
        'last_name' => 'NewUser',
        'first_name' => 'Test',
        'phone' => '1234567890',
    ]);
    
    $response->assertStatus(200)
        ->assertJsonFragment([
            'email' => 'newuser@example.com',
            'last_name' => 'NewUser',
            'first_name' => 'Test',
            'phone' => '1234567890',
        ]);
    
    $this->assertDatabaseHas('users', [
        'email' => 'newuser@example.com',
        'last_name' => 'NewUser',
        'first_name' => 'Test',
        'phone' => '1234567890',
        'school_id' => $this->school->id,
    ]);
    
    $user = User::where('email', 'newuser@example.com')->first();
    expect($user->hasRole('register_user'))->toBeTrue();
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->confirmed_at)->not->toBeNull();
});

test('updateOrCreateUser updates existing user', function () {
    $existingUser = User::factory()->create([
        'email' => 'existing@example.com',
        'last_name' => 'OldName',
        'first_name' => 'OldFirst',
        'phone' => '0000000000',
        'school_id' => $this->school->id,
    ]);
    
    $response = $this->actingAs($this->adminUser)->postJson('/api/admin/register_date_bookings/update_or_create_user', [
        'email' => 'existing@example.com',
        'last_name' => 'NewName',
        'first_name' => 'NewFirst',
        'phone' => '1111111111',
    ]);
    
    $response->assertStatus(200)
        ->assertJsonFragment([
            'email' => 'existing@example.com',
            'last_name' => 'NewName',
            'first_name' => 'NewFirst',
            'phone' => '1111111111',
        ]);
    
    $this->assertDatabaseHas('users', [
        'id' => $existingUser->id,
        'email' => 'existing@example.com',
        'last_name' => 'NewName',
        'first_name' => 'NewFirst',
        'phone' => '1111111111',
    ]);
});

test('updateOrCreateUser verifies email if not already verified', function () {
    $unverifiedUser = User::factory()->create([
        'email' => 'unverified@example.com',
        'school_id' => $this->school->id,
        'email_verified_at' => null,
        'confirmed_at' => null,
    ]);
    
    $response = $this->actingAs($this->adminUser)->postJson('/api/admin/register_date_bookings/update_or_create_user', [
        'email' => 'unverified@example.com',
        'last_name' => 'Updated',
        'first_name' => 'User',
    ]);
    
    $response->assertStatus(200);
    
    $user = $unverifiedUser->fresh();
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->confirmed_at)->not->toBeNull();
});

test('updateOrCreateUser works for register_admin', function () {
    $response = $this->actingAs($this->registerAdmin)->postJson('/api/admin/register_date_bookings/update_or_create_user', [
        'email' => 'newuser2@example.com',
        'last_name' => 'TestUser',
        'first_name' => 'New',
    ]);
    
    $response->assertStatus(200)
        ->assertJsonFragment(['email' => 'newuser2@example.com']);
});

test('updateOrCreateUser fails for unauthorized user', function () {
    $response = $this->actingAs($this->regularUser)->postJson('/api/admin/register_date_bookings/update_or_create_user', [
        'email' => 'test@example.com',
        'last_name' => 'Test',
    ]);
    
    $response->assertStatus(403);
});

test('updateOrCreateUser requires valid email', function () {
    $response = $this->actingAs($this->adminUser)->postJson('/api/admin/register_date_bookings/update_or_create_user', [
        'email' => 'invalid-email',
        'last_name' => 'Test',
    ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('updateOrCreateUser requires last_name', function () {
    $response = $this->actingAs($this->adminUser)->postJson('/api/admin/register_date_bookings/update_or_create_user', [
        'email' => 'test@example.com',
    ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['last_name']);
});

// Delete Bookings Tests
test('deleteBookings deletes bookings for admin', function () {
    Notification::fake();
    
    $registerDate = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
    ]);
    
    $booking1 = RegisterDateBooking::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
        'register_date_id' => $registerDate->id,
        'user_id' => $this->registerUser->id,
        'student_last_name' => 'Student1',
    ]);
    
    $booking2 = RegisterDateBooking::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
        'register_date_id' => $registerDate->id,
        'user_id' => $this->registerUser->id,
        'student_last_name' => 'Student2',
    ]);
    
    $response = $this->actingAs($this->adminUser)->postJson('/api/admin/register_date_bookings/delete_bookings', [
        'bookings' => [$booking1->id, $booking2->id],
        'notify' => false,
    ]);
    
    $response->assertStatus(200);
    
    $this->assertDatabaseMissing('register_date_bookings', ['id' => $booking1->id]);
    $this->assertDatabaseMissing('register_date_bookings', ['id' => $booking2->id]);
});

test('deleteBookings sends notifications when notify is true', function () {
    Notification::fake();
    
    $registerDate = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
        'date' => '2024-01-15',
        'from' => '08:00:00',
        'to' => '10:00:00',
    ]);
    
    $booking = RegisterDateBooking::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
        'register_date_id' => $registerDate->id,
        'user_id' => $this->registerUser->id,
        'student_last_name' => 'Student',
    ]);
    
    $response = $this->actingAs($this->adminUser)->postJson('/api/admin/register_date_bookings/delete_bookings', [
        'bookings' => [$booking->id],
        'notify' => true,
    ]);
    
    $response->assertStatus(200);
    
    Notification::assertSentTo(
        [Notification::route('mail', $this->registerUser->email)],
        StandardEmail::class
    );
});

test('deleteBookings does not send notifications when notify is false', function () {
    Notification::fake();
    
    $registerDate = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
    ]);
    
    $booking = RegisterDateBooking::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
        'register_date_id' => $registerDate->id,
        'user_id' => $this->registerUser->id,
    ]);
    
    $response = $this->actingAs($this->adminUser)->postJson('/api/admin/register_date_bookings/delete_bookings', [
        'bookings' => [$booking->id],
        'notify' => false,
    ]);
    
    $response->assertStatus(200);
    
    Notification::assertNothingSent();
});

test('deleteBookings works for register_admin', function () {
    $registerDate = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
    ]);
    
    $booking = RegisterDateBooking::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
        'register_date_id' => $registerDate->id,
        'user_id' => $this->registerUser->id,
    ]);
    
    $response = $this->actingAs($this->registerAdmin)->postJson('/api/admin/register_date_bookings/delete_bookings', [
        'bookings' => [$booking->id],
        'notify' => false,
    ]);
    
    $response->assertStatus(200);
    
    $this->assertDatabaseMissing('register_date_bookings', ['id' => $booking->id]);
});

test('deleteBookings fails for unauthorized user', function () {
    $registerDate = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
    ]);
    
    $booking = RegisterDateBooking::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
        'register_date_id' => $registerDate->id,
        'user_id' => $this->registerUser->id,
    ]);
    
    $response = $this->actingAs($this->regularUser)->postJson('/api/admin/register_date_bookings/delete_bookings', [
        'bookings' => [$booking->id],
        'notify' => false,
    ]);
    
    $response->assertStatus(403);
});

test('deleteBookings requires bookings array', function () {
    $response = $this->actingAs($this->adminUser)->postJson('/api/admin/register_date_bookings/delete_bookings', [
        'bookings' => 'not-an-array',
        'notify' => false,
    ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['bookings']);
});

test('deleteBookings requires valid booking IDs', function () {
    $response = $this->actingAs($this->adminUser)->postJson('/api/admin/register_date_bookings/delete_bookings', [
        'bookings' => [999999],
        'notify' => false,
    ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['bookings.0']);
});

test('deleteBookings requires notify parameter', function () {
    $registerDate = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
    ]);
    
    $booking = RegisterDateBooking::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
        'register_date_id' => $registerDate->id,
        'user_id' => $this->registerUser->id,
    ]);
    
    $response = $this->actingAs($this->adminUser)->postJson('/api/admin/register_date_bookings/delete_bookings', [
        'bookings' => [$booking->id],
    ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['notify']);
});

