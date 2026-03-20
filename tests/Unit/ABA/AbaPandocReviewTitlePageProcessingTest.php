<?php

use App\Services\AbaPandocReviewBuilderService;
use Tests\TestCase;

uses(TestCase::class);

test('buildReview exposes title_page_processing with normalized date and ui-safe logo state', function () {
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
            'type' => 'paragraph',
            'order' => 2,
            'plain_text' => 'Ästhetik, Technologie und gesellschaftliche Auswirkungen',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 3,
            'plain_text' => 'Verfasser*in: Sandra Banu',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 4,
            'plain_text' => 'Betreuer*in: Dipl.-Ing. Günther Kron',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 5,
            'plain_text' => 'Klasse: 8M',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 6,
            'plain_text' => 'Fach: Medieninformatik',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 7,
            'plain_text' => 'Schuljahr: 2025/26',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'high'],
        ],
        [
            'type' => 'paragraph',
            'order' => 8,
            'plain_text' => 'Datum: --',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['pandoc_paragraph_block']],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'high'],
        ],
        [
            'type' => 'image',
            'order' => 9,
            'plain_text' => 'Schullogo',
            'text' => 'Schullogo',
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['image_only_paragraph']],
            'image' => ['target' => 'media/image1.png', 'title' => '', 'alt_text' => 'Schullogo'],
            'document_zone' => ['zone' => 'title_page', 'label' => 'Titelblatt', 'confidence' => 'high'],
        ],
    ];

    $review = $service->buildReview($blocks);
    $processing = is_array($review['title_page_processing'] ?? null) ? $review['title_page_processing'] : [];

    expect($processing)->not->toBeEmpty()
        ->and($processing['source_extraction']['date'] ?? null)->toBe('--')
        ->and($processing['source_extraction']['school_year'] ?? null)->toBe('2025/26')
        ->and($processing['normalized_output']['date'] ?? null)->toBe('2025/26')
        ->and($processing['normalized_output']['school_year'] ?? null)->toBeNull()
        ->and($processing['normalized_output']['additional_properties'] ?? null)->toBeArray()->toHaveCount(1)
        ->and(($processing['normalized_output']['additional_properties'][0]['label'] ?? null))->toBe('Fach')
        ->and(($processing['normalized_output']['additional_properties'][0]['value'] ?? null))->toBe('Medieninformatik')
        ->and($processing['pandoc_metadata']['Datum'] ?? null)->toBe('2025/26')
        ->and($processing['logos'] ?? null)->toBeArray()->toHaveCount(1)
        ->and($processing['logo']['logo_detected'] ?? null)->toBeTrue()
        ->and($processing['logo']['logo_count'] ?? null)->toBe(1)
        ->and($processing['logo']['logo_detected_count'] ?? null)->toBe(1)
        ->and($processing['logo']['logo_asset_available'] ?? null)->toBeFalse()
        ->and($processing['logo']['logo_ui_displayable'] ?? null)->toBeFalse()
        ->and($processing['ui_model']['show_logo'] ?? null)->toBeFalse()
        ->and($processing['ui_model']['additional_properties'] ?? null)->toBeArray()->toHaveCount(1)
        ->and($processing['ui_model']['logo_assets'] ?? null)->toBeArray()->toHaveCount(1)
        ->and($processing['test_report']['overall_status'] ?? null)->toBe('warning');
});
