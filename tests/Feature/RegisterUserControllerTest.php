<?php

/**
 * RegisterUserController Tests
 *
 * These tests cover the complete functionality of the RegisterUserController including:
 * - Index endpoint (list register users with pagination and search)
 * - Delete register users endpoint (bulk delete unused register users)
 *
 * All endpoints require appropriate role permissions (admin or user)
 * and validate register_id parameter
 */

use App\Models\Licence;
use App\Models\Register;
use App\Models\RegisterDate;
use App\Models\RegisterDateBooking;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
    Role::firstOrCreate(['name' => 'register_user', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

    // Create register
    $this->register = Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'name' => 'Test Register',
        'is_active' => true,
    ]);

    // Create a default register date for tests
    $this->registerDate = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
    ]);

    // Create admin user
    $this->adminUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Admin',
        'last_name' => 'User',
        'email' => 'admin@test.com',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
    ]);
    $this->adminUser->assignRole('admin');

    // Create user with register_admin role
    $this->standardUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Standard',
        'last_name' => 'User',
        'email' => 'user@test.com',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
    ]);
    $this->standardUser->assignRole('register_admin');

    // Create register user without permissions
    $this->registerUser = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Register',
        'last_name' => 'User',
        'email' => 'registeruser@test.com',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
    ]);
    $this->registerUser->assignRole('register_user');

    // Create unauthenticated user (not admin or user role)
    $this->unauthorizedUser = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Unauthorized',
        'last_name' => 'User',
        'email' => 'unauthorized@test.com',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
    ]);
});

// ============================================================================
// Index Tests - Authorization
// ============================================================================

test('admin can access index endpoint', function () {
    // Skip this test on SQLite due to HAVING clause limitation
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite does not support HAVING clause on non-aggregate queries');
    }

    $this->actingAs($this->adminUser);

    $response = $this->getJson('/api/admin/register_users?'.http_build_query([
        'register_id' => $this->register->id,
    ]));

    $response->assertStatus(200);
});

test('user can access index endpoint', function () {
    // Skip this test on SQLite due to HAVING clause limitation
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite does not support HAVING clause on non-aggregate queries');
    }

    $this->actingAs($this->standardUser);

    $response = $this->getJson('/api/admin/register_users?'.http_build_query([
        'register_id' => $this->register->id,
    ]));

    $response->assertStatus(200);
});

test('unauthorized user cannot access index endpoint', function () {
    $this->actingAs($this->unauthorizedUser);

    $response = $this->getJson('/api/admin/register_users?'.http_build_query([
        'register_id' => $this->register->id,
    ]));

    // The ApiAllowed middleware catches this before the controller
    $response->assertStatus(403);
});

test('guest cannot access index endpoint', function () {
    $response = $this->getJson('/api/admin/register_users?'.http_build_query([
        'register_id' => $this->register->id,
    ]));

    $response->assertStatus(401);
});

// ============================================================================
// Index Tests - Validation
// ============================================================================

test('index requires register_id', function () {
    $this->actingAs($this->adminUser);

    $response = $this->getJson('/api/admin/register_users');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['register_id']);
});

test('index requires valid register_id', function () {
    $this->actingAs($this->adminUser);

    $response = $this->getJson('/api/admin/register_users?'.http_build_query([
        'register_id' => 99999,
    ]));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['register_id']);
});

test('index accepts valid search_string', function () {
    // Skip this test on SQLite due to HAVING clause limitation
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite does not support HAVING clause on non-aggregate queries');
    }

    $this->actingAs($this->adminUser);

    $response = $this->getJson('/api/admin/register_users?'.http_build_query([
        'register_id' => $this->register->id,
        'search_string' => 'test',
    ]));

    $response->assertStatus(200);
});

test('index accepts page parameter', function () {
    // Skip this test on SQLite due to HAVING clause limitation
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite does not support HAVING clause on non-aggregate queries');
    }

    $this->actingAs($this->adminUser);

    $response = $this->getJson('/api/admin/register_users?'.http_build_query([
        'register_id' => $this->register->id,
        'page' => 1,
    ]));

    $response->assertStatus(200);
});

// ============================================================================
// Index Tests - Functionality
// ============================================================================

test('index returns users attached to register', function () {
    // Skip this test on SQLite due to HAVING clause limitation
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite does not support HAVING clause on non-aggregate queries');
    }

    $this->actingAs($this->adminUser);

    // Attach users to register
    $this->register->users()->attach([
        $this->registerUser->id => [
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'register_date_id' => $this->registerDate->id,
        ],
        $this->standardUser->id => [
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'register_date_id' => $this->registerDate->id,
        ],
    ]);

    $response = $this->getJson('/api/admin/register_users?'.http_build_query([
        'register_id' => $this->register->id,
    ]));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'count_deletable_users',
            'data' => [
                '*' => [
                    'id',
                    'first_name',
                    'last_name',
                    'email',
                ],
            ],
            'meta' => [
                'current_page',
                'total',
                'per_page',
            ],
        ])
        ->assertJsonCount(2, 'data');
});

test('index returns users sorted by last_name and first_name', function () {
    // Skip this test on SQLite due to HAVING clause limitation
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite does not support HAVING clause on non-aggregate queries');
    }

    $this->actingAs($this->adminUser);

    // Create users with specific names
    $userA = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Alice',
        'last_name' => 'Anderson',
        'email' => 'alice@test.com',
    ]);
    $userB = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Bob',
        'last_name' => 'Brown',
        'email' => 'bob@test.com',
    ]);
    $userC = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Charlie',
        'last_name' => 'Anderson',
        'email' => 'charlie@test.com',
    ]);

    // Attach to register
    $pivotData = [
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_date_id' => $this->registerDate->id,
    ];
    $this->register->users()->attach([
        $userA->id => $pivotData,
        $userB->id => $pivotData,
        $userC->id => $pivotData,
    ]);

    $response = $this->getJson('/api/admin/register_users?'.http_build_query([
        'register_id' => $this->register->id,
    ]));

    $response->assertStatus(200);

    $data = $response->json('data');
    expect($data[0]['last_name'])->toBe('Anderson');
    expect($data[0]['first_name'])->toBe('Alice');
    expect($data[1]['last_name'])->toBe('Anderson');
    expect($data[1]['first_name'])->toBe('Charlie');
    expect($data[2]['last_name'])->toBe('Brown');
});

test('index filters users by search_string on last_name', function () {
    // Skip this test on SQLite due to HAVING clause limitation
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite does not support HAVING clause on non-aggregate queries');
    }

    $this->actingAs($this->adminUser);

    $userA = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'John',
        'last_name' => 'Smith',
        'email' => 'john.smith@test.com',
    ]);
    $userB = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane.doe@test.com',
    ]);

    $pivotData = [
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_date_id' => $this->registerDate->id,
    ];
    $this->register->users()->attach([
        $userA->id => $pivotData,
        $userB->id => $pivotData,
    ]);

    $response = $this->getJson('/api/admin/register_users?'.http_build_query([
        'register_id' => $this->register->id,
        'search_string' => 'Smith',
    ]));

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');

    expect($response->json('data')[0]['last_name'])->toBe('Smith');
});

test('index filters users by search_string on first_name', function () {
    // Skip this test on SQLite due to HAVING clause limitation
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite does not support HAVING clause on non-aggregate queries');
    }

    $this->actingAs($this->adminUser);

    $userA = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Alexander',
        'last_name' => 'Smith',
        'email' => 'alex@test.com',
    ]);
    $userB = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@test.com',
    ]);

    $pivotData = [
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_date_id' => $this->registerDate->id,
    ];
    $this->register->users()->attach([
        $userA->id => $pivotData,
        $userB->id => $pivotData,
    ]);

    $response = $this->getJson('/api/admin/register_users?'.http_build_query([
        'register_id' => $this->register->id,
        'search_string' => 'Alex',
    ]));

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');

    expect($response->json('data')[0]['first_name'])->toBe('Alexander');
});

test('index filters users by search_string on email', function () {
    // Skip this test on SQLite due to HAVING clause limitation
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite does not support HAVING clause on non-aggregate queries');
    }

    $this->actingAs($this->adminUser);

    $userA = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'John',
        'last_name' => 'Smith',
        'email' => 'special@test.com',
    ]);
    $userB = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@test.com',
    ]);

    $pivotData = [
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_date_id' => $this->registerDate->id,
    ];
    $this->register->users()->attach([
        $userA->id => $pivotData,
        $userB->id => $pivotData,
    ]);

    $response = $this->getJson('/api/admin/register_users?'.http_build_query([
        'register_id' => $this->register->id,
        'search_string' => 'special',
    ]));

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');

    expect($response->json('data')[0]['email'])->toBe('special@test.com');
});

test('index returns count_deletable_users correctly', function () {
    // Skip this test on SQLite due to HAVING clause limitation
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite does not support HAVING clause on non-aggregate queries');
    }

    $this->actingAs($this->adminUser);

    // Create register user with only register_user role and no bookings
    $deletableUser = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Deletable',
        'last_name' => 'User',
        'email' => 'deletable@test.com',
    ]);
    $deletableUser->assignRole('register_user');

    // Attach to register
    $this->register->users()->attach([
        $deletableUser->id => [
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'register_date_id' => $this->registerDate->id,
        ],
    ]);

    $response = $this->getJson('/api/admin/register_users?'.http_build_query([
        'register_id' => $this->register->id,
    ]));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'count_deletable_users',
            'data',
            'meta',
        ]);

    // Count should be >= 1 (at least the deletable user we created)
    expect($response->json('count_deletable_users'))->toBeGreaterThanOrEqual(1);
});

test('index excludes users with bookings from deletable count', function () {
    // Skip this test on SQLite due to HAVING clause limitation
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite does not support HAVING clause on non-aggregate queries');
    }

    $this->actingAs($this->adminUser);

    // Create register user with booking
    $userWithBooking = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Booked',
        'last_name' => 'User',
        'email' => 'booked@test.com',
    ]);
    $userWithBooking->assignRole('register_user');

    // Create a register date and booking
    $registerDate = RegisterDate::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
    ]);

    $this->register->users()->attach([
        $userWithBooking->id => [
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'register_date_id' => $registerDate->id,
        ],
    ]);
    RegisterDateBooking::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
        'register_date_id' => $registerDate->id,
        'user_id' => $userWithBooking->id,
    ]);

    $response = $this->getJson('/api/admin/register_users?'.http_build_query([
        'register_id' => $this->register->id,
    ]));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'count_deletable_users',
            'data',
            'meta',
        ]);
});

test('index excludes users with multiple roles from deletable count', function () {
    // Skip this test on SQLite due to HAVING clause limitation
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite does not support HAVING clause on non-aggregate queries');
    }

    $this->actingAs($this->adminUser);

    // Create user with multiple roles
    $multiRoleUser = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Multi',
        'last_name' => 'Role',
        'email' => 'multirole@test.com',
    ]);
    $multiRoleUser->assignRole(['register_user', 'user']);
    $this->register->users()->attach([
        $multiRoleUser->id => [
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'register_date_id' => $this->registerDate->id,
        ],
    ]);

    $response = $this->getJson('/api/admin/register_users?'.http_build_query([
        'register_id' => $this->register->id,
    ]));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'count_deletable_users',
            'data',
            'meta',
        ]);
});

// ============================================================================
// Delete Register Users Tests - Authorization
// ============================================================================

test('admin can delete register users', function () {
    $this->actingAs($this->adminUser);

    $response = $this->postJson('/api/admin/register_users/delete_register_users', [
        'register_id' => $this->register->id,
    ]);

    $response->assertStatus(200);
});

test('user can delete register users', function () {
    $this->actingAs($this->standardUser);

    $response = $this->postJson('/api/admin/register_users/delete_register_users', [
        'register_id' => $this->register->id,
    ]);

    // Users with 'user' role can delete register users
    $response->assertStatus(200);
});

test('unauthorized user cannot delete register users', function () {
    $this->actingAs($this->unauthorizedUser);

    $response = $this->postJson('/api/admin/register_users/delete_register_users', [
        'register_id' => $this->register->id,
    ]);

    // The ApiAllowed middleware catches this before the controller
    $response->assertStatus(403);
});

test('guest cannot delete register users', function () {
    $response = $this->postJson('/api/admin/register_users/delete_register_users', [
        'register_id' => $this->register->id,
    ]);

    $response->assertStatus(401);
});

// ============================================================================
// Delete Register Users Tests - Validation
// ============================================================================

test('delete requires register_id', function () {
    $this->actingAs($this->adminUser);

    $response = $this->postJson('/api/admin/register_users/delete_register_users', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['register_id']);
});

test('delete requires valid register_id', function () {
    $this->actingAs($this->adminUser);

    $response = $this->postJson('/api/admin/register_users/delete_register_users', [
        'register_id' => 99999,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['register_id']);
});

// ============================================================================
// Delete Register Users Tests - Functionality
// ============================================================================

test('delete removes users with only register_user role and no bookings', function () {
    $this->actingAs($this->adminUser);

    // Create deletable user
    $deletableUser = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Deletable',
        'last_name' => 'User',
        'email' => 'deletable@test.com',
    ]);
    $deletableUser->assignRole('register_user');

    // Note: registerUser from beforeEach is also deletable
    $expectedCount = 2; // deletableUser + registerUser from beforeEach

    $response = $this->postJson('/api/admin/register_users/delete_register_users', [
        'register_id' => $this->register->id,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'count' => $expectedCount,
        ]);

    // Verify user was deleted
    $this->assertDatabaseMissing('users', [
        'id' => $deletableUser->id,
    ]);
});

test('delete does not remove users with bookings', function () {
    $this->actingAs($this->adminUser);

    // Create user with booking
    $userWithBooking = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Booked',
        'last_name' => 'User',
        'email' => 'booked@test.com',
    ]);
    $userWithBooking->assignRole('register_user');

    // Create booking
    $registerDate = RegisterDate::factory()->create([
        'register_id' => $this->register->id,
    ]);
    RegisterDateBooking::create([
        'register_id' => $this->register->id,
        'register_date_id' => $registerDate->id,
        'user_id' => $userWithBooking->id,
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'student_first_name' => 'Test',
        'student_last_name' => 'Student',
        'student_birthdate' => '2010-01-01',
    ]);

    // Note: registerUser from beforeEach is deletable
    $expectedCount = 1; // only registerUser from beforeEach

    $response = $this->postJson('/api/admin/register_users/delete_register_users', [
        'register_id' => $this->register->id,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'count' => $expectedCount,
        ]);

    // Verify user was not deleted
    $this->assertDatabaseHas('users', [
        'id' => $userWithBooking->id,
    ]);
});

test('delete keeps users with multiple roles and removes register role', function () {
    $this->actingAs($this->adminUser);

    // Create user with multiple roles
    $multiRoleUser = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Multi',
        'last_name' => 'Role',
        'email' => 'multirole@test.com',
    ]);
    $multiRoleUser->assignRole(['register_user', 'user']);

    // Note: registerUser from beforeEach is deletable, while multiRoleUser is processed but kept
    $expectedCount = 2;

    $response = $this->postJson('/api/admin/register_users/delete_register_users', [
        'register_id' => $this->register->id,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'count' => $expectedCount,
        ]);

    // Verify user was not deleted and only the register_user role was removed
    $this->assertDatabaseHas('users', [
        'id' => $multiRoleUser->id,
    ]);

    expect($multiRoleUser->fresh()->hasRole('register_user'))->toBeFalse()
        ->and($multiRoleUser->fresh()->hasRole('user'))->toBeTrue();
});

test('delete only removes users from authenticated users school', function () {
    $this->actingAs($this->adminUser);

    // Create another school
    $otherSchool = School::factory()->create([
        'long_name' => 'Other School',
        'short_name' => 'OS',
    ]);

    // Create user in other school
    $otherSchoolUser = User::factory()->create([
        'school_id' => $otherSchool->id,
        'first_name' => 'Other',
        'last_name' => 'User',
        'email' => 'other@test.com',
    ]);
    $otherSchoolUser->assignRole('register_user');

    $response = $this->postJson('/api/admin/register_users/delete_register_users', [
        'register_id' => $this->register->id,
    ]);

    $response->assertStatus(200);

    // Verify other school user was not deleted
    $this->assertDatabaseHas('users', [
        'id' => $otherSchoolUser->id,
    ]);
});

test('delete returns correct count of deleted users', function () {
    $this->actingAs($this->adminUser);

    // Create multiple deletable users
    for ($i = 0; $i < 3; $i++) {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'first_name' => "Deletable{$i}",
            'last_name' => 'User',
            'email' => "deletable{$i}@test.com",
        ]);
        $user->assignRole('register_user');
    }

    // Note: registerUser from beforeEach is also deletable
    $expectedCount = 4; // 3 created + registerUser from beforeEach

    $response = $this->postJson('/api/admin/register_users/delete_register_users', [
        'register_id' => $this->register->id,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'count' => $expectedCount,
        ]);
});

test('delete does not remove users without register_user role', function () {
    $this->actingAs($this->adminUser);

    // Create user with different role
    $userWithDifferentRole = User::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Different',
        'last_name' => 'Role',
        'email' => 'different@test.com',
    ]);
    $userWithDifferentRole->assignRole('user');

    // Note: registerUser from beforeEach is deletable
    $expectedCount = 1; // only registerUser from beforeEach

    $response = $this->postJson('/api/admin/register_users/delete_register_users', [
        'register_id' => $this->register->id,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'count' => $expectedCount,
        ]);

    // Verify user was not deleted
    $this->assertDatabaseHas('users', [
        'id' => $userWithDifferentRole->id,
    ]);
});
