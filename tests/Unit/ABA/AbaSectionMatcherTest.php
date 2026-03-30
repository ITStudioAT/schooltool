<?php

use App\Services\AbaSectionMatcher;
use App\Services\AbaSectionRuleProvider;
use Tests\TestCase;

uses(TestCase::class);

test('matches extracted sections to aba rules and detects missing required areas', function () {
    $rules = app(AbaSectionRuleProvider::class)->sections();
    $matcher = app(AbaSectionMatcher::class);

    $result = $matcher->match($rules, [
        [
            'section_key' => 'section-title',
            'section_type' => 'title_page',
            'section_title' => 'Titelblatt',
            'extracted_text' => 'Titel der Arbeit',
            'metadata' => [],
        ],
        [
            'section_key' => 'section-abstract',
            'section_type' => 'abstract',
            'section_title' => 'Zusammenfassung',
            'extracted_text' => 'Kurzfassung der Arbeit.',
            'metadata' => ['abstract_language' => 'de'],
        ],
        [
            'section_key' => 'section-introduction',
            'section_type' => 'chapter',
            'section_title' => 'Einleitung',
            'extracted_text' => 'Einleitungstext',
            'metadata' => [],
        ],
        [
            'section_key' => 'section-main',
            'section_type' => 'chapter',
            'section_title' => 'Methodik',
            'extracted_text' => 'Ausarbeitung und Ergebnisse.',
            'metadata' => [],
        ],
        [
            'section_key' => 'section-conclusion',
            'section_type' => 'chapter',
            'section_title' => 'Fazit',
            'extracted_text' => 'Schlussfolgerungen.',
            'metadata' => [],
        ],
        [
            'section_key' => 'section-bibliography',
            'section_type' => 'bibliography',
            'section_title' => 'Literaturverzeichnis',
            'extracted_text' => 'Quelle A',
            'metadata' => [],
        ],
        [
            'section_key' => 'section-declaration',
            'section_type' => 'consent_declaration',
            'section_title' => 'Eigenständigkeitserklärung',
            'extracted_text' => 'Hiermit erkläre ich...',
            'metadata' => [],
        ],
    ]);

    $matchedSections = collect($result['sections'] ?? [])->keyBy('key');

    expect($matchedSections->get('title_page')['found'] ?? null)->toBeTrue()
        ->and($matchedSections->get('abstract_de')['found'] ?? null)->toBeTrue()
        ->and($matchedSections->get('introduction')['found'] ?? null)->toBeTrue()
        ->and($matchedSections->get('main_body')['found'] ?? null)->toBeTrue()
        ->and($matchedSections->get('conclusion')['found'] ?? null)->toBeTrue()
        ->and($matchedSections->get('bibliography')['found'] ?? null)->toBeTrue()
        ->and($matchedSections->get('consent_declaration')['found'] ?? null)->toBeTrue()
        ->and($result['missing_required_section_keys'])->toContain('table_of_contents')
        ->and($result['unmatched_blocks_count'])->toBe(0);
});
