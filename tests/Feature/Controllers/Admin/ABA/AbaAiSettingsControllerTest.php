<?php

test('aba api routes no longer expose the ai settings endpoints', function () {
    $content = file_get_contents(base_path('routes/api.php'));

    expect($content)
        ->not->toContain('/admin/aba/ai-settings')
        ->not->toContain('/admin/aba/seed-report')
        ->not->toContain('/admin/aba/seed-review');
});

test('aba frontend no longer ships the ai settings page', function () {
    expect(file_exists(resource_path('js/pages/admin/aba/AbaAiSettings.vue')))->toBeFalse();
});
