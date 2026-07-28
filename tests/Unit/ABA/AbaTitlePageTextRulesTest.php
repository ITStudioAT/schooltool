<?php

use App\Services\AbaTitlePageTextRules;

it('normalizes stable title-page address and date signals', function () {
    $rules = new AbaTitlePageTextRules;

    expect($rules->normalizeWhitespace("  Eine\t Abschließende   Arbeit  "))
        ->toBe('Eine Abschließende Arbeit')
        ->and($rules->isAddressLine('Musterstraße 12'))
        ->toBeTrue()
        ->and($rules->isPostalCityLine('1010 Wien'))
        ->toBeTrue()
        ->and($rules->isAddressLine('Eine Überschrift ohne Anschrift'))
        ->toBeFalse()
        ->and($rules->isDatePlaceholder('Wien, Abgabedatum'))
        ->toBeTrue()
        ->and($rules->isDatePlaceholder('Schuljahr 2025/26'))
        ->toBeFalse();
});

it('removes only credible trailing dates from sufficiently descriptive titles', function () {
    $rules = new AbaTitlePageTextRules;
    $title = 'Die Rolle der Fotografie in sozialen Medien';

    expect($rules->stripTrailingDateSuffix($title.' Wien, 21.06.2026'))
        ->toBe($title)
        ->and($rules->stripTrailingDateSuffix($title.' September 2026'))
        ->toBe($title)
        ->and($rules->stripTrailingDateSuffix($title.' Abgabedatum'))
        ->toBe($title)
        ->and($rules->stripTrailingDateSuffix($title.' Abgabedatum', false))
        ->toBe($title.' Abgabedatum')
        ->and($rules->stripTrailingDateSuffix('Kurzer Titel 21.06.2026'))
        ->toBe('Kurzer Titel 21.06.2026');
});
