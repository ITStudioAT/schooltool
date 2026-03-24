<?php

use Tests\TestCase;

uses(TestCase::class);

it('does not use literal unicode escape sequences in restaurant vue template text', function () {
    $files = glob(resource_path('js/pages/admin/restaurant/**/*.vue'));
    $files[] = resource_path('js/pages/admin/restaurant/Restaurant.vue');
    $files = array_values(array_unique(array_filter($files, fn ($path) => is_string($path) && file_exists($path))));

    $violations = [];

    foreach ($files as $file) {
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
