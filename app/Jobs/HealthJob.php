<?php

namespace App\Jobs;

use App\Models\SchoolTool;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class HealthJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {

        Log::info('HealthJob handle() START', [
            'queue_connection' => config('queue.default'),
        ]);

        $schooltool = SchoolTool::findOrFail(1);
        $schooltool->health_at = now();
        $schooltool->save();

        Log::info('HealthJob handle() DONE', [
            'health_at' => $schooltool->health_at?->format('Y-m-d H:i:s'),
        ]);
    }
}
