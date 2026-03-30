<?php

use App\Services\AbaExtractionResultBuilder;
use Tests\TestCase;

uses(TestCase::class);

test('enriches the title page extraction with detailed aba metadata', function () {
    $builder = app(AbaExtractionResultBuilder::class);

    $result = $builder->build(
        ['version' => '2026-03-16', 'domain' => 'ahs-aba', 'scope' => []],
        [
            'document' => [
                'source_original_name' => 'test-main.docx',
                'page_image_counts' => [
                    1 => 1,
                ],
            ],
            'sections' => [
                [
                    'section_key' => 'title-page',
                    'section_type' => 'title_page',
                    'section_title' => 'Titelseite',
                    'extracted_text' => implode("\n", [
                        'Titel der Arbeit',
                        'Untertitel der Arbeit',
                        'Schule: BORG Musterstadt',
                        'Fach: Medieninformatik',
                        'Verfasser: Max Mustermann',
                        'Klasse: 8M',
                        'Betreuer: Mag. Erika Muster',
                        'Datum: März 2026',
                    ]),
                    'start_page' => 1,
                    'end_page' => 1,
                    'metadata' => [
                        'title_page_details' => [
                            'title' => 'Untertitel der Arbeit',
                            'submitter' => 'Max Mustermann',
                            'advisor' => 'Mag. Erika Muster',
                            'class' => '8M',
                            'year' => '2025/26',
                            'date_context' => 'März 2026',
                        ],
                    ],
                ],
            ],
            'outline' => [],
            'toc_lines' => [],
            'diagnostics' => [],
        ],
        [
            'sections' => [
                [
                    'key' => 'title_page',
                    'label' => 'Titelblatt',
                    'required' => true,
                    'found' => true,
                    'uncertain' => false,
                    'confidence' => 0.99,
                    'matched_heading' => 'Titelseite',
                    'preview_text' => 'Titel der Arbeit',
                    'start_index' => 0,
                    'end_index' => 0,
                    'matched_section_keys' => ['title-page'],
                    'warnings' => [],
                ],
            ],
            'missing_required_section_keys' => [],
            'found_optional_section_keys' => [],
            'uncertain_matches' => [],
            'unmatched_blocks_count' => 0,
            'warnings' => [],
            'errors' => [],
            'matched_rule_keys_by_section' => [
                'title-page' => ['title_page'],
            ],
        ],
    );

    $titlePage = collect($result['sections'] ?? [])->firstWhere('key', 'title_page');
    $otherThings = collect(data_get($titlePage, 'title_page.other_things', []));

    expect($titlePage)->toBeArray()
        ->and(data_get($titlePage, 'title_page.title'))->toBe('Titel der Arbeit')
        ->and(data_get($titlePage, 'title_page.subtitle'))->toBe('Untertitel der Arbeit')
        ->and(data_get($titlePage, 'title_page.author'))->toBe('Max Mustermann')
        ->and(data_get($titlePage, 'title_page.class'))->toBe('8M')
        ->and(data_get($titlePage, 'title_page.advisor'))->toBe('Mag. Erika Muster')
        ->and(data_get($titlePage, 'title_page.date'))->toBe('März 2026')
        ->and(data_get($titlePage, 'title_page.page_number'))->toBe(1)
        ->and(data_get($titlePage, 'title_page.found_images_count'))->toBe(1)
        ->and($otherThings->contains(fn (array $item): bool => ($item['label'] ?? null) === 'Schule' && ($item['value'] ?? null) === 'BORG Musterstadt'))->toBeTrue()
        ->and($otherThings->contains(fn (array $item): bool => ($item['label'] ?? null) === 'Fach' && ($item['value'] ?? null) === 'Medieninformatik'))->toBeTrue();
});
