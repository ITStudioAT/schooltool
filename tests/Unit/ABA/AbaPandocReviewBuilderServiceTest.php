<?php

use App\Services\AbaPandocReviewBuilderService;

uses(Tests\TestCase::class);

test('builds pandoc outline with separated frontmatter main content and endmatter', function () {
    $service = app(AbaPandocReviewBuilderService::class);

    $blocks = [
        [
            'type' => 'heading',
            'order' => 1,
            'plain_text' => 'Die Rolle der Medien in der politischen Meinungsbildung',
            'heading_level' => 1,
            'is_usable_heading' => false,
            'problem_tags' => ['document_title_candidate'],
            'classification' => ['confidence' => 'medium', 'strategy' => 'heuristic', 'signals' => ['document_title_signal']],
            'section_hint' => ['section_type' => 'title_page', 'reason' => 'document_title_signal'],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'medium'],
        ],
        [
            'type' => 'paragraph',
            'order' => 2,
            'plain_text' => 'Verfasst von',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 3,
            'plain_text' => 'Yvonne Pucher',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 4,
            'plain_text' => 'Betreuer: Dipl.-Ing. Günther Kron',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 5,
            'plain_text' => 'Klasse 8M',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'high'],
        ],
        [
            'type' => 'heading',
            'order' => 6,
            'plain_text' => 'Abstract',
            'heading_level' => 1,
            'is_usable_heading' => true,
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['section_keyword']],
            'section_hint' => ['section_type' => 'abstract', 'reason' => 'section_keyword'],
            'document_zone' => ['zone' => 'front_matter', 'label' => 'Frontmatter', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 7,
            'plain_text' => 'Abstract-Inhalt mit aussagekräftigem Abschnittstext.',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'front_matter', 'label' => 'Frontmatter', 'confidence' => 'high'],
        ],
        [
            'type' => 'heading',
            'order' => 8,
            'plain_text' => '1. Einleitung',
            'heading_level' => 1,
            'is_usable_heading' => true,
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['section_keyword']],
            'section_hint' => ['section_type' => 'chapter', 'reason' => 'section_keyword'],
            'document_zone' => ['zone' => 'main_content', 'label' => 'Hauptteil', 'confidence' => 'high'],
        ],
        [
            'type' => 'heading',
            'order' => 9,
            'plain_text' => '1.1 Historischer Kontext',
            'heading_level' => 2,
            'is_usable_heading' => true,
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['numbered_heading']],
            'section_hint' => ['section_type' => 'chapter', 'reason' => 'numbered_heading'],
            'document_zone' => ['zone' => 'main_content', 'label' => 'Hauptteil', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 10,
            'plain_text' => 'Kapitelinhalt für den historischen Kontext.',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'main_content', 'label' => 'Hauptteil', 'confidence' => 'high'],
        ],
        [
            'type' => 'image',
            'order' => 11,
            'plain_text' => 'Abbildung 1',
            'text' => 'Abbildung 1',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['image_only_paragraph']],
            'image' => ['target' => 'media/image1.png', 'title' => '', 'alt_text' => 'Abbildung 1'],
            'document_zone' => ['zone' => 'main_content', 'label' => 'Hauptteil', 'confidence' => 'high'],
        ],
        [
            'type' => 'heading',
            'order' => 12,
            'plain_text' => 'Literaturverzeichnis',
            'heading_level' => 1,
            'is_usable_heading' => true,
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['section_keyword']],
            'section_hint' => ['section_type' => 'bibliography', 'reason' => 'section_keyword'],
            'document_zone' => ['zone' => 'bibliography_area', 'label' => 'Verzeichnisse / Bibliographie', 'confidence' => 'high'],
        ],
        [
            'type' => 'heading',
            'order' => 13,
            'plain_text' => 'Abbildungsverzeichnis',
            'heading_level' => 1,
            'is_usable_heading' => true,
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['section_keyword']],
            'section_hint' => ['section_type' => 'figure_index', 'reason' => 'section_keyword'],
            'document_zone' => ['zone' => 'bibliography_area', 'label' => 'Verzeichnisse / Bibliographie', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 14,
            'plain_text' => 'Abbildung 1. Eine Beispielabbildung.',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'bibliography_area', 'label' => 'Verzeichnisse / Bibliographie', 'confidence' => 'high'],
        ],
        [
            'type' => 'heading',
            'order' => 15,
            'plain_text' => '1. Einleitung 5',
            'heading_level' => 1,
            'is_usable_heading' => false,
            'problem_tags' => ['probable_toc_artifact'],
            'classification' => ['confidence' => 'low', 'strategy' => 'heuristic', 'signals' => ['toc_pattern']],
            'section_hint' => ['section_type' => 'table_of_contents', 'reason' => 'toc_pattern'],
            'document_zone' => ['zone' => 'table_of_contents', 'label' => 'Inhaltsverzeichnis', 'confidence' => 'medium'],
        ],
    ];

    $review = $service->buildReview($blocks);

    $outline = is_array($review['outline'] ?? null) ? $review['outline'] : [];
    $frontmatter = is_array($outline['frontmatter_sections'] ?? null) ? $outline['frontmatter_sections'] : [];
    $main = is_array($outline['main_content_outline'] ?? null) ? $outline['main_content_outline'] : [];
    $endmatter = is_array($outline['endmatter_sections'] ?? null) ? $outline['endmatter_sections'] : [];
    $excluded = is_array($outline['excluded_headings'] ?? null) ? $outline['excluded_headings'] : [];
    $specialSections = is_array($review['special_sections'] ?? null) ? $review['special_sections'] : [];
    $figureIndexEntries = is_array($review['figure_index_entries'] ?? null) ? $review['figure_index_entries'] : [];
    $titlePageDetails = is_array($review['title_page_details'] ?? null) ? $review['title_page_details'] : [];

    expect($frontmatter)->toHaveCount(2)
        ->and($frontmatter[0]['text'] ?? null)->toContain('Die Rolle der Medien')
        ->and($frontmatter[1]['text'] ?? null)->toBe('Abstract')
        ->and($main)->toHaveCount(1)
        ->and($main[0]['text'] ?? null)->toBe('1. Einleitung')
        ->and(is_array($main[0]['children'] ?? null))->toBeTrue()
        ->and($main[0]['content_scope'] ?? null)->toBe('own_content_only')
        ->and($main[0]['children'][0]['text'] ?? null)->toBe('1.1 Historischer Kontext')
        ->and($main[0]['children'][0]['content_scope'] ?? null)->toBe('own_content_only')
        ->and($main[0]['children'][0]['children'][0]['text'] ?? null)->toBe('Abbildung 1')
        ->and($main[0]['children'][0]['children'][0]['section_type'] ?? null)->toBe('figure')
        ->and($endmatter)->toHaveCount(2)
        ->and($endmatter[0]['text'] ?? null)->toBe('Literaturverzeichnis')
        ->and($endmatter[1]['text'] ?? null)->toBe('Abbildungsverzeichnis')
        ->and($excluded)->toHaveCount(1)
        ->and($excluded[0]['text'] ?? null)->toBe('1. Einleitung 5')
        ->and($outline['main_content_orphan_figures'] ?? [])->toBeArray()->toHaveCount(0)
        ->and($specialSections)->toHaveCount(4)
        ->and($specialSections[0]['special_area_key'] ?? null)->toBe('titlepage')
        ->and($specialSections[0]['text'] ?? null)->toBe('Titelseite')
        ->and($specialSections[0]['compare_key'] ?? null)->toBe('titelseite')
        ->and($specialSections[0]['detail_lines'] ?? [])->toContain('Titel: Die Rolle der Medien in der politischen Meinungsbildung')
        ->and($specialSections[0]['detail_lines'] ?? [])->toContain('Verfasst von: Yvonne Pucher')
        ->and($specialSections[0]['detail_lines'] ?? [])->toContain('Datum: --')
        ->and($specialSections[0]['content_text'] ?? null)->toContain('Die Rolle der Medien in der politischen Meinungsbildung')
        ->and($specialSections[1]['special_area_key'] ?? null)->toBe('abstract')
        ->and($specialSections[1]['content_text'] ?? null)->toContain('Abstract-Inhalt mit aussagekräftigem Abschnittstext.')
        ->and($specialSections[2]['special_area_key'] ?? null)->toBe('bibliography')
        ->and($specialSections[3]['special_area_key'] ?? null)->toBe('figure_index')
        ->and($main[0]['children'][0]['content_text'] ?? null)->toContain('Kapitelinhalt für den historischen Kontext.')
        ->and($review['recognized_main_sections'][0]['text'] ?? null)->toBe('1. Einleitung')
        ->and($review['recognized_main_sections'])->not->toContain(fn (array $item): bool => ($item['section_type'] ?? null) === 'figure')
        ->and($review['recognized_main_sections'])->not->toContain(fn (array $item): bool => ($item['text'] ?? null) === 'Abstract')
        ->and($figureIndexEntries)->toHaveCount(1)
        ->and($figureIndexEntries[0]['text'] ?? null)->toBe('Abbildung 1')
        ->and($figureIndexEntries[0]['content_text'] ?? null)->toContain('Eine Beispielabbildung.')
        ->and($titlePageDetails['title'] ?? null)->toBe('Die Rolle der Medien in der politischen Meinungsbildung')
        ->and($titlePageDetails['submitter'] ?? null)->toBe('Yvonne Pucher')
        ->and($titlePageDetails['advisor'] ?? null)->toBe('Dipl.-Ing. Günther Kron')
        ->and($titlePageDetails['class'] ?? null)->toBe('8M');
});

test('extracts titlepage school address and date metadata for pandoc projection', function () {
    $service = app(AbaPandocReviewBuilderService::class);

    $blocks = [
        [
            'type' => 'heading',
            'order' => 1,
            'plain_text' => 'Die Rolle der Medien in der politischen Meinungsbildung',
            'heading_level' => 1,
            'is_usable_heading' => false,
            'problem_tags' => ['document_title_candidate'],
            'classification' => ['confidence' => 'medium', 'strategy' => 'heuristic', 'signals' => ['document_title_signal']],
            'section_hint' => ['section_type' => 'title_page', 'reason' => 'document_title_signal'],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'medium'],
        ],
        [
            'type' => 'paragraph',
            'order' => 2,
            'plain_text' => 'Christian Doppler-Gymnasium',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 3,
            'plain_text' => 'Franz-Josef-Kai 41',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 4,
            'plain_text' => '5020 Salzburg',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 5,
            'plain_text' => 'Verfasst von',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 6,
            'plain_text' => 'Yvonne Pucher',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 7,
            'plain_text' => 'Betreuer: Dipl.-Ing. Günther Kron',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 8,
            'plain_text' => 'Klasse 8M',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 9,
            'plain_text' => 'Ort, Datum',
            'problem_tags' => [],
            'classification' => ['confidence' => 'medium', 'strategy' => 'heuristic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 10,
            'plain_text' => '17.04.2025',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'high'],
        ],
        [
            'type' => 'heading',
            'order' => 11,
            'plain_text' => 'Abstract',
            'heading_level' => 1,
            'is_usable_heading' => true,
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['section_keyword']],
            'section_hint' => ['section_type' => 'abstract', 'reason' => 'section_keyword'],
            'document_zone' => ['zone' => 'front_matter', 'label' => 'Frontmatter', 'confidence' => 'high'],
        ],
    ];

    $review = $service->buildReview($blocks);
    $titlePageDetails = is_array($review['title_page_details'] ?? null) ? $review['title_page_details'] : [];
    $specialSections = is_array($review['special_sections'] ?? null) ? $review['special_sections'] : [];
    $titleSection = collect($specialSections)->first(fn (array $item): bool => ($item['special_area_key'] ?? null) === 'titlepage');

    expect($titlePageDetails['school'] ?? null)->toBe('Christian Doppler-Gymnasium')
        ->and($titlePageDetails['school_address'] ?? null)->toBe('Franz-Josef-Kai 41')
        ->and($titlePageDetails['school_city'] ?? null)->toBe('5020 Salzburg')
        ->and($titlePageDetails['school_full'] ?? null)->toBe('Christian Doppler-Gymnasium, Franz-Josef-Kai 41, 5020 Salzburg')
        ->and($titlePageDetails['date'] ?? null)->toBe('17.04.2025')
        ->and($titleSection)->not->toBeNull()
        ->and($titleSection['detail_lines'] ?? [])->toContain('Schule: Christian Doppler-Gymnasium, Franz-Josef-Kai 41, 5020 Salzburg')
        ->and($titleSection['detail_lines'] ?? [])->toContain('Datum: 17.04.2025');
});

test('builds a single titlepage section from fragmented titlepage candidates and detects month year date', function () {
    $service = app(AbaPandocReviewBuilderService::class);

    $blocks = [
        [
            'type' => 'heading',
            'order' => 1,
            'plain_text' => 'WER IST BONG JOON-HO? | DOKU',
            'heading_level' => 1,
            'is_usable_heading' => false,
            'problem_tags' => ['document_title_candidate'],
            'classification' => ['confidence' => 'medium', 'strategy' => 'heuristic', 'signals' => ['document_title_signal']],
            'section_hint' => ['section_type' => 'title_page', 'reason' => 'document_title_signal'],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'medium'],
        ],
        [
            'type' => 'paragraph',
            'order' => 2,
            'plain_text' => 'Eine Dokumentation über den einzigen südkoreanischen Regisseur mit einem Oscar',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 3,
            'plain_text' => 'Abschließende Arbeit',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 4,
            'plain_text' => 'verfasst von',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 5,
            'plain_text' => 'Yvonne Pucher',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 6,
            'plain_text' => 'Klasse 8M',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 7,
            'plain_text' => 'betreut von',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 8,
            'plain_text' => 'Dipl.-Ing. Günther Kron',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 9,
            'plain_text' => 'Christian-Doppler-Gymnasium',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 10,
            'plain_text' => 'Franz Josef-Kai 41, 5020 Salzburg',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 11,
            'plain_text' => 'Februar 2026',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'high'],
        ],
        [
            'type' => 'heading',
            'order' => 12,
            'plain_text' => 'Ein Bild, das Schrift, Grafiken, Text, Kreis enthält. Automatisch generierte Beschreibung',
            'heading_level' => 2,
            'is_usable_heading' => false,
            'problem_tags' => ['document_title_candidate'],
            'classification' => ['confidence' => 'low', 'strategy' => 'heuristic', 'signals' => ['document_title_signal']],
            'section_hint' => ['section_type' => 'title_page', 'reason' => 'document_title_signal'],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'low'],
        ],
        [
            'type' => 'heading',
            'order' => 13,
            'plain_text' => 'Abstract',
            'heading_level' => 1,
            'is_usable_heading' => true,
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['section_keyword']],
            'section_hint' => ['section_type' => 'abstract', 'reason' => 'section_keyword'],
            'document_zone' => ['zone' => 'front_matter', 'label' => 'Frontmatter', 'confidence' => 'high'],
        ],
    ];

    $review = $service->buildReview($blocks);
    $specialSections = is_array($review['special_sections'] ?? null) ? $review['special_sections'] : [];
    $titlePageDetails = is_array($review['title_page_details'] ?? null) ? $review['title_page_details'] : [];
    $titlePageSections = array_values(array_filter($specialSections, fn (array $item): bool => ($item['special_area_key'] ?? null) === 'titlepage'));
    $titleSection = $titlePageSections[0] ?? null;

    expect($titlePageSections)->toHaveCount(1)
        ->and($titlePageDetails['title'] ?? null)->toContain('Eine Dokumentation über den einzigen südkoreanischen Regisseur')
        ->and($titlePageDetails['document_type'] ?? null)->toBe('DOKU')
        ->and($titlePageDetails['advisor'] ?? null)->toBe('Dipl.-Ing. Günther Kron')
        ->and($titlePageDetails['school_full'] ?? null)->toBe('Christian-Doppler-Gymnasium, Franz Josef-Kai 41, 5020 Salzburg')
        ->and($titlePageDetails['date'] ?? null)->toBe('Februar 2026')
        ->and($titleSection)->toBeArray()
        ->and($titleSection['detail_lines'] ?? [])->toContain('Datum: Februar 2026')
        ->and($titleSection['detail_lines'] ?? [])->toContain('Betreuer: Dipl.-Ing. Günther Kron');
});

test('normalizes numbering and compare keys for tightly glued chapter headings', function () {
    $service = app(AbaPandocReviewBuilderService::class);

    $blocks = [
        [
            'type' => 'heading',
            'order' => 1,
            'plain_text' => '3.3.1.Wahlumfragen',
            'heading_level' => 3,
            'is_usable_heading' => true,
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_header_block']],
            'section_hint' => ['section_type' => 'chapter', 'reason' => 'numbered_heading'],
            'document_zone' => ['zone' => 'main_content', 'label' => 'Hauptteil', 'confidence' => 'high'],
        ],
        [
            'type' => 'heading',
            'order' => 2,
            'plain_text' => '4.1.Begriffsbestimmung',
            'heading_level' => 2,
            'is_usable_heading' => true,
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_header_block']],
            'section_hint' => ['section_type' => 'chapter', 'reason' => 'numbered_heading'],
            'document_zone' => ['zone' => 'main_content', 'label' => 'Hauptteil', 'confidence' => 'high'],
        ],
    ];

    $review = $service->buildReview($blocks);
    $mainLinear = is_array($review['outline']['main_content_linear'] ?? null) ? $review['outline']['main_content_linear'] : [];

    expect($mainLinear)->toHaveCount(2)
        ->and($mainLinear[0]['numbering'] ?? null)->toBe('3.3.1')
        ->and($mainLinear[0]['compare_key'] ?? null)->toBe('wahlumfragen')
        ->and($mainLinear[1]['numbering'] ?? null)->toBe('4.1')
        ->and($mainLinear[1]['compare_key'] ?? null)->toBe('begriffsbestimmung');
});

test('builds structured toc content projection from detected main outline', function () {
    $service = app(AbaPandocReviewBuilderService::class);

    $blocks = [
        [
            'type' => 'heading',
            'order' => 1,
            'plain_text' => 'Inhaltsverzeichnis',
            'heading_level' => 1,
            'is_usable_heading' => true,
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['section_keyword']],
            'section_hint' => ['section_type' => 'table_of_contents', 'reason' => 'section_keyword'],
            'document_zone' => ['zone' => 'table_of_contents', 'label' => 'Inhaltsverzeichnis', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 2,
            'plain_text' => '1 Kapitel Eins 1 Unterpunkt A',
            'problem_tags' => [],
            'classification' => ['confidence' => 'medium', 'strategy' => 'heuristic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'table_of_contents', 'label' => 'Inhaltsverzeichnis', 'confidence' => 'medium'],
        ],
        [
            'type' => 'heading',
            'order' => 3,
            'plain_text' => '1. Kapitel Eins',
            'heading_level' => 1,
            'is_usable_heading' => true,
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['numbered_heading']],
            'section_hint' => ['section_type' => 'chapter', 'reason' => 'numbered_heading'],
            'document_zone' => ['zone' => 'main_content', 'label' => 'Hauptteil', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 4,
            'plain_text' => 'Kapiteltext',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'main_content', 'label' => 'Hauptteil', 'confidence' => 'high'],
        ],
        [
            'type' => 'heading',
            'order' => 5,
            'plain_text' => '1.1 Unterpunkt A',
            'heading_level' => 2,
            'is_usable_heading' => true,
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['numbered_heading']],
            'section_hint' => ['section_type' => 'chapter', 'reason' => 'numbered_heading'],
            'document_zone' => ['zone' => 'main_content', 'label' => 'Hauptteil', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 6,
            'plain_text' => 'Unterkapiteltext',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'main_content', 'label' => 'Hauptteil', 'confidence' => 'high'],
        ],
        [
            'type' => 'heading',
            'order' => 7,
            'plain_text' => 'Fazit',
            'heading_level' => 1,
            'is_usable_heading' => true,
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['section_keyword']],
            'section_hint' => ['section_type' => 'chapter', 'reason' => 'section_keyword'],
            'document_zone' => ['zone' => 'main_content', 'label' => 'Hauptteil', 'confidence' => 'high'],
        ],
    ];

    $review = $service->buildReview($blocks);
    $specialSections = is_array($review['special_sections'] ?? null) ? $review['special_sections'] : [];
    $toc = collect($specialSections)->first(fn (array $item): bool => ($item['special_area_key'] ?? null) === 'toc');

    expect($toc)->toBeArray()
        ->and($toc['content_text'] ?? null)->toContain('1 Kapitel Eins')
        ->and($toc['content_text'] ?? null)->toContain('- 1.1 Unterpunkt A')
        ->and($toc['content_text'] ?? null)->toContain('Fazit')
        ->and($toc['toc_primary_kind'] ?? null)->toBe('outline')
        ->and($toc['toc_outline_lines'] ?? [])->toContain('1 Kapitel Eins')
        ->and($toc['content_text'] ?? null)->not->toContain('1 Kapitel Eins 1 Unterpunkt A');
});

test('segments merged toc fallback text into readable toc lines', function () {
    $service = app(AbaPandocReviewBuilderService::class);

    $blocks = [
        [
            'type' => 'heading',
            'order' => 1,
            'plain_text' => 'Inhaltsverzeichnis',
            'heading_level' => 1,
            'is_usable_heading' => true,
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['section_keyword']],
            'section_hint' => ['section_type' => 'table_of_contents', 'reason' => 'section_keyword'],
            'document_zone' => ['zone' => 'table_of_contents', 'label' => 'Inhaltsverzeichnis', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 2,
            'plain_text' => '1 Printmedien 5 1.1 Rundfunk 8 2 Kontrolle und Macht 12 Fazit 24',
            'problem_tags' => [],
            'classification' => ['confidence' => 'medium', 'strategy' => 'heuristic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'table_of_contents', 'label' => 'Inhaltsverzeichnis', 'confidence' => 'medium'],
        ],
    ];

    $review = $service->buildReview($blocks);
    $specialSections = is_array($review['special_sections'] ?? null) ? $review['special_sections'] : [];
    $toc = collect($specialSections)->first(fn (array $item): bool => ($item['special_area_key'] ?? null) === 'toc');

    expect($toc)->toBeArray()
        ->and($toc['content_text'] ?? null)->toContain("1 Printmedien 5\n1.1 Rundfunk 8")
        ->and($toc['content_text'] ?? null)->toContain('2 Kontrolle und Macht 12')
        ->and($toc['content_text'] ?? null)->toContain('Fazit 24')
        ->and($toc['toc_primary_kind'] ?? null)->toBe('page_index')
        ->and($toc['toc_page_index_lines'] ?? [])->toContain('1.1 Rundfunk 8')
        ->and($toc['content_text'] ?? null)->not->toContain('1 Printmedien 5 1.1 Rundfunk 8 2 Kontrolle und Macht 12 Fazit 24');
});

test('keeps toc projection preview complete for larger chapter trees', function () {
    $service = app(AbaPandocReviewBuilderService::class);

    $blocks = [
        [
            'type' => 'heading',
            'order' => 1,
            'plain_text' => 'Inhaltsverzeichnis',
            'heading_level' => 1,
            'is_usable_heading' => true,
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['section_keyword']],
            'section_hint' => ['section_type' => 'table_of_contents', 'reason' => 'section_keyword'],
            'document_zone' => ['zone' => 'table_of_contents', 'label' => 'Inhaltsverzeichnis', 'confidence' => 'high'],
        ],
    ];

    $order = 2;
    for ($chapter = 1; $chapter <= 12; $chapter++) {
        $blocks[] = [
            'type' => 'heading',
            'order' => $order++,
            'plain_text' => $chapter.'. Kapitel Thema '.$chapter.'A',
            'heading_level' => 1,
            'is_usable_heading' => true,
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['numbered_heading']],
            'section_hint' => ['section_type' => 'chapter', 'reason' => 'numbered_heading'],
            'document_zone' => ['zone' => 'main_content', 'label' => 'Hauptteil', 'confidence' => 'high'],
        ];
        $blocks[] = [
            'type' => 'paragraph',
            'order' => $order++,
            'plain_text' => 'Einleitender Abschnitt zu Kapitel '.$chapter.'.',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'main_content', 'label' => 'Hauptteil', 'confidence' => 'high'],
        ];

        for ($sub = 1; $sub <= 2; $sub++) {
            $blocks[] = [
                'type' => 'heading',
                'order' => $order++,
                'plain_text' => $chapter.'.'.$sub.' Unterkapitel Thema '.$chapter.$sub.'B',
                'heading_level' => 2,
                'is_usable_heading' => true,
                'problem_tags' => [],
                'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['numbered_heading']],
                'section_hint' => ['section_type' => 'chapter', 'reason' => 'numbered_heading'],
                'document_zone' => ['zone' => 'main_content', 'label' => 'Hauptteil', 'confidence' => 'high'],
            ];
        }
    }

    $review = $service->buildReview($blocks);
    $specialSections = is_array($review['special_sections'] ?? null) ? $review['special_sections'] : [];
    $toc = collect($specialSections)->first(fn (array $item): bool => ($item['special_area_key'] ?? null) === 'toc');
    $previewLines = is_array($toc['content_preview_lines'] ?? null) ? $toc['content_preview_lines'] : [];
    $outlineLines = is_array($toc['toc_outline_lines'] ?? null) ? $toc['toc_outline_lines'] : [];
    $previewText = implode("\n", $previewLines);

    expect($toc)->toBeArray()
        ->and($toc['toc_primary_kind'] ?? null)->toBe('outline')
        ->and(count($outlineLines))->toBeGreaterThan(20)
        ->and(count($previewLines))->toBeGreaterThan(20)
        ->and($previewText)->toContain('12 Kapitel Thema 12A')
        ->and($previewText)->toContain('12.2 Unterkapitel Thema 122B');
});

test('segments chapter projection content into list and sentence lines', function () {
    $service = app(AbaPandocReviewBuilderService::class);

    $blocks = [
        [
            'type' => 'heading',
            'order' => 1,
            'plain_text' => '1. Einleitung',
            'heading_level' => 1,
            'is_usable_heading' => true,
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['numbered_heading']],
            'section_hint' => ['section_type' => 'chapter', 'reason' => 'numbered_heading'],
            'document_zone' => ['zone' => 'main_content', 'label' => 'Hauptteil', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 2,
            'plain_text' => '1 Kontrolle und Macht 2 Aufdeckung durch Medien 3 Ziele und Motive',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'main_content', 'label' => 'Hauptteil', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 3,
            'plain_text' => 'Dieser Abschnitt beschreibt die Ausgangslage sehr ausführlich und verbindet historische Entwicklung, aktuelle Nutzung und gesellschaftliche Wirkung in einem durchgängigen Fließtext. Danach wird die Rolle von Medienkompetenz in der Schule mit mehreren Perspektiven dargestellt.',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'main_content', 'label' => 'Hauptteil', 'confidence' => 'high'],
        ],
        [
            'type' => 'heading',
            'order' => 4,
            'plain_text' => '2. Hauptteil',
            'heading_level' => 1,
            'is_usable_heading' => true,
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['numbered_heading']],
            'section_hint' => ['section_type' => 'chapter', 'reason' => 'numbered_heading'],
            'document_zone' => ['zone' => 'main_content', 'label' => 'Hauptteil', 'confidence' => 'high'],
        ],
    ];

    $review = $service->buildReview($blocks);
    $outline = is_array($review['outline'] ?? null) ? $review['outline'] : [];
    $main = is_array($outline['main_content_outline'] ?? null) ? $outline['main_content_outline'] : [];
    $firstChapter = is_array($main[0] ?? null) ? $main[0] : [];

    expect($main)->toHaveCount(2)
        ->and($firstChapter['content_text'] ?? null)->toContain("1 Kontrolle und Macht\n2 Aufdeckung durch Medien\n3 Ziele und Motive")
        ->and($firstChapter['content_text'] ?? null)->toContain("Fließtext.\nDanach wird die Rolle von Medienkompetenz")
        ->and(is_array($firstChapter['content_preview_lines'] ?? null))->toBeTrue()
        ->and(count($firstChapter['content_preview_lines'] ?? []))->toBeGreaterThan(3);
});

test('excludes heuristic loose term headings from main content sections', function () {
    $service = app(AbaPandocReviewBuilderService::class);

    $blocks = [
        [
            'type' => 'heading',
            'order' => 1,
            'plain_text' => 'Einleitung',
            'heading_level' => 1,
            'is_usable_heading' => true,
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['section_keyword']],
            'section_hint' => ['section_type' => 'chapter', 'reason' => 'section_keyword'],
            'document_zone' => ['zone' => 'main_content', 'label' => 'Hauptteil', 'confidence' => 'high'],
        ],
        [
            'type' => 'heading',
            'order' => 2,
            'plain_text' => 'Instagram',
            'is_usable_heading' => true,
            'problem_tags' => [],
            'classification' => ['confidence' => 'medium', 'strategy' => 'heuristic', 'signals' => ['heading_like_paragraph']],
            'section_hint' => [],
            'document_zone' => ['zone' => 'main_content', 'label' => 'Hauptteil', 'confidence' => 'medium'],
        ],
    ];

    $review = $service->buildReview($blocks);
    $mainLinear = is_array($review['outline']['main_content_linear'] ?? null) ? $review['outline']['main_content_linear'] : [];
    $recognizedMain = is_array($review['recognized_main_sections'] ?? null) ? $review['recognized_main_sections'] : [];

    expect($mainLinear)->toHaveCount(1)
        ->and($mainLinear[0]['text'] ?? null)->toBe('Einleitung')
        ->and(collect($recognizedMain)->pluck('text')->all())->toBe(['Einleitung']);
});

test('does not treat titlepage metadata headings as extra title candidates', function () {
    $service = app(AbaPandocReviewBuilderService::class);

    $blocks = [
        [
            'type' => 'heading',
            'order' => 1,
            'plain_text' => 'Die Rolle der Fotografie in sozialen Medien',
            'heading_level' => 1,
            'is_usable_heading' => false,
            'problem_tags' => ['document_title_candidate'],
            'classification' => ['confidence' => 'medium', 'strategy' => 'heuristic', 'signals' => ['document_title_signal']],
            'section_hint' => ['section_type' => 'title_page', 'reason' => 'document_title_signal'],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'medium'],
        ],
        [
            'type' => 'heading',
            'order' => 2,
            'plain_text' => 'Verfasser*in: Sandra Banu',
            'heading_level' => 2,
            'is_usable_heading' => false,
            'problem_tags' => ['document_title_candidate'],
            'classification' => ['confidence' => 'low', 'strategy' => 'heuristic', 'signals' => ['document_title_signal']],
            'section_hint' => ['section_type' => 'title_page', 'reason' => 'document_title_signal'],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'medium'],
        ],
        [
            'type' => 'heading',
            'order' => 3,
            'plain_text' => 'Klasse: 8M',
            'heading_level' => 2,
            'is_usable_heading' => false,
            'problem_tags' => ['document_title_candidate'],
            'classification' => ['confidence' => 'low', 'strategy' => 'heuristic', 'signals' => ['document_title_signal']],
            'section_hint' => ['section_type' => 'title_page', 'reason' => 'document_title_signal'],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'medium'],
        ],
        [
            'type' => 'heading',
            'order' => 4,
            'plain_text' => 'Schuljahr: 2025/26',
            'heading_level' => 2,
            'is_usable_heading' => false,
            'problem_tags' => ['document_title_candidate'],
            'classification' => ['confidence' => 'low', 'strategy' => 'heuristic', 'signals' => ['document_title_signal']],
            'section_hint' => ['section_type' => 'title_page', 'reason' => 'document_title_signal'],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'medium'],
        ],
    ];

    $review = $service->buildReview($blocks);
    $titleCandidates = is_array($review['document_title_candidates'] ?? null) ? $review['document_title_candidates'] : [];
    $uncertain = is_array($review['uncertain_headings'] ?? null) ? $review['uncertain_headings'] : [];

    expect($titleCandidates)->toHaveCount(1)
        ->and($titleCandidates[0]['text'] ?? null)->toBe('Die Rolle der Fotografie in sozialen Medien')
        ->and(collect($uncertain)->pluck('text')->all())->not->toContain('Klasse: 8M')
        ->and(collect($uncertain)->pluck('text')->all())->not->toContain('Schuljahr: 2025/26')
        ->and(collect($uncertain)->pluck('text')->all())->not->toContain('Verfasser*in: Sandra Banu');
});

test('does not count toc artifact headings as normal zone headings', function () {
    $service = app(AbaPandocReviewBuilderService::class);

    $blocks = [
        [
            'type' => 'heading',
            'order' => 1,
            'plain_text' => 'Inhaltsverzeichnis',
            'heading_level' => 1,
            'is_usable_heading' => true,
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['section_keyword']],
            'section_hint' => ['section_type' => 'table_of_contents', 'reason' => 'section_keyword'],
            'document_zone' => ['zone' => 'table_of_contents', 'label' => 'Inhaltsverzeichnis', 'confidence' => 'high'],
        ],
        [
            'type' => 'heading',
            'order' => 2,
            'plain_text' => 'Einleitung 7',
            'heading_level' => 1,
            'is_usable_heading' => false,
            'problem_tags' => ['probable_toc_artifact'],
            'classification' => ['confidence' => 'low', 'strategy' => 'heuristic', 'signals' => ['toc_pattern']],
            'section_hint' => ['section_type' => 'table_of_contents', 'reason' => 'toc_pattern'],
            'document_zone' => ['zone' => 'table_of_contents', 'label' => 'Inhaltsverzeichnis', 'confidence' => 'medium'],
        ],
    ];

    $review = $service->buildReview($blocks);
    $zones = is_array($review['zone_overview'] ?? null) ? $review['zone_overview'] : [];
    $tocZone = collect($zones)->first(fn (array $zone): bool => ($zone['zone_key'] ?? null) === 'table_of_contents');

    expect($tocZone)->toBeArray()
        ->and($tocZone['heading_count'] ?? null)->toBe(1);
});

test('keeps abstract visible when abstract heading is marked as toc artifact but abstract paragraph follows', function () {
    $service = app(AbaPandocReviewBuilderService::class);

    $blocks = [
        [
            'type' => 'heading',
            'order' => 1,
            'plain_text' => 'Inhaltsverzeichnis',
            'heading_level' => 1,
            'is_usable_heading' => true,
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['section_keyword']],
            'section_hint' => ['section_type' => 'table_of_contents', 'reason' => 'section_keyword'],
            'document_zone' => ['zone' => 'table_of_contents', 'label' => 'Inhaltsverzeichnis', 'confidence' => 'high'],
        ],
        [
            'type' => 'heading',
            'order' => 2,
            'plain_text' => 'Abstract 6',
            'heading_level' => 1,
            'is_usable_heading' => false,
            'problem_tags' => ['probable_toc_artifact', 'toc_duplicate_of_content_heading'],
            'classification' => ['confidence' => 'low', 'strategy' => 'heuristic', 'signals' => ['toc_pattern']],
            'section_hint' => ['section_type' => 'abstract', 'reason' => 'toc_pattern'],
            'document_zone' => ['zone' => 'table_of_contents', 'label' => 'Inhaltsverzeichnis', 'confidence' => 'medium'],
        ],
        [
            'type' => 'heading',
            'order' => 3,
            'plain_text' => 'Abstract',
            'heading_level' => 1,
            'is_usable_heading' => false,
            'problem_tags' => ['probable_toc_artifact'],
            'classification' => ['confidence' => 'low', 'strategy' => 'heuristic', 'signals' => ['toc_pattern']],
            'section_hint' => ['section_type' => 'abstract', 'reason' => 'toc_pattern'],
            'document_zone' => ['zone' => 'table_of_contents', 'label' => 'Inhaltsverzeichnis', 'confidence' => 'medium'],
        ],
        [
            'type' => 'paragraph',
            'order' => 4,
            'plain_text' => 'Diese vorwissenschaftliche Arbeit untersucht die Rolle der Fotografie in sozialen Medien und beschreibt den Forschungszugang im Abstract-Abschnitt.',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'table_of_contents', 'label' => 'Inhaltsverzeichnis', 'confidence' => 'medium'],
        ],
        [
            'type' => 'heading',
            'order' => 5,
            'plain_text' => 'Einleitung',
            'heading_level' => 1,
            'is_usable_heading' => true,
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['section_keyword']],
            'section_hint' => ['section_type' => 'chapter', 'reason' => 'section_keyword'],
            'document_zone' => ['zone' => 'main_content', 'label' => 'Hauptteil', 'confidence' => 'high'],
        ],
    ];

    $review = $service->buildReview($blocks);
    $specialSections = is_array($review['special_sections'] ?? null) ? $review['special_sections'] : [];
    $probableTocArtifacts = is_array($review['probable_toc_artifacts'] ?? null) ? $review['probable_toc_artifacts'] : [];
    $abstract = collect($specialSections)->first(fn (array $item): bool => ($item['special_area_key'] ?? null) === 'abstract');

    expect($abstract)->toBeArray()
        ->and($abstract['text'] ?? null)->toBe('Abstract')
        ->and($abstract['content_text'] ?? null)->toContain('Diese vorwissenschaftliche Arbeit untersucht die Rolle der Fotografie in sozialen Medien')
        ->and($abstract['content_text'] ?? null)->not->toContain('Abstract 6')
        ->and(collect($probableTocArtifacts)->pluck('text')->all())->toContain('Abstract 6')
        ->and(collect($probableTocArtifacts)->pluck('text')->all())->toContain('Abstract');
});
