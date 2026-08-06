<?php

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
