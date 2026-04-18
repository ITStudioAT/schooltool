<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ConfiguresLegacyRestaurantConnection;
use App\Models\School;
use App\Services\LegacyRestaurantImportService;
use App\Services\RestaurantCdgymLunchUserSepaSyncService;
use App\Services\RestaurantCdgymUserSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RestaurantImportCommand extends Command
{
    use ConfiguresLegacyRestaurantConnection;

    protected $signature = 'restaurant:import-legacy
        {--school-id=1 : Target local school id}
        {--dry-run : Read and summarize without writing}
        {--remote : Use the remote legacy database instead of the local one}
        {--fresh : Clear all restaurant tables before importing}';

    protected $description = 'Import restaurant foods, menus, lunch users, and SEPA information from the configured legacy restaurant database.';

    public function handle(
        LegacyRestaurantImportService $legacyRestaurantImportService,
        RestaurantCdgymUserSyncService $restaurantCdgymUserSyncService,
        RestaurantCdgymLunchUserSepaSyncService $restaurantCdgymLunchUserSepaSyncService
    ): int {
        $schoolId = (int) $this->option('school-id');
        $school = School::query()->find($schoolId);

        if (! $school) {
            $this->error('Target school not found.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $connectionName = 'legacy_restaurant_full_import';

        if (! $this->configureLegacyConnection($connectionName)) {
            return self::FAILURE;
        }

        DB::purge($connectionName);

        try {
            $legacyConnection = DB::connection($connectionName);
            $legacyFoods = $legacyConnection->table('food')->orderBy('id')->get();
            $legacyMenus = $legacyConnection->table('menus')->orderBy('id')->get();
            $legacyRestaurantUsers = $legacyConnection->table('users as users')
                ->join('model_has_roles as model_has_roles', function ($join): void {
                    $join->on('model_has_roles.model_id', '=', 'users.id')
                        ->where('model_has_roles.model_type', '=', 'App\\Models\\User');
                })
                ->join('roles as roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->whereIn('roles.name', ['lunch_user', 'lunch_admin'])
                ->orderBy('users.id')
                ->get([
                    'users.id',
                    'users.email',
                    'users.first_name',
                    'users.last_name',
                    'users.email_verified_at',
                    'roles.name as role_name',
                ]);
            $legacySepaRows = $legacyConnection->table('lunch_users as lunch_users')
                ->join('users as users', 'users.id', '=', 'lunch_users.user_id')
                ->orderBy('lunch_users.id')
                ->get([
                    'users.email',
                    'lunch_users.sepa',
                ]);

            $summary = $dryRun
                ? $this->runImport(
                    $schoolId,
                    $legacyFoods,
                    $legacyMenus,
                    $legacyRestaurantUsers,
                    $legacySepaRows,
                    true,
                    $legacyRestaurantImportService,
                    $restaurantCdgymUserSyncService,
                    $restaurantCdgymLunchUserSepaSyncService,
                )
                : DB::transaction(function () use (
                    $schoolId,
                    $legacyFoods,
                    $legacyMenus,
                    $legacyRestaurantUsers,
                    $legacySepaRows,
                    $legacyRestaurantImportService,
                    $restaurantCdgymUserSyncService,
                    $restaurantCdgymLunchUserSepaSyncService,
                ): array {
                    if ((bool) $this->option('fresh')) {
                        $this->clearRestaurantTables($schoolId);
                    }

                    return $this->runImport(
                        $schoolId,
                        $legacyFoods,
                        $legacyMenus,
                        $legacyRestaurantUsers,
                        $legacySepaRows,
                        false,
                        $legacyRestaurantImportService,
                        $restaurantCdgymUserSyncService,
                        $restaurantCdgymLunchUserSepaSyncService,
                    );
                });
        } catch (\Throwable $throwable) {
            $this->error('Restaurant import failed: '.$throwable->getMessage());
            DB::disconnect($connectionName);
            DB::purge($connectionName);

            return self::FAILURE;
        }

        DB::disconnect($connectionName);
        DB::purge($connectionName);

        $this->info($dryRun ? 'Restaurant dry run completed.' : 'Restaurant import completed.');
        $this->line('School: '.($school->long_name ?: $school->short_name ?: '#'.$school->id));
        $this->newLine();
        $this->info('Foods and menus');
        $this->line('Legacy foods seen: '.$summary['legacy']['legacy_foods_seen']);
        $this->line('Legacy menus seen: '.$summary['legacy']['legacy_menus_seen']);
        $this->line('Categories created: '.$summary['legacy']['categories_created']);
        $this->line('Foods created: '.$summary['legacy']['foods_created']);
        $this->line('Foods updated: '.$summary['legacy']['foods_updated']);
        $this->line('Menus created: '.$summary['legacy']['menus_created']);
        $this->line('Menus updated: '.$summary['legacy']['menus_updated']);
        $this->line('Menu food links synced: '.$summary['legacy']['menu_food_links_synced']);
        $this->line('Missing menu food references: '.$summary['legacy']['missing_menu_food_references']);
        $this->newLine();
        $this->info('Lunch users');
        $this->line('Source rows seen: '.$summary['users']['source_rows_seen']);
        $this->line('Source users seen: '.$summary['users']['source_users_seen']);
        $this->line('Source users skipped: '.$summary['users']['source_users_skipped']);
        $this->line('Users matched: '.$summary['users']['users_matched']);
        $this->line('Users to create: '.$summary['users']['users_to_create']);
        $this->line('Users created: '.$summary['users']['users_created']);
        $this->line('Roles to assign: '.$summary['users']['roles_to_assign']);
        $this->line('Roles assigned: '.$summary['users']['roles_assigned']);
        $this->line('Lunch user roles assigned: '.$summary['users']['lunch_user_roles_assigned']);
        $this->line('Lunch admin roles assigned: '.$summary['users']['lunch_admin_roles_assigned']);
        $this->newLine();
        $this->info('SEPA');
        $this->line('Source rows seen: '.$summary['sepa']['source_rows_seen']);
        $this->line('Source users seen: '.$summary['sepa']['source_users_seen']);
        $this->line('Source users skipped: '.$summary['sepa']['source_users_skipped']);
        $this->line('Local users matched: '.$summary['sepa']['local_users_matched']);
        $this->line('Local users missing: '.$summary['sepa']['local_users_missing']);
        $this->line('SEPA true seen: '.$summary['sepa']['sepa_true_seen']);
        $this->line('SEPA false seen: '.$summary['sepa']['sepa_false_seen']);
        $this->line('Users to mark SEPA: '.$summary['sepa']['users_to_mark_sepa']);
        $this->line('Users to clear SEPA: '.$summary['sepa']['users_to_clear_sepa']);
        $this->line('Users marked SEPA: '.$summary['sepa']['users_marked_sepa']);
        $this->line('Users cleared SEPA: '.$summary['sepa']['users_cleared_sepa']);

        return self::SUCCESS;
    }

    /**
     * @return array{
     *     legacy: array<string, int>,
     *     users: array<string, int>,
     *     sepa: array<string, int>
     * }
     */
    private function clearRestaurantTables(int $schoolId): void
    {
        $this->warn('Clearing all restaurant data for school #'.$schoolId.'...');

        DB::table('restaurant_menu_plan_bookings')->where('school_id', $schoolId)->delete();

        $entryIds = DB::table('restaurant_menu_plan_entries')
            ->join('restaurant_menu_plans', 'restaurant_menu_plans.id', '=', 'restaurant_menu_plan_entries.restaurant_menu_plan_id')
            ->where('restaurant_menu_plans.school_id', $schoolId)
            ->pluck('restaurant_menu_plan_entries.id');

        if ($entryIds->isNotEmpty()) {
            DB::table('restaurant_menu_plan_entry_eating_times')
                ->whereIn('restaurant_menu_plan_entry_id', $entryIds)
                ->delete();

            DB::table('restaurant_menu_plan_entries')
                ->whereIn('id', $entryIds)
                ->delete();
        }

        DB::table('restaurant_menu_plans')->where('school_id', $schoolId)->delete();

        $menuIds = DB::table('restaurant_menus')->where('school_id', $schoolId)->pluck('id');
        if ($menuIds->isNotEmpty()) {
            DB::table('restaurant_food_restaurant_menu')
                ->whereIn('restaurant_menu_id', $menuIds)
                ->delete();
        }

        DB::table('restaurant_menus')->where('school_id', $schoolId)->delete();

        $foodIds = DB::table('restaurant_foods')->where('school_id', $schoolId)->pluck('id');
        if ($foodIds->isNotEmpty()) {
            DB::table('restaurant_food_restaurant_ingredient_icon')
                ->whereIn('restaurant_food_id', $foodIds)
                ->delete();
        }

        DB::table('restaurant_foods')->where('school_id', $schoolId)->delete();
        DB::table('restaurant_categories')->where('school_id', $schoolId)->delete();

        $this->info('Restaurant tables cleared.');
    }

    private function runImport(
        int $schoolId,
        Collection $legacyFoods,
        Collection $legacyMenus,
        Collection $legacyRestaurantUsers,
        Collection $legacySepaRows,
        bool $dryRun,
        LegacyRestaurantImportService $legacyRestaurantImportService,
        RestaurantCdgymUserSyncService $restaurantCdgymUserSyncService,
        RestaurantCdgymLunchUserSepaSyncService $restaurantCdgymLunchUserSepaSyncService
    ): array {
        return [
            'legacy' => $legacyRestaurantImportService->import($schoolId, $legacyFoods, $legacyMenus, $dryRun),
            'users' => $restaurantCdgymUserSyncService->sync($schoolId, $legacyRestaurantUsers, ! $dryRun),
            'sepa' => $restaurantCdgymLunchUserSepaSyncService->sync($schoolId, $legacySepaRows, ! $dryRun),
        ];
    }
}
