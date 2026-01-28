<?php

/**
 * Import116FinishedEvent Tests
 *
 * Tests the broadcasting event for Import 116 job completion including:
 * - Event properties
 * - Broadcasting channel configuration
 * - ShouldBroadcast implementation
 */

use App\Events\Import116FinishedEvent;
use Illuminate\Broadcasting\PrivateChannel;

// ============================================================================
// Event Properties Tests
// ============================================================================

describe('event properties', function () {
    test('stores status correctly', function () {
        $event = new Import116FinishedEvent(200, 1, 'Success message');

        expect($event->status)->toBe(200);
    });

    test('stores userId correctly', function () {
        $event = new Import116FinishedEvent(200, 42, 'Success message');

        expect($event->userId)->toBe(42);
    });

    test('stores message correctly', function () {
        $event = new Import116FinishedEvent(200, 1, 'Import completed successfully');

        expect($event->message)->toBe('Import completed successfully');
    });

    test('stores data array correctly', function () {
        $data = ['created' => 10, 'updated' => 5];

        $event = new Import116FinishedEvent(200, 1, 'Success', $data);

        expect($event->data)->toBe($data)
            ->and($event->data['created'])->toBe(10)
            ->and($event->data['updated'])->toBe(5);
    });

    test('defaults data to empty array', function () {
        $event = new Import116FinishedEvent(200, 1, 'Success');

        expect($event->data)->toBe([]);
    });
});

// ============================================================================
// Status Code Tests
// ============================================================================

describe('status codes', function () {
    test('accepts success status 200', function () {
        $event = new Import116FinishedEvent(200, 1, 'Success');

        expect($event->status)->toBe(200);
    });

    test('accepts not found status 404', function () {
        $event = new Import116FinishedEvent(404, 1, 'File not found');

        expect($event->status)->toBe(404);
    });

    test('accepts validation error status 422', function () {
        $event = new Import116FinishedEvent(422, 1, 'Invalid headers');

        expect($event->status)->toBe(422);
    });

    test('accepts server error status 500', function () {
        $event = new Import116FinishedEvent(500, 1, 'Internal error');

        expect($event->status)->toBe(500);
    });
});

// ============================================================================
// Broadcasting Configuration Tests
// ============================================================================

describe('broadcasting', function () {
    test('broadcasts on private user channel', function () {
        $userId = 123;
        $event = new Import116FinishedEvent(200, $userId, 'Success');

        $channels = $event->broadcastOn();

        expect($channels)->toBeArray()
            ->and($channels)->toHaveCount(1)
            ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
            ->and($channels[0]->name)->toBe('private-user.' . $userId);
    });

    test('broadcasts on correct channel for different user', function () {
        $event = new Import116FinishedEvent(200, 456, 'Success');

        $channels = $event->broadcastOn();

        expect($channels[0]->name)->toBe('private-user.456');
    });

    test('implements ShouldBroadcast interface', function () {
        $event = new Import116FinishedEvent(200, 1, 'Success');

        expect($event)->toBeInstanceOf(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class);
    });
});

// ============================================================================
// Error Scenario Tests
// ============================================================================

describe('error scenarios', function () {
    test('handles file not found error', function () {
        $event = new Import116FinishedEvent(
            404,
            1,
            'Import 116 fehlgeschlagen: Datei nicht gefunden.',
            ['path' => 'app/private/1/excel/116.xlsx']
        );

        expect($event->status)->toBe(404)
            ->and($event->message)->toContain('nicht gefunden')
            ->and($event->data['path'])->toBe('app/private/1/excel/116.xlsx');
    });

    test('handles header validation error', function () {
        $event = new Import116FinishedEvent(
            422,
            1,
            'Import 116 fehlgeschlagen: Spaltenüberschriften nicht erkannt.',
            []
        );

        expect($event->status)->toBe(422)
            ->and($event->message)->toContain('Spaltenüberschriften');
    });

    test('handles success with statistics', function () {
        $event = new Import116FinishedEvent(
            200,
            1,
            'Import 116 wurde abgeschlossen.',
            ['created' => 100, 'updated' => 50]
        );

        expect($event->status)->toBe(200)
            ->and($event->message)->toContain('abgeschlossen')
            ->and($event->data['created'])->toBe(100)
            ->and($event->data['updated'])->toBe(50);
    });
});

// ============================================================================
// Traits Tests
// ============================================================================

describe('traits', function () {
    test('uses Dispatchable trait', function () {
        expect(class_uses_recursive(Import116FinishedEvent::class))
            ->toContain(\Illuminate\Foundation\Events\Dispatchable::class);
    });

    test('uses InteractsWithSockets trait', function () {
        expect(class_uses_recursive(Import116FinishedEvent::class))
            ->toContain(\Illuminate\Broadcasting\InteractsWithSockets::class);
    });

    test('uses SerializesModels trait', function () {
        expect(class_uses_recursive(Import116FinishedEvent::class))
            ->toContain(\Illuminate\Queue\SerializesModels::class);
    });
});
