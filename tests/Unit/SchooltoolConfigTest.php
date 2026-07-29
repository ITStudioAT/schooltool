<?php

use Tests\TestCase;

uses(TestCase::class);

it('does not profile the frequently polled health status endpoint', function () {
    expect(config('debugbar.except'))->toContain('api/admin/health/status');
});

it('provides the European allergen list with character and short description', function () {
    $allergens = config('schooltool.eu_allergens');

    expect($allergens)->toBeArray()->toHaveCount(14);

    $expectedAllergens = [
        'A' => 'Glutenhaltiges Getreide',
        'B' => 'Krebstiere',
        'C' => 'Eier',
        'D' => 'Fisch',
        'E' => 'Erdnüsse',
        'F' => 'Soja',
        'G' => 'Milch oder Laktose',
        'H' => 'Schalenfrüchte',
        'L' => 'Sellerie',
        'M' => 'Senf',
        'N' => 'Sesam',
        'O' => 'Sulfite',
        'P' => 'Lupinen',
        'R' => 'Weichtiere',
    ];

    $actualAllergens = collect($allergens)
        ->mapWithKeys(fn (array $allergen): array => [
            (string) ($allergen['character'] ?? '') => (string) ($allergen['short_description'] ?? ''),
        ])
        ->all();

    expect($actualAllergens)->toBe($expectedAllergens);
});
