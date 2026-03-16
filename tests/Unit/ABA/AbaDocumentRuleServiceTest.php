<?php

use App\Services\AbaDocumentRuleService;

uses(Tests\TestCase::class);

test('document rule service exposes structured rule base summary', function () {
    $summary = app(AbaDocumentRuleService::class)->summary();

    expect($summary['domain'] ?? null)->toBe('ahs-aba')
        ->and($summary['scope']['school_type'] ?? null)->toBe('AHS')
        ->and($summary['assessment_classes']['verbindlich_pruefbar']['label'] ?? null)->toBe('verbindlich prüfbar')
        ->and($summary['structure_rules']['sections']['conclusion']['maps_to_section_type'] ?? null)->toBe('chapter')
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
