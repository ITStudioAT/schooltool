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

        if (! $this->canRunShellCommands()) {
            $this->error('Shell-Funktionen (exec) sind deaktiviert. Queue-Prozessprüfung ist nicht möglich.');
            Log::warning('Queue health check skipped because exec() is unavailable.');

            return self::FAILURE;
        }

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
        if ($this->isWindows()) {
            foreach ($this->windowsQueueWorkerLookupCommands() as $command) {
                $output = $this->runShellCommand($command);

                foreach ($output as $line) {
                    if ($this->lineIndicatesQueueWorker($line)) {
                        return true;
                    }
                }
            }

            return false;
        }

        $output = $this->runShellCommand('ps aux | grep -E "queue:(work|listen)" | grep -v grep');

        foreach ($output as $line) {
            if ($this->lineIndicatesQueueWorker($line)) {
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
            $stuckThresholdTimestamp = now()->subMinutes(5)->getTimestamp();

            $stuckJobs = DB::table('jobs')
                ->whereNotNull('reserved_at')
                ->where('reserved_at', '<', $stuckThresholdTimestamp)
                ->count();

            return $stuckJobs;
        } catch (\Exception $e) {
            Log::error('Error checking for stuck jobs: '.$e->getMessage());

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

            if ($this->isWindows()) {
                if (! function_exists('popen') || ! function_exists('pclose')) {
                    $this->error('Neustart auf Windows nicht möglich: popen/pclose sind deaktiviert.');
                    Log::warning('Queue health check restart skipped because popen/pclose are unavailable.');

                    return self::FAILURE;
                }

                $command = sprintf(
                    'start "" /B "%s" "%s" queue:work --queue=default --tries=1 --sleep=3 --no-interaction > NUL 2>&1',
                    PHP_BINARY,
                    base_path('artisan')
                );
                pclose(popen($command, 'r'));
                $this->info('✓ Queue Worker wurde gestartet (Windows)');
            } else {
                $command = sprintf(
                    'nohup "%s" "%s" queue:work --queue=default --tries=1 --sleep=3 --no-interaction > /dev/null 2>&1 &',
                    PHP_BINARY,
                    base_path('artisan')
                );
                $this->runShellCommand($command);
                $this->info('✓ Queue Worker wurde gestartet (Linux)');
            }

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
        if ($this->isWindows()) {
            $this->runShellCommand(
                'powershell -NoProfile -Command "Get-CimInstance Win32_Process | '.
                "Where-Object { \$_.CommandLine -match 'queue:(work|listen)' } | ".
                'ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }"'
            );
        } else {
            $this->runShellCommand('pkill -f "queue:work"');
            $this->runShellCommand('pkill -f "queue:listen"');
        }

        sleep(1);
    }

    private function isWindows(): bool
    {
        return stripos(PHP_OS, 'WIN') === 0;
    }

    /**
     * @return array<int, string>
     */
    private function windowsQueueWorkerLookupCommands(): array
    {
        return [
            "powershell -NoProfile -Command \"(Get-CimInstance Win32_Process -Filter \\\"Name = 'php.exe'\\\").CommandLine\"",
            "wmic process where \"name='php.exe'\" get commandline",
        ];
    }

    private function lineIndicatesQueueWorker(string $line): bool
    {
        return str_contains($line, 'queue:work') || str_contains($line, 'queue:listen');
    }

    private function canRunShellCommands(): bool
    {
        return function_exists('exec');
    }

    /**
     * @return array<int, string>
     */
    private function runShellCommand(string $command): array
    {
        if (! $this->canRunShellCommands()) {
            return [];
        }

        $output = [];
        exec($command, $output);

        return $output;
    }
}
