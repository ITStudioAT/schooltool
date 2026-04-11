<?php

namespace App\Services\Materials;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MaterialInboxImportStatusStore
{
    private const TTL_HOURS = 12;

    private const REFRESH_AFTER_SECONDS = 3;

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function createQueuedOperation(int $userId, string $kind, array $context = []): array
    {
        $operationId = (string) Str::uuid();
        $payload = [
            'operation_id' => $operationId,
            'kind' => $kind,
            'status' => 'queued',
            'refresh_after_seconds' => self::REFRESH_AFTER_SECONDS,
            'queued_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'message' => null,
            'result' => null,
            'context' => $context,
        ];

        Cache::put($this->cacheKey($userId, $operationId), $payload, now()->addHours(self::TTL_HOURS));

        return $payload;
    }

    /**
     * @param  array<string, mixed>|null  $current
     */
    public function markRunning(int $userId, string $operationId, ?array $current = null): void
    {
        $payload = $current ?? $this->getOperation($userId, $operationId);
        if (! is_array($payload)) {
            return;
        }

        $payload['status'] = 'running';
        $payload['started_at'] = now()->toIso8601String();
        $payload['updated_at'] = now()->toIso8601String();

        Cache::put($this->cacheKey($userId, $operationId), $payload, now()->addHours(self::TTL_HOURS));
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function markCompleted(int $userId, string $operationId, string $message, array $result = []): void
    {
        $payload = $this->getOperation($userId, $operationId);
        if (! is_array($payload)) {
            return;
        }

        $payload['status'] = 'completed';
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
        return 'materials:inbox-import:'.$userId.':'.$operationId;
    }
}
