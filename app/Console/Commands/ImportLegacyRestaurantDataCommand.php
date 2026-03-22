<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\LegacyRestaurantImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacyRestaurantDataCommand extends Command
{
    protected $signature = 'restaurant:import-legacy
        {school_id : Target school id}
        {--source-host=127.0.0.1 : Legacy database host}
        {--source-port=3306 : Legacy database port}
        {--source-database=cdgym_info : Legacy database name}
        {--source-username=root : Legacy database username}
        {--source-password= : Legacy database password}
        {--dry-run : Read and summarize without writing}';

    protected $description = 'Import legacy food and menu records from an external database into the restaurant tables.';

    public function handle(LegacyRestaurantImportService $service): int
    {
        $school = School::query()->find((int) $this->argument('school_id'));

        if (! $school) {
            $this->error('Target school not found.');

            return self::FAILURE;
        }

        $connectionName = 'legacy_restaurant_import';

        config([
            "database.connections.$connectionName" => [
                'driver' => 'mysql',
                'host' => (string) $this->option('source-host'),
                'port' => (int) $this->option('source-port'),
                'database' => (string) $this->option('source-database'),
                'username' => (string) $this->option('source-username'),
                'password' => (string) $this->option('source-password'),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
            ],
        ]);

        DB::purge($connectionName);

        try {
            $legacyConnection = DB::connection($connectionName);
            $legacyFoods = $legacyConnection->table('food')->orderBy('id')->get();
            $legacyMenus = $legacyConnection->table('menus')->orderBy('id')->get();

            $summary = $service->import(
                (int) $school->id,
                $legacyFoods,
                $legacyMenus,
                (bool) $this->option('dry-run')
            );
        } catch (\Throwable $e) {
            $this->error('Legacy restaurant import failed: '.$e->getMessage());
            DB::disconnect($connectionName);
            DB::purge($connectionName);

            return self::FAILURE;
        }

        DB::disconnect($connectionName);
        DB::purge($connectionName);

        $this->info((bool) $this->option('dry-run') ? 'Dry run completed.' : 'Import completed.');
        $this->line('Legacy foods seen: '.$summary['legacy_foods_seen']);
        $this->line('Legacy menus seen: '.$summary['legacy_menus_seen']);
        $this->line('Categories created: '.$summary['categories_created']);
        $this->line('Foods created: '.$summary['foods_created']);
        $this->line('Foods updated: '.$summary['foods_updated']);
        $this->line('Menus created: '.$summary['menus_created']);
        $this->line('Menus updated: '.$summary['menus_updated']);
        $this->line('Menu food links synced: '.$summary['menu_food_links_synced']);
        $this->line('Missing menu food references: '.$summary['missing_menu_food_references']);

        return self::SUCCESS;
    }
}
