<?php

test('aba api routes no longer expose pdf openai debug endpoints', function () {
    $content = file_get_contents(base_path('routes/api.php'));

    expect($content)
        ->not->toContain('/admin/aba/ai-settings/pdf-openai-debug/run');
});
