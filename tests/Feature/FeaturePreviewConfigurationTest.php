<?php

use App\Models\FeaturePreviewSetting;
use App\Services\FeaturePreviewControlClient;
use App\Services\FeaturePreviewControlSignature;
use App\Services\FeaturePreviewDatabaseGuard;
use App\Services\FeaturePreviewSnapshotFiles;
use App\Services\FeaturePreviewSnapshotIdentityStore;
use Illuminate\Console\Scheduling\Schedule as ScheduleManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    config([
        'database.default' => 'preview_configuration_test',
        'database.connections.preview_configuration_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        'schooltool.preview.instance' => true,
        'schooltool.preview.url' => 'https://preview.example.test',
        'schooltool.preview.live_url' => 'https://live.example.test',
        'schooltool.preview.expected_host' => 'preview.example.test',
        'app.url' => 'https://preview.example.test',
        'app.debug' => false,
        'app.previous_keys' => [],
        'schooltool.preview.legacy_control_credentials_present' => false,
        'app.maintenance.driver' => 'file',
        'session.driver' => 'file',
        'session.files' => storage_path('framework/sessions'),
        'session.cookie' => 'schooltool_preview_session',
        'session.domain' => null,
        'session.secure' => true,
        'session.http_only' => true,
        'session.same_site' => 'lax',
        'cache.default' => 'file',
        'cache.stores.file.path' => storage_path('framework/cache/data'),
        'cache.stores.file.lock_path' => storage_path('framework/cache/data'),
        'cache.limiter' => null,
        'permission.cache.store' => 'default',
        'queue.default' => 'sync',
        'broadcasting.default' => 'null',
        'pulse.enabled' => false,
        'telescope.enabled' => false,
        'nightwatch.enabled' => false,
        'database.connections.cloudways' => [],
        'schooltool.legacy_restaurant_remote' => [],
        'services.cloudways.deployment.access_token' => null,
        'filesystems.disks.s3.key' => null,
        'filesystems.disks.s3.secret' => null,
        'services.ses.key' => null,
        'services.ses.secret' => null,
        'queue.connections.sqs.key' => null,
        'queue.connections.sqs.secret' => null,
        'cache.stores.dynamodb.key' => null,
        'cache.stores.dynamodb.secret' => null,
    ]);
    DB::purge('preview_configuration_test');
    expect(DB::connection()->getDatabaseName())->toBe(':memory:');
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->boolean('feature_preview_allowed')->default(false);
    });
    (require database_path('migrations/2026_09_16_105645_create_feature_preview_settings_table.php'))->up();
    $client = Mockery::mock(FeaturePreviewControlClient::class);
    $client->shouldReceive('request')->with('status')->andReturnUsing(fn (): array => [
        'schema_ready' => Schema::hasTable('feature_preview_settings'), 'enabled' => false,
        'source' => ['database' => 'live_fixture', 'server_fingerprint' => str_repeat('a', 64), 'app_key_fingerprint' => str_repeat('b', 64)],
    ]);
    app()->instance(FeaturePreviewControlClient::class, $client);
    $signature = Mockery::mock(FeaturePreviewControlSignature::class);
    $signature->shouldReceive('configurationIsSafe')->andReturn(true);
    app()->instance(FeaturePreviewControlSignature::class, $signature);
    $guard = Mockery::mock(FeaturePreviewDatabaseGuard::class);
    $guard->shouldReceive('target')->andReturnUsing(fn () => DB::connection());
    app()->instance(FeaturePreviewDatabaseGuard::class, $guard);
    $files = Mockery::mock(FeaturePreviewSnapshotFiles::class);
    $files->shouldReceive('assertConfigurationSafe');
    app()->instance(FeaturePreviewSnapshotFiles::class, $files);
    $identity = Mockery::mock(FeaturePreviewSnapshotIdentityStore::class);
    $identity->shouldReceive('directory')->andReturn(sys_get_temp_dir());
    app()->instance(FeaturePreviewSnapshotIdentityStore::class, $identity);
    app()->usePublicPath(storage_path('framework/preview-public'));
});

afterEach(function (): void {
    DB::purge('preview_configuration_test');
});

test('preview check accepts isolated configuration without enabling preview or writing data', function (?string $broadcaster): void {
    config(['broadcasting.default' => $broadcaster]);
    DB::connection()->enableQueryLog();
    $this->artisan('preview:check')->assertSuccessful();
    expect(FeaturePreviewSetting::query()->count())->toBe(0);
    foreach (DB::connection()->getQueryLog() as $query) {
        expect(strtolower(ltrim($query['query'])))->toStartWith('select');
    }
})->with([null, 'null', 'log']);

test('preview check rejects unsafe runtime configuration', function (string $key, mixed $value, string $message): void {
    config([$key => $value]);
    $this->artisan('preview:check')->expectsOutputToContain($message)->assertFailed();
})->with([
    'main instance' => ['schooltool.preview.instance', false, 'SCHOOLTOOL_PREVIEW_INSTANCE'],
    'debug output' => ['app.debug', true, 'APP_DEBUG'],
    'retained application key' => ['app.previous_keys', ['shared-live-key'], 'previous application'],
    'missing preview URL' => ['schooltool.preview.url', '', 'valid HTTPS'],
    'insecure preview URL' => ['schooltool.preview.url', 'http://preview.example.test', 'valid HTTPS'],
    'embedded credentials' => ['schooltool.preview.url', 'https://secret@preview.example.test', 'valid HTTPS'],
    'same live host' => ['schooltool.preview.live_url', 'https://preview.example.test', 'different hosts'],
    'missing host guard' => ['schooltool.preview.expected_host', '', 'EXPECTED_HOST'],
    'wrong application URL' => ['app.url', 'https://live.example.test', 'APP_URL'],
    'shared sessions' => ['session.driver', 'database', 'file sessions'],
    'session path outside storage' => ['session.files', sys_get_temp_dir(), 'file sessions'],
    'shared cookie name' => ['session.cookie', 'schooltool_session', 'dedicated preview name'],
    'parent domain cookie' => ['session.domain', '.example.test', 'host-only'],
    'insecure cookie' => ['session.secure', false, 'host-only'],
    'script readable cookie' => ['session.http_only', false, 'host-only'],
    'cross site cookie' => ['session.same_site', 'none', 'SameSite'],
    'shared cache' => ['cache.default', 'redis', 'file cache'],
    'external cache path' => ['cache.stores.file.path', sys_get_temp_dir(), 'file cache'],
    'external lock path' => ['cache.stores.file.lock_path', sys_get_temp_dir(), 'file cache'],
    'shared rate limiter' => ['cache.limiter', 'redis', 'isolated default cache'],
    'shared permission cache' => ['permission.cache.store', 'redis', 'isolated default cache'],
    'shared queue' => ['queue.default', 'redis', 'sync queue'],
    'live broadcasting' => ['broadcasting.default', 'reverb', 'Broadcasting'],
    'shared maintenance' => ['app.maintenance.driver', 'cache', 'Maintenance'],
    'shared telemetry' => ['pulse.enabled', true, 'Pulse'],
    'additional Cloudways database' => ['database.connections.cloudways.password', 'prohibited-test-value', 'Remove additional Cloudways'],
    'legacy remote database' => ['schooltool.legacy_restaurant_remote.host', 'database.example.test', 'Remove additional Cloudways'],
    'legacy live control credentials' => ['schooltool.preview.legacy_control_credentials_present', true, 'Remove additional Cloudways'],
    'Cloudways deployment token' => ['services.cloudways.deployment.access_token', 'prohibited-test-value', 'Remove additional Cloudways'],
    'AWS filesystem key' => ['filesystems.disks.s3.key', 'prohibited-test-value', 'Remove additional Cloudways'],
    'AWS queue secret' => ['queue.connections.sqs.secret', 'prohibited-test-value', 'Remove additional Cloudways'],
]);

test('preview check requires a safely configured authenticated main control bridge', function (): void {
    $signature = Mockery::mock(FeaturePreviewControlSignature::class);
    $signature->shouldReceive('configurationIsSafe')->andReturn(false);
    app()->instance(FeaturePreviewControlSignature::class, $signature);
    $this->artisan('preview:check')->expectsOutputToContain('authenticated HTTPS main control bridge')->assertFailed();
});

test('preview check rejects SMTP URL overrides that disable transport security', function (string $option): void {
    config([
        'mail.default' => 'preview_check_smtp',
        'mail.mailers.preview_check_smtp' => [
            'transport' => 'smtp',
            'scheme' => 'smtp',
            'url' => 'smtp://smtp.example.test:587?'.$option,
            'host' => 'smtp.example.test',
            'port' => 587,
            'username' => 'synthetic-preview-user',
            'password' => 'synthetic-preview-password',
            'require_tls' => true,
            'timeout' => 10,
        ],
    ]);

    $this->artisan('preview:check')->expectsOutputToContain('mandatory TLS and certificate verification')->assertFailed();
})->with(['require_tls=false', 'verify_peer=false']);

test('preview check rejects missing shared database migration without running it', function (): void {
    Schema::drop('feature_preview_settings');
    $this->artisan('preview:check')->expectsOutputToContain('approved main-application migration')->assertFailed();
    expect(Schema::hasTable('feature_preview_settings'))->toBeFalse();
});

test('preview check rejects files exposed directly in the web root', function (): void {
    app()->usePublicPath(storage_path('framework/preview-public-'.uniqid()));
    mkdir(public_path('storage'), 0777, true);
    $this->artisan('preview:check')->expectsOutputToContain('public/storage')->assertFailed();
});

test('preview has no application schedules while main retains them', function (bool $preview): void {
    config(['schooltool.preview.instance' => $preview]);
    Schedule::swap(new ScheduleManager);
    require base_path('routes/console.php');
    expect(Schedule::events() === [])->toBe($preview);
})->with([true, false]);

test('preview instance flag is read safely from environment configuration', function (?string $flag, bool $expected): void {
    $name = 'SCHOOLTOOL_PREVIEW_INSTANCE';
    $previousEnv = $_ENV[$name] ?? null;
    $previousServer = $_SERVER[$name] ?? null;
    $previousProcess = getenv($name);
    if ($flag === null) {
        unset($_ENV[$name], $_SERVER[$name]);
        putenv($name);
    } else {
        $_ENV[$name] = $_SERVER[$name] = $flag;
        putenv("{$name}={$flag}");
    }

    try {
        $configuration = require config_path('schooltool.php');
        expect($configuration['preview']['instance'])->toBe($expected);
    } finally {
        if ($previousEnv === null) {
            unset($_ENV[$name]);
        } else {
            $_ENV[$name] = $previousEnv;
        }
        if ($previousServer === null) {
            unset($_SERVER[$name]);
        } else {
            $_SERVER[$name] = $previousServer;
        }
        putenv($previousProcess === false ? $name : "{$name}={$previousProcess}");
    }
})->with([
    'absent preserves main' => [null, false],
    'false preserves main' => ['false', false],
    'enabled' => ['true', true],
    'shell compatible enabled' => ['yes', true],
    'numeric enabled' => ['1', true],
    'invalid stays disabled' => ['invalid', false],
]);
