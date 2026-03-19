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

test('detects numbered short paragraph as heading candidate', function () {
    $ast = [
        'blocks' => [
            [
                't' => 'Para',
                'c' => [
                    ['t' => 'Str', 'c' => '3.2. Personalisierung und Emotionalisierung von Politik'],
                ],
            ],
        ],
    ];

    $result = app(AbaPandocAstNormalizerService::class)->normalizeAst($ast);
    $block = $result['blocks'][0] ?? [];

    expect($result['ok'] ?? false)->toBeTrue()
        ->and($block['type'] ?? null)->toBe('heading')
        ->and($block['is_usable_heading'] ?? false)->toBeTrue()
        ->and(in_array('numbered_heading_paragraph', $block['classification']['signals'] ?? [], true))->toBeTrue();
});

test('keeps plain year paragraph as paragraph and not heading', function () {
    $ast = [
        'blocks' => [
            [
                't' => 'Para',
                'c' => [
                    ['t' => 'Str', 'c' => '2024 war ein intensives politisches Jahr mit zahlreichen Ereignissen.'],
                ],
            ],
        ],
    ];

    $result = app(AbaPandocAstNormalizerService::class)->normalizeAst($ast);
    $block = $result['blocks'][0] ?? [];

    expect($result['ok'] ?? false)->toBeTrue()
        ->and($block['type'] ?? null)->toBe('paragraph')
        ->and(($block['is_usable_heading'] ?? null) === null)->toBeTrue();
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

test('treats placeholder heading text as empty and unusable', function () {
    $ast = [
        'blocks' => [
            [
                't' => 'Header',
                'c' => [
                    1,
                    ['', [], []],
                    [
                        ['t' => 'Str', 'c' => 'Kein Textinhalt'],
                    ],
                ],
            ],
        ],
    ];

    $result = app(AbaPandocAstNormalizerService::class)->normalizeAst($ast);
    $block = $result['blocks'][0] ?? [];

    expect($result['ok'] ?? false)->toBeTrue()
        ->and(in_array('empty_heading', $block['problem_tags'] ?? [], true))->toBeTrue()
        ->and($block['is_usable_heading'] ?? true)->toBeFalse()
        ->and($block['structure_role'] ?? null)->toBe('invalid_heading');
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

test('salvages recoverable chapter tail from suspicious heading text', function () {
    $ast = [
        'blocks' => [
            [
                't' => 'Para',
                'c' => [
                    ['t' => 'Strong', 'c' => [['t' => 'Str', 'c' => 'Archäologische Gesellschaft ...3.2.3. Becken']]],
                ],
            ],
        ],
    ];

    $result = app(AbaPandocAstNormalizerService::class)->normalizeAst($ast);
    $block = $result['blocks'][0] ?? [];

    expect($result['ok'] ?? false)->toBeTrue()
        ->and($block['type'] ?? null)->toBe('heading')
        ->and($block['plain_text'] ?? null)->toBe('3.2.3. Becken')
        ->and($block['is_usable_heading'] ?? false)->toBeTrue()
        ->and(in_array('suspicious_heading_text', $block['problem_tags'] ?? [], true))->toBeTrue()
        ->and(in_array('suspicious_heading_salvaged', $block['classification']['signals'] ?? [], true))->toBeTrue();
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
    $second = $result['blocks'][1] ?? [];

    expect($result['ok'] ?? false)->toBeTrue()
        ->and(in_array('document_title_candidate', $first['problem_tags'] ?? [], true))->toBeTrue()
        ->and($first['structure_role'] ?? null)->toBe('title_page_heading')
        ->and($first['is_usable_heading'] ?? true)->toBeFalse()
        ->and($first['document_zone']['zone'] ?? null)->toBe('title_page')
        ->and($second['document_zone']['zone'] ?? null)->toBe('table_of_contents');
});

test('stabilizes title candidate confidence when title-page metadata context is nearby', function () {
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
                't' => 'Para',
                'c' => [
                    ['t' => 'Str', 'c' => 'AHS Mustergymnasium, Betreuer: Max Mustermann'],
                ],
            ],
            [
                't' => 'Header',
                'c' => [1, ['', [], []], [['t' => 'Str', 'c' => 'Inhaltsverzeichnis']]],
            ],
        ],
    ];

    $result = app(AbaPandocAstNormalizerService::class)->normalizeAst($ast);
    $titleHeading = $result['blocks'][0] ?? [];

    expect($result['ok'] ?? false)->toBeTrue()
        ->and(in_array('document_title_candidate', $titleHeading['problem_tags'] ?? [], true))->toBeTrue()
        ->and($titleHeading['classification']['confidence'] ?? null)->toBe('medium')
        ->and(in_array('title_page_metadata_context', $titleHeading['classification']['signals'] ?? [], true))->toBeTrue();
});

test('reconstructs title page zone range from early metadata before abstract', function () {
    $ast = [
        'blocks' => [
            ['t' => 'Para', 'c' => [['t' => 'Str', 'c' => 'AHS Mustergymnasium']]],
            ['t' => 'Para', 'c' => [['t' => 'Str', 'c' => 'Franz-Josef-Kai 41']]],
            ['t' => 'Para', 'c' => [['t' => 'Str', 'c' => '5020 Salzburg']]],
            ['t' => 'Para', 'c' => [['t' => 'Strong', 'c' => [['t' => 'Str', 'c' => 'Auswirkungen digitaler Medien auf Lernmotivation im Unterricht']]]]],
            ['t' => 'Para', 'c' => [['t' => 'Str', 'c' => 'Verfasst von']]],
            ['t' => 'Para', 'c' => [['t' => 'Str', 'c' => 'Max Mustermann']]],
            ['t' => 'Para', 'c' => [['t' => 'Str', 'c' => 'Betreuer: Mag. Erika Beispiel']]],
            ['t' => 'Para', 'c' => [['t' => 'Str', 'c' => 'Klasse 8A']]],
            ['t' => 'Header', 'c' => [1, ['', [], []], [['t' => 'Str', 'c' => 'Abstract']]]],
        ],
    ];

    $result = app(AbaPandocAstNormalizerService::class)->normalizeAst($ast);
    $blocks = is_array($result['blocks'] ?? null) ? $result['blocks'] : [];

    $titlePageCount = collect($blocks)
        ->filter(fn (array $block): bool => ($block['document_zone']['zone'] ?? null) === 'title_page')
        ->count();
    $titleHeading = $blocks[3] ?? [];
    $abstractHeading = $blocks[8] ?? [];

    expect($result['ok'] ?? false)->toBeTrue()
        ->and($titlePageCount)->toBeGreaterThanOrEqual(6)
        ->and(in_array('document_title_candidate', $titleHeading['problem_tags'] ?? [], true))->toBeTrue()
        ->and($titleHeading['section_hint']['section_type'] ?? null)->toBe('title_page')
        ->and($abstractHeading['document_zone']['zone'] ?? null)->toBe('front_matter');
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
        ->and($contentHeading['is_usable_heading'] ?? false)->toBeTrue()
        ->and($tocHeading['document_zone']['zone'] ?? null)->toBe('table_of_contents')
        ->and($contentHeading['document_zone']['zone'] ?? null)->toBe('main_content');
});

test('keeps dense toc heading cluster in toc zone before main content heading', function () {
    $ast = [
        'blocks' => [
            [
                't' => 'Header',
                'c' => [1, ['', [], []], [['t' => 'Str', 'c' => 'Inhaltsverzeichnis']]],
            ],
            [
                't' => 'Header',
                'c' => [1, ['', [], []], [['t' => 'Str', 'c' => '1. Einleitung']]],
            ],
            [
                't' => 'Header',
                'c' => [1, ['', [], []], [['t' => 'Str', 'c' => '2. Hauptteil']]],
            ],
            [
                't' => 'Header',
                'c' => [1, ['', [], []], [['t' => 'Str', 'c' => '1. Einleitung']]],
            ],
        ],
    ];

    $result = app(AbaPandocAstNormalizerService::class)->normalizeAst($ast);
    $tocLike = $result['blocks'][1] ?? [];
    $mainHeading = $result['blocks'][3] ?? [];

    expect($result['ok'] ?? false)->toBeTrue()
        ->and($tocLike['document_zone']['zone'] ?? null)->toBe('table_of_contents')
        ->and(in_array('probable_toc_artifact', $tocLike['problem_tags'] ?? [], true))->toBeTrue()
        ->and($mainHeading['document_zone']['zone'] ?? null)->toBe('main_content')
        ->and($mainHeading['is_usable_heading'] ?? false)->toBeTrue();
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
        ->and($sectionHint['subtype'] ?? null)->toBe('internet_sources')
        ->and($block['document_zone']['zone'] ?? null)->toBe('bibliography_area');
});

test('classifies declaration heading and trailing content into declaration and end matter zones', function () {
    $ast = [
        'blocks' => [
            [
                't' => 'Header',
                'c' => [1, ['', [], []], [['t' => 'Str', 'c' => '1. Einleitung']]],
            ],
            [
                't' => 'Para',
                'c' => [['t' => 'Str', 'c' => 'Fließtext im Hauptteil.']],
            ],
            [
                't' => 'Header',
                'c' => [1, ['', [], []], [['t' => 'Str', 'c' => 'Eigenständigkeitserklärung']]],
            ],
            [
                't' => 'Para',
                'c' => [['t' => 'Str', 'c' => 'Ich erkläre hiermit die eigenständige Erstellung.']],
            ],
            [
                't' => 'Para',
                'c' => [['t' => 'Str', 'c' => 'Anhang: zusätzliche Daten']],
            ],
        ],
    ];

    $result = app(AbaPandocAstNormalizerService::class)->normalizeAst($ast);
    $declarationHeading = $result['blocks'][2] ?? [];
    $declarationParagraph = $result['blocks'][3] ?? [];
    $tailParagraph = $result['blocks'][4] ?? [];

    expect($result['ok'] ?? false)->toBeTrue()
        ->and($declarationHeading['document_zone']['zone'] ?? null)->toBe('declaration_area')
        ->and($declarationParagraph['document_zone']['zone'] ?? null)->toBe('declaration_area')
        ->and(in_array($tailParagraph['document_zone']['zone'] ?? '', ['declaration_area', 'end_matter'], true))->toBeTrue();
});

test('falls back to front matter zone when no stable anchors exist', function () {
    $ast = [
        'blocks' => [
            [
                't' => 'Para',
                'c' => [['t' => 'Str', 'c' => 'AHS Abschlussarbeit']],
            ],
            [
                't' => 'Para',
                'c' => [['t' => 'Str', 'c' => 'Ein weiterer kurzer Absatz ohne klare Kapitelanker.']],
            ],
        ],
    ];

    $result = app(AbaPandocAstNormalizerService::class)->normalizeAst($ast);
    $first = $result['blocks'][0] ?? [];
    $second = $result['blocks'][1] ?? [];

    expect($result['ok'] ?? false)->toBeTrue()
        ->and(in_array($first['document_zone']['zone'] ?? '', ['title_page', 'front_matter'], true))->toBeTrue()
        ->and(in_array($second['document_zone']['zone'] ?? '', ['title_page', 'front_matter', 'main_content'], true))->toBeTrue();
});

test('returns invalid_ast error when pandoc blocks are missing', function () {
    $result = app(AbaPandocAstNormalizerService::class)->normalizeAst([
        'meta' => [],
    ]);

    expect($result['ok'] ?? true)->toBeFalse()
        ->and($result['error']['type'] ?? null)->toBe('invalid_ast');
});

test('collapses line breaks inside parenthetical citation references', function () {
    $ast = [
        'blocks' => [
            [
                't' => 'Para',
                'c' => [
                    ['t' => 'Str', 'c' => 'Laut Mathpal'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => '(vgl.'],
                    ['t' => 'LineBreak'],
                    ['t' => 'Str', 'c' => 'Mathpal,'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'o.'],
                    ['t' => 'LineBreak'],
                    ['t' => 'Str', 'c' => 'J.)'],
                ],
            ],
        ],
    ];

    $result = app(AbaPandocAstNormalizerService::class)->normalizeAst($ast);
    $block = $result['blocks'][0] ?? [];

    expect($result['ok'] ?? false)->toBeTrue()
        ->and($block['type'] ?? null)->toBe('paragraph')
        ->and($block['text'] ?? null)->toBe('Laut Mathpal (vgl. Mathpal, o. J.)');
});

test('detects single-word strong platform name as heuristic heading', function () {
    $ast = [
        'blocks' => [
            [
                't' => 'Para',
                'c' => [
                    ['t' => 'Strong', 'c' => [['t' => 'Str', 'c' => 'Instagram']]],
                ],
            ],
            [
                't' => 'Para',
                'c' => [
                    ['t' => 'Strong', 'c' => [['t' => 'Str', 'c' => 'Pinterest']]],
                ],
            ],
        ],
    ];

    $result = app(AbaPandocAstNormalizerService::class)->normalizeAst($ast);

    expect($result['ok'] ?? false)->toBeTrue()
        ->and($result['blocks'][0]['type'] ?? null)->toBe('heading')
        ->and($result['blocks'][0]['text'] ?? null)->toBe('Instagram')
        ->and($result['blocks'][1]['type'] ?? null)->toBe('heading')
        ->and($result['blocks'][1]['text'] ?? null)->toBe('Pinterest');
});

test('keeps strong-plus-body paragraph as paragraph not as heading', function () {
    $ast = [
        'blocks' => [
            [
                't' => 'Para',
                'c' => [
                    ['t' => 'Strong', 'c' => [['t' => 'Str', 'c' => 'Instagram']]],
                    ['t' => 'Str', 'c' => ','],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'die weltweit bekannte und meistgenutzte App der Fotografen, ist als eine der populärsten Social-Media-Plattformen für alle ein wichtiger Teil unseres Alltags.'],
                ],
            ],
        ],
    ];

    $result = app(AbaPandocAstNormalizerService::class)->normalizeAst($ast);
    $block = $result['blocks'][0] ?? [];

    expect($result['ok'] ?? false)->toBeTrue()
        ->and($block['type'] ?? null)->toBe('paragraph');
});

test('collapses line breaks after single-letter initials in paragraph text', function () {
    $ast = [
        'blocks' => [
            [
                't' => 'Para',
                'c' => [
                    ['t' => 'Str', 'c' => 'Die Autorin A.'],
                    ['t' => 'LineBreak'],
                    ['t' => 'Str', 'c' => 'Eldridge erläutert die Funktionen.'],
                ],
            ],
        ],
    ];

    $result = app(AbaPandocAstNormalizerService::class)->normalizeAst($ast);
    $block = $result['blocks'][0] ?? [];

    expect($result['ok'] ?? false)->toBeTrue()
        ->and($block['type'] ?? null)->toBe('paragraph')
        ->and($block['text'] ?? null)->toBe('Die Autorin A. Eldridge erläutert die Funktionen.');
});

test('preserves intentional line breaks within paragraph text', function () {
    $ast = [
        'blocks' => [
            [
                't' => 'Para',
                'c' => [
                    ['t' => 'Str', 'c' => 'Erste Zeile des Absatzes'],
                    ['t' => 'LineBreak'],
                    ['t' => 'Str', 'c' => 'Zweite Zeile des Absatzes'],
                ],
            ],
        ],
    ];

    $result = app(AbaPandocAstNormalizerService::class)->normalizeAst($ast);
    $block = $result['blocks'][0] ?? [];

    expect($result['ok'] ?? false)->toBeTrue()
        ->and($block['type'] ?? null)->toBe('paragraph')
        ->and(str_contains($block['text'] ?? '', "\n"))->toBeTrue();
});
