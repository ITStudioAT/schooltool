<?php

use App\Services\PdfTableDompdfDriver;
use App\Services\PdfTableGenerator;
use Dompdf\Canvas;
use Dompdf\FontMetrics;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

uses(TestCase::class);

function pdfTableDocument(bool $withTitlePage = true): array
{
    $document = [
        'eyebrow' => 'Schooltool report',
        'title' => 'Attendance overview',
        'subtitle' => 'A reusable A4 table document.',
        'user_name' => 'Erika Muster',
        'print_date_time' => '01.08.2026 · 14:30',
        'metadata' => [
            'Class' => '8A',
            'Period' => 'August 2026',
        ],
        'columns' => [
            ['key' => 'name', 'label' => 'Student', 'width' => '55%'],
            ['key' => 'days', 'label' => 'Days', 'width' => '20%', 'align' => 'center'],
            ['key' => 'rate', 'label' => 'Attendance', 'width' => '25%', 'align' => 'right'],
        ],
        'rows' => [
            ['name' => 'Ada Lovelace', 'days' => 18, 'rate' => '94.7%'],
            ['name' => '<script>alert("unsafe")</script>', 'days' => 17, 'rate' => '89.5%'],
        ],
        'note' => 'Generated for review.',
    ];

    if (! $withTitlePage) {
        return $document;
    }

    $document['title_page'] = [
        'title' => 'Annual attendance',
        'subtitle' => 'School year 2025/2026',
        'comments' => 'Internal review copy.',
    ];

    return $document;
}

it('defaults to a portrait DIN A4 page with the print margins', function () {
    $pdf = app(PdfTableGenerator::class)->generate(pdfTableDocument());

    expect($pdf->viewName)->toBe('pdfs.pdfTableGenerator')
        ->and($pdf->format)->toBe('a4')
        ->and($pdf->orientation)->toBe('Portrait')
        ->and($pdf->margins)->toBe([
            'top' => 10.0,
            'right' => 10.0,
            'bottom' => 10.0,
            'left' => 20.0,
            'unit' => 'mm',
        ]);
});

it('starts the complete running header after the 10 mm top margin', function () {
    $driver = new PdfTableDompdfDriver([
        'title' => 'Attendance overview',
        'subtitle' => 'School year 2025/2026',
        'user_name' => 'Erika Muster',
        'print_date_time' => '01.08.2026 · 14:30',
        'has_title_page' => false,
    ]);
    $canvas = Mockery::mock(Canvas::class);
    $fontMetrics = Mockery::mock(FontMetrics::class);
    $expectedHeaderTop = 10 * 72 / 25.4;
    $expectedHeaderDetailsTop = 14 * 72 / 25.4;
    $expectedHeaderBorderTop = 19 * 72 / 25.4;

    $canvas->shouldReceive('get_width')->once()->andReturn(841.89);
    $canvas->shouldReceive('text')
        ->twice()
        ->withArgs(fn (
            float $left,
            float $top,
            string $text,
            string $font,
            float $size,
            array $color,
        ): bool => abs($top - $expectedHeaderTop) < 0.000001);
    $canvas->shouldReceive('text')
        ->twice()
        ->withArgs(fn (
            float $left,
            float $top,
            string $text,
            string $font,
            float $size,
            array $color,
        ): bool => abs($top - $expectedHeaderDetailsTop) < 0.000001);
    $canvas->shouldReceive('line')
        ->once()
        ->withArgs(fn (
            float $left,
            float $top,
            float $right,
            float $bottom,
            array $color,
            float $width,
        ): bool => abs($top - $expectedHeaderBorderTop) < 0.000001
            && abs($bottom - $expectedHeaderBorderTop) < 0.000001)
        ->andReturnNull();
    $fontMetrics->shouldReceive('getTextWidth')->andReturn(40.0);

    $drawHeader = new ReflectionMethod($driver, 'drawHeader');
    $drawHeader->invoke(
        $driver,
        $canvas,
        $fontMetrics,
        'regular-font',
        'bold-font',
        1,
        1,
    );
});

it('supports DIN A4 landscape documents', function () {
    $document = pdfTableDocument();
    $document['orientation'] = 'landscape';

    $pdf = app(PdfTableGenerator::class)->generate($document);
    $html = view($pdf->viewName, $pdf->viewData)->render();

    expect($pdf->format)->toBe('a4')
        ->and($pdf->orientation)->toBe('Landscape')
        ->and($html)->toContain('size: A4 landscape;')
        ->and($html)->toContain('height: 170mm;');
});

it('renders the optional title page before the table document', function () {
    $pdf = app(PdfTableGenerator::class)->generate(pdfTableDocument());
    $html = view($pdf->viewName, $pdf->viewData)->render();

    expect($html)->toContain('page-break-after: always;')
        ->and($html)->toContain('Annual attendance')
        ->and($html)->toContain('School year 2025/2026')
        ->and($html)->toContain('Erika Muster')
        ->and($html)->toContain('01.08.2026 · 14:30')
        ->and($html)->toContain('Internal review copy.')
        ->and(strpos($html, '<section class="title-page">'))->toBeLessThan(strpos($html, '<main class="pdf-table-generator">'));
});

it('can generate only the title page without a blank content page or running header', function () {
    $document = pdfTableDocument();
    $document['title_page_only'] = true;

    $pdf = app(PdfTableGenerator::class)->generate($document);
    $html = view($pdf->viewName, $pdf->viewData)->render();
    $pages = (new Parser)->parseContent(base64_decode($pdf->base64(), true))->getPages();
    $pageText = $pages[0]->getText();

    expect($html)->toContain('class="title-page title-page-only"')
        ->and($html)->not->toContain('<main class="pdf-table-generator">')
        ->and($pages)->toHaveCount(1)
        ->and($pageText)->toContain('Annual attendance')
        ->and($pageText)->toContain('School year 2025/2026')
        ->and($pageText)->toContain('Erika Muster')
        ->and($pageText)->toContain('01.08.2026 · 14:30')
        ->and($pageText)->toContain('Internal review copy.')
        ->and($pageText)->not->toContain('Ada Lovelace')
        ->and($pageText)->not->toContain('Page 1 /');
});

it('starts with a numbered content page when the title page is omitted', function () {
    $pdf = app(PdfTableGenerator::class)->generate(pdfTableDocument(withTitlePage: false));
    $html = view($pdf->viewName, $pdf->viewData)->render();
    $pages = (new Parser)->parseContent(base64_decode($pdf->base64(), true))->getPages();
    $pageText = $pages[0]->getText();

    expect($html)->not->toContain('<section class="title-page">')
        ->and($pages)->toHaveCount(1)
        ->and($pageText)->toContain('Attendance overview')
        ->and($pageText)->toContain('A reusable A4 table document.')
        ->and($pageText)->toContain('Erika Muster')
        ->and($pageText)->toContain('01.08.2026 · 14:30')
        ->and($pageText)->toContain('Page 1 / 1');
});

it('renders each configured table page on a new PDF page', function () {
    $document = pdfTableDocument(withTitlePage: false);
    $document['table_pages'] = [
        [
            'columns' => $document['columns'],
            'rows' => [
                ['name' => 'First page student', 'days' => 18, 'rate' => '94.7%'],
            ],
        ],
        [
            'columns' => $document['columns'],
            'rows' => [
                ['name' => 'Second page student', 'days' => 17, 'rate' => '89.5%'],
            ],
        ],
    ];
    unset($document['columns'], $document['rows']);

    $pdf = app(PdfTableGenerator::class)->generate($document);
    $html = view($pdf->viewName, $pdf->viewData)->render();
    $pages = (new Parser)->parseContent(base64_decode($pdf->base64(), true))->getPages();

    expect($pdf->viewData['document']['table_pages'])->toHaveCount(2)
        ->and($pdf->viewData['document']['columns'])->toBe($pdf->viewData['document']['table_pages'][0]['columns'])
        ->and($html)->toContain('page-break-before: always;')
        ->and(substr_count($html, 'class="document-table-page"'))->toBe(2)
        ->and($pages)->toHaveCount(2)
        ->and($pages[0]->getText())->toContain('First page student')
        ->and($pages[0]->getText())->not->toContain('Second page student')
        ->and($pages[0]->getText())->toContain('Page 1 / 2')
        ->and($pages[1]->getText())->toContain('Second page student')
        ->and($pages[1]->getText())->not->toContain('First page student')
        ->and($pages[1]->getText())->toContain('Page 2 / 2');
});

it('supports physical table widths and rotated row-spanning columns', function () {
    $document = pdfTableDocument(withTitlePage: false);
    $document['orientation'] = 'landscape';
    $document['columns'] = [
        ['key' => 'student', 'label' => 'Student', 'width' => '37mm'],
        [
            'key' => 'holiday',
            'label' => '17.09.',
            'width' => '10mm',
            'row_span_value' => 'Herbstferien',
            'row_span_rotation' => -90,
            'row_span_font_size' => 6,
            'cell_background' => '#d9f0df',
            'header_font_size' => 6,
            'header_padding' => ['top' => 1.4, 'right' => 0.2, 'bottom' => 1.4, 'left' => 0.2],
        ],
        ['key' => 'course_date', 'label' => '24.09.', 'width' => '23mm'],
    ];
    $document['rows'] = [
        ['student' => 'Ada Lovelace', 'holiday' => '', 'course_date' => ''],
        ['student' => 'Grace Hopper', 'holiday' => '', 'course_date' => ''],
    ];

    $pdf = app(PdfTableGenerator::class)->generate($document);
    $html = view($pdf->viewName, $pdf->viewData)->render();
    $tablePage = $pdf->viewData['document']['table_pages'][0];

    expect($tablePage['table_layout_width'])->toBe('70.0000mm')
        ->and($tablePage['column_layout_widths'])->toBe(['37mm', '10mm', '23mm'])
        ->and($tablePage['columns'][1]['header_font_size'])->toBe(6.0)
        ->and($tablePage['columns'][1]['header_padding'])->toBe([
            'top' => 1.4,
            'right' => 0.2,
            'bottom' => 1.4,
            'left' => 0.2,
        ])
        ->and($html)->toContain('class="document-table"')
        ->and($html)->toContain('style="width: 70.0000mm;"')
        ->and($html)->toContain('font-size: 6pt; padding: 1.4mm 0.2mm 1.4mm 0.2mm;')
        ->and(substr_count($html, 'rowspan="2"'))->toBe(1)
        ->and($html)->toContain('row-spanning-column-cell')
        ->and($html)->toContain('class="rotated-table-cell"')
        ->and($html)->toContain('transform: rotate(-90deg); font-size: 6pt;')
        ->and($html)->toContain('background-color: #d9f0df;')
        ->and($html)->toContain('background: #d9f0df !important;')
        ->and($html)->toContain('.document-table thead tr:last-child')
        ->and($html)->toContain('border-bottom: 0.6mm solid #16a394 !important;')
        ->and(substr_count($html, 'border-bottom: 0.6mm solid #16a394 !important;'))->toBeGreaterThanOrEqual(3)
        ->and($html)->toContain('.document-table th.spacer-column')
        ->and(substr_count($html, 'Herbstferien'))->toBe(1);
});

it('renders a print-safe reusable table document', function () {
    $pdf = app(PdfTableGenerator::class)->generate(pdfTableDocument());
    $html = view($pdf->viewName, $pdf->viewData)->render();

    expect($html)->toContain('size: A4 portrait;')
        ->and($html)->toContain('margin: 10mm 10mm 10mm 20mm;')
        ->and($html)->toContain('display: table-header-group;')
        ->and($html)->toContain('class="running-header-space"')
        ->and($html)->toContain('height: 10mm;')
        ->and($html)->toContain('padding: 1.4mm 2.8mm;')
        ->and($html)->toContain('padding: 1.2mm 2.8mm;')
        ->and($html)->toContain('font-size: 10pt;')
        ->and($html)->toContain('line-height: 1.2;')
        ->and($html)->toContain('border-right: 0.35mm solid #b9cecb;')
        ->and($html)->toContain('padding-left: 0;')
        ->and($html)->toContain('padding-right: 0;')
        ->and($html)->toContain('page-break-inside: avoid;')
        ->and($html)->toContain('Attendance overview')
        ->and($html)->toContain('Ada Lovelace')
        ->and($html)->toContain('&lt;script&gt;alert(&quot;unsafe&quot;)&lt;/script&gt;')
        ->and($html)->not->toContain('<script>alert("unsafe")</script>');
});

it('renders a fixed-width column with safe multi-line cell content', function () {
    $document = pdfTableDocument(withTitlePage: false);
    $document['orientation'] = 'landscape';
    $document['columns'] = [
        [
            'key' => 'student',
            'label' => 'Student',
            'width' => '30mm',
            'font_size' => 8,
            'cell_padding' => ['top' => 0.8, 'right' => 1.6, 'bottom' => 0.8, 'left' => 0],
        ],
        [
            'key' => 'description',
            'label' => 'Description',
            'width' => '177mm',
            'font_size' => 9.5,
            'cell_padding' => ['top' => 1, 'right' => 2, 'bottom' => 1.5, 'left' => 2.5],
        ],
        ['key' => 'status', 'label' => 'Status', 'width' => '60mm'],
    ];
    $document['rows'] = [
        [
            'student' => ['Muster 2A', 'Erika'],
            'description' => 'Description marker',
            'status' => 'Status marker',
        ],
        [
            'student' => ['Example 2A', 'Alex'],
            'description' => '<script>alert("unsafe")</script>',
            'status' => '',
        ],
    ];

    $pdf = app(PdfTableGenerator::class)->generate($document);
    $html = view($pdf->viewName, $pdf->viewData)->render();
    $page = (new Parser)->parseContent(base64_decode($pdf->base64(), true))->getPages()[0];
    $textPositions = collect($page->getDataTm());
    $studentPosition = $textPositions->first(fn (array $position): bool => str_contains($position[1], 'Muster 2A'));
    $descriptionPosition = $textPositions->first(fn (array $position): bool => str_contains($position[1], 'Description marker'));
    $studentLeftPoints = (float) $studentPosition[0][4];
    $descriptionLeftPoints = (float) $descriptionPosition[0][4];
    $descriptionLeftPaddingMillimetres = $pdf->viewData['document']['columns'][1]['cell_padding']['left'];
    $firstColumnWidthMillimetres = ($descriptionLeftPoints - $studentLeftPoints) / (72 / 25.4)
        - $descriptionLeftPaddingMillimetres;

    expect($pdf->viewData['document']['columns'][0]['width'])->toBe('30mm')
        ->and($pdf->viewData['document']['column_layout_widths'])->toBe([
            '11.17041199%',
            '66.35767790%',
            '22.47191011%',
        ])
        ->and($html)->toContain('style="width: 11.17041199%;"')
        ->and($html)->toContain('style="width: 11.17041199%; font-size: 8pt; padding: 0.8mm 1.6mm 0.8mm 0mm;"')
        ->and($html)->toContain('style="width: 66.35767790%; font-size: 9.5pt; padding: 1mm 2mm 1.5mm 2.5mm;"')
        ->and($html)->toContain('<span class="table-cell-line">Muster 2A</span>')
        ->and($html)->toContain('&lt;script&gt;alert(&quot;unsafe&quot;)&lt;/script&gt;')
        ->and($html)->not->toContain('<script>alert("unsafe")</script>')
        ->and(abs($firstColumnWidthMillimetres - 30.0))->toBeLessThan(0.02);
});

it('generates a title page and exactly two content pages with minimal headers', function () {
    $document = pdfTableDocument();
    $document['orientation'] = 'landscape';
    unset($document['metadata']);
    $document['rows'] = collect(range(1, 26))
        ->map(fn (int $number): array => [
            'name' => "Student {$number}",
            'days' => 18,
            'rate' => '94.7%',
        ])
        ->all();

    $pdf = app(PdfTableGenerator::class)->generate($document);
    $content = base64_decode($pdf->base64(), true);
    $pages = (new Parser)->parseContent($content)->getPages();
    $contentPages = array_slice($pages, 1);
    $contentPageCount = count($contentPages);

    expect($content)->toBeString()
        ->and($content)->toStartWith('%PDF-')
        ->and($pages)->toHaveCount(3)
        ->and($contentPageCount)->toBe(2)
        ->and($pages[0]->getText())->not->toContain('Page 1 /');

    foreach ($contentPages as $index => $page) {
        $pageText = $page->getText();

        expect($pageText)->toContain('Attendance overview')
            ->and($pageText)->toContain('A reusable A4 table document.')
            ->and($pageText)->toContain('Erika Muster')
            ->and($pageText)->toContain('01.08.2026 · 14:30')
            ->and($pageText)->toContain('Page '.($index + 1)." / {$contentPageCount}");
    }
});
