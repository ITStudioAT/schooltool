<?php

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

require_once dirname(__DIR__, 2).'/scripts/frontend-release.php';

function deploymentProjectPath(string $relativePath = ''): string
{
    $projectDirectory = dirname(__DIR__, 2);

    return $relativePath === ''
        ? $projectDirectory
        : $projectDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
}

function runDeploymentScript(array $arguments): Process
{
    $process = new Process([PHP_BINARY, ...$arguments], deploymentProjectPath());
    $process->run();

    return $process;
}

function deploymentBashExecutable(): string
{
    $gitBash = 'C:\\Program Files\\Git\\bin\\bash.exe';

    return PHP_OS_FAMILY === 'Windows' && is_file($gitBash) ? $gitBash : 'bash';
}

function deploymentBashPath(string $path): string
{
    $normalizedPath = str_replace('\\', '/', $path);

    if (PHP_OS_FAMILY !== 'Windows'
        || preg_match('/^(?<drive>[A-Za-z]):(?<path>\/.*)$/', $normalizedPath, $matches) !== 1) {
        return $normalizedPath;
    }

    return '/'.strtolower($matches['drive']).$matches['path'];
}

function writeDeploymentExecutable(string $path, string $contents): void
{
    file_put_contents($path, str_replace(["\r\n", "\r"], "\n", $contents));
    chmod($path, 0777);
}

function createTerminalPullFixture(bool $useRealFlock = false): string
{
    $filesystem = new Filesystem;
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'schooltool-pdeploy-test-'.bin2hex(random_bytes(6));

    foreach (['bin', 'scripts', 'storage/framework'] as $relativePath) {
        $filesystem->ensureDirectoryExists($directory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
    }

    $filesystem->copy(
        deploymentProjectPath('scripts/pdeploy_cloudways.sh'),
        $directory.DIRECTORY_SEPARATOR.'scripts'.DIRECTORY_SEPARATOR.'pdeploy_cloudways.sh',
    );
    writeDeploymentExecutable($directory.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'git', <<<'BASH'
#!/usr/bin/bash
exit 1
BASH);
    if (! $useRealFlock) {
        writeDeploymentExecutable($directory.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'flock', <<<'BASH'
#!/usr/bin/bash
set -e

if [ "${1:-}" != --exclusive ] \
    || [ "${2:-}" != --nonblock ] \
    || [ "${3:-}" != --close ] \
    || [ "${4:-}" != --conflict-exit-code ] \
    || [ "${5:-}" != 75 ]; then
    exit 1
fi

touch storage/framework/terminal-lock-attempted
exec "${@:7}"
BASH);
    }

    writeDeploymentExecutable($directory.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'php', <<<'BASH'
#!/usr/bin/bash
set -e

if [ "${1:-}" = -r ]; then exec "$SCHOOLTOOL_TEST_PHP_BINARY" "$@"; fi
if [ "${1:-}" = scripts/frontend-release.php ]; then touch storage/framework/artifact-verified; exit 0; fi

if [ "${1:-}" = artisan ] && [ "${2:-}" = cloudways:pull ] && [ "${3:-}" = --check ]; then
    if [ -f storage/framework/fail-cloudways-preflight ]; then
        echo 'Cloudways deployment requires the configured main branch.' >&2
        exit 1
    fi
    touch storage/framework/cloudways-api-checked
    exit 0
fi

if [ "${1:-}" = artisan ] && [ "${2:-}" = cloudways:pull ]; then
    if [ -f storage/framework/fail-cloudways-pull ]; then
        exit 1
    fi

    touch storage/framework/cloudways-api-pulled
    exit 0
fi

if [ "${1:-}" = artisan ] && [ "${2:-}" = up ]; then
    rm -f storage/framework/down storage/framework/cloudways-deploy-maintenance
    exit 0
fi

if [ "${1:-}" = scripts/deployment-status.php ] && [ "${2:-}" = idle ]; then
    touch storage/framework/deployment-announcement-cleared
    exit 0
fi

exit 1
BASH);
    writeDeploymentExecutable($directory.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'bash', <<<'BASH'
#!/usr/bin/bash
set -e

if [ "${1:-}" != scripts/deploy_cloudways.sh ]; then
    exit 1
fi

if [ "${2:-}" = --prepare ]; then
    touch storage/framework/down
    printf 'prepared\n' > storage/framework/cloudways-deploy-maintenance
    exit 0
fi

if [ -f storage/framework/spawn-lock-inheritor ]; then
    nohup sh -c 'cd / && exec sleep 30' </dev/null >/dev/null 2>&1 &
    printf '%s\n' "$!" > storage/framework/lock-inheritor-pid
fi

touch storage/framework/full-deployment-ran
rm -f storage/framework/down storage/framework/cloudways-deploy-maintenance
BASH);

    $filesystem->ensureDirectoryExists($directory.'/deployment');
    $sourceFiles = ['artisan', 'composer.json', 'composer.lock', 'scripts/frontend-release.php', 'scripts/source-manifest.php', 'scripts/deploy_cloudways.sh'];
    foreach ($sourceFiles as $file) {
        file_put_contents($directory.'/'.$file, "verified source bytes\r\n");
    }
    $sourceFiles[] = 'scripts/pdeploy_cloudways.sh';
    $manifest = implode("\n", array_map(
        fn (string $file): string => hash('sha256', str_replace(["\r\n", "\r"], "\n", file_get_contents($directory.'/'.$file))).'  '.$file,
        $sourceFiles,
    ))."\n";
    file_put_contents($directory.'/deployment/source-commit', str_repeat('a', 40)."\n");
    file_put_contents($directory.'/deployment/frontend-build.tar.gz', 'verified frontend bytes');
    file_put_contents($directory.'/deployment/source-manifest.sha256', $manifest);

    return $directory;
}

function terminalPullFixtureHandoff(string $directory): array
{
    $manifest = file_get_contents($directory.'/deployment/source-manifest.sha256');

    return [
        'SCHOOLTOOL_EXPECTED_MAIN_COMMIT' => str_repeat('b', 40),
        'SCHOOLTOOL_EXPECTED_SOURCE_COMMIT' => trim(file_get_contents($directory.'/deployment/source-commit')),
        'SCHOOLTOOL_EXPECTED_FRONTEND_SHA256' => hash_file('sha256', $directory.'/deployment/frontend-build.tar.gz'),
        'SCHOOLTOOL_EXPECTED_SOURCE_MANIFEST_BLOB' => sha1('blob '.strlen($manifest)."\0".$manifest),
        'SCHOOLTOOL_PUBLICATION_POLICY' => 'background-ci-v1',
    ];
}

function runTerminalPullFixture(string $directory, array $environment = [], bool $withHandoff = true): Process
{
    $handoff = terminalPullFixtureHandoff($directory);
    $environment = array_replace(
        $withHandoff ? $handoff : array_fill_keys(array_keys($handoff), false),
        ['SCHOOLTOOL_TEST_PHP_BINARY' => deploymentBashPath(PHP_BINARY)],
        $environment,
    );
    $process = new Process([
        deploymentBashExecutable(),
        '-lc',
        'export PATH="$1/bin:$PATH"; /usr/bin/bash "$1/scripts/pdeploy_cloudways.sh"',
        'schooltool-pdeploy-test',
        deploymentBashPath($directory),
    ], $directory, $environment);
    $process->run();

    return $process;
}

it('reenters the staged release launcher under the lock instead of the old server launcher', function (): void {
    $directory = createTerminalPullFixture();
    try {
        copy($directory.'/scripts/pdeploy_cloudways.sh', $directory.'/storage/framework/staged.sh');
        file_put_contents($directory.'/scripts/pdeploy_cloudways.sh', "#!/usr/bin/env bash\necho OLD_LAUNCHER_EXECUTED >&2\nexit 99\n");
        touch($directory.'/storage/framework/fail-cloudways-preflight');
        $process = new Process([
            deploymentBashExecutable(), '-lc',
            'export PATH="$1/bin:$PATH"; /usr/bin/bash "$1/storage/framework/staged.sh"',
            'schooltool-staged-test', deploymentBashPath($directory),
        ], $directory, terminalPullFixtureHandoff($directory) + [
            'SCHOOLTOOL_DEPLOY_PROJECT_DIRECTORY' => deploymentBashPath($directory),
            'SCHOOLTOOL_TEST_PHP_BINARY' => deploymentBashPath(PHP_BINARY),
        ]);
        $process->run();
        expect($process->isSuccessful())->toBeFalse()
            ->and($process->getErrorOutput())->toContain('requires the configured main branch')->not->toContain('OLD_LAUNCHER_EXECUTED')
            ->and(is_file($directory.'/storage/framework/terminal-lock-attempted'))->toBeTrue()
            ->and(is_file($directory.'/storage/framework/down'))->toBeFalse();
    } finally {
        (new Filesystem)->deleteDirectory($directory);
    }
});

it('rejects a bare manual pull before locks preflight maintenance or deployment', function (): void {
    $directory = createTerminalPullFixture();

    try {
        $process = runTerminalPullFixture($directory, withHandoff: false);

        expect($process->isSuccessful())->toBeFalse()
            ->and($process->getErrorOutput())->toContain('Use gitdeploy after its package checks and LIVE confirmation.')
            ->and(glob($directory.'/storage/framework/*'))->toBe([]);
    } finally {
        (new Filesystem)->deleteDirectory($directory);
    }
});

it('rejects missing or malformed package handoff metadata before any deployment effect', function (string $field, string|false $value): void {
    $directory = createTerminalPullFixture();

    try {
        $process = runTerminalPullFixture($directory, [$field => $value]);

        expect($process->isSuccessful())->toBeFalse()
            ->and($process->getErrorOutput())->toContain('requires the background-ci-v1 package handoff')
            ->and(glob($directory.'/storage/framework/*'))->toBe([]);
    } finally {
        (new Filesystem)->deleteDirectory($directory);
    }
})->with(['SCHOOLTOOL_PUBLICATION_POLICY'])->with([
    'missing' => false,
    'empty' => '',
    'zero' => '0',
    'negative' => '-1',
    'leading zero' => '01',
    'decimal' => '1.0',
    'signed' => '+1',
    'whitespace' => ' 1',
    'newline' => "1\n",
    'shell text' => '1; touch storage/framework/unexpected',
]);

it('requires each release pin even when package handoff metadata is present', function (string $field): void {
    $directory = createTerminalPullFixture();

    try {
        $process = runTerminalPullFixture($directory, [$field => false]);

        expect($process->isSuccessful())->toBeFalse()
            ->and($process->getErrorOutput())->toContain('requires pinned main, source, frontend and source manifest identities')
            ->and(glob($directory.'/storage/framework/*'))->toBe([]);
    } finally {
        (new Filesystem)->deleteDirectory($directory);
    }
})->with([
    'SCHOOLTOOL_EXPECTED_MAIN_COMMIT',
    'SCHOOLTOOL_EXPECTED_SOURCE_COMMIT',
    'SCHOOLTOOL_EXPECTED_FRONTEND_SHA256',
    'SCHOOLTOOL_EXPECTED_SOURCE_MANIFEST_BLOB',
]);

it('stops a rejected Cloudways branch preflight before maintenance or any pull', function (): void {
    $directory = createTerminalPullFixture();
    touch($directory.'/storage/framework/fail-cloudways-preflight');

    try {
        $process = runTerminalPullFixture($directory);

        expect($process->isSuccessful())->toBeFalse()
            ->and($process->getErrorOutput())->toContain('Cloudways deployment requires the configured main branch.')
            ->and(is_file($directory.'/storage/framework/down'))->toBeFalse()
            ->and(is_file($directory.'/storage/framework/cloudways-deploy-maintenance'))->toBeFalse()
            ->and(is_file($directory.'/storage/framework/cloudways-api-pulled'))->toBeFalse()
            ->and(is_file($directory.'/storage/framework/full-deployment-ran'))->toBeFalse();
    } finally {
        (new Filesystem)->deleteDirectory($directory);
    }
});

it('pins the complete backend manifest before a platform API deployment', function (string $changedArtifact, bool $success): void {
    $directory = createTerminalPullFixture();
    $filesystem = new Filesystem;
    $handoff = terminalPullFixtureHandoff($directory);
    if (str_starts_with($changedArtifact, 'scripts/')) {
        file_put_contents($directory.'/'.$changedArtifact, '<?php exit(0); // changed verifier must never be executed');
    } elseif ($changedArtifact !== '') {
        file_put_contents($directory.'/deployment/'.$changedArtifact, 'unconfirmed bytes');
    }

    try {
        $process = runTerminalPullFixture($directory, $handoff);
        expect($process->isSuccessful())->toBe($success, $process->getErrorOutput())
            ->and(is_file($directory.'/storage/framework/full-deployment-ran'))->toBe($success)
            ->and(is_file($directory.'/storage/framework/artifact-verified'))->toBe($success)
            ->and(is_file($directory.'/storage/framework/down'))->toBe(! $success)
            ->and(is_file($directory.'/storage/framework/cloudways-api-pulled'))->toBeTrue();
    } finally {
        $filesystem->deleteDirectory($directory);
    }
})->with([
    'exact confirmed release' => ['', true],
    'different backend with unchanged frontend' => ['source-manifest.sha256', false],
    'different frontend' => ['frontend-build.tar.gz', false],
    'different source identity' => ['source-commit', false],
    'changed frontend verifier with unchanged manifest and artifacts' => ['scripts/frontend-release.php', false],
    'changed manifest verifier with unchanged manifest and artifacts' => ['scripts/source-manifest.php', false],
]);

it('stops a pinned Git deployment before maintenance when main advances', function (): void {
    $directory = createTerminalPullFixture();
    writeDeploymentExecutable($directory.'/bin/git', <<<'BASH'
#!/usr/bin/bash
if [ "$1 $2" = 'rev-parse --is-inside-work-tree' ]; then echo true; exit 0; fi
if [ "$1 $2" = 'branch --show-current' ]; then echo main; exit 0; fi
if [ "$1" = status ] || [ "$1" = fetch ]; then exit 0; fi
if [ "$1 $2" = 'rev-parse FETCH_HEAD' ]; then printf '%040d\n' 9; exit 0; fi
exit 1
BASH);
    try {
        $process = runTerminalPullFixture($directory, [
            'SCHOOLTOOL_EXPECTED_MAIN_COMMIT' => str_repeat('a', 40),
            'SCHOOLTOOL_EXPECTED_SOURCE_COMMIT' => str_repeat('a', 40),
            'SCHOOLTOOL_EXPECTED_FRONTEND_SHA256' => str_repeat('b', 64),
            'SCHOOLTOOL_EXPECTED_SOURCE_MANIFEST_BLOB' => str_repeat('c', 40),
        ]);
        expect($process->isSuccessful())->toBeFalse()
            ->and($process->getErrorOutput())->toContain('main changed after confirmation')
            ->and(is_file($directory.'/storage/framework/down'))->toBeFalse()
            ->and(is_file($directory.'/storage/framework/full-deployment-ran'))->toBeFalse();
    } finally {
        (new Filesystem)->deleteDirectory($directory);
    }
});

function probeTerminalPullFixtureLock(string $directory): Process
{
    $process = new Process([
        deploymentBashExecutable(),
        '-lc',
        'flock -n "$1/storage/framework/cloudways-pdeploy.lock" true',
        'schooltool-pdeploy-lock-probe',
        deploymentBashPath($directory),
    ], $directory);
    $process->run();

    return $process;
}

function stopTerminalPullFixtureLockInheritor(string $directory): void
{
    $processIdPath = $directory.DIRECTORY_SEPARATOR.'storage/framework/lock-inheritor-pid';

    if (! is_file($processIdPath)) {
        return;
    }

    $processId = trim((string) file_get_contents($processIdPath));

    if (preg_match('/^[1-9][0-9]*$/', $processId) !== 1) {
        return;
    }

    $process = new Process([
        deploymentBashExecutable(),
        '-lc',
        'kill -TERM "$1" 2>/dev/null || true',
        'schooltool-pdeploy-lock-cleanup',
        $processId,
    ], $directory);
    $process->run();
}

it('selects the local update plan explicitly', function (): void {
    $process = runDeploymentScript([
        'scripts/update.php',
        '--target=local',
        '--dry-run',
    ]);

    expect($process->isSuccessful())->toBeTrue()
        ->and($process->getOutput())
        ->toContain('Update target: local')
        ->toContain('Composer dependencies')
        ->toContain('verified release artifact');
});

it('selects the Cloudways update plan explicitly', function (): void {
    $process = runDeploymentScript([
        'scripts/update.php',
        '--target=cloudways',
        '--dry-run',
    ]);

    expect($process->isSuccessful())->toBeTrue()
        ->and($process->getOutput())
        ->toContain('Update target: cloudways')
        ->toContain('guarded Cloudways production deployment');
});

it('keeps the frontend release script executable after loading its helpers', function (): void {
    $process = runDeploymentScript(['scripts/frontend-release.php']);

    expect($process->getExitCode())->toBe(2)
        ->and($process->getErrorOutput())->toContain('Usage: php scripts/frontend-release.php');
});

it('runs Windows command wrappers without proc open warnings', function (): void {
    if (PHP_OS_FAMILY !== 'Windows') {
        $this->markTestSkipped('This regression test covers Windows command wrappers.');
    }

    $filesystem = new Filesystem;
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'schooltool-runtime-probe-'.bin2hex(random_bytes(6));
    $commandPath = $directory.DIRECTORY_SEPARATOR.'schooltool-runtime-probe.cmd';
    $originalPath = getenv('PATH');
    $reportedErrors = [];
    $errorHandlerInstalled = false;

    try {
        $filesystem->ensureDirectoryExists($directory);
        file_put_contents($commandPath, "@echo off\r\necho Runtime probe 1.2.3\r\n");
        putenv('PATH='.$directory.PATH_SEPARATOR.($originalPath ?: ''));
        set_error_handler(function (int $severity, string $message) use (&$reportedErrors): bool {
            if ((error_reporting() & $severity) !== 0) {
                $reportedErrors[] = $message;
            }

            return true;
        });
        $errorHandlerInstalled = true;

        $output = releaseCommandOutput(['schooltool-runtime-probe', '--version']);

        expect($output)->toBe('Runtime probe 1.2.3')
            ->and($reportedErrors)->toBe([]);
    } finally {
        if ($errorHandlerInstalled) {
            restore_error_handler();
        }

        if (is_string($originalPath)) {
            putenv("PATH={$originalPath}");
        }

        if (! is_string($originalPath)) {
            putenv('PATH');
        }

        $filesystem->deleteDirectory($directory);
    }
});

it('keeps unavailable optional release runtime probes silent', function (): void {
    $reportedErrors = [];

    set_error_handler(function (int $severity, string $message) use (&$reportedErrors): bool {
        if ((error_reporting() & $severity) !== 0) {
            $reportedErrors[] = $message;
        }

        return true;
    });

    try {
        $version = releaseRuntimeVersion(
            ['schooltool-runtime-that-does-not-exist-'.bin2hex(random_bytes(6)), '--version'],
            '/(\d+\.\d+\.\d+)/',
        );

        expect($version)->toBeNull()
            ->and($reportedErrors)->toBe([]);
    } finally {
        restore_error_handler();
    }
});

it('retries transient frontend release directory move failures', function (): void {
    $moveAttempts = 0;
    $retryPauses = 0;

    $moved = moveReleaseDirectory(
        'source',
        'destination',
        attemptLimit: 3,
        renameDirectory: function (string $source, string $destination) use (&$moveAttempts): bool {
            $moveAttempts++;

            return $moveAttempts === 3;
        },
        pauseBeforeRetry: function () use (&$retryPauses): void {
            $retryPauses++;
        },
    );

    expect($moved)->toBeTrue()
        ->and($moveAttempts)->toBe(3)
        ->and($retryPauses)->toBe(2);
});

it('stops retrying frontend release directory moves at the attempt limit', function (): void {
    $moveAttempts = 0;
    $retryPauses = 0;

    $moved = moveReleaseDirectory(
        'source',
        'destination',
        attemptLimit: 3,
        renameDirectory: function (string $source, string $destination) use (&$moveAttempts): bool {
            $moveAttempts++;

            return false;
        },
        pauseBeforeRetry: function () use (&$retryPauses): void {
            $retryPauses++;
        },
    );

    expect($moved)->toBeFalse()
        ->and($moveAttempts)->toBe(3)
        ->and($retryPauses)->toBe(2);
});

it('checks frontend release rollback and reports the preserved backup path', function (): void {
    $frontendRelease = file_get_contents(deploymentProjectPath('scripts/frontend-release.php'));

    expect($frontendRelease)
        ->toContain('$rollbackSucceeded = moveReleaseDirectory($backupDirectory, $buildDirectory);')
        ->toContain('The previous frontend build could not be restored automatically and remains at {$backupDirectory}.');
});

it('uses the cross-platform update launcher for composer deploy', function (): void {
    $composer = json_decode(
        file_get_contents(deploymentProjectPath('composer.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $updateLauncher = file_get_contents(deploymentProjectPath('scripts/update.php'));
    $cloudwaysDeployment = file_get_contents(deploymentProjectPath('scripts/deploy_cloudways.sh'));

    expect($composer['scripts']['deploy'])
        ->toContain('Composer\\Config::disableProcessTimeout')
        ->toContain('@php scripts/update.php')
        ->and($updateLauncher)
        ->not->toContain('artisan test')
        ->not->toContain('npm run build')
        ->and($cloudwaysDeployment)
        ->not->toContain('artisan test')
        ->not->toContain('npm run build');
});

it('exposes a terminal Cloudways pull deployment workflow', function (): void {
    $composer = json_decode(
        file_get_contents(deploymentProjectPath('composer.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $updateLauncher = file_get_contents(deploymentProjectPath('scripts/update.php'));
    $terminalDeployment = file_get_contents(deploymentProjectPath('scripts/pdeploy_cloudways.sh'));
    $cloudwaysDeployment = file_get_contents(deploymentProjectPath('scripts/deploy_cloudways.sh'));

    expect($composer['scripts']['deploy:prepare'])
        ->toBe([
            'Composer\\Config::disableProcessTimeout',
            '@php scripts/update.php --prepare',
        ])
        ->and(array_slice($composer['scripts']['pdeploy'], 0, 2))
        ->toBe([
            'Composer\\Config::disableProcessTimeout',
            'bash scripts/pdeploy_cloudways.sh',
        ])
        ->and($updateLauncher)
        ->toContain("\$cloudwaysCommand[] = '--prepare';")
        ->and($terminalDeployment)
        ->toContain('cloudways:pull --check --no-interaction')
        ->toContain('SCHOOLTOOL_CLOUDWAYS_TERMINAL_PULL=true bash scripts/deploy_cloudways.sh --prepare')
        ->toContain('php artisan cloudways:pull --no-interaction')
        ->toContain('bash scripts/deploy_cloudways.sh')
        ->toContain('SCHOOLTOOL_CLOUDWAYS_PDEPLOY_LOCKED=true flock')
        ->toContain('--close')
        ->toContain('storage/framework/cloudways-pdeploy.lock')
        ->not->toContain('exec 8>storage/framework/cloudways-pdeploy.lock')
        ->and($cloudwaysDeployment)
        ->toContain('prepare_cloudways_pull')
        ->toContain('nohup php artisan horizon 8>&- 9>&-')
        ->toContain("printf 'prepared\\n' > \"\$maintenance_marker\"")
        ->toContain("printf 'backend-started\\n' > \"\$maintenance_marker\"")
        ->toContain('php scripts/source-manifest.php prune-unlisted');
});

it('prints Vienna completion time inside each deployment entrypoint', function (string $workflow): void {
    $composer = json_decode(file_get_contents(deploymentProjectPath('composer.json')), true, flags: JSON_THROW_ON_ERROR);
    $steps = $composer['scripts'][$workflow];
    expect($steps)->toHaveCount(2);
    $script = file_get_contents(deploymentProjectPath("scripts/{$workflow}_cloudways.sh"));
    expect(preg_match('/^\s*(php -r \'echo "Abgeschlossen:[^\r\n]+)$/m', $script, $matches))->toBe(1);

    $before = time();
    $process = new Process([deploymentBashExecutable(), '-c', $matches[1]], deploymentProjectPath(), ['TZ' => 'Pacific/Honolulu']);
    $process->run();
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
    $output = trim($process->getOutput());
    expect($output)->toMatch('/^Abgeschlossen: \d{2}\.\d{2}\.\d{4} \d{2}:\d{2}:\d{2} CE(?:S)?T \(Europe\/Vienna\)$/');
    $timestamp = substr($output, strlen('Abgeschlossen: '), 19);
    $finished = DateTimeImmutable::createFromFormat('!d.m.Y H:i:s', $timestamp, new DateTimeZone('Europe/Vienna'));
    expect($finished->getTimestamp())->toBeGreaterThanOrEqual($before)->toBeLessThanOrEqual(time());
})->with(['deploy', 'pdeploy']);

it('keeps both Cloudways shell entrypoints syntactically valid', function (): void {
    foreach (['scripts/deploy_cloudways.sh', 'scripts/pdeploy_cloudways.sh'] as $script) {
        $process = new Process([deploymentBashExecutable(), '-n', $script], deploymentProjectPath());
        $process->run();

        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
    }
});

it('uses the Cloudways API and hands a no-Git terminal pull to deployment', function (): void {
    $filesystem = new Filesystem;
    $directory = createTerminalPullFixture();

    try {
        $process = runTerminalPullFixture($directory);

        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
            ->and($process->getOutput())->toContain('Abgeschlossen:', '(Europe/Vienna)')
            ->and(is_file($directory.DIRECTORY_SEPARATOR.'storage/framework/cloudways-api-checked'))->toBeTrue()
            ->and(is_file($directory.DIRECTORY_SEPARATOR.'storage/framework/cloudways-api-pulled'))->toBeTrue()
            ->and(is_file($directory.DIRECTORY_SEPARATOR.'storage/framework/full-deployment-ran'))->toBeTrue()
            ->and(is_file($directory.DIRECTORY_SEPARATOR.'storage/framework/down'))->toBeFalse()
            ->and(is_file($directory.DIRECTORY_SEPARATOR.'storage/framework/cloudways-deploy-maintenance'))->toBeFalse();
    } finally {
        $filesystem->deleteDirectory($directory);
    }
});

it('does not let deployment descendants retain the terminal pull lock', function (): void {
    if (PHP_OS_FAMILY === 'Windows') {
        $this->markTestSkipped('This regression test requires the production util-linux flock implementation.');
    }

    $filesystem = new Filesystem;
    $directory = createTerminalPullFixture(useRealFlock: true);
    file_put_contents($directory.DIRECTORY_SEPARATOR.'storage/framework/spawn-lock-inheritor', '1');

    try {
        $deployment = runTerminalPullFixture($directory);
        $lockProbe = probeTerminalPullFixtureLock($directory);

        expect($deployment->isSuccessful())->toBeTrue($deployment->getErrorOutput())
            ->and($lockProbe->isSuccessful())->toBeTrue('A deployment descendant retained the terminal pull lock.');
    } finally {
        stopTerminalPullFixtureLockInheritor($directory);
        $filesystem->deleteDirectory($directory);
    }
});

it('restores the application when the Cloudways API pull fails before handoff', function (): void {
    $filesystem = new Filesystem;
    $directory = createTerminalPullFixture();
    file_put_contents($directory.DIRECTORY_SEPARATOR.'storage/framework/fail-cloudways-pull', '1');

    try {
        $process = runTerminalPullFixture($directory);

        expect($process->isSuccessful())->toBeFalse()
            ->and($process->getErrorOutput())->toContain('restoring the application from maintenance mode')
            ->and(is_file($directory.DIRECTORY_SEPARATOR.'storage/framework/cloudways-api-checked'))->toBeTrue()
            ->and(is_file($directory.DIRECTORY_SEPARATOR.'storage/framework/cloudways-api-pulled'))->toBeFalse()
            ->and(is_file($directory.DIRECTORY_SEPARATOR.'storage/framework/deployment-announcement-cleared'))->toBeTrue()
            ->and(is_file($directory.DIRECTORY_SEPARATOR.'storage/framework/full-deployment-ran'))->toBeFalse()
            ->and(is_file($directory.DIRECTORY_SEPARATOR.'storage/framework/down'))->toBeFalse()
            ->and(is_file($directory.DIRECTORY_SEPARATOR.'storage/framework/cloudways-deploy-maintenance'))->toBeFalse();
    } finally {
        $filesystem->deleteDirectory($directory);
    }
});

it('records environment versions before starting development services', function (): void {
    $composer = json_decode(
        file_get_contents(deploymentProjectPath('composer.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($composer['scripts']['dev'])
        ->toContain('@php artisan app:update --versions-only')
        ->toContain("npx concurrently -c \"#93c5fd,#c4b5fd,#fdba74,#86efac\" \"php artisan serve\" \"composer run queues:local\" \"php artisan schedule:work\" \"npm run dev\" --names='server,queues,scheduler,vite'");
});

it('prints a Vienna timestamp only after successful PowerShell pulls', function (): void {
    if (PHP_OS_FAMILY !== 'Windows') {
        $this->markTestSkipped('Windows PowerShell helper verification.');
    }

    $command = <<<'POWERSHELL'
. ./scripts/git_helpers.ps1
function git { $global:LASTEXITCODE = 0 }
gitpull
function git { $global:LASTEXITCODE = 1 }
try { gitpull; exit 2 } catch { Write-Output 'Failure preserved' }
POWERSHELL;
    $process = new Process(['powershell', '-NoProfile', '-Command', $command], deploymentProjectPath());
    $process->run();
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
        ->and(substr_count($process->getOutput(), 'Abgeschlossen:'))->toBe(1)
        ->and($process->getOutput())->toContain('(Europe/Vienna)', 'Failure preserved');
});

it('installs a trusted repository-aware gitpush dispatcher', function (): void {
    $installer = file_get_contents(deploymentProjectPath('scripts/install_powershell_helpers.ps1'));
    $entrypoint = file_get_contents(deploymentProjectPath('scripts/gitpush.ps1'));

    expect($installer)
        ->toContain('function gitpull {')
        ->toContain('ITStudioAT/(?:schooltool|stocks)')
        ->toContain('remote get-url --all --push origin')
        ->toContain('Push-Location -LiteralPath `$repositoryRoot')
        ->toContain('Join-Path `$repositoryRoot \'scripts/gitpush.ps1\'')
        ->not->toContain(". 'C:\\laravel\\schooltool\\scripts\\git_helpers.ps1'")
        ->and($entrypoint)
        ->toContain('Join-Path $PSScriptRoot \'git_helpers.ps1\'')
        ->toContain('gitpush @PSBoundParameters');
});

it('writes manifests from tracked files and ignores extra server files', function (): void {
    $probeName = '.source-manifest-probe-'.bin2hex(random_bytes(4));
    $probePath = deploymentProjectPath("scripts/{$probeName}");
    $manifestPath = "storage/framework/{$probeName}.sha256";
    $absoluteManifestPath = deploymentProjectPath($manifestPath);

    try {
        file_put_contents($probePath, "untracked server file\n");

        $write = runDeploymentScript([
            'scripts/source-manifest.php',
            'write',
            $manifestPath,
        ]);

        $verify = runDeploymentScript([
            'scripts/source-manifest.php',
            'verify',
            $manifestPath,
        ]);

        expect($write->isSuccessful())->toBeTrue()
            ->and(file_get_contents($absoluteManifestPath))->not->toContain($probeName)
            ->and($verify->isSuccessful())->toBeTrue();
    } finally {
        if (is_file($probePath)) {
            unlink($probePath);
        }

        if (is_file($absoluteManifestPath)) {
            unlink($absoluteManifestPath);
        }
    }
});

it('detects changed required source files', function (): void {
    $probeName = '.source-manifest-probe-'.bin2hex(random_bytes(4));
    $manifestPath = "storage/framework/{$probeName}.sha256";
    $absoluteManifestPath = deploymentProjectPath($manifestPath);

    try {
        $write = runDeploymentScript([
            'scripts/source-manifest.php',
            'write',
            $manifestPath,
        ]);
        $manifest = file_get_contents($absoluteManifestPath);
        $manifest = preg_replace('/^[0-9a-f]{64}  artisan$/m', str_repeat('0', 64).'  artisan', $manifest);
        file_put_contents($absoluteManifestPath, $manifest);

        $verify = runDeploymentScript([
            'scripts/source-manifest.php',
            'verify',
            $manifestPath,
        ]);

        expect($write->isSuccessful())->toBeTrue()
            ->and($verify->isSuccessful())->toBeFalse()
            ->and($verify->getErrorOutput())
            ->toContain('changed: artisan')
            ->toContain('Pull main again after gitpush has completed.');
    } finally {
        if (is_file($absoluteManifestPath)) {
            unlink($absoluteManifestPath);
        }
    }
});

it('verifies a freshly written complete source manifest', function (): void {
    $probeName = '.source-manifest-probe-'.bin2hex(random_bytes(4));
    $manifestPath = "storage/framework/{$probeName}.sha256";
    $absoluteManifestPath = deploymentProjectPath($manifestPath);

    try {
        $write = runDeploymentScript([
            'scripts/source-manifest.php',
            'write',
            $manifestPath,
        ]);

        $verify = runDeploymentScript([
            'scripts/source-manifest.php',
            'verify',
            $manifestPath,
        ]);

        expect($write->isSuccessful())->toBeTrue()
            ->and($verify->isSuccessful())->toBeTrue();
    } finally {
        if (is_file($absoluteManifestPath)) {
            unlink($absoluteManifestPath);
        }
    }
});

it('verifies and prunes a manifest without Git metadata', function (): void {
    $filesystem = new Filesystem;
    $temporaryProject = sys_get_temp_dir().DIRECTORY_SEPARATOR.'schooltool-source-manifest-'.bin2hex(random_bytes(6));
    $manifestScript = $temporaryProject.DIRECTORY_SEPARATOR.'scripts'.DIRECTORY_SEPARATOR.'source-manifest.php';
    $manifestPath = $temporaryProject.DIRECTORY_SEPARATOR.'deployment'.DIRECTORY_SEPARATOR.'source-manifest.sha256';
    $requiredFiles = [
        'artisan' => "<?php\n",
        'composer.json' => "{}\n",
        'composer.lock' => "{}\n",
        'scripts/deploy_cloudways.sh' => "#!/usr/bin/env bash\n",
        'scripts/frontend-release.php' => "<?php\n",
        'scripts/pdeploy_cloudways.sh' => "#!/usr/bin/env bash\n",
    ];

    try {
        $filesystem->ensureDirectoryExists(dirname($manifestScript));
        $filesystem->ensureDirectoryExists(dirname($manifestPath));
        $filesystem->copy(deploymentProjectPath('scripts/source-manifest.php'), $manifestScript);

        foreach ($requiredFiles as $relativePath => $contents) {
            $absolutePath = $temporaryProject.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $filesystem->ensureDirectoryExists(dirname($absolutePath));
            file_put_contents($absolutePath, $contents);
        }

        $manifestFiles = [...array_keys($requiredFiles), 'scripts/source-manifest.php'];
        sort($manifestFiles, SORT_STRING);
        $manifest = collect($manifestFiles)
            ->map(fn (string $relativePath): string => hash(
                'sha256',
                str_replace(["\r\n", "\r"], "\n", (string) file_get_contents(
                    $temporaryProject.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath),
                )),
            )."  {$relativePath}")
            ->implode("\n")."\n";
        file_put_contents($manifestPath, $manifest);
        file_put_contents($temporaryProject.DIRECTORY_SEPARATOR.'scripts'.DIRECTORY_SEPARATOR.'stale.php', "<?php\n");

        $verifyBeforePrune = new Process([
            PHP_BINARY,
            $manifestScript,
            'verify',
            'deployment/source-manifest.sha256',
        ], $temporaryProject);
        $verifyBeforePrune->run();

        $prune = new Process([
            PHP_BINARY,
            $manifestScript,
            'prune-unlisted',
            'deployment/source-manifest.sha256',
        ], $temporaryProject);
        $prune->run();

        expect($verifyBeforePrune->isSuccessful())->toBeFalse()
            ->and($verifyBeforePrune->getErrorOutput())->toContain('unlisted: scripts/stale.php')
            ->and($prune->isSuccessful())->toBeTrue($prune->getErrorOutput())
            ->and($prune->getOutput())->toContain('Pruned stale deployment source file: scripts/stale.php')
            ->and(is_file($temporaryProject.DIRECTORY_SEPARATOR.'scripts'.DIRECTORY_SEPARATOR.'stale.php'))->toBeFalse();
    } finally {
        $filesystem->deleteDirectory($temporaryProject);
    }
});
