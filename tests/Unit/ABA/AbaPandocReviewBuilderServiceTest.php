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
            'type' => 'heading',
            'order' => 2,
            'plain_text' => 'Abstract',
            'heading_level' => 1,
            'is_usable_heading' => true,
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['section_keyword']],
            'section_hint' => ['section_type' => 'abstract', 'reason' => 'section_keyword'],
            'document_zone' => ['zone' => 'front_matter', 'label' => 'Frontmatter', 'confidence' => 'high'],
        ],
        [
            'type' => 'heading',
            'order' => 3,
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
            'order' => 4,
            'plain_text' => '1.1 Historischer Kontext',
            'heading_level' => 2,
            'is_usable_heading' => true,
            'problem_tags' => [],
            'classification' => ['confidence' => 'high', 'strategy' => 'deterministic', 'signals' => ['numbered_heading']],
            'section_hint' => ['section_type' => 'chapter', 'reason' => 'numbered_heading'],
            'document_zone' => ['zone' => 'main_content', 'label' => 'Hauptteil', 'confidence' => 'high'],
        ],
        [
            'type' => 'heading',
            'order' => 5,
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
            'order' => 6,
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

    expect($frontmatter)->toHaveCount(2)
        ->and($frontmatter[0]['text'] ?? null)->toContain('Die Rolle der Medien')
        ->and($frontmatter[1]['text'] ?? null)->toBe('Abstract')
        ->and($main)->toHaveCount(1)
        ->and($main[0]['text'] ?? null)->toBe('1. Einleitung')
        ->and(is_array($main[0]['children'] ?? null))->toBeTrue()
        ->and($main[0]['children'][0]['text'] ?? null)->toBe('1.1 Historischer Kontext')
        ->and($endmatter)->toHaveCount(1)
        ->and($endmatter[0]['text'] ?? null)->toBe('Literaturverzeichnis')
        ->and($excluded)->toHaveCount(1)
        ->and($excluded[0]['text'] ?? null)->toBe('1. Einleitung 5')
        ->and($review['recognized_main_sections'][0]['text'] ?? null)->toBe('1. Einleitung')
        ->and($review['recognized_main_sections'])->not->toContain(fn (array $item): bool => ($item['text'] ?? null) === 'Abstract');
});
