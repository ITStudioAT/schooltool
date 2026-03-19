<?php

use App\Services\AbaTitlePageProcessorService;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

test('processes source extraction and semantic normalization for title page metadata', function () {
    $service = app(AbaTitlePageProcessorService::class);

    $titlePageDetails = [
        'title' => 'Die Rolle der Fotografie in sozialen Medien',
        'subtitle' => 'Ästhetik, Technologie und gesellschaftliche Auswirkungen',
        'submitter' => 'Sandra Banu',
        'advisor' => 'Dipl.-Ing. Günther Kron',
        'class' => '8M',
        'date' => null,
        'school_year' => '2025/26',
    ];

    $blocks = [
        [
            'type' => 'paragraph',
            'order' => 1,
            'plain_text' => 'Betreuer*in: Dipl.-Ing. Günther Kron',
            'document_zone' => ['zone' => 'title_page'],
        ],
        [
            'type' => 'paragraph',
            'order' => 2,
            'plain_text' => 'Datum: --',
            'document_zone' => ['zone' => 'title_page'],
        ],
    ];

    $result = $service->process($titlePageDetails, $blocks);

    expect($result['source_extraction']['date'] ?? null)->toBe('--')
        ->and($result['source_extraction']['school_year'] ?? null)->toBe('2025/26')
        ->and($result['source_extraction']['advisor_label_original'] ?? null)->toBe('Betreuer*in')
        ->and($result['normalized_output']['date'] ?? null)->toBe('2025/26')
        ->and($result['normalized_output']['school_year'] ?? null)->toBeNull()
        ->and($result['source_extraction']['additional_properties'] ?? null)->toBeArray()->toHaveCount(0)
        ->and($result['normalized_output']['additional_properties'] ?? null)->toBeArray()->toHaveCount(0)
        ->and($result['pandoc_metadata']['Datum'] ?? null)->toBe('2025/26')
        ->and($result['logo']['logo_detected'] ?? null)->toBeFalse()
        ->and($result['logos'] ?? null)->toBeArray()->toHaveCount(0)
        ->and($result['logo']['logo_ui_displayable'] ?? null)->toBeFalse()
        ->and($result['logo']['logo_count'] ?? null)->toBe(0)
        ->and($result['ui_model']['show_logo'] ?? null)->toBeFalse()
        ->and($result['ui_model']['logo_assets'] ?? null)->toBeArray()->toHaveCount(0)
        ->and($result['ui_model']['additional_properties'] ?? null)->toBeArray()->toHaveCount(0)
        ->and($result['ui_model']['preview_ready'] ?? null)->toBeTrue()
        ->and($result['test_report']['overall_status'] ?? null)->toBe('pass');
});

test('extracts additional title page properties as structured label-value pairs', function () {
    $service = app(AbaTitlePageProcessorService::class);

    $titlePageDetails = [
        'title' => 'Die Rolle der Fotografie in sozialen Medien',
        'subtitle' => 'Ästhetik, Technologie und gesellschaftliche Auswirkungen',
        'submitter' => 'Sandra Banu',
        'advisor' => 'Dipl.-Ing. Günther Kron',
        'class' => '8M',
        'date' => '2025/26',
        'school_year' => null,
        'school' => 'BORG Musterstadt',
    ];

    $blocks = [
        [
            'type' => 'paragraph',
            'order' => 1,
            'plain_text' => 'Fach: Medieninformatik',
            'document_zone' => ['zone' => 'title_page'],
        ],
        [
            'type' => 'paragraph',
            'order' => 2,
            'plain_text' => 'Ort: Wien',
            'document_zone' => ['zone' => 'title_page'],
        ],
        [
            'type' => 'paragraph',
            'order' => 3,
            'plain_text' => 'Titel: Die Rolle der Fotografie in sozialen Medien',
            'document_zone' => ['zone' => 'title_page'],
        ],
    ];

    $result = $service->process($titlePageDetails, $blocks);
    $sourceAdditional = is_array($result['source_extraction']['additional_properties'] ?? null)
        ? $result['source_extraction']['additional_properties']
        : [];
    $normalizedAdditional = is_array($result['normalized_output']['additional_properties'] ?? null)
        ? $result['normalized_output']['additional_properties']
        : [];
    $uiAdditional = is_array($result['ui_model']['additional_properties'] ?? null)
        ? $result['ui_model']['additional_properties']
        : [];
    $labels = array_values(array_map(static fn (array $property): string => (string) ($property['label'] ?? ''), $normalizedAdditional));
    $checks = collect($result['test_report']['checks'] ?? [])->keyBy('name');

    expect($sourceAdditional)->toHaveCount(3)
        ->and($normalizedAdditional)->toHaveCount(3)
        ->and($uiAdditional)->toHaveCount(3)
        ->and($labels)->toContain('Fach')
        ->and($labels)->toContain('Ort')
        ->and($labels)->toContain('Schule')
        ->and($labels)->not->toContain('Titel')
        ->and($checks['additional_properties_preserved']['status'] ?? null)->toBe('pass')
        ->and($result['test_report']['overall_status'] ?? null)->toBe('pass');
});

test('keeps additional title page properties in stable document order without duplicating standard fields', function () {
    $service = app(AbaTitlePageProcessorService::class);

    $titlePageDetails = [
        'title' => 'Die Rolle der Fotografie in sozialen Medien',
        'subtitle' => 'Ästhetik, Technologie und gesellschaftliche Auswirkungen',
        'submitter' => 'Sandra Banu',
        'advisor' => 'Dipl.-Ing. Günther Kron',
        'class' => '8M',
        'date' => '2025/26',
        'school_year' => null,
    ];

    $blocks = [
        [
            'type' => 'paragraph',
            'order' => 8,
            'plain_text' => 'Version: 1.2',
            'document_zone' => ['zone' => 'title_page'],
        ],
        [
            'type' => 'paragraph',
            'order' => 4,
            'plain_text' => 'Prüfungsgebiet: Medienanalyse',
            'document_zone' => ['zone' => 'title_page'],
        ],
        [
            'type' => 'paragraph',
            'order' => 6,
            'plain_text' => 'Ort: Wien',
            'document_zone' => ['zone' => 'title_page'],
        ],
        [
            'type' => 'paragraph',
            'order' => 7,
            'plain_text' => 'Datum: 2025/26',
            'document_zone' => ['zone' => 'title_page'],
        ],
    ];

    $result = $service->process($titlePageDetails, $blocks);
    $normalizedAdditional = is_array($result['normalized_output']['additional_properties'] ?? null)
        ? $result['normalized_output']['additional_properties']
        : [];
    $labels = array_values(array_map(static fn (array $property): string => (string) ($property['label'] ?? ''), $normalizedAdditional));
    $orders = array_values(array_map(static fn (array $property): ?int => $property['order'] ?? null, $normalizedAdditional));

    expect($labels)->toBe(['Prüfungsgebiet', 'Ort', 'Version'])
        ->and($orders)->toBe([4, 6, 8])
        ->and($labels)->not->toContain('Datum');
});

test('extracts title page logo asset and enables ui logo rendering', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension is required for this test.');
    }

    Storage::fake('local');

    $service = app(AbaTitlePageProcessorService::class);

    $tempPath = tempnam(sys_get_temp_dir(), 'aba_logo_');
    expect($tempPath)->toBeString()->not->toBe('');

    $docxPath = $tempPath.'.docx';
    @rename($tempPath, $docxPath);

    $zip = new ZipArchive;
    expect($zip->open($docxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();
    $zip->addFromString('word/media/image1.png', 'not-a-real-png-but-valid-test-binary');
    $zip->close();

    try {
        $titlePageDetails = [
            'title' => 'Die Rolle der Fotografie in sozialen Medien',
            'subtitle' => 'Ästhetik, Technologie und gesellschaftliche Auswirkungen',
            'submitter' => 'Sandra Banu',
            'advisor' => 'Dipl.-Ing. Günther Kron',
            'class' => '8M',
            'date' => 'März 2026',
            'school_year' => null,
        ];

        $blocks = [
            [
                'type' => 'image',
                'order' => 1,
                'document_zone' => ['zone' => 'title_page'],
                'image' => [
                    'target' => 'media/image1.png',
                    'title' => null,
                    'alt_text' => 'Schullogo',
                ],
                'anchor' => [
                    'bbox' => [
                        'x' => 0.1,
                        'y' => 0.1,
                        'width' => 0.25,
                        'height' => 0.2,
                    ],
                ],
            ],
        ];

        $result = $service->process($titlePageDetails, $blocks, [
            'source_docx_path' => $docxPath,
            'logo_asset_disk' => 'local',
            'logo_asset_base_dir' => 'aba/titlepage-assets-test',
        ]);

        $assetPath = $result['logo']['logo_asset_path'] ?? null;
        $logos = is_array($result['logos'] ?? null) ? $result['logos'] : [];
        $firstLogo = $logos[0] ?? [];
        $uiAssets = is_array($result['ui_model']['logo_assets'] ?? null) ? $result['ui_model']['logo_assets'] : [];

        expect($result['logo']['logo_detected'] ?? null)->toBeTrue()
            ->and($result['logo']['logo_asset_available'] ?? null)->toBeTrue()
            ->and($result['logo']['logo_ui_displayable'] ?? null)->toBeTrue()
            ->and($logos)->toHaveCount(1)
            ->and($firstLogo['logo_asset_available'] ?? null)->toBeTrue()
            ->and($assetPath)->toBeString()->not->toBe('')
            ->and($result['logo']['logo_asset_filename'] ?? null)->toBeString()
            ->and($result['logo']['logo_asset_mime_type'] ?? null)->toBe('image/png')
            ->and($result['ui_model']['show_logo'] ?? null)->toBeTrue()
            ->and($result['ui_model']['logo_asset_path'] ?? null)->toBe($assetPath)
            ->and($uiAssets)->toHaveCount(1)
            ->and(($uiAssets[0]['logo_asset_path'] ?? null))->toBe($assetPath)
            ->and($result['test_report']['overall_status'] ?? null)->toBe('pass');

        Storage::disk('local')->assertExists($assetPath);
    } finally {
        @unlink($docxPath);
    }
});

test('keeps detected logo in fallback mode when no extractable docx source is provided', function () {
    $service = app(AbaTitlePageProcessorService::class);

    $titlePageDetails = [
        'title' => 'Die Rolle der Fotografie in sozialen Medien',
        'subtitle' => 'Ästhetik, Technologie und gesellschaftliche Auswirkungen',
        'submitter' => 'Sandra Banu',
        'advisor' => 'Dipl.-Ing. Günther Kron',
        'class' => '8M',
        'date' => 'März 2026',
        'school_year' => null,
    ];

    $blocks = [
        [
            'type' => 'image',
            'order' => 1,
            'document_zone' => ['zone' => 'title_page'],
            'image' => [
                'target' => 'media/image1.png',
                'title' => null,
                'alt_text' => 'Schullogo',
            ],
        ],
    ];

    $result = $service->process($titlePageDetails, $blocks);
    $logoChecks = collect($result['test_report']['checks'] ?? [])->keyBy('name');

    expect($result['logo']['logo_detected'] ?? null)->toBeTrue()
        ->and($result['logo']['logo_asset_available'] ?? null)->toBeFalse()
        ->and($result['logo']['logo_ui_displayable'] ?? null)->toBeFalse()
        ->and($result['logos'] ?? null)->toBeArray()->toHaveCount(1)
        ->and($result['ui_model']['show_logo'] ?? null)->toBeFalse()
        ->and($logoChecks['logo_asset_handling']['status'] ?? null)->toBe('warning')
        ->and($result['test_report']['overall_status'] ?? null)->toBe('warning');
});

test('extracts multiple title page logo assets and keeps order with unique file names', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension is required for this test.');
    }

    Storage::fake('local');

    $service = app(AbaTitlePageProcessorService::class);

    $tempPath = tempnam(sys_get_temp_dir(), 'aba_logo_multi_');
    expect($tempPath)->toBeString()->not->toBe('');

    $docxPath = $tempPath.'.docx';
    @rename($tempPath, $docxPath);

    $zip = new ZipArchive;
    expect($zip->open($docxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();
    $zip->addFromString('word/media/image1.png', 'image-one-binary');
    $zip->addFromString('word/media/image2.jpg', 'image-two-binary');
    $zip->close();

    try {
        $result = $service->process([
            'title' => 'Die Rolle der Fotografie in sozialen Medien',
            'subtitle' => 'Ästhetik, Technologie und gesellschaftliche Auswirkungen',
            'submitter' => 'Sandra Banu',
            'advisor' => 'Dipl.-Ing. Günther Kron',
            'class' => '8M',
            'date' => '2025/26',
            'school_year' => null,
        ], [
            [
                'type' => 'image',
                'order' => 1,
                'document_zone' => ['zone' => 'title_page'],
                'image' => ['target' => 'media/image1.png', 'alt_text' => 'Schullogo oben links'],
                'anchor' => ['bbox' => ['x' => 0.1, 'y' => 0.1, 'width' => 0.2, 'height' => 0.2]],
            ],
            [
                'type' => 'image',
                'order' => 2,
                'document_zone' => ['zone' => 'title_page'],
                'image' => ['target' => 'media/image2.jpg', 'alt_text' => 'Partnerlogo oben rechts'],
                'anchor' => ['bbox' => ['x' => 0.75, 'y' => 0.1, 'width' => 0.2, 'height' => 0.2]],
            ],
        ], [
            'source_docx_path' => $docxPath,
            'logo_asset_disk' => 'local',
            'logo_asset_base_dir' => 'aba/titlepage-assets-test',
        ]);

        $logos = is_array($result['logos'] ?? null) ? $result['logos'] : [];
        $uiAssets = is_array($result['ui_model']['logo_assets'] ?? null) ? $result['ui_model']['logo_assets'] : [];
        $paths = array_values(array_filter(array_map(static fn (array $logo): ?string => $logo['logo_asset_path'] ?? null, $logos)));
        $filenames = array_values(array_filter(array_map(static fn (array $logo): ?string => $logo['logo_asset_filename'] ?? null, $logos)));

        expect($logos)->toHaveCount(2)
            ->and($uiAssets)->toHaveCount(2)
            ->and($result['logo']['logo_count'] ?? null)->toBe(2)
            ->and($result['logo']['logo_asset_available_count'] ?? null)->toBe(2)
            ->and($result['logo']['logo_ui_displayable_count'] ?? null)->toBe(2)
            ->and($result['ui_model']['show_logo'] ?? null)->toBeTrue()
            ->and($paths)->toHaveCount(2)
            ->and(array_unique($paths))->toHaveCount(2)
            ->and($filenames)->toHaveCount(2)
            ->and(array_unique($filenames))->toHaveCount(2)
            ->and($result['test_report']['overall_status'] ?? null)->toBe('pass');

        foreach ($paths as $path) {
            Storage::disk('local')->assertExists($path);
        }
    } finally {
        @unlink($docxPath);
    }
});

test('distinguishes mixed logo asset availability per detected title page image', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive extension is required for this test.');
    }

    Storage::fake('local');

    $service = app(AbaTitlePageProcessorService::class);

    $tempPath = tempnam(sys_get_temp_dir(), 'aba_logo_mixed_');
    expect($tempPath)->toBeString()->not->toBe('');

    $docxPath = $tempPath.'.docx';
    @rename($tempPath, $docxPath);

    $zip = new ZipArchive;
    expect($zip->open($docxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();
    $zip->addFromString('word/media/image1.png', 'extractable-logo-binary');
    $zip->close();

    try {
        $result = $service->process([
            'title' => 'Titel',
            'subtitle' => 'Untertitel',
            'submitter' => 'Autor',
            'advisor' => 'Betreuer',
            'class' => '8M',
            'date' => '2025/26',
            'school_year' => null,
        ], [
            [
                'type' => 'image',
                'order' => 1,
                'document_zone' => ['zone' => 'title_page'],
                'image' => ['target' => 'media/image1.png', 'alt_text' => 'Schullogo'],
            ],
            [
                'type' => 'image',
                'order' => 2,
                'document_zone' => ['zone' => 'title_page'],
                'image' => ['target' => 'media/missing.png', 'alt_text' => 'Weiteres Logo'],
            ],
        ], [
            'source_docx_path' => $docxPath,
            'logo_asset_disk' => 'local',
            'logo_asset_base_dir' => 'aba/titlepage-assets-test',
        ]);

        $logos = is_array($result['logos'] ?? null) ? $result['logos'] : [];
        $checks = collect($result['test_report']['checks'] ?? [])->keyBy('name');

        expect($logos)->toHaveCount(2)
            ->and((bool) ($logos[0]['logo_asset_available'] ?? false))->toBeTrue()
            ->and((bool) ($logos[1]['logo_asset_available'] ?? false))->toBeFalse()
            ->and($result['logo']['logo_detected_count'] ?? null)->toBe(2)
            ->and($result['logo']['logo_asset_available_count'] ?? null)->toBe(1)
            ->and($result['logo']['logo_ui_displayable_count'] ?? null)->toBe(1)
            ->and($result['ui_model']['show_logo'] ?? null)->toBeTrue()
            ->and(($checks['logo_asset_handling']['status'] ?? null))->toBe('warning')
            ->and($result['test_report']['overall_status'] ?? null)->toBe('warning');
    } finally {
        @unlink($docxPath);
    }
});
