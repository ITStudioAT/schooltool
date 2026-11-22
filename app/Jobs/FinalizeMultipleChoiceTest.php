<?php

namespace App\Jobs;

use App\Models\McTest;
use App\Services\MultipleChoice\McTestService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FinalizeMultipleChoiceTest implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $testId)
    {
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(McTestService $tests): void
    {
        $test = McTest::query()->find($this->testId);
        $tests->finalizeExpired($this->testId);
        if ($this->job === null || $test?->start_mode !== 'common' || $test->ends_at === null || $test->hasWorkDeadlineConflict()) {
            return;
        }

        $end = $test->capDeadline($test->ends_at);
        if ($end->isFuture()) {
            $this->release((int) max(1, now()->diffInSeconds($end)));
        }
    }
}
