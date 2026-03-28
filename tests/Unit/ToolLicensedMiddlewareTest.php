<?php

use App\Http\Middleware\ToolLicensed;
use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\User;
use App\Services\AccessScopeService;
use App\Services\LicenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as IlluminateRoute;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('passes explicit candidate roles to the licence service', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);

    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('teaching_admin', 'web');
    $user->assignRole('admin');

    $licence = Licence::create([
        'name' => 'Lehrertool',
        'long_name' => 'Lehrertool',
    ]);

    SchoolLicence::create([
        'school_id' => $school->id,
        'licence_id' => $licence->id,
        'valid_until' => now()->addYear(),
    ]);

    $request = Request::create('/api/admin/teaching_upload/demo', 'POST');
    $request->setRouteResolver(fn () => new IlluminateRoute('POST', '/api/admin/teaching_upload/{slug}', []));

    $this->actingAs($user);

    $licenceService = Mockery::mock(LicenceService::class);
    $licenceService->shouldReceive('toolAccessStatusForUser')
        ->once()
        ->withArgs(function ($authUser, $resolvedSchool, $licenceName, $candidateRoleNames) use ($user, $school) {
            return $authUser?->is($user)
                && $resolvedSchool?->is($school)
                && $licenceName === 'Lehrertool'
                && $candidateRoleNames === ['admin', 'teaching_admin'];
        })
        ->andReturn('active');

    $this->app->instance(LicenceService::class, $licenceService);

    $response = app(ToolLicensed::class)->handle(
        $request,
        fn () => response()->json(['ok' => true]),
        'Lehrertool',
        'auto',
        'admin',
        'teaching_admin'
    );

    expect($response->getStatusCode())->toBe(200);
});

it('passes scope candidate roles to the licence service', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);

    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('materials_admin', 'web');
    Role::findOrCreate('materials_moderator', 'web');
    $user->assignRole('materials_admin');

    $licence = Licence::create([
        'name' => 'Materialientool',
        'long_name' => 'Materialientool',
    ]);

    SchoolLicence::create([
        'school_id' => $school->id,
        'licence_id' => $licence->id,
        'valid_until' => now()->addYear(),
    ]);

    $request = Request::create('/api/admin/materials/config', 'GET');
    $request->setRouteResolver(fn () => new IlluminateRoute('GET', '/api/admin/materials/config', []));

    $this->actingAs($user);

    $licenceService = Mockery::mock(LicenceService::class);
    $licenceService->shouldReceive('toolAccessStatusForUser')
        ->once()
        ->withArgs(function ($authUser, $resolvedSchool, $licenceName, $candidateRoleNames) use ($user, $school) {
            return $authUser?->is($user)
                && $resolvedSchool?->is($school)
                && $licenceName === 'Materialientool'
                && $candidateRoleNames === ['admin', 'materials_admin', 'materials_moderator'];
        })
        ->andReturn('active');

    $this->app->instance(LicenceService::class, $licenceService);

    $response = app(ToolLicensed::class)->handle(
        $request,
        fn () => response()->json(['ok' => true]),
        'Materialientool',
        'auto',
        'scope:materials_access'
    );

    expect($response->getStatusCode())->toBe(200);
});

it('requires explicit candidate roles on routes that combine role and licence middleware', function () {
    $accessScopeService = app(AccessScopeService::class);

    collect(app('router')->getRoutes())->each(function ($route) use ($accessScopeService) {
        $middlewares = collect($route->gatherMiddleware())
            ->filter(fn ($middleware) => is_string($middleware))
            ->values();

        $roleNames = $middlewares
            ->filter(fn (string $middleware) => str_starts_with($middleware, 'api-allowed:') || str_starts_with($middleware, 'web-allowed:'))
            ->flatMap(function (string $middleware) use ($accessScopeService) {
                [, $roleList] = array_pad(explode(':', $middleware, 2), 2, '');

                return $accessScopeService->resolveRoleNames(explode(',', $roleList));
            })
            ->unique()
            ->values()
            ->all();

        if ($roleNames === []) {
            return;
        }

        $middlewares
            ->filter(fn (string $middleware) => str_starts_with($middleware, 'tool-licensed:'))
            ->each(function (string $middleware) use ($accessScopeService, $roleNames, $route) {
                [, $argumentList] = array_pad(explode(':', $middleware, 2), 2, '');
                $parts = collect(explode(',', $argumentList))
                    ->map(fn (string $part) => trim($part))
                    ->values();

                $candidateRoles = $parts->slice(1)->values();
                if (in_array($candidateRoles->first(), ['auto', 'auth'], true)) {
                    $candidateRoles = $candidateRoles->slice(1)->values();
                }

                $candidateRoles = collect($accessScopeService->resolveRoleNames($candidateRoles->all()))
                    ->values();

                expect($candidateRoles->all())
                    ->toEqualCanonicalizing($roleNames, sprintf('Route %s must declare explicit candidate roles for %s', $route->uri(), $middleware));
            });
    });
});

it('uses scope references on route middleware instead of raw role lists', function () {
    collect(app('router')->getRoutes())->each(function ($route) {
        collect($route->gatherMiddleware())
            ->filter(fn ($middleware) => is_string($middleware))
            ->filter(fn (string $middleware) => str_starts_with($middleware, 'api-allowed:') || str_starts_with($middleware, 'web-allowed:'))
            ->each(function (string $middleware) use ($route) {
                [, $argumentList] = array_pad(explode(':', $middleware, 2), 2, '');
                $parts = collect(explode(',', $argumentList))
                    ->map(fn (string $part) => trim($part))
                    ->filter()
                    ->values();

                expect($parts->every(fn (string $part) => str_starts_with($part, 'scope:')))
                    ->toBeTrue(sprintf('Route %s must use scope-based middleware arguments for %s', $route->uri(), $middleware));
            });
    });
});

it('redirects blocked admin web routes to /admin instead of /', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);

    $request = Request::create('/admin/teaching', 'GET');
    $request->setRouteResolver(fn () => new IlluminateRoute('GET', '/admin/teaching/{any?}', []));

    $this->actingAs($user);

    $licenceService = Mockery::mock(LicenceService::class);
    $licenceService->shouldReceive('toolAccessStatusForUser')
        ->once()
        ->andReturn('missing');

    $this->app->instance(LicenceService::class, $licenceService);

    $response = app(ToolLicensed::class)->handle(
        $request,
        fn () => response('ok'),
        'Lehrertool',
        'auth',
        'scope:tool_web_access'
    );

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toBe(url('/admin'));
});
