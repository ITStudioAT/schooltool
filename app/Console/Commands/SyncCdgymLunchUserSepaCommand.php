<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ConfiguresLegacyRestaurantConnection;
use App\Models\School;
use App\Services\RestaurantCdgymLunchUserSepaSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncCdgymLunchUserSepaCommand extends Command
{
    use ConfiguresLegacyRestaurantConnection;

    protected $signature = 'restaurant:sync-cdgym-lunch-user-sepa
        {--school-id=1 : Target local school id}
        {--dry-run : Read and summarize without writing}
        {--live : Persist changes}
        {--remote : Use the remote legacy database instead of the local one}';

    protected $description = 'Sync SEPA information from the configured legacy restaurant database into local lunch users.';

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

        if (! $this->configureLegacyConnection($connectionName)) {
            return self::FAILURE;
        }

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
