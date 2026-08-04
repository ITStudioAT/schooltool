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

it('detects source files added after a release manifest was created', function (): void {
    $probeName = '.source-manifest-probe-'.bin2hex(random_bytes(4));
    $probePath = deploymentProjectPath("scripts/{$probeName}");
    $manifestPath = "storage/framework/{$probeName}.sha256";
    $absoluteManifestPath = deploymentProjectPath($manifestPath);

    try {
        $write = runDeploymentScript([
            'scripts/source-manifest.php',
            'write',
            $manifestPath,
        ]);

        expect($write->isSuccessful())->toBeTrue();

        file_put_contents($probePath, "probe\n");

        $verify = runDeploymentScript([
            'scripts/source-manifest.php',
            'verify',
            $manifestPath,
        ]);

        expect($verify->isSuccessful())->toBeFalse()
            ->and($verify->getErrorOutput())
            ->toContain("unexpected: scripts/{$probeName}")
            ->toContain('Pull main again after gitpush has completed.');
    } finally {
        if (is_file($probePath)) {
            unlink($probePath);
        }

        if (is_file($absoluteManifestPath)) {
            unlink($absoluteManifestPath);
        }
    }
});
