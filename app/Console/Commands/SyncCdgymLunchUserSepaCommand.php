<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\RestaurantCdgymLunchUserSepaSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncCdgymLunchUserSepaCommand extends Command
{
    protected $signature = 'restaurant:sync-cdgym-lunch-user-sepa
        {--school-id=1 : Target local school id}
        {--source-host=127.0.0.1 : Source database host}
        {--source-port=3306 : Source database port}
        {--source-database=cdgym_info : Source database name}
        {--source-username=root : Source database username}
        {--source-password= : Source database password}
        {--dry-run : Read and summarize without writing}
        {--live : Persist changes}';

    protected $description = 'Sync SEPA information from legacy cdgym_info lunch_users into local lunch users.';

    public function handle(RestaurantCdgymLunchUserSepaSyncService $service): int
    {
        if ((bool) $this->option('dry-run') && (bool) $this->option('live')) {
            $this->error('Use either --dry-run or --live, not both.');

            return self::FAILURE;
        }

        $schoolId = (int) $this->option('school-id');
        $school = School::query()->find($schoolId);

        if (! $school) {
            $this->error('Target school not found.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('live');
        $connectionName = 'legacy_cdgym_lunch_user_sepa_sync';

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
            $sourceRows = $legacyConnection->table('lunch_users as lunch_users')
                ->join('users as users', 'users.id', '=', 'lunch_users.user_id')
                ->orderBy('lunch_users.id')
                ->get([
                    'users.email',
                    'lunch_users.sepa',
                ]);

            $summary = $service->sync($schoolId, collect($sourceRows), $apply);
        } catch (\Throwable $throwable) {
            $this->error('Legacy lunch user SEPA sync failed: '.$throwable->getMessage());
            DB::disconnect($connectionName);
            DB::purge($connectionName);

            return self::FAILURE;
        }

        DB::disconnect($connectionName);
        DB::purge($connectionName);

        $this->info($apply ? 'SEPA sync completed.' : 'SEPA dry run completed.');
        $this->line('Source rows seen: '.$summary['source_rows_seen']);
        $this->line('Source users seen: '.$summary['source_users_seen']);
        $this->line('Source users skipped: '.$summary['source_users_skipped']);
        $this->line('Local users matched: '.$summary['local_users_matched']);
        $this->line('Local users missing: '.$summary['local_users_missing']);
        $this->line('SEPA true seen: '.$summary['sepa_true_seen']);
        $this->line('SEPA false seen: '.$summary['sepa_false_seen']);
        $this->line('Users to mark SEPA: '.$summary['users_to_mark_sepa']);
        $this->line('Users to clear SEPA: '.$summary['users_to_clear_sepa']);
        $this->line('Users marked SEPA: '.$summary['users_marked_sepa']);
        $this->line('Users cleared SEPA: '.$summary['users_cleared_sepa']);

        return self::SUCCESS;
    }
}
