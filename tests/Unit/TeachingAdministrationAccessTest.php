<?php

use App\Http\Middleware\WebAllowed;
use App\Models\User;
use App\Services\AccessScopeService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Lab404\Impersonate\Services\ImpersonateManager;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class);

it('enforces administration roles on the direct teaching page route', function (string $roleName, bool $allowed) {
    $request = Request::create('/admin/teaching/administration?panel=teachers');
    $route = Route::getRoutes()->match($request);

    expect($route->getName())->toBe('admin.teaching.administration')
        ->and($route->gatherMiddleware())->toContain(
            'auth:sanctum',
            'web-allowed:scope:teaching_administration_access',
            'tool-licensed:Lehrertool,auth,scope:teaching_administration_access',
        )
        ->and(app(AccessScopeService::class)->roleNamesForScope('teaching_administration_access'))
        ->toBe(['admin', 'super_admin', 'teaching_admin']);

    $user = new User;
    $user->setRelation('roles', new Collection([
        new Role(['name' => $roleName, 'guard_name' => 'web']),
    ]));
    Auth::shouldReceive('check')->andReturn(true);
    Auth::shouldReceive('user')->andReturn($user);
    $this->mock(ImpersonateManager::class)
        ->shouldReceive('isImpersonating')->andReturn(false);

    $roleMiddleware = collect($route->gatherMiddleware())
        ->first(fn (string $middleware): bool => str_starts_with($middleware, 'web-allowed:'));
    $roles = explode(',', substr($roleMiddleware, strlen('web-allowed:')));
    $response = (new WebAllowed)->handle($request, fn () => response('allowed'), ...$roles);

    if ($allowed) {
        expect($response->getStatusCode())->toBe(200)
            ->and($response->getContent())->toBe('allowed');
    } else {
        expect($response->isRedirect())->toBeTrue()
            ->and($response->getTargetUrl())->toEndWith('/admin/login');
    }
})->with([
    ['admin', true],
    ['super_admin', true],
    ['teaching_admin', true],
    ['teacher', false],
    ['register_admin', false],
    ['materials_admin', false],
    ['lunch_admin', false],
]);
