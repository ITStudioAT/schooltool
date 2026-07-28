<?php

namespace App\Jobs;

use App\Services\Materials\MaterialStorageAuditService;
use App\Services\Materials\MaterialStorageSyncStatusStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncActiveSchoolMaterialFilesToLocalJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $authUserId,
        public string $operationId,
        public int $schoolId,
    ) {
        $this->onQueue('maintenance');
    }

    /**
     * Execute the job.
     */
    public function handle(MaterialStorageAuditService $service, MaterialStorageSyncStatusStore $statusStore): void
    {
        $result = $service->syncCloudObjectsToLocalForSchoolWithProgress(
            $this->schoolId,
            function (int $completedSteps, int $totalSteps, string $message) use ($statusStore): void {
                if ($completedSteps === 0) {
                    $statusStore->markRunning($this->authUserId, $this->operationId, $totalSteps, $message);

                    return;
                }

                $statusStore->markProgress($this->authUserId, $this->operationId, $completedSteps, $totalSteps, $message);
            },
        );

        $statusStore->markCompleted(
            $this->authUserId,
            $this->operationId,
            $result,
            'Der Download der Materialdateien ist abgeschlossen.',
        );
    }

    public function failed(Throwable $exception): void
    {
        app(MaterialStorageSyncStatusStore::class)->markFailed(
            $this->authUserId,
            $this->operationId,
            'Der Download der Materialdateien konnte nicht abgeschlossen werden.',
        );
    }
}
