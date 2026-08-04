<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;

class QueueHealthCheck extends Command
{
    protected $signature = 'queue:health-check';

    protected $description = 'Zeigt den von Horizon gemeldeten Queue-Status';

    public function handle(
        MasterSupervisorRepository $masterSupervisors,
        SupervisorRepository $supervisors,
    ): int {
        $masters = collect($masterSupervisors->all());

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
        $activeQueues = collect($supervisors->all())
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
