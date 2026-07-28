<?php

use App\Providers\BroadcastServiceProvider;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

uses(TestCase::class);

test('broadcast service provider registers broadcast routes and channels', function () {
    Broadcast::shouldReceive('channel')
        ->once()
        ->with('user.{id}', Mockery::type('Closure'));

    $provider = new BroadcastServiceProvider(app());
    $provider->boot();

    $route = collect(Route::getRoutes()->getRoutes())
        ->first(fn ($route): bool => $route->uri() === 'broadcasting/auth');

    expect($route)->not->toBeNull()
        ->and($route->methods())->toContain('POST')
        ->and($route->gatherMiddleware())->toContain('web')
        ->and(file_get_contents(base_path('bootstrap/app.php')))
        ->not->toContain('broadcasting/auth');
});
