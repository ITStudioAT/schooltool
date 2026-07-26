<?php

use App\Models\MaterialV2Attachment;
use App\Services\MaterialsV2\MaterialV2DocumentTextExtractor;
use Dompdf\Dompdf;
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

it('extracts text from PDF documents without OCR', function () {
    Storage::fake('local');
    $pdf = new Dompdf;
    $pdf->loadHtml('<h1>Photosynthese</h1><p>Chlorophyll speichert Lichtenergie.</p>');
    $pdf->render();
    Storage::disk('local')->put('materials-v2/test/biology.pdf', $pdf->output());

    $attachment = new MaterialV2Attachment([
        'disk' => 'local',
        'path' => 'materials-v2/test/biology.pdf',
        'original_name' => 'biology.pdf',
        'mime_type' => 'application/pdf',
    ]);

    expect(app(MaterialV2DocumentTextExtractor::class)->extract($attachment))
        ->toContain('Photosynthese')
        ->toContain('Chlorophyll');
});

it('extracts paragraphs and German umlauts from DOCX documents', function () {
    Storage::fake('local');
    $archive = createOfficeArchive([
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:p><w:r><w:t>Künstliche Intelligenz</w:t></w:r></w:p>
        <w:p><w:r><w:t>Datenschutz schützt persönliche Daten.</w:t></w:r></w:p>
    </w:body>
</w:document>
XML,
    ]);
    Storage::disk('local')->put('materials-v2/test/artificial-intelligence.docx', $archive);

    $attachment = new MaterialV2Attachment([
        'disk' => 'local',
        'path' => 'materials-v2/test/artificial-intelligence.docx',
        'original_name' => 'artificial-intelligence.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ]);

    $text = app(MaterialV2DocumentTextExtractor::class)->extract($attachment);

    expect($text)
        ->toContain('Künstliche Intelligenz')
        ->toContain('Datenschutz schützt persönliche Daten.')
        ->and(strpos($text, 'Künstliche Intelligenz'))
        ->toBeLessThan(strpos($text, 'Datenschutz schützt persönliche Daten.'));
});

it('extracts slides in their natural order from PPTX documents', function () {
    Storage::fake('local');
    $archive = createOfficeArchive([
        'ppt/slides/slide2.xml' => '<p:sld xmlns:p="p" xmlns:a="a"><a:p><a:r><a:t>Zweite Folie</a:t></a:r></a:p></p:sld>',
        'ppt/slides/slide1.xml' => '<p:sld xmlns:p="p" xmlns:a="a"><a:p><a:r><a:t>Erste Folie</a:t></a:r></a:p></p:sld>',
    ]);
    Storage::disk('local')->put('materials-v2/test/presentation.pptx', $archive);

    $attachment = new MaterialV2Attachment([
        'disk' => 'local',
        'path' => 'materials-v2/test/presentation.pptx',
        'original_name' => 'presentation.pptx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ]);

    $text = app(MaterialV2DocumentTextExtractor::class)->extract($attachment);

    expect($text)
        ->toContain('Erste Folie')
        ->toContain('Zweite Folie')
        ->and(strpos($text, 'Erste Folie'))->toBeLessThan(strpos($text, 'Zweite Folie'));
});

it('returns an empty result for empty text and rejects malformed archives clearly', function () {
    Storage::fake('local');
    Storage::disk('local')->put('materials-v2/test/empty.txt', '');
    Storage::disk('local')->put('materials-v2/test/broken.docx', 'not a zip archive');

    $emptyAttachment = new MaterialV2Attachment([
        'disk' => 'local',
        'path' => 'materials-v2/test/empty.txt',
        'original_name' => 'empty.txt',
        'mime_type' => 'text/plain',
    ]);
    $brokenAttachment = new MaterialV2Attachment([
        'disk' => 'local',
        'path' => 'materials-v2/test/broken.docx',
        'original_name' => 'broken.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ]);

    expect(app(MaterialV2DocumentTextExtractor::class)->extract($emptyAttachment))->toBe('');

    app(MaterialV2DocumentTextExtractor::class)->extract($brokenAttachment);
})->throws(RuntimeException::class, 'Dokumentarchiv konnte nicht geöffnet werden');

/**
 * @param  array<string, string>  $entries
 */
function createOfficeArchive(array $entries): string
{
    $temporaryPath = tempnam(sys_get_temp_dir(), 'materials-v2-office-');
    expect($temporaryPath)->toBeString();

    $archive = new ZipArchive;
    expect($archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();

    foreach ($entries as $path => $content) {
        $archive->addFromString($path, $content);
    }

    $archive->close();
    $contents = file_get_contents($temporaryPath);
    unlink($temporaryPath);

    expect($contents)->toBeString();

    return $contents;
}
