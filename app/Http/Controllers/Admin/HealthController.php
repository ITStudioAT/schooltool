<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QueueTest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HealthController extends Controller
{
    public function testQueue()
    {
        $user = Auth::user();

        // Create test record
        $queueTest = QueueTest::create([
            'user_id' => $user->id,
            'status' => 'dispatched',
            'dispatched_at' => now()
        ]);

        // Dispatch job
        dispatch(function () use ($queueTest) {
            sleep(2); // Simulate some work

            $queueTest->update([
                'status' => 'completed',
                'processed_at' => now()
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
            'message' => 'Queue test initiated'
        ]);
    }

    public function checkQueueStatus($testId)
    {
        $queueTest = QueueTest::where('id', $testId)
            ->where('user_id', Auth::id())
            ->first();

        if (!$queueTest) {
            return response()->json([
                'success' => false,
                'message' => 'Test not found'
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
            'status' => $status,
            'is_completed' => $isCompleted,
            'dispatched_at' => $dispatchedAt,
            'processed_at' => $processedAt,
            'duration_seconds' => $duration
        ]);
    }
}
