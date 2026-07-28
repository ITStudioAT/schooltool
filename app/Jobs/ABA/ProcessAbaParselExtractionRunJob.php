<?php

namespace App\Jobs\ABA;

use App\Services\AbaParselExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAbaParselExtractionRunJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public int $runId)
    {
        $this->onQueue('materials');
    }

    public function handle(AbaParselExtractionService $extractionService): void
    {
        $extractionService->processRun($this->runId);
    }
}
