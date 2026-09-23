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
    $frontendLock = json_encode(['lockfileVersion' => 3, 'packages' => ['' => []]]);
    file_put_contents($this->deploymentCheckout.'/package.json', '{}');
    file_put_contents($this->deploymentCheckout.'/package-lock.json', $frontendLock);
    file_put_contents($this->deploymentCheckout.'/vendor/autoload.php', '<?php');
    file_put_contents($this->deploymentCheckout.'/vendor/composer/installed.php', '<?php return ["root" => ["dev" => true]];');
    file_put_contents($this->deploymentCheckout.'/node_modules/.package-lock.json', $frontendLock);
    file_put_contents($this->deploymentCheckout.'/storage/framework/composer-dependencies.sha256', hash('sha256', '{}'));
    file_put_contents($this->deploymentCheckout.'/storage/framework/frontend-dependencies.sha256', hash('sha256', $frontendLock));
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

test('frontend preparation checks installed packages instead of npm cache presence or timestamps', function (string $state, bool $current): void {
    $directory = $this->deploymentCheckout;
    $filesystem = new Filesystem;
    $filesystem->ensureDirectoryExists($directory.'/node_modules/example');
    $filesystem->ensureDirectoryExists($directory.'/node_modules/.bin');
    $package = ['version' => '1.0.0', 'resolved' => 'https://example.test/example.tgz', 'integrity' => 'sha512-example'];
    $lock = ['lockfileVersion' => 3, 'packages' => ['' => ['devDependencies' => ['example' => '1.0.0']], 'node_modules/example' => $package]];
    $installed = ['version' => '1.0.0', 'bin' => ['example' => 'cli.js']];
    file_put_contents($directory.'/package.json', json_encode(['devDependencies' => ['example' => '1.0.0']]));
    file_put_contents($directory.'/node_modules/example/package.json', json_encode($installed));
    file_put_contents($directory.'/node_modules/example/cli.js', 'console.log("example");');
    $shim = $directory.'/node_modules/.bin/example'.(PHP_OS_FAMILY === 'Windows' ? '.cmd' : '');
    file_put_contents($shim, 'fixture');
    file_put_contents($directory.'/package-lock.json', json_encode($lock));
    file_put_contents($directory.'/node_modules/.package-lock.json', json_encode($lock));
    $receipt = $directory.'/storage/framework/frontend-dependencies.sha256';
    file_put_contents($receipt, hash_file('sha256', $directory.'/package-lock.json'));

    if ($state === 'missing npm cache') {
        unlink($directory.'/node_modules/.package-lock.json');
    } elseif ($state === 'missing receipt') {
        unlink($receipt);
        touch($directory.'/node_modules/.package-lock.json', time() - 3600);
    } elseif ($state === 'stale receipt') {
        file_put_contents($receipt, str_repeat('a', 64));
    } elseif ($state === 'changed package version') {
        $installed['version'] = '0.9.0';
        file_put_contents($directory.'/node_modules/example/package.json', json_encode($installed));
    } elseif ($state === 'changed lock integrity') {
        $lock['packages']['node_modules/example']['integrity'] = 'sha512-changed';
        file_put_contents($directory.'/package-lock.json', json_encode($lock));
    } elseif ($state === 'changed manifest') {
        file_put_contents($directory.'/package.json', '{"devDependencies":{"example":"2.0.0"}}');
    } elseif ($state === 'missing package') {
        unlink($directory.'/node_modules/example/package.json');
    } elseif ($state === 'missing executable') {
        unlink($directory.'/node_modules/example/cli.js');
    } elseif ($state === 'missing shim') {
        unlink($shim);
    } elseif ($state === 'partial install') {
        file_put_contents($receipt.'.installing', 'incomplete');
    } elseif ($state === 'missing evidence') {
        unlink($receipt);
        unlink($directory.'/node_modules/.package-lock.json');
    } elseif ($state === 'other platform optional package' || $state === 'missing current platform optional package') {
        $lock['packages']['node_modules/native'] = ['version' => '1.0.0', 'optional' => true, 'os' => [$state === 'other platform optional package' ? 'other-platform' : (PHP_OS_FAMILY === 'Windows' ? 'win32' : 'linux')]];
        file_put_contents($directory.'/package-lock.json', json_encode($lock));
        file_put_contents($directory.'/node_modules/.package-lock.json', json_encode($lock));
        file_put_contents($receipt, hash_file('sha256', $directory.'/package-lock.json'));
    }

    $process = new Process([PHP_BINARY, '-r', 'require "scripts/update.php"; exit(frontendDependenciesAreCurrent() ? 0 : 1);'], $directory);
    $process->run();

    expect($process->getExitCode())->toBe($current ? 0 : 1, $process->getErrorOutput());
    if ($current) {
        expect(trim(file_get_contents($receipt)))->toBe(hash_file('sha256', $directory.'/package-lock.json'));
    }
})->with([
    ['matching installation', true],
    ['missing npm cache', true],
    ['missing receipt', true],
    ['stale receipt', true],
    ['changed package version', false],
    ['changed lock integrity', false],
    ['changed manifest', false],
    ['missing package', false],
    ['missing executable', false],
    ['missing shim', false],
    ['partial install', false],
    ['missing evidence', false],
    ['other platform optional package', true],
    ['missing current platform optional package', false],
]);

test('frontend preparation keeps failed installations incomplete and clears the marker only on success', function (int $exitCode): void {
    $directory = $this->deploymentCheckout;
    $bin = $directory.'/fake-bin';
    (new Filesystem)->ensureDirectoryExists($bin);
    $wrapper = $bin.'/npm'.(PHP_OS_FAMILY === 'Windows' ? '.cmd' : '');
    file_put_contents($wrapper, PHP_OS_FAMILY === 'Windows' ? "@echo off\r\nexit /b {$exitCode}\r\n" : "#!/bin/sh\nexit {$exitCode}\n");
    chmod($wrapper, 0755);
    $receipt = $directory.'/storage/framework/frontend-dependencies.sha256';
    unlink($receipt);
    unlink($directory.'/node_modules/.package-lock.json');
    $process = new Process(
        [PHP_BINARY, '-r', 'require "scripts/update.php"; exit(installFrontendDependencies());'],
        $directory,
        ['PATH' => $bin.PATH_SEPARATOR.getenv('PATH')],
    );
    $process->run();

    expect($process->getExitCode())->toBe($exitCode, $process->getErrorOutput())
        ->and(is_file($receipt.'.installing'))->toBe($exitCode !== 0);
    if ($exitCode === 0) {
        expect(trim(file_get_contents($receipt)))->toBe(hash_file('sha256', $directory.'/package-lock.json'));
    }
})->with([0, 37]);
