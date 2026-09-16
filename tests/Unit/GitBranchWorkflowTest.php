<?php

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

function runBranchWorkflowGit(string $directory, string ...$arguments): string
{
    $process = new Process(['git', ...$arguments], $directory);
    $process->mustRun();

    return trim($process->getOutput());
}

function runBranchWorkflowCommand(string $directory, string $command): Process
{
    $bootstrap = <<<'POWERSHELL'
$ErrorActionPreference = 'Stop'
. $env:SCHOOLTOOL_TEST_HELPERS
function Invoke-SchooltoolLocalPreparation { Write-Output 'Local preparation mocked; database untouched.' }
try {
POWERSHELL;
    $process = new Process(
        ['powershell', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-Command', $bootstrap."\n".$command."\n".'} catch { Write-Output $_.Exception.Message; exit 1 }'],
        $directory,
        ['SCHOOLTOOL_TEST_HELPERS' => dirname(__DIR__, 2).'/scripts/git_helpers.ps1'],
        timeout: 60,
    );
    $process->run();

    return $process;
}

function assertBranchWorkflowSucceeded(Process $process): void
{
    expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
}

function commitBranchWorkflowFile(string $directory, string $filename, string $contents): string
{
    file_put_contents($directory.'/'.$filename, $contents);
    runBranchWorkflowGit($directory, 'add', '--', $filename);
    runBranchWorkflowGit($directory, 'commit', '-m', 'Update '.$filename);

    return runBranchWorkflowGit($directory, 'rev-parse', 'HEAD');
}

function branchWorkflowReleaseMocks(): string
{
    return <<<'POWERSHELL'
function php {
    if ($args[0] -eq 'scripts/frontend-release.php') {
        if ($args[1] -eq 'create') {
            [System.IO.Directory]::CreateDirectory((Join-Path (Get-Location) 'deployment')) | Out-Null
            foreach ($name in @('frontend-build.sha256', 'frontend-build.tar.gz', 'source-commit', 'source-manifest.sha256')) {
                [System.IO.File]::WriteAllText((Join-Path (Get-Location) "deployment/$name"), $args[2])
            }
        }
        if ($args[1] -eq 'verify') {
            $source = [System.IO.File]::ReadAllText((Join-Path (Get-Location) 'deployment/source-commit'))
            if ($source -ne $args[2]) { $global:LASTEXITCODE = 1; return }
        }
    }
    $global:LASTEXITCODE = 0
}
function node {
    [System.IO.File]::WriteAllText((Join-Path (Get-Location) 'release-notes.txt'), $args[1])
    $global:LASTEXITCODE = 0
}
function Invoke-SchooltoolReleaseChecks {
    param([switch]$Full)
    if (-not $Full) { throw 'Full checks were not requested.' }
    Write-Host 'FULL_CHECKS_REQUESTED'
}
function Read-Host { 'RELEASE' }
POWERSHELL;
}

beforeEach(function (): void {
    if (PHP_OS_FAMILY !== 'Windows') {
        $this->markTestSkipped('Windows PowerShell branch workflow verification.');
    }

    $this->workflowDirectory = sys_get_temp_dir().'/schooltool-git-workflow-'.bin2hex(random_bytes(8));
    $this->workflowRemote = $this->workflowDirectory.'/origin.git';
    $this->workflowPc = $this->workflowDirectory.'/pc';
    $this->workflowLaptop = $this->workflowDirectory.'/laptop';
    mkdir($this->workflowDirectory);
    runBranchWorkflowGit($this->workflowDirectory, 'init', '--bare', '--initial-branch=main', $this->workflowRemote);
    runBranchWorkflowGit($this->workflowDirectory, 'clone', $this->workflowRemote, $this->workflowPc);

    foreach (['user.name' => 'Workflow Test', 'user.email' => 'workflow@example.test', 'commit.gpgsign' => 'false', 'tag.gpgsign' => 'false', 'core.autocrlf' => 'false', 'core.hooksPath' => '.git/no-test-hooks'] as $key => $value) {
        runBranchWorkflowGit($this->workflowPc, 'config', $key, $value);
    }

    mkdir($this->workflowPc.'/scripts');
    copy(dirname(__DIR__, 2).'/scripts/check-encoding.php', $this->workflowPc.'/scripts/check-encoding.php');
    file_put_contents($this->workflowPc.'/shared.txt', "Original\n");
    runBranchWorkflowGit($this->workflowPc, 'add', '.');
    runBranchWorkflowGit($this->workflowPc, 'commit', '-m', 'Create initial application');
    runBranchWorkflowGit($this->workflowPc, 'push', '-u', 'origin', 'main');
    $this->workflowMain = runBranchWorkflowGit($this->workflowPc, 'rev-parse', 'HEAD');
    runBranchWorkflowGit($this->workflowDirectory, '-c', 'core.autocrlf=false', 'clone', $this->workflowRemote, $this->workflowLaptop);

    foreach (['user.name' => 'Workflow Test', 'user.email' => 'workflow@example.test', 'commit.gpgsign' => 'false', 'tag.gpgsign' => 'false', 'core.autocrlf' => 'false', 'core.hooksPath' => '.git/no-test-hooks'] as $key => $value) {
        runBranchWorkflowGit($this->workflowLaptop, 'config', $key, $value);
    }
});

afterEach(function (): void {
    if (isset($this->workflowDirectory)) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->workflowDirectory, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->isFile()) {
                chmod($file->getPathname(), 0666);
            }
        }
        (new Filesystem)->deleteDirectory($this->workflowDirectory);
    }
});

it('shares unfinished development between two devices without changing main', function (): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"'));
    file_put_contents($this->workflowPc.'/pc.txt', "PC work\n");
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitsave "Add PC work"'));
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowLaptop, 'gitwork "new-function"'));
    expect(file_get_contents($this->workflowLaptop.'/pc.txt'))->toBe("PC work\n");
    file_put_contents($this->workflowLaptop.'/laptop.txt', "Laptop work\n");
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowLaptop, 'gitsave "Add laptop work"'));
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitwork "new-function"'));

    expect(file_get_contents($this->workflowPc.'/laptop.txt'))->toBe("Laptop work\n")
        ->and(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'main'))->toBe($this->workflowMain)
        ->and(runBranchWorkflowGit($this->workflowPc, 'rev-parse', 'HEAD'))->toBe(runBranchWorkflowGit($this->workflowLaptop, 'rev-parse', 'HEAD'));

    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitmain'));
    expect(runBranchWorkflowGit($this->workflowPc, 'branch', '--show-current'))->toBe('main')
        ->and(file_exists($this->workflowPc.'/pc.txt'))->toBeFalse();
});

it('refuses branch switches with unsaved work', function (bool $committed): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"'));
    file_put_contents($this->workflowPc.'/unfinished.txt', "Keep this work\n");

    if ($committed) {
        runBranchWorkflowGit($this->workflowPc, 'add', '.');
        runBranchWorkflowGit($this->workflowPc, 'commit', '-m', 'Keep unpushed work');
    }

    $head = runBranchWorkflowGit($this->workflowPc, 'rev-parse', 'HEAD');
    $result = runBranchWorkflowCommand($this->workflowPc, 'gitmain');

    expect($result->isSuccessful())->toBeFalse()
        ->and(runBranchWorkflowGit($this->workflowPc, 'branch', '--show-current'))->toBe('feature/new-function')
        ->and(runBranchWorkflowGit($this->workflowPc, 'rev-parse', 'HEAD'))->toBe($head)
        ->and(file_get_contents($this->workflowPc.'/unfinished.txt'))->toBe("Keep this work\n");
})->with(['uncommitted changes' => false, 'unpushed commit' => true]);

it('rejects feature-only commands on main and unsafe branch names', function (): void {
    foreach (['gitsave "Accidental publication"', 'gitrelease "Accidental release"', 'gitstart "../main"', 'gitstart "--force"'] as $command) {
        expect(runBranchWorkflowCommand($this->workflowPc, $command)->isSuccessful())->toBeFalse();
    }

    expect(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'main'))->toBe($this->workflowMain)
        ->and(runBranchWorkflowGit($this->workflowPc, 'branch', '--show-current'))->toBe('main');
});

it('merges independent device changes without rewriting either saved commit', function (): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"'));
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowLaptop, 'gitwork "new-function"'));
    file_put_contents($this->workflowLaptop.'/laptop.txt', "Laptop work\n");
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowLaptop, 'gitsave "Save laptop work"'));
    $laptopCommit = runBranchWorkflowGit($this->workflowLaptop, 'rev-parse', 'HEAD');
    $pcCommit = commitBranchWorkflowFile($this->workflowPc, 'pc.txt', "PC work\n");
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitsave "Synchronize both devices"'));

    expect(runBranchWorkflowGit($this->workflowPc, 'merge-base', '--is-ancestor', $laptopCommit, 'HEAD'))->toBe('')
        ->and(runBranchWorkflowGit($this->workflowPc, 'merge-base', '--is-ancestor', $pcCommit, 'HEAD'))->toBe('')
        ->and(file_get_contents($this->workflowPc.'/laptop.txt'))->toBe("Laptop work\n")
        ->and(file_get_contents($this->workflowPc.'/pc.txt'))->toBe("PC work\n")
        ->and(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'main'))->toBe($this->workflowMain);
});

it('preserves both devices commits and stops pushing when changes conflict', function (): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"'));
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowLaptop, 'gitwork "new-function"'));
    file_put_contents($this->workflowPc.'/shared.txt', "PC replacement\n");
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitsave "Save PC replacement"'));
    $remoteCommit = runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'feature/new-function');
    $laptopCommit = commitBranchWorkflowFile($this->workflowLaptop, 'shared.txt', "Laptop replacement\n");
    $result = runBranchWorkflowCommand($this->workflowLaptop, 'gitsave "Synchronize replacements"');

    expect($result->isSuccessful())->toBeFalse()
        ->and(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'feature/new-function'))->toBe($remoteCommit)
        ->and(runBranchWorkflowGit($this->workflowLaptop, 'rev-parse', 'HEAD'))->toBe($laptopCommit)
        ->and(runBranchWorkflowGit($this->workflowLaptop, 'status', '--porcelain'))->toContain('UU shared.txt')
        ->and(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'main'))->toBe($this->workflowMain);
});

it('brings main hotfixes into development without publishing the feature', function (): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"'));
    file_put_contents($this->workflowPc.'/feature.txt', "Unreleased work\n");
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitsave "Save feature"'));
    $hotfix = commitBranchWorkflowFile($this->workflowLaptop, 'hotfix.txt', "Published correction\n");
    runBranchWorkflowGit($this->workflowLaptop, 'push', 'origin', 'main');
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitupdate'));

    expect(runBranchWorkflowGit($this->workflowPc, 'merge-base', '--is-ancestor', $hotfix, 'HEAD'))->toBe('')
        ->and(file_get_contents($this->workflowPc.'/feature.txt'))->toBe("Unreleased work\n")
        ->and(file_get_contents($this->workflowPc.'/hotfix.txt'))->toBe("Published correction\n")
        ->and(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'main'))->toBe($hotfix);
});

it('blocks release until the latest main changes are included', function (): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"'));
    file_put_contents($this->workflowPc.'/feature.txt', "Unreleased work\n");
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitsave "Save feature"'));
    $hotfix = commitBranchWorkflowFile($this->workflowLaptop, 'hotfix.txt', "Published correction\n");
    runBranchWorkflowGit($this->workflowLaptop, 'push', 'origin', 'main');
    $result = runBranchWorkflowCommand($this->workflowPc, <<<'POWERSHELL'
function Invoke-SchooltoolPublish { throw 'UNEXPECTED_PUBLISH_CALL' }
gitrelease 'Release feature'
POWERSHELL);

    expect($result->isSuccessful())->toBeFalse()
        ->and($result->getOutput())->not->toContain('UNEXPECTED_PUBLISH_CALL')
        ->and(runBranchWorkflowGit($this->workflowPc, 'branch', '--show-current'))->toBe('feature/new-function')
        ->and(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'main'))->toBe($hotfix);
});

it('keeps local and remote main unchanged when release validation fails', function (): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"'));
    file_put_contents($this->workflowPc.'/feature.txt', "Unreleased work\n");
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitsave "Save feature"'));
    $feature = runBranchWorkflowGit($this->workflowPc, 'rev-parse', 'HEAD');
    $result = runBranchWorkflowCommand($this->workflowPc, <<<'POWERSHELL'
function Invoke-SchooltoolPublish { throw 'SIMULATED_RELEASE_CHECK_FAILURE' }
gitrelease 'Release feature'
POWERSHELL);

    expect($result->isSuccessful())->toBeFalse()
        ->and($result->getOutput())->toContain('SIMULATED_RELEASE_CHECK_FAILURE')
        ->and(runBranchWorkflowGit($this->workflowPc, 'rev-parse', 'main'))->toBe($this->workflowMain)
        ->and(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'main'))->toBe($this->workflowMain)
        ->and(runBranchWorkflowGit($this->workflowPc, 'rev-parse', 'feature/new-function'))->toBe($feature)
        ->and(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'feature/new-function'))->toBe($feature);
});

it('publishes a fully checked release with an optional version through the actual pipeline', function (?string $version): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"'));
    file_put_contents($this->workflowPc.'/feature.txt', "Completed work\n");
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitsave "Finish feature"'));
    $feature = runBranchWorkflowGit($this->workflowPc, 'rev-parse', 'HEAD');
    $command = branchWorkflowReleaseMocks()."\n".'gitrelease "Release feature"'.($version === null ? '' : ' "'.$version.'"');
    $result = runBranchWorkflowCommand($this->workflowPc, $command);
    assertBranchWorkflowSucceeded($result);
    $release = runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'main');

    expect($result->getOutput())->toContain('FULL_CHECKS_REQUESTED')
        ->and(runBranchWorkflowGit($this->workflowPc, 'branch', '--show-current'))->toBe('main')
        ->and(runBranchWorkflowGit($this->workflowPc, 'rev-parse', 'main'))->toBe($release)
        ->and(runBranchWorkflowGit($this->workflowRemote, 'show', 'main:feature.txt'))->toBe('Completed work')
        ->and(runBranchWorkflowGit($this->workflowRemote, 'show', 'main:deployment/source-commit'))->toBe(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'main^'))
        ->and(runBranchWorkflowGit($this->workflowPc, 'rev-parse', 'feature/new-function'))->toBe($feature)
        ->and(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'feature/new-function'))->toBe($feature);

    if ($version !== null) {
        expect(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'v'.$version.'^{}'))->toBe($release)
            ->and(runBranchWorkflowGit($this->workflowRemote, 'show', 'main:release-notes.txt'))->toBe($version);
    } else {
        expect(runBranchWorkflowGit($this->workflowRemote, 'tag', '--list'))->toBe('');
    }
})->with(['without version increase' => null, 'with version increase' => '3.48.0']);

it('stops the actual release pipeline when checks fail or publication is cancelled', function (string $failure): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"'));
    file_put_contents($this->workflowPc.'/feature.txt', "Completed work\n");
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitsave "Finish feature"'));
    $override = $failure === 'checks'
        ? "function Invoke-SchooltoolReleaseChecks { throw 'SIMULATED_CHECK_FAILURE' }"
        : "function Read-Host { 'CANCEL' }";
    $result = runBranchWorkflowCommand($this->workflowPc, branchWorkflowReleaseMocks()."\n".$override."\n".'gitrelease "Release feature"');

    expect($result->isSuccessful())->toBeFalse()
        ->and($result->getOutput())->toContain($failure === 'checks' ? 'SIMULATED_CHECK_FAILURE' : 'Release cancelled')
        ->and(runBranchWorkflowGit($this->workflowPc, 'rev-parse', 'main'))->toBe($this->workflowMain)
        ->and(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'main'))->toBe($this->workflowMain)
        ->and(runBranchWorkflowGit($this->workflowPc, 'branch', '--show-current'))->toBe('feature/new-function');
})->with(['failed checks' => 'checks', 'cancelled confirmation' => 'confirmation']);

it('does not bypass full checks when the feature already contains a complete release artifact', function (): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"'));
    $source = commitBranchWorkflowFile($this->workflowPc, 'feature.txt', "Completed work\n");
    mkdir($this->workflowPc.'/deployment');
    foreach (['frontend-build.sha256', 'frontend-build.tar.gz', 'source-commit', 'source-manifest.sha256'] as $filename) {
        file_put_contents($this->workflowPc.'/deployment/'.$filename, $source);
    }
    runBranchWorkflowGit($this->workflowPc, 'add', '.');
    runBranchWorkflowGit($this->workflowPc, 'commit', '-m', 'Existing release artifact');
    runBranchWorkflowGit($this->workflowPc, 'push', 'origin', 'HEAD:feature/new-function');
    $command = branchWorkflowReleaseMocks()."\n".<<<'POWERSHELL'
function Invoke-SchooltoolReleaseChecks { throw 'RESUMED_RELEASE_CHECKED' }
gitrelease 'Release existing artifact'
POWERSHELL;
    $result = runBranchWorkflowCommand($this->workflowPc, $command);

    expect($result->isSuccessful())->toBeFalse()
        ->and($result->getOutput())->toContain('RESUMED_RELEASE_CHECKED')
        ->and(runBranchWorkflowGit($this->workflowPc, 'rev-parse', 'main'))->toBe($this->workflowMain)
        ->and(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'main'))->toBe($this->workflowMain);
});

it('installs idempotent profile wrappers and dispatches arguments only for a trusted remote', function (): void {
    copy(dirname(__DIR__, 2).'/scripts/install_powershell_helpers.ps1', $this->workflowPc.'/scripts/install_powershell_helpers.ps1');
    file_put_contents($this->workflowPc.'/scripts/git_workflow.ps1', <<<'POWERSHELL'
param([string]$Command, [string[]]$CommandArguments)
$entry = @{ command = $Command; arguments = @($CommandArguments) } | ConvertTo-Json -Compress
[System.IO.File]::AppendAllText((Join-Path (Get-Location) '.git/workflow-dispatch.jsonl'), $entry + [Environment]::NewLine)
POWERSHELL);
    runBranchWorkflowGit($this->workflowPc, 'remote', 'set-url', 'origin', 'https://github.com/ITStudioAT/schooltool.git');
    $result = runBranchWorkflowCommand($this->workflowPc, <<<'POWERSHELL'
$PROFILE = [pscustomobject]@{ CurrentUserCurrentHost = (Join-Path (Get-Location) '.git/test-profile.ps1') }
$documentsDirectory = Join-Path (Get-Location) '.git/test-documents'
$coreProfile = Join-Path $documentsDirectory 'PowerShell/Microsoft.PowerShell_profile.ps1'
[System.IO.Directory]::CreateDirectory((Split-Path -Parent $coreProfile)) | Out-Null
[System.IO.File]::WriteAllText($coreProfile, "# Preserve existing PowerShell 7 settings`n")
& ./scripts/install_powershell_helpers.ps1 -DocumentsDirectory $documentsDirectory
& ./scripts/install_powershell_helpers.ps1 -DocumentsDirectory $documentsDirectory
$tokens = $null
$parseErrors = $null
[System.Management.Automation.Language.Parser]::ParseFile($PROFILE.CurrentUserCurrentHost, [ref]$tokens, [ref]$parseErrors) | Out-Null
if ($parseErrors.Count -ne 0) { throw 'Generated profile does not parse.' }
. $PROFILE.CurrentUserCurrentHost
gitstart 'new-function'
gitwork 'new-function'
gitmain
gitsave 'Message with spaces'
gitupdate
gitrelease 'Release with spaces'
gitrelease 'Versioned release' '3.48.0'
gitcheck
git remote set-url --push origin https://example.invalid/other.git
try { gitsave 'Must be blocked'; throw 'UNTRUSTED_DISPATCH_ALLOWED' } catch {
    if ($_.Exception.Message -eq 'UNTRUSTED_DISPATCH_ALLOWED') { throw }
    Write-Host 'UNTRUSTED_REMOTE_BLOCKED'
}
POWERSHELL);
    assertBranchWorkflowSucceeded($result);
    $profile = file_get_contents($this->workflowPc.'/.git/test-profile.ps1');
    foreach (['WindowsPowerShell', 'PowerShell'] as $edition) {
        $editionProfile = file_get_contents($this->workflowPc.'/.git/test-documents/'.$edition.'/Microsoft.PowerShell_profile.ps1');
        expect(substr_count($editionProfile, '# >>> project git dispatcher >>>'))->toBe(1)
            ->and($editionProfile)->toContain('function gitcheck {', 'function gitrelease {');
        if ($edition === 'PowerShell') {
            expect($editionProfile)->toContain('# Preserve existing PowerShell 7 settings');
        }
    }
    $entries = array_map(
        function (string $line): array {
            $entry = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

            return ['command' => $entry['command'], 'arguments' => $entry['arguments']];
        },
        file($this->workflowPc.'/.git/workflow-dispatch.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES),
    );

    expect(substr_count($profile, '# >>> project git dispatcher >>>'))->toBe(1)
        ->and($entries)->toBe([
            ['command' => 'gitstart', 'arguments' => ['new-function']],
            ['command' => 'gitwork', 'arguments' => ['new-function']],
            ['command' => 'gitmain', 'arguments' => []],
            ['command' => 'gitsave', 'arguments' => ['Message with spaces']],
            ['command' => 'gitupdate', 'arguments' => []],
            ['command' => 'gitrelease', 'arguments' => ['Release with spaces']],
            ['command' => 'gitrelease', 'arguments' => ['Versioned release', '3.48.0']],
            ['command' => 'gitcheck', 'arguments' => []],
        ])
        ->and($result->getOutput())->toContain('UNTRUSTED_REMOTE_BLOCKED');
});

it('atomically rejects publication if main advances after release confirmation begins', function (): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"'));
    file_put_contents($this->workflowPc.'/feature.txt', "Completed work\n");
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitsave "Finish feature"'));
    $hotfix = commitBranchWorkflowFile($this->workflowLaptop, 'hotfix.txt', "Concurrent hotfix\n");
    $laptopPath = str_replace("'", "''", $this->workflowLaptop);
    $override = '$raceLaptop = \''.$laptopPath."'\n".<<<'POWERSHELL'
function Read-Host {
    Invoke-SchooltoolGit -C $raceLaptop push origin main
    'RELEASE'
}
gitrelease 'Release feature' '3.48.0'
POWERSHELL;
    $result = runBranchWorkflowCommand($this->workflowPc, branchWorkflowReleaseMocks()."\n".$override);

    expect($result->isSuccessful())->toBeFalse()
        ->and($result->getOutput())->toContain('atomic release push failed')
        ->and(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'main'))->toBe($hotfix)
        ->and(runBranchWorkflowGit($this->workflowPc, 'rev-parse', 'main'))->toBe($this->workflowMain)
        ->and(runBranchWorkflowGit($this->workflowRemote, 'tag', '--list'))->toBe('');
});

it('dispatches the real workflow entrypoint and rejects releasing main', function (): void {
    $entrypoint = dirname(__DIR__, 2).'/scripts/git_workflow.ps1';
    $check = new Process(['powershell', '-NoProfile', '-NonInteractive', '-File', $entrypoint, '-Command', 'gitcheck'], $this->workflowPc);
    $check->run();
    assertBranchWorkflowSucceeded($check);

    $release = new Process(['powershell', '-NoProfile', '-NonInteractive', '-File', $entrypoint, '-Command', 'gitrelease', 'Release with spaces', '3.48.0'], $this->workflowPc);
    $release->run();

    expect($check->getOutput())->toContain('Branch: main')
        ->and($release->isSuccessful())->toBeFalse()
        ->and($release->getErrorOutput())->toContain('requires a feature branch')
        ->and(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'main'))->toBe($this->workflowMain);
});
