<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\RestaurantCdgymUserSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncCdgymLunchUsersCommand extends Command
{
    protected $signature = 'restaurant:sync-cdgym-users
        {--school-id=1 : Target local school id}
        {--dry-run : Read and summarize without writing}
        {--live : Persist changes}';

    protected $description = 'Sync lunch_user and lunch_admin users from the configured legacy restaurant database into a local school.';

    public function handle(RestaurantCdgymUserSyncService $service): int
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
        $connectionName = 'legacy_cdgym_user_sync';

        if (! $this->configureLegacyConnection($connectionName)) {
            return self::FAILURE;
        }

        DB::purge($connectionName);

        try {
            $legacyConnection = DB::connection($connectionName);
            $sourceRows = $legacyConnection->table('users as users')
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

            $summary = $service->sync($schoolId, collect($sourceRows), $apply);
        } catch (\Throwable $throwable) {
            $this->error('Legacy lunch user sync failed: '.$throwable->getMessage());
            DB::disconnect($connectionName);
            DB::purge($connectionName);

            return self::FAILURE;
        }

        DB::disconnect($connectionName);
        DB::purge($connectionName);

        $this->info($apply ? 'Sync completed.' : 'Dry run completed.');
        $this->line('Source rows seen: '.$summary['source_rows_seen']);
        $this->line('Source users seen: '.$summary['source_users_seen']);
        $this->line('Source users skipped: '.$summary['source_users_skipped']);
        $this->line('Users matched: '.$summary['users_matched']);
        $this->line('Users to create: '.$summary['users_to_create']);
        $this->line('Users created: '.$summary['users_created']);
        $this->line('Roles to assign: '.$summary['roles_to_assign']);
        $this->line('Roles assigned: '.$summary['roles_assigned']);
        $this->line('Lunch user roles assigned: '.$summary['lunch_user_roles_assigned']);
        $this->line('Lunch admin roles assigned: '.$summary['lunch_admin_roles_assigned']);

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
