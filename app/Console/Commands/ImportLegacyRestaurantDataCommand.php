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
        {--dry-run : Read and summarize without writing}';

    protected $description = 'Import legacy food and menu records from the configured legacy restaurant database into the restaurant tables.';

    public function handle(LegacyRestaurantImportService $service): int
    {
        $school = School::query()->find((int) $this->argument('school_id'));

        if (! $school) {
            $this->error('Target school not found.');

            return self::FAILURE;
        }

        $connectionName = 'legacy_restaurant_import';

        if (! $this->configureLegacyConnection($connectionName)) {
            return self::FAILURE;
        }

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

    private function configureLegacyConnection(string $connectionName): bool
    {
        $legacyConnection = config('schooltool.legacy_restaurant');

        if (! is_array($legacyConnection) || $legacyConnection === []) {
            $this->error('Legacy restaurant database connection is not configured.');

            return false;
        }

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
}
