<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QueueTest;
use App\Models\SchoolTool;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HealthController extends Controller
{
    public function testQueue(): JsonResponse
    {
        $user = Auth::user();

        // Create test record
        $queueTest = QueueTest::create([
            'user_id' => $user->id,
            'status' => 'dispatched',
            'dispatched_at' => now(),
        ]);

        // Dispatch job
        dispatch(function () use ($queueTest) {
            $queueTest->update([
                'status' => 'completed',
                'processed_at' => now(),
            ]);
        });

        // Return variables
        $testId = $queueTest->id;
        $status = 'dispatched';
        $dispatchedAt = $queueTest->dispatched_at;

        return response()->json([
            'success' => true,
            'testId' => $testId,
            'status' => $status,
            'dispatched_at' => $dispatchedAt,
            'message' => 'Queue test initiated',
        ]);
    }

    public function checkQueueStatus(Request $request): JsonResponse
    {
        $queueTest = $this->resolveQueueTestForStatusCheck($request);

        if (! $queueTest) {
            return response()->json([
                'success' => false,
                'message' => 'Test not found',
            ], 404);
        }

        // Variables with results
        $status = $queueTest->status;
        $dispatchedAt = $queueTest->dispatched_at;
        $processedAt = $queueTest->processed_at;
        $isCompleted = $status === 'completed';
        $duration = null;

        if ($isCompleted && $processedAt) {
            $duration = $dispatchedAt->diffInSeconds($processedAt);
        }

        return response()->json([
            'success' => true,
            'test_id' => $queueTest->id,
            'status' => $status,
            'is_completed' => $isCompleted,
            'dispatched_at' => $dispatchedAt,
            'processed_at' => $processedAt,
            'duration_seconds' => $duration,
        ]);
    }

    public function testCron(): JsonResponse
    {
        $schooltool = SchoolTool::findOrFail(1);
        $healthy = Carbon::parse($schooltool->health_at)
            ->greaterThan(Carbon::now()->subMinutes(2));

        $data = [
            'is_healthy' => $healthy,
            'health_at' => Carbon::parse($schooltool->health_at)->format('Y-m-d H:i:s'),
        ];

        return response()->json($data, 200);
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
