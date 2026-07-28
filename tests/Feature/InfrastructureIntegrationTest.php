<?php

use App\Models\MaterialV2Item;
use App\Models\School;
use App\Models\User;
use App\Services\MaterialsV2\MaterialV2ScoutSearchService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

uses(DatabaseTruncation::class);

it('uses MySQL and executes the Materials V2 full-text Scout index', function (): void {
    expect(DB::connection()->getDriverName())->toBe('mysql')
        ->and(DB::connection()->getDatabaseName())
        ->toMatch('/^pest_test(?:_test_\d+)?$/');

    $fullTextIndex = collect(DB::select(
        "SHOW INDEX FROM material_v2_items WHERE Key_name = 'material_v2_items_search_fulltext'",
    ));

    expect($fullTextIndex)->toHaveCount(3);

    $expectedIndexes = [
        'teachers' => [
            'teachers_school_email_index',
            'teachers_email_index',
            'teachers_school_sort_index',
        ],
        'tutoring_subjects' => [
            'tutoring_subjects_school_short_index',
        ],
        'tutoring_offers' => [
            'tutoring_offers_public_index',
            'tutoring_offers_cross_school_index',
            'tutoring_offers_user_created_index',
            'tutoring_offers_subject_index',
            'tutoring_offers_school_mentor_active_index',
        ],
        'tutoring_offer_requests' => [
            'tutoring_requests_school_created_index',
            'tutoring_requests_offer_sender_index',
            'tutoring_requests_sender_inbox_index',
            'tutoring_requests_recipient_inbox_index',
        ],
    ];

    foreach ($expectedIndexes as $table => $indexNames) {
        $actualIndexNames = collect(DB::select("SHOW INDEX FROM {$table}"))
            ->pluck('Key_name')
            ->unique();

        expect($actualIndexNames)->toContain(...$indexNames);
    }

    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);
    $searchToken = 'scout'.Str::lower(Str::random(16));
    $item = MaterialV2Item::factory()->create([
        'school_id' => $school->id,
        'user_id' => $user->id,
        'title' => "Infrastructure {$searchToken}",
        'description' => 'Committed MySQL full-text integration record.',
        'search_text' => "integration {$searchToken}",
    ]);

    try {
        $results = app(MaterialV2ScoutSearchService::class)
            ->search($user, $searchToken, 1, 18);

        expect($results->total())->toBe(1)
            ->and($results->items()[0]->is($item))->toBeTrue();
    } finally {
        $item->forceDelete();
        $user->delete();
        $school->delete();
    }
})->group('integration', 'mysql');

it('pushes and consumes an isolated Redis queue job', function (): void {
    $queueName = 'integration-'.Str::lower(Str::random(20));
    config()->set('queue.connections.redis-integration', [
        'driver' => 'redis',
        'connection' => 'integration',
        'queue' => $queueName,
        'retry_after' => 60,
        'block_for' => null,
        'after_commit' => false,
    ]);

    Redis::purge('integration');

    $redis = Redis::connection('integration');
    $queue = Queue::connection('redis-integration');
    $probeKey = 'probe:'.Str::lower(Str::random(20));

    try {
        $redis->set($probeKey, 'ready');
        expect((string) $redis->get($probeKey))->toBe('ready');

        $queue->pushRaw(json_encode([
            'uuid' => (string) Str::uuid(),
            'displayName' => 'Infrastructure integration probe',
            'attempts' => 0,
        ], JSON_THROW_ON_ERROR), $queueName);

        expect($queue->size($queueName))->toBe(1);

        $job = $queue->pop($queueName);

        expect($job)->not->toBeNull();

        $job?->delete();

        expect($queue->size($queueName))->toBe(0);
    } finally {
        $queue->clear($queueName);
        $redis->del($probeKey);
        Redis::purge('integration');
    }
})->group('integration', 'redis');
