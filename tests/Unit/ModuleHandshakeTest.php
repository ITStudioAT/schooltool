<?php

use App\Services\ModuleHandshake;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

test('module handshake preserves the existing UUID spelling', function (): void {
    $id = 'A263B873-4f27-4167-90f0-7d94ea0f67fa';

    expect((new ModuleHandshake)->parse(json_encode(['schema_version' => 1, 'module_id' => $id])))
        ->toBe(['schema_version' => 1, 'module_id' => $id]);
});

test('module handshake rejects malformed ambiguous and unsupported documents', function (string $contents): void {
    expect(fn () => (new ModuleHandshake)->parse($contents))->toThrow(ValidationException::class);
})->with([
    'broken JSON' => ['{'],
    'array' => ['[]'],
    'missing module' => ['{"schema_version":1}'],
    'wrong UUID' => ['{"schema_version":1,"module_id":"not-a-uuid"}'],
    'wrong version' => ['{"schema_version":2,"module_id":"a263b873-4f27-4167-90f0-7d94ea0f67fa"}'],
    'string version' => ['{"schema_version":"1","module_id":"a263b873-4f27-4167-90f0-7d94ea0f67fa"}'],
    'extra locator' => ['{"schema_version":1,"module_id":"a263b873-4f27-4167-90f0-7d94ea0f67fa","folder_id":"id:claim"}'],
    'duplicate key' => ['{"schema_version":2,"schema_version":1,"module_id":"a263b873-4f27-4167-90f0-7d94ea0f67fa"}'],
    'escaped duplicate' => ['{"schema_version":1,"module_id":"bad","module_\\u0069d":"a263b873-4f27-4167-90f0-7d94ea0f67fa"}'],
    'oversized' => [str_repeat(' ', 4097)],
]);
