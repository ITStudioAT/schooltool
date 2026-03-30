<?php

test('aba api routes no longer expose analysis run endpoints', function () {
    $content = file_get_contents(base_path('routes/api.php'));

    expect($content)
        ->not->toContain('/admin/abas/{aba}/analysis')
        ->not->toContain('/admin/abas/{aba}/analysis/results');
});

test('aba resources no longer expose latest analysis run metadata', function () {
    $content = file_get_contents(app_path('Http/Resources/Admin/ABA/AbaResource.php'));

    expect($content)
        ->not->toContain('latest_analysis_run')
        ->not->toContain('AbaAnalysisRunResource');
});

test('legacy analysis controller and resource are removed from the active code path', function () {
    expect(file_exists(app_path('Http/Controllers/Admin/ABA/AbaAnalysisRunController.php')))->toBeFalse();
    expect(file_exists(app_path('Http/Resources/Admin/ABA/AbaAnalysisRunResource.php')))->toBeFalse();
});
