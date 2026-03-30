<?php

use App\Services\AbaDocxPageImageCounter;
use Tests\TestCase;

uses(TestCase::class);

test('counts docx images by page in document body order', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension is required for this test.');
    }

    $service = app(AbaDocxPageImageCounter::class);

    $tempPath = tempnam(sys_get_temp_dir(), 'aba_docx_images_');
    expect($tempPath)->toBeString()->not->toBe('');

    $docxPath = $tempPath.'.docx';
    @rename($tempPath, $docxPath);

    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
    xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing">
    <w:body>
        <w:p>
            <w:r><w:t>Titel der Arbeit</w:t></w:r>
        </w:p>
        <w:p>
            <w:r>
                <w:drawing>
                    <wp:inline />
                </w:drawing>
            </w:r>
        </w:p>
        <w:p>
            <w:r><w:br w:type="page" /></w:r>
        </w:p>
        <w:p>
            <w:r>
                <w:drawing>
                    <wp:inline />
                </w:drawing>
            </w:r>
        </w:p>
    </w:body>
</w:document>
XML;

    $zip = new ZipArchive;
    expect($zip->open($docxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();
    $zip->addFromString('word/document.xml', $xml);
    $zip->close();

    try {
        $result = $service->countByPage($docxPath);

        expect($result['page_image_counts'] ?? null)->toBe([
            1 => 1,
            2 => 1,
        ])
            ->and($result['total_image_count'] ?? null)->toBe(2)
            ->and($result['page_count_with_images'] ?? null)->toBe(2)
            ->and($result['method'] ?? null)->toBe('docx_xml_body_scan');
    } finally {
        @unlink($docxPath);
    }
});
