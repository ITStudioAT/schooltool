<?php

use App\Jobs\HealthJob;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('handle', function () {
    it('writes worker heartbeat to cache', function () {
        Cache::forget('health:worker');

        $job = new HealthJob;
        $job->handle();

        expect(Cache::get('health:worker'))->not->toBeNull();
    });

    it('writes an ISO 8601 timestamp to cache', function () {
        $job = new HealthJob;
        $job->handle();

        $cached = Cache::get('health:worker');
        $parsed = Carbon::parse($cached);

        expect($parsed)->toBeInstanceOf(Carbon::class)
            ->and($parsed->diffInSeconds(now()))->toBeLessThanOrEqual(2);
    });

    it('overwrites previous heartbeat on repeated execution', function () {
        Cache::put('health:worker', now()->subHour()->toIso8601String(), 300);

        $job = new HealthJob;
        $job->handle();

        $cached = Carbon::parse(Cache::get('health:worker'));

        expect($cached->diffInSeconds(now()))->toBeLessThanOrEqual(2);
    });

    it('pings external service when configured', function () {
        Http::fake();
        config(['services.healthcheck.worker_ping_url' => 'https://hc-ping.com/test-uuid']);

        $job = new HealthJob;
        $job->handle();

        Http::assertSentCount(1);
    });

    it('does not ping when no URL is configured', function () {
        Http::fake();
        config(['services.healthcheck.worker_ping_url' => null]);

        $job = new HealthJob;
        $job->handle();

        Http::assertNothingSent();
    });

    it('still writes cache when ping fails', function () {
        Http::fake(fn () => Http::response('', 500));
        config(['services.healthcheck.worker_ping_url' => 'https://hc-ping.com/test-uuid']);

        Cache::forget('health:worker');

        $job = new HealthJob;
        $job->handle();

        expect(Cache::get('health:worker'))->not->toBeNull();
    });
});

describe('job configuration', function () {
    it('implements ShouldQueue interface', function () {
        $job = new HealthJob;
        expect($job)->toBeInstanceOf(ShouldQueue::class);
    });

    it('uses Queueable trait', function () {
        $job = new HealthJob;
        expect(class_uses($job))->toContain(Queueable::class);
    });

    it('can be dispatched to queue', function () {
        Queue::fake();

        HealthJob::dispatch();

        Queue::assertPushed(HealthJob::class);
    });
});
