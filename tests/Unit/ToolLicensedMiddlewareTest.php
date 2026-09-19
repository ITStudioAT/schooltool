<?php

use App\Http\Middleware\ToolLicensed;
use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
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
    SchoolTool::factory()->create([
        'school_id' => $school->id,
        'teaching_visible_admin' => true,
    ]);
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
    SchoolTool::factory()->create([
        'school_id' => $school->id,
        'materials_visible_admin' => true,
    ]);
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

it('requires the administration role licence when a teacher also has teaching administration access', function (string $licensedRole, bool $allowed) {
    $school = School::factory()->create();
    SchoolTool::factory()->create([
        'school_id' => $school->id,
        'teaching_visible_admin' => true,
    ]);
    $user = User::factory()->create(['school_id' => $school->id]);
    foreach (['teacher', 'teaching_admin'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    $user->assignRole('teacher', 'teaching_admin');
    $licence = Licence::create([
        'name' => 'Lehrertool',
        'long_name' => 'Lehrertool',
        'licence_model' => [
            'school_licence_required' => true,
            'affected_roles' => ['teacher', 'teaching_admin'],
            'user_licence_required_by_role' => ['teacher' => true, 'teaching_admin' => true],
        ],
    ]);
    SchoolLicence::create([
        'school_id' => $school->id,
        'licence_id' => $licence->id,
        'valid_until' => now()->addYear(),
        'user_licence_assignments' => [
            (string) $user->id => [
                $licensedRole => ['valid_until' => now()->addMonth()->toDateString()],
            ],
        ],
    ]);
    $this->actingAs($user);
    $request = Request::create('/admin/teaching/administration');
    $route = app('router')->getRoutes()->match($request);
    $request->setRouteResolver(fn () => $route);
    $middleware = collect($route->gatherMiddleware())
        ->first(fn (string $middleware): bool => str_starts_with($middleware, 'tool-licensed:'));
    $arguments = explode(',', substr($middleware, strlen('tool-licensed:')));

    $response = app(ToolLicensed::class)->handle($request, fn () => response('allowed'), ...$arguments);

    if ($allowed) {
        expect($response->getStatusCode())->toBe(200)
            ->and($response->getContent())->toBe('allowed');

        return;
    }

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toBe(url('/admin'));
})->with([
    'only teacher licence' => ['teacher', false],
    'teaching administration licence' => ['teaching_admin', true],
]);

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
    SchoolTool::factory()->create([
        'school_id' => $school->id,
        'teaching_visible_admin' => true,
    ]);
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

it('denies access when a module is set to coming soon', function () {
    $school = School::factory()->create();
    SchoolTool::factory()->create([
        'school_id' => $school->id,
        'teaching_visible_user' => false,
        'teaching_user_test_mode' => false,
        'teaching_user_comming_soon' => true,
    ]);

    $user = User::factory()->create(['school_id' => $school->id]);
    Role::findOrCreate('teaching_admin', 'web');
    $user->assignRole('teaching_admin');

    $licence = Licence::create([
        'name' => 'Lehrertool',
        'long_name' => 'Lehrertool',
    ]);

    SchoolLicence::create([
        'school_id' => $school->id,
        'licence_id' => $licence->id,
        'valid_until' => now()->addYear(),
    ]);

    $request = Request::create('/admin/teaching', 'GET');
    $request->setRouteResolver(fn () => new IlluminateRoute('GET', '/admin/teaching/{any?}', []));

    $this->actingAs($user);

    $response = app(ToolLicensed::class)->handle(
        $request,
        fn () => response('ok'),
        'Lehrertool',
        'auth',
        'scope:teaching_access'
    );

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toBe(url('/admin'));
});

it('allows access when a module is set to test modus', function () {
    $school = School::factory()->create();
    SchoolTool::factory()->create([
        'school_id' => $school->id,
        'teaching_visible_admin' => true,
        'teaching_visible_user' => false,
        'teaching_user_test_mode' => true,
        'teaching_user_comming_soon' => false,
    ]);

    $user = User::factory()->create(['school_id' => $school->id]);
    Role::findOrCreate('teaching_admin', 'web');
    $user->assignRole('teaching_admin');

    $licence = Licence::create([
        'name' => 'Lehrertool',
        'long_name' => 'Lehrertool',
    ]);

    SchoolLicence::create([
        'school_id' => $school->id,
        'licence_id' => $licence->id,
        'valid_until' => now()->addYear(),
    ]);

    $request = Request::create('/admin/teaching', 'GET');
    $request->setRouteResolver(fn () => new IlluminateRoute('GET', '/admin/teaching/{any?}', []));

    $this->actingAs($user);

    $response = app(ToolLicensed::class)->handle(
        $request,
        fn () => response('ok'),
        'Lehrertool',
        'auth',
        'scope:teaching_access'
    );

    expect($response->getStatusCode())->toBe(200);
});

it('denies admin access when admin visibility is disabled', function () {
    $school = School::factory()->create();
    SchoolTool::factory()->create([
        'school_id' => $school->id,
        'teaching_visible_admin' => false,
        'teaching_visible_user' => true,
    ]);

    $user = User::factory()->create(['school_id' => $school->id]);
    Role::findOrCreate('teaching_admin', 'web');
    $user->assignRole('teaching_admin');

    $licence = Licence::create([
        'name' => 'Lehrertool',
        'long_name' => 'Lehrertool',
    ]);

    SchoolLicence::create([
        'school_id' => $school->id,
        'licence_id' => $licence->id,
        'valid_until' => now()->addYear(),
    ]);

    $request = Request::create('/admin/teaching', 'GET');
    $request->setRouteResolver(fn () => new IlluminateRoute('GET', '/admin/teaching/{any?}', []));

    $this->actingAs($user);

    $response = app(ToolLicensed::class)->handle(
        $request,
        fn () => response('ok'),
        'Lehrertool',
        'auth',
        'scope:teaching_access'
    );

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toBe(url('/admin'));
});
