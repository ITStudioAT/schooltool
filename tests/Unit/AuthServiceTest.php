<?php

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new AuthService();
});

describe('getAuth', function () {
    it('returns is_auth false when user is not authenticated', function () {
        Auth::logout();

        $result = $this->service->getAuth();

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('is_auth')
            ->and($result['is_auth'])->toBeFalse()
            ->and($result)->not->toHaveKey('user')
            ->and($result)->not->toHaveKey('roles');
    });

    it('returns is_auth true when user is authenticated', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        Auth::login($user);

        $result = $this->service->getAuth();

        expect($result)->toBeArray()
            ->and($result['is_auth'])->toBeTrue()
            ->and($result)->toHaveKey('user')
            ->and($result)->toHaveKey('roles');
    });

    it('returns user data with correct structure when authenticated', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'sex' => 'm',
            'phone' => '1234567890',
            'schoolclass' => '10A',
        ]);

        Auth::login($user);

        $result = $this->service->getAuth();
        $userData = $result['user']->resolve();

        expect($userData)->toBeArray()
            ->and($userData['id'])->toBe($user->id)
            ->and($userData['email'])->toBe('test@example.com')
            ->and($userData['first_name'])->toBe('John')
            ->and($userData['last_name'])->toBe('Doe')
            ->and($userData['sex'])->toBe('m')
            ->and($userData['phone'])->toBe('1234567890')
            ->and($userData['schoolclass'])->toBe('10A');
    });

    it('returns user with default tutoring_filter when not set', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'tutoring_filter' => null,
        ]);

        Auth::login($user);

        $result = $this->service->getAuth();
        $userData = $result['user']->resolve();

        expect($userData['tutoring_filter'])->toBeArray()
            ->and($userData['tutoring_filter']['only_boys'])->toBeFalse()
            ->and($userData['tutoring_filter']['only_girls'])->toBeFalse()
            ->and($userData['tutoring_filter']['only_in_my_school'])->toBeTrue();
    });

    it('returns user with custom tutoring_filter when set', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'tutoring_filter' => [
                'only_boys' => true,
                'only_girls' => false,
                'only_in_my_school' => false,
            ],
        ]);

        Auth::login($user);

        $result = $this->service->getAuth();
        $userData = $result['user']->resolve();

        expect($userData['tutoring_filter'])->toBeArray()
            ->and($userData['tutoring_filter']['only_boys'])->toBeTrue()
            ->and($userData['tutoring_filter']['only_girls'])->toBeFalse()
            ->and($userData['tutoring_filter']['only_in_my_school'])->toBeFalse();
    });

    it('returns empty roles collection when user has no roles', function () {
        $user = User::factory()->create();

        Auth::login($user);

        $result = $this->service->getAuth();
        $rolesData = $result['roles']->resolve();

        expect($rolesData)->toBeArray()
            ->and($rolesData)->toBeEmpty();
    });

    it('returns roles array when user has single role', function () {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('admin');

        Auth::login($user);

        $result = $this->service->getAuth();
        $rolesData = $result['roles']->resolve();

        expect($rolesData)->toBeArray()
            ->and($rolesData)->toHaveCount(1)
            ->and($rolesData[0]['name'])->toBe('admin')
            ->and($rolesData[0])->toHaveKey('id');
    });

    it('returns roles array when user has multiple roles', function () {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'register_admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole(['admin', 'teacher', 'register_admin']);

        Auth::login($user);

        $result = $this->service->getAuth();
        $rolesData = $result['roles']->resolve();

        expect($rolesData)->toBeArray()
            ->and($rolesData)->toHaveCount(3);

        $roleNames = array_column($rolesData, 'name');
        expect($roleNames)->toContain('admin')
            ->and($roleNames)->toContain('teacher')
            ->and($roleNames)->toContain('register_admin');
    });

    it('returns correct role structure with id and name', function () {
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('admin');

        Auth::login($user);

        $result = $this->service->getAuth();
        $rolesData = $result['roles']->resolve();

        expect($rolesData[0])->toBeArray()
            ->and($rolesData[0])->toHaveKey('id')
            ->and($rolesData[0])->toHaveKey('name')
            ->and($rolesData[0]['id'])->toBe($role->id)
            ->and($rolesData[0]['name'])->toBe('admin');
    });

    it('handles authentication state changes correctly', function () {
        $user = User::factory()->create();

        // First call - not authenticated
        $result1 = $this->service->getAuth();
        expect($result1['is_auth'])->toBeFalse();

        // Login
        Auth::login($user);

        // Second call - authenticated
        $result2 = $this->service->getAuth();
        $userData2 = $result2['user']->resolve();

        expect($result2['is_auth'])->toBeTrue()
            ->and($userData2['id'])->toBe($user->id);

        // Logout
        Auth::logout();

        // Third call - not authenticated again
        $result3 = $this->service->getAuth();
        expect($result3['is_auth'])->toBeFalse();
    });

    it('returns consistent structure for different users', function () {
        $user1 = User::factory()->create(['email' => 'user1@example.com']);
        $user2 = User::factory()->create(['email' => 'user2@example.com']);

        Auth::login($user1);
        $result1 = $this->service->getAuth();
        $userData1 = $result1['user']->resolve();

        Auth::logout();
        Auth::login($user2);
        $result2 = $this->service->getAuth();
        $userData2 = $result2['user']->resolve();

        expect($result1)->toHaveKeys(['is_auth', 'user', 'roles'])
            ->and($result2)->toHaveKeys(['is_auth', 'user', 'roles'])
            ->and($userData1['id'])->toBe($user1->id)
            ->and($userData2['id'])->toBe($user2->id);
    });

    it('does not expose sensitive user data', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('secret'),
        ]);

        Auth::login($user);

        $result = $this->service->getAuth();
        $userData = $result['user']->resolve();

        expect($userData)->not->toHaveKey('password')
            ->and($userData)->not->toHaveKey('remember_token')
            ->and($userData)->not->toHaveKey('token_2fa')
            ->and($userData)->not->toHaveKey('created_at')
            ->and($userData)->not->toHaveKey('updated_at');
    });

    it('handles users with null optional fields', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'first_name' => null,
            'phone' => null,
            'schoolclass' => null,
            'sex' => null,
        ]);

        Auth::login($user);

        $result = $this->service->getAuth();
        $userData = $result['user']->resolve();

        expect($userData['first_name'])->toBeNull()
            ->and($userData['phone'])->toBeNull()
            ->and($userData['schoolclass'])->toBeNull()
            ->and($userData['sex'])->toBeNull();
    });

    it('returns user with all available role types', function () {
        // Create all common role types
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'register_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole(['super_admin', 'admin', 'register_admin', 'teacher', 'user']);

        Auth::login($user);

        $result = $this->service->getAuth();
        $rolesData = $result['roles']->resolve();

        expect($rolesData)->toHaveCount(5);

        $roleNames = array_column($rolesData, 'name');
        expect($roleNames)->toContain('super_admin')
            ->and($roleNames)->toContain('admin')
            ->and($roleNames)->toContain('register_admin')
            ->and($roleNames)->toContain('teacher')
            ->and($roleNames)->toContain('user');
    });

    it('returns UserResource instance for user field', function () {
        $user = User::factory()->create();

        Auth::login($user);

        $result = $this->service->getAuth();

        expect($result['user'])->toBeInstanceOf(\App\Http\Resources\Homepage\UserResource::class);
    });

    it('returns AnonymousResourceCollection for roles field', function () {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('admin');

        Auth::login($user);

        $result = $this->service->getAuth();

        expect($result['roles'])->toBeInstanceOf(\Illuminate\Http\Resources\Json\AnonymousResourceCollection::class);
    });

    it('returns auth structure matches expected API format', function () {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
        $user->assignRole('admin');

        Auth::login($user);

        $result = $this->service->getAuth();

        expect($result)->toHaveKeys(['is_auth', 'user', 'roles'])
            ->and($result['is_auth'])->toBeTrue();

        $userData = $result['user']->resolve();
        expect($userData)->toHaveKeys(['id', 'email', 'last_name', 'first_name', 'sex', 'phone', 'schoolclass', 'tutoring_filter']);

        $rolesData = $result['roles']->resolve();
        expect($rolesData)->toBeArray()
            ->and($rolesData[0])->toHaveKeys(['id', 'name']);
    });
});

