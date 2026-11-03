<?php

use App\Services\Materials\MaterialAttachmentPreviewService;
use App\Support\PowerPoint2007Reader;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Shape\RichText;

test('presentation relationships use relative ZIP entry names on every platform', function (string $part, string $expected): void {
    $method = new ReflectionMethod(PowerPoint2007Reader::class, 'relsOf');

    expect($method->invoke(new PowerPoint2007Reader, $part))->toBe($expected);
})->with([
    'package root' => ['', '_rels/.rels'],
    'root part' => ['presentation.xml', '_rels/presentation.xml.rels'],
    'presentation part' => ['ppt/presentation.xml', 'ppt/_rels/presentation.xml.rels'],
    'nested slide' => ['ppt/slides/slide1.xml', 'ppt/slides/_rels/slide1.xml.rels'],
]);

test('presentation targets retain canonical relative ZIP resolution', function (string $relsPart, string $target, string $expected): void {
    $method = new ReflectionMethod(PowerPoint2007Reader::class, 'resolve');

    expect($method->invoke(new PowerPoint2007Reader, $relsPart, $target))->toBe($expected);
})->with([
    'root document' => ['_rels/.rels', 'ppt/presentation.xml', 'ppt/presentation.xml'],
    'absolute document' => ['_rels/.rels', '/ppt/presentation.xml', 'ppt/presentation.xml'],
    'slide' => ['ppt/_rels/presentation.xml.rels', 'slides/slide1.xml', 'ppt/slides/slide1.xml'],
    'parent image' => ['ppt/slides/_rels/slide1.xml.rels', '../media/image1.png', 'ppt/media/image1.png'],
    'dot and parent image' => ['ppt/slides/_rels/slide1.xml.rels', './../media/image1.png', 'ppt/media/image1.png'],
]);

test('presentation HTML preserves source bytes and the existing OpenDocument reader', function (string $format): void {
    $presentation = new PhpPresentation;
    $shape = new RichText;
    $shape->createTextRun('Portable presentation preview');
    $presentation->getActiveSlide()->addShape($shape);
    $path = tempnam(sys_get_temp_dir(), 'presentation-preview-');

    try {
        IOFactory::createWriter($presentation, $format)->save($path);
        $before = hash_file('sha256', $path);
        $method = new ReflectionMethod(MaterialAttachmentPreviewService::class, 'renderPresentationHtml');
        $html = $method->invoke(new MaterialAttachmentPreviewService, $path);

        expect($html)->toContain('Portable presentation preview')
            ->and(hash_file('sha256', $path))->toBe($before);
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
})->with(['PowerPoint2007', 'ODPresentation']);

test('the platform reader still rejects unrelated ZIP packages', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'unrelated-presentation-');

    try {
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('content.txt', 'This is not a presentation.');
        $zip->close();
        $reader = new PowerPoint2007Reader;

        expect($reader->canRead($path))->toBeFalse();
    } finally {
        unset($reader);
        if (is_file($path)) {
            unlink($path);
        }
    }
});
