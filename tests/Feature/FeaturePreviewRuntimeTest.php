<?php

use App\Services\FeaturePreviewDatabaseGuard;
use App\Services\FeaturePreviewRuntimeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Connectors\ConnectorInterface;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->originalStoragePath = storage_path();
    $this->runtimeStorage = storage_path('framework/testing/preview-runtime-'.bin2hex(random_bytes(6)));
    (new Filesystem)->makeDirectory($this->runtimeStorage, 0775, true);
    app()->useStoragePath($this->runtimeStorage);
    config([
        'schooltool.preview.instance' => true,
        'filesystems.disks.local' => ['driver' => 'local', 'root' => storage_path('app/private'), 'throw' => true],
        'filesystems.disks.public' => ['driver' => 'local', 'root' => storage_path('app/public'), 'throw' => true],
        'queue.connections.sync' => ['driver' => 'sync'],
        'app.key' => 'base64:'.base64_encode(str_repeat('r', 32)),
    ]);
    Redis::swap(new RedisManager(app(), config('database.redis.client', 'phpredis'), config('database.redis')));
});

afterEach(function (): void {
    app()->useStoragePath($this->originalStoragePath);
    (new Filesystem)->deleteDirectory($this->runtimeStorage);
});

test('preview stops outbound HTTP before a request reaches its handler', function (): void {
    $sent = false;
    Http::fake(function () use (&$sent) {
        $sent = true;

        return Http::response('must not be sent');
    });
    app(FeaturePreviewRuntimeService::class)->install();

    expect(fn () => Http::post('https://api.example.test/mutate', ['secret' => 'preview']))
        ->toThrow(RuntimeException::class, 'Outbound HTTP integrations are disabled');
    expect($sent)->toBeFalse();
});

test('preview denies configured and on demand remote filesystems before constructing a client', function (string $driver): void {
    config(['filesystems.disks.external' => ['driver' => $driver, 'bucket' => 'production']]);
    app(FeaturePreviewRuntimeService::class)->install();

    expect(fn () => Storage::disk('external')->delete('important.pdf'))
        ->toThrow(RuntimeException::class, 'Remote filesystems are disabled');
    expect(fn () => Storage::build(['driver' => $driver, 'bucket' => 'production']))
        ->toThrow(RuntimeException::class, 'Remote filesystems are disabled');
})->with(['s3', 'ftp', 'sftp', 'scoped']);

test('preview permits local storage but refuses external local paths including traversal', function (): void {
    app(FeaturePreviewRuntimeService::class)->install();
    Storage::disk('local')->put('preview.txt', 'isolated');
    expect(Storage::disk('local')->get('preview.txt'))->toBe('isolated');

    foreach ([sys_get_temp_dir(), storage_path('../outside'), base_path('public')] as $unsafeRoot) {
        expect(fn () => Storage::build(['driver' => 'local', 'root' => $unsafeRoot]))
            ->toThrow(RuntimeException::class, 'Preview filesystem roots must stay inside');
    }
});

test('preview refuses a storage root outside its application', function (): void {
    app()->useStoragePath(sys_get_temp_dir());
    expect(app(FeaturePreviewRuntimeService::class)->ownsStoragePath(sys_get_temp_dir()))->toBeFalse();
});

test('preview rejects explicit Redis and external queue connections while sync jobs work', function (string $queueDriver): void {
    config(['queue.connections.external' => ['driver' => $queueDriver]]);
    app(FeaturePreviewRuntimeService::class)->install();

    expect(fn () => Redis::connection('default')->set('production-key', 'changed'))
        ->toThrow(RuntimeException::class, 'Redis connections are disabled');
    expect(fn () => Queue::connection('external'))
        ->toThrow(RuntimeException::class, 'Only synchronous local jobs are permitted');

    PreviewRuntimeLocalJob::$handled = false;
    Queue::connection('sync')->push(new PreviewRuntimeLocalJob);
    expect(PreviewRuntimeLocalJob::$handled)->toBeTrue();
})->with(['redis', 'database', 'sqs', 'beanstalkd', 'deferred', 'background', 'failover']);

test('runtime protections leave main HTTP and storage behavior unchanged', function (): void {
    config(['schooltool.preview.instance' => false]);
    Http::fake(['api.example.test/*' => Http::response(['ok' => true])]);
    app(FeaturePreviewRuntimeService::class)->install();

    expect(Http::post('https://api.example.test/mutate')->json('ok'))->toBeTrue();
    Storage::disk('local')->put('main.txt', 'main');
    expect(Storage::disk('local')->get('main.txt'))->toBe('main');
});

test('preview stops direct remote broadcasts while its null broadcaster remains usable', function (string $driver): void {
    config(['broadcasting.connections.external' => ['driver' => $driver]]);
    app(FeaturePreviewRuntimeService::class)->install();

    expect(fn () => Broadcast::connection('external')->broadcast(['private-test'], 'changed', []))
        ->toThrow(RuntimeException::class, 'Remote broadcasts are disabled');
    Broadcast::connection('null')->broadcast(['private-test'], 'changed', []);
})->with(['pusher', 'reverb', 'ably', 'redis']);

test('preview prevents remote and custom log handlers while retaining private local audit logs', function (string $driver): void {
    config([
        'logging.channels.external' => ['driver' => $driver, 'handler' => 'MissingRemoteHandler', 'via' => 'MissingCustomLogger'],
        'logging.channels.single' => ['driver' => 'single', 'path' => storage_path('logs/audit.log')],
    ]);
    app(FeaturePreviewRuntimeService::class)->install();

    Log::channel('external')->critical('must not leave the preview');
    Log::build(['driver' => $driver, 'handler' => 'MissingRemoteHandler', 'via' => 'MissingCustomLogger'])->critical('blocked');
    Log::channel('single')->info('preview audit metadata');

    expect(file_get_contents(storage_path('logs/audit.log')))->toContain('preview audit metadata')
        ->not->toContain('must not leave the preview', 'MissingRemoteHandler', 'MissingCustomLogger');
})->with(['slack', 'monolog', 'custom', 'syslog', 'errorlog']);

test('preview refuses explicit shared cache stores while local cache remains usable', function (string $driver): void {
    config(['cache.stores.external' => ['driver' => $driver]]);
    app(FeaturePreviewRuntimeService::class)->install();

    expect(fn () => Cache::store('external')->put('live-key', 'changed'))
        ->toThrow(RuntimeException::class, 'Shared cache stores are disabled');
    Cache::store('array')->put('preview-key', 'isolated');
    expect(Cache::store('array')->get('preview-key'))->toBe('isolated');
})->with(['memcached', 'redis', 'dynamodb', 'database', 'octane', 'apc', 'failover']);

test('preview permits only its captured target database and preserves already opened target data', function (): void {
    config([
        'database.default' => 'runtime_target',
        'database.connections.runtime_target' => ['driver' => 'sqlite', 'database' => ':memory:'],
        'database.connections.preview_control' => ['driver' => 'sqlite', 'database' => ':memory:'],
    ]);
    $target = DB::connection();
    $target->statement('CREATE TABLE runtime_probe (value TEXT)');
    $target->table('runtime_probe')->insert(['value' => 'retained']);
    app(FeaturePreviewRuntimeService::class)->install();

    expect(DB::connection())->toBe($target)
        ->and(DB::table('runtime_probe')->value('value'))->toBe('retained');
    expect(fn () => DB::connection('preview_control')->scalar('SELECT 1'))
        ->toThrow(RuntimeException::class, 'Only the configured preview database');
});

test('preview refuses legacy live SQL credentials after removing the connection configuration', function (): void {
    config(['schooltool.preview.legacy_control_credentials_present' => true]);
    expect(app(FeaturePreviewRuntimeService::class)->integrationCredentialsAreAbsent())->toBeFalse();
});

test('preview refuses additional named and dynamic SQL connections before their connector is called', function (string $driver): void {
    config(['database.connections.external' => ['driver' => $driver, 'database' => 'live', 'host' => 'live.example.test']]);
    $connector = Mockery::mock(ConnectorInterface::class);
    $connector->shouldNotReceive('connect');
    app()->instance('db.connector.'.$driver, $connector);
    app(FeaturePreviewRuntimeService::class)->install();

    foreach ([1, 2] as $attempt) {
        expect(fn () => DB::connection('external')->getPdo())
            ->toThrow(RuntimeException::class, 'Only the configured preview');
        expect(fn () => DB::build(config('database.connections.external'))->getPdo())
            ->toThrow(RuntimeException::class, 'Only the configured preview');
    }
    Event::fake([ConnectionEstablished::class]);
    expect(fn () => DB::build(config('database.connections.external'))->getPdo())
        ->toThrow(RuntimeException::class, 'Only the configured preview');
})->with(['mysql', 'mariadb', 'pgsql', 'sqlite', 'sqlsrv']);

test('preview refuses reconfiguration and forged on demand use of an allowed connection name', function (): void {
    config([
        'database.default' => 'runtime_target',
        'database.connections.runtime_target' => ['driver' => 'sqlite', 'database' => ':memory:'],
    ]);
    app(FeaturePreviewRuntimeService::class)->install();
    $foreign = ['driver' => 'sqlite', 'database' => '/must-never-be-opened.sqlite'];

    expect(fn () => DB::connectUsing('runtime_target', $foreign)->getPdo())
        ->toThrow(RuntimeException::class, 'Only the configured preview');
    expect(fn () => DB::build([...$foreign, 'name' => 'runtime_target'])->getPdo())
        ->toThrow(RuntimeException::class, 'Only the configured preview');
    config(['database.connections.runtime_target' => $foreign]);
    expect(fn () => DB::connection()->getPdo())
        ->toThrow(RuntimeException::class, 'Only the configured preview');
});

test('snapshot connection cannot change the captured preview credentials or hardening', function (string $change): void {
    config(['database.default' => 'mysql']);
    $configuration = FeaturePreviewDatabaseGuard::snapshotConfiguration(config('database.connections.mysql'));
    $connector = Mockery::mock(ConnectorInterface::class);
    $connector->shouldNotReceive('connect');
    app()->instance('db.connector.mysql', $connector);
    app(FeaturePreviewRuntimeService::class)->install();
    $name = 'preview_snapshot_target';
    match ($change) {
        'host', 'database', 'username', 'password' => $configuration[$change] = 'foreign',
        'port' => $configuration['port'] = 3307,
        'persistent' => $configuration['options'][PDO::ATTR_PERSISTENT] = true,
        'multiple statements' => $configuration['options'][PDO::MYSQL_ATTR_MULTI_STATEMENTS] = true,
        'local files' => $configuration['options'][PDO::MYSQL_ATTR_LOCAL_INFILE] = true,
        'initial command' => $configuration['options'][PDO::MYSQL_ATTR_INIT_COMMAND] = 'SELECT 1',
        'source alias' => $name = 'preview_snapshot_source',
    };
    expect(fn () => app('db.factory')->make($configuration, $name)->getPdo())
        ->toThrow(RuntimeException::class, 'Only the configured preview');
})->with(['host', 'database', 'username', 'password', 'port', 'persistent', 'multiple statements', 'local files', 'initial command', 'source alias']);

test('preview closes a previously opened additional database and blocks retained connection references', function (): void {
    config(['database.connections.external' => ['driver' => 'sqlite', 'database' => ':memory:']]);
    $external = DB::connection('external');
    expect($external->scalar('SELECT 1'))->toBe(1);
    app(FeaturePreviewRuntimeService::class)->install();

    expect(fn () => $external->scalar('SELECT 1'))->toThrow(RuntimeException::class, 'Only the configured preview')
        ->and(fn () => DB::connection('external'))->toThrow(RuntimeException::class, 'Only the configured preview');
});

class PreviewRuntimeLocalJob implements ShouldQueue
{
    public static bool $handled = false;

    public function handle(): void
    {
        self::$handled = true;
    }
}
