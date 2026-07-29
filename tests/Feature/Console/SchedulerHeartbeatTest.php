<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('writes scheduler heartbeat to cache', function () {
    Cache::forget('health:scheduler');

    $this->artisan('health:scheduler-heartbeat')->assertExitCode(0);

    $cached = Cache::get('health:scheduler');
    expect($cached)->not->toBeNull();

    $parsed = Carbon::parse($cached);
    expect($parsed->diffInSeconds(now()))->toBeLessThanOrEqual(2);
});

it('pings external service when configured', function () {
    Http::fake();
    config(['services.healthcheck.scheduler_ping_url' => 'https://hc-ping.com/test-uuid']);

    $this->artisan('health:scheduler-heartbeat')->assertExitCode(0);

    Http::assertSentCount(1);
});

it('does not ping when no URL is configured', function () {
    Http::fake();
    config(['services.healthcheck.scheduler_ping_url' => null]);

    $this->artisan('health:scheduler-heartbeat')->assertExitCode(0);

    Http::assertNothingSent();
});

it('succeeds even when ping fails', function () {
    Http::fake(fn () => Http::response('', 500));
    config(['services.healthcheck.scheduler_ping_url' => 'https://hc-ping.com/test-uuid']);

    Cache::forget('health:scheduler');

    $this->artisan('health:scheduler-heartbeat')->assertExitCode(0);

    expect(Cache::get('health:scheduler'))->not->toBeNull();
});
