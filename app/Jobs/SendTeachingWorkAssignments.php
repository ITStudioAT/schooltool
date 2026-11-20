<?php

namespace App\Jobs;

use App\Services\TeachingWorkAssignmentMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendTeachingWorkAssignments implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 45;

    public bool $failOnTimeout = true;

    public function __construct(public int $workId, public string $slot, public string $batchId, public string $recipient) {}

    public function handle(TeachingWorkAssignmentMail $mail): void
    {
        $mail->sendOne($this->workId, $this->slot, $this->batchId, $this->recipient);
    }

    public function failed(?Throwable $exception): void
    {
        app(TeachingWorkAssignmentMail::class)->failed($this->workId, $this->slot, $this->batchId, $this->recipient);
    }
}
