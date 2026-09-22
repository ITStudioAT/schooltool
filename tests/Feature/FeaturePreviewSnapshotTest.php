<?php

use App\Models\User;
use App\Services\FeaturePreviewControlClient;
use App\Services\FeaturePreviewDatabaseGuard;
use App\Services\FeaturePreviewSnapshotArchive;
use App\Services\FeaturePreviewSnapshotFiles;
use App\Services\FeaturePreviewSnapshotIdentityStore;
use App\Services\FeaturePreviewSnapshotService;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

test('snapshot key creation is explicit private and never overwrites an existing key', function (): void {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'schooltool-snapshot-key-'.bin2hex(random_bytes(8));
    mkdir($directory, 0700);
    config(['schooltool.preview.instance' => true, 'schooltool.preview.snapshot_directory' => $directory, 'schooltool.preview.snapshot_key_path' => $directory.DIRECTORY_SEPARATOR.'recipient.key']);
    try {
        $service = app(FeaturePreviewSnapshotService::class);
        $public = $service->generateKey();
        $key = file_get_contents($directory.DIRECTORY_SEPARATOR.'recipient.key');
        expect(strlen($public))->toBe(64)->and(strlen($key))->toBe(SODIUM_CRYPTO_BOX_KEYPAIRBYTES);
        expect(fn () => $service->generateKey())->toThrow(RuntimeException::class);
        expect(file_get_contents($directory.DIRECTORY_SEPARATOR.'recipient.key'))->toBe($key);
    } finally {
        (new Filesystem)->deleteDirectory($directory);
    }
});

test('snapshot metadata refuses storage inside the application', function (): void {
    config(['schooltool.preview.snapshot_directory' => base_path()]);
    expect(fn () => app(FeaturePreviewSnapshotIdentityStore::class)->directory())->toThrow(RuntimeException::class);
});

test('snapshot command does not expose database errors or secrets', function (): void {
    $service = Mockery::mock(FeaturePreviewSnapshotService::class);
    $service->shouldReceive('status')->once()->andThrow(new PDOException('password=very-secret SELECT personal_data'));
    app()->instance(FeaturePreviewSnapshotService::class, $service);
    $this->artisan('preview:snapshot', ['action' => 'status', '--feature' => str_repeat('a', 32)])
        ->doesntExpectOutputToContain('very-secret')->doesntExpectOutputToContain('personal_data')->assertFailed();
});

test('preview plans bind complete lifecycle state and refuse stale or missing confirmation', function (string $change): void {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'schooltool-preview-plan-'.bin2hex(random_bytes(8));
    mkdir($directory, 0700);
    config(['schooltool.preview.instance' => true, 'schooltool.preview.snapshot_directory' => $directory, 'schooltool.preview.snapshot_key_path' => $directory.DIRECTORY_SEPARATOR.'recipient.key']);
    $sourceIdentity = ['database' => 'isolated-source-fixture'];
    $guard = Mockery::mock(FeaturePreviewDatabaseGuard::class);
    $guard->shouldReceive('target')->andReturn(Mockery::mock(Connection::class));
    $guard->shouldReceive('sourceIdentity')->andReturnUsing(function () use (&$sourceIdentity): array {
        return $sourceIdentity;
    });
    $files = Mockery::mock(FeaturePreviewSnapshotFiles::class);
    $files->shouldReceive('assertConfigurationSafe');
    $identity = app(FeaturePreviewSnapshotIdentityStore::class);
    $service = new FeaturePreviewSnapshotService($guard, app(FeaturePreviewSnapshotArchive::class), $identity, $files);
    app()->instance(FeaturePreviewSnapshotService::class, $service);
    $feature = str_repeat('a', 32);
    $state = ['feature_id' => $feature, 'source_commit' => str_repeat('b', 40), 'source_identity' => $sourceIdentity, 'backup' => 'first-private-backup'];
    $originalStorage = storage_path();
    mkdir($directory.DIRECTORY_SEPARATOR.'runtime'.DIRECTORY_SEPARATOR.'framework', 0700, true);
    app()->useStoragePath($directory.DIRECTORY_SEPARATOR.'runtime');
    try {
        $service->generateKey();
        $identity->write($state);
        $token = $service->status($feature)['state_token'];
        expect($token)->toMatch('/\A[a-f0-9]{64}\z/');
        $service->assertPlan($feature, $token);
        $this->artisan('preview:snapshot', ['action' => 'assert-plan', '--feature' => $feature, '--state-token' => $token])->assertSuccessful();

        match ($change) {
            'feature' => $identity->write(array_replace($state, ['feature_id' => str_repeat('c', 32)])),
            'same feature source' => $identity->write(array_replace($state, ['source_commit' => str_repeat('d', 40)])),
            'returned feature' => $identity->write(array_replace($state, ['backup' => 'later-private-backup'])),
            'pending' => $identity->write(['phase' => 'importing'], 'snapshot-pending.json'),
            'source identity' => $sourceIdentity = ['database' => 'different-isolated-source'],
            'recipient key' => file_put_contents($directory.DIRECTORY_SEPARATOR.'recipient.key', FeaturePreviewSnapshotArchive::generateKeyPair()),
            'published release' => file_put_contents(storage_path('framework/preview-release.json'), json_encode(['source' => str_repeat('e', 40)])),
            'maintenance' => file_put_contents(storage_path('framework/down'), '{}'),
            'missing' => $token = '',
            'malformed' => $token = str_repeat('z', 64),
        };
        expect(fn () => $service->assertPlan($feature, $token))->toThrow(RuntimeException::class);
        $this->artisan('preview:snapshot', ['action' => 'assert-plan', '--feature' => $feature, '--state-token' => $token])->assertFailed();
        expect($identity->read()['source_commit'])->toBe($change === 'same feature source' ? str_repeat('d', 40) : $state['source_commit']);
    } finally {
        app()->useStoragePath($originalStorage);
        (new Filesystem)->deleteDirectory($directory);
    }
})->with(['feature', 'same feature source', 'returned feature', 'pending', 'source identity', 'recipient key', 'published release', 'maintenance', 'missing', 'malformed']);

test('export removes completed ciphertext if the final source connection check fails', function (): void {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'schooltool-snapshot-final-check-'.bin2hex(random_bytes(8));
    mkdir($directory, 0700);
    config(['schooltool.preview.instance' => false, 'schooltool.preview.snapshot_directory' => $directory]);
    $guard = Mockery::mock(FeaturePreviewDatabaseGuard::class);
    $failure = new PDOException('lost source connection');
    $guard->shouldReceive('withMainReadOnlyConnection')->once()->andReturnUsing(function (callable $callback) use ($failure): never {
        $callback(Mockery::mock(Connection::class));
        throw $failure;
    });
    $files = Mockery::mock(FeaturePreviewSnapshotFiles::class);
    $files->shouldReceive('assertSourceConfigurationSafe')->once();
    $files->shouldReceive('fingerprint')->with(true)->once()->andReturn(str_repeat('a', 64));
    $archive = Mockery::mock(FeaturePreviewSnapshotArchive::class);
    $archive->shouldReceive('write')->once()->andReturnUsing(function (string $path): void {
        file_put_contents($path, 'completed ciphertext fixture');
        chmod($path, 0600);
    });
    $service = new FeaturePreviewSnapshotService($guard, $archive, app(FeaturePreviewSnapshotIdentityStore::class), $files);
    try {
        expect(fn () => $service->export(str_repeat('a', 32), str_repeat('b', 64), str_repeat('c', 32)))
            ->toThrow(PDOException::class, 'lost source connection');
        expect(glob($directory.DIRECTORY_SEPARATOR.'*'))->toBe([]);
    } finally {
        (new Filesystem)->deleteDirectory($directory);
    }
});

test('snapshot transport is received only from a private verified temporary directory', function (): void {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'schooltool-preview-'.bin2hex(random_bytes(16));
    $private = sys_get_temp_dir().DIRECTORY_SEPARATOR.'schooltool-snapshot-receive-'.bin2hex(random_bytes(8));
    mkdir($directory, 0700);
    mkdir($private, 0700);
    $path = $directory.DIRECTORY_SEPARATOR.bin2hex(random_bytes(16)).'.stpreview';
    file_put_contents($path, 'encrypted opaque fixture');
    chmod($path, 0600);
    config(['schooltool.preview.instance' => true, 'schooltool.preview.snapshot_directory' => $private]);
    $guard = Mockery::mock(FeaturePreviewDatabaseGuard::class);
    $guard->shouldReceive('target')->andReturn(Mockery::mock(Connection::class));
    app()->instance(FeaturePreviewDatabaseGuard::class, $guard);
    try {
        $service = app(FeaturePreviewSnapshotService::class);
        expect(fn () => $service->receive($path, str_repeat('0', 64)))->toThrow(RuntimeException::class);
        expect(glob($private.DIRECTORY_SEPARATOR.'*'))->toBe([]);
        $result = $service->receive($path, hash_file('sha256', $path));
        expect(dirname(str_replace('/', DIRECTORY_SEPARATOR, $result['path'])))->toBe($private)
            ->and(file_get_contents($result['path']))->toBe('encrypted opaque fixture');
        expect(fn () => $service->receive($result['path'], hash_file('sha256', $path)))->toThrow(RuntimeException::class);
    } finally {
        (new Filesystem)->deleteDirectory($directory);
        (new Filesystem)->deleteDirectory($private);
    }
});

test('real MySQL snapshot copies data and private files while preserving live and reencrypting secrets', function (bool $emptySlowTarget): void {
    if (getenv('SCHOOLTOOL_SNAPSHOT_MYSQL_TEST') !== '1') {
        $this->markTestSkipped('Explicit opt-in for disposable local MySQL schemas and users.');
    }

    $base = config('database.connections.mysql');
    expect($base['host'])->toBeIn(['127.0.0.1', 'localhost']);
    $admin = new PDO('mysql:host=127.0.0.1;port='.(int) $base['port'].';charset=utf8mb4', $base['username'], $base['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $accountHost = (string) $admin->query("SELECT SUBSTRING_INDEX(USER(), '@', -1)")->fetchColumn();
    expect($accountHost)->toMatch('/\A[a-zA-Z0-9.:-]+\z/');
    $prefix = 'stps'.bin2hex(random_bytes(7));
    $sourceName = $prefix.'source';
    $targetName = $prefix.'target';
    $mainUser = $prefix.'main';
    $writer = $prefix.'writer';
    $password = bin2hex(random_bytes(24));
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'schooltool-snapshot-integration-'.bin2hex(random_bytes(8));
    $originalStorage = storage_path();
    $createdDatabases = [];
    $createdUsers = [];
    try {
        foreach ([$sourceName, $targetName] as $name) {
            $admin->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $createdDatabases[] = $name;
        }
        foreach ([$mainUser, $writer] as $user) {
            $admin->exec('CREATE USER '.$admin->quote($user).'@'.$admin->quote($accountHost).' IDENTIFIED BY '.$admin->quote($password));
            $createdUsers[] = $user;
        }
        $admin->exec('GRANT ALL PRIVILEGES ON `'.$sourceName.'`.* TO '.$admin->quote($mainUser).'@'.$admin->quote($accountHost));
        $admin->exec('GRANT ALL PRIVILEGES ON `'.$targetName.'`.* TO '.$admin->quote($writer).'@'.$admin->quote($accountHost));
        $base = array_replace($base, ['host' => '127.0.0.1', 'database' => $sourceName, 'url' => null, 'unix_socket' => '']);
        config([
            'database.connections.snapshot_main' => array_replace($base, ['username' => $mainUser, 'password' => $password]),
            'database.connections.snapshot_target' => array_replace($base, ['database' => $targetName, 'username' => $writer, 'password' => $password]),
            'database.default' => 'snapshot_main',
        ]);
        foreach (['snapshot_main', 'snapshot_target'] as $connection) {
            DB::purge($connection);
        }
        $source = DB::connection('snapshot_main');
        $target = DB::connection('snapshot_target');
        expect($source->getPdo()->query('SELECT CURRENT_USER()')->fetchColumn())->toBe($mainUser.'@'.$accountHost)
            ->and($target->getPdo()->query('SELECT CURRENT_USER()')->fetchColumn())->toBe($writer.'@'.$accountHost);
        $source->statement('CREATE TABLE users (id BIGINT PRIMARY KEY, school_id BIGINT, email VARCHAR(255), password VARCHAR(255), created_at TIMESTAMP NULL, confirmed_at TIMESTAMP NULL, email_verified_at TIMESTAMP NULL, is_2fa TINYINT, email_2fa VARCHAR(255) NULL, email_2fa_verified_at TIMESTAMP NULL, two_factor_secret TEXT NULL, two_factor_recovery_codes TEXT NULL, two_factor_confirmed_at TIMESTAMP NULL, remember_token VARCHAR(100) NULL, uuid VARCHAR(255) NULL, uuid_at TIMESTAMP NULL, token_2fa VARCHAR(100) NULL, token_2fa_expires_at TIMESTAMP NULL, feature_preview_allowed TINYINT) ENGINE=InnoDB');
        $source->statement('CREATE TABLE migrations (id BIGINT PRIMARY KEY, migration VARCHAR(255), batch INT) ENGINE=InnoDB');
        $source->statement('CREATE TABLE personal_access_tokens (id BIGINT PRIMARY KEY, token VARCHAR(255)) ENGINE=InnoDB');
        $source->statement('CREATE TABLE teaching_course_students (id BIGINT PRIMARY KEY, special_information TEXT NULL) ENGINE=InnoDB');
        $source->statement('CREATE TABLE teachers (id BIGINT PRIMARY KEY, token VARCHAR(255) NULL, token_expires_at TIMESTAMP NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
        foreach (['tutoring_offers', 'tutoring_offer_requests'] as $legacyTable) {
            $source->statement('CREATE TABLE '.$legacyTable.' (id BIGINT PRIMARY KEY, token VARCHAR(255) NULL, token_expires_at TIMESTAMP NULL) ENGINE=InnoDB');
            $source->table($legacyTable)->insert(['id' => 1, 'token' => 'legacy-private-token', 'token_expires_at' => '2026-01-01 00:00:00']);
        }
        $source->table('migrations')->insert([
            ['id' => 100, 'migration' => '2025_11_25_163630_create_tutoring_subjects_table', 'batch' => 1],
            ['id' => 101, 'migration' => '2025_11_30_101107_create_tutoring_offers_table', 'batch' => 1],
            ['id' => 102, 'migration' => '2025_12_24_110112_update_tutoring_offers', 'batch' => 1],
            ['id' => 103, 'migration' => '2025_12_24_111600_update_users', 'batch' => 1],
            ['id' => 104, 'migration' => '2025_12_27_182739_update_tutoring_offers', 'batch' => 1],
            ['id' => 105, 'migration' => '2025_12_29_113636_update_tutoring_offers', 'batch' => 1],
            ['id' => 106, 'migration' => '2025_12_29_113809_update_tutoring_offers', 'batch' => 1],
            ['id' => 107, 'migration' => '2026_01_02_171225_create_tutoring_offer_requests_table', 'batch' => 1],
            ['id' => 108, 'migration' => '2026_01_02_222403_update_tutoring_offer_requests', 'batch' => 1],
            ['id' => 109, 'migration' => '2026_01_04_225534_update_tutoring_offer_requests', 'batch' => 1],
            ['id' => 110, 'migration' => '2026_01_16_165437_update_school_tools', 'batch' => 1],
            ['id' => 111, 'migration' => '2026_02_17_010000_repair_school_tools_columns', 'batch' => 1],
            ['id' => 112, 'migration' => '2026_02_26_120000_change_tutoring_offers_accepted_at_to_datetime', 'batch' => 1],
        ]);
        if (! $emptySlowTarget) {
            $target->statement('CREATE TABLE old_only (id BIGINT PRIMARY KEY) ENGINE=InnoDB');
            $target->insert('INSERT INTO old_only VALUES (99)');
        }
        $originalTargetValue = fn (): mixed => $emptySlowTarget ? $target->select('SHOW TABLES') : $target->table('old_only')->value('id');
        $expectedOriginalTarget = $emptySlowTarget ? [] : 99;
        if ($emptySlowTarget) {
            $factory = app('db.factory');
            $testFactory = Mockery::mock($factory);
            $testFactory->shouldReceive('make')->andReturnUsing(function (array $configuration, string $name) use ($factory): Connection {
                $connection = $factory->make($configuration, $name);
                $connection->getPdo()->exec('SET SESSION wait_timeout = 1');

                return $connection;
            });
            app()->instance('db.factory', $testFactory);
            app()->bind(FeaturePreviewSnapshotFiles::class, fn () => new class extends FeaturePreviewSnapshotFiles
            {
                public function records(bool $source = false): Generator
                {
                    yield from parent::records($source);
                    sleep(2);
                }

                public function restore(#[SensitiveParameter] iterable $records, string $stagingDir): void
                {
                    parent::restore($records, $stagingDir);
                    sleep(2);
                }
            });
        }

        $mainKey = 'base64:'.base64_encode(random_bytes(32));
        $previewKey = 'base64:'.base64_encode(random_bytes(32));
        $configure = function (string $area, string $key, bool $preview) use ($directory): void {
            $storage = $directory.DIRECTORY_SEPARATOR.$area.DIRECTORY_SEPARATOR.'storage';
            foreach (['app/private', 'app/public', 'framework/sessions', 'framework/cache/data', 'framework/views'] as $suffix) {
                (new Filesystem)->ensureDirectoryExists($storage.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $suffix), 0700);
            }
            $snapshots = $directory.DIRECTORY_SEPARATOR.$area.'-snapshots';
            (new Filesystem)->ensureDirectoryExists($snapshots, 0700);
            app()->useStoragePath($storage);
            config([
                'app.key' => $key,
                'schooltool.preview.instance' => $preview,
                'schooltool.preview.snapshot_directory' => $snapshots,
                'schooltool.preview.snapshot_key_path' => $snapshots.DIRECTORY_SEPARATOR.'recipient.key',
                'filesystems.default' => 'local',
                'filesystems.disks.local' => ['driver' => 'local', 'root' => $storage.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'private'],
                'filesystems.disks.public' => ['driver' => 'local', 'root' => $storage.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public'],
            ]);
            app()->instance('encrypter', new Encrypter(base64_decode(substr($key, 7)), 'AES-256-CBC'));
            Crypt::clearResolvedInstance('encrypter');
        };
        $configure('target', $previewKey, true);
        $publicKey = app(FeaturePreviewSnapshotService::class)->generateKey();
        $targetPrivate = storage_path('app/private');
        file_put_contents($targetPrivate.DIRECTORY_SEPARATOR.'old.txt', 'old preview file');
        $configure('source', $mainKey, false);
        $sourceIdentity = app(FeaturePreviewDatabaseGuard::class)->mainIdentity();
        $bridgeSource = $sourceIdentity;
        $client = Mockery::mock(FeaturePreviewControlClient::class);
        $client->shouldReceive('request')->with('status')->andReturnUsing(function () use (&$bridgeSource): array {
            return ['schema_ready' => true, 'enabled' => true, 'source' => $bridgeSource];
        });
        app()->instance(FeaturePreviewControlClient::class, $client);
        file_put_contents(storage_path('app/private/report.txt'), 'private live attachment');
        file_put_contents(storage_path('app/public/logo.bin'), "binary\0logo");
        $sourceSecret = Crypt::encrypt('authenticator-secret');
        $sourceRecovery = Crypt::encrypt(json_encode(['recovery-code']));
        $source->table('users')->insert([
            'id' => 1, 'school_id' => 1, 'email' => 'tester@example.test', 'password' => 'bcrypt-fixture', 'created_at' => '2026-01-01 00:00:00',
            'confirmed_at' => '2026-01-01 00:00:00', 'email_verified_at' => '2026-01-01 00:00:00', 'is_2fa' => 1,
            'two_factor_secret' => $sourceSecret, 'two_factor_recovery_codes' => $sourceRecovery, 'two_factor_confirmed_at' => '2026-01-01 00:00:00',
            'remember_token' => 'live-remember', 'uuid' => 'live-email-verification', 'uuid_at' => '2026-01-01 00:00:00', 'token_2fa' => '123456', 'feature_preview_allowed' => 1,
        ]);
        $source->table('personal_access_tokens')->insert(['id' => 1, 'token' => 'live-api-token']);
        $source->table('teaching_course_students')->insert(['id' => 1, 'special_information' => Crypt::encryptString('private pupil details')]);
        $source->table('teachers')->insert(['id' => 1, 'token' => 'live-teacher-invitation', 'token_expires_at' => '2026-01-01 00:00:00', 'created_at' => '2020-01-02 03:04:05']);
        $feature = bin2hex(random_bytes(16));
        Event::fake(['eloquent.retrieved: '.User::class]);
        $export = app(FeaturePreviewSnapshotService::class)->export($feature, $publicKey, bin2hex(random_bytes(16)));
        Event::assertNotDispatched('eloquent.retrieved: '.User::class);
        expect(file_get_contents($export['path']))->not->toContain('private pupil details', 'authenticator-secret', 'live-api-token', 'private live attachment');
        $source->table('migrations')->insert(['id' => 1, 'migration' => '2099_01_01_live_migration_missing_from_candidate', 'batch' => 2]);
        $newerExport = app(FeaturePreviewSnapshotService::class)->export($feature, $publicKey, bin2hex(random_bytes(16)));
        $source->table('migrations')->where('id', 1)->delete();

        $configure('target', $previewKey, true);
        config(['database.default' => 'snapshot_target']);
        $importPath = config('schooltool.preview.snapshot_directory').DIRECTORY_SEPARATOR.'incoming.stpreview';
        copy($export['path'], $importPath);
        chmod($importPath, 0600);
        file_put_contents(storage_path('framework/down'), '{}');
        file_put_contents(storage_path('framework/sessions/session'), 'stale session');
        $service = app(FeaturePreviewSnapshotService::class);
        $bridgeSource['database'] = $targetName;
        expect(fn () => $service->import($feature, $importPath, $export['sha256'], true))->toThrow(RuntimeException::class);
        expect($originalTargetValue())->toBe($expectedOriginalTarget);
        $bridgeSource = $sourceIdentity;
        $bridgeSource['server_fingerprint'] = str_repeat('1', 64);
        expect(fn () => $service->import($feature, $importPath, $export['sha256'], true))->toThrow(RuntimeException::class);
        expect($originalTargetValue())->toBe($expectedOriginalTarget);
        $bridgeSource = $sourceIdentity;
        $forged = (function () use ($importPath): Generator {
            $key = file_get_contents(config('schooltool.preview.snapshot_key_path'));
            foreach (app(FeaturePreviewSnapshotArchive::class)->read($importPath, $key) as $record) {
                if ($record['kind'] === 'header') {
                    $record['source']['database'] = 'forged_source';
                }
                yield $record;
            }
        })();
        $forgedPath = config('schooltool.preview.snapshot_directory').DIRECTORY_SEPARATOR.'forged.stpreview';
        app(FeaturePreviewSnapshotArchive::class)->write($forgedPath, $publicKey, $forged);
        expect(fn () => $service->import($feature, $forgedPath, hash_file('sha256', $forgedPath), true))->toThrow(RuntimeException::class);
        expect($originalTargetValue())->toBe($expectedOriginalTarget)
            ->and(glob(config('schooltool.preview.snapshot_directory').DIRECTORY_SEPARATOR.'backup-*.stpreview'))->toBe([]);
        $newerPath = config('schooltool.preview.snapshot_directory').DIRECTORY_SEPARATOR.'newer.stpreview';
        copy($newerExport['path'], $newerPath);
        chmod($newerPath, 0600);
        expect(fn () => $service->import($feature, $newerPath, $newerExport['sha256'], true))->toThrow(RuntimeException::class);
        expect($originalTargetValue())->toBe($expectedOriginalTarget)
            ->and(file_get_contents(storage_path('app/private/old.txt')))->toBe('old preview file')
            ->and(app(FeaturePreviewSnapshotIdentityStore::class)->read('snapshot-pending.json'))->toBe([])
            ->and(glob(config('schooltool.preview.snapshot_directory').DIRECTORY_SEPARATOR.'backup-*.stpreview'))->toBe([]);
        expect(fn () => $service->import($feature, $importPath, str_repeat('0', 64), true))->toThrow(RuntimeException::class);
        expect($originalTargetValue())->toBe($expectedOriginalTarget);
        app()->instance('encrypter', new Encrypter(base64_decode(substr($mainKey, 7)), 'AES-256-CBC'));
        Crypt::clearResolvedInstance('encrypter');
        config(['app.key' => base64_decode(substr($mainKey, 7))]);
        expect(fn () => $service->import($feature, $importPath, $export['sha256'], true))->toThrow(RuntimeException::class);
        expect($originalTargetValue())->toBe($expectedOriginalTarget);
        app()->instance('encrypter', new Encrypter(base64_decode(substr($previewKey, 7)), 'AES-256-CBC'));
        Crypt::clearResolvedInstance('encrypter');
        config(['app.key' => $previewKey]);
        $result = $service->import($feature, $importPath, $export['sha256'], true);
        $copied = $target->table('users')->first();
        expect($result['imported'])->toBeTrue()
            ->and(is_file($result['backup']))->toBeTrue()
            ->and($copied->two_factor_secret)->not->toBe($sourceSecret)
            ->and(Crypt::decrypt($copied->two_factor_secret))->toBe('authenticator-secret')
            ->and(Crypt::decrypt($copied->two_factor_recovery_codes))->toBe(json_encode(['recovery-code']))
            ->and($copied->token_2fa)->toBeNull()
            ->and($copied->remember_token)->toBeNull()
            ->and($copied->uuid)->toBeNull()
            ->and($copied->uuid_at)->toBeNull()
            ->and($target->table('teachers')->value('token'))->toBeNull()
            ->and($target->table('teachers')->value('token_expires_at'))->toBeNull()
            ->and($target->table('tutoring_offers')->value('token'))->toBeNull()
            ->and($target->table('tutoring_offers')->value('token_expires_at'))->toBeNull()
            ->and($target->table('tutoring_offer_requests')->value('token'))->toBeNull()
            ->and($target->table('tutoring_offer_requests')->value('token_expires_at'))->toBeNull()
            ->and($target->table('migrations')->count())->toBe(13)
            ->and($target->table('teachers')->value('created_at'))->toBe('2020-01-02 03:04:05')
            ->and($target->table('personal_access_tokens')->count())->toBe(0)
            ->and(Crypt::decryptString($target->table('teaching_course_students')->value('special_information')))->toBe('private pupil details')
            ->and(file_get_contents(storage_path('app/private/report.txt')))->toBe('private live attachment')
            ->and(file_get_contents(storage_path('app/public/logo.bin')))->toBe("binary\0logo")
            ->and(is_file(storage_path('app/private/old.txt')))->toBeFalse()
            ->and(glob(storage_path('framework/sessions/*')))->toBe([])
            ->and($source->table('users')->value('token_2fa'))->toBe('123456')
            ->and($source->table('users')->value('two_factor_secret'))->toBe($sourceSecret)
            ->and($source->table('users')->value('uuid'))->toBe('live-email-verification')
            ->and($source->table('teachers')->value('token'))->toBe('live-teacher-invitation')
            ->and($source->table('tutoring_offers')->value('token'))->toBe('legacy-private-token')
            ->and($source->table('tutoring_offer_requests')->value('token'))->toBe('legacy-private-token')
            ->and($source->table('personal_access_tokens')->count())->toBe(1);
        $identity = app(FeaturePreviewSnapshotIdentityStore::class);
        expect($identity->read())->toBe([])->and($identity->read('snapshot-pending.json')['phase'])->toBe('imported');
        expect(fn () => $service->status($feature))->toThrow(RuntimeException::class);
        $service->restore(true);
        expect($originalTargetValue())->toBe($expectedOriginalTarget)
            ->and(file_get_contents(storage_path('app/private/old.txt')))->toBe('old preview file')
            ->and($identity->read())->toBe([])
            ->and($identity->read('snapshot-pending.json'))->toBe([])
            ->and(is_file(storage_path('framework/down')))->toBeTrue();
        $service->import($feature, $importPath, $export['sha256'], true);
        $service->activate($feature, str_repeat('a', 40));
        expect($identity->read()['feature_id'])->toBe($feature)->and($identity->read('snapshot-pending.json'))->toBe([]);
        expect($service->status($feature)['needs_snapshot'])->toBeFalse();
        expect($identity->read()['source_identity'])->toBe($sourceIdentity);
        $bridgeSource['server_fingerprint'] = str_repeat('2', 64);
        expect($service->status($feature)['needs_snapshot'])->toBeTrue();
        expect(fn () => $service->assertCurrent($feature))->toThrow(RuntimeException::class);
        $bridgeSource = $sourceIdentity;
        expect($service->status(bin2hex(random_bytes(16)))['needs_snapshot'])->toBeTrue();
        $target->table('users')->where('id', 1)->update(['password' => 'changed-only-in-preview']);
        $service->assertCurrent($feature);
        expect($target->table('users')->value('password'))->toBe('changed-only-in-preview');
        $target->table('migrations')->insert(['id' => 1, 'migration' => '2099_01_01_missing_from_candidate', 'batch' => 2]);
        expect(fn () => $service->assertCurrent($feature))->toThrow(RuntimeException::class, 'older than');
        $target->table('migrations')->where('id', 1)->delete();
        $service->checkpoint($feature);
        $target->table('users')->where('id', 1)->update(['password' => 'partly-applied-migration']);
        file_put_contents(storage_path('app/private/report.txt'), 'partly changed by failed update');
        $service->restore(true);
        expect($target->table('users')->value('password'))->toBe('changed-only-in-preview')
            ->and(file_get_contents(storage_path('app/private/report.txt')))->toBe('private live attachment')
            ->and($identity->read()['feature_id'])->toBe($feature)
            ->and(is_file(storage_path('framework/down')))->toBeTrue();
        expect(fn () => $target->select('SELECT * FROM `'.$sourceName.'`.users'))->toThrow(QueryException::class);
    } finally {
        app()->useStoragePath($originalStorage);
        foreach (['snapshot_main', 'snapshot_target'] as $connection) {
            DB::purge($connection);
        }
        foreach ($createdDatabases as $name) {
            $admin->exec('DROP DATABASE `'.$name.'`');
        }
        foreach ($createdUsers as $user) {
            $admin->exec('DROP USER '.$admin->quote($user).'@'.$admin->quote($accountHost));
        }
        (new Filesystem)->deleteDirectory($directory);
    }
})->with(['existing preview data' => false, 'empty preview and slow file streaming' => true]);
