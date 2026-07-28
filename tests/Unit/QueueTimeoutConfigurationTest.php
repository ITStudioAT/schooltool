<?php

use App\Jobs\BuildMaterialStorageAuditJob;
use App\Jobs\HealthJob;
use App\Jobs\MaterialsV2\ProcessMaterialV2Item;
use App\Jobs\StudentsTimetables\ProcessTimetableImportJob;
use App\Notifications\StandardEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;
use Laravel\Pulse\Recorders\CacheInteractions;
use Laravel\Pulse\Recorders\Servers;
use Laravel\Pulse\Recorders\SlowOutgoingRequests;
use Laravel\Pulse\Recorders\UserJobs;
use Laravel\Pulse\Recorders\UserRequests;
use Tests\TestCase;

uses(TestCase::class);

it('keeps queue retry windows above every explicit job timeout', function () {
    $jobFiles = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(app_path('Jobs'), FilesystemIterator::SKIP_DOTS),
    );
    $timeouts = [];

    foreach ($jobFiles as $jobFile) {
        if ($jobFile->getExtension() !== 'php') {
            continue;
        }

        $relativePath = Str::after($jobFile->getPathname(), app_path('Jobs').DIRECTORY_SEPARATOR);
        $class = 'App\\Jobs\\'.str_replace(
            [DIRECTORY_SEPARATOR, '.php'],
            ['\\', ''],
            $relativePath,
        );

        if (! class_exists($class) || ! is_subclass_of($class, ShouldQueue::class)) {
            continue;
        }

        $timeout = (new ReflectionClass($class))->getDefaultProperties()['timeout'] ?? null;

        if (is_int($timeout)) {
            $timeouts[$class] = $timeout;
        }
    }

    expect($timeouts)->not->toBeEmpty();

    foreach (['database', 'beanstalkd', 'redis'] as $connection) {
        $retryAfter = (int) config("queue.connections.{$connection}.retry_after");

        foreach ($timeouts as $class => $timeout) {
            expect($retryAfter)
                ->toBeGreaterThanOrEqual(
                    $timeout + 60,
                    "{$connection} retry_after is too short for {$class}.",
                );
        }
    }
});

it('keeps every Horizon timeout below the Redis retry window', function (): void {
    $redisRetryAfter = (int) config('queue.connections.redis.retry_after');
    $supervisors = config('horizon.defaults');

    expect($supervisors)->toBeArray()->not->toBeEmpty();

    foreach ($supervisors as $name => $supervisor) {
        $horizonTimeout = (int) ($supervisor['timeout'] ?? 0);

        expect($horizonTimeout)
            ->toBeGreaterThan(0, "{$name} must define a positive timeout.")
            ->and($redisRetryAfter)
            ->toBeGreaterThan($horizonTimeout, "{$name} timeout must remain below retry_after.");
    }
});

it('segments latency-sensitive, import, material, and maintenance workloads', function (): void {
    expect(config('horizon.defaults.supervisor-critical.queue'))
        ->toBe(['critical', 'notifications'])
        ->and(config('horizon.defaults.supervisor-default.queue'))
        ->toBe(['default'])
        ->and(config('horizon.defaults.supervisor-imports.queue'))
        ->toBe(['imports'])
        ->and(config('horizon.defaults.supervisor-long-running.queue'))
        ->toBe(['materials', 'maintenance'])
        ->and((new HealthJob)->queue)->toBe('critical')
        ->and((new ProcessTimetableImportJob(1))->queue)->toBe('imports')
        ->and((new ProcessMaterialV2Item(1))->queue)->toBe('materials')
        ->and((new BuildMaterialStorageAuditJob(1, 'operation'))->queue)->toBe('maintenance')
        ->and((new StandardEmail([]))->viaQueues())->toBe(['mail' => 'notifications']);
});

it('ships a production process monitor for Horizon', function (): void {
    $supervisorConfig = file_get_contents(base_path('supervisor.horizon.conf.example'));
    $horizonTimeout = collect(config('horizon.defaults'))->max('timeout');

    preg_match('/^stopwaitsecs=(\d+)$/m', $supervisorConfig, $stopWaitMatches);

    expect($supervisorConfig)
        ->toContain('[program:schooltool-horizon]')
        ->toContain('command=php artisan horizon')
        ->toContain('autostart=true')
        ->toContain('autorestart=true')
        ->not->toContain('queue:work')
        ->and($stopWaitMatches)
        ->toHaveKey(1)
        ->and((int) $stopWaitMatches[1])
        ->toBeGreaterThan((int) $horizonTimeout);
});

it('runs isolated infrastructure and Horizon smoke coverage in CI', function (): void {
    $workflow = file_get_contents(base_path('.github/workflows/ci.yml'));
    $composer = json_decode(
        file_get_contents(base_path('composer.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect(config('database.redis.integration.url'))->toBeNull()
        ->and(config('database.redis.integration.host'))->toBe('127.0.0.1')
        ->and((int) config('database.redis.integration.database'))->toBe(15)
        ->and(config('database.redis.integration.prefix'))->toContain('integration')
        ->and($composer['scripts']['test:integration'])
        ->toContain('@php artisan test --compact tests/Feature/InfrastructureIntegrationTest.php --fail-on-skipped')
        ->and($workflow)
        ->toContain('mysql:')
        ->toContain('redis:')
        ->toContain('composer test:integration')
        ->toContain('php artisan horizon')
        ->toContain('php artisan horizon:status')
        ->not->toContain('RUN_REDIS_INTEGRATION_TESTS');
});

it('keeps sensitive Pulse user and request recording disabled', function (): void {
    expect(config('pulse.recorders.'.UserJobs::class.'.enabled'))->toBeFalse()
        ->and(config('pulse.recorders.'.UserRequests::class.'.enabled'))->toBeFalse()
        ->and(config('pulse.recorders.'.Servers::class.'.enabled'))->toBeFalse()
        ->and(config('pulse.storage.driver'))->toBe('database')
        ->and(config('pulse.ingest.driver'))->toBe('storage')
        ->and(config('pulse.recorders.'.CacheInteractions::class.'.ignore'))
        ->toContain('#^restaurant:manual-registration:#')
        ->and(config('pulse.recorders.'.SlowOutgoingRequests::class.'.groups'))
        ->toHaveKey('#\?.*$#', '');
});
