<?php

/**
 * ApiAllowed Middleware Tests
 *
 * Tests the API allowed middleware that checks authentication and role authorization
 * for API routes. Aborts with 401 for unauthenticated and 403 for unauthorized users.
 */

use App\Http\Middleware\ApiAllowed;
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

    $this->middleware = new ApiAllowed();
});

describe('handle method - unauthenticated requests', function () {
    it('aborts with 401 when user is not authenticated', function () {
        $request = Request::create('/api/admin/users', 'GET');
        $next = fn($req) => response('OK');

        expect(fn() => $this->middleware->handle($request, $next, 'admin'))
            ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        try {
            $this->middleware->handle($request, $next, 'admin');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            expect($e->getStatusCode())->toBe(401)
                ->and($e->getMessage())->toBe('Nicht authorisiert');
        }
    });

    it('aborts with 401 and German error message', function () {
        $request = Request::create('/api/admin/settings', 'GET');
        $next = fn($req) => response('OK');

        try {
            $this->middleware->handle($request, $next, 'admin');
            $this->fail('Expected HttpException was not thrown');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            expect($e->getStatusCode())->toBe(401)
                ->and($e->getMessage())->toBe('Nicht authorisiert');
        }
    });

    it('aborts with 401 regardless of required role', function () {
        $request = Request::create('/api/admin/data', 'GET');
        $next = fn($req) => response('OK');

        try {
            $this->middleware->handle($request, $next, 'super_admin');
            $this->fail('Expected HttpException was not thrown');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            expect($e->getStatusCode())->toBe(401);
        }
    });
});

describe('handle method - authenticated user without required role', function () {
    it('aborts with 403 when user lacks required role', function () {
        $user = User::factory()->create();
        $user->assignRole('user');
        Auth::login($user);

        $request = Request::create('/api/admin/users', 'GET');
        $next = fn($req) => response('OK');

        try {
            $this->middleware->handle($request, $next, 'admin');
            $this->fail('Expected HttpException was not thrown');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            expect($e->getStatusCode())->toBe(403)
                ->and($e->getMessage())->toBe('Unzulässig');
        }
    });

    it('aborts with 403 when user lacks any of multiple required roles', function () {
        $user = User::factory()->create();
        $user->assignRole('user');
        Auth::login($user);

        $request = Request::create('/api/admin/settings', 'GET');
        $next = fn($req) => response('OK');

        try {
            $this->middleware->handle($request, $next, 'admin', 'register_admin');
            $this->fail('Expected HttpException was not thrown');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            expect($e->getStatusCode())->toBe(403)
                ->and($e->getMessage())->toBe('Unzulässig');
        }
    });

    it('aborts with 403 when user has no roles at all', function () {
        $user = User::factory()->create();
        Auth::login($user);

        $request = Request::create('/api/admin/data', 'GET');
        $next = fn($req) => response('OK');

        try {
            $this->middleware->handle($request, $next, 'admin');
            $this->fail('Expected HttpException was not thrown');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            expect($e->getStatusCode())->toBe(403);
        }
    });

    it('aborts with 403 for wrong role even with authentication', function () {
        $user = User::factory()->create();
        $user->assignRole('user');
        Auth::login($user);

        $request = Request::create('/api/admin/restricted', 'GET');
        $next = fn($req) => response('OK');

        try {
            $this->middleware->handle($request, $next, 'register_admin');
            $this->fail('Expected HttpException was not thrown');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            expect($e->getStatusCode())->toBe(403);
        }
    });
});

describe('handle method - authenticated user with required role', function () {
    it('allows access when user has exact required role', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');
        Auth::login($user);

        $request = Request::create('/api/admin/users', 'GET');
        $next = fn($req) => response('Admin Users');

        $response = $this->middleware->handle($request, $next, 'admin');

        expect($response->getContent())->toBe('Admin Users');
    });

    it('allows access when user has one of multiple required roles', function () {
        $user = User::factory()->create();
        $user->assignRole('register_admin');
        Auth::login($user);

        $request = Request::create('/api/admin/registers', 'GET');
        $next = fn($req) => response('Registers Data');

        $response = $this->middleware->handle($request, $next, 'admin', 'register_admin');

        expect($response->getContent())->toBe('Registers Data');
    });

    it('allows access when user has super_admin role regardless of required roles', function () {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        Auth::login($user);

        $request = Request::create('/api/admin/restricted', 'GET');
        $next = fn($req) => response('Restricted Data');

        $response = $this->middleware->handle($request, $next, 'admin');

        expect($response->getContent())->toBe('Restricted Data');
    });

    it('allows super_admin access even when specific role is required', function () {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        Auth::login($user);

        $request = Request::create('/api/admin/special', 'GET');
        $next = fn($req) => response('Special Data');

        $response = $this->middleware->handle($request, $next, 'register_admin');

        expect($response->getContent())->toBe('Special Data');
    });

    it('calls next closure and returns its response', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');
        Auth::login($user);

        $request = Request::create('/api/admin/test', 'GET');
        $next = fn($req) => response()->json(['status' => 'success', 'data' => 'test']);

        $response = $this->middleware->handle($request, $next, 'admin');

        expect($response->getStatusCode())->toBe(200)
            ->and(json_decode($response->getContent(), true))->toBe(['status' => 'success', 'data' => 'test']);
    });
});

describe('handle method - variadic role parameters', function () {
    it('works with single role parameter', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');
        Auth::login($user);

        $request = Request::create('/api/test', 'GET');
        $next = fn($req) => response('OK');

        $response = $this->middleware->handle($request, $next, 'admin');

        expect($response->getContent())->toBe('OK');
    });

    it('works with multiple role parameters', function () {
        $user = User::factory()->create();
        $user->assignRole('user');
        Auth::login($user);

        $request = Request::create('/api/test', 'GET');
        $next = fn($req) => response('OK');

        $response = $this->middleware->handle($request, $next, 'admin', 'user', 'register_admin');

        expect($response->getContent())->toBe('OK');
    });

    it('works with no role parameters but super_admin is added automatically', function () {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        Auth::login($user);

        $request = Request::create('/api/test', 'GET');
        $next = fn($req) => response('OK');

        // No roles specified, but super_admin should be added automatically by HasRoleTrait
        $response = $this->middleware->handle($request, $next);

        expect($response->getContent())->toBe('OK');
    });

    it('accepts three or more role parameters', function () {
        $user = User::factory()->create();
        $user->assignRole('register_admin');
        Auth::login($user);

        $request = Request::create('/api/test', 'GET');
        $next = fn($req) => response('OK');

        $response = $this->middleware->handle($request, $next, 'admin', 'user', 'register_admin', 'super_admin');

        expect($response->getContent())->toBe('OK');
    });
});

describe('handle method - different HTTP methods', function () {
    it('handles GET requests correctly', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');
        Auth::login($user);

        $request = Request::create('/api/admin/data', 'GET');
        $next = fn($req) => response('GET OK');

        $response = $this->middleware->handle($request, $next, 'admin');

        expect($response->getContent())->toBe('GET OK');
    });

    it('handles POST requests correctly', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');
        Auth::login($user);

        $request = Request::create('/api/admin/users', 'POST', ['name' => 'Test']);
        $next = fn($req) => response('POST OK');

        $response = $this->middleware->handle($request, $next, 'admin');

        expect($response->getContent())->toBe('POST OK');
    });

    it('handles PUT requests correctly', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');
        Auth::login($user);

        $request = Request::create('/api/admin/users/1', 'PUT');
        $next = fn($req) => response('PUT OK');

        $response = $this->middleware->handle($request, $next, 'admin');

        expect($response->getContent())->toBe('PUT OK');
    });

    it('handles DELETE requests correctly', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');
        Auth::login($user);

        $request = Request::create('/api/admin/users/1', 'DELETE');
        $next = fn($req) => response('DELETE OK');

        $response = $this->middleware->handle($request, $next, 'admin');

        expect($response->getContent())->toBe('DELETE OK');
    });

    it('handles PATCH requests correctly', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');
        Auth::login($user);

        $request = Request::create('/api/admin/users/1', 'PATCH');
        $next = fn($req) => response('PATCH OK');

        $response = $this->middleware->handle($request, $next, 'admin');

        expect($response->getContent())->toBe('PATCH OK');
    });
});

describe('middleware integration with HasRoleTrait', function () {
    it('uses HasRoleTrait userHasRole method correctly', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');
        Auth::login($user);

        $request = Request::create('/api/test', 'GET');
        $next = fn($req) => response('OK');

        // The middleware should use HasRoleTrait's userHasRole which auto-adds super_admin
        $response = $this->middleware->handle($request, $next, 'admin');

        expect($response->getContent())->toBe('OK');
    });

    it('respects HasRoleTrait automatic super_admin inclusion', function () {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        Auth::login($user);

        $request = Request::create('/api/admin/restricted', 'GET');
        $next = fn($req) => response('Restricted OK');

        // Even though 'restricted_role' is required, super_admin should pass
        $response = $this->middleware->handle($request, $next, 'restricted_role');

        expect($response->getContent())->toBe('Restricted OK');
    });

    it('returns User instance when HasRoleTrait userHasRole succeeds', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');
        Auth::login($user);

        $request = Request::create('/api/test', 'GET');
        $next = function ($req) {
            // If we get here, userHasRole returned truthy (the User instance)
            return response('Passed');
        };

        $response = $this->middleware->handle($request, $next, 'admin');

        expect($response->getContent())->toBe('Passed');
    });
});

describe('error handling', function () {
    it('throws HttpException not generic exception for 401', function () {
        $request = Request::create('/api/test', 'GET');
        $next = fn($req) => response('OK');

        expect(fn() => $this->middleware->handle($request, $next, 'admin'))
            ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    });

    it('throws HttpException not generic exception for 403', function () {
        $user = User::factory()->create();
        $user->assignRole('user');
        Auth::login($user);

        $request = Request::create('/api/test', 'GET');
        $next = fn($req) => response('OK');

        expect(fn() => $this->middleware->handle($request, $next, 'admin'))
            ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    });

    it('uses German error messages consistently', function () {
        // Test 401 error message
        $request = Request::create('/api/test', 'GET');
        $next = fn($req) => response('OK');

        try {
            $this->middleware->handle($request, $next, 'admin');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            expect($e->getMessage())->toBe('Nicht authorisiert');
        }

        // Test 403 error message
        $user = User::factory()->create();
        $user->assignRole('user');
        Auth::login($user);

        try {
            $this->middleware->handle($request, $next, 'admin');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            expect($e->getMessage())->toBe('Unzulässig');
        }
    });
});

describe('edge cases', function () {
    it('handles empty role array from variadic parameters', function () {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        Auth::login($user);

        $request = Request::create('/api/test', 'GET');
        $next = fn($req) => response('OK');

        // No roles passed, but super_admin should be added by HasRoleTrait
        $response = $this->middleware->handle($request, $next);

        expect($response->getContent())->toBe('OK');
    });

    it('handles authenticated user with multiple roles', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $user->assignRole('user');
        Auth::login($user);

        $request = Request::create('/api/test', 'GET');
        $next = fn($req) => response('OK');

        $response = $this->middleware->handle($request, $next, 'admin');

        expect($response->getContent())->toBe('OK');
    });

    it('checks authentication before checking roles', function () {
        // Unauthenticated should return 401, not 403
        $request = Request::create('/api/test', 'GET');
        $next = fn($req) => response('OK');

        try {
            $this->middleware->handle($request, $next, 'admin');
            $this->fail('Expected HttpException was not thrown');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // Should be 401 (unauthenticated), not 403 (unauthorized)
            expect($e->getStatusCode())->toBe(401);
        }
    });

    it('preserves request data through middleware', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');
        Auth::login($user);

        $request = Request::create('/api/test', 'POST', ['key' => 'value']);
        $next = function ($req) {
            return response()->json($req->all());
        };

        $response = $this->middleware->handle($request, $next, 'admin');

        expect(json_decode($response->getContent(), true))->toBe(['key' => 'value']);
    });
});

