<?php

use App\Services\AbaPandocAstNormalizerService;

uses(Tests\TestCase::class);

test('reconstructs readable text and inline signals from paragraph inlines', function () {
    $ast = [
        'blocks' => [
            [
                't' => 'Para',
                'c' => [
                    ['t' => 'Str', 'c' => 'Hallo'],
                    ['t' => 'Space'],
                    ['t' => 'Strong', 'c' => [['t' => 'Str', 'c' => 'Welt']]],
                    ['t' => 'Space'],
                    ['t' => 'Emph', 'c' => [['t' => 'Str', 'c' => 'Test']]],
                    ['t' => 'LineBreak'],
                    ['t' => 'Str', 'c' => 'Zeile2'],
                ],
            ],
        ],
    ];

    $result = app(AbaPandocAstNormalizerService::class)->normalizeAst($ast);

    expect($result['ok'] ?? false)->toBeTrue()
        ->and($result['blocks'])->toHaveCount(1)
        ->and($result['blocks'][0]['type'] ?? null)->toBe('paragraph')
        ->and($result['blocks'][0]['text'] ?? null)->toBe("Hallo Welt Test\nZeile2")
        ->and($result['blocks'][0]['inline_signals']['has_strong'] ?? false)->toBeTrue()
        ->and($result['blocks'][0]['inline_signals']['has_emphasis'] ?? false)->toBeTrue()
        ->and(($result['blocks'][0]['inline_signals']['line_break_count'] ?? 0) >= 1)->toBeTrue();
});

test('keeps explicit pandoc header as heading and resolves section type from rules', function () {
    $ast = [
        'blocks' => [
            [
                't' => 'Header',
                'c' => [
                    1,
                    ['', [], []],
                    [
                        ['t' => 'Str', 'c' => 'Literaturverzeichnis'],
                    ],
                ],
            ],
        ],
    ];

    $result = app(AbaPandocAstNormalizerService::class)->normalizeAst($ast);
    $block = $result['blocks'][0] ?? [];

    expect($result['ok'] ?? false)->toBeTrue()
        ->and($block['type'] ?? null)->toBe('heading')
        ->and($block['heading_level'] ?? null)->toBe(1)
        ->and($block['section_hint']['section_type'] ?? null)->toBe('bibliography')
        ->and($block['section_hint']['confidence'] ?? null)->toBe('high');
});

test('detects short strong paragraph as heuristic heading with chapter rule hint', function () {
    $ast = [
        'blocks' => [
            [
                't' => 'Para',
                'c' => [
                    ['t' => 'Strong', 'c' => [['t' => 'Str', 'c' => 'Conclusio']]],
                ],
            ],
        ],
    ];

    $result = app(AbaPandocAstNormalizerService::class)->normalizeAst($ast);
    $block = $result['blocks'][0] ?? [];

    expect($result['ok'] ?? false)->toBeTrue()
        ->and($block['type'] ?? null)->toBe('heading')
        ->and($block['section_hint']['section_type'] ?? null)->toBe('chapter')
        ->and($block['classification']['confidence'] ?? null)->toBe('medium')
        ->and($block['classification']['strategy'] ?? null)->toBe('heuristic');
});

test('maps image inline paragraph to image block and keeps target metadata', function () {
    $ast = [
        'blocks' => [
            [
                't' => 'Para',
                'c' => [
                    [
                        't' => 'Image',
                        'c' => [
                            ['', [], []],
                            [['t' => 'Str', 'c' => 'Abbildung 1']],
                            ['media/image1.png', ''],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $result = app(AbaPandocAstNormalizerService::class)->normalizeAst($ast);
    $block = $result['blocks'][0] ?? [];

    expect($result['ok'] ?? false)->toBeTrue()
        ->and($block['type'] ?? null)->toBe('image')
        ->and($block['image']['target'] ?? null)->toBe('media/image1.png')
        ->and($block['image']['alt_text'] ?? null)->toBe('Abbildung 1');
});

test('recognizes rule keywords for consent declaration and keeps unknown blocks stable', function () {
    $ast = [
        'blocks' => [
            [
                't' => 'Para',
                'c' => [
                    ['t' => 'Strong', 'c' => [['t' => 'Str', 'c' => 'Eigenständigkeitserklärung']]],
                ],
            ],
            [
                't' => 'PandocCustomUnknown',
                'c' => [
                    ['foo' => 'bar'],
                ],
            ],
        ],
    ];

    $result = app(AbaPandocAstNormalizerService::class)->normalizeAst($ast);

    expect($result['ok'] ?? false)->toBeTrue()
        ->and($result['blocks'])->toHaveCount(2)
        ->and($result['blocks'][0]['section_hint']['section_type'] ?? null)->toBe('consent_declaration')
        ->and(in_array($result['blocks'][1]['type'] ?? '', ['paragraph', 'unknown'], true))->toBeTrue();
});

test('marks probable toc artifact headings as low confidence heuristic', function () {
    $ast = [
        'blocks' => [
            [
                't' => 'Header',
                'c' => [
                    1,
                    ['', [], []],
                    [
                        ['t' => 'Str', 'c' => '1. Einleitung 5'],
                    ],
                ],
            ],
        ],
    ];

    $result = app(AbaPandocAstNormalizerService::class)->normalizeAst($ast);
    $block = $result['blocks'][0] ?? [];

    expect($result['ok'] ?? false)->toBeTrue()
        ->and($block['type'] ?? null)->toBe('heading')
        ->and(in_array('probable_toc_artifact', $block['problem_tags'] ?? [], true))->toBeTrue()
        ->and($block['classification']['confidence'] ?? null)->toBe('low')
        ->and($block['classification']['strategy'] ?? null)->toBe('heuristic');
});

test('marks empty headings as problematic', function () {
    $ast = [
        'blocks' => [
            [
                't' => 'Header',
                'c' => [
                    1,
                    ['', [], []],
                    [],
                ],
            ],
        ],
    ];

    $result = app(AbaPandocAstNormalizerService::class)->normalizeAst($ast);
    $block = $result['blocks'][0] ?? [];

    expect($result['ok'] ?? false)->toBeTrue()
        ->and($block['type'] ?? null)->toBe('heading')
        ->and(($block['plain_text'] ?? '') === '')->toBeTrue()
        ->and(in_array('empty_heading', $block['problem_tags'] ?? [], true))->toBeTrue();
});

test('marks suspicious heading texts as problematic', function () {
    $ast = [
        'blocks' => [
            [
                't' => 'Para',
                'c' => [
                    ['t' => 'Strong', 'c' => [['t' => 'Str', 'c' => 'Osteoporose: ...3.1.5. Knochendichte']]],
                ],
            ],
        ],
    ];

    $result = app(AbaPandocAstNormalizerService::class)->normalizeAst($ast);
    $block = $result['blocks'][0] ?? [];

    expect($result['ok'] ?? false)->toBeTrue()
        ->and($block['type'] ?? null)->toBe('heading')
        ->and(in_array('suspicious_heading_text', $block['problem_tags'] ?? [], true))->toBeTrue();
});

test('marks early document title candidates and makes them unusable as chapter heading', function () {
    $ast = [
        'blocks' => [
            [
                't' => 'Header',
                'c' => [
                    1,
                    ['', [], []],
                    [
                        ['t' => 'Str', 'c' => 'Auswirkungen digitaler Medien auf Lernmotivation im Unterricht'],
                    ],
                ],
            ],
            [
                't' => 'Header',
                'c' => [
                    1,
                    ['', [], []],
                    [
                        ['t' => 'Str', 'c' => 'Inhaltsverzeichnis'],
                    ],
                ],
            ],
        ],
    ];

    $result = app(AbaPandocAstNormalizerService::class)->normalizeAst($ast);
    $first = $result['blocks'][0] ?? [];

    expect($result['ok'] ?? false)->toBeTrue()
        ->and(in_array('document_title_candidate', $first['problem_tags'] ?? [], true))->toBeTrue()
        ->and($first['structure_role'] ?? null)->toBe('title_page_heading')
        ->and($first['is_usable_heading'] ?? true)->toBeFalse();
});

test('distinguishes toc entry from later real heading with same title', function () {
    $ast = [
        'blocks' => [
            [
                't' => 'Header',
                'c' => [1, ['', [], []], [['t' => 'Str', 'c' => 'Inhaltsverzeichnis']]],
            ],
            [
                't' => 'Header',
                'c' => [1, ['', [], []], [['t' => 'Str', 'c' => '1. Einleitung 5']]],
            ],
            [
                't' => 'Header',
                'c' => [1, ['', [], []], [['t' => 'Str', 'c' => '1. Einleitung']]],
            ],
        ],
    ];

    $result = app(AbaPandocAstNormalizerService::class)->normalizeAst($ast);
    $tocHeading = $result['blocks'][1] ?? [];
    $contentHeading = $result['blocks'][2] ?? [];

    expect($result['ok'] ?? false)->toBeTrue()
        ->and(in_array('probable_toc_artifact', $tocHeading['problem_tags'] ?? [], true))->toBeTrue()
        ->and(in_array('toc_duplicate_of_content_heading', $tocHeading['problem_tags'] ?? [], true))->toBeTrue()
        ->and(in_array('content_heading_repeated_after_toc', $contentHeading['classification']['signals'] ?? [], true))->toBeTrue()
        ->and($contentHeading['is_usable_heading'] ?? false)->toBeTrue();
});

test('adds bibliography grouping and subtype for internet sources', function () {
    $ast = [
        'blocks' => [
            [
                't' => 'Header',
                'c' => [1, ['', [], []], [['t' => 'Str', 'c' => 'Internetquellenverzeichnis']]],
            ],
        ],
    ];

    $result = app(AbaPandocAstNormalizerService::class)->normalizeAst($ast);
    $block = $result['blocks'][0] ?? [];
    $sectionHint = is_array($block['section_hint'] ?? null) ? $block['section_hint'] : [];

    expect($result['ok'] ?? false)->toBeTrue()
        ->and($sectionHint['section_type'] ?? null)->toBe('bibliography')
        ->and($sectionHint['group'] ?? null)->toBe('bibliography_area')
        ->and($sectionHint['subtype'] ?? null)->toBe('internet_sources');
});

test('returns invalid_ast error when pandoc blocks are missing', function () {
    $result = app(AbaPandocAstNormalizerService::class)->normalizeAst([
        'meta' => [],
    ]);

    expect($result['ok'] ?? true)->toBeFalse()
        ->and($result['error']['type'] ?? null)->toBe('invalid_ast');
});
