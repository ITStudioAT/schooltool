<?php

use Tests\TestCase;

uses(TestCase::class);

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
