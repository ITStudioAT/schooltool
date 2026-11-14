<?php

use App\Models\MaterialCardAttachment;
use App\Models\MaterialV2Attachment;
use App\Services\Materials\MaterialService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config(['filesystems.default' => 'local']);
    Storage::fake('local');
    Storage::fake('public');
});

test('editable HTML reads bytes from the validated storage disk', function (string $disk): void {
    Storage::fake('s3');
    $html = '<h1>Übung</h1><p>Gespeicherter Inhalt.</p>';
    Storage::disk($disk)->put('materials/editable.html', $html);
    $attachment = new MaterialCardAttachment([
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'file_path' => 'materials/editable.html',
        'name' => 'editable.html',
        'mime_type' => 'text/html',
    ]);

    expect(app(MaterialService::class)->readEditableTextAttachmentContent($attachment))->toBe($html);
})->with(['local', 'public']);

test('editable HTML resolution retains attachment type and format validation', function (string $type, string $name, string $mime, string $message): void {
    Storage::disk('public')->put('materials/invalid', 'must not be returned');
    $attachment = new MaterialCardAttachment([
        'attachment_type' => $type,
        'file_path' => 'materials/invalid',
        'name' => $name,
        'mime_type' => $mime,
    ]);

    expect(fn () => app(MaterialService::class)->readEditableTextAttachmentContent($attachment))
        ->toThrow(ValidationException::class, $message);
})->with([
    ['link', 'document.html', 'text/html', 'Nur Datei-Anhänge können bearbeitet werden.'],
    [MaterialCardAttachment::TYPE_FILE, 'document.pdf', 'application/pdf', 'Dieser Anhangstyp kann nicht als Text bearbeitet werden.'],
]);

test('material V2 disk resolution preserves main and unknown disk identities', function (string $disk, string $expected): void {
    $attachment = new MaterialV2Attachment(['disk' => $disk]);

    expect($attachment->storageDiskName())->toBe($expected)
        ->and($attachment->disk)->toBe($disk);
})->with([
    ['s3', 's3'],
    ['unknown', 'unknown'],
]);

test('main material resolution retains its configured S3 fallback', function (): void {
    Storage::fake('s3');
    Storage::disk('s3')->put('materials/main.txt', 'main file');

    $method = new ReflectionMethod(MaterialService::class, 'resolveAttachmentStorageDisk');
    expect($method->invoke(app(MaterialService::class), 'materials/main.txt', true)->get('materials/main.txt'))->toBe('main file');
});
