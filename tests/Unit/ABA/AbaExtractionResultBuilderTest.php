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
    $previewNotes = collect(data_get($titlePage, 'title_page.preview_notes', []));

    expect($titlePage)->toBeArray()
        ->and(data_get($titlePage, 'title_page.title'))->toBe('Titel der Arbeit')
        ->and(data_get($titlePage, 'title_page.subtitle'))->toBe('Untertitel der Arbeit')
        ->and(data_get($titlePage, 'title_page.author'))->toBe('Max Mustermann')
        ->and(data_get($titlePage, 'title_page.class'))->toBe('8M')
        ->and(data_get($titlePage, 'title_page.advisor'))->toBe('Mag. Erika Muster')
        ->and(data_get($titlePage, 'title_page.date'))->toBe('März 2026')
        ->and(data_get($titlePage, 'title_page.page_number'))->toBe(1)
        ->and(data_get($titlePage, 'title_page.found_images_count'))->toBe(1)
        ->and($previewNotes->contains('DOCX-Seitenanalyse: 1 Bild auf Seite 1 erkannt.'))->toBeTrue()
        ->and($previewNotes->contains('Titelblatt-Bilder erkannt, aber ohne renderbares Asset.'))->toBeTrue()
        ->and($previewNotes->contains('Keine Titelblatt-Bilder erkannt.'))->toBeFalse()
        ->and($otherThings->contains(fn (array $item): bool => ($item['label'] ?? null) === 'Schule' && ($item['value'] ?? null) === 'BORG Musterstadt'))->toBeTrue()
        ->and($otherThings->contains(fn (array $item): bool => ($item['label'] ?? null) === 'Fach' && ($item['value'] ?? null) === 'Medieninformatik'))->toBeTrue();
});

test('uses precomputed title page processing to expose extracted title page images', function () {
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
            'title_page_processing' => [
                'ui_model' => [
                    'preview_title' => 'Titel der Arbeit',
                    'preview_subtitle' => 'Untertitel der Arbeit',
                    'preview_author' => 'Max Mustermann',
                    'preview_advisor' => 'Mag. Erika Muster',
                    'preview_class' => '8M',
                    'preview_date' => 'März 2026',
                    'additional_properties' => [],
                    'preview_notes' => ['Vorschau enthält 1 renderbares Titelblatt-Bild/Logo.'],
                ],
                'logo' => [
                    'logo_detected_count' => 1,
                ],
                'logos' => [
                    [
                        'asset_index' => 0,
                        'logo_asset_available' => true,
                        'logo_ui_displayable' => true,
                        'logo_asset_path' => 'aba/titlepage-assets/aba-1/run-7/titlepage-asset-1.png',
                        'logo_asset_disk' => 'local',
                        'logo_asset_filename' => 'titlepage-asset-1.png',
                        'logo_asset_mime_type' => 'image/png',
                        'logo_alt_text' => 'Schullogo',
                        'logo_description' => 'Schullogo',
                        'logo_position' => 'oben',
                        'logo_type' => 'offizielles Schul-/Institutionslogo',
                        'logo_ui_display_note' => 'Asset extrahiert und für UI-Rendering verfügbar.',
                    ],
                ],
            ],
            'sections' => [
                [
                    'section_key' => 'title-page',
                    'section_type' => 'title_page',
                    'section_title' => 'Titelseite',
                    'extracted_text' => 'Titel der Arbeit',
                    'start_page' => 1,
                    'end_page' => 1,
                    'metadata' => [
                        'title_page_details' => [
                            'title' => 'Titel der Arbeit',
                            'submitter' => 'Max Mustermann',
                            'advisor' => 'Mag. Erika Muster',
                            'class' => '8M',
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
    $images = collect(data_get($titlePage, 'title_page.images', []));
    $previewNotes = collect(data_get($titlePage, 'title_page.preview_notes', []));

    expect($images)->toHaveCount(1)
        ->and(data_get($titlePage, 'title_page.found_images_count'))->toBe(1)
        ->and(($images->first()['asset_path'] ?? null))->toBe('aba/titlepage-assets/aba-1/run-7/titlepage-asset-1.png')
        ->and(($images->first()['asset_available'] ?? null))->toBeTrue()
        ->and(($images->first()['ui_displayable'] ?? null))->toBeTrue()
        ->and($previewNotes->contains('DOCX-Seitenanalyse: 1 Bild auf Seite 1 erkannt.'))->toBeTrue()
        ->and($previewNotes->contains('Vorschau enthält 1 renderbares Titelblatt-Bild/Logo.'))->toBeTrue();
});

test('exposes table of contents entries for the extraction card', function () {
    $builder = app(AbaExtractionResultBuilder::class);

    $result = $builder->build(
        ['version' => '2026-03-16', 'domain' => 'ahs-aba', 'scope' => []],
        [
            'document' => [
                'source_original_name' => 'test-main.docx',
            ],
            'sections' => [
                [
                    'section_key' => 'toc-1',
                    'section_type' => 'table_of_contents',
                    'section_title' => 'Inhaltsverzeichnis',
                    'extracted_text' => implode("\n", [
                        'Inhalt',
                        '1. Einleitung 5',
                        '2. Therapieformen 8',
                        '2.1 Basistherapie 9',
                    ]),
                    'start_page' => 1,
                    'end_page' => 1,
                    'metadata' => [],
                ],
            ],
            'outline' => [],
            'toc_lines' => [],
            'diagnostics' => [],
        ],
        [
            'sections' => [
                [
                    'key' => 'table_of_contents',
                    'label' => 'Inhaltsverzeichnis',
                    'required' => true,
                    'found' => true,
                    'uncertain' => false,
                    'confidence' => 0.99,
                    'matched_heading' => 'Inhaltsverzeichnis',
                    'preview_text' => 'Inhalt 1. Einleitung 5',
                    'start_index' => 0,
                    'end_index' => 0,
                    'matched_section_keys' => ['toc-1'],
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
                'toc-1' => ['table_of_contents'],
            ],
        ],
    );

    $toc = collect($result['sections'] ?? [])->firstWhere('key', 'table_of_contents');

    expect($toc)->toBeArray()
        ->and(data_get($toc, 'table_of_contents.heading'))->toBe('Inhalt')
        ->and(data_get($toc, 'table_of_contents.entry_count'))->toBe(3)
        ->and(data_get($toc, 'table_of_contents.entries.0'))->toBe('1. Einleitung 5')
        ->and(data_get($toc, 'table_of_contents.entries.2'))->toBe('2.1 Basistherapie 9');
});

test('prefers structured title page title and subtitle when precomputed preview values are generic metadata', function () {
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
            'title_page_processing' => [
                'ui_model' => [
                    'preview_title' => 'Titelblatt',
                    'preview_subtitle' => 'Fach: Medieninformatik',
                    'preview_author' => 'Max Mustermann',
                    'preview_advisor' => 'Mag. Erika Muster',
                    'preview_class' => '8M',
                    'preview_date' => 'März 2026',
                    'additional_properties' => [
                        ['label' => 'Fach', 'value' => 'Medieninformatik'],
                    ],
                    'preview_notes' => ['Vorschau enthält 1 renderbares Titelblatt-Bild/Logo.'],
                ],
                'logo' => [
                    'logo_detected_count' => 1,
                ],
                'logos' => [
                    [
                        'asset_index' => 0,
                        'logo_asset_available' => true,
                        'logo_ui_displayable' => true,
                        'logo_asset_path' => 'aba/titlepage-assets/aba-1/run-7/titlepage-asset-1.png',
                        'logo_asset_disk' => 'local',
                        'logo_asset_filename' => 'titlepage-asset-1.png',
                        'logo_asset_mime_type' => 'image/png',
                    ],
                ],
            ],
            'sections' => [
                [
                    'section_key' => 'title-page',
                    'section_type' => 'title_page',
                    'section_title' => 'Titelseite',
                    'extracted_text' => "Titel der Arbeit\nUntertitel der Arbeit\nSchule: BORG Musterstadt\nVerfasser: Max Mustermann\nKlasse: 8M\nBetreuer: Mag. Erika Muster\nFach: Medieninformatik\nMärz 2026",
                    'start_page' => 1,
                    'end_page' => 1,
                    'metadata' => [
                        'title_page_details' => [
                            'title' => 'Titel der Arbeit',
                            'subtitle' => 'Untertitel der Arbeit',
                            'submitter' => 'Max Mustermann',
                            'advisor' => 'Mag. Erika Muster',
                            'class' => '8M',
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

    expect(data_get($titlePage, 'title_page.title'))->toBe('Titel der Arbeit')
        ->and(data_get($titlePage, 'title_page.subtitle'))->toBe('Untertitel der Arbeit')
        ->and(data_get($titlePage, 'title_page.author'))->toBe('Max Mustermann')
        ->and(data_get($titlePage, 'title_page.found_images_count'))->toBe(1);
});

test('detects a multi-line school block before the actual title on the title page', function () {
    $builder = app(AbaExtractionResultBuilder::class);

    $result = $builder->build(
        ['version' => '2026-03-16', 'domain' => 'ahs-aba', 'scope' => []],
        [
            'document' => [
                'source_original_name' => 'test-main.docx',
                'page_image_counts' => [],
            ],
            'sections' => [
                [
                    'section_key' => 'title-page',
                    'section_type' => 'title_page',
                    'section_title' => 'Titelseite',
                    'extracted_text' => implode("\n", [
                        'Christian Doppler-Gymnasium',
                        'Franz-Josef-Kai 41',
                        '5020 Salzburg',
                        '',
                        'Die Rolle der Medien in der politischen Meinungsbildung',
                        '',
                        'Verfasst von',
                        'Yvonne Pucher',
                        'Betreuer: Dipl.-Ing. Günther Kron',
                        'Klasse: 8M',
                    ]),
                    'start_page' => 1,
                    'end_page' => 1,
                    'metadata' => [
                        'title_page_details' => [
                            'submitter' => 'Yvonne Pucher',
                            'advisor' => 'Dipl.-Ing. Günther Kron',
                            'class' => '8M',
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
                    'preview_text' => 'Christian Doppler-Gymnasium',
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
        ->and(data_get($titlePage, 'title_page.title'))->toBe('Die Rolle der Medien in der politischen Meinungsbildung')
        ->and(data_get($titlePage, 'title_page.subtitle'))->toBeNull()
        ->and(data_get($titlePage, 'title_page.author'))->toBe('Yvonne Pucher')
        ->and(data_get($titlePage, 'title_page.advisor'))->toBe('Dipl.-Ing. Günther Kron')
        ->and(data_get($titlePage, 'title_page.class'))->toBe('8M')
        ->and($otherThings->contains(fn (array $item): bool => ($item['label'] ?? null) === 'Schule' && ($item['value'] ?? null) === 'Christian Doppler-Gymnasium'))->toBeTrue()
        ->and($otherThings->contains(fn (array $item): bool => ($item['label'] ?? null) === 'Schuladresse' && ($item['value'] ?? null) === 'Franz-Josef-Kai 41'))->toBeTrue()
        ->and($otherThings->contains(fn (array $item): bool => ($item['label'] ?? null) === 'Schulort' && ($item['value'] ?? null) === '5020 Salzburg'))->toBeTrue()
        ->and($otherThings->contains(fn (array $item): bool => ($item['label'] ?? null) === 'Schule (vollständig)' && ($item['value'] ?? null) === 'Christian Doppler-Gymnasium, Franz-Josef-Kai 41, 5020 Salzburg'))->toBeTrue();
});

test('strips a trailing school block from precomputed title page titles', function () {
    $builder = app(AbaExtractionResultBuilder::class);

    $result = $builder->build(
        ['version' => '2026-03-16', 'domain' => 'ahs-aba', 'scope' => []],
        [
            'document' => [
                'source_original_name' => 'test-main.docx',
                'page_image_counts' => [],
            ],
            'title_page_processing' => [
                'ui_model' => [
                    'preview_title' => 'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich Christian-Doppler-Gymnasium Franz-Josef-Kai 41 5020 Salzburg',
                    'preview_author' => 'Hanna Danninger',
                    'preview_advisor' => '/in: Mag. Gerhild Ungeringer-Kron',
                    'preview_class' => '8B',
                    'preview_date' => 'Februar 2024',
                    'additional_properties' => [],
                    'preview_notes' => [],
                ],
                'logo' => [
                    'logo_detected_count' => 0,
                ],
                'logos' => [],
            ],
            'sections' => [
                [
                    'section_key' => 'title-page',
                    'section_type' => 'title_page',
                    'section_title' => 'Titelseite',
                    'extracted_text' => implode("\n", [
                        'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich',
                        '',
                        'Vorwissenschaftliche Arbeit verfasst von',
                        'Hanna Danninger',
                        'Klasse 8B',
                        'Betreuer/in: Mag. Gerhild Ungeringer-Kron',
                        '',
                        'Februar 2024',
                        'Christian-Doppler-Gymnasium',
                        'Franz-Josef-Kai 41',
                        '5020 Salzburg',
                    ]),
                    'start_page' => 1,
                    'end_page' => 1,
                    'metadata' => [
                        'title_page_details' => [
                            'title' => 'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich Christian-Doppler-Gymnasium Franz-Josef-Kai 41 5020 Salzburg',
                            'submitter' => 'Hanna Danninger',
                            'advisor' => '/in: Mag. Gerhild Ungeringer-Kron',
                            'class' => '8B',
                            'date_context' => 'Februar 2024',
                            'school' => 'Christian-Doppler-Gymnasium',
                            'school_address' => 'Franz-Josef-Kai 41',
                            'school_city' => '5020 Salzburg',
                            'school_full' => 'Christian-Doppler-Gymnasium, Franz-Josef-Kai 41, 5020 Salzburg',
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
                    'preview_text' => 'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich',
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

    expect($titlePage)->toBeArray()
        ->and(data_get($titlePage, 'title_page.title'))->toBe('Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich')
        ->and(data_get($titlePage, 'title_page.advisor'))->toBe('Mag. Gerhild Ungeringer-Kron');
});

test('derives school details from a single-line school block and strips it from preview titles', function () {
    $builder = app(AbaExtractionResultBuilder::class);

    $result = $builder->build(
        ['version' => '2026-03-16', 'domain' => 'ahs-aba', 'scope' => []],
        [
            'document' => [
                'source_original_name' => 'test-main.docx',
                'page_image_counts' => [],
            ],
            'title_page_processing' => [
                'ui_model' => [
                    'preview_title' => 'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich Christian-Doppler-Gymnasium Franz-Josef-Kai 41 5020 Salzburg',
                    'preview_author' => 'Hanna Danninger',
                    'preview_advisor' => 'Betreuer/in: Mag. Gerhild Ungeringer-Kron',
                    'preview_class' => '8B',
                    'preview_date' => 'Februar 2024',
                    'additional_properties' => [],
                    'preview_notes' => [],
                ],
                'logo' => [
                    'logo_detected_count' => 0,
                ],
                'logos' => [],
            ],
            'sections' => [
                [
                    'section_key' => 'title-page',
                    'section_type' => 'title_page',
                    'section_title' => 'Titelseite',
                    'extracted_text' => implode("\n", [
                        'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich',
                        'Vorwissenschaftliche Arbeit verfasst von',
                        'Hanna Danninger',
                        'Klasse 8B',
                        'Betreuer/in: Mag. Gerhild Ungeringer-Kron',
                        'Februar 2024',
                        'Christian-Doppler-Gymnasium Franz-Josef-Kai 41 5020 Salzburg',
                    ]),
                    'start_page' => 1,
                    'end_page' => 1,
                    'metadata' => [
                        'title_page_details' => [
                            'title' => 'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich',
                            'submitter' => 'Hanna Danninger',
                            'advisor' => 'Betreuer/in: Mag. Gerhild Ungeringer-Kron',
                            'class' => '8B',
                            'date_context' => 'Februar 2024',
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
                    'preview_text' => 'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich',
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

    expect($titlePage)->toBeArray()
        ->and(data_get($titlePage, 'title_page.title'))->toBe('Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich')
        ->and(data_get($titlePage, 'title_page.advisor'))->toBe('Mag. Gerhild Ungeringer-Kron')
        ->and(data_get($titlePage, 'title_page.school'))->toBe('Christian-Doppler-Gymnasium')
        ->and(data_get($titlePage, 'title_page.school_address'))->toBe('Franz-Josef-Kai 41')
        ->and(data_get($titlePage, 'title_page.school_city'))->toBe('5020 Salzburg')
        ->and(data_get($titlePage, 'title_page.school_full'))->toBe('Christian-Doppler-Gymnasium, Franz-Josef-Kai 41, 5020 Salzburg');
});

test('strips a trailing location-prefixed date from precomputed title page titles', function () {
    $builder = app(AbaExtractionResultBuilder::class);

    $result = $builder->build(
        ['version' => '2026-03-16', 'domain' => 'ahs-aba', 'scope' => []],
        [
            'document' => [
                'source_original_name' => 'test-main.docx',
                'page_image_counts' => [],
            ],
            'title_page_processing' => [
                'ui_model' => [
                    'preview_title' => 'Der Einfluss ionisierender Strahlung auf den menschlichen Körper Salzburg, 24.02.2023',
                    'preview_author' => 'Marx Azad',
                    'preview_advisor' => 'Betreuerin: Mag. Gerhild Ungeringer-Kron',
                    'preview_class' => '8C',
                    'preview_date' => '24.02.2023',
                    'additional_properties' => [],
                    'preview_notes' => [],
                ],
                'logo' => [
                    'logo_detected_count' => 0,
                ],
                'logos' => [],
            ],
            'sections' => [
                [
                    'section_key' => 'title-page',
                    'section_type' => 'title_page',
                    'section_title' => 'Titelseite',
                    'extracted_text' => implode("\n", [
                        'Christian-Doppler-Gymnasium',
                        'Franz-Josef Kai 41',
                        '5020 Salzburg',
                        '',
                        'Der Einfluss ionisierender Strahlung auf den menschlichen Körper',
                        '',
                        'Betreuerin: Mag. Gerhild Ungeringer-Kron',
                        '',
                        'Eingereicht von: Marx Azad',
                        'Klasse: 8C',
                        '',
                        'Salzburg, 24.02.2023',
                    ]),
                    'start_page' => 1,
                    'end_page' => 1,
                    'metadata' => [
                        'title_page_details' => [
                            'title' => 'Der Einfluss ionisierender Strahlung auf den menschlichen Körper Salzburg, 24.02.2023',
                            'submitter' => 'Marx Azad',
                            'advisor' => 'Betreuerin: Mag. Gerhild Ungeringer-Kron',
                            'class' => '8C',
                            'date_context' => '24.02.2023',
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
                    'preview_text' => 'Der Einfluss ionisierender Strahlung auf den menschlichen Körper',
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

    expect($titlePage)->toBeArray()
        ->and(data_get($titlePage, 'title_page.title'))->toBe('Der Einfluss ionisierender Strahlung auf den menschlichen Körper')
        ->and(data_get($titlePage, 'title_page.subtitle'))->toBeNull()
        ->and(data_get($titlePage, 'title_page.advisor'))->toBe('Mag. Gerhild Ungeringer-Kron')
        ->and(data_get($titlePage, 'title_page.author'))->toBe('Marx Azad');
});

test('continues collecting school address lines after an inline school line with trailing city text', function () {
    $builder = app(AbaExtractionResultBuilder::class);

    $result = $builder->build(
        ['version' => '2026-03-16', 'domain' => 'ahs-aba', 'scope' => []],
        [
            'document' => [
                'source_original_name' => 'test-main.docx',
                'page_image_counts' => [],
            ],
            'title_page_processing' => [
                'ui_model' => [
                    'preview_title' => 'Behandlungsmethoden bei Neurodermitis',
                    'preview_author' => 'Anh Vu Duy',
                    'preview_advisor' => 'Betreuer: Mag. Ungeringer-Kron Gerhild',
                    'preview_class' => '8C',
                    'preview_date' => null,
                    'additional_properties' => [],
                    'preview_notes' => [],
                ],
                'logo' => [
                    'logo_detected_count' => 0,
                ],
                'logos' => [],
            ],
            'sections' => [
                [
                    'section_key' => 'title-page',
                    'section_type' => 'title_page',
                    'section_title' => 'Titelseite',
                    'extracted_text' => implode("\n", [
                        'Christian-Doppler-Gymnasium Salzburg',
                        'Franz-Josef Kai 41',
                        '5020 Salzburg',
                        '',
                        'Behandlungsmethoden bei Neurodermitis',
                        '',
                        'Betreuer: Mag. Ungeringer-Kron Gerhild',
                        'Eingereicht von: Anh Vu Duy',
                        'Klasse: 8C',
                        '',
                        'Salzburg, Abgabedatum',
                    ]),
                    'start_page' => 1,
                    'end_page' => 1,
                    'metadata' => [
                        'title_page_details' => [
                            'title' => 'Behandlungsmethoden bei Neurodermitis',
                            'submitter' => 'Anh Vu Duy',
                            'advisor' => 'Betreuer: Mag. Ungeringer-Kron Gerhild',
                            'class' => '8C',
                            'date_context' => null,
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
                    'preview_text' => 'Behandlungsmethoden bei Neurodermitis',
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

    expect($titlePage)->toBeArray()
        ->and(data_get($titlePage, 'title_page.school'))->toBe('Christian-Doppler-Gymnasium')
        ->and(data_get($titlePage, 'title_page.school_address'))->toBe('Franz-Josef Kai 41')
        ->and(data_get($titlePage, 'title_page.school_city'))->toBe('5020 Salzburg')
        ->and(data_get($titlePage, 'title_page.school_full'))->toBe('Christian-Doppler-Gymnasium, Franz-Josef Kai 41, 5020 Salzburg');
});

test('prefers the real title over school and placeholder date lines on the title page', function () {
    $builder = app(AbaExtractionResultBuilder::class);

    $result = $builder->build(
        ['version' => '2026-03-16', 'domain' => 'ahs-aba', 'scope' => []],
        [
            'document' => [
                'source_original_name' => 'test-main.docx',
                'page_image_counts' => [],
            ],
            'title_page_processing' => [
                'ui_model' => [
                    'preview_title' => 'Salzburg, Abgabedatum',
                    'preview_author' => 'Anh Vu Duy',
                    'preview_advisor' => 'Betreuer: Mag. Ungeringer-Kron Gerhild',
                    'preview_class' => '8C',
                    'preview_date' => null,
                    'additional_properties' => [],
                    'preview_notes' => [],
                ],
                'logo' => [
                    'logo_detected_count' => 0,
                ],
                'logos' => [],
            ],
            'sections' => [
                [
                    'section_key' => 'title-page',
                    'section_type' => 'title_page',
                    'section_title' => 'Titelseite',
                    'extracted_text' => implode("\n", [
                        'Christian-Doppler-Gymnasium Salzburg',
                        'Franz-Josef Kai 41',
                        '5020 Salzburg',
                        '',
                        '',
                        '',
                        'Behandlungsmethoden bei Neurodermitis',
                        '',
                        '',
                        'Betreuer: Mag. Ungeringer-Kron Gerhild',
                        '',
                        'Eingereicht von: Anh Vu Duy',
                        'Klasse: 8C',
                        '',
                        'Salzburg, Abgabedatum',
                    ]),
                    'start_page' => 1,
                    'end_page' => 1,
                    'metadata' => [
                        'title_page_details' => [
                            'title' => 'Salzburg, Abgabedatum',
                            'submitter' => 'Anh Vu Duy',
                            'advisor' => 'Betreuer: Mag. Ungeringer-Kron Gerhild',
                            'class' => '8C',
                            'date_context' => null,
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
                    'preview_text' => 'Behandlungsmethoden bei Neurodermitis',
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

    expect($titlePage)->toBeArray()
        ->and(data_get($titlePage, 'title_page.title'))->toBe('Behandlungsmethoden bei Neurodermitis')
        ->and(data_get($titlePage, 'title_page.subtitle'))->toBeNull()
        ->and(data_get($titlePage, 'title_page.author'))->toBe('Anh Vu Duy')
        ->and(data_get($titlePage, 'title_page.advisor'))->toBe('Mag. Ungeringer-Kron Gerhild');
});

test('strips a trailing toc entry from precomputed title page titles', function () {
    $builder = app(AbaExtractionResultBuilder::class);

    $result = $builder->build(
        ['version' => '2026-03-16', 'domain' => 'ahs-aba', 'scope' => []],
        [
            'document' => [
                'source_original_name' => 'test-main.docx',
                'page_image_counts' => [],
            ],
            'title_page_processing' => [
                'ui_model' => [
                    'preview_title' => 'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich 1 Einleitung 5',
                    'preview_author' => 'Hanna Danninger',
                    'preview_advisor' => 'Betreuer/in: Mag. Gerhild Ungeringer-Kron',
                    'preview_class' => '8B',
                    'preview_date' => 'Februar 2024',
                    'additional_properties' => [],
                    'preview_notes' => [],
                ],
                'logo' => [
                    'logo_detected_count' => 0,
                ],
                'logos' => [],
            ],
            'sections' => [
                [
                    'section_key' => 'title-page',
                    'section_type' => 'title_page',
                    'section_title' => 'Titelseite',
                    'extracted_text' => implode("\n", [
                        'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich',
                        '',
                        'Vorwissenschaftliche Arbeit verfasst von',
                        'Hanna Danninger',
                        'Klasse 8B',
                        'Betreuer/in: Mag. Gerhild Ungeringer-Kron',
                        '',
                        'Februar 2024',
                        'Christian-Doppler-Gymnasium',
                        'Franz-Josef-Kai 41',
                        '5020 Salzburg',
                        '',
                        'Inhaltsverzeichnis',
                        '1 Einleitung 5',
                    ]),
                    'start_page' => 1,
                    'end_page' => 1,
                    'metadata' => [
                        'title_page_details' => [
                            'title' => 'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich 1 Einleitung 5',
                            'submitter' => 'Hanna Danninger',
                            'advisor' => 'Betreuer/in: Mag. Gerhild Ungeringer-Kron',
                            'class' => '8B',
                            'date_context' => 'Februar 2024',
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
                    'preview_text' => 'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich',
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

    expect($titlePage)->toBeArray()
        ->and(data_get($titlePage, 'title_page.title'))->toBe('Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich')
        ->and(data_get($titlePage, 'title_page.author'))->toBe('Hanna Danninger')
        ->and(data_get($titlePage, 'title_page.class'))->toBe('8B')
        ->and(data_get($titlePage, 'title_page.advisor'))->toBe('Mag. Gerhild Ungeringer-Kron');
});

test('strips a trailing toc heading suffix from precomputed title page titles', function () {
    $builder = app(AbaExtractionResultBuilder::class);

    $result = $builder->build(
        ['version' => '2026-03-16', 'domain' => 'ahs-aba', 'scope' => []],
        [
            'document' => [
                'source_original_name' => 'test-main.docx',
                'page_image_counts' => [],
            ],
            'title_page_processing' => [
                'ui_model' => [
                    'preview_title' => 'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich - Einleitung',
                    'preview_author' => 'Hanna Danninger',
                    'preview_advisor' => 'Betreuer/in: Mag. Gerhild Ungeringer-Kron',
                    'preview_class' => '8B',
                    'preview_date' => 'Februar 2024',
                    'additional_properties' => [],
                    'preview_notes' => [],
                ],
                'logo' => [
                    'logo_detected_count' => 0,
                ],
                'logos' => [],
            ],
            'sections' => [
                [
                    'section_key' => 'title-page',
                    'section_type' => 'title_page',
                    'section_title' => 'Titelseite',
                    'extracted_text' => implode("\n", [
                        'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich',
                        '',
                        'Vorwissenschaftliche Arbeit verfasst von',
                        'Hanna Danninger',
                        'Klasse 8B',
                        'Betreuer/in: Mag. Gerhild Ungeringer-Kron',
                        '',
                        'Februar 2024',
                    ]),
                    'start_page' => 1,
                    'end_page' => 1,
                    'metadata' => [
                        'title_page_details' => [
                            'title' => 'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich - Einleitung',
                            'submitter' => 'Hanna Danninger',
                            'advisor' => 'Betreuer/in: Mag. Gerhild Ungeringer-Kron',
                            'class' => '8B',
                            'date_context' => 'Februar 2024',
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
                    'preview_text' => 'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich',
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

    expect($titlePage)->toBeArray()
        ->and(data_get($titlePage, 'title_page.title'))->toBe('Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich')
        ->and(data_get($titlePage, 'title_page.author'))->toBe('Hanna Danninger')
        ->and(data_get($titlePage, 'title_page.class'))->toBe('8B')
        ->and(data_get($titlePage, 'title_page.advisor'))->toBe('Mag. Gerhild Ungeringer-Kron');
});

test('rejects a subtitle that only contains a structure heading with leading punctuation', function () {
    $builder = app(AbaExtractionResultBuilder::class);

    $result = $builder->build(
        ['version' => '2026-03-16', 'domain' => 'ahs-aba', 'scope' => []],
        [
            'document' => [
                'source_original_name' => 'test-main.docx',
                'page_image_counts' => [],
            ],
            'title_page_processing' => [
                'ui_model' => [
                    'preview_title' => 'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich',
                    'preview_subtitle' => '- Einleitung',
                    'preview_author' => 'Hanna Danninger',
                    'preview_advisor' => 'Betreuer/in: Mag. Gerhild Ungeringer-Kron',
                    'preview_class' => '8B',
                    'preview_date' => 'Februar 2024',
                    'additional_properties' => [],
                    'preview_notes' => [],
                ],
                'logo' => [
                    'logo_detected_count' => 0,
                ],
                'logos' => [],
            ],
            'sections' => [
                [
                    'section_key' => 'title-page',
                    'section_type' => 'title_page',
                    'section_title' => 'Titelseite',
                    'extracted_text' => implode("\n", [
                        'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich',
                        '',
                        'Vorwissenschaftliche Arbeit verfasst von',
                        'Hanna Danninger',
                        'Klasse 8B',
                        'Betreuer/in: Mag. Gerhild Ungeringer-Kron',
                        '',
                        'Februar 2024',
                    ]),
                    'start_page' => 1,
                    'end_page' => 1,
                    'metadata' => [
                        'title_page_details' => [
                            'title' => 'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich',
                            'subtitle' => '- Einleitung',
                            'submitter' => 'Hanna Danninger',
                            'advisor' => 'Betreuer/in: Mag. Gerhild Ungeringer-Kron',
                            'class' => '8B',
                            'date_context' => 'Februar 2024',
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
                    'preview_text' => 'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich',
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

    expect($titlePage)->toBeArray()
        ->and(data_get($titlePage, 'title_page.title'))->toBe('Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich')
        ->and(data_get($titlePage, 'title_page.subtitle'))->toBeNull();
});

test('rejects a subtitle that is actually flowing paragraph text', function () {
    $builder = app(AbaExtractionResultBuilder::class);

    $result = $builder->build(
        ['version' => '2026-03-16', 'domain' => 'ahs-aba', 'scope' => []],
        [
            'document' => [
                'source_original_name' => 'test-main.docx',
                'page_image_counts' => [],
            ],
            'title_page_processing' => [
                'ui_model' => [
                    'preview_title' => 'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich',
                    'preview_subtitle' => 'Zuletzt gilt mein großer Dank meiner Familie, die mich durch ihre uneingeschränkte Motivation während der Entstehung der VWA unterstützt hat.',
                    'preview_author' => 'Hanna Danninger',
                    'preview_advisor' => 'Betreuer/in: Mag. Gerhild Ungeringer-Kron',
                    'preview_class' => '8B',
                    'preview_date' => 'Februar 2024',
                    'additional_properties' => [],
                    'preview_notes' => [],
                ],
                'logo' => [
                    'logo_detected_count' => 0,
                ],
                'logos' => [],
            ],
            'sections' => [
                [
                    'section_key' => 'title-page',
                    'section_type' => 'title_page',
                    'section_title' => 'Titelseite',
                    'extracted_text' => implode("\n", [
                        'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich',
                        '',
                        'Vorwissenschaftliche Arbeit verfasst von',
                        'Hanna Danninger',
                        'Klasse 8B',
                        'Betreuer/in: Mag. Gerhild Ungeringer-Kron',
                        '',
                        'Februar 2024',
                        '',
                        'Zuletzt gilt mein großer Dank meiner Familie, die mich durch ihre uneingeschränkte Motivation während der Entstehung der VWA unterstützt hat.',
                    ]),
                    'start_page' => 1,
                    'end_page' => 1,
                    'metadata' => [
                        'title_page_details' => [
                            'title' => 'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich',
                            'subtitle' => 'Zuletzt gilt mein großer Dank meiner Familie, die mich durch ihre uneingeschränkte Motivation während der Entstehung der VWA unterstützt hat.',
                            'submitter' => 'Hanna Danninger',
                            'advisor' => 'Betreuer/in: Mag. Gerhild Ungeringer-Kron',
                            'class' => '8B',
                            'date_context' => 'Februar 2024',
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
                    'preview_text' => 'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich',
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

    expect($titlePage)->toBeArray()
        ->and(data_get($titlePage, 'title_page.title'))->toBe('Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich')
        ->and(data_get($titlePage, 'title_page.subtitle'))->toBeNull();
});
