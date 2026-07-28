<?php

use Symfony\Component\Process\Process;

echo "Running AdminNavigationService tests...\n\n";

require __DIR__.'/vendor/autoload.php';

$process = new Process([
    PHP_BINARY,
    'artisan',
    'test',
    '--filter=AdminNavigationServiceTest',
], __DIR__);
$process->setTimeout(null);
$process->run(static function (string $type, string $output): void {
    echo $output;
});

exit($process->getExitCode() ?? 1);
