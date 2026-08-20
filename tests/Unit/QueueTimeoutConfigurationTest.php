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

it('starts segmented queue workers and the scheduler in the local development workflow', function (): void {
    $composer = json_decode(
        file_get_contents(base_path('composer.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $developmentCommand = implode(' ', $composer['scripts']['dev']);
    $localQueueCommand = implode(' ', $composer['scripts']['queues:local']);

    expect($developmentCommand)
        ->toContain('composer run queues:local')
        ->toContain('php artisan schedule:work')
        ->not->toContain('php artisan horizon')
        ->not->toContain('queue:listen')
        ->and($localQueueCommand)
        ->toContain('--queue=critical,notifications')
        ->toContain('--queue=default')
        ->toContain('--queue=imports')
        ->toContain('--queue=materials,maintenance');
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

it('recovers Cloudways Horizon before and after deployment', function (): void {
    $deploymentScript = file_get_contents(base_path('scripts/deploy_cloudways.sh'));

    expect($deploymentScript)
        ->toContain('verify_queue_runtime')
        ->toContain('wait_for_queue_runtime')
        ->toContain('ensure_queue_runtime')
        ->toContain('start_horizon_directly')
        ->toContain('php artisan queue:health-check')
        ->toContain('DEPLOY_HORIZON_RESTART_TIMEOUT:-60')
        ->toContain('attempt <= horizon_restart_timeout')
        ->toContain('nohup php artisan horizon 9>&- >> storage/logs/horizon.log 2>&1 </dev/null &')
        ->toContain('wait_for_previous_horizon_to_exit')
        ->toContain('Recycling unhealthy Horizon master process(es):')
        ->toContain('kill -TERM "$process_id"')
        ->toContain('The process monitor started Horizon, but it is still unhealthy:')
        ->toContain('health_check_arguments+=("--exclude-master-pid=${process_id}")')
        ->toContain('if wait_for_queue_runtime "$excluded_process_ids"; then')
        ->toContain('start_horizon_directly "$excluded_process_ids"')
        ->toContain('if wait_for_queue_runtime "$existing_process_ids"; then')
        ->toMatch('/verify_queue_runtime\(\).*?php artisan queue:health-check.*?ensure_queue_runtime/s')
        ->toMatch('/verify_queue_runtime\s+.*?php artisan down.*?prune-unlisted.*?prepare_frontend_artifact/s')
        ->toMatch('/install_frontend_artifact\s+php artisan app:update --no-interaction --skip-frontend\s+php artisan optimize.*?previous_horizon_process_ids=.*?php artisan horizon:terminate\s+ensure_queue_runtime "\$previous_horizon_process_ids"\s+\s*php artisan up/s')
        ->not->toMatch('/php artisan horizon:terminate\s+wait_for_previous_horizon_to_exit/s')
        ->not->toContain('Horizon did not restart within 20 seconds.');
});

it('validates the locally published frontend release in parallel CI', function (): void {
    $deploymentScript = file_get_contents(base_path('scripts/deploy_cloudways.sh'));
    $workflow = file_get_contents(base_path('.github/workflows/ci.yml'));
    $gitHelpers = file_get_contents(base_path('scripts/git_helpers.ps1'));

    expect($workflow)
        ->toContain('php-quality:')
        ->toContain('frontend:')
        ->toContain('php-tests:')
        ->toContain('infrastructure:')
        ->toContain('release-integrity:')
        ->toContain('READY — CI verified Cloudways release')
        ->toContain('--parallel --processes=2 --recreate-databases --coverage-clover=storage/logs/clover.xml')
        ->toContain('php scripts/frontend-release.php verify "$(git rev-parse HEAD^)"')
        ->toContain('if: github.event_name == \'push\' && github.ref == \'refs/heads/main\'')
        ->toMatch('/php-quality:.*?if: github\.event_name != \'push\'/s')
        ->toMatch('/frontend:.*?if: github\.event_name != \'push\'/s')
        ->toMatch('/php-tests:.*?if: github\.event_name != \'push\'/s')
        ->toMatch('/infrastructure:.*?if: github\.event_name != \'push\'/s')
        ->not->toContain('composer test:php:smoke')
        ->not->toContain('npm run test:ui:smoke')
        ->not->toContain('contents: write')
        ->not->toContain('git push origin HEAD:main')
        ->and($deploymentScript)
        ->toContain('frontend_release_archive="${project_directory}/deployment/frontend-build.tar.gz"')
        ->toContain('frontend_release_archive_hash="${project_directory}/deployment/frontend-build.sha256"')
        ->toContain('frontend_release_marker="${project_directory}/deployment/source-commit"')
        ->toContain('frontend_release_manifest="${project_directory}/deployment/source-manifest.sha256"')
        ->toContain('php scripts/frontend-release.php verify')
        ->toContain('tar -xzf "$frontend_release_archive"')
        ->toContain('artifact_source_commit="$(tr -d \'\r\n\' < "$frontend_artifact_directory/deployment-source.txt")"')
        ->toContain('if [ "$artifact_source_commit" != "$release_source_commit" ]')
        ->toContain('public/.schooltool-build.XXXXXX')
        ->toContain('flock -n 9')
        ->toContain('php artisan app:update --no-interaction --skip-frontend')
        ->toContain('Run gitpush locally and use Cloudways Pull again.')
        ->not->toContain('git fetch')
        ->not->toContain('git rev-parse')
        ->and($gitHelpers)
        ->toContain('Invoke-SchooltoolReleaseChecks')
        ->toContain('php scripts/frontend-release.php create $sourceCommit')
        ->toContain('php scripts/frontend-release.php verify $sourceCommit')
        ->toContain('deployment/frontend-build.sha256')
        ->toContain('git push @pushArguments')
        ->toContain("'--atomic', 'origin', 'HEAD:main'")
        ->toContain('Cloudways may Pull main and run: composer deploy');
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
        ->toContain('php artisan queue:health-check')
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
