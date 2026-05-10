<?php

namespace App\Console\Commands;

use App\Services\TeachingBackupService;
use Illuminate\Console\Command;

class TeachingBackupMaintenanceCommand extends Command
{
    protected $signature = 'teaching:backup-maintenance';

    protected $description = 'Recover stale teaching restore runs and prune old teaching backups';

    public function handle(TeachingBackupService $service): int
    {
        $staleRuns = $service->recoverStaleRestoreRuns();
        $retention = $service->pruneBackupRetention();

        $this->info("Stale restore runs failed: {$staleRuns['failed']}");
        $this->info("Safety backups pruned: {$retention['safety_deleted']}");
        $this->info("Manual/imported backups pruned: {$retention['manual_deleted']}");

        return self::SUCCESS;
    }
}
