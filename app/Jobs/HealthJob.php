<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HealthJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Cache::put('health:worker', now()->toIso8601String(), 300);

        $url = config('services.healthcheck.worker_ping_url');

        if (! $url) {
            return;
        }

        try {
            Http::timeout(5)->get($url);
        } catch (\Throwable $e) {
            Log::warning('Worker healthcheck ping failed: '.$e->getMessage());
        }
    }
}
