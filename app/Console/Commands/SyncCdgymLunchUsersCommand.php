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
        {--source-host=127.0.0.1 : Source database host}
        {--source-port=3306 : Source database port}
        {--source-database=cdgym_info : Source database name}
        {--source-username=root : Source database username}
        {--source-password= : Source database password}
        {--dry-run : Read and summarize without writing}
        {--live : Persist changes}';

    protected $description = 'Sync lunch_user and lunch_admin users from the legacy cdgym_info database into a local school.';

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
}
