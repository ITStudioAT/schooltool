<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Connection;
use Illuminate\Database\Connectors\ConnectorInterface;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PDO;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Throwable;

class FeaturePreviewRuntimeService
{
    public function install(): void
    {
        if (! config('schooltool.preview.instance', false)) {
            return;
        }

        if (! $this->ownsStoragePath(storage_path())) {
            throw new RuntimeException('Preview storage must stay inside this application directory.');
        }

        $this->restrictDatabaseConnections();

        Http::globalMiddleware(function (callable $handler): \Closure {
            return function (RequestInterface $request, array $options) use ($handler) {
                try {
                    $signature = app(FeaturePreviewControlSignature::class);
                    if ($request->getMethod() !== 'POST' || (string) $request->getUri() !== $signature->url()
                        || ($options['verify'] ?? null) !== true || ($options['allow_redirects'] ?? null) !== false
                        || ($options['proxy'] ?? null) !== '' || ! empty($options['curl']) || ! empty($options['debug'])
                        || ($options['timeout'] ?? 0) <= 0 || $options['timeout'] > 5
                        || ($options['connect_timeout'] ?? 0) <= 0 || $options['connect_timeout'] > 2
                        || ($request->getBody()->getSize() ?? PHP_INT_MAX) > FeaturePreviewControlSignature::MAX_BODY_BYTES) {
                        throw new RuntimeException('Invalid preview control transport.');
                    }
                    $headers = [];
                    foreach ([FeaturePreviewControlSignature::TIMESTAMP, FeaturePreviewControlSignature::NONCE, FeaturePreviewControlSignature::SIGNATURE] as $name) {
                        $headers[$name] = $request->getHeaderLine($name);
                    }
                    $position = $request->getBody()->tell();
                    $body = (string) $request->getBody();
                    $request->getBody()->seek($position);
                    $signature->validateRequest($body, $headers);
                } catch (Throwable) {
                    throw new RuntimeException('Outbound HTTP integrations are disabled in the preview; only the signed live control endpoint is permitted.');
                }

                return $handler($request, $options);
            };
        });

        foreach (config('logging.channels', []) as $channel => $configuration) {
            if (in_array($configuration['driver'] ?? null, ['single', 'daily'], true)
                && (! is_string($configuration['path'] ?? null) || ! $this->ownsStoragePath(dirname($configuration['path'])))) {
                config(['logging.channels.'.$channel.'.path' => storage_path('logs/'.preg_replace('/[^a-zA-Z0-9_-]/', '_', $channel).'.log')]);
            }
        }
        config(['logging.channels.emergency.path' => storage_path('logs/laravel.log')]);

        foreach (array_keys(Log::getChannels()) as $channel) {
            Log::forgetChannel($channel);
        }
        $loggingDrivers = array_unique(array_merge(
            ['slack', 'monolog', 'custom', 'syslog', 'errorlog'],
            array_column(config('logging.channels', []), 'driver'),
        ));
        foreach ($loggingDrivers as $driver) {
            if (! in_array($driver, ['single', 'daily', 'stack'], true)) {
                Log::extend($driver, fn (): Logger => new Logger('preview-blocked', [new NullHandler]));
            }
        }

        $filesystems = app(FilesystemManager::class);
        $filesystems->forgetDisk(array_keys(config('filesystems.disks', [])));
        $runtime = $this;
        $filesystems->extend('local', function ($app, array $configuration) use ($filesystems, $runtime): Filesystem {
            if (! $runtime->ownsStoragePath($configuration['root'] ?? null)) {
                throw new RuntimeException('Preview filesystem roots must stay inside this application storage directory.');
            }

            return $filesystems->createLocalDriver($configuration);
        });

        $drivers = array_unique(array_merge(
            ['s3', 'ftp', 'sftp', 'scoped'],
            array_column(config('filesystems.disks', []), 'driver'),
        ));

        foreach ($drivers as $driver) {
            if ($driver !== 'local') {
                Storage::extend($driver, function (): never {
                    throw new RuntimeException('Remote filesystems are disabled in the preview.');
                });
            }
        }

        foreach (Redis::connections() ?: [] as $name => $connection) {
            Redis::purge($name);
        }

        foreach (array_unique(['predis', 'phpredis', config('database.redis.client', 'phpredis')]) as $driver) {
            Redis::extend($driver, function (): never {
                throw new RuntimeException('Redis connections are disabled in the preview.');
            });
        }

        Cache::forgetDriver(array_keys(config('cache.stores', [])));
        $cacheDrivers = array_unique(array_merge(
            ['database', 'memcached', 'redis', 'dynamodb', 'octane', 'apc', 'failover'],
            array_column(config('cache.stores', []), 'driver'),
        ));
        foreach ($cacheDrivers as $driver) {
            if (! in_array($driver, ['file', 'array', 'null'], true)) {
                Cache::extend($driver, function (): never {
                    throw new RuntimeException('Shared cache stores are disabled in the preview.');
                });
            }
        }

        $queueDrivers = array_unique(array_merge(
            ['database', 'redis', 'sqs', 'beanstalkd', 'failover', 'background', 'deferred'],
            array_column(config('queue.connections', []), 'driver'),
        ));

        foreach ($queueDrivers as $driver) {
            if (! in_array($driver, ['sync', 'null'], true)) {
                Queue::extend($driver, function (): never {
                    throw new RuntimeException('Only synchronous local jobs are permitted in the preview.');
                });
            }
        }

        Broadcast::forgetDrivers();
        $broadcastDrivers = array_unique(array_merge(
            ['reverb', 'pusher', 'ably', 'redis'],
            array_column(config('broadcasting.connections', []), 'driver'),
        ));
        foreach ($broadcastDrivers as $driver) {
            if (! in_array($driver, ['null', 'log'], true)) {
                Broadcast::extend($driver, function (): never {
                    throw new RuntimeException('Remote broadcasts are disabled in the preview.');
                });
            }
        }
    }

    public function ownsStoragePath(mixed $path): bool
    {
        if (! is_string($path) || $path === '' || preg_match('~(?:^|[\\\\/])\.\.?([\\\\/]|$)~', $path)) {
            return false;
        }

        $application = realpath(base_path());
        $storage = realpath(storage_path());
        if ($application === false || $storage === false || is_file($path)) {
            return false;
        }

        $candidate = $path;
        while (! file_exists($candidate) && ! is_link($candidate)) {
            $parent = dirname($candidate);
            if ($parent === $candidate || $parent === '.') {
                return false;
            }
            $candidate = $parent;
        }

        $resolved = realpath($candidate);
        if ($resolved === false) {
            return false;
        }

        $normalize = fn (string $value): string => PHP_OS_FAMILY === 'Windows'
            ? strtolower(str_replace('\\', '/', rtrim($value, '/\\'))).'/'
            : str_replace('\\', '/', rtrim($value, '/\\')).'/';
        $application = $normalize($application);
        $storage = $normalize($storage);

        return str_starts_with($storage, $application)
            && str_starts_with($normalize($resolved), $storage);
    }

    public function integrationCredentialsAreAbsent(): bool
    {
        if (config('schooltool.preview.legacy_control_credentials_present', false)) {
            return false;
        }
        foreach ([
            'database.connections.cloudways.host', 'database.connections.cloudways.database',
            'database.connections.cloudways.username', 'database.connections.cloudways.password',
            'schooltool.legacy_restaurant_remote.host', 'schooltool.legacy_restaurant_remote.database',
            'schooltool.legacy_restaurant_remote.username', 'schooltool.legacy_restaurant_remote.password',
            'services.cloudways.deployment.access_token',
            'filesystems.disks.s3.key', 'filesystems.disks.s3.secret',
            'services.ses.key', 'services.ses.secret',
            'queue.connections.sqs.key', 'queue.connections.sqs.secret',
            'cache.stores.dynamodb.key', 'cache.stores.dynamodb.secret',
            'database.connections.preview_control.host', 'database.connections.preview_control.database',
            'database.connections.preview_control.username', 'database.connections.preview_control.password',
        ] as $key) {
            if (config($key) !== null && config($key) !== '') {
                return false;
            }
        }

        return true;
    }

    private function restrictDatabaseConnections(): void
    {
        $allowed = [];
        foreach ([(string) config('database.default')] as $name) {
            $configuration = config('database.connections.'.$name);
            if (is_array($configuration)) {
                $allowed[$name] = $this->normalizedDatabaseConfiguration($configuration, $name);
                if (in_array($configuration['driver'] ?? null, ['mysql', 'mariadb'], true)) {
                    // The snapshot uses a separate PDO, with the same captured credentials and stricter options.
                    $snapshot = FeaturePreviewDatabaseGuard::snapshotConfiguration($configuration);
                    $snapshot['name'] = 'preview_snapshot_target';
                    $allowed['preview_snapshot_target'] = $this->normalizedDatabaseConfiguration($snapshot, 'preview_snapshot_target');
                }
            }
        }
        $assertAllowed = function (array $configuration, string $name) use ($allowed): void {
            if (! isset($allowed[$name]) || $this->normalizedDatabaseConfiguration($configuration, $name) !== $allowed[$name]) {
                throw new RuntimeException('Only the configured preview database connection is permitted.');
            }
        };

        $manager = DB::getFacadeRoot();
        foreach ($manager->getConnections() as $name => $connection) {
            try {
                $assertAllowed($connection->getConfig(), $connection->getName());
            } catch (RuntimeException) {
                $manager->purge($name);
            }
        }

        $factory = app('db.factory');
        foreach (['mysql', 'mariadb', 'pgsql', 'sqlite', 'sqlsrv'] as $driver) {
            $connector = $factory->createConnector(['driver' => $driver]);
            app()->instance('db.connector.'.$driver, new class($connector, $assertAllowed) implements ConnectorInterface
            {
                public function __construct(private ConnectorInterface $connector, private \Closure $assertAllowed) {}

                public function connect(array $config): PDO
                {
                    ($this->assertAllowed)($config, (string) ($config['name'] ?? ''));

                    return $this->connector->connect($config);
                }
            });
            $manager->extend($driver, function (array $configuration, string $name) use ($assertAllowed, $factory): Connection {
                $assertAllowed($configuration, $name);

                return $factory->make($configuration, $name);
            });
        }

        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event) use ($assertAllowed, $manager): void {
            try {
                $assertAllowed($event->connection->getConfig(), $event->connection->getName());
            } catch (RuntimeException $exception) {
                $manager->purge($event->connection->getNameWithReadWriteType());
                throw $exception;
            }
        });
    }

    /** @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    private function normalizedDatabaseConfiguration(array $configuration, string $name): array
    {
        $configuration = (new ConfigurationUrlParser)->parseConfiguration($configuration);
        $configuration['prefix'] ??= '';
        $configuration['name'] ??= $name;
        ksort($configuration);

        return $configuration;
    }
}
