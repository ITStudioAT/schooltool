<?php

namespace App\Services;

use App\Jobs\CreateTeachingAssessment;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Throwable;

class TeachingAssessmentQueue
{
    public function publishAfterCommit(int $jobId): void
    {
        if (! $this->usesRedis()) {
            return;
        }

        DB::afterCommit(function () use ($jobId): void {
            try {
                $this->publish($jobId);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }

    public function publishPending(): void
    {
        foreach ($this->pendingJobs()->orderBy('id')->limit(25)->pluck('id') as $jobId) {
            $this->publish((int) $jobId);
        }
    }

    public function usesRedis(): bool
    {
        return config('queue.default') === 'redis';
    }

    private function publish(int $jobId): void
    {
        DB::transaction(function () use ($jobId): void {
            $pending = $this->pendingJobs()->where('id', $jobId);
            $payload = (clone $pending)->lockForUpdate()->value('payload');
            if ($payload === null) {
                return;
            }

            // Keep the durable copy until Redis accepts it. The run claims its queued state only once.
            if (! Queue::connection('redis')->pushRaw($payload, CreateTeachingAssessment::QUEUE)) {
                throw new RuntimeException('Redis did not acknowledge the assessment job.');
            }
            $pending->delete();
        });
    }

    private function pendingJobs(): Builder
    {
        return DB::table(config('queue.connections.database.table'))
            ->where('queue', CreateTeachingAssessment::QUEUE)
            ->where('payload->data->commandName', CreateTeachingAssessment::class)
            ->where('attempts', 0)
            ->whereNull('reserved_at')
            ->where('available_at', '<=', now()->getTimestamp());
    }
}
