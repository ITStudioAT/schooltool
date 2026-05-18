<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QueueTest;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class HealthController extends Controller
{
    public function status(): JsonResponse
    {
        $schedulerAt = Cache::get('health:scheduler');
        $workerAt = Cache::get('health:worker');

        $schedulerHealthy = $schedulerAt && Carbon::parse($schedulerAt)->greaterThan(now()->subMinutes(2));
        $workerHealthy = $workerAt && Carbon::parse($workerAt)->greaterThan(now()->subMinutes(2));

        return response()->json([
            'scheduler' => [
                'is_healthy' => $schedulerHealthy,
                'last_heartbeat' => $schedulerAt,
            ],
            'worker' => [
                'is_healthy' => $workerHealthy,
                'last_heartbeat' => $workerAt,
            ],
            'is_healthy' => $schedulerHealthy && $workerHealthy,
        ]);
    }

    public function testQueue(): JsonResponse
    {
        $user = Auth::user();

        $queueTest = QueueTest::create([
            'user_id' => $user->id,
            'status' => 'dispatched',
            'dispatched_at' => now(),
        ]);

        dispatch(function () use ($queueTest) {
            $queueTest->update([
                'status' => 'completed',
                'processed_at' => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'test_id' => $queueTest->id,
            'dispatched_at' => $queueTest->dispatched_at,
        ]);
    }

    public function checkQueueTest(Request $request): JsonResponse
    {
        $queueTest = $this->resolveQueueTestForStatusCheck($request);

        if (! $queueTest) {
            return response()->json([
                'success' => false,
                'message' => 'Test not found',
            ], 404);
        }

        $isCompleted = $queueTest->status === 'completed';
        $duration = null;

        if ($isCompleted && $queueTest->processed_at) {
            $duration = $queueTest->dispatched_at->diffInSeconds($queueTest->processed_at);
        }

        return response()->json([
            'success' => true,
            'test_id' => $queueTest->id,
            'status' => $queueTest->status,
            'is_completed' => $isCompleted,
            'dispatched_at' => $queueTest->dispatched_at,
            'processed_at' => $queueTest->processed_at,
            'duration_seconds' => $duration,
        ]);
    }

    private function resolveQueueTestForStatusCheck(Request $request): ?QueueTest
    {
        $userId = Auth::id();
        if ($userId === null) {
            return null;
        }

        $normalizedTestId = $this->normalizeTestId(
            $request->input('test_id', $request->input('testId'))
        );

        $query = QueueTest::query()
            ->where('user_id', (int) $userId);

        if ($normalizedTestId === null) {
            return $query
                ->where('dispatched_at', '>=', now()->subMinutes(10))
                ->latest('dispatched_at')
                ->first();
        }

        return $query
            ->where('id', $normalizedTestId)
            ->first();
    }

    private function normalizeTestId(mixed $testId): ?string
    {
        if (! is_scalar($testId)) {
            return null;
        }

        $normalizedTestId = trim((string) $testId);
        if ($normalizedTestId === '') {
            return null;
        }

        if (in_array(strtolower($normalizedTestId), ['null', 'undefined'], true)) {
            return null;
        }

        return $normalizedTestId;
    }
}
