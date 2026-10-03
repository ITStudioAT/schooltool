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

function createDevelopmentFrontendFixture(string $directory): void
{
    $filesystem = new Filesystem;
    $lock = ['lockfileVersion' => 3, 'packages' => ['' => ['devDependencies' => ['concurrently' => '1.0.0', 'vite' => '1.0.0']]]];
    foreach (['concurrently', 'vite'] as $name) {
        $filesystem->ensureDirectoryExists($directory.'/node_modules/'.$name);
        $package = [
            'name' => $name,
            'version' => '1.0.0',
            'bin' => [$name => 'cli.js'],
            'scripts' => ['install' => 'node -e "require(\'fs\').writeFileSync(\'../../install-script-ran\', \'1\')"'],
        ];
        file_put_contents($directory.'/node_modules/'.$name.'/package.json', json_encode($package));
        file_put_contents($directory.'/node_modules/'.$name.'/cli.js', "#!/usr/bin/env node\nconsole.log('{$name} fixture');\n");
        $lock['packages']['node_modules/'.$name] = ['version' => '1.0.0', 'bin' => $package['bin']];
    }
    file_put_contents($directory.'/package.json', json_encode(['name' => 'schooltool-dev-fixture', 'devDependencies' => $lock['packages']['']['devDependencies']]));
    file_put_contents($directory.'/package-lock.json', json_encode($lock));
    file_put_contents($directory.'/node_modules/.package-lock.json', json_encode($lock));
    file_put_contents($directory.'/storage/framework/frontend-dependencies.sha256', hash_file('sha256', $directory.'/package-lock.json'));
}

function createDevelopmentNpmFailureFixture(string $directory, int $exitCode): string
{
    $bin = $directory.'/fake-bin';
    (new Filesystem)->ensureDirectoryExists($bin);
    $wrapper = $bin.'/npm'.(PHP_OS_FAMILY === 'Windows' ? '.cmd' : '');
    file_put_contents($wrapper, PHP_OS_FAMILY === 'Windows'
        ? "@echo off\r\necho called > npm-called\r\nexit /b {$exitCode}\r\n"
        : "#!/bin/sh\necho called > npm-called\nexit {$exitCode}\n");
    chmod($wrapper, 0755);

    return $bin.PATH_SEPARATOR.getenv('PATH');
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
    $filesystem->copy(dirname(__DIR__, 2).'/scripts/frontend-install.ps1', $this->deploymentCheckout.'/scripts/frontend-install.ps1');
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
    if (PHP_OS_FAMILY === 'Windows' && ($this->frontendFixtureProcesses ?? []) !== []) {
        $ownedProcessIds = [];
        foreach ($this->frontendFixtureProcesses as $process) {
            if ($process->isRunning()) {
                $ownedProcessIds[] = $process->getPid();
            }
        }
        $cleanup = new Process(['powershell.exe', '-NoProfile', '-NonInteractive', '-Command', <<<'POWERSHELL'
$root = [IO.Path]::GetFullPath($env:SCHOOLTOOL_PROCESS_FIXTURE).TrimEnd('\') + '\'
$processes = @(Get-CimInstance Win32_Process)
$owned = @{}
foreach ($id in @($env:SCHOOLTOOL_PROCESS_IDS | ConvertFrom-Json)) { $owned[[int]$id] = $true }
do {
    $added = $false
    foreach ($process in $processes) {
        if (-not $owned.ContainsKey([int]$process.ProcessId) -and $owned.ContainsKey([int]$process.ParentProcessId)) {
            $parent = $processes | Where-Object ProcessId -EQ $process.ParentProcessId | Select-Object -First 1
            if ($parent -and $parent.CreationDate -le $process.CreationDate) {
                $owned[[int]$process.ProcessId] = $true
                $added = $true
            }
        }
    }
} while ($added)
foreach ($process in @($processes | Sort-Object CreationDate -Descending)) {
    if ($owned.ContainsKey([int]$process.ProcessId) -or ($process.ExecutablePath -and $process.ExecutablePath.StartsWith($root, [StringComparison]::OrdinalIgnoreCase)) -or
        ($process.CommandLine -and $process.ExecutablePath -and [IO.Path]::GetFileName($process.ExecutablePath) -eq 'node.exe' -and $process.CommandLine.Replace('\','/').Contains($root.Replace('\','/')))) {
        $current = Get-CimInstance Win32_Process -Filter "ProcessId=$($process.ProcessId)"
        if ($current -and $current.CreationDate -eq $process.CreationDate -and $current.CommandLine -ceq $process.CommandLine) {
            Stop-Process -Id $process.ProcessId -ErrorAction SilentlyContinue
        }
    }
}
POWERSHELL], env: ['SCHOOLTOOL_PROCESS_FIXTURE' => $this->deploymentFixture, 'SCHOOLTOOL_PROCESS_IDS' => json_encode($ownedProcessIds)]);
        $cleanup->mustRun();
    }
    foreach ($this->frontendFixtureProcesses ?? [] as $process) {
        $process->stop(1);
    }
    $filesystem = new Filesystem;
    $resolvedFixture = realpath($this->deploymentFixture);
    if ($resolvedFixture === false || ! str_starts_with($resolvedFixture, realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.'schooltool-local-deploy-')) {
        throw new RuntimeException('Unsafe deployment fixture cleanup path.');
    }
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->deploymentFixture, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->isFile()) {
            chmod($file->getPathname(), 0666);
        }
    }
    $filesystem->deleteDirectory($this->deploymentFixture);
});

function startFrontendViteProcessFixture(object $test, string $directory, string $kind = 'vite', ?string $esbuildDirectory = null): Process
{
    $filesystem = new Filesystem;
    $entry = $kind === 'vitest' ? 'node_modules/vitest/vitest.mjs' : 'node_modules/vite/bin/vite.js';
    $filesystem->ensureDirectoryExists(dirname($directory.'/'.$entry));
    $filesystem->ensureDirectoryExists($directory.'/storage');
    $esbuildDirectory ??= $directory;
    $binary = $esbuildDirectory.'/node_modules/@esbuild/win32-x64/esbuild.exe';
    $filesystem->ensureDirectoryExists(dirname($binary));
    $filesystem->copy(dirname(__DIR__, 2).'/node_modules/@esbuild/win32-x64/esbuild.exe', $binary);
    $versionProcess = new Process([$binary, '--version']);
    $versionProcess->mustRun();
    $javascript = 'const fs = require("node:fs"), cp = require("node:child_process");'.
        'fs.appendFileSync('.json_encode($directory.'/storage/vite-runs').', process.pid + "\n");'.
        'cp.spawn('.json_encode($binary).', ['.json_encode('--service='.trim($versionProcess->getOutput())).', "--ping"], { stdio: ["pipe", "pipe", "pipe"] });'.
        'setInterval(() => {}, 1000);';
    if ($kind === 'vitest') {
        $javascript = 'import { createRequire } from "node:module"; const require = createRequire(import.meta.url);'.$javascript;
    }
    file_put_contents($directory.'/'.$entry, $javascript);
    $launchEntry = $directory.'/'.$entry;
    if ($kind === 'supervised') {
        $launchEntry = $directory.'/node_modules/concurrently/index.js';
        $filesystem->ensureDirectoryExists(dirname($launchEntry));
        file_put_contents($launchEntry, 'const cp = require("node:child_process"); const child = cp.spawn(process.execPath, ['.json_encode($directory.'/'.$entry).']); child.on("exit", () => process.exit(42)); setInterval(() => {}, 1000);');
    }
    $command = ['node', $launchEntry];
    if ($kind === 'managed') {
        $filesystem->copy(dirname(__DIR__, 2).'/scripts/vite-dev.mjs', $directory.'/scripts/vite-dev.mjs');
        $package = json_decode(file_get_contents(dirname(__DIR__, 2).'/package.json'), true);
        file_put_contents($directory.'/package.json', json_encode(['scripts' => ['dev' => $package['scripts']['dev']]]));
        foreach (['server', 'queue'] as $service) {
            file_put_contents($directory.'/'.$service.'.php', '<?php file_put_contents(__DIR__."/storage/'.$service.'-pid", (string) getmypid()); while (true) { usleep(100000); file_put_contents(__DIR__."/storage/'.$service.'-heartbeat", "1", FILE_APPEND); }');
        }
        $command = ['node', dirname(__DIR__, 2).'/node_modules/concurrently/dist/bin/concurrently.js', '--kill-others', '--names=server,queue,vite',
            'php server.php', 'php queue.php', 'npm run dev'];
    }
    $process = new Process($command, $directory);
    $process->start();
    $test->frontendFixtureProcesses[] = $process;
    $deadline = microtime(true) + 5;
    while ((! is_file($directory.'/storage/vite-runs') || ($kind === 'managed' && (! is_file($directory.'/storage/queue-heartbeat') || ! is_file($directory.'/storage/server-heartbeat')))) && microtime(true) < $deadline && $process->isRunning()) {
        usleep(20000);
    }
    if (! $process->isRunning() || ! is_file($directory.'/storage/vite-runs') || ($kind === 'managed' && (! is_file($directory.'/storage/queue-heartbeat') || ! is_file($directory.'/storage/server-heartbeat')))) {
        throw new RuntimeException('Frontend process fixture failed to start: '.$process->getOutput().$process->getErrorOutput());
    }

    return $process;
}

function frontendPreparationProcessFixture(object $test, int $npmExitCode = 0): Process
{
    $directory = $test->deploymentCheckout;
    foreach (['storage/framework/frontend-dependencies.sha256', 'node_modules/.package-lock.json'] as $file) {
        if (is_file($directory.'/'.$file)) {
            unlink($directory.'/'.$file);
        }
    }

    return new Process([PHP_BINARY, 'scripts/update.php', '--target=local', '--prepare', '--pause-vite'], $directory,
        ['SCHOOLTOOL_PREVIEW_INSTANCE' => 'false', 'PATH' => createDevelopmentNpmFailureFixture($directory, $npmExitCode)], timeout: 30);
}

test('gitsave frontend coordination leaves current packages and their running Vite untouched', function (): void {
    if (PHP_OS_FAMILY !== 'Windows') {
        $this->markTestSkipped('Windows process coordination fixture');
    }
    $vite = startFrontendViteProcessFixture($this, $this->deploymentCheckout);
    $process = runLocalDeploymentFixture($this->deploymentCheckout, ['--prepare', '--pause-vite']);

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
        ->and($vite->isRunning())->toBeTrue()
        ->and($process->getOutput())->toContain('skipping npm ci')->not->toContain('Pausing')
        ->and(file($this->deploymentCheckout.'/storage/vite-runs'))->toHaveCount(1);
});

test('gitsave frontend coordination pauses and restores only the blocking project Vite', function (): void {
    if (PHP_OS_FAMILY !== 'Windows') {
        $this->markTestSkipped('Windows process coordination fixture');
    }
    $vite = startFrontendViteProcessFixture($this, $this->deploymentCheckout);
    $other = startFrontendViteProcessFixture($this, $this->deploymentFixture.'/other-schooltool');
    $lock = hash_file('sha256', $this->deploymentCheckout.'/package-lock.json');
    $process = frontendPreparationProcessFixture($this);
    $process->run();

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput().$process->getOutput())
        ->and($vite->isRunning())->toBeFalse()->and($other->isRunning())->toBeTrue()
        ->and(file($this->deploymentCheckout.'/storage/vite-runs'))->toHaveCount(2)
        ->and(is_file($this->deploymentCheckout.'/npm-called'))->toBeTrue()
        ->and(is_file($this->deploymentCheckout.'/storage/framework/frontend-dependencies.sha256.installing'))->toBeFalse()
        ->and(hash_file('sha256', $this->deploymentCheckout.'/package-lock.json'))->toBe($lock);
});

test('gitsave frontend coordination prepares absent packages normally when no project Vite runs', function (): void {
    if (PHP_OS_FAMILY !== 'Windows') {
        $this->markTestSkipped('Windows process coordination fixture');
    }
    $process = frontendPreparationProcessFixture($this);
    $process->run();

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput().$process->getOutput())
        ->and(is_file($this->deploymentCheckout.'/npm-called'))->toBeTrue()
        ->and($process->getOutput())->not->toContain('Pausing', 'Restored');
});

test('gitsave frontend coordination is unavailable outside bounded local preparation', function (array $arguments): void {
    $process = new Process([PHP_BINARY, 'scripts/update.php', '--pause-vite', ...$arguments], $this->deploymentCheckout, ['SCHOOLTOOL_PREVIEW_INSTANCE' => 'false']);
    $process->run();

    expect($process->getExitCode())->toBe(2)
        ->and($process->getOutput())->not->toContain('Composer dependencies', '$ ')
        ->and(is_file($this->deploymentCheckout.'/storage/frontend-installed'))->toBeFalse()
        ->and(is_file($this->deploymentCheckout.'/storage/application-updated'))->toBeFalse();
})->with([
    'full update' => [[]],
    'production' => [['--target=cloudways', '--prepare']],
    'dry run' => [['--target=local', '--prepare', '--dry-run']],
    'development preflight' => [['--target=local', '--prepare', '--dev-preflight']],
]);

test('gitsave frontend coordination keeps failed npm installation incomplete without restarting broken Vite', function (): void {
    if (PHP_OS_FAMILY !== 'Windows') {
        $this->markTestSkipped('Windows process coordination fixture');
    }
    $vite = startFrontendViteProcessFixture($this, $this->deploymentCheckout);
    $process = frontendPreparationProcessFixture($this, 37);
    $process->run();

    expect($process->getExitCode())->toBe(37, $process->getErrorOutput().$process->getOutput())
        ->and($vite->isRunning())->toBeFalse()
        ->and(file($this->deploymentCheckout.'/storage/vite-runs'))->toHaveCount(1)
        ->and(is_file($this->deploymentCheckout.'/storage/framework/frontend-dependencies.sha256.installing'))->toBeTrue()
        ->and($process->getOutput())->toContain('Vite remains stopped');
});

test('gitsave frontend coordination preserves shared PHP services while pausing and restoring managed Vite', function (int $npmExitCode): void {
    if (PHP_OS_FAMILY !== 'Windows') {
        $this->markTestSkipped('Windows process coordination fixture');
    }
    $directory = $this->deploymentCheckout;
    $supervisor = startFrontendViteProcessFixture($this, $directory, 'managed');
    $before = [];
    foreach (['server', 'queue'] as $service) {
        $before[$service] = ['pid' => file_get_contents($directory.'/storage/'.$service.'-pid'),
            'heartbeat' => filesize($directory.'/storage/'.$service.'-heartbeat')];
    }
    $process = frontendPreparationProcessFixture($this, $npmExitCode);
    $process->run();
    expect($process->getExitCode())->toBe($npmExitCode, $process->getOutput().$process->getErrorOutput())
        ->and($supervisor->isRunning())->toBeTrue($supervisor->getOutput().$supervisor->getErrorOutput().$process->getOutput().$process->getErrorOutput());
    clearstatcache();
    foreach (['server', 'queue'] as $service) {
        expect(file_get_contents($directory.'/storage/'.$service.'-pid'))->toBe($before[$service]['pid'])
            ->and(filesize($directory.'/storage/'.$service.'-heartbeat'))->toBeGreaterThan($before[$service]['heartbeat']);
    }
    expect(file($directory.'/storage/vite-runs'))->toHaveCount($npmExitCode === 0 ? 2 : 1);
    if ($npmExitCode !== 0) {
        expect($process->getOutput())->toContain('Only Vite remains paused');
        $retry = frontendPreparationProcessFixture($this);
        $retry->run();
        expect($retry->isSuccessful())->toBeTrue($retry->getOutput().$retry->getErrorOutput())
            ->and($supervisor->isRunning())->toBeTrue()->and(file($directory.'/storage/vite-runs'))->toHaveCount(2);
    }
})->with([0, 37]);

test('gitsave frontend coordination refuses unknown shared and foreign launchers without stopping them', function (string $kind): void {
    if (PHP_OS_FAMILY !== 'Windows') {
        $this->markTestSkipped('Windows process coordination fixture');
    }
    $directory = $kind === 'foreign' ? $this->deploymentFixture.'/other-schooltool' : $this->deploymentCheckout;
    $vite = startFrontendViteProcessFixture($this, $directory, $kind === 'foreign' ? 'vite' : $kind, $this->deploymentCheckout);
    $process = frontendPreparationProcessFixture($this);
    $process->run();

    expect($process->getExitCode())->toBe(1)
        ->and($vite->isRunning())->toBeTrue()
        ->and(is_file($this->deploymentCheckout.'/npm-called'))->toBeFalse()
        ->and(is_file($this->deploymentCheckout.'/storage/framework/frontend-dependencies.sha256.installing'))->toBeFalse();
})->with(['vitest', 'supervised', 'foreign']);

test('gitsave frontend coordination rejects changed process identities before stopping a process', function (): void {
    if (PHP_OS_FAMILY !== 'Windows') {
        $this->markTestSkipped('Windows process coordination fixture');
    }
    $vite = startFrontendViteProcessFixture($this, $this->deploymentCheckout);
    $process = new Process(['powershell.exe', '-NoProfile', '-NonInteractive', '-Command', <<<'POWERSHELL'
. ./scripts/frontend-install.ps1
$plan = @(Get-SchooltoolFrontendPausePlan -Project (Get-Location).Path)
if ($plan.Count -ne 1) { throw 'No owned Vite fixture' }
$expected = $plan[0].Process | Select-Object ProcessId,CreationDate,ExecutablePath,CommandLine,ParentProcessId
$expected.CreationDate = $expected.CreationDate.AddSeconds(-1)
try { Assert-SchooltoolFrontendProcess $expected; exit 5 }
catch { Write-Output $_.Exception.Message; exit 0 }
POWERSHELL], $this->deploymentCheckout);
    $process->run();

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
        ->and($process->getOutput())->toContain('identity changed')->and($vite->isRunning())->toBeTrue();
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

test('development preflight restores missing npm executables without installing packages or running lifecycle scripts', function (string $missing): void {
    $directory = $this->deploymentCheckout;
    createDevelopmentFrontendFixture($directory);
    file_put_contents($directory.'/.npmrc', "bin-links=false\n");
    $before = hash_file('sha256', $directory.'/package-lock.json');

    if ($missing !== 'all') {
        (new Filesystem)->ensureDirectoryExists($directory.'/node_modules/.bin');
        $available = $missing === 'concurrently' ? 'vite' : 'concurrently';
        file_put_contents($directory.'/node_modules/.bin/'.$available.(PHP_OS_FAMILY === 'Windows' ? '.cmd' : ''), 'fixture');
    }

    $process = runLocalDeploymentFixture($directory, ['--dev-preflight']);
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
        ->and($process->getOutput())->toContain('Restoring missing npm development executables')
        ->and(hash_file('sha256', $directory.'/package-lock.json'))->toBe($before)
        ->and(is_file($directory.'/install-script-ran'))->toBeFalse()
        ->and(is_file($directory.'/storage/application-updated'))->toBeFalse()
        ->and(is_file($directory.'/storage/frontend-installed'))->toBeFalse();

    foreach (['concurrently', 'vite'] as $name) {
        $command = Process::fromShellCommandline('npm exec --no -- '.$name, $directory);
        $command->run();
        expect($command->isSuccessful())->toBeTrue($command->getErrorOutput())
            ->and(trim($command->getOutput()))->toBe($name.' fixture');
    }

    $healthy = new Process(
        [PHP_BINARY, 'scripts/update.php', '--target=local', '--dev-preflight'],
        $directory,
        ['PATH' => createDevelopmentNpmFailureFixture($directory, 37), 'SCHOOLTOOL_PREVIEW_INSTANCE' => 'false'],
    );
    $healthy->run();
    expect($healthy->isSuccessful())->toBeTrue($healthy->getErrorOutput())
        ->and($healthy->getOutput())->toBe('')
        ->and(is_file($directory.'/npm-called'))->toBeFalse();
})->with(['all', 'concurrently', 'vite']);

test('development preflight refuses incomplete packages instead of reinstalling them', function (string $missing): void {
    $directory = $this->deploymentCheckout;
    createDevelopmentFrontendFixture($directory);
    unlink($directory.'/node_modules/concurrently/'.$missing);
    $process = new Process(
        [PHP_BINARY, 'scripts/update.php', '--target=local', '--dev-preflight'],
        $directory,
        ['PATH' => createDevelopmentNpmFailureFixture($directory, 37), 'SCHOOLTOOL_PREVIEW_INSTANCE' => 'false'],
    );
    $process->run();

    expect($process->getExitCode())->toBe(1)
        ->and($process->getErrorOutput())->toContain('Run npm ci before composer dev')
        ->and(is_file($directory.'/npm-called'))->toBeFalse()
        ->and(is_file($directory.'/storage/application-updated'))->toBeFalse();
})->with(['package.json', 'cli.js']);

test('development preflight stops when npm repair fails or leaves executables missing', function (int $npmExitCode): void {
    $directory = $this->deploymentCheckout;
    createDevelopmentFrontendFixture($directory);
    $process = new Process(
        [PHP_BINARY, 'scripts/update.php', '--target=local', '--dev-preflight'],
        $directory,
        ['PATH' => createDevelopmentNpmFailureFixture($directory, $npmExitCode), 'SCHOOLTOOL_PREVIEW_INSTANCE' => 'false'],
    );
    $process->run();

    expect($process->getExitCode())->toBe($npmExitCode === 0 ? 1 : $npmExitCode)
        ->and($process->getErrorOutput())->toContain($npmExitCode === 0 ? 'did not restore' : 'Could not restore')
        ->and(is_file($directory.'/npm-called'))->toBeTrue()
        ->and(is_file($directory.'/storage/application-updated'))->toBeFalse();
})->with([0, 37]);
