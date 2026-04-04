<?php

namespace App\Console\Commands;

use Doctrine\DBAL\Configuration as DoctrineConfiguration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SchemaSyncCommand extends Command
{
    protected $signature = 'schema:sync
        {--connection= : Target database connection (defaults to database.default)}
        {--temp-connection= : Optional preconfigured connection to build the expected schema}
        {--temp-database= : Optional temp database name for mysql/mariadb/pgsql/sqlsrv}
        {--drop-extra-tables : Drop tables that do not exist in migrations}
        {--dry-run : Print SQL only, without applying changes}';

    protected $description = 'Compare migrations to the live database schema and sync column differences.';

    public function handle(): int
    {
        if (! class_exists(Comparator::class)) {
            $this->error('Missing dependency: doctrine/dbal');
            $this->line('Install it with: composer require doctrine/dbal');

            return self::FAILURE;
        }

        $targetConnection = $this->option('connection') ?: (string) config('database.default');
        $targetConfig = config("database.connections.$targetConnection");

        if (! is_array($targetConfig)) {
            $this->error("Unknown connection [$targetConnection].");

            return self::FAILURE;
        }

        [$tempConnection, $tempConfig, $cleanup] = $this->prepareTempConnection($targetConnection, $targetConfig);

        config(["database.connections.$tempConnection" => $tempConfig]);
        DB::purge($tempConnection);

        try {
            $cleanup['create']();
        } catch (\Throwable $e) {
            $this->error('Failed to prepare the temporary database: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Running migrations on the temporary database...');
        $exitCode = Artisan::call('migrate', [
            '--database' => $tempConnection,
            '--force' => true,
            '--no-interaction' => true,
        ]);

        if ($exitCode !== 0) {
            $this->error('Migrations failed on the temporary database.');
            $this->line(Artisan::output());
            $cleanup['destroy']();

            return self::FAILURE;
        }

        $actualConnection = DB::connection($targetConnection);
        $expectedConnection = DB::connection($tempConnection);

        $actualDoctrine = $this->buildDoctrineConnection($targetConnection);
        $expectedDoctrine = $this->buildDoctrineConnection($tempConnection);

        $actualSchemaManager = $actualDoctrine->createSchemaManager();
        $expectedSchemaManager = $expectedDoctrine->createSchemaManager();

        $actualSchema = $actualSchemaManager->introspectSchema();
        $expectedSchema = $expectedSchemaManager->introspectSchema();

        $comparator = $actualSchemaManager->createComparator();
        $schemaDiff = $comparator->compareSchemas($actualSchema, $expectedSchema);
        $platform = $actualDoctrine->getDatabasePlatform();
        $sqlStatements = $platform->getAlterSchemaSQL($schemaDiff);

        if (! $this->option('drop-extra-tables')) {
            $sqlStatements = array_values(array_filter(
                $sqlStatements,
                fn (string $sql) => ! preg_match('/^\s*drop\s+table/i', $sql)
            ));
        }

        if (empty($sqlStatements)) {
            $this->info('Schema is already in sync with migrations.');
            $cleanup['destroy']();

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info('Planned SQL statements:');
            foreach ($sqlStatements as $statement) {
                $this->line($statement.';');
            }
            $cleanup['destroy']();

            return self::SUCCESS;
        }

        $this->info('Applying schema changes...');
        foreach ($sqlStatements as $statement) {
            if ($this->shouldSkipStatement($actualSchemaManager, $statement)) {
                continue;
            }
            $actualConnection->statement($statement);
        }

        $cleanup['destroy']();
        $this->info('Schema sync completed.');

        return self::SUCCESS;
    }

    private function prepareTempConnection(string $targetConnection, array $targetConfig): array
    {
        $tempConnection = $this->option('temp-connection') ?: 'schema_sync_temp';
        $tempConfig = config("database.connections.$tempConnection");

        if (is_array($tempConfig)) {
            return [$tempConnection, $tempConfig, [
                'create' => static fn () => null,
                'destroy' => static fn () => null,
            ]];
        }

        $driver = $targetConfig['driver'] ?? null;
        $cleanup = [
            'create' => static fn () => null,
            'destroy' => static fn () => null,
        ];

        if ($driver === 'sqlite') {
            $tempPath = storage_path('app/schema-sync-temp.sqlite');
            File::ensureDirectoryExists(dirname($tempPath));
            File::put($tempPath, '');

            $tempConfig = $targetConfig;
            $tempConfig['database'] = $tempPath;

            $cleanup['destroy'] = static function () use ($tempPath, $tempConnection): void {
                DB::disconnect($tempConnection);
                if (File::exists($tempPath)) {
                    File::delete($tempPath);
                }
            };

            return [$tempConnection, $tempConfig, $cleanup];
        }

        if (! in_array($driver, ['mysql', 'mariadb', 'pgsql', 'sqlsrv'], true)) {
            throw new \RuntimeException("Unsupported driver [$driver] for schema sync.");
        }

        $baseName = (string) ($targetConfig['database'] ?? 'database');
        $baseName = preg_replace('/[^A-Za-z0-9_]/', '_', $baseName);
        $tempDatabase = $this->option('temp-database')
            ?: substr($baseName.'_schema_sync_tmp_'.Str::uuid()->toString(), 0, 56);

        $tempConfig = $targetConfig;
        $tempConfig['database'] = $tempDatabase;

        $cleanup['create'] = function () use ($targetConnection, $driver, $tempDatabase): void {
            $quoted = $this->quoteDatabaseName($driver, $tempDatabase);
            DB::connection($targetConnection)->statement("CREATE DATABASE $quoted");
        };

        $cleanup['destroy'] = function () use ($targetConnection, $driver, $tempDatabase, $tempConnection): void {
            DB::disconnect($tempConnection);
            $quoted = $this->quoteDatabaseName($driver, $tempDatabase);
            DB::connection($targetConnection)->statement("DROP DATABASE $quoted");
        };

        return [$tempConnection, $tempConfig, $cleanup];
    }

    private function quoteDatabaseName(string $driver, string $database): string
    {
        return match ($driver) {
            'mysql', 'mariadb' => '`'.str_replace('`', '``', $database).'`',
            'pgsql' => '"'.str_replace('"', '""', $database).'"',
            'sqlsrv' => '['.str_replace(']', ']]', $database).']',
            default => $database,
        };
    }

    private function buildDoctrineConnection(string $connectionName): Connection
    {
        $config = config("database.connections.$connectionName");

        if (! is_array($config)) {
            throw new \RuntimeException("Unknown connection [$connectionName].");
        }

        $driver = $config['driver'] ?? null;

        $params = match ($driver) {
            'mysql', 'mariadb' => [
                'driver' => 'pdo_mysql',
                'host' => $config['host'] ?? '127.0.0.1',
                'port' => $config['port'] ?? 3306,
                'dbname' => $config['database'] ?? '',
                'user' => $config['username'] ?? '',
                'password' => $config['password'] ?? '',
                'charset' => $config['charset'] ?? 'utf8mb4',
            ],
            'pgsql' => [
                'driver' => 'pdo_pgsql',
                'host' => $config['host'] ?? '127.0.0.1',
                'port' => $config['port'] ?? 5432,
                'dbname' => $config['database'] ?? '',
                'user' => $config['username'] ?? '',
                'password' => $config['password'] ?? '',
            ],
            'sqlsrv' => [
                'driver' => 'pdo_sqlsrv',
                'host' => $config['host'] ?? 'localhost',
                'port' => $config['port'] ?? 1433,
                'dbname' => $config['database'] ?? '',
                'user' => $config['username'] ?? '',
                'password' => $config['password'] ?? '',
            ],
            'sqlite' => [
                'driver' => 'pdo_sqlite',
                'path' => $config['database'] ?? '',
            ],
            default => throw new \RuntimeException("Unsupported driver [$driver] for doctrine connection."),
        };

        if (! empty($config['unix_socket']) && in_array($driver, ['mysql', 'mariadb'], true)) {
            $params['unix_socket'] = $config['unix_socket'];
        }

        return DriverManager::getConnection($params, new DoctrineConfiguration);
    }

    private function shouldSkipStatement(AbstractSchemaManager $schemaManager, string $statement): bool
    {
        if (preg_match('/^\s*DROP\s+INDEX\s+`?([A-Za-z0-9_]+)`?\s+ON\s+`?([A-Za-z0-9_]+)`?/i', $statement, $matches)) {
            [$indexName, $tableName] = [$matches[1], $matches[2]];
            $indexes = $schemaManager->listTableIndexes($tableName);

            return ! array_key_exists(strtolower($indexName), array_change_key_case($indexes, CASE_LOWER));
        }

        if (preg_match('/^\s*ALTER\s+TABLE\s+`?([A-Za-z0-9_]+)`?\s+DROP\s+FOREIGN\s+KEY\s+`?([A-Za-z0-9_]+)`?/i', $statement, $matches)) {
            [$tableName, $fkName] = [$matches[1], $matches[2]];
            $foreignKeys = $schemaManager->listTableForeignKeys($tableName);
            foreach ($foreignKeys as $foreignKey) {
                if (strcasecmp($foreignKey->getName(), $fkName) === 0) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }
}
