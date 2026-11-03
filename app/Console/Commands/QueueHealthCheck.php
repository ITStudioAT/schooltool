<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Throwable;

class QueueHealthCheck extends Command
{
    protected $signature = 'queue:health-check
                            {--exclude-master-pid=* : Horizon master process IDs that must not satisfy this health check}';

    protected $description = 'Zeigt den Queue-Status von Horizon oder der lokalen Datenbank-Queue';

    public function handle(): int
    {
        $connection = (string) config('queue.default');

        if (app()->environment('local')
            && config("queue.connections.{$connection}.driver") === 'database') {
            return $this->localDatabaseStatus($connection);
        }

        $masterSupervisors = app(MasterSupervisorRepository::class);
        $supervisors = app(SupervisorRepository::class);
        $excludedMasterProcessIds = collect($this->option('exclude-master-pid'))
            ->filter(fn (mixed $processId): bool => is_string($processId)
                && ctype_digit($processId)
                && (int) $processId > 0)
            ->map(fn (string $processId): int => (int) $processId)
            ->values();
        $masters = collect($masterSupervisors->all())
            ->reject(fn (object $master): bool => $excludedMasterProcessIds->containsStrict(
                (int) ($master->pid ?? 0),
            ));

        if ($masters->isEmpty()) {
            $this->error('Horizon ist inaktiv.');
            $this->line('Erwartete Queues: '.implode(', ', $this->expectedQueueNames()));
            $this->line('Produktionsprozess: php artisan horizon');

            return 2;
        }

        if ($masters->contains(fn (object $master): bool => $master->status === 'paused')) {
            $this->warn('Horizon ist pausiert.');

            return 1;
        }

        $expectedQueues = collect($this->expectedQueueNames());
        $activeSupervisors = collect($supervisors->all());

        if ($excludedMasterProcessIds->isNotEmpty()) {
            $replacementSupervisorNames = $masters
                ->flatMap(fn (object $master): array => is_array($master->supervisors ?? null)
                    ? $master->supervisors
                    : [])
                ->filter(fn (mixed $name): bool => is_string($name) && $name !== '')
                ->values();
            $activeSupervisors = $activeSupervisors
                ->filter(fn (object $supervisor): bool => $replacementSupervisorNames->containsStrict(
                    $supervisor->name ?? null,
                ));
        }

        $activeQueues = $activeSupervisors
            ->filter(fn (object $supervisor): bool => $supervisor->status === 'running')
            ->flatMap(function (object $supervisor): array {
                $queues = $supervisor->options['queue'] ?? '';

                return is_string($queues) ? explode(',', $queues) : [];
            })
            ->map(fn (string $queue): string => trim($queue))
            ->filter()
            ->unique();

        $missingQueues = $expectedQueues->diff($activeQueues)->values();

        if ($missingQueues->isNotEmpty()) {
            $this->error('Horizon bedient nicht alle erwarteten Queues.');
            $this->line('Fehlende Queues: '.$missingQueues->implode(', '));

            return 3;
        }

        $this->info('Horizon laeuft.');

        return self::SUCCESS;
    }

    private function localDatabaseStatus(string $connection): int
    {
        try {
            $queue = Queue::connection($connection);
            $rows = collect($this->expectedQueueNames())
                ->map(fn (string $name): array => [$name, $queue->size($name)])
                ->all();
            $heartbeat = Cache::get('health:worker');
            $workerAt = is_string($heartbeat) && $heartbeat !== '' ? Carbon::parse($heartbeat) : null;
        } catch (Throwable) {
            $this->error('Lokaler Datenbank-Queue-Status konnte nicht gelesen werden.');

            return 2;
        }

        $this->line('Lokale Datenbank-Queue; Horizon-Metriken sind hier nicht verfügbar.');
        $this->table(['Queue', 'Aufträge'], $rows);

        if (! $workerAt || $workerAt->lessThanOrEqualTo(now()->subMinutes(2)) || $workerAt->greaterThan(now())) {
            $this->error('Kein aktueller Worker-Heartbeat der critical-Queue.');

            return 2;
        }

        $this->info('Worker-Heartbeat der critical-Queue ist aktuell.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function expectedQueueNames(): array
    {
        return collect(config('horizon.defaults', []))
            ->flatMap(fn (array $supervisor): array => $supervisor['queue'] ?? [])
            ->filter(fn (mixed $queue): bool => is_string($queue) && $queue !== '')
            ->unique()
            ->values()
            ->all();
    }
}
