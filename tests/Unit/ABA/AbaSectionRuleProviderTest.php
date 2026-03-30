<?php

use App\Services\AbaSectionRuleProvider;
use Tests\TestCase;

uses(TestCase::class);

test('normalizes aba document rules from config as ordered section definitions', function () {
    $provider = app(AbaSectionRuleProvider::class);

    $definition = $provider->definition();
    $sections = collect($definition['sections'] ?? [])->keyBy('key');

    expect($definition['version'])->toBe(config('aba_document_rules.version'))
        ->and($definition['domain'])->toBe(config('aba_document_rules.domain'))
        ->and($sections->get('title_page')['required'] ?? null)->toBeTrue()
        ->and($sections->get('abstract_en')['required'] ?? null)->toBeFalse()
        ->and($sections->get('conclusion')['maps_to_section_type'] ?? null)->toBe('chapter')
        ->and($sections->get('bibliography')['all_heading_options'] ?? [])
        ->toContain('Literaturverzeichnis')
        ->toContain('References')
        ->and($provider->requiredSectionKeys())
        ->toContain('title_page')
        ->toContain('bibliography')
        ->not->toContain('appendix');
});
