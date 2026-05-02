<?php

/**
 * WebAllowed Middleware Tests
 *
 * Tests the web allowed middleware that checks authentication and role authorization
 * for admin routes. Redirects unauthenticated users to /admin/login.
 */

use App\Http\Middleware\WebAllowed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Create roles
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'register_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'materials_moderator', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'lunch_admin', 'guard_name' => 'web']);

    $this->middleware = new WebAllowed;
});

describe('handle method - login route exception', function () {
    it('allows access to /admin/login without authentication', function () {
        $request = Request::create('/admin/login', 'GET');
        $next = fn ($req) => response('OK');

        $response = $this->middleware->handle($request, $next, 'admin');

        expect($response->getContent())->toBe('OK');
    });

    it('allows access to /admin/login regardless of role requirements', function () {
        $request = Request::create('/admin/login', 'GET');
        $next = fn ($req) => response('OK');

        $response = $this->middleware->handle($request, $next, 'admin', 'super_admin');

        expect($response->getContent())->toBe('OK');
    });
});

describe('handle method - non-admin routes', function () {
    it('allows access to non-admin routes without authentication', function () {
        $request = Request::create('/homepage', 'GET');
        $next = fn ($req) => response('Homepage OK');

        $response = $this->middleware->handle($request, $next, 'admin');

        expect($response->getContent())->toBe('Homepage OK');
    });

    it('allows access to root route without authentication', function () {
        $request = Request::create('/', 'GET');
        $next = fn ($req) => response('Root OK');

        $response = $this->middleware->handle($request, $next, 'admin');

        expect($response->getContent())->toBe('Root OK');
    });

    it('allows access to non-admin routes without role check', function () {
        $request = Request::create('/api/something', 'GET');
        $next = fn ($req) => response('API OK');

        $response = $this->middleware->handle($request, $next, 'admin');

        expect($response->getContent())->toBe('API OK');
    });
});

describe('handle method - /admin route without authentication', function () {
    it('redirects to /admin/login when accessing /admin without authentication', function () {
        $request = Request::create('/admin', 'GET');
        $next = fn ($req) => response('Admin Dashboard');

        $response = $this->middleware->handle($request, $next, 'admin');

        expect($response->isRedirect())->toBeTrue()
            ->and($response->getTargetUrl())->toContain('/admin/login');
    });

    it('redirects to /admin/login when accessing /admin/* without authentication', function () {
        $request = Request::create('/admin/users', 'GET');
        $next = fn ($req) => response('Users Page');

        $response = $this->middleware->handle($request, $next, 'admin');

        expect($response->isRedirect())->toBeTrue()
            ->and($response->getTargetUrl())->toContain('/admin/login');
    });

    it('redirects to /admin/login when accessing nested admin routes', function () {
        $request = Request::create('/admin/users/create', 'GET');
        $next = fn ($req) => response('Create User');

        $response = $this->middleware->handle($request, $next, 'admin');

        expect($response->isRedirect())->toBeTrue()
            ->and($response->getTargetUrl())->toContain('/admin/login');
    });
});

describe('handle method - authenticated user without required role', function () {
    it('redirects when user lacks required role for /admin', function () {
        $user = User::factory()->create();
        $user->assignRole('user');
        Auth::login($user);

        $request = Request::create('/admin', 'GET');
        $next = fn ($req) => response('Admin Dashboard');

        $response = $this->middleware->handle($request, $next, 'admin');

        expect($response->isRedirect())->toBeTrue()
            ->and($response->getTargetUrl())->toContain('/admin/login');
    });

    it('redirects when user lacks any of the required roles for /admin/*', function () {
        $user = User::factory()->create();
        $user->assignRole('user');
        Auth::login($user);

        $request = Request::create('/admin/settings', 'GET');
        $next = fn ($req) => response('Settings');

        $response = $this->middleware->handle($request, $next, 'admin', 'register_admin');

        expect($response->isRedirect())->toBeTrue()
            ->and($response->getTargetUrl())->toContain('/admin/login');
    });

    it('redirects when user has no roles at all', function () {
        $user = User::factory()->create();
        Auth::login($user);

        $request = Request::create('/admin/dashboard', 'GET');
        $next = fn ($req) => response('Dashboard');

        $response = $this->middleware->handle($request, $next, 'admin');

        expect($response->isRedirect())->toBeTrue()
            ->and($response->getTargetUrl())->toContain('/admin/login');
    });
});

describe('handle method - authenticated user with required role', function () {
    it('allows access when user has exact required role', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');
        Auth::login($user);

        $request = Request::create('/admin', 'GET');
        $next = fn ($req) => response('Admin Dashboard');

        $response = $this->middleware->handle($request, $next, 'admin');

        expect($response->getContent())->toBe('Admin Dashboard');
    });

    it('allows access when user has one of multiple required roles', function () {
        $user = User::factory()->create();
        $user->assignRole('register_admin');
        Auth::login($user);

        $request = Request::create('/admin/registers', 'GET');
        $next = fn ($req) => response('Registers Page');

        $response = $this->middleware->handle($request, $next, 'admin', 'register_admin');

        expect($response->getContent())->toBe('Registers Page');
    });

    it('allows access when user has super_admin role regardless of required roles', function () {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        Auth::login($user);

        $request = Request::create('/admin/users', 'GET');
        $next = fn ($req) => response('Users Page');

        $response = $this->middleware->handle($request, $next, 'admin');

        expect($response->getContent())->toBe('Users Page');
    });

    it('allows super_admin access even when specific role is required', function () {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        Auth::login($user);

        $request = Request::create('/admin/special', 'GET');
        $next = fn ($req) => response('Special Page');

        $response = $this->middleware->handle($request, $next, 'register_admin');

        expect($response->getContent())->toBe('Special Page');
    });

    it('allows access for nested admin routes with correct role', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');
        Auth::login($user);

        $request = Request::create('/admin/users/1/edit', 'GET');
        $next = fn ($req) => response('Edit User');

        $response = $this->middleware->handle($request, $next, 'admin');

        expect($response->getContent())->toBe('Edit User');
    });
});

describe('handle method - scope parameters', function () {
    it('allows access when user matches a named scope', function () {
        $user = User::factory()->create();
        $user->assignRole('materials_moderator');
        Auth::login($user);

        $request = Request::create('/admin/materials', 'GET');
        $next = fn ($req) => response('Materials Page');

        $response = $this->middleware->handle($request, $next, 'scope:materials_access');

        expect($response->getContent())->toBe('Materials Page');
    });

    it('allows access to admin shell scope when user has a custom role marked as admin', function () {
        $user = User::factory()->create();
        $role = Role::create([
            'name' => 'custom_dashboard_role',
            'guard_name' => 'web',
            'is_admin' => true,
        ]);
        $user->assignRole($role);
        Auth::login($user);

        $request = Request::create('/admin', 'GET');
        $next = fn ($req) => response('Admin Dashboard');

        $response = $this->middleware->handle($request, $next, 'scope:admin_shell_access');

        expect($response->getContent())->toBe('Admin Dashboard');
    });

    it('allows lunch_admin to access restaurant settings through admin shell scope', function () {
        $user = User::factory()->create();
        $user->assignRole('lunch_admin');
        Auth::login($user);

        $request = Request::create('/admin/settings?tab=restaurant', 'GET');
        $next = fn ($req) => response('Restaurant Settings');

        $response = $this->middleware->handle($request, $next, 'scope:admin_shell_access');

        expect($response->getContent())->toBe('Restaurant Settings');
    });
});

describe('handle method - variadic role parameters', function () {
    it('works with single role parameter', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');
        Auth::login($user);

        $request = Request::create('/admin', 'GET');
        $next = fn ($req) => response('OK');

        $response = $this->middleware->handle($request, $next, 'admin');

        expect($response->getContent())->toBe('OK');
    });

    it('works with multiple role parameters', function () {
        $user = User::factory()->create();
        $user->assignRole('user');
        Auth::login($user);

        $request = Request::create('/admin', 'GET');
        $next = fn ($req) => response('OK');

        $response = $this->middleware->handle($request, $next, 'admin', 'user', 'register_admin');

        expect($response->getContent())->toBe('OK');
    });

    it('works with no role parameters (empty variadic)', function () {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        Auth::login($user);

        $request = Request::create('/admin', 'GET');
        $next = fn ($req) => response('OK');

        // No roles specified, but super_admin should be added automatically
        $response = $this->middleware->handle($request, $next);

        expect($response->getContent())->toBe('OK');
    });
});

describe('handle method - edge cases', function () {
    it('handles /admin with trailing slash', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');
        Auth::login($user);

        $request = Request::create('/admin/', 'GET');
        $next = fn ($req) => response('OK');

        $response = $this->middleware->handle($request, $next, 'admin');

        expect($response->getContent())->toBe('OK');
    });

    it('differentiates between /admin and /administrator paths', function () {
        // /administrator should not trigger admin route protection
        $request = Request::create('/administrator', 'GET');
        $next = fn ($req) => response('Administrator OK');

        $response = $this->middleware->handle($request, $next, 'admin');

        expect($response->getContent())->toBe('Administrator OK');
    });

    it('handles POST requests to admin routes', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');
        Auth::login($user);

        $request = Request::create('/admin/users', 'POST');
        $next = fn ($req) => response('User Created');

        $response = $this->middleware->handle($request, $next, 'admin');

        expect($response->getContent())->toBe('User Created');
    });

    it('handles PUT requests to admin routes', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');
        Auth::login($user);

        $request = Request::create('/admin/users/1', 'PUT');
        $next = fn ($req) => response('User Updated');

        $response = $this->middleware->handle($request, $next, 'admin');

        expect($response->getContent())->toBe('User Updated');
    });

    it('handles DELETE requests to admin routes', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');
        Auth::login($user);

        $request = Request::create('/admin/users/1', 'DELETE');
        $next = fn ($req) => response('User Deleted');

        $response = $this->middleware->handle($request, $next, 'admin');

        expect($response->getContent())->toBe('User Deleted');
    });
});

describe('middleware integration with HasRoleTrait', function () {
    it('uses HasRoleTrait userHasRole method correctly', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');
        Auth::login($user);

        $request = Request::create('/admin', 'GET');
        $next = fn ($req) => response('OK');

        // The middleware should use HasRoleTrait's userHasRole which auto-adds super_admin
        $response = $this->middleware->handle($request, $next, 'admin');

        expect($response->getContent())->toBe('OK');
    });

    it('respects HasRoleTrait automatic super_admin inclusion', function () {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        Auth::login($user);

        $request = Request::create('/admin/restricted', 'GET');
        $next = fn ($req) => response('Restricted OK');

        // Even though 'restricted_role' is required, super_admin should pass
        $response = $this->middleware->handle($request, $next, 'restricted_role');

        expect($response->getContent())->toBe('Restricted OK');
    });
});
