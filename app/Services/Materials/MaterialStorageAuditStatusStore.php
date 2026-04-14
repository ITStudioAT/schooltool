<?php

namespace App\Services\Materials;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MaterialStorageAuditStatusStore
{
    private const TTL_HOURS = 1;

    private const REFRESH_AFTER_SECONDS = 1;

    /**
     * @return array<string, mixed>
     */
    public function createOperation(int $userId, ?int $schoolId = null): array
    {
        $operationId = (string) Str::uuid();
        $payload = [
            'operation_id' => $operationId,
            'status' => 'queued',
            'progress' => 0,
            'completed_steps' => 0,
            'total_steps' => 0,
            'refresh_after_seconds' => self::REFRESH_AFTER_SECONDS,
            'queued_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'message' => 'Speicherprüfung wird gestartet.',
            'result' => null,
            'context' => [
                'school_id' => $schoolId,
            ],
        ];

        Cache::put($this->cacheKey($userId, $operationId), $payload, now()->addHours(self::TTL_HOURS));

        return $payload;
    }

    public function markRunning(int $userId, string $operationId, int $totalSteps, string $message): void
    {
        $payload = $this->getOperation($userId, $operationId);
        if (! is_array($payload)) {
            return;
        }

        $payload['status'] = 'running';
        $payload['progress'] = 0;
        $payload['completed_steps'] = 0;
        $payload['total_steps'] = max(0, $totalSteps);
        $payload['started_at'] = now()->toIso8601String();
        $payload['updated_at'] = now()->toIso8601String();
        $payload['message'] = $message;

        Cache::put($this->cacheKey($userId, $operationId), $payload, now()->addHours(self::TTL_HOURS));
    }

    public function markProgress(int $userId, string $operationId, int $completedSteps, int $totalSteps, string $message): void
    {
        $payload = $this->getOperation($userId, $operationId);
        if (! is_array($payload)) {
            return;
        }

        $normalizedTotalSteps = max(0, $totalSteps);
        $normalizedCompletedSteps = min(max(0, $completedSteps), $normalizedTotalSteps > 0 ? $normalizedTotalSteps : $completedSteps);

        $payload['status'] = 'running';
        $payload['completed_steps'] = $normalizedCompletedSteps;
        $payload['total_steps'] = $normalizedTotalSteps;
        $payload['progress'] = $normalizedTotalSteps > 0
            ? (int) round(($normalizedCompletedSteps / $normalizedTotalSteps) * 100)
            : 0;
        $payload['updated_at'] = now()->toIso8601String();
        $payload['message'] = $message;

        Cache::put($this->cacheKey($userId, $operationId), $payload, now()->addHours(self::TTL_HOURS));
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function markCompleted(int $userId, string $operationId, array $result, string $message = 'Speicherprüfung abgeschlossen.'): void
    {
        $payload = $this->getOperation($userId, $operationId);
        if (! is_array($payload)) {
            return;
        }

        $payload['status'] = 'completed';
        $payload['progress'] = 100;
        $payload['completed_steps'] = max((int) ($payload['completed_steps'] ?? 0), (int) ($payload['total_steps'] ?? 0));
        $payload['message'] = $message;
        $payload['result'] = $result;
        $payload['completed_at'] = now()->toIso8601String();
        $payload['updated_at'] = now()->toIso8601String();

        Cache::put($this->cacheKey($userId, $operationId), $payload, now()->addHours(self::TTL_HOURS));
    }

    public function markFailed(int $userId, string $operationId, string $message): void
    {
        $payload = $this->getOperation($userId, $operationId);
        if (! is_array($payload)) {
            return;
        }

        $payload['status'] = 'failed';
        $payload['message'] = $message;
        $payload['failed_at'] = now()->toIso8601String();
        $payload['updated_at'] = now()->toIso8601String();

        Cache::put($this->cacheKey($userId, $operationId), $payload, now()->addHours(self::TTL_HOURS));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getOperation(int $userId, string $operationId): ?array
    {
        $payload = Cache::get($this->cacheKey($userId, $operationId));

        return is_array($payload) ? $payload : null;
    }

    private function cacheKey(int $userId, string $operationId): string
    {
        return 'materials:storage-audit:'.$userId.':'.$operationId;
    }
}
