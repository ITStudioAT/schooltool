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
    $relativeProbePath = "scripts/{$probeName}";
    $probePath = deploymentProjectPath($relativeProbePath);
    $manifestPath = "storage/framework/{$probeName}.sha256";
    $absoluteManifestPath = deploymentProjectPath($manifestPath);

    try {
        file_put_contents($probePath, "changed\n");
        file_put_contents($absoluteManifestPath, hash('sha256', "expected\n")."  {$relativeProbePath}\n");

        $verify = runDeploymentScript([
            'scripts/source-manifest.php',
            'verify',
            $manifestPath,
        ]);

        expect($verify->isSuccessful())->toBeFalse()
            ->and($verify->getErrorOutput())
            ->toContain("changed: {$relativeProbePath}")
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

it('normalizes source line endings across Windows and Linux', function (): void {
    $probeName = '.source-manifest-probe-'.bin2hex(random_bytes(4));
    $relativeProbePath = "scripts/{$probeName}";
    $probePath = deploymentProjectPath($relativeProbePath);
    $manifestPath = "storage/framework/{$probeName}.sha256";
    $absoluteManifestPath = deploymentProjectPath($manifestPath);

    try {
        file_put_contents($probePath, "first\r\nsecond\r\n");
        file_put_contents($absoluteManifestPath, hash('sha256', "first\nsecond\n")."  {$relativeProbePath}\n");

        $verify = runDeploymentScript([
            'scripts/source-manifest.php',
            'verify',
            $manifestPath,
        ]);

        expect($verify->isSuccessful())->toBeTrue();
    } finally {
        if (is_file($probePath)) {
            unlink($probePath);
        }

        if (is_file($absoluteManifestPath)) {
            unlink($absoluteManifestPath);
        }
    }
});
