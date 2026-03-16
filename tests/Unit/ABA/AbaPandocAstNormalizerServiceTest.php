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

test('returns invalid_ast error when pandoc blocks are missing', function () {
    $result = app(AbaPandocAstNormalizerService::class)->normalizeAst([
        'meta' => [],
    ]);

    expect($result['ok'] ?? true)->toBeFalse()
        ->and($result['error']['type'] ?? null)->toBe('invalid_ast');
});
