<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Support\Facades\DB;

trait ConfiguresLegacyRestaurantConnection
{
    protected function configureLegacyConnection(string $connectionName): bool
    {
        $configKey = (bool) $this->option('remote')
            ? 'schooltool.legacy_restaurant_remote'
            : 'schooltool.legacy_restaurant';

        $legacyConnection = config($configKey);

        if (! is_array($legacyConnection) || $legacyConnection === []) {
            $label = (bool) $this->option('remote') ? 'Remote' : 'Local';
            $this->error("{$label} legacy restaurant database connection is not configured ({$configKey}).");

            return false;
        }

        $label = (bool) $this->option('remote') ? 'remote' : 'local';
        $databaseLocation = $this->legacyConnectionLocation($legacyConnection);
        $this->info("Using {$label} database: {$databaseLocation}");

        config([
            "database.connections.$connectionName" => array_merge([
                'driver' => 'mysql',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
            ], $legacyConnection),
        ]);

        return true;
    }

    protected function disconnectLegacyConnection(string $connectionName): void
    {
        DB::disconnect($connectionName);
        DB::purge($connectionName);
    }

    /**
     * @param  array<string, mixed>  $legacyConnection
     */
    private function legacyConnectionLocation(array $legacyConnection): string
    {
        $database = (string) ($legacyConnection['database'] ?? '');
        $host = trim((string) ($legacyConnection['host'] ?? ''));

        if ($host === '') {
            return $database;
        }

        $port = trim((string) ($legacyConnection['port'] ?? ''));

        return $port !== ''
            ? "{$host}:{$port}/{$database}"
            : "{$host}/{$database}";
    }
}
