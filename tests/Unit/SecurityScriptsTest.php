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

it('does not commit Laravel application keys', function () {
    $basePath = dirname(__DIR__, 2);
    $process = new Process([
        'git',
        'ls-files',
        '-z',
    ], $basePath);
    $process->mustRun();

    $committedKeyLocations = [];

    foreach (array_filter(explode("\0", $process->getOutput())) as $relativePath) {
        $absolutePath = $basePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if (! is_file($absolutePath)) {
            continue;
        }

        $contents = file_get_contents($absolutePath);

        if ($contents === false || str_contains($contents, "\0")) {
            continue;
        }

        preg_match_all(
            '/^\s*APP_KEY\s*[:=]\s*["\']?(?:base64:)?[A-Za-z0-9+\/=]{20,}["\']?\s*$/m',
            $contents,
            $matches,
            PREG_OFFSET_CAPTURE,
        );

        foreach ($matches[0] as [$match, $offset]) {
            $lineNumber = substr_count(substr($contents, 0, $offset), "\n") + 1;
            $committedKeyLocations[] = "{$relativePath}:{$lineNumber}";
        }
    }

    expect($committedKeyLocations)->toBeEmpty();
});

it('generates isolated application keys for CI and E2E at runtime', function () {
    $basePath = dirname(__DIR__, 2);
    $gitignore = file_get_contents($basePath.'/.gitignore');
    $e2eEnvironmentExample = file_get_contents($basePath.'/.env.e2e.example');
    $package = file_get_contents($basePath.'/package.json');
    $workflow = file_get_contents($basePath.'/.github/workflows/ci.yml');

    expect($gitignore)
        ->toContain('/.env.e2e')
        ->and($e2eEnvironmentExample)
        ->toMatch('/^APP_KEY=\s*$/m')
        ->and($package)
        ->toContain('key:generate --env=e2e --force --no-interaction')
        ->and($workflow)
        ->toContain('APP_KEY=$(php artisan key:generate --show --no-ansi --no-interaction)')
        ->not->toMatch('/^\s*APP_KEY\s*:\s*["\']?(?:base64:)?[A-Za-z0-9+\/=]{20,}["\']?\s*$/m');
});
