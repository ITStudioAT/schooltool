<?php

namespace App\Jobs;

use App\Services\TeachingWorkActionLog;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PublishTeachingWorkActions implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 300;

    public int $timeout = 100;

    public function __construct(public int $workId) {}

    public function uniqueId(): string
    {
        return (string) $this->workId;
    }

    public function handle(TeachingWorkActionLog $log): void
    {
        $log->publish($this->workId);
    }
}
