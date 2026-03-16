<?php

$defaultBinary = PHP_OS_FAMILY === 'Windows' ? 'pandoc.exe' : 'pandoc';

return [
    'enabled' => (bool) env('ABA_PANDOC_ENABLED', true),
    'binary' => (string) env('ABA_PANDOC_BINARY', $defaultBinary),
    'timeout_seconds' => (int) env('ABA_PANDOC_TIMEOUT_SECONDS', 30),
];
