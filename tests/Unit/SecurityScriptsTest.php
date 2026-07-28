<?php

use Checkpoint\Checks\FilePermissionsCheck;
use Checkpoint\Checks\GitIgnoreCheck;
use Symfony\Component\Process\Process;

it('keeps the public opcache reset endpoint disabled', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/public/reset-opcache.php');

    expect($source)
        ->toContain('http_response_code(404);')
        ->not->toContain('$_GET')
        ->not->toContain('passthru(')
        ->not->toContain('YOUR_SECRET_KEY_HERE');
});

it('runs the local test helper without a shell', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/create_test_dir.php');

    expect($source)
        ->toContain('new Process([')
        ->toContain('PHP_BINARY')
        ->not->toContain('passthru(')
        ->not->toContain('shell_exec(');
});

it('uses Windows-safe checks for sensitive ignored files', function () {
    $basePath = dirname(__DIR__, 2);
    $checkpointConfig = require $basePath.'/config/checkpoint.php';
    $gitignore = file_get_contents($basePath.'/.gitignore');

    expect($checkpointConfig['checks'][GitIgnoreCheck::class])
        ->toBe(PHP_OS_FAMILY !== 'Windows')
        ->and($checkpointConfig['checks'][FilePermissionsCheck::class])
        ->toBe(PHP_OS_FAMILY !== 'Windows')
        ->and($gitignore)
        ->toContain('.env', '*.key', '*.pem', 'storage/logs');

    $process = new Process([
        'git',
        'ls-files',
        '--error-unmatch',
        '.env',
    ], $basePath);
    $process->run();

    expect($process->getExitCode())->not->toBe(0);
});
