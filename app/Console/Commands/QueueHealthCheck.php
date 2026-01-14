<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QueueHealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:health-check {--restart : Automatically restart queue worker if down}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Überprüft Queue-Gesundheit und startet Worker bei Bedarf neu';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Queue Health Check wird ausgeführt...');

        $isRunning = $this->isQueueWorkerRunning();

        if ($isRunning) {
            $this->info('✓ Queue Worker läuft');

            $stuckJobs = $this->checkForStuckJobs();

            if ($stuckJobs > 0) {
                $this->warn("⚠ {$stuckJobs} Jobs scheinen hängen zu bleiben");
                Log::warning("Queue health check: {$stuckJobs} stuck jobs detected");
            }

            return self::SUCCESS;
        }

        $this->error('✗ Queue Worker läuft NICHT');
        Log::error('Queue health check: Queue worker is not running');

        if ($this->option('restart')) {
            return $this->restartQueueWorker();
        }

        $this->warn('Verwende --restart Option zum automatischen Neustart');

        return self::FAILURE;
    }

    /**
     * Check if queue worker process is running
     */
    private function isQueueWorkerRunning(): bool
    {
        // Windows-kompatible Prozess-Prüfung
        $command = stripos(PHP_OS, 'WIN') === 0
            ? 'tasklist /FI "IMAGENAME eq php.exe" /FO CSV'
            : 'ps aux | grep -E "queue:(work|listen)" | grep -v grep';

        exec($command, $output);

        foreach ($output as $line) {
            if (str_contains($line, 'queue:work') || str_contains($line, 'queue:listen')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for jobs that have been processing for too long (>5 minutes)
     */
    private function checkForStuckJobs(): int
    {
        try {
            $stuckThreshold = now()->subMinutes(5);

            $stuckJobs = DB::table('jobs')
                ->whereNotNull('reserved_at')
                ->where('reserved_at', '<', $stuckThreshold)
                ->count();

            return $stuckJobs;
        } catch (\Exception $e) {
            Log::error('Error checking for stuck jobs: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Restart queue worker
     */
    private function restartQueueWorker(): int
    {
        $this->info('Versuche Queue Worker neu zu starten...');

        try {
            // Kill existing queue processes (if any)
            $this->killExistingWorkers();

            // Retry all failed jobs
            $this->call('queue:retry', ['id' => 'all']);
            $this->info('✓ Fehlgeschlagene Jobs wurden neu gestartet');

            // Start new queue worker in background
            if (stripos(PHP_OS, 'WIN') === 0) {
                // Windows
                $command = 'start /B php artisan queue:listen --tries=1 > nul 2>&1';
                pclose(popen($command, 'r'));
                $this->info('✓ Queue Worker wurde gestartet (Windows)');
            } else {
                // Linux/Unix
                exec('nohup php artisan queue:listen --tries=1 > /dev/null 2>&1 &');
                $this->info('✓ Queue Worker wurde gestartet (Linux)');
            }

            Log::info('Queue health check: Queue worker restarted successfully');

            // Wait and verify
            sleep(2);

            if ($this->isQueueWorkerRunning()) {
                $this->info('✓ Queue Worker läuft jetzt');

                return self::SUCCESS;
            } else {
                $this->error('✗ Neustart fehlgeschlagen - Worker läuft nicht');
                Log::error('Queue health check: Worker restart failed - process not running');

                return self::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error('Fehler beim Neustart: '.$e->getMessage());
            Log::error('Queue health check: Restart error - '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Kill existing queue worker processes
     */
    private function killExistingWorkers(): void
    {
        if (stripos(PHP_OS, 'WIN') === 0) {
            // Windows: Kill by window title (if started with 'start')
            exec('taskkill /FI "WINDOWTITLE eq queue:*" /F 2>nul');
        } else {
            // Linux/Unix
            exec('pkill -f "queue:work"');
            exec('pkill -f "queue:listen"');
        }

        sleep(1); // Give processes time to terminate
    }
}
