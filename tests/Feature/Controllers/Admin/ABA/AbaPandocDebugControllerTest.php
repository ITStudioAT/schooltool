<?php

test('aba api routes no longer expose pandoc debug endpoints', function () {
    $content = file_get_contents(base_path('routes/api.php'));

    expect($content)
        ->not->toContain('/admin/aba/ai-settings/pandoc-debug/run');
});

test('aba frontend no longer ships pandoc debug views', function () {
    $routerContent = file_get_contents(resource_path('routes/admin.js'));

    expect($routerContent)
        ->not->toContain('/admin/aba/ai-settings/pandoc-debug')
        ->not->toContain('AbaPandocDebug.vue');

    expect(file_exists(resource_path('js/pages/admin/aba/AbaPandocDebug.vue')))->toBeFalse();
});
