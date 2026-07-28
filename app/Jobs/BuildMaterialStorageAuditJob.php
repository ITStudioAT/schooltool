<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Materials\MaterialStorageAuditService;
use App\Services\Materials\MaterialStorageAuditStatusStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BuildMaterialStorageAuditJob implements ShouldQueue
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
        public ?int $schoolId = null,
    ) {
        $this->onQueue('maintenance');
    }

    /**
     * Execute the job.
     */
    public function handle(MaterialStorageAuditService $service, MaterialStorageAuditStatusStore $statusStore): void
    {
        $totalSteps = ($this->schoolId > 0 ? 2 : 1) * 5;
        $statusStore->markRunning($this->authUserId, $this->operationId, $totalSteps, 'Speicherprüfung läuft.');

        try {
            $user = User::query()->findOrFail($this->authUserId);
            $result = $service->auditForUserWithProgress(
                $user,
                $this->schoolId,
                function (int $completedSteps, int $progressTotalSteps, string $message) use ($statusStore): void {
                    $statusStore->markProgress(
                        $this->authUserId,
                        $this->operationId,
                        $completedSteps,
                        $progressTotalSteps,
                        $message,
                    );
                },
            );

            $statusStore->markCompleted($this->authUserId, $this->operationId, $result);
        } catch (\Throwable $throwable) {
            $statusStore->markFailed(
                $this->authUserId,
                $this->operationId,
                $throwable->getMessage() !== '' ? $throwable->getMessage() : 'Speicherprüfung konnte nicht abgeschlossen werden.',
            );

            throw $throwable;
        }
    }
}
