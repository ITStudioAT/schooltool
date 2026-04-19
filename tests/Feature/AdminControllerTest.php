<?php

/**
 * AdminController Tests
 *
 * These tests cover the core functionality of the AdminController including:
 * - Configuration endpoint
 * - Login flow
 * - Logout functionality
 * - Role management
 *
 * Note: Some registration and password reset flows require mocking due to
 * database constraints in the test environment.
 */

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
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
    ]);

    // Create roles
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    // Create test user
    $this->user = User::factory()->create([
        'email' => 'test@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'password' => Hash::make('password123'),
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'confirmed_at' => now(),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    // Assign role to user
    $this->user->assignRole('admin');
});

// Config Tests
test('config returns json response with app configuration', function () {
    $response = $this->getJson('/api/admin/config');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'logo',
            'copyright',
            'title',
            'company',
            'version',
            'timeout',
            'is_auth',
            'user',
            'selected_school',
            'selected_schoolyear',
            'selected_register',
            'menu',
            'capabilities',
            'roles',
            'health',
        ]);
});

test('config returns correct app information', function () {
    $response = $this->getJson('/api/admin/config');

    $response->assertStatus(200)
        ->assertJson([
            'title' => 'SchoolTool',
            'company' => 'ITStudio Dipl.-Ing. Günther Kron',
            'is_auth' => false,
        ]);
});

test('config returns user data when authenticated', function () {
    $this->actingAs($this->user);

    $response = $this->getJson('/api/admin/config');

    $response->assertStatus(200)
        ->assertJson([
            'is_auth' => true,
        ])
        ->assertJsonStructure([
            'user' => ['id', 'email', 'first_name', 'last_name'],
        ]);
});

test('config returns selected school when user has one', function () {
    $this->actingAs($this->user);

    $response = $this->getJson('/api/admin/config');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'selected_school' => ['id', 'long_name'],
        ]);
});

test('config returns empty menu for unauthenticated user', function () {
    $response = $this->getJson('/api/admin/config');

    $response->assertStatus(200)
        ->assertJson([
            'menu' => [],
            'roles' => [],
            'capabilities' => [
                'home' => false,
                'profile' => false,
                'users' => false,
                'user_roles' => false,
                'super_admin' => false,
                'register_system' => false,
                'tutoring' => false,
                'teaching' => false,
                'materials' => false,
                'groups' => false,
                'restaurant' => false,
                'aba' => false,
            ],
        ]);
});

test('config includes health check data', function () {
    $response = $this->getJson('/api/admin/config');

    $response->assertStatus(200)
        ->assertJsonPath('health.queue_working', true);
});

// Login Tests
test('login step email returns correct data for valid email', function () {
    $data = [
        'data' => [
            'step' => 'LOGIN_ENTER_EMAIL',
            'email' => $this->user->email,
        ],
    ];

    $response = $this->postJson('/api/admin/login_step_email', $data);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'users_count',
        ]);
});

test('login step email rejects non-existent email', function () {
    $data = [
        'data' => [
            'step' => 'LOGIN_ENTER_EMAIL',
            'email' => 'nonexistent@example.com',
        ],
    ];

    $response = $this->postJson('/api/admin/login_step_email', $data);

    $response->assertStatus(401);
});

test('login step 2 authenticates user with correct credentials', function () {
    $data = [
        'data' => [
            'step' => 'LOGIN_ENTER_PASSWORD',
            'email' => $this->user->email,
            'password' => 'password123',
            'remember' => true,
            'school' => [
                'id' => $this->school->id,
            ],
        ],
    ];

    $response = $this->postJson('/api/admin/login_step_2', $data);

    $response->assertStatus(200)
        ->assertJsonStructure(['step']);
});

test('login step 2 rejects incorrect password', function () {
    $data = [
        'data' => [
            'step' => 'LOGIN_ENTER_PASSWORD',
            'email' => $this->user->email,
            'password' => 'wrongpassword',
            'school' => [
                'id' => $this->school->id,
            ],
        ],
    ];

    $response = $this->postJson('/api/admin/login_step_2', $data);

    $response->assertStatus(401);
});

test('login step 2 handles 2fa enabled users', function () {
    $this->user->is_2fa = true;
    $this->user->email_2fa = 'second@example.com';
    $this->user->save();

    $data = [
        'data' => [
            'step' => 'LOGIN_ENTER_PASSWORD',
            'email' => $this->user->email,
            'password' => 'password123',
            'remember' => true,
            'school' => [
                'id' => $this->school->id,
                'long_name' => $this->school->long_name,
            ],
        ],
    ];

    $response = $this->postJson('/api/admin/login_step_2', $data);

    $response->assertStatus(200)
        ->assertJson([
            'step' => 'LOGIN_ENTER_TOKEN',
        ]);
});

test('login step 2 keeps remember token empty when remember is false', function () {
    $this->user->remember_token = null;
    $this->user->save();
    $this->user->refresh();

    expect($this->user->remember_token)->toBeNull();

    $data = [
        'data' => [
            'step' => 'LOGIN_ENTER_PASSWORD',
            'email' => $this->user->email,
            'password' => 'password123',
            'remember' => false,
            'school' => [
                'id' => $this->school->id,
            ],
        ],
    ];

    $response = $this->postJson('/api/admin/login_step_2', $data);

    $response->assertStatus(200)
        ->assertJson([
            'step' => 'LOGIN_SUCCESS',
        ]);

    $this->user->refresh();
    expect($this->user->remember_token)->toBeNull();
});

test('login step 3 sets remember token when remember is true', function () {
    $this->user->is_2fa = true;
    $this->user->email_2fa = 'second@example.com';
    $this->user->save();

    $step2Data = [
        'data' => [
            'step' => 'LOGIN_ENTER_PASSWORD',
            'email' => $this->user->email,
            'password' => 'password123',
            'remember' => true,
            'school' => [
                'id' => $this->school->id,
                'long_name' => $this->school->long_name,
            ],
        ],
    ];

    $step2Response = $this->postJson('/api/admin/login_step_2', $step2Data);
    $step2Response->assertStatus(200)
        ->assertJson([
            'step' => 'LOGIN_ENTER_TOKEN',
        ]);

    $this->user->refresh();
    expect($this->user->token_2fa)->not->toBeNull();

    $step3Data = [
        'data' => [
            'step' => 'LOGIN_ENTER_TOKEN',
            'email' => $this->user->email,
            'password' => 'password123',
            'token_2fa' => $this->user->token_2fa,
            'remember' => true,
            'school' => [
                'id' => $this->school->id,
            ],
        ],
    ];

    $step3Response = $this->postJson('/api/admin/login_step_3', $step3Data);

    $step3Response->assertStatus(200)
        ->assertJson([
            'step' => 'LOGIN_SUCCESS',
            'auth' => true,
        ]);

    $this->user->refresh();
    expect($this->user->remember_token)->not->toBeNull();
});

// Logout Tests
test('execute logout logs out authenticated user', function () {
    $this->actingAs($this->user);

    expect(Auth::check())->toBeTrue();

    $response = $this->postJson('/api/admin/execute_logout');

    $response->assertStatus(200)
        ->assertJson([
            'is_auth' => false,
        ]);
});

test('execute logout works for unauthenticated user', function () {
    $response = $this->postJson('/api/admin/execute_logout');

    $response->assertStatus(200)
        ->assertJson([
            'is_auth' => false,
        ]);
});

test('execute logout returns config data', function () {
    $this->actingAs($this->user);

    $response = $this->postJson('/api/admin/execute_logout');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'logo',
            'copyright',
            'title',
            'is_auth',
            'menu',
        ]);
});

// Load Roles Tests
test('load roles returns roles for super admin', function () {
    if (! Role::where('name', 'super_admin')->exists()) {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    Role::firstOrCreate(['name' => 'custom_admin_shell', 'guard_name' => 'web', 'is_admin' => true]);

    $this->user->syncRoles(['super_admin']);
    $this->actingAs($this->user);

    $response = $this->postJson('/api/admin/load_roles');

    $response->assertStatus(200)
        ->assertJsonStructure([
            '*' => ['id', 'name', 'is_admin'],
        ]);

    expect(collect($response->json())->firstWhere('name', 'custom_admin_shell')['is_admin'])->toBeTrue();
});

test('load roles denies access for non-super admin', function () {
    $this->actingAs($this->user);

    $response = $this->postJson('/api/admin/load_roles');

    $response->assertStatus(403);
});

test('load roles requires authentication', function () {
    $response = $this->postJson('/api/admin/load_roles');

    $response->assertStatus(401);
});

// Password Reset - Token Validation
test('password reset validates user exists and is active', function () {
    $this->user->token_2fa = '123456';
    $this->user->token_2fa_expires_at = now()->addMinutes(10);
    $this->user->save();

    $data = [
        'data' => [
            'step' => 'PASSWORD_UNKNOWN_ENTER_TOKEN',
            'email' => $this->user->email,
            'school_id' => $this->school->id,
            'token_2fa' => '123456',
        ],
    ];

    $response = $this->postJson('/api/admin/password_unknown_step_token', $data);

    $response->assertStatus(200);
});

// Register Token Validation
test('register step 2 validates token correctly', function () {
    $registerUser = User::factory()->create([
        'email' => 'register@example.com',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_started_at' => now(),
        'token_2fa' => '123456',
        'token_2fa_expires_at' => now()->addMinutes(10),
        'email_verified_at' => null,
    ]);

    $data = [
        'data' => [
            'email' => 'register@example.com',
            'token_2fa' => '123456',
            'step' => 'REGISTER_ENTER_TOKEN',
        ],
    ];

    $response = $this->postJson('/api/admin/register_step_2', $data);

    $response->assertStatus(200)
        ->assertJson([
            'step' => 'REGISTER_ENTER_FIELDS',
        ]);

    $registerUser->refresh();
    expect($registerUser->email_verified_at)->not->toBeNull();
});

test('register step 2 rejects invalid token', function () {
    $registerUser = User::factory()->create([
        'email' => 'register@example.com',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_started_at' => now(),
        'token_2fa' => '123456',
        'token_2fa_expires_at' => now()->addMinutes(10),
    ]);

    $data = [
        'data' => [
            'email' => 'register@example.com',
            'token_2fa' => '999999',
            'step' => 'REGISTER_ENTER_TOKEN',
        ],
    ];

    $response = $this->postJson('/api/admin/register_step_2', $data);

    $response->assertStatus(401);
});

test('register step 3 updates user with valid data', function () {
    $registerUser = User::factory()->create([
        'email' => 'complete@example.com',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_started_at' => now(),
        'token_2fa' => '123456',
        'token_2fa_expires_at' => now()->addMinutes(10),
        'email_verified_at' => now(),
        'first_name' => null,
        'last_name' => null,
        'confirmed_at' => null,
    ]);

    $data = [
        'data' => [
            'email' => 'complete@example.com',
            'token_2fa' => '123456',
            'step' => 'REGISTER_ENTER_FIELDS',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'password' => 'SecurePass123!',
            'password_repeat' => 'SecurePass123!',
        ],
    ];

    $response = $this->postJson('/api/admin/register_step_3', $data);

    $response->assertStatus(200);

    $registerUser->refresh();
    expect($registerUser->last_name)->toBe('Smith')
        ->and($registerUser->first_name)->toBe('Jane');
});

// Authentication State Tests
test('config reflects authentication state correctly', function () {
    // Unauthenticated
    $response = $this->getJson('/api/admin/config');
    $response->assertJson(['is_auth' => false]);

    // Authenticated
    $this->actingAs($this->user);
    $response = $this->getJson('/api/admin/config');
    $response->assertJson(['is_auth' => true]);
});

test('authenticated user config includes navigation menu', function () {
    $this->actingAs($this->user);

    $response = $this->getJson('/api/admin/config');

    $response->assertStatus(200);
    $data = $response->json();

    expect($data)->toHaveKey('menu');
});

test('authenticated user config includes hopper menu below logout when hopper accounts exist', function () {
    $otherSchool = School::factory()->create([
        'long_name' => 'Second School',
        'short_name' => 'SS',
    ]);
    $otherSchoolyear = Schoolyear::factory()->create([
        'school_id' => $otherSchool->id,
    ]);

    $targetUser = User::factory()->create([
        'email' => 'hopper@example.com',
        'first_name' => 'Anna',
        'last_name' => 'Hopper',
        'school_id' => $otherSchool->id,
        'schoolyear_id' => $otherSchoolyear->id,
        'confirmed_at' => now(),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);
    $targetUser->assignRole('admin');

    $this->user->forceFill([
        'hopper_account_ids' => [$targetUser->id],
    ])->save();

    $this->actingAs($this->user);

    $response = $this->getJson('/api/admin/config');

    $response->assertSuccessful();

    $menu = $response->json('menu');
    $logoutIndex = collect($menu)->search(fn (array $item) => ($item['title'] ?? null) === 'Abmelden');
    $hopperIndex = collect($menu)->search(fn (array $item) => ($item['title'] ?? null) === 'Hopp');
    $hopperItem = collect($menu)->firstWhere('title', 'Hopp');

    expect($logoutIndex)->not->toBeFalse()
        ->and($hopperIndex)->toBe($logoutIndex + 1)
        ->and($hopperItem)->not->toBeNull()
        ->and($hopperItem['children'][0]['title'])->toBe('Second School')
        ->and($hopperItem['children'][0]['subtitle'])->toBe('Anna Hopper • hopper@example.com')
        ->and($hopperItem['children'][0]['click'])->toBe('switchHopperAccount')
        ->and($hopperItem['children'][0]['target_user_id'])->toBe($targetUser->id);
});

test('authenticated admin config includes backend route capabilities', function () {
    $this->actingAs($this->user);

    $response = $this->getJson('/api/admin/config');

    $response->assertStatus(200)
        ->assertJsonPath('capabilities.home', true)
        ->assertJsonPath('capabilities.profile', true)
        ->assertJsonPath('capabilities.users', true)
        ->assertJsonPath('capabilities.super_admin', true)
        ->assertJsonPath('capabilities.register_system', false)
        ->assertJsonPath('capabilities.tutoring', false)
        ->assertJsonPath('capabilities.teaching', false)
        ->assertJsonPath('capabilities.materials', false)
        ->assertJsonPath('capabilities.groups', false)
        ->assertJsonPath('capabilities.restaurant', false)
        ->assertJsonPath('capabilities.aba', false);
});
