<?php

$defaultBinary = PHP_OS_FAMILY === 'Windows' ? 'soffice.exe' : 'soffice';

return [
    'max_render_bytes' => (int) env('MATERIALS_PREVIEW_MAX_RENDER_BYTES', 31_457_280),

    'libreoffice' => [
        'enabled' => (bool) env('MATERIALS_PREVIEW_LIBREOFFICE_ENABLED', true),
        'binary' => (string) env('MATERIALS_PREVIEW_LIBREOFFICE_BINARY', $defaultBinary),
        'timeout_seconds' => (int) env('MATERIALS_PREVIEW_LIBREOFFICE_TIMEOUT_SECONDS', 60),
    ],
];
