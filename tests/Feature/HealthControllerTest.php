<?php

use App\Models\QueueTest;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create([
        'long_name' => 'Test School',
        'short_name' => 'TS',
    ]);

    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->user = User::factory()->create([
        'email' => 'test@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'password' => Hash::make('password123'),
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'confirmed_at' => now(),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    $this->user->assignRole('admin');
});

// Health Status Tests

test('health status returns scheduler and worker health', function () {
    Cache::put('health:scheduler', now()->toIso8601String(), 300);
    Cache::put('health:worker', now()->toIso8601String(), 300);

    $this->actingAs($this->user);

    $response = $this->getJson('/api/admin/health/status');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'scheduler' => ['is_healthy', 'last_heartbeat'],
            'worker' => ['is_healthy', 'last_heartbeat'],
            'is_healthy',
        ])
        ->assertJson([
            'scheduler' => ['is_healthy' => true],
            'worker' => ['is_healthy' => true],
            'is_healthy' => true,
        ]);
});

test('health status reports unhealthy when scheduler heartbeat is stale', function () {
    Cache::put('health:scheduler', now()->subMinutes(5)->toIso8601String(), 300);
    Cache::put('health:worker', now()->toIso8601String(), 300);

    $this->actingAs($this->user);

    $response = $this->getJson('/api/admin/health/status');

    $response->assertStatus(200)
        ->assertJson([
            'scheduler' => ['is_healthy' => false],
            'worker' => ['is_healthy' => true],
            'is_healthy' => false,
        ]);
});

test('health status reports unhealthy when worker heartbeat is stale', function () {
    Cache::put('health:scheduler', now()->toIso8601String(), 300);
    Cache::put('health:worker', now()->subMinutes(5)->toIso8601String(), 300);

    $this->actingAs($this->user);

    $response = $this->getJson('/api/admin/health/status');

    $response->assertStatus(200)
        ->assertJson([
            'scheduler' => ['is_healthy' => true],
            'worker' => ['is_healthy' => false],
            'is_healthy' => false,
        ]);
});

test('health status reports unhealthy when no heartbeats exist', function () {
    Cache::forget('health:scheduler');
    Cache::forget('health:worker');

    $this->actingAs($this->user);

    $response = $this->getJson('/api/admin/health/status');

    $response->assertStatus(200)
        ->assertJson([
            'scheduler' => ['is_healthy' => false],
            'worker' => ['is_healthy' => false],
            'is_healthy' => false,
        ]);
});

test('health status requires authentication', function () {
    $response = $this->getJson('/api/admin/health/status');

    $response->assertStatus(401);
});

// Queue Test Tests

test('test queue creates queue test record', function () {
    $this->actingAs($this->user);

    $response = $this->getJson('/api/admin/health/test-queue');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'test_id',
            'dispatched_at',
        ])
        ->assertJson([
            'success' => true,
        ]);

    $this->assertDatabaseHas('queue_tests', [
        'id' => $response->json('test_id'),
        'user_id' => $this->user->id,
    ]);
});

test('test queue returns valid uuid test id', function () {
    $this->actingAs($this->user);

    $response = $this->getJson('/api/admin/health/test-queue');

    $testId = $response->json('test_id');

    expect($testId)->toBeString()
        ->and($testId)->not->toBeEmpty()
        ->and($testId)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');
});

test('test queue requires authentication', function () {
    $response = $this->getJson('/api/admin/health/test-queue');

    $response->assertStatus(401);
});

// Check Queue Test Status

test('check queue test returns status for valid test id', function () {
    $this->actingAs($this->user);

    $queueTest = QueueTest::create([
        'user_id' => $this->user->id,
        'status' => 'dispatched',
        'dispatched_at' => now(),
    ]);

    $response = $this->getJson('/api/admin/health/test-queue/check?test_id='.$queueTest->id);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'test_id',
            'status',
            'is_completed',
            'dispatched_at',
            'processed_at',
            'duration_seconds',
        ])
        ->assertJson([
            'success' => true,
            'status' => 'dispatched',
            'is_completed' => false,
        ]);
});

test('check queue test returns completed status with duration', function () {
    $this->actingAs($this->user);

    $dispatchedAt = now()->subSeconds(10);
    $processedAt = now();

    $queueTest = QueueTest::create([
        'user_id' => $this->user->id,
        'status' => 'completed',
        'dispatched_at' => $dispatchedAt,
        'processed_at' => $processedAt,
    ]);

    $response = $this->getJson('/api/admin/health/test-queue/check?test_id='.$queueTest->id);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'status' => 'completed',
            'is_completed' => true,
        ]);

    $duration = $response->json('duration_seconds');
    expect($duration)->toBeInt()
        ->and($duration)->toBeGreaterThanOrEqual(9)
        ->and($duration)->toBeLessThanOrEqual(11);
});

test('check queue test returns 404 for non-existent test', function () {
    $this->actingAs($this->user);

    $response = $this->getJson('/api/admin/health/test-queue/check?test_id=00000000-0000-0000-0000-000000000000');

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'Test not found',
        ]);
});

test('check queue test returns 404 for another users test', function () {
    $this->actingAs($this->user);

    $otherUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);

    $queueTest = QueueTest::create([
        'user_id' => $otherUser->id,
        'status' => 'dispatched',
        'dispatched_at' => now(),
    ]);

    $response = $this->getJson('/api/admin/health/test-queue/check?test_id='.$queueTest->id);

    $response->assertStatus(404);
});

test('check queue test requires authentication', function () {
    $response = $this->getJson('/api/admin/health/test-queue/check');

    $response->assertStatus(401);
});

test('check queue test falls back to latest users test when no id provided', function () {
    $this->actingAs($this->user);

    QueueTest::create([
        'user_id' => $this->user->id,
        'status' => 'dispatched',
        'dispatched_at' => now()->subMinute(),
    ]);

    $latestQueueTest = QueueTest::create([
        'user_id' => $this->user->id,
        'status' => 'completed',
        'dispatched_at' => now()->subSeconds(5),
        'processed_at' => now(),
    ]);

    $response = $this->getJson('/api/admin/health/test-queue/check');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'test_id' => $latestQueueTest->id,
            'is_completed' => true,
        ]);
});

test('check queue test treats undefined as missing id', function () {
    $this->actingAs($this->user);

    $queueTest = QueueTest::create([
        'user_id' => $this->user->id,
        'status' => 'dispatched',
        'dispatched_at' => now(),
    ]);

    $response = $this->getJson('/api/admin/health/test-queue/check?test_id=undefined');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'test_id' => $queueTest->id,
        ]);
});

// Integration

test('full queue test workflow works end to end', function () {
    $this->actingAs($this->user);

    $initiateResponse = $this->getJson('/api/admin/health/test-queue');
    $initiateResponse->assertStatus(200);
    $testId = $initiateResponse->json('test_id');

    $statusResponse = $this->getJson('/api/admin/health/test-queue/check?test_id='.$testId);
    $statusResponse->assertStatus(200);

    $queueTest = QueueTest::find($testId);
    expect($queueTest)->not->toBeNull();

    if ($queueTest->status !== 'completed') {
        $queueTest->update([
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }

    $completedResponse = $this->getJson('/api/admin/health/test-queue/check?test_id='.$testId);
    $completedResponse->assertStatus(200)
        ->assertJson([
            'status' => 'completed',
            'is_completed' => true,
        ]);

    expect($completedResponse->json('duration_seconds'))->not->toBeNull();
});
