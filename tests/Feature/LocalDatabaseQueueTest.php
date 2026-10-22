<?php

use App\Providers\HorizonServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Horizon\Horizon;
use Symfony\Component\Process\Process;

require_once __DIR__.'/../Support/MultipleChoiceTestDatabase.php';
requireMultipleChoiceTestDatabase();

class LocalDatabaseQueueProbeJob implements ShouldQueue
{
    use Queueable;

    public static array $executed = [];

    public function __construct(public string $marker) {}

    public function handle(): void
    {
        self::$executed[] = $this->marker;
    }
}

class UniqueLocalDatabaseQueueProbeJob extends LocalDatabaseQueueProbeJob implements ShouldBeUnique
{
    public int $uniqueFor = 60;

    public function uniqueId(): string
    {
        return $this->marker;
    }
}

class OverlappingLocalDatabaseQueueProbeJob extends LocalDatabaseQueueProbeJob
{
    /** @return array<int, WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->marker))->releaseAfter(1)->expireAfter(60)];
    }
}

beforeEach(function (): void {
    expect(DB::connection()->getDatabaseName())->toBe(getenv('DB_DATABASE'))
        ->toMatch('/^pest_test_test_[0-9]{24}$/');
    $this->queueTable = 'queue_probe_'.Str::lower(Str::random(16));
    $this->queueCachePath = storage_path('framework/cache/'.$this->queueTable);
    Schema::create($this->queueTable, function (Blueprint $table): void {
        $table->id();
        $table->string('queue')->index();
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
    });
    config([
        'queue.default' => 'database', 'queue.connections.database.table' => $this->queueTable,
        'queue.connections.database.retry_after' => 2, 'queue.connections.database.after_commit' => false,
        'cache.default' => 'file', 'cache.stores.file.path' => $this->queueCachePath,
        'cache.stores.file.lock_path' => $this->queueCachePath,
        'services.healthcheck.worker_ping_url' => null, 'services.healthcheck.scheduler_ping_url' => null,
    ]);
    Cache::forgetDriver('file');
    Redis::shouldReceive('connection')->never();
    Http::preventStrayRequests();
    Http::fake();
    Mail::fake();
    Notification::fake();
    LocalDatabaseQueueProbeJob::$executed = [];
});

afterEach(function (): void {
    $this->travelBack();
    if (isset($this->queueTable)) {
        Schema::dropIfExists($this->queueTable);
        Cache::forgetDriver('file');
        if (str_starts_with($this->queueCachePath, storage_path('framework/cache/queue_probe_'))) {
            app('files')->deleteDirectory($this->queueCachePath);
        }
    }
    Http::assertNothingSent();
    Mail::assertNothingSent();
    Notification::assertNothingSent();
});

it('routes all six application queues and consumes an asynchronous job using the configured default', function (): void {
    foreach (['critical', 'notifications', 'default', 'imports', 'materials', 'maintenance'] as $name) {
        Queue::push(new LocalDatabaseQueueProbeJob($name), queue: $name);
        expect(Queue::size($name))->toBe(1);
    }
    expect(LocalDatabaseQueueProbeJob::$executed)->toBe([]);
    $this->artisan('queue:work --once --queue=critical,notifications --sleep=0 --tries=3')->assertSuccessful();
    expect(LocalDatabaseQueueProbeJob::$executed)->toBe(['critical'])
        ->and(Queue::size('critical'))->toBe(0)->and(Queue::size('notifications'))->toBe(1);
});

it('retains delayed releases attempts and expired reservations', function (): void {
    $this->freezeTime();
    Queue::later(60, new LocalDatabaseQueueProbeJob('delayed'));
    expect(Queue::pop())->toBeNull();
    $this->travel(61)->seconds();
    $job = Queue::pop();
    expect($job)->not->toBeNull()->and($job->attempts())->toBe(1);
    $uuid = $job->uuid();
    $job->release(3);
    expect(Queue::pop())->toBeNull();
    $this->travel(4)->seconds();
    $job = Queue::pop();
    expect($job->uuid())->toBe($uuid)->and($job->attempts())->toBe(2);
    $id = $job->getJobId();
    $this->travel(3)->seconds();
    $recovered = Queue::pop();
    expect($recovered->getJobId())->toEqual($id)->and($recovered->attempts())->toBe(3);
    $recovered->fire();
    expect(LocalDatabaseQueueProbeJob::$executed)->toBe(['delayed'])->and(Queue::size())->toBe(0);
});

it('honours explicit after-commit dispatch and rollback', function (): void {
    DB::beginTransaction();
    Queue::push((new LocalDatabaseQueueProbeJob('committed'))->afterCommit());
    expect(Queue::size())->toBe(0);
    DB::commit();
    expect(Queue::size())->toBe(1);
    DB::beginTransaction();
    Queue::push((new LocalDatabaseQueueProbeJob('rolled-back'))->afterCommit());
    DB::rollBack();
    expect(Queue::size())->toBe(1);
    Queue::pop()->fire();
    expect(LocalDatabaseQueueProbeJob::$executed)->toBe(['committed']);
});

it('keeps unique-job locks with the local file cache', function (): void {
    UniqueLocalDatabaseQueueProbeJob::dispatch('unique');
    UniqueLocalDatabaseQueueProbeJob::dispatch('unique');
    expect(Queue::size())->toBe(1);
    Queue::pop()->fire();
    UniqueLocalDatabaseQueueProbeJob::dispatch('unique');
    expect(Queue::size())->toBe(1)->and(LocalDatabaseQueueProbeJob::$executed)->toBe(['unique']);
});

it('releases overlapping work until the local file lock is available', function (): void {
    $this->freezeTime();
    $probe = new OverlappingLocalDatabaseQueueProbeJob('overlap');
    $middleware = $probe->middleware()[0];
    $lock = Cache::lock($middleware->getLockKey($probe), 60);
    expect($lock->get())->toBeTrue();
    Queue::push($probe);
    Queue::pop()->fire();
    expect(LocalDatabaseQueueProbeJob::$executed)->toBe([])->and(Queue::size())->toBe(1);
    $lock->release();
    $this->travel(2)->seconds();
    Queue::pop()->fire();
    expect(LocalDatabaseQueueProbeJob::$executed)->toBe(['overlap']);
});

it('reserves different jobs across two actual MySQL workers', function (): void {
    $resultFile = storage_path('app/'.$this->queueTable.'.jsonl');
    foreach ([1, 2] as $number) {
        Queue::push(Closure::bind(static function () use ($resultFile, $number): void {
            usleep(200000);
            file_put_contents($resultFile, $number.PHP_EOL, FILE_APPEND | LOCK_EX);
        }, null, null));
    }
    $environment = [
        'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'),
        'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306',
        'DB_DATABASE' => DB::connection()->getDatabaseName(), 'DB_DATABASE_TEST' => DB::connection()->getDatabaseName(),
        'DB_USERNAME' => 'root', 'DB_PASSWORD' => '', 'DB_QUEUE_TABLE' => $this->queueTable,
        'QUEUE_CONNECTION' => 'database', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
        'MAIL_MAILER' => 'array', 'BROADCAST_CONNECTION' => 'log',
        'REDIS_HOST' => '127.0.0.1', 'REDIS_PORT' => '16379', 'REDIS_DB' => '0',
    ];
    $workers = [];
    try {
        foreach ([1, 2] as $worker) {
            $process = new Process([PHP_BINARY, 'artisan', 'queue:work', '--once', '--sleep=0', '--tries=1', '--timeout=20'], base_path(), $environment);
            $process->setTimeout(45)->start();
            $workers[] = $process;
        }
        foreach ($workers as $process) {
            $process->wait();
            expect($process->isSuccessful())->toBeTrue($process->getErrorOutput().$process->getOutput());
        }
        expect(is_file($resultFile))->toBeTrue(implode(PHP_EOL, array_map(
            fn (Process $process): string => $process->getOutput().$process->getErrorOutput(), $workers,
        )));
        $numbers = file($resultFile, FILE_IGNORE_NEW_LINES);
        sort($numbers);
        expect($numbers)->toBe(['1', '2'])->and(Queue::size())->toBe(0);
    } finally {
        foreach ($workers as $process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }
        @unlink($resultFile);
    }
});

it('skips local database Horizon snapshots and dashboard access while retaining production authorization', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'horizon:snapshot'));
    expect($event)->not->toBeNull();
    app()->instance('env', 'local');
    expect($event->filtersPass(app()))->toBeFalse();
    (new HorizonServiceProvider(app()))->boot();
    expect(Horizon::check(Request::create('/horizon')))->toBeFalse();
    config(['queue.default' => 'redis']);
    expect($event->filtersPass(app()))->toBeTrue();
    (new HorizonServiceProvider(app()))->boot();
    expect(Horizon::check(Request::create('/horizon')))->toBeTrue();
    app()->instance('env', 'production');
    (new HorizonServiceProvider(app()))->boot();
    expect($event->filtersPass(app()))->toBeTrue()
        ->and(Horizon::check(Request::create('/horizon')))->toBeFalse();
});
