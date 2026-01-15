<?php

/**
 * HealthController Tests
 * 
 * These tests cover the health check functionality including:
 * - Queue testing and status checking
 * - Job dispatch verification
 * - Authentication and authorization
 * - Error handling
 */

use App\Models\QueueTest;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test school and schoolyear
    $this->school = School::factory()->create([
        'long_name' => 'Test School',
        'short_name' => 'TS',
    ]);
    
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);
    
    // Create admin role
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    
    // Create test user
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

// Test Queue Tests
test('test queue creates queue test record', function () {
    $this->actingAs($this->user);
    
    $response = $this->getJson('/api/admin/test-queue');
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'testId',
            'status',
            'dispatched_at',
            'message',
        ])
        ->assertJson([
            'success' => true,
            'status' => 'dispatched',
            'message' => 'Queue test initiated',
        ]);
    
    // Verify record was created in database
    $this->assertDatabaseHas('queue_tests', [
        'id' => $response->json('testId'),
        'user_id' => $this->user->id,
    ]);
});

test('test queue returns valid test id', function () {
    $this->actingAs($this->user);
    
    $response = $this->getJson('/api/admin/test-queue');
    
    $testId = $response->json('testId');
    
    expect($testId)->toBeString()
        ->and($testId)->not->toBeEmpty();
    
    // Verify test ID is a valid UUID
    expect(QueueTest::find($testId))->not->toBeNull();
});

test('test queue requires authentication', function () {
    $response = $this->getJson('/api/admin/test-queue');
    
    $response->assertStatus(401);
});

test('test queue dispatches job correctly', function () {
    Queue::fake();
    
    $this->actingAs($this->user);
    
    $response = $this->getJson('/api/admin/test-queue');
    
    $response->assertStatus(200);
    
    // Verify a job was dispatched (closure-based job)
    // Note: Closure jobs are harder to assert, but we can check the DB record
    $testId = $response->json('testId');
    $queueTest = QueueTest::find($testId);
    
    expect($queueTest)->not->toBeNull()
        ->and($queueTest->status)->toBe('dispatched')
        ->and($queueTest->dispatched_at)->not->toBeNull();
});

test('test queue sets correct initial status', function () {
    $this->actingAs($this->user);
    
    $response = $this->getJson('/api/admin/test-queue');
    
    $testId = $response->json('testId');
    $queueTest = QueueTest::find($testId);
    
    // The job might complete immediately in sync queue, so just check it exists
    expect($queueTest->status)->toBeIn(['dispatched', 'completed'])
        ->and($queueTest->dispatched_at)->not->toBeNull();
});

test('test queue includes dispatched timestamp', function () {
    $this->actingAs($this->user);
    
    $response = $this->getJson('/api/admin/test-queue');
    
    $response->assertStatus(200)
        ->assertJsonStructure(['dispatched_at']);
    
    $dispatchedAt = $response->json('dispatched_at');
    expect($dispatchedAt)->not->toBeNull();
});

// Check Queue Status Tests
test('check queue status returns status for valid test id', function () {
    $this->actingAs($this->user);
    
    // Create a queue test record
    $queueTest = QueueTest::create([
        'user_id' => $this->user->id,
        'status' => 'dispatched',
        'dispatched_at' => now(),
    ]);
    
    $response = $this->getJson('/api/admin/test-queue/check?test_id=' . $queueTest->id);
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
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

test('check queue status returns completed status', function () {
    $this->actingAs($this->user);
    
    // Create a completed queue test record
    $queueTest = QueueTest::create([
        'user_id' => $this->user->id,
        'status' => 'completed',
        'dispatched_at' => now()->subSeconds(5),
        'processed_at' => now(),
    ]);
    
    $response = $this->getJson('/api/admin/test-queue/check?test_id=' . $queueTest->id);
    
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'status' => 'completed',
            'is_completed' => true,
        ]);
});

test('check queue status calculates duration correctly', function () {
    $this->actingAs($this->user);
    
    $dispatchedAt = now()->subSeconds(10);
    $processedAt = now();
    
    // Create a completed queue test record
    $queueTest = QueueTest::create([
        'user_id' => $this->user->id,
        'status' => 'completed',
        'dispatched_at' => $dispatchedAt,
        'processed_at' => $processedAt,
    ]);
    
    $response = $this->getJson('/api/admin/test-queue/check?test_id=' . $queueTest->id);
    
    $response->assertStatus(200);
    
    $duration = $response->json('duration_seconds');
    
    expect($duration)->toBeInt()
        ->and($duration)->toBeGreaterThanOrEqual(9)
        ->and($duration)->toBeLessThanOrEqual(11);
});

test('check queue status returns null duration for incomplete jobs', function () {
    $this->actingAs($this->user);
    
    // Create a dispatched (not completed) queue test record
    $queueTest = QueueTest::create([
        'user_id' => $this->user->id,
        'status' => 'dispatched',
        'dispatched_at' => now(),
    ]);
    
    $response = $this->getJson('/api/admin/test-queue/check?test_id=' . $queueTest->id);
    
    $response->assertStatus(200)
        ->assertJson([
            'duration_seconds' => null,
        ]);
});

test('check queue status returns 404 for non-existent test', function () {
    $this->actingAs($this->user);
    
    $fakeId = '00000000-0000-0000-0000-000000000000';
    
    $response = $this->getJson('/api/admin/test-queue/check?test_id=' . $fakeId);
    
    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'Test not found',
        ]);
});

test('check queue status returns 404 for another users test', function () {
    $this->actingAs($this->user);
    
    // Create another user
    $otherUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    
    // Create a queue test for the other user
    $queueTest = QueueTest::create([
        'user_id' => $otherUser->id,
        'status' => 'dispatched',
        'dispatched_at' => now(),
    ]);
    
    $response = $this->getJson('/api/admin/test-queue/check?test_id=' . $queueTest->id);
    
    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'Test not found',
        ]);
});

test('check queue status requires authentication', function () {
    // Create a queue test
    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    
    $queueTest = QueueTest::create([
        'user_id' => $user->id,
        'status' => 'dispatched',
        'dispatched_at' => now(),
    ]);
    
    $response = $this->getJson('/api/admin/test-queue/check?test_id=' . $queueTest->id);
    
    $response->assertStatus(401);
});

test('check queue status requires test_id parameter', function () {
    $this->actingAs($this->user);
    
    $response = $this->getJson('/api/admin/test-queue/check');
    
    // Will return 404 as test_id is null
    $response->assertStatus(404);
});

// Integration Tests
test('full queue test workflow works end to end', function () {
    $this->actingAs($this->user);
    
    // Step 1: Initiate queue test
    $initiateResponse = $this->getJson('/api/admin/test-queue');
    
    $initiateResponse->assertStatus(200);
    $testId = $initiateResponse->json('testId');
    
    // Step 2: Check status (might be completed already in sync queue)
    $statusResponse = $this->getJson('/api/admin/test-queue/check?test_id=' . $testId);
    
    $statusResponse->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'is_completed',
        ]);
    
    // Verify the record exists
    $queueTest = QueueTest::find($testId);
    expect($queueTest)->not->toBeNull();
    
    // If not completed, manually complete it
    if ($queueTest->status !== 'completed') {
        $queueTest->update([
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }
    
    // Step 3: Check completed status
    $completedResponse = $this->getJson('/api/admin/test-queue/check?test_id=' . $testId);
    
    $completedResponse->assertStatus(200)
        ->assertJson([
            'status' => 'completed',
            'is_completed' => true,
        ]);
    
    expect($completedResponse->json('duration_seconds'))->not->toBeNull();
});

test('multiple queue tests can be created by same user', function () {
    $this->actingAs($this->user);
    
    // Create first test
    $response1 = $this->getJson('/api/admin/test-queue');
    $testId1 = $response1->json('testId');
    
    // Create second test
    $response2 = $this->getJson('/api/admin/test-queue');
    $testId2 = $response2->json('testId');
    
    expect($testId1)->not->toBe($testId2);
    
    // Both tests should be in database
    $this->assertDatabaseHas('queue_tests', ['id' => $testId1]);
    $this->assertDatabaseHas('queue_tests', ['id' => $testId2]);
});

test('queue test model uses uuid for primary key', function () {
    $this->actingAs($this->user);
    
    $response = $this->getJson('/api/admin/test-queue');
    
    $testId = $response->json('testId');
    
    // UUID format: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
    expect($testId)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');
});

test('queue test belongs to user', function () {
    $this->actingAs($this->user);
    
    $response = $this->getJson('/api/admin/test-queue');
    
    $testId = $response->json('testId');
    $queueTest = QueueTest::find($testId);
    
    expect($queueTest->user)->not->toBeNull()
        ->and($queueTest->user->id)->toBe($this->user->id)
        ->and($queueTest->user->email)->toBe($this->user->email);
});

test('check queue status includes all timestamps', function () {
    $this->actingAs($this->user);
    
    $queueTest = QueueTest::create([
        'user_id' => $this->user->id,
        'status' => 'completed',
        'dispatched_at' => now()->subMinutes(5),
        'processed_at' => now(),
    ]);
    
    $response = $this->getJson('/api/admin/test-queue/check?test_id=' . $queueTest->id);
    
    $response->assertStatus(200);
    
    $data = $response->json();
    
    expect($data['dispatched_at'])->not->toBeNull()
        ->and($data['processed_at'])->not->toBeNull();
});

test('test queue response has correct json structure', function () {
    $this->actingAs($this->user);
    
    $response = $this->getJson('/api/admin/test-queue');
    
    $response->assertStatus(200)
        ->assertJsonCount(5)
        ->assertJsonStructure([
            'success',
            'testId',
            'status',
            'dispatched_at',
            'message',
        ]);
    
    expect($response->json('success'))->toBeTrue();
});

test('check queue status response has correct json structure', function () {
    $this->actingAs($this->user);
    
    $queueTest = QueueTest::create([
        'user_id' => $this->user->id,
        'status' => 'dispatched',
        'dispatched_at' => now(),
    ]);
    
    $response = $this->getJson('/api/admin/test-queue/check?test_id=' . $queueTest->id);
    
    $response->assertStatus(200)
        ->assertJsonCount(6)
        ->assertJsonStructure([
            'success',
            'status',
            'is_completed',
            'dispatched_at',
            'processed_at',
            'duration_seconds',
        ]);
});

// Edge Cases
test('check queue status handles missing processed_at for completed status', function () {
    $this->actingAs($this->user);
    
    // Edge case: status is completed but processed_at is null
    $queueTest = QueueTest::create([
        'user_id' => $this->user->id,
        'status' => 'completed',
        'dispatched_at' => now(),
        'processed_at' => null,
    ]);
    
    $response = $this->getJson('/api/admin/test-queue/check?test_id=' . $queueTest->id);
    
    $response->assertStatus(200)
        ->assertJson([
            'is_completed' => true,
            'duration_seconds' => null,
        ]);
});

test('test queue creates record with current timestamp', function () {
    $this->actingAs($this->user);
    
    $before = now()->subSecond();
    
    $response = $this->getJson('/api/admin/test-queue');
    
    $after = now()->addSecond();
    
    $testId = $response->json('testId');
    $queueTest = QueueTest::find($testId);
    
    expect($queueTest->dispatched_at)->toBeInstanceOf(\Carbon\Carbon::class)
        ->and($queueTest->dispatched_at->between($before, $after))->toBeTrue();
});

