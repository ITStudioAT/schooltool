<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SchedulerHeartbeat extends Command
{
    protected $signature = 'health:scheduler-heartbeat';

    protected $description = 'Schreibt einen Scheduler-Heartbeat in den Cache';

    public function handle(): int
    {
        Cache::put('health:scheduler', now()->toIso8601String(), 300);

        $this->pingExternalService();

        return self::SUCCESS;
    }

    private function pingExternalService(): void
    {
        $url = config('services.healthcheck.scheduler_ping_url');

        if (! $url) {
            return;
        }

        try {
            Http::timeout(5)->get($url);
        } catch (\Throwable $e) {
            Log::warning('Scheduler healthcheck ping failed: '.$e->getMessage());
        }
    }
}
