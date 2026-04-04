<?php

use Tests\TestCase;

uses(TestCase::class);

function restaurantUiEncodingFiles(): array
{
    $files = array_merge(
        glob(resource_path('js/pages/admin/restaurant/**/*.vue')) ?: [],
        [
            resource_path('js/pages/admin/restaurant/Restaurant.vue'),
            resource_path('js/pages/homepage/index/Restaurant.vue'),
        ]
    );

    return array_values(array_unique(array_filter($files, fn ($path) => is_string($path) && file_exists($path))));
}

it('does not use literal unicode escape sequences in restaurant vue template text', function () {
    $violations = [];

    foreach (restaurantUiEncodingFiles() as $file) {
        $content = file_get_contents($file);
        if (! is_string($content)) {
            continue;
        }

        if (preg_match('/<template>(.*?)<\/template>/s', $content, $matches) !== 1) {
            continue;
        }

        $template = $matches[1];

        if (preg_match('/\\\\u00[0-9a-fA-F]{2}/', $template) === 1) {
            $violations[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
        }
    }

    expect($violations)->toBe([]);
});

it('does not contain suspicious mojibake in restaurant ui copy', function () {
    $patterns = [
        'ausw?hlen',
        'w?hlen',
        '?bersicht',
        'Bestellm?glichkeiten',
        'Speisepl?ne',
        'verf?gbar',
        '?ber',
        'geh?rt',
        'M?chten',
        'f?r',
        'Best?tigung',
        'n?chste',
        'pr?fen',
        'Sch?ler',
        'erg?nzen',
        'gepr?ft',
    ];

    $violations = [];

    foreach (restaurantUiEncodingFiles() as $file) {
        $content = file_get_contents($file);
        if (! is_string($content)) {
            continue;
        }

        $matches = array_values(array_filter($patterns, fn (string $pattern): bool => str_contains($content, $pattern)));

        if ($matches !== []) {
            $violations[str_replace(base_path().DIRECTORY_SEPARATOR, '', $file)] = $matches;
        }
    }

    expect($violations)->toBe([]);
});

it('keeps the encoding smoke test file intact', function () {
    $content = file_get_contents(base_path('encoding-smoke-test.txt'));

    expect($content)->toBe(
        "ä ö ü Ä Ö Ü ß\n".
        "€ – — „Hallo“ ‘Test’\n".
        "E-Mail-Adresse gehört zu einem Mittagskonto.\n".
        'Möchten Sie sich per Code oder per Passwort anmelden?'
    );
});
