<?php

use App\Services\AbaLocalDocumentStructureExtractor;
use Tests\TestCase;

uses(TestCase::class);

it('detects abstract heading that reappears after TOC with body text', function () {
    // Simulates a document where "Abstract" appears in the outline TOC (L8),
    // as a numbered TOC entry (L12 "Abstract 6"), and as the real heading (L18)
    // followed by actual abstract body text (L19).
    $text = implode("\n", [
        '„Die Rolle der Fotografie in sozialen Medien"',                     // L1 title
        'Ästhetik, Technologie und gesellschaftliche Auswirkungen',          // L2
        'Verfasser*in: Sandra Banu',                                          // L3
        'Klasse: 8M',                                                         // L4
        'Betreuer*in: Dipl.-Ing. Günther Kron',                              // L5
        'Schuljahr: 2025/26',                                                 // L6
        'Inhaltsverzeichnis',                                                 // L7 TOC heading
        'Abstract',                                                           // L8 TOC entry (outline)
        '- Einleitung',                                                       // L9
        '- Entwicklung der Fotografie im Kontext sozialer Medien 2.1 Fotografie vor dem Zeitalter sozialer Medien', // L10
        'Inhaltsverzeichnis',                                                 // L11 structured TOC
        'Abstract 6',                                                         // L12 TOC entry
        '1. Einleitung 7',                                                    // L13
        '2. Entwicklung der Fotografie im Kontext sozialer Medien 9',        // L14
        '3. Technologische Aspekte 14',                                       // L15
        'Literaturverzeichnis 34',                                            // L16
        'Eigenständigkeitserklärung 38',                                      // L17
        'Abstract',                                                           // L18 real abstract heading
        'Diese vorwissenschaftliche Arbeit untersucht die Rolle der Fotografie in sozialen Medien unter besonderer Berücksichtigung ästhetischer, technologischer und gesellschaftlicher Aspekte.', // L19 abstract body
        '1. Einleitung',                                                      // L20 chapter heading
        'In meiner abschließenden Arbeit beschäftige ich mich mit der Frage, welche Bedeutung Fotografien haben.', // L21
        '2.1 Fotografie vor dem Zeitalter sozialer Medien',                  // L22 subchapter
        'Bis in die späten 90er Jahre dominierte die analoge Fotografie.',    // L23
    ]);

    $outline = [
        ['line_number' => 7, 'title' => 'Inhaltsverzeichnis', 'level' => 1, 'source' => 'keyword', 'is_toc' => false, 'section_type' => 'table_of_contents', 'is_bold' => true],
        ['line_number' => 8, 'title' => 'Abstract', 'level' => 1, 'source' => 'keyword', 'is_toc' => false, 'section_type' => 'abstract', 'is_bold' => true],
        ['line_number' => 18, 'title' => 'Abstract', 'level' => 1, 'source' => 'keyword', 'is_toc' => false, 'section_type' => 'abstract', 'style' => 'meineFormatvorlagegrn', 'is_bold' => false],
        ['line_number' => 22, 'title' => '2.1 Fotografie vor dem Zeitalter sozialer Medien', 'level' => 2, 'source' => 'docx_style', 'is_toc' => false, 'section_type' => null, 'is_bold' => false],
    ];

    $tocLines = [11, 12, 13, 14, 15, 16, 17];

    $extractor = app(AbaLocalDocumentStructureExtractor::class);
    $sections = $extractor->extractSections($text, [
        'outline' => $outline,
        'toc_lines' => $tocLines,
    ]);

    $abstractSections = array_values(array_filter(
        $sections,
        fn (array $s) => ($s['section_type'] ?? '') === 'abstract'
    ));

    expect($abstractSections)->not->toBeEmpty('Abstract section should be detected');
    expect($abstractSections[0]['start_line'])->toBe(18);
    expect($abstractSections[0]['extracted_text'])->toContain('vorwissenschaftliche Arbeit');

    $diagnostics = $extractor->lastDiagnostics();
    expect($diagnostics['abstract_detected'])->toBeTrue();

    // TOC should not extend past line 17
    $tocSections = array_values(array_filter(
        $sections,
        fn (array $s) => ($s['section_type'] ?? '') === 'table_of_contents'
    ));
    expect($tocSections)->not->toBeEmpty();
    expect($tocSections[0]['end_line'])->toBeLessThanOrEqual(17);
});

it('detects a table of contents from structured toc lines when only "Inhalt" is present', function () {
    $text = implode("\n", [
        'Behandlungsmethoden bei Neurodermitis',
        'Anh Vu Duy',
        'Abstract',
        'Kurze Zusammenfassung der Arbeit.',
        'Vorwort',
        'Persönliche Motivation zur Themenwahl.',
        'Inhalt',
        '1. Einleitung 5',
        '2. Therapieformen 8',
        '2.1 Basistherapie 9',
        '3. Fazit 14',
        '1. Einleitung',
        'Einleitungstext.',
        '2. Therapieformen',
        'Kapiteltext.',
        '2.1 Basistherapie',
        'Unterkapiteltext.',
        '3. Fazit',
        'Schlusstext.',
    ]);

    $outline = [
        ['line_number' => 3, 'title' => 'Abstract', 'level' => 1, 'source' => 'keyword', 'is_toc' => false, 'section_type' => 'abstract', 'is_bold' => true],
        ['line_number' => 5, 'title' => 'Vorwort', 'level' => 1, 'source' => 'keyword', 'is_toc' => false, 'section_type' => 'foreword', 'is_bold' => true],
        ['line_number' => 12, 'title' => '1. Einleitung', 'level' => 1, 'source' => 'docx_style', 'is_toc' => false, 'section_type' => null, 'is_bold' => false],
        ['line_number' => 14, 'title' => '2. Therapieformen', 'level' => 1, 'source' => 'docx_style', 'is_toc' => false, 'section_type' => null, 'is_bold' => false],
        ['line_number' => 16, 'title' => '2.1 Basistherapie', 'level' => 2, 'source' => 'docx_style', 'is_toc' => false, 'section_type' => null, 'is_bold' => false],
        ['line_number' => 18, 'title' => '3. Fazit', 'level' => 1, 'source' => 'docx_style', 'is_toc' => false, 'section_type' => null, 'is_bold' => false],
    ];

    $extractor = app(AbaLocalDocumentStructureExtractor::class);
    $sections = $extractor->extractSections($text, [
        'outline' => $outline,
        'toc_lines' => [7, 8, 9, 10, 11],
    ]);

    $tocSections = array_values(array_filter(
        $sections,
        fn (array $section): bool => ($section['section_type'] ?? '') === 'table_of_contents'
    ));

    expect($tocSections)->not->toBeEmpty()
        ->and($tocSections[0]['start_line'] ?? null)->toBe(7)
        ->and($tocSections[0]['end_line'] ?? null)->toBe(11)
        ->and($tocSections[0]['section_title'] ?? null)->toBe('Inhaltsverzeichnis')
        ->and($tocSections[0]['extracted_text'] ?? '')->toContain("Inhalt\n1. Einleitung 5");
});

it('detects two table of contents blocks when a manual heading-only toc and a word toc both exist', function () {
    $text = implode("\n", [
        'Die Rolle der Fotografie in sozialen Medien',
        'Abstract',
        'Kurze Zusammenfassung der Arbeit.',
        'Inhaltsverzeichnis',
        'Abstract',
        '1. Einleitung',
        '2. Entwicklung der Fotografie im Kontext sozialer Medien',
        '2.1 Fotografie vor dem Zeitalter sozialer Medien',
        'Literaturverzeichnis',
        'Inhaltsverzeichnis',
        'Abstract 3',
        '1. Einleitung 5',
        '2. Entwicklung der Fotografie im Kontext sozialer Medien 7',
        '2.1 Fotografie vor dem Zeitalter sozialer Medien 8',
        'Literaturverzeichnis 20',
        '1. Einleitung',
        'Einleitungstext der Arbeit.',
    ]);

    $extractor = app(AbaLocalDocumentStructureExtractor::class);
    $sections = $extractor->extractSections($text, [
        'outline' => [],
        'toc_lines' => [11, 12, 13, 14, 15],
    ]);

    $tocSections = array_values(array_filter(
        $sections,
        fn (array $section): bool => ($section['section_type'] ?? '') === 'table_of_contents'
    ));

    expect($tocSections)->toHaveCount(2)
        ->and($tocSections[0]['extracted_text'] ?? '')->toContain("Inhaltsverzeichnis\nAbstract\n1. Einleitung")
        ->and($tocSections[1]['extracted_text'] ?? '')->toContain("Inhaltsverzeichnis\nAbstract 3\n1. Einleitung 5");
});
