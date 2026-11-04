<?php

namespace App\Console\Commands;

use App\Support\LocalMigrationSafety;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class GitUpdateMigrateCommand extends Command
{
    protected $signature = 'schooltool:gitupdate-migrate {--dry-run : Check the target and pending SQL without applying migrations}';

    protected $description = 'Apply additive pending migrations to the local Windows development database after gitupdate';

    public function handle(Migrator $migrator): int
    {
        $connectionName = (string) config('database.default');
        $connection = config("database.connections.$connectionName");

        if (PHP_OS_FAMILY !== 'Windows' || ! LocalMigrationSafety::allowsTarget(
            (string) config('app.env'),
            (bool) config('schooltool.preview.instance'),
            $connectionName,
            is_array($connection) ? $connection : [],
        )) {
            $this->error('Automatic gitupdate migrations require a local Windows MySQL database, without preview or remote database settings.');

            return self::FAILURE;
        }

        $database = (string) $connection['database'];
        if (DB::connection($connectionName)->selectOne('select database() as name')?->name !== $database) {
            $this->error('The connected database does not match the configured local database.');

            return self::FAILURE;
        }

        $repository = $migrator->getRepository();
        if (! $repository->repositoryExists()) {
            $this->error('The migration repository is missing. Initialize this database separately before gitupdate.');

            return self::FAILURE;
        }

        $files = $migrator->getMigrationFiles([...$migrator->paths(), database_path('migrations')]);
        $ran = $repository->getRan();
        $pending = array_filter($files, fn (string $path, string $name): bool => ! in_array($name, $ran, true), ARRAY_FILTER_USE_BOTH);

        if ($pending === []) {
            $this->info("Local database [$database] is already up to date.");

            return self::SUCCESS;
        }

        $arguments = [
            '--database' => $connectionName,
            '--path' => array_values($pending),
            '--realpath' => true,
            '--no-interaction' => true,
        ];

        if (Artisan::call('migrate', [...$arguments, '--pretend' => true]) !== self::SUCCESS) {
            $this->error('Could not inspect the pending migrations. No migration was applied.');

            return self::FAILURE;
        }

        $statements = LocalMigrationSafety::plannedStatements(Artisan::output(), array_keys($pending));
        if ($statements === null || array_any($statements, fn (string $sql): bool => ! LocalMigrationSafety::allowsStatement($sql))) {
            $this->error('Pending migrations contain destructive or unclear SQL. No migration was applied; review them explicitly.');

            return self::FAILURE;
        }

        $this->info('Additive migrations checked for local database ['.$database.']: '.implode(', ', array_keys($pending)));
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        if (Artisan::call('migrate', [...$arguments, '--force' => true]) !== self::SUCCESS) {
            $this->error('Local migration failed: '.Artisan::output());

            return self::FAILURE;
        }

        $this->info(Artisan::output());

        return self::SUCCESS;
    }
}
