<?php

namespace App\Jobs;

use App\Services\Materials\MaterialService;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

class SynchronizeMaterialLinkedContent implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public int $timeout = 1800;

    public int $uniqueFor = 3600;

    public function __construct(
        public int $targetUserId,
    ) {
        $this->onQueue('materials');
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->uniqueId()))
                ->releaseAfter(30)
                ->expireAfter(1830),
        ];
    }

    public function uniqueId(): string
    {
        return "materials-linked-content:user:{$this->targetUserId}";
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(3);
    }

    public function handle(MaterialService $materialService): void
    {
        $materialService->synchronizeLinkedContentForUserId($this->targetUserId);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Linked material synchronization failed.', [
            'target_user_id' => $this->targetUserId,
            'exception' => $exception,
        ]);
    }
}
