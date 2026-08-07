<?php

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

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

function createTerminalPullFixture(): string
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
    writeDeploymentExecutable($directory.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'flock', <<<'BASH'
#!/usr/bin/bash
exit 0
BASH);
    writeDeploymentExecutable($directory.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'php', <<<'BASH'
#!/usr/bin/bash
set -e

if [ "${1:-}" = artisan ] && [ "${2:-}" = cloudways:pull ] && [ "${3:-}" = --check ]; then
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

touch storage/framework/full-deployment-ran
rm -f storage/framework/down storage/framework/cloudways-deploy-maintenance
BASH);

    return $directory;
}

function runTerminalPullFixture(string $directory): Process
{
    $process = new Process([
        deploymentBashExecutable(),
        '-lc',
        'export PATH="$1/bin:$PATH"; /usr/bin/bash "$1/scripts/pdeploy_cloudways.sh"',
        'schooltool-pdeploy-test',
        deploymentBashPath($directory),
    ], $directory);
    $process->run();

    return $process;
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
        ->and($composer['scripts']['pdeploy'])
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
        ->toContain('exec 8>storage/framework/cloudways-pdeploy.lock')
        ->and($cloudwaysDeployment)
        ->toContain('prepare_cloudways_pull')
        ->toContain("printf 'prepared\\n' > \"\$maintenance_marker\"")
        ->toContain("printf 'backend-started\\n' > \"\$maintenance_marker\"")
        ->toContain('php scripts/source-manifest.php prune-unlisted');
});

it('keeps both Cloudways shell entrypoints syntactically valid', function (): void {
    foreach (['scripts/deploy_cloudways.sh', 'scripts/pdeploy_cloudways.sh'] as $script) {
        $process = new Process(['bash', '-n', $script], deploymentProjectPath());
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
            ->and(is_file($directory.DIRECTORY_SEPARATOR.'storage/framework/cloudways-api-checked'))->toBeTrue()
            ->and(is_file($directory.DIRECTORY_SEPARATOR.'storage/framework/cloudways-api-pulled'))->toBeTrue()
            ->and(is_file($directory.DIRECTORY_SEPARATOR.'storage/framework/full-deployment-ran'))->toBeTrue()
            ->and(is_file($directory.DIRECTORY_SEPARATOR.'storage/framework/down'))->toBeFalse()
            ->and(is_file($directory.DIRECTORY_SEPARATOR.'storage/framework/cloudways-deploy-maintenance'))->toBeFalse();
    } finally {
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

it('installs a trusted repository-aware gitpush dispatcher', function (): void {
    $installer = file_get_contents(deploymentProjectPath('scripts/install_powershell_helpers.ps1'));
    $entrypoint = file_get_contents(deploymentProjectPath('scripts/gitpush.ps1'));

    expect($installer)
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
