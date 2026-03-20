<?php

use App\Services\AbaDocumentRuleService;
use Tests\TestCase;

uses(TestCase::class);

test('document rule service exposes structured rule base summary', function () {
    $summary = app(AbaDocumentRuleService::class)->summary();

    expect($summary['domain'] ?? null)->toBe('ahs-aba')
        ->and($summary['scope']['school_type'] ?? null)->toBe('AHS')
        ->and($summary['assessment_classes']['verbindlich_pruefbar']['label'] ?? null)->toBe('verbindlich prüfbar')
        ->and($summary['structure_rules']['sections']['conclusion']['maps_to_section_type'] ?? null)->toBe('chapter')
        ->and($summary['structure_rules']['sections']['abstract_en']['requirement'] ?? null)->toBe('optional')
        ->and($summary['structure_rules']['sections']['accompanying_log']['assessment_class'] ?? null)->toBe('schulspezifisch_offen');
});

test('document rule service resolves configured section variants', function () {
    $rules = app(AbaDocumentRuleService::class);

    expect($rules->resolveSectionTypeFromTitle('Literatur und Quellenverzeichnis'))->toBe('bibliography')
        ->and($rules->resolveSectionTypeFromTitle('Eidesstattliche Erklärung'))->toBe('consent_declaration')
        ->and($rules->resolveSectionTypeFromTitle('Inhaltsverzeichnis'))->toBe('table_of_contents');
});

test('document rule service marks conclusio as chapter heading and keyword', function () {
    $rules = app(AbaDocumentRuleService::class);

    expect($rules->isConfiguredChapterHeading('Conclusio'))->toBeTrue()
        ->and($rules->looksLikeSectionKeyword('Begleitprotokoll'))->toBeTrue();
});

test('document zone rule base is persisted and machine-readable', function () {
    $rules = app(AbaDocumentRuleService::class);
    $zoneRules = $rules->documentZoneRules();

    expect($zoneRules['zones']['titlepage']['requirement'] ?? null)->toBe('required')
        ->and($zoneRules['zones']['abstract_de']['requirement'] ?? null)->toBe('required')
        ->and($zoneRules['zones']['bibliography']['requirement'] ?? null)->toBe('required')
        ->and($zoneRules['zones']['declaration']['requirement'] ?? null)->toBe('required')
        ->and($zoneRules['zones']['abstract_en']['requirement'] ?? null)->toBe('optional')
        ->and($zoneRules['zones']['appendix']['requirement'] ?? null)->toBe('optional')
        ->and($zoneRules['zones']['figure_table_index']['requirement'] ?? null)->toBe('optional')
        ->and($zoneRules['zones']['abstract_en']['assessment_class'] ?? null)->toBe('plausibilisierbar')
        ->and($zoneRules['zones']['titlepage']['assessment_class'] ?? null)->toBe('verbindlich_pruefbar')
        ->and($zoneRules['zones']['titlepage']['heading_variants'] ?? [])->toContain('Titelblatt');
});

test('document zone sequence rules are available for later analyzers', function () {
    $rules = app(AbaDocumentRuleService::class);
    $sequenceRules = $rules->documentZoneSequenceRules();

    expect($sequenceRules)->not->toBeEmpty()
        ->and(collect($sequenceRules)->pluck('rule_key')->all())->toContain('titlepage_before_toc')
        ->and(collect($sequenceRules)->pluck('rule_key')->all())->toContain('declaration_at_end');
});
