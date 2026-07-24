<?php

use App\Models\MaterialV2Attachment;
use App\Services\MaterialsV2\MaterialV2DocumentTextExtractor;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

it('extracts and normalizes text files from storage', function () {
    Storage::fake('local');
    Storage::disk('local')->put('materials-v2/test/biology.txt', "Chlorophyll   wandelt Licht um.\n\n\nGlucose entsteht.");

    $attachment = new MaterialV2Attachment([
        'disk' => 'local',
        'path' => 'materials-v2/test/biology.txt',
        'original_name' => 'biology.txt',
        'mime_type' => 'text/plain',
    ]);

    $text = app(MaterialV2DocumentTextExtractor::class)->extract($attachment);

    expect($text)
        ->toContain('Chlorophyll wandelt Licht um.')
        ->toContain('Glucose entsteht.');
});

it('marks image attachments as unsupported for text extraction', function () {
    $attachment = new MaterialV2Attachment([
        'disk' => 'local',
        'path' => 'materials-v2/test/image.png',
        'original_name' => 'image.png',
        'mime_type' => 'image/png',
    ]);

    expect(app(MaterialV2DocumentTextExtractor::class)->supports($attachment))->toBeFalse();
});
