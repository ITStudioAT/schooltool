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
        if (! $test?->ends_at) {
            return;
        }
        if (now()->lessThan($test->ends_at)) {
            if ($this->job !== null) {
                $this->release((int) max(1, now()->diffInSeconds($test->ends_at)));
            }

            return;
        }

        $tests->finalizeExpired($this->testId);
    }
}
