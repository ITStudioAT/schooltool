<?php

use App\Http\Middleware\WebAllowed;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Lab404\Impersonate\Services\ImpersonateManager;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class);

it('guards the direct groups page with the current super admin role even during impersonation', function (string $roleName, bool $allowed, bool $impersonating) {
    $request = Request::create('/admin/groups?panel=groups_own');
    $route = Route::getRoutes()->match($request);

    expect($route->getName())->toBe('admin.groups')
        ->and($route->gatherMiddleware())->toContain('auth:sanctum', 'web-allowed:scope:super_admin_access');

    $user = new User;
    $user->setRelation('roles', new Collection([
        new Role(['name' => $roleName, 'guard_name' => 'web']),
    ]));
    Auth::shouldReceive('check')->andReturn(true);
    Auth::shouldReceive('user')->andReturn($user);
    $this->mock(ImpersonateManager::class)
        ->shouldReceive('isImpersonating')->andReturn($impersonating);

    $next = function () use ($route) {
        $view = ($route->getAction('uses'))();
        expect($view->name())->toBe('spa::admin');

        return response('allowed');
    };

    if (! $allowed && $impersonating) {
        try {
            (new WebAllowed)->handle($request, $next, 'scope:super_admin_access');
            $this->fail('The groups page must reject an impersonated non-super-admin.');
        } catch (HttpException $exception) {
            expect($exception->getStatusCode())->toBe(403);
        }

        return;
    }

    $response = (new WebAllowed)->handle($request, $next, 'scope:super_admin_access');

    if ($allowed) {
        expect($response->getStatusCode())->toBe(200)
            ->and($response->getContent())->toBe('allowed');
    } else {
        expect($response->isRedirect())->toBeTrue()
            ->and($response->getTargetUrl())->toEndWith('/admin/login');
    }
})->with([
    ['super_admin', true],
    ['admin', false],
    ['materials_admin', false],
    ['materials_moderator', false],
    ['teacher', false],
    ['lunch_admin', false],
])->with([false, true]);
