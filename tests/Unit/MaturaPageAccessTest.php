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

it('guards Matura with the current administrator role even during impersonation', function (?string $roleName, bool $allowed, bool $impersonating) {
    $request = Request::create('/admin/matura');
    $route = Route::getRoutes()->match($request);

    expect($route->getName())->toBe('admin.matura')
        ->and($route->gatherMiddleware())->toContain('auth:sanctum', 'web-allowed:scope:admin_or_super_admin_access');

    $user = new User;
    $user->setRelation('roles', new Collection($roleName === null ? [] : [
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
            (new WebAllowed)->handle($request, $next, 'scope:admin_or_super_admin_access');
            $this->fail('Matura must reject an impersonated user without an administrator role.');
        } catch (HttpException $exception) {
            expect($exception->getStatusCode())->toBe(403);
        }

        return;
    }

    $response = (new WebAllowed)->handle($request, $next, 'scope:admin_or_super_admin_access');

    if ($allowed) {
        expect($response->getStatusCode())->toBe(200)
            ->and($response->getContent())->toBe('allowed');

        return;
    }

    expect($response->isRedirect())->toBeTrue()
        ->and($response->getTargetUrl())->toEndWith('/admin/login');
})->with([
    ['super_admin', true],
    ['admin', true],
    ['teacher', false],
    ['teaching_admin', false],
    ['materials_admin', false],
    ['studentstimetables_admin', false],
    ['register_admin', false],
    ['user', false],
    ['student', false],
    ['studentstimetables_user', false],
    [null, false],
])->with([false, true]);

it('redirects guests away from Matura', function () {
    Auth::shouldReceive('check')->andReturn(false);

    $response = (new WebAllowed)->handle(
        Request::create('/admin/matura'),
        function () {
            $this->fail('Guests must not reach the Matura page.');
        },
        'scope:admin_or_super_admin_access',
    );

    expect($response->isRedirect())->toBeTrue()
        ->and($response->getTargetUrl())->toEndWith('/admin/login');
});
