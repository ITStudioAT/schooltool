<?php

test('aba api routes no longer expose legacy analysis and document review endpoints', function () {
    $content = file_get_contents(base_path('routes/api.php'));

    expect($content)
        ->not->toContain('/admin/abas/{aba}/analysis')
        ->not->toContain('/admin/abas/{aba}/analysis/results')
        ->not->toContain('/admin/abas/{aba}/analysis/document-review')
        ->not->toContain('/admin/abas/{aba}/analysis/document-review/logo-asset');
});

test('aba frontend no longer ships the legacy analysis results page', function () {
    expect(file_exists(resource_path('js/pages/admin/aba/AbaAnalysisResults.vue')))->toBeFalse();
});

test('aba router no longer registers legacy analysis pages', function () {
    $content = file_get_contents(resource_path('routes/admin.js'));

    expect($content)
        ->not->toContain('/admin/aba/results/:abaId')
        ->not->toContain('AbaAnalysisResults.vue');
});
