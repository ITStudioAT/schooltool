<?php

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/** @param array<int, string> $arguments */
function runLocalDeploymentFixtureGit(string $directory, array $arguments): string
{
    $process = new Process(['git', ...$arguments], $directory);
    $process->mustRun();

    return trim($process->getOutput());
}

/** @param array<int, string> $arguments */
function runLocalDeploymentFixture(string $directory, array $arguments = []): Process
{
    $process = new Process(
        [PHP_BINARY, 'scripts/update.php', '--target=local', ...$arguments],
        $directory,
        ['SCHOOLTOOL_PREVIEW_INSTANCE' => 'false'],
    );
    $process->run();

    return $process;
}

beforeEach(function (): void {
    $filesystem = new Filesystem;
    $this->deploymentFixture = sys_get_temp_dir().DIRECTORY_SEPARATOR.'schooltool-local-deploy-'.bin2hex(random_bytes(6));
    $this->deploymentCheckout = $this->deploymentFixture.DIRECTORY_SEPARATOR.'checkout';
    $this->deploymentRemote = $this->deploymentFixture.DIRECTORY_SEPARATOR.'remote.git';

    foreach (['scripts', 'vendor/composer', 'node_modules', 'storage/framework'] as $path) {
        $filesystem->ensureDirectoryExists($this->deploymentCheckout.DIRECTORY_SEPARATOR.$path);
    }
    $filesystem->copy(dirname(__DIR__, 2).'/scripts/update.php', $this->deploymentCheckout.'/scripts/update.php');
    file_put_contents($this->deploymentCheckout.'/.gitignore', "/vendor\n/node_modules\n/storage\n");
    file_put_contents($this->deploymentCheckout.'/composer.lock', '{}');
    file_put_contents($this->deploymentCheckout.'/package-lock.json', '{}');
    file_put_contents($this->deploymentCheckout.'/vendor/autoload.php', '<?php');
    file_put_contents($this->deploymentCheckout.'/vendor/composer/installed.php', '<?php return ["root" => ["dev" => true]];');
    file_put_contents($this->deploymentCheckout.'/node_modules/.package-lock.json', '{}');
    file_put_contents($this->deploymentCheckout.'/storage/framework/composer-dependencies.sha256', hash('sha256', '{}'));
    file_put_contents($this->deploymentCheckout.'/storage/framework/frontend-dependencies.sha256', hash('sha256', '{}'));
    file_put_contents($this->deploymentCheckout.'/scripts/frontend-release.php', '<?php file_put_contents(__DIR__."/../storage/frontend-installed", "1");');
    file_put_contents($this->deploymentCheckout.'/artisan', '<?php file_put_contents(__DIR__."/storage/application-updated", "1");');

    runLocalDeploymentFixtureGit($this->deploymentFixture, ['init', '--bare', '--initial-branch=main', $this->deploymentRemote]);
    runLocalDeploymentFixtureGit($this->deploymentCheckout, ['init', '--initial-branch=main']);
    runLocalDeploymentFixtureGit($this->deploymentCheckout, ['config', 'user.name', 'Deployment Test']);
    runLocalDeploymentFixtureGit($this->deploymentCheckout, ['config', 'user.email', 'deployment@example.test']);
    runLocalDeploymentFixtureGit($this->deploymentCheckout, ['add', '.']);
    runLocalDeploymentFixtureGit($this->deploymentCheckout, ['commit', '-m', 'Fixture']);
    runLocalDeploymentFixtureGit($this->deploymentCheckout, ['remote', 'add', 'origin', $this->deploymentRemote]);
    runLocalDeploymentFixtureGit($this->deploymentCheckout, ['push', '--set-upstream', 'origin', 'main']);
});

afterEach(function (): void {
    $filesystem = new Filesystem;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->deploymentFixture, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->isFile()) {
            chmod($file->getPathname(), 0666);
        }
    }
    $filesystem->deleteDirectory($this->deploymentFixture);
});

test('full local deployment accepts only clean freshly fetched published main', function (): void {
    $process = runLocalDeploymentFixture($this->deploymentCheckout);

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
        ->and(is_file($this->deploymentCheckout.'/storage/frontend-installed'))->toBeTrue()
        ->and(is_file($this->deploymentCheckout.'/storage/application-updated'))->toBeTrue()
        ->and($process->getOutput())->toContain('Abgeschlossen:');
});

test('full local deployment stops before dependencies frontend and application updates for unsafe git states', function (string $state): void {
    if ($state === 'feature') {
        runLocalDeploymentFixtureGit($this->deploymentCheckout, ['switch', '-c', 'feature/example']);
    } elseif ($state === 'detached') {
        runLocalDeploymentFixtureGit($this->deploymentCheckout, ['switch', '--detach']);
    } elseif ($state === 'untracked') {
        file_put_contents($this->deploymentCheckout.'/untracked.txt', 'not saved');
    } elseif ($state === 'dirty' || $state === 'staged') {
        file_put_contents($this->deploymentCheckout.'/composer.lock', '{"changed":true}');
        if ($state === 'staged') {
            runLocalDeploymentFixtureGit($this->deploymentCheckout, ['add', 'composer.lock']);
        }
    } elseif ($state === 'ahead' || $state === 'behind') {
        runLocalDeploymentFixtureGit($this->deploymentCheckout, ['commit', '--allow-empty', '-m', 'New commit']);
        if ($state === 'behind') {
            runLocalDeploymentFixtureGit($this->deploymentCheckout, ['push', 'origin', 'main']);
            runLocalDeploymentFixtureGit($this->deploymentCheckout, ['reset', '--hard', 'HEAD^']);
        }
    } elseif ($state === 'unreachable origin') {
        runLocalDeploymentFixtureGit($this->deploymentCheckout, ['remote', 'set-url', 'origin', $this->deploymentFixture.'/missing.git']);
    } else {
        file_put_contents($this->deploymentCheckout.'/.git/'.$state, 'in-progress');
    }

    $before = runLocalDeploymentFixtureGit($this->deploymentCheckout, ['rev-parse', 'HEAD']);
    $process = runLocalDeploymentFixture($this->deploymentCheckout);

    expect($process->isSuccessful())->toBeFalse()
        ->and($process->getOutput())->not->toContain('Composer dependencies', '$ ', 'Abgeschlossen:')
        ->and(is_file($this->deploymentCheckout.'/storage/frontend-installed'))->toBeFalse()
        ->and(is_file($this->deploymentCheckout.'/storage/application-updated'))->toBeFalse()
        ->and(runLocalDeploymentFixtureGit($this->deploymentCheckout, ['rev-parse', 'HEAD']))->toBe($before);
})->with(['feature', 'detached', 'untracked', 'dirty', 'staged', 'ahead', 'behind', 'unreachable origin', 'MERGE_HEAD', 'CHERRY_PICK_HEAD', 'REVERT_HEAD', 'rebase-merge', 'rebase-apply', 'BISECT_LOG']);

test('dependency preparation remains available on an unpublished dirty feature without application updates', function (): void {
    runLocalDeploymentFixtureGit($this->deploymentCheckout, ['switch', '-c', 'feature/example']);
    runLocalDeploymentFixtureGit($this->deploymentCheckout, ['remote', 'set-url', 'origin', $this->deploymentFixture.'/missing.git']);
    file_put_contents($this->deploymentCheckout.'/untracked.txt', 'in progress');
    $process = runLocalDeploymentFixture($this->deploymentCheckout, ['--prepare']);

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
        ->and($process->getOutput())->toContain('Composer dependencies', 'Frontend dependencies')
        ->and(is_file($this->deploymentCheckout.'/storage/frontend-installed'))->toBeFalse()
        ->and(is_file($this->deploymentCheckout.'/storage/application-updated'))->toBeFalse();
});
