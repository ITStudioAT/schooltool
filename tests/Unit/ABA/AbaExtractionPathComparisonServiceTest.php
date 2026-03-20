<?php

use App\Services\AbaExtractionPathComparisonService;
use Tests\TestCase;

uses(TestCase::class);

test('builds comparable matrix for legacy and pandoc paths', function () {
    $service = app(AbaExtractionPathComparisonService::class);

    $legacyPayload = [
        'ok' => true,
        'sections' => [
            ['section_title' => 'Einleitung'],
            ['section_title' => 'Fazit'],
        ],
        'diagnostics' => [
            'title_page_detected' => true,
            'abstract_de_detected' => true,
            'abstract_en_detected' => false,
            'table_of_contents_detected' => true,
            'bibliography_detected' => true,
            'consent_declaration_detected' => true,
            'body_detected' => true,
            'figure_index_detected' => false,
            'section_type_counts' => [
                'chapter' => 2,
            ],
            'toc_special_entries_count' => 1,
            'unresolved_heading_candidates_count' => 2,
            'hierarchy_anomaly_count' => 1,
        ],
        'extraction' => [
            'selected_candidate' => 'docx_xml',
        ],
    ];

    $pandocPayload = [
        'ok' => true,
        'model_version' => 'v1',
        'blocks' => [
            [
                'type' => 'heading',
                'plain_text' => 'Inhaltsverzeichnis',
                'document_zone' => ['zone' => 'table_of_contents', 'label' => 'Inhaltsverzeichnis'],
                'problem_tags' => [],
                'classification' => ['confidence' => 'medium', 'strategy' => 'heuristic'],
                'section_hint' => ['section_type' => 'table_of_contents'],
            ],
            [
                'type' => 'heading',
                'plain_text' => '1. Einleitung',
                'document_zone' => ['zone' => 'main_content', 'label' => 'Hauptteil'],
                'problem_tags' => [],
                'classification' => ['confidence' => 'high', 'strategy' => 'deterministic'],
                'section_hint' => ['section_type' => 'chapter'],
            ],
            [
                'type' => 'heading',
                'plain_text' => 'Literaturverzeichnis',
                'document_zone' => ['zone' => 'bibliography_area', 'label' => 'Verzeichnisse / Bibliographie'],
                'problem_tags' => [],
                'classification' => ['confidence' => 'high', 'strategy' => 'deterministic'],
                'section_hint' => ['section_type' => 'bibliography'],
            ],
            [
                'type' => 'heading',
                'plain_text' => 'Eigenständigkeitserklärung',
                'document_zone' => ['zone' => 'declaration_area', 'label' => 'Erklärungsbereich'],
                'problem_tags' => [],
                'classification' => ['confidence' => 'high', 'strategy' => 'deterministic'],
                'section_hint' => ['section_type' => 'consent_declaration'],
            ],
            [
                'type' => 'heading',
                'plain_text' => '1. Einleitung 5',
                'document_zone' => ['zone' => 'table_of_contents', 'label' => 'Inhaltsverzeichnis'],
                'problem_tags' => ['probable_toc_artifact'],
                'classification' => ['confidence' => 'low', 'strategy' => 'heuristic'],
                'section_hint' => ['section_type' => null],
            ],
        ],
    ];

    $comparison = $service->compare($legacyPayload, $pandocPayload);

    expect($comparison['format'] ?? null)->toBe('aba_path_compare_v1')
        ->and($comparison['paths']['legacy_local']['status'] ?? null)->toBe('ok')
        ->and($comparison['paths']['pandoc']['status'] ?? null)->toBe('ok')
        ->and($comparison['paths']['openai_pdf']['supported'] ?? true)->toBeFalse()
        ->and($comparison['summary']['required_zone_count'] ?? 0)->toBeGreaterThan(0)
        ->and($comparison['summary']['pandoc_toc_artifacts'] ?? null)->toBe(1)
        ->and($comparison['paths']['legacy_local']['missing_required_parts'] ?? [])->not->toContain('abstract_en');
});

test('keeps path state transparent when legacy path fails', function () {
    $service = app(AbaExtractionPathComparisonService::class);

    $comparison = $service->compare(
        [
            'ok' => false,
            'error' => 'Legacy extraction failed',
        ],
        [
            'ok' => true,
            'blocks' => [],
            'model_version' => 'v1',
        ]
    );

    expect($comparison['paths']['legacy_local']['status'] ?? null)->toBe('error')
        ->and($comparison['paths']['legacy_local']['structure_notes'][0] ?? null)->toContain('Legacy extraction failed');
});
