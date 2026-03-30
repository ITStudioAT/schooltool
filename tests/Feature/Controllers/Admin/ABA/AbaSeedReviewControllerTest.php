<?php

test('aba api routes no longer expose seed report and review endpoints', function () {
    $content = file_get_contents(base_path('routes/api.php'));

    expect($content)
        ->not->toContain('/admin/aba/seed-report')
        ->not->toContain('/admin/aba/seed-review/proposals')
        ->not->toContain('/admin/aba/seed-review/diff/{filename}')
        ->not->toContain('/admin/aba/seed-review/content/{filename}')
        ->not->toContain('/admin/aba/seed-review/generate-replacement')
        ->not->toContain('/admin/aba/seed-review/apply');
});

test('aba frontend no longer ships seed report and review pages', function () {
    $routerContent = file_get_contents(resource_path('routes/admin.js'));

    expect($routerContent)
        ->not->toContain('/admin/aba/ai-settings/seed-report')
        ->not->toContain('AbaSeedReport.vue')
        ->not->toContain('AbaSeedReview.vue');

    expect(file_exists(resource_path('js/pages/admin/aba/AbaSeedReport.vue')))->toBeFalse();
    expect(file_exists(resource_path('js/pages/admin/aba/AbaSeedReview.vue')))->toBeFalse();
});
