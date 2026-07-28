<?php

use App\Services\AbaExtractionResultBuilder;
use App\Services\AbaLocalDocumentStructureExtractor;
use App\Services\AbaSectionMatcher;
use App\Services\AbaSectionRuleProvider;
use Tests\TestCase;

uses(TestCase::class);

it('preserves the stable extractor matcher and result contract for a portable document', function () {
    $text = implode("\n", [
        'Behandlungsmethoden bei Neurodermitis',
        'Max Mustermann',
        'Abstract',
        'Diese Arbeit untersucht verschiedene Behandlungsmethoden.',
        'Vorwort',
        'Persönliche Motivation zur Themenwahl.',
        'Inhaltsverzeichnis',
        '1. Einleitung 5',
        '2. Therapieformen 8',
        '2.1 Basistherapie 9',
        '3. Fazit 14',
        '1. Einleitung',
        'Einleitungstext zur Fragestellung.',
        '2. Therapieformen',
        'Beschreibung der Therapieformen.',
        '2.1 Basistherapie',
        'Beschreibung der Basistherapie.',
        '3. Fazit',
        'Zusammenfassung der Ergebnisse.',
        'Literaturverzeichnis',
        'Mustermann, M. (2025). Beispielquelle.',
    ]);
    $outline = [
        ['line_number' => 3, 'title' => 'Abstract', 'level' => 1, 'source' => 'keyword', 'is_toc' => false, 'section_type' => 'abstract', 'is_bold' => true],
        ['line_number' => 5, 'title' => 'Vorwort', 'level' => 1, 'source' => 'keyword', 'is_toc' => false, 'section_type' => 'foreword', 'is_bold' => true],
        ['line_number' => 12, 'title' => '1. Einleitung', 'level' => 1, 'source' => 'docx_style', 'is_toc' => false, 'section_type' => null, 'is_bold' => true],
        ['line_number' => 14, 'title' => '2. Therapieformen', 'level' => 1, 'source' => 'docx_style', 'is_toc' => false, 'section_type' => null, 'is_bold' => true],
        ['line_number' => 16, 'title' => '2.1 Basistherapie', 'level' => 2, 'source' => 'docx_style', 'is_toc' => false, 'section_type' => null, 'is_bold' => true],
        ['line_number' => 18, 'title' => '3. Fazit', 'level' => 1, 'source' => 'docx_style', 'is_toc' => false, 'section_type' => null, 'is_bold' => true],
        ['line_number' => 20, 'title' => 'Literaturverzeichnis', 'level' => 1, 'source' => 'keyword', 'is_toc' => false, 'section_type' => 'bibliography', 'is_bold' => true],
    ];
    $context = [
        'outline' => $outline,
        'toc_lines' => [7, 8, 9, 10, 11],
    ];

    $extractor = app(AbaLocalDocumentStructureExtractor::class);
    $firstSections = $extractor->extractSections($text, $context);
    $secondSections = $extractor->extractSections($text, $context);

    expect($secondSections)->toBe($firstSections);

    $stableSections = array_map(
        fn (array $section): array => [
            'section_type' => $section['section_type'] ?? null,
            'section_title' => $section['section_title'] ?? null,
            'hierarchy_level' => $section['hierarchy_level'] ?? null,
            'start_line' => $section['start_line'] ?? null,
            'end_line' => $section['end_line'] ?? null,
        ],
        $firstSections,
    );

    expect($stableSections)->toBe([
        ['section_type' => 'title_page', 'section_title' => 'Titelseite', 'hierarchy_level' => 1, 'start_line' => 1, 'end_line' => 2],
        ['section_type' => 'abstract', 'section_title' => 'Abstract', 'hierarchy_level' => 1, 'start_line' => 3, 'end_line' => 4],
        ['section_type' => 'foreword', 'section_title' => 'Vorwort', 'hierarchy_level' => 1, 'start_line' => 5, 'end_line' => 6],
        ['section_type' => 'table_of_contents', 'section_title' => 'Inhaltsverzeichnis', 'hierarchy_level' => 1, 'start_line' => 7, 'end_line' => 11],
        ['section_type' => 'chapter', 'section_title' => '1. Einleitung', 'hierarchy_level' => 1, 'start_line' => 12, 'end_line' => 13],
        ['section_type' => 'chapter', 'section_title' => '2. Therapieformen', 'hierarchy_level' => 1, 'start_line' => 14, 'end_line' => 15],
        ['section_type' => 'subchapter', 'section_title' => '2.1 Basistherapie', 'hierarchy_level' => 2, 'start_line' => 16, 'end_line' => 17],
        ['section_type' => 'chapter', 'section_title' => '3. Fazit', 'hierarchy_level' => 1, 'start_line' => 18, 'end_line' => 19],
        ['section_type' => 'bibliography', 'section_title' => 'Literaturverzeichnis', 'hierarchy_level' => 1, 'start_line' => 20, 'end_line' => 21],
    ]);

    $sectionKeys = array_column($firstSections, 'section_key');
    expect($sectionKeys)->toHaveCount(count(array_unique($sectionKeys)));

    foreach ($firstSections as $section) {
        expect(array_keys($section))->toBe([
            'section_key',
            'parent_key',
            'section_type',
            'section_title',
            'extracted_text',
            'hierarchy_level',
            'start_line',
            'end_line',
            'start_page',
            'end_page',
            'anchor',
            'metadata',
        ]);

        if (($section['parent_key'] ?? null) !== null) {
            expect($sectionKeys)->toContain($section['parent_key']);
        }
    }

    $ruleDefinition = app(AbaSectionRuleProvider::class)->definition();
    $matchedSections = app(AbaSectionMatcher::class)->match($ruleDefinition['sections'], $firstSections);
    $result = app(AbaExtractionResultBuilder::class)->build($ruleDefinition, [
        'document' => [
            'source_original_name' => 'portable-characterization.docx',
            'source_mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'page_image_counts' => [],
        ],
        'outline' => $outline,
        'toc_lines' => $context['toc_lines'],
        'diagnostics' => $extractor->lastDiagnostics(),
        'sections' => $firstSections,
        'title_page_processing' => [],
    ], $matchedSections);

    expect(array_keys($matchedSections))->toBe([
        'sections',
        'missing_required_section_keys',
        'found_optional_section_keys',
        'uncertain_matches',
        'unmatched_blocks_count',
        'warnings',
        'errors',
        'used_section_keys',
        'matched_rule_keys_by_section',
    ])->and(array_keys($result))->toBe([
        'rule_version',
        'rule_domain',
        'scope',
        'document',
        'sections',
        'found_required_section_keys',
        'missing_required_section_keys',
        'found_optional_section_keys',
        'uncertain_matches',
        'unmatched_blocks_count',
        'warnings',
        'errors',
        'raw_structure',
        'matched_rule_keys_by_section',
    ])->and($result['document'])->toBe([
        'source_original_name' => 'portable-characterization.docx',
        'source_mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'page_image_counts' => [],
    ])->and($result['raw_structure']['sections'])->toHaveCount(9);
});
