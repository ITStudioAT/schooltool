<?php

use App\Services\FeaturePreviewControlClient;
use App\Services\FeaturePreviewDatabaseGuard;
use App\Services\FeaturePreviewRuntimeService;
use Illuminate\Database\Connection;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;

test('preview cannot open the main snapshot connection or retrieve its identity', function (): void {
    config(['schooltool.preview.instance' => true, 'database.default' => 'missing_snapshot_connection']);
    $called = false;
    $guard = app(FeaturePreviewDatabaseGuard::class);

    expect(fn () => $guard->withMainReadOnlyConnection(function () use (&$called): void {
        $called = true;
    }))->toThrow(RuntimeException::class)
        ->and(fn () => $guard->mainIdentity())->toThrow(RuntimeException::class)
        ->and($called)->toBeFalse();
});

describe('real MySQL main snapshot connection', function (): void {
    beforeEach(function (): void {
        $this->snapshotCreatedDatabase = null;
        $this->snapshotCreatedUser = null;
        $this->snapshotAdmin = null;

        if (getenv('SCHOOLTOOL_SNAPSHOT_MYSQL_TEST') !== '1') {
            $this->markTestSkipped('Explicit opt-in for disposable local MySQL schemas and users.');
        }

        $base = config('database.connections.mysql');
        expect($base['host'])->toBeIn(['127.0.0.1', 'localhost']);
        $this->snapshotAdmin = new PDO('mysql:host=127.0.0.1;port='.(int) $base['port'].';charset=utf8mb4', $base['username'], $base['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $admin = $this->snapshotAdmin;
        $database = 'streadonly'.bin2hex(random_bytes(8));
        $username = 'streader'.bin2hex(random_bytes(8));
        $password = bin2hex(random_bytes(24));

        $admin->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->snapshotCreatedDatabase = $database;
        $admin->exec('CREATE USER '.$admin->quote($username)."@'127.0.0.1' IDENTIFIED BY ".$admin->quote($password));
        $this->snapshotCreatedUser = $username;
        $admin->exec('GRANT ALL PRIVILEGES ON `'.$database.'`.* TO '.$admin->quote($username)."@'127.0.0.1'");

        $configuration = array_replace($base, [
            'host' => '127.0.0.1', 'database' => $database, 'username' => $username,
            'password' => $password, 'url' => null, 'unix_socket' => '', 'prefix' => '',
        ]);
        unset($configuration['read'], $configuration['write']);
        config([
            'schooltool.preview.instance' => false,
            'database.default' => 'snapshot_readonly_main',
            'database.connections.snapshot_readonly_main' => $configuration,
            'database.connections.snapshot_readonly_writer' => $configuration,
        ]);
        DB::purge('snapshot_readonly_main');
        DB::purge('snapshot_readonly_writer');
        $source = DB::connection();
        $source->statement('CREATE TABLE records (id BIGINT PRIMARY KEY, content VARCHAR(255)) ENGINE=InnoDB');
        $source->table('records')->insert(['id' => 1, 'content' => 'original']);
    });

    afterEach(function (): void {
        DB::purge('snapshot_readonly_main');
        DB::purge('snapshot_readonly_writer');

        try {
            if ($this->snapshotCreatedDatabase !== null) {
                $this->snapshotAdmin->exec('DROP DATABASE `'.$this->snapshotCreatedDatabase.'`');
            }
        } finally {
            if ($this->snapshotCreatedUser !== null) {
                $this->snapshotAdmin->exec('DROP USER '.$this->snapshotAdmin->quote($this->snapshotCreatedUser)."@'127.0.0.1'");
            }
        }
    });

    test('pins a consistent snapshot before the callback and leaves the default connection writable', function (): void {
        $default = DB::connection();
        $writer = DB::connection('snapshot_readonly_writer');
        $defaultPdo = $default->getPdo();
        $captured = null;
        $snapshotPdo = null;

        $result = app(FeaturePreviewDatabaseGuard::class)->withMainReadOnlyConnection(function (Connection $connection) use ($writer, $defaultPdo, &$captured, &$snapshotPdo): string {
            $captured = $connection;
            $snapshotPdo = $connection->getPdo();
            expect($snapshotPdo)->not->toBe($defaultPdo)
                ->and($snapshotPdo->inTransaction())->toBeTrue();

            $writer->table('records')->where('id', 1)->update(['content' => 'concurrent first change']);
            $writer->table('records')->insert(['id' => 2, 'content' => 'concurrent new record']);
            expect($connection->table('records')->value('content'))->toBe('original')
                ->and($connection->table('records')->count())->toBe(1);

            $writer->table('records')->where('id', 1)->update(['content' => 'concurrent second change']);
            expect($connection->table('records')->value('content'))->toBe('original');

            return 'callback result';
        });

        expect($result)->toBe('callback result')
            ->and($captured->getRawPdo())->toBeNull()
            ->and($snapshotPdo->inTransaction())->toBeFalse()
            ->and($default->getPdo())->toBe($defaultPdo)
            ->and($default->table('records')->where('id', 1)->value('content'))->toBe('concurrent second change');
        expect($default->table('records')->where('id', 1)->update(['content' => 'ordinary main write']))->toBe(1);
    });

    test('keeps the dedicated snapshot alive during file streaming without changing other sessions', function (int $initialTimeout): void {
        $default = DB::connection()->getPdo();
        $defaultTimeout = (int) $default->query('SELECT @@SESSION.wait_timeout')->fetchColumn();
        $globalTimeout = (int) $default->query('SELECT @@GLOBAL.wait_timeout')->fetchColumn();
        $factory = app('db.factory');
        $testFactory = Mockery::mock($factory);
        $testFactory->shouldReceive('make')->once()->andReturnUsing(function (array $configuration, string $name) use ($factory, $initialTimeout): Connection {
            $connection = $factory->make($configuration, $name);
            $connection->getPdo()->exec('SET SESSION wait_timeout = '.$initialTimeout);

            return $connection;
        });
        app()->instance('db.factory', $testFactory);

        $result = app(FeaturePreviewDatabaseGuard::class)->withMainReadOnlyConnection(function (Connection $connection) use ($initialTimeout): string {
            $pdo = $connection->getPdo();
            expect((int) $pdo->query('SELECT @@SESSION.wait_timeout')->fetchColumn())->toBe(max(3600, $initialTimeout));
            if ($initialTimeout === 1) {
                sleep(2);
            }
            expect($pdo->inTransaction())->toBeTrue()
                ->and($connection->table('records')->value('content'))->toBe('original');

            return 'stream completed';
        });

        expect($result)->toBe('stream completed')
            ->and((int) $default->query('SELECT @@SESSION.wait_timeout')->fetchColumn())->toBe($defaultTimeout)
            ->and((int) $default->query('SELECT @@GLOBAL.wait_timeout')->fetchColumn())->toBe($globalTimeout);
    })->with(['short server timeout' => 1, 'existing longer timeout' => 7200]);

    test('keeps a runtime approved isolated preview target alive and never reconnects it', function (): void {
        config(['schooltool.preview.instance' => true]);
        $client = Mockery::mock(FeaturePreviewControlClient::class);
        $client->shouldReceive('request')->with('status')->andReturn(['source' => [
            'database' => 'separate_live_schema',
            'server_fingerprint' => str_repeat('a', 64),
            'app_key_fingerprint' => str_repeat('b', 64),
        ]]);
        app()->instance(FeaturePreviewControlClient::class, $client);
        $default = DB::connection()->getPdo();
        $defaultTimeout = (int) $default->query('SELECT @@SESSION.wait_timeout')->fetchColumn();
        app(FeaturePreviewRuntimeService::class)->install();
        $factory = app('db.factory');
        $testFactory = Mockery::mock($factory);
        $testFactory->shouldReceive('make')->once()->andReturnUsing(function (array $configuration, string $name) use ($factory): Connection {
            $connection = $factory->make($configuration, $name);
            $connection->getPdo()->exec('SET SESSION wait_timeout = 1');

            return $connection;
        });
        app()->instance('db.factory', $testFactory);
        $captured = null;
        app(FeaturePreviewDatabaseGuard::class)->withSnapshotTargetConnection(function (Connection $connection) use ($default, &$captured): void {
            $captured = $connection;
            expect($connection->getPdo())->not->toBe($default);
            sleep(2);
            expect($connection->table('records')->where('id', 1)->update(['content' => 'preview only']))->toBe(1);
            $connection->disconnect();
            expect(fn () => $connection->reconnect())->toThrow(RuntimeException::class)
                ->and(fn () => $connection->select('SELECT 1'))->toThrow(RuntimeException::class);
        });
        expect($captured->getRawPdo())->toBeNull()
            ->and((int) $default->query('SELECT @@SESSION.wait_timeout')->fetchColumn())->toBe($defaultTimeout)
            ->and(DB::connection()->table('records')->value('content'))->toBe('preview only');
    });

    test('rejects unsafe target PDO options before opening a connection', function (array $options): void {
        config([
            'schooltool.preview.instance' => true,
            'database.connections.snapshot_readonly_main.options' => $options,
        ]);
        $factory = Mockery::mock(app('db.factory'));
        $factory->shouldNotReceive('make');
        app()->instance('db.factory', $factory);
        $called = false;
        expect(fn () => app(FeaturePreviewDatabaseGuard::class)->withSnapshotTargetConnection(function () use (&$called): void {
            $called = true;
        }))->toThrow(RuntimeException::class, 'safe nonpersistent PDO options')
            ->and($called)->toBeFalse();
    })->with([
        'persistent' => [[PDO::ATTR_PERSISTENT => true]],
        'initialization command' => [[PDO::MYSQL_ATTR_INIT_COMMAND => 'SET @unexpected = 1']],
        'local infile' => [[PDO::MYSQL_ATTR_LOCAL_INFILE => true]],
        'multiple statements' => [[PDO::MYSQL_ATTR_MULTI_STATEMENTS => true]],
        'silent errors' => [[PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]],
    ]);

    test('MySQL itself rejects mutations even when bypassing the query builder', function (string $statement): void {
        app(FeaturePreviewDatabaseGuard::class)->withMainReadOnlyConnection(function (Connection $connection) use ($statement): void {
            try {
                $connection->getPdo()->exec($statement);
                $this->fail('MySQL accepted a mutation on the read-only snapshot connection.');
            } catch (PDOException $exception) {
                expect($exception->errorInfo[1] ?? null)->toBe(1792);
            }
        });

        expect(DB::connection()->table('records')->value('content'))->toBe('original')
            ->and(DB::connection()->table('records')->count())->toBe(1)
            ->and(DB::connection()->select('SHOW TABLES'))->toHaveCount(1);
    })->with([
        'update' => ["UPDATE records SET content = 'forbidden' WHERE id = 1"],
        'delete' => ['DELETE FROM records WHERE id = 1'],
        'create table' => ['CREATE TABLE forbidden_records (id BIGINT PRIMARY KEY) ENGINE=InnoDB'],
        'drop table' => ['DROP TABLE records'],
    ]);

    test('rejects writes and transaction changes before they reach the source database', function (): void {
        app(FeaturePreviewDatabaseGuard::class)->withMainReadOnlyConnection(function (Connection $connection): void {
            foreach ([
                "UPDATE records SET content = 'forbidden'",
                'DROP TABLE records',
                'SET SESSION TRANSACTION READ WRITE',
                'COMMIT',
                'SELECT * FROM records FOR UPDATE',
                "SELECT GET_LOCK('snapshot-test', 0)",
                'SELECT 1; COMMIT',
            ] as $statement) {
                expect(fn () => $connection->select($statement))->toThrow(RuntimeException::class);
            }

            expect($connection->getPdo()->inTransaction())->toBeTrue()
                ->and($connection->table('records')->value('content'))->toBe('original');
        });
    });

    test('rejects reconnects after the dedicated connection is disconnected', function (): void {
        $captured = null;

        app(FeaturePreviewDatabaseGuard::class)->withMainReadOnlyConnection(function (Connection $connection) use (&$captured): void {
            $captured = $connection;
            $connection->disconnect();
            expect(fn () => $connection->reconnect())->toThrow(RuntimeException::class)
                ->and(fn () => $connection->select('SELECT 1'))->toThrow(RuntimeException::class);
        });

        expect($captured->getRawPdo())->toBeNull()
            ->and(DB::connection()->table('records')->value('content'))->toBe('original');
    });

    test('rolls back and disconnects when the export callback fails', function (): void {
        $captured = null;
        $snapshotPdo = null;
        $failure = new RuntimeException('The export callback failed.');

        try {
            app(FeaturePreviewDatabaseGuard::class)->withMainReadOnlyConnection(function (Connection $connection) use (&$captured, &$snapshotPdo, $failure): never {
                $captured = $connection;
                $snapshotPdo = $connection->getPdo();
                expect($snapshotPdo->inTransaction())->toBeTrue();
                throw $failure;
            });
            $this->fail('The export exception must propagate.');
        } catch (RuntimeException $exception) {
            expect($exception)->toBe($failure);
        }

        expect($captured->getRawPdo())->toBeNull()
            ->and($snapshotPdo->inTransaction())->toBeFalse()
            ->and(DB::connection()->table('records')->where('id', 1)->update(['content' => 'write after failed export']))->toBe(1);
    });

    test('rejects further queries and callback success after the snapshot transaction ends or becomes writable', function (string $statement): void {
        expect(fn () => app(FeaturePreviewDatabaseGuard::class)->withMainReadOnlyConnection(function (Connection $connection) use ($statement): void {
            $connection->getPdo()->exec($statement);
            expect(fn () => $connection->table('records')->get())->toThrow(RuntimeException::class);
        }))->toThrow(RuntimeException::class);
    })->with([
        'committed transaction' => ['COMMIT'],
        'rolled back transaction' => ['ROLLBACK'],
        'writable session' => ['SET SESSION TRANSACTION READ WRITE'],
    ]);

    test('returns stable source fingerprints without database credentials or the application key', function (): void {
        $guard = app(FeaturePreviewDatabaseGuard::class);
        $encrypter = app('encrypter');
        $identity = $guard->mainIdentity();
        $serialized = json_encode($identity, JSON_THROW_ON_ERROR);

        expect(array_keys($identity))->toBe(['database', 'server_fingerprint', 'app_key_fingerprint'])
            ->and($identity['database'])->toBe($this->snapshotCreatedDatabase)
            ->and($identity['server_fingerprint'])->toMatch('/\A[a-f0-9]{64}\z/')
            ->and($identity['app_key_fingerprint'])->toMatch('/\A[a-f0-9]{64}\z/')
            ->and($guard->mainIdentity())->toBe($identity)
            ->and($serialized)->not->toContain($this->snapshotCreatedUser)
            ->and($serialized)->not->toContain(config('database.connections.snapshot_readonly_main.password'))
            ->and($serialized)->not->toContain(base64_encode($encrypter->getKey()));

        app()->instance('encrypter', new Encrypter(random_bytes(32), 'AES-256-CBC'));
        try {
            $changed = $guard->mainIdentity();
            expect($changed['app_key_fingerprint'])->not->toBe($identity['app_key_fingerprint'])
                ->and($changed['server_fingerprint'])->toBe($identity['server_fingerprint'])
                ->and($changed['database'])->toBe($identity['database']);
        } finally {
            app()->instance('encrypter', $encrypter);
        }
    });

    test('refuses a source account that cannot inspect every schema object', function (): void {
        $admin = $this->snapshotAdmin;
        $account = $admin->quote($this->snapshotCreatedUser)."@'127.0.0.1'";
        $schema = '`'.$this->snapshotCreatedDatabase.'`.*';
        $admin->exec('REVOKE ALL PRIVILEGES ON '.$schema.' FROM '.$account);
        $admin->exec('GRANT SELECT ON '.$schema.' TO '.$account);
        $called = false;

        expect(fn () => app(FeaturePreviewDatabaseGuard::class)->withMainReadOnlyConnection(function () use (&$called): void {
            $called = true;
        }))->toThrow(RuntimeException::class, 'hidden triggers')
            ->and($called)->toBeFalse();
    });
});
