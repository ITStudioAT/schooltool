<?php

namespace App\Jobs;

use App\Models\SchoolTool;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
        $schooltool = SchoolTool::findOrFail(1);
        $schooltool->health_at = now();
        $schooltool->save();
    }
}
