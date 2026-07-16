<?php

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('app service provider registers debugbar alias in local env', function () {
    config(['app.env' => 'local']);

    $provider = new AppServiceProvider(app());
    $provider->register();

    $aliases = AliasLoader::getInstance()->getAliases();
    expect($aliases)->toHaveKey('Debugbar');
});

test('app service provider forces https in production', function () {
    config([
        'app.env' => 'production',
        'app.url' => 'http://example.test',
    ]);

    URL::forceScheme('http');

    $provider = new AppServiceProvider(app());
    $provider->boot();

    expect(URL::to('/status'))->toStartWith('https://');
});

test('app service provider registers rate limiters', function () {
    config([
        'app.env' => 'testing',
        'spa.api_throttle' => 120,
        'spa.web_throttle' => 80,
        'spa.global_throttle' => 1000,
    ]);

    $provider = new AppServiceProvider(app());
    $provider->boot();

    $apiLimiter = RateLimiter::limiter('api');
    $webLimiter = RateLimiter::limiter('web');
    $globalLimiter = RateLimiter::limiter('global');
    $authenticationLimiter = RateLimiter::limiter('authentication');

    $user = User::factory()->create();
    $requestWithUser = Request::create('/api/test');
    $requestWithUser->setUserResolver(fn () => $user);

    $apiLimit = $apiLimiter($requestWithUser);
    $webLimit = $webLimiter($requestWithUser);

    expect($apiLimit->maxAttempts)->toBe(120)
        ->and($apiLimit->key)->toBe($user->id)
        ->and($webLimit->maxAttempts)->toBe(80)
        ->and($webLimit->key)->toBe($user->id);

    $requestWithIp = Request::create('/web/test');
    $requestWithIp->server->set('REMOTE_ADDR', '127.0.0.1');
    $requestWithIp->setUserResolver(fn () => null);

    $apiIpLimit = $apiLimiter($requestWithIp);
    $globalLimit = $globalLimiter($requestWithIp);

    $authenticationRequest = Request::create('/api/admin/login_step_email', 'POST', [
        'data' => ['email' => 'User@Example.Test'],
    ]);
    $authenticationRequest->server->set('REMOTE_ADDR', '127.0.0.1');
    $authenticationLimits = $authenticationLimiter($authenticationRequest);

    expect($apiIpLimit->key)->toBe('127.0.0.1')
        ->and($globalLimit->maxAttempts)->toBe(1000)
        ->and($globalLimit->key)->toBe('127.0.0.1')
        ->and($authenticationLimits)->toHaveCount(2)
        ->and($authenticationLimits[0]->maxAttempts)->toBe(10)
        ->and($authenticationLimits[0]->key)->toContain('user@example.test')
        ->and($authenticationLimits[1]->maxAttempts)->toBe(60);
});
