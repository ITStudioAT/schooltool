<?php

namespace App\Console\Commands;

use App\Services\InstallUpdateService;
use Illuminate\Console\Command;

class PruneOrphanSchoolFoldersCommand extends Command
{
    protected $signature = 'private:prune-orphan-school-folders {--dry-run : Show orphan folders without deleting them}';

    protected $description = 'Remove orphan school folders from storage/app/private';

    public function handle(InstallUpdateService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $service->pruneOrphanPrivateSchoolFolders(! $dryRun);

        $orphans = $result['orphans'];
        $deleted = $result['deleted'];
        $failed = $result['failed'];

        $schoolIdsLine = empty($result['school_ids']) ? '-' : implode(', ', $result['school_ids']);
        $this->line("School IDs in DB: {$schoolIdsLine}");

        if (empty($orphans)) {
            $this->info('No orphan school folders found in storage/app/private.');

            return self::SUCCESS;
        }

        $this->line('Orphan folders:');
        foreach ($orphans as $path) {
            $this->line(" - {$path}");
        }

        if ($dryRun) {
            $this->warn('Dry-run mode: no folders were deleted.');

            return self::SUCCESS;
        }

        $this->info('Deleted folders: '.count($deleted));
        if (! empty($failed)) {
            $this->error('Failed to delete folders: '.count($failed));
            foreach ($failed as $path) {
                $this->error(" - {$path}");
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
