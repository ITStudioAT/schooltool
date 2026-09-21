<?php

use Checkpoint\Checks\FilePermissionsCheck;
use Checkpoint\Checks\GitIgnoreCheck;
use Illuminate\Filesystem\Filesystem;
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

it('scans every commit in the CI range for Laravel application keys', function () {
    $basePath = dirname(__DIR__, 2);
    $script = file_get_contents($basePath.'/scripts/check-secret-history.php');
    $workflow = file_get_contents($basePath.'/.github/workflows/ci.yml');

    expect($script)
        ->toContain("'rev-list', '--reverse'")
        ->toContain("'show'")
        ->toContain('Values were intentionally not printed')
        ->and($workflow)
        ->toContain('fetch-depth: 0')
        ->toContain('check-secret-history.php --base="$base_sha"');

    $process = new Process([
        PHP_BINARY,
        'scripts/check-secret-history.php',
        '--base=HEAD',
    ], $basePath);
    $process->mustRun();

    expect($process->getOutput())->toContain('No Laravel APP_KEY was introduced');
});

it('cancels superseded CI workflows per event and branch revision with bounded job timeouts', function () {
    $workflow = file_get_contents(dirname(__DIR__, 2).'/.github/workflows/ci.yml');

    expect($workflow)
        ->toMatch('/push:\s+branches:\s+- main/')
        ->toContain('group: ${{ github.workflow }}-${{ github.event_name }}-${{ github.event.pull_request.number || github.ref }}')
        ->toContain('cancel-in-progress: true')
        ->toContain('timeout-minutes: 10')
        ->toContain('timeout-minutes: 15')
        ->toContain('timeout-minutes: 90')
        ->toContain('timeout-minutes: 45');
});

it('detects a Laravel application key that was removed in a later commit', function () {
    $basePath = dirname(__DIR__, 2);
    $filesystem = new Filesystem;
    $temporaryRepository = sys_get_temp_dir().DIRECTORY_SEPARATOR.'schooltool-secret-scan-'.bin2hex(random_bytes(8));
    $generatedTestKey = 'base64:'.base64_encode(random_bytes(32));

    $filesystem->makeDirectory($temporaryRepository);

    $runGit = function (array $arguments) use ($temporaryRepository): Process {
        $process = new Process(['git', ...$arguments], $temporaryRepository);
        $process->mustRun();

        return $process;
    };

    try {
        $runGit(['init']);
        $runGit(['config', 'user.email', 'security-test@example.invalid']);
        $runGit(['config', 'user.name', 'Security Test']);

        $filesystem->put($temporaryRepository.DIRECTORY_SEPARATOR.'README.md', "safe\n");
        $runGit(['add', 'README.md']);
        $runGit(['commit', '-m', 'Initial safe commit']);
        $baseCommit = trim($runGit(['rev-parse', 'HEAD'])->getOutput());

        $filesystem->put(
            $temporaryRepository.DIRECTORY_SEPARATOR.'.env',
            "APP_KEY={$generatedTestKey}\n",
        );
        $runGit(['add', '.env']);
        $runGit(['commit', '-m', 'Introduce key']);

        $filesystem->delete($temporaryRepository.DIRECTORY_SEPARATOR.'.env');
        $runGit(['add', '-A']);
        $runGit(['commit', '-m', 'Remove key']);

        $scan = new Process([
            PHP_BINARY,
            $basePath.'/scripts/check-secret-history.php',
            "--base={$baseCommit}",
        ], $temporaryRepository);
        $scan->run();

        expect($scan->getExitCode())->toBe(1)
            ->and($scan->getErrorOutput())
            ->toContain('Potential Laravel APP_KEY introduced in commit')
            ->not->toContain($generatedTestKey);
    } finally {
        if (is_dir($temporaryRepository)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($temporaryRepository, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($iterator as $item) {
                @chmod($item->getPathname(), $item->isDir() ? 0777 : 0666);
            }

            @chmod($temporaryRepository, 0777);
        }

        $filesystem->deleteDirectory($temporaryRepository);
    }
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
        ->toContain('if [[ "$base_sha" =~ ^0+$ ]]')
        ->toContain('git merge-base HEAD "origin/$DEFAULT_BRANCH"')
        ->toContain('check-changed-code-coverage.php --base="$base_sha"')
        ->not->toMatch('/^\s*APP_KEY\s*:\s*["\']?(?:base64:)?[A-Za-z0-9+\/=]{20,}["\']?\s*$/m');
});
