<?php

use App\Events\TeachersListImportFinishedEvent;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

uses(TestCase::class);

describe('TeachersListImportFinishedEvent', function () {
    it('can be instantiated with all parameters', function () {
        $event = new TeachersListImportFinishedEvent(
            200,
            123,
            'Test message',
            ['key' => 'value']
        );

        expect($event->status)->toBe(200)
            ->and($event->userId)->toBe(123)
            ->and($event->message)->toBe('Test message')
            ->and($event->data)->toBe(['key' => 'value']);
    });

    it('can be instantiated with empty data', function () {
        $event = new TeachersListImportFinishedEvent(
            500,
            456,
            'Error message',
            []
        );

        expect($event->status)->toBe(500)
            ->and($event->userId)->toBe(456)
            ->and($event->message)->toBe('Error message')
            ->and($event->data)->toBe([]);
    });

    it('broadcasts on correct private channel', function () {
        $userId = 789;

        $event = new TeachersListImportFinishedEvent(
            200,
            $userId,
            'Test message'
        );

        $channels = $event->broadcastOn();

        expect($channels)->toBeArray()
            ->toHaveCount(1)
            ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
            ->and($channels[0]->name)->toBe("private-user.{$userId}");
    });

    it('broadcasts on different channel for different users', function () {
        $event1 = new TeachersListImportFinishedEvent(200, 1, 'Message 1');
        $event2 = new TeachersListImportFinishedEvent(200, 2, 'Message 2');

        expect($event1->broadcastOn()[0]->name)->toBe('private-user.1')
            ->and($event2->broadcastOn()[0]->name)->toBe('private-user.2');
    });

    it('implements ShouldBroadcast interface', function () {
        $event = new TeachersListImportFinishedEvent(200, 1, 'Test');

        expect($event)->toBeInstanceOf(ShouldBroadcast::class);
    });

    it('stores success status', function () {
        $event = new TeachersListImportFinishedEvent(
            200,
            1,
            'Success message',
            ['created' => 5, 'updated' => 3, 'deleted' => 2]
        );

        expect($event->status)->toBe(200)
            ->and($event->data['created'])->toBe(5)
            ->and($event->data['updated'])->toBe(3)
            ->and($event->data['deleted'])->toBe(2);
    });

    it('stores error status', function () {
        $event = new TeachersListImportFinishedEvent(
            500,
            1,
            'Error: Invalid headers'
        );

        expect($event->status)->toBe(500)
            ->and($event->message)->toContain('Error');
    });

    it('can be dispatched', function () {
        Event::fake();

        event(new TeachersListImportFinishedEvent(200, 123, 'Test message', []));

        Event::assertDispatched(TeachersListImportFinishedEvent::class, function ($event) {
            return $event->status === 200
                && $event->userId === 123
                && $event->message === 'Test message';
        });
    });

    it('contains German success message structure', function () {
        $event = new TeachersListImportFinishedEvent(
            200,
            1,
            'Die Lehrerliste (Excel) wurde erfolgreich importiert (5 neu, 3 geprüft, 2 gelöscht)',
            ['created' => 5, 'updated' => 3, 'deleted' => 2]
        );

        expect($event->message)->toContain('erfolgreich importiert')
            ->and($event->message)->toContain('neu')
            ->and($event->message)->toContain('geprüft')
            ->and($event->message)->toContain('gelöscht');
    });

    it('contains German error message structure', function () {
        $event = new TeachersListImportFinishedEvent(
            500,
            1,
            'Die Überschriften der Excel-Datei sind nicht korrekt! (Kurz, Nachname, Vorname, Email)'
        );

        expect($event->message)->toContain('Überschriften')
            ->and($event->message)->toContain('nicht korrekt');
    });

    it('preserves all data fields', function () {
        $data = [
            'created' => 10,
            'updated' => 5,
            'deleted' => 3,
            'custom_field' => 'custom_value',
            'nested' => [
                'key' => 'value',
            ],
        ];

        $event = new TeachersListImportFinishedEvent(200, 1, 'Message', $data);

        expect($event->data)->toBe($data)
            ->and($event->data['custom_field'])->toBe('custom_value')
            ->and($event->data['nested']['key'])->toBe('value');
    });

    it('broadcasts only client-safe import counts', function () {
        $event = new TeachersListImportFinishedEvent(200, 1, 'Message', [
            'created' => 10,
            'updated' => 5,
            'deleted' => 3,
            'custom_field' => 'custom_value',
            'nested' => [
                'key' => 'value',
            ],
        ]);

        expect($event->broadcastWith())->toBe([
            'status' => 200,
            'message' => 'Message',
            'data' => [
                'created' => 10,
                'updated' => 5,
                'deleted' => 3,
            ],
        ]);
    });
});
