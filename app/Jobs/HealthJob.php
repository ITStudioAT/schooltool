<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\UniqueFor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

#[UniqueFor(86400)]
class HealthJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('critical');
    }

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
