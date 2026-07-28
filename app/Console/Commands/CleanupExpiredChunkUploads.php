<?php

namespace App\Console\Commands;

use App\Services\FileUploadService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('uploads:cleanup-expired {--hours= : Uploads older than this number of hours are deleted}')]
#[Description('Delete expired incomplete chunk uploads')]
class CleanupExpiredChunkUploads extends Command
{
    public function handle(FileUploadService $fileUploadService): int
    {
        $hours = $this->option('hours');

        if ($hours !== null && (! is_numeric($hours) || (int) $hours < 1)) {
            $this->error('The --hours option must be a positive integer.');

            return self::FAILURE;
        }

        $deleted = $fileUploadService->cleanupExpiredUploads(
            $hours !== null ? (int) $hours : null,
        );

        $this->info("Deleted {$deleted} expired chunk upload(s).");

        return self::SUCCESS;
    }
}
