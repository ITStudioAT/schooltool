<?php

namespace App\Jobs;

use App\Services\TeachingAssessmentRuns;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class CreateTeachingAssessment implements ShouldQueue
{
    use Queueable;

    public const QUEUE = 'teaching-assessments';

    public const TIMEOUT = 1830;

    public int $tries = 1;

    public int $timeout = self::TIMEOUT;

    public function __construct(public int $workId, public string $runId) {}

    public function handle(TeachingAssessmentRuns $runs): void
    {
        $runs->execute($this->workId, $this->runId);
    }

    public function failed(?Throwable $exception): void
    {
        app(TeachingAssessmentRuns::class)->interrupt($this->workId, $this->runId);
    }
}
