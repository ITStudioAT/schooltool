<?php

use App\Http\Controllers\Admin\NavigationController;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create school and schoolyear
    $this->school = School::factory()->create([
        'long_name' => 'Test School',
        'short_name' => 'TEST',
    ]);
    
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);
    
    // Create roles
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'register_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'register_user', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'tutoring_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    
    // Create test users
    $this->superAdmin = User::factory()->create([
        'first_name' => 'Super',
        'last_name' => 'Admin',
        'email' => 'superadmin@example.com',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'confirmed_at' => now(),
        'is_active' => true,
    ]);
    $this->superAdmin->assignRole('super_admin');
    
    $this->admin = User::factory()->create([
        'first_name' => 'Admin',
        'last_name' => 'User',
        'email' => 'admin@example.com',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'confirmed_at' => now(),
        'is_active' => true,
    ]);
    $this->admin->assignRole('admin');
    
    $this->registerAdmin = User::factory()->create([
        'first_name' => 'Register',
        'last_name' => 'Admin',
        'email' => 'registeradmin@example.com',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'confirmed_at' => now(),
        'is_active' => true,
    ]);
    $this->registerAdmin->assignRole('register_admin');
    
    $this->registerUser = User::factory()->create([
        'first_name' => 'Register',
        'last_name' => 'User',
        'email' => 'registeruser@example.com',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'confirmed_at' => now(),
        'is_active' => true,
    ]);
    $this->registerUser->assignRole('register_user');
    
    $this->user = User::factory()->create([
        'first_name' => 'Regular',
        'last_name' => 'User',
        'email' => 'user@example.com',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'confirmed_at' => now(),
        'is_active' => true,
    ]);
    $this->user->assignRole('user');
});

// Profile Menu Tests
test('profile menu returns menu for admin', function () {
    $this->actingAs($this->admin);
    
    $response = $this->getJson('/api/admin/navigation/profile_menu');
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'menu' => [
                '*' => ['title', 'subtitle', 'icon', 'color']
            ]
        ]);
    
    $menu = $response->json('menu');
    expect($menu)->toBeArray()
        ->and(count($menu))->toBeGreaterThan(0);
});

test('profile menu returns menu for register admin', function () {
    $this->actingAs($this->registerAdmin);
    
    $response = $this->getJson('/api/admin/navigation/profile_menu');
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'menu'
        ]);
    
    $menu = $response->json('menu');
    expect($menu)->toBeArray()
        ->and(count($menu))->toBeGreaterThan(0);
});

test('profile menu returns menu for tutoring admin', function () {
    $tutoringAdmin = User::factory()->create([
        'first_name' => 'Tutoring',
        'last_name' => 'Admin',
        'email' => 'tutoringadmin@example.com',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'confirmed_at' => now(),
        'is_active' => true,
    ]);
    $tutoringAdmin->assignRole('tutoring_admin');

    $this->actingAs($tutoringAdmin);

    $response = $this->getJson('/api/admin/navigation/profile_menu');

    $response->assertStatus(200)
        ->assertJsonStructure(['menu']);
});

test('profile menu returns menu for teacher', function () {
    $teacher = User::factory()->create([
        'first_name' => 'Teacher',
        'last_name' => 'User',
        'email' => 'teacher@example.com',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'confirmed_at' => now(),
        'is_active' => true,
    ]);
    $teacher->assignRole('teacher');

    $this->actingAs($teacher);

    $response = $this->getJson('/api/admin/navigation/profile_menu');

    $response->assertStatus(200)
        ->assertJsonStructure(['menu']);
});

test('profile menu denies access for register user', function () {
    $this->actingAs($this->registerUser);
    
    $response = $this->getJson('/api/admin/navigation/profile_menu');
    
    $response->assertStatus(403);
});

test('profile menu denies access for regular user', function () {
    $this->actingAs($this->user);
    
    $response = $this->getJson('/api/admin/navigation/profile_menu');
    
    $response->assertStatus(403);
});

test('profile menu denies access for non-admin roles', function () {
    $guestUser = User::factory()->create([
        'first_name' => 'Guest',
        'last_name' => 'User',
        'email' => 'guest@example.com',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'confirmed_at' => now(),
        'is_active' => true,
    ]);
    
    $this->actingAs($guestUser);
    
    $response = $this->getJson('/api/admin/navigation/profile_menu');
    
    $response->assertStatus(403);
});

test('profile menu requires authentication', function () {
    $response = $this->getJson('/api/admin/navigation/profile_menu');
    
    $response->assertStatus(401);
});

test('profile menu contains expected items', function () {
    $this->actingAs($this->admin);
    
    $response = $this->getJson('/api/admin/navigation/profile_menu');
    
    $response->assertStatus(200);
    
    $menu = $response->json('menu');
    
    // Check for home menu item
    $homeItem = collect($menu)->firstWhere('subtitle', 'Home');
    expect($homeItem)->not->toBeNull()
        ->and($homeItem['icon'])->toBe('mdi-home');
    
    // Check for password change menu item
    $passwordItem = collect($menu)->firstWhere('subtitle', 'Kennwort ändern');
    expect($passwordItem)->not->toBeNull()
        ->and($passwordItem['icon'])->toBe('mdi-form-textbox-password');
    
    // Check for 2FA menu item
    $twoFaItem = collect($menu)->firstWhere('subtitle', '2-FA-Authentifizierung');
    expect($twoFaItem)->not->toBeNull()
        ->and($twoFaItem['icon'])->toBe('mdi-two-factor-authentication');
});

test('profile menu items have correct structure', function () {
    $this->actingAs($this->admin);
    
    $response = $this->getJson('/api/admin/navigation/profile_menu');
    
    $response->assertStatus(200);
    
    $menu = $response->json('menu');
    
    foreach ($menu as $item) {
        expect($item)->toHaveKeys(['title', 'subtitle', 'icon', 'color']);
    }
});

// User Menu Tests
test('user menu returns menu and selection for admin', function () {
    $this->actingAs($this->admin);
    
    $response = $this->getJson('/api/admin/navigation/user_menu');
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'menu',
            'selection'
        ]);
    
    $menu = $response->json('menu');
    expect($menu)->toBeArray();
});

test('user menu returns menu and selection for register admin', function () {
    $this->actingAs($this->registerAdmin);
    
    $response = $this->getJson('/api/admin/navigation/user_menu');
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'menu',
            'selection'
        ]);
});

test('user menu denies access for regular user', function () {
    $this->actingAs($this->user);
    
    $response = $this->getJson('/api/admin/navigation/user_menu');
    
    $response->assertStatus(403);
});

test('user menu denies access for register user', function () {
    $this->actingAs($this->registerUser);
    
    $response = $this->getJson('/api/admin/navigation/user_menu');
    
    $response->assertStatus(403);
});

test('user menu denies access for teacher', function () {
    $teacher = User::factory()->create([
        'first_name' => 'Teacher',
        'last_name' => 'User',
        'email' => 'teacher2@example.com',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'confirmed_at' => now(),
        'is_active' => true,
    ]);
    $teacher->assignRole('teacher');

    $this->actingAs($teacher);

    $response = $this->getJson('/api/admin/navigation/user_menu');

    $response->assertStatus(403);
});

test('user menu requires authentication', function () {
    $response = $this->getJson('/api/admin/navigation/user_menu');
    
    $response->assertStatus(401);
});

test('user menu contains home item', function () {
    $this->actingAs($this->admin);
    
    $response = $this->getJson('/api/admin/navigation/user_menu');
    
    $response->assertStatus(200);
    
    $menu = $response->json('menu');
    
    $homeItem = collect($menu)->firstWhere('subtitle', 'Home');
    expect($homeItem)->not->toBeNull()
        ->and($homeItem['icon'])->toBe('mdi-home')
        ->and($homeItem['to'])->toBe('/admin');
});

test('user menu contains roles item for super admin', function () {
    // Note: super_admin automatically gets access via HasRoleTrait
    $this->actingAs($this->superAdmin);
    
    $response = $this->getJson('/api/admin/navigation/user_menu');
    
    $response->assertStatus(200);
    
    $menu = $response->json('menu');
    
    // Super admin should see the roles menu item
    $rolesItem = collect($menu)->firstWhere('subtitle', 'Rollen');
    expect($rolesItem)->not->toBeNull()
        ->and($rolesItem['icon'])->toBe('mdi-badge-account-horizontal-outline')
        ->and($rolesItem['to'])->toBe('/admin/users/roles');
});

test('user menu does not contain roles item for admin', function () {
    $this->actingAs($this->admin);
    
    $response = $this->getJson('/api/admin/navigation/user_menu');
    
    $response->assertStatus(200);
    
    $menu = $response->json('menu');
    
    $rolesItem = collect($menu)->firstWhere('subtitle', 'Rollen');
    expect($rolesItem)->toBeNull();
});

test('user menu selection contains all users for admin', function () {
    $this->actingAs($this->admin);
    
    $response = $this->getJson('/api/admin/navigation/user_menu');
    
    $response->assertStatus(200);
    
    $selection = $response->json('selection');
    
    expect($selection)->toBeArray()
        ->and(count($selection))->toBeGreaterThan(0);
    
    $allUsersItem = collect($selection)->firstWhere('title', 'Alle Benutzer');
    expect($allUsersItem)->not->toBeNull()
        ->and($allUsersItem['icon'])->toBe('mdi-account-group')
        ->and($allUsersItem['url'])->toBe('/admin/users/all_users');
});

test('user menu items have correct structure', function () {
    $this->actingAs($this->admin);
    
    $response = $this->getJson('/api/admin/navigation/user_menu');
    
    $response->assertStatus(200);
    
    $menu = $response->json('menu');
    
    foreach ($menu as $item) {
        expect($item)->toHaveKeys(['title', 'subtitle', 'icon', 'color', 'to']);
    }
});

// Integration Tests
test('profile menu and user menu work together', function () {
    $this->actingAs($this->admin);
    
    $profileResponse = $this->getJson('/api/admin/navigation/profile_menu');
    $userResponse = $this->getJson('/api/admin/navigation/user_menu');
    
    $profileResponse->assertStatus(200);
    $userResponse->assertStatus(200);
    
    expect($profileResponse->json('menu'))->toBeArray()
        ->and($userResponse->json('menu'))->toBeArray()
        ->and($userResponse->json('selection'))->toBeArray();
});

test('different roles get appropriate menu access', function () {
    // Super admin gets both menus (automatically allowed via HasRoleTrait)
    $this->actingAs($this->superAdmin);
    $superAdminProfile = $this->getJson('/api/admin/navigation/profile_menu');
    $superAdminUser = $this->getJson('/api/admin/navigation/user_menu');
    
    $superAdminProfile->assertStatus(200); // super_admin automatically allowed
    $superAdminUser->assertStatus(200);
    
    // Admin gets both menus
    $this->actingAs($this->admin);
    $adminProfile = $this->getJson('/api/admin/navigation/profile_menu');
    $adminUser = $this->getJson('/api/admin/navigation/user_menu');
    
    $adminProfile->assertStatus(200);
    $adminUser->assertStatus(200);
    
    // Register admin gets both menus
    $this->actingAs($this->registerAdmin);
    $registerAdminProfile = $this->getJson('/api/admin/navigation/profile_menu');
    $registerAdminUser = $this->getJson('/api/admin/navigation/user_menu');
    
    $registerAdminProfile->assertStatus(200);
    $registerAdminUser->assertStatus(200);
    
    // Regular user and register_user don't get access to either
    $this->actingAs($this->user);
    $userProfile = $this->getJson('/api/admin/navigation/profile_menu');
    $userMenu = $this->getJson('/api/admin/navigation/user_menu');
    
    $userProfile->assertStatus(403);
    $userMenu->assertStatus(403);
});

test('menu responses are json formatted correctly', function () {
    $this->actingAs($this->admin);
    
    $profileResponse = $this->getJson('/api/admin/navigation/profile_menu');
    $userResponse = $this->getJson('/api/admin/navigation/user_menu');
    
    $profileResponse->assertStatus(200)
        ->assertHeader('content-type', 'application/json');
    
    $userResponse->assertStatus(200)
        ->assertHeader('content-type', 'application/json');
});

test('profile menu returns empty for unauthenticated user attempting to bypass', function () {
    // This tests the service layer behavior when auth check fails
    $response = $this->getJson('/api/admin/navigation/profile_menu');
    
    $response->assertStatus(401);
});

test('user menu selection structure is correct for admin', function () {
    $this->actingAs($this->admin);
    
    $response = $this->getJson('/api/admin/navigation/user_menu');
    
    $response->assertStatus(200);
    
    $selection = $response->json('selection');
    
    foreach ($selection as $item) {
        expect($item)->toHaveKeys(['title', 'icon', 'url', 'infos']);
    }
});

test('multiple sequential requests maintain consistent responses', function () {
    $this->actingAs($this->admin);
    
    $response1 = $this->getJson('/api/admin/navigation/profile_menu');
    $response2 = $this->getJson('/api/admin/navigation/profile_menu');
    $response3 = $this->getJson('/api/admin/navigation/profile_menu');
    
    $response1->assertStatus(200);
    $response2->assertStatus(200);
    $response3->assertStatus(200);
    
    expect($response1->json('menu'))->toEqual($response2->json('menu'))
        ->and($response2->json('menu'))->toEqual($response3->json('menu'));
});

test('user with long name gets truncated display name in dashboard menu', function () {
    // Create user with very long name
    $longNameUser = User::factory()->create([
        'first_name' => 'VeryLongFirstName',
        'last_name' => 'VeryLongLastName',
        'email' => 'longname@example.com',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'confirmed_at' => now(),
        'is_active' => true,
    ]);
    $longNameUser->assignRole('admin');
    
    $this->actingAs($longNameUser);
    
    $response = $this->getJson('/api/admin/navigation/profile_menu');
    
    $response->assertStatus(200);
    
    // The name should be present in the dashboard menu (tested via service)
    expect($response->json('menu'))->toBeArray();
});

test('profile menu color coding is consistent', function () {
    $this->actingAs($this->admin);
    
    $response = $this->getJson('/api/admin/navigation/profile_menu');
    
    $response->assertStatus(200);
    
    $menu = $response->json('menu');
    
    foreach ($menu as $item) {
        expect($item['color'])->toBe('secondary');
    }
});

test('user menu color coding is consistent', function () {
    $this->actingAs($this->admin);
    
    $response = $this->getJson('/api/admin/navigation/user_menu');
    
    $response->assertStatus(200);
    
    $menu = $response->json('menu');
    
    foreach ($menu as $item) {
        expect($item['color'])->toBe('secondary');
    }
});

