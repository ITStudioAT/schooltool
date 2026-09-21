<?php

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

function runBranchWorkflowGit(string $directory, string ...$arguments): string
{
    $process = new Process(['git', ...$arguments], $directory);
    $process->mustRun();

    return trim($process->getOutput());
}

function runBranchWorkflowCommand(string $directory, string $command, string $powershell = 'powershell'): Process
{
    $bootstrap = <<<'POWERSHELL'
$ErrorActionPreference = 'Stop'
. $env:SCHOOLTOOL_TEST_HELPERS
function Invoke-SchooltoolLocalPreparation { Write-Output 'Local preparation mocked; database untouched.' }
function New-SchooltoolCandidateTestDatabase { [pscustomobject]@{ Database = 'pest_test_test_123456789012345678901234'; ReceiptPath = 'schooltool-test-db-mocked.json' } }
function Remove-SchooltoolCandidateTestDatabase { Write-Host 'MOCK_TEST_DATABASE_REMOVED' }
try {
POWERSHELL;
    $process = new Process(
        [$powershell, '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-Command', $bootstrap."\n".$command."\n".'} catch { Write-Output $_.Exception.Message; exit 1 }'],
        $directory,
        [
            'SCHOOLTOOL_TEST_HELPERS' => dirname(__DIR__, 2).'/scripts/git_helpers.ps1',
            'TEMP' => dirname($directory),
            'TMP' => dirname($directory),
        ],
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

function branchWorkflowBatchEnvironment(): string
{
    return <<<'POWERSHELL'
function New-SchooltoolCandidateTestDatabase {
    $receipt = Join-Path $env:TEMP 'schooltool-test-db-batches.json'
    [System.IO.File]::WriteAllText($receipt, '{"format":"schooltool-owned-test-database-v1","host":"127.0.0.1","port":3306,"database":"pest_test_test_123456789012345678901234","state":"created"}')
    [pscustomobject]@{ Database = 'pest_test_test_123456789012345678901234'; ReceiptPath = $receipt }
}

$candidate = New-SchooltoolCandidateWorktree -Kind release -SourceCommit HEAD
$state = Enter-SchooltoolCandidateEnvironment -Candidate $candidate
$logPrefix = Join-Path $env:TEMP 'batch-check'
POWERSHELL;
}

function branchWorkflowPreviewMocks(): string
{
    return branchWorkflowReleaseMocks()."\n".<<<'POWERSHELL'
function Read-Host { 'PREVIEW' }
function Get-SchooltoolPreviewTarget { [pscustomobject]@{ Ssh = 'schooltool-feature@example.test'; Path = '/home/example/applications/preview/public_html' } }
function Get-SchooltoolDeploymentTarget { [pscustomobject]@{ Ssh = 'schooltool-main@example.test'; Path = '/home/example/applications/main/public_html' } }
function Invoke-SchooltoolRemoteJson { [pscustomobject]@{ public_key = ('a' * 64); needs_snapshot = $false } }
function Send-SchooltoolPreview { Write-Host 'PREVIEW_UPLOAD_REQUESTED' }
function ConvertTo-LegacyPreviewTestReceipt {
    param($Receipt)
    $old = $Receipt.Candidate
    $branch = 'codex/preview-' + $old.Id
    $archive = 'refs/schooltool/archived-heads/20260921-' + ('a' * 32) + '/' + $branch
    Invoke-SchooltoolGit update-ref --no-deref $archive $Receipt.ArtifactCommit ('0' * 40)
    $Receipt.Format = 'schooltool-preview-v1'
    $Receipt.Candidate = [pscustomobject]@{ Branch = $branch; Path = $old.Path }
}
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
    file_put_contents($this->workflowPc.'/.gitignore', ".env\n");
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
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowLaptop, 'gitwork'));
    expect(file_get_contents($this->workflowLaptop.'/pc.txt'))->toBe("PC work\n");
    file_put_contents($this->workflowLaptop.'/laptop.txt', "Laptop work\n");
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowLaptop, 'gitsave "Add laptop work"'));
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitwork'));

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
    foreach (['gitrelease "Accidental release"', 'gitstart "../main"', 'gitstart "--force"'] as $command) {
        expect(runBranchWorkflowCommand($this->workflowPc, $command)->isSuccessful())->toBeFalse();
    }

    expect(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'main'))->toBe($this->workflowMain)
        ->and(runBranchWorkflowGit($this->workflowPc, 'branch', '--show-current'))->toBe('main');
});

it('refuses divergent device changes without merging or rewriting either saved commit', function (): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"'));
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowLaptop, 'gitwork "new-function"'));
    file_put_contents($this->workflowLaptop.'/laptop.txt', "Laptop work\n");
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowLaptop, 'gitsave "Save laptop work"'));
    $laptopCommit = runBranchWorkflowGit($this->workflowLaptop, 'rev-parse', 'HEAD');
    $pcCommit = commitBranchWorkflowFile($this->workflowPc, 'pc.txt', "PC work\n");
    $result = runBranchWorkflowCommand($this->workflowPc, 'gitsave "Synchronize both devices"');

    expect($result->isSuccessful())->toBeFalse()
        ->and($result->getOutput())->toContain('Nothing was merged or committed')
        ->and(runBranchWorkflowGit($this->workflowPc, 'rev-parse', 'HEAD'))->toBe($pcCommit)
        ->and(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'feature/new-function'))->toBe($laptopCommit)
        ->and(file_exists($this->workflowPc.'/.git/MERGE_HEAD'))->toBeFalse()
        ->and(file_exists($this->workflowPc.'/laptop.txt'))->toBeFalse()
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
        ->and(runBranchWorkflowGit($this->workflowLaptop, 'status', '--porcelain'))->toBe('')
        ->and(file_get_contents($this->workflowLaptop.'/shared.txt'))->toBe("Laptop replacement\n")
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

it('includes the latest main automatically in the isolated release candidate', function (): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"'));
    file_put_contents($this->workflowPc.'/feature.txt', "Unreleased work\n");
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitsave "Save feature"'));
    $hotfix = commitBranchWorkflowFile($this->workflowLaptop, 'hotfix.txt', "Published correction\n");
    runBranchWorkflowGit($this->workflowLaptop, 'push', 'origin', 'main');
    $result = runBranchWorkflowCommand($this->workflowPc, branchWorkflowReleaseMocks()."\n"."gitrelease 'Release feature'");
    assertBranchWorkflowSucceeded($result);

    expect($result->getOutput())->toContain('FULL_CHECKS_REQUESTED')
        ->and(runBranchWorkflowGit($this->workflowPc, 'branch', '--show-current'))->toBe('main')
        ->and(runBranchWorkflowGit($this->workflowRemote, 'merge-base', '--is-ancestor', $hotfix, 'main'))->toBe('')
        ->and(runBranchWorkflowGit($this->workflowRemote, 'show', 'main:hotfix.txt'))->toBe('Published correction');
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
        ->and(runBranchWorkflowGit($this->workflowPc, 'for-each-ref', '--format=%(refname)', 'refs/heads/feature/'))->toBe('')
        ->and(runBranchWorkflowGit($this->workflowRemote, 'for-each-ref', '--format=%(refname)', 'refs/heads/feature/', 'refs/heads/codex/active-feature'))->toBe('');

    if ($version !== null) {
        expect(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'v'.$version.'^{}'))->toBe($release)
            ->and(runBranchWorkflowGit($this->workflowRemote, 'show', 'main:release-notes.txt'))->toBe($version);
    } else {
        expect(runBranchWorkflowGit($this->workflowRemote, 'tag', '--list'))->toBe('');
    }
})->with(['without version increase' => null, 'with version increase' => '3.48.0']);

it('prepares local main after publication and reports preparation failures without undoing the release', function (bool $failPreparation, string $powershell): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"', $powershell));
    file_put_contents($this->workflowPc.'/feature.txt', "Completed work\n");
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitsave "Finish feature"', $powershell));
    $feature = runBranchWorkflowGit($this->workflowPc, 'rev-parse', 'HEAD');
    $command = branchWorkflowReleaseMocks()."\n".<<<'POWERSHELL'
function Invoke-SchooltoolLocalPreparation {
    if ((Invoke-SchooltoolGit branch --show-current) -ne 'main') { throw 'Preparation ran on the wrong branch.' }
    if ($script:SchooltoolActiveCandidateEnvironment) { throw 'Candidate environment was not restored.' }
    if ((Invoke-SchooltoolGit rev-parse HEAD) -ne (Invoke-SchooltoolGit ls-remote origin refs/heads/main).Split("`t")[0]) {
        throw 'Preparation ran before publication completed.'
    }
    Write-Host 'LOCAL_PUBLISHED_MAIN_PREPARATION'
    if (FAIL_PREPARATION) { throw 'LOCAL_DEPENDENCY_FAILURE' }
}
gitrelease 'Release feature'
POWERSHELL;
    $command = str_replace('FAIL_PREPARATION', $failPreparation ? '$true' : '$false', $command);
    $result = runBranchWorkflowCommand($this->workflowPc, $command, $powershell);
    assertBranchWorkflowSucceeded($result);

    expect($result->getOutput())->toContain('LOCAL_PUBLISHED_MAIN_PREPARATION')
        ->and(runBranchWorkflowGit($this->workflowPc, 'rev-parse', 'HEAD'))->toBe(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'main'))
        ->and(runBranchWorkflowGit($this->workflowRemote, 'show', 'main:feature.txt'))->toBe('Completed work')
        ->and(runBranchWorkflowGit($this->workflowRemote, 'for-each-ref', '--format=%(refname)', 'refs/heads/feature/', 'refs/heads/codex/active-feature'))->toBe('');

    if ($failPreparation) {
        expect($result->getOutput())->toContain('Release is already published. Local main preparation failed: LOCAL_DEPENDENCY_FAILURE')
            ->toContain('run gitmain to retry. Do not publish the release again.')
            ->toContain('After gitmain succeeds, remove the integrated local branch if still present: git branch -d feature/new-function')
            ->not->toContain('Release stopped.');
        expect(runBranchWorkflowGit($this->workflowPc, 'rev-parse', 'feature/new-function'))->toBe($feature);
        $published = runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'main');
        $retry = runBranchWorkflowCommand($this->workflowPc, 'gitmain', $powershell);
        assertBranchWorkflowSucceeded($retry);
        expect($retry->getOutput())->toContain('Local preparation mocked; database untouched.')
            ->and(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'main'))->toBe($published)
            ->and(runBranchWorkflowGit($this->workflowPc, 'rev-parse', 'feature/new-function'))->toBe($feature);
    } else {
        expect($result->getOutput())->not->toContain('Local main preparation failed:');
        expect(runBranchWorkflowGit($this->workflowPc, 'for-each-ref', '--format=%(refname)', 'refs/heads/feature/'))->toBe('');
    }
})->with(['preparation succeeds' => false, 'preparation fails after publication' => true])
    ->with(['Windows PowerShell' => 'powershell', 'PowerShell 7' => 'pwsh']);

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
    $recovery = runBranchWorkflowGit($this->workflowPc, 'for-each-ref', '--format=%(refname)', 'refs/schooltool/candidates/release/');
    expect($recovery)->not->toBe('')
        ->and(runBranchWorkflowGit($this->workflowPc, 'show', "$recovery:feature.txt"))->toBe('Completed work')
        ->and(runBranchWorkflowGit($this->workflowPc, 'for-each-ref', '--format=%(refname)', 'refs/heads/'))->toBe("refs/heads/feature/new-function\nrefs/heads/main");
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
gitwork
gitmain
gitsave 'Message with spaces'
gitsave 'Versioned main' '3.48.0'
gitupdate
gitrelease 'Release with spaces'
gitrelease 'Versioned release' '3.48.0'
gitcheck
gitpreview -RefreshData
gitpreview resume '0123456789abcdef0123456789abcdef' -RefreshData
gitdeploy
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
            ['command' => 'gitwork', 'arguments' => []],
            ['command' => 'gitmain', 'arguments' => []],
            ['command' => 'gitsave', 'arguments' => ['Message with spaces']],
            ['command' => 'gitsave', 'arguments' => ['Versioned main', '3.48.0']],
            ['command' => 'gitupdate', 'arguments' => []],
            ['command' => 'gitrelease', 'arguments' => ['Release with spaces']],
            ['command' => 'gitrelease', 'arguments' => ['Versioned release', '3.48.0']],
            ['command' => 'gitcheck', 'arguments' => []],
            ['command' => 'gitpreview', 'arguments' => ['-RefreshData']],
            ['command' => 'gitpreview', 'arguments' => ['resume', '0123456789abcdef0123456789abcdef', '-RefreshData']],
            ['command' => 'gitdeploy', 'arguments' => []],
        ])
        ->and($result->getOutput())->toContain('UNTRUSTED_REMOTE_BLOCKED');
});

it('reserves exactly one active feature and rejects a second name on either device', function (): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "first-feature"'));
    $reservation = json_decode(runBranchWorkflowGit($this->workflowRemote, 'show', 'codex/active-feature:feature.json'), true, flags: JSON_THROW_ON_ERROR);
    $parents = explode(' ', runBranchWorkflowGit($this->workflowRemote, 'rev-list', '--parents', '-n', '1', 'codex/active-feature'));

    foreach ([$this->workflowPc, $this->workflowLaptop] as $device) {
        $result = runBranchWorkflowCommand($device, 'gitstart "second-feature"');
        expect($result->isSuccessful())->toBeFalse()
            ->and($result->getOutput())->toContain('already active');
    }

    expect($reservation['branch'])->toBe('feature/first-feature')
        ->and($reservation['id'])->toMatch('/^[a-f0-9]{32}$/')
        ->and($parents)->toHaveCount(1)
        ->and(runBranchWorkflowGit($this->workflowRemote, 'for-each-ref', '--format=%(refname)', 'refs/heads/feature/'))->toBe('refs/heads/feature/first-feature');
});

it('atomically rejects a concurrent feature reservation even from the same main commit', function (string $otherName): void {
    $laptop = str_replace("'", "''", $this->workflowLaptop);
    $command = '$raceLaptop = \''.$laptop."'\n".'$otherFeature = \''.$otherName."'\n".<<<'POWERSHELL'
$nativeGit = (Get-Command git -CommandType Application | Select-Object -First 1).Source
$script:injected = $false
function git {
    if (-not $script:injected -and $args[0] -eq 'push' -and $args -contains '--atomic') {
        $script:injected = $true
        Push-Location -LiteralPath $raceLaptop
        try {
            $other = New-SchooltoolFeatureReservation "feature/$otherFeature"
            $main = & $nativeGit rev-parse HEAD
            & $nativeGit push --atomic '--force-with-lease=refs/heads/codex/active-feature:' origin "$($other.ReservationCommit):refs/heads/codex/active-feature" "${main}:refs/heads/feature/$otherFeature"
            if ($LASTEXITCODE -ne 0) { throw 'Could not simulate concurrent start.' }
        }
        finally { Pop-Location }
    }
    & $nativeGit @args
}
gitstart 'new-function'
POWERSHELL;
    $result = runBranchWorkflowCommand($this->workflowPc, $command);
    $metadata = json_decode(runBranchWorkflowGit($this->workflowRemote, 'show', 'codex/active-feature:feature.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($result->isSuccessful())->toBeFalse()
        ->and(runBranchWorkflowGit($this->workflowPc, 'branch', '--show-current'))->toBe('main')
        ->and($metadata['branch'])->toBe('feature/'.$otherName)
        ->and(runBranchWorkflowGit($this->workflowRemote, 'for-each-ref', '--format=%(refname)', 'refs/heads/feature/'))->toBe('refs/heads/feature/'.$otherName)
        ->and(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'main'))->toBe($this->workflowMain);
})->with(['another name' => 'other-feature', 'same name' => 'new-function']);

it('registers a single legacy feature and refuses ambiguous legacy branches', function (bool $ambiguous): void {
    runBranchWorkflowGit($this->workflowPc, 'push', 'origin', 'HEAD:refs/heads/feature/old-feature');
    if ($ambiguous) {
        runBranchWorkflowGit($this->workflowPc, 'push', 'origin', 'HEAD:refs/heads/feature/another-feature');
    }
    $result = runBranchWorkflowCommand($this->workflowLaptop, 'gitwork');

    if ($ambiguous) {
        expect($result->isSuccessful())->toBeFalse()
            ->and(runBranchWorkflowGit($this->workflowRemote, 'for-each-ref', '--format=%(refname)', 'refs/heads/codex/active-feature'))->toBe('');

        return;
    }

    assertBranchWorkflowSucceeded($result);
    expect($result->getOutput())->toContain('registered for this workflow')
        ->and(runBranchWorkflowGit($this->workflowLaptop, 'branch', '--show-current'))->toBe('feature/old-feature');
})->with(['one legacy feature' => false, 'ambiguous legacy features' => true]);

it('saves main with full checks and an optional version', function (?string $version): void {
    file_put_contents($this->workflowPc.'/fix.txt', "Main correction\n");
    $result = runBranchWorkflowCommand($this->workflowPc, branchWorkflowReleaseMocks()."\n".'gitsave "Save main correction"'.($version ? ' "'.$version.'"' : ''));
    assertBranchWorkflowSucceeded($result);

    expect($result->getOutput())->toContain('FULL_CHECKS_REQUESTED')
        ->and(runBranchWorkflowGit($this->workflowRemote, 'show', 'main:fix.txt'))->toBe('Main correction')
        ->and(runBranchWorkflowGit($this->workflowRemote, 'tag', '--list'))->toBe($version ? 'v'.$version : '');
})->with(['without version' => null, 'with version' => '3.48.0']);

it('does not merge remote main into unsaved local changes', function (): void {
    file_put_contents($this->workflowPc.'/fix.txt', "Keep my correction\n");
    $hotfix = commitBranchWorkflowFile($this->workflowLaptop, 'other.txt', "Other device\n");
    runBranchWorkflowGit($this->workflowLaptop, 'push', 'origin', 'main');
    $result = runBranchWorkflowCommand($this->workflowPc, branchWorkflowReleaseMocks()."\n".'gitsave "Save correction"');

    expect($result->isSuccessful())->toBeFalse()
        ->and($result->getOutput())->toContain('Nothing was merged')
        ->and(runBranchWorkflowGit($this->workflowPc, 'rev-parse', 'HEAD'))->toBe($this->workflowMain)
        ->and(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'main'))->toBe($hotfix)
        ->and(file_get_contents($this->workflowPc.'/fix.txt'))->toBe("Keep my correction\n");
});

it('rejects a version on a feature without committing its changes', function (): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"'));
    file_put_contents($this->workflowPc.'/feature.txt', "Keep feature work\n");
    $result = runBranchWorkflowCommand($this->workflowPc, 'gitsave "Feature work" "3.48.0"');

    expect($result->isSuccessful())->toBeFalse()
        ->and($result->getOutput())->toContain('Versions can only')
        ->and(runBranchWorkflowGit($this->workflowPc, 'rev-parse', 'HEAD'))->toBe($this->workflowMain)
        ->and(runBranchWorkflowGit($this->workflowPc, 'status', '--porcelain'))->toContain('?? feature.txt');
});

it('never resurrects a feature deleted after saving starts', function (): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"'));
    file_put_contents($this->workflowPc.'/feature.txt', "Keep committed work\n");
    $command = <<<'POWERSHELL'
$nativeGit = (Get-Command git -CommandType Application | Select-Object -First 1).Source
function git {
    if ($args[0] -eq 'push' -and $args -contains '--set-upstream') {
        & $nativeGit push origin ':refs/heads/feature/new-function'
        if ($LASTEXITCODE -ne 0) { throw 'Could not simulate branch deletion.' }
    }
    & $nativeGit @args
}
gitsave 'Preserve my work'
POWERSHELL;
    $result = runBranchWorkflowCommand($this->workflowPc, $command);

    expect($result->isSuccessful())->toBeFalse()
        ->and($result->getOutput())->toContain('remain committed locally')
        ->and(runBranchWorkflowGit($this->workflowPc, 'show', 'HEAD:feature.txt'))->toBe('Keep committed work')
        ->and(runBranchWorkflowGit($this->workflowRemote, 'for-each-ref', '--format=%(refname)', 'refs/heads/feature/'))->toBe('');
});

it('checks releases in a separate worktree and preserves new edits in the original checkout', function (): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"'));
    file_put_contents($this->workflowPc.'/feature.txt', "Ready work\n");
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitsave "Save feature"'));
    $pc = str_replace("'", "''", $this->workflowPc);
    $command = branchWorkflowReleaseMocks()."\n".'$originalCheckout = [System.IO.Path]::GetFullPath(\''.$pc."')\n".<<<'POWERSHELL'
function Invoke-SchooltoolReleaseChecks {
    param([switch]$Full)
    if ((Get-Location).Path -eq $originalCheckout) { throw 'Checks used original checkout.' }
    if ((Invoke-SchooltoolGit -C $originalCheckout branch --show-current) -ne 'feature/new-function') { throw 'Original branch changed during checks.' }
    [System.IO.File]::WriteAllText((Join-Path $originalCheckout 'new-edit.txt'), 'New unsaved work')
}
gitrelease 'Release completed work'
POWERSHELL;
    $result = runBranchWorkflowCommand($this->workflowPc, $command);
    assertBranchWorkflowSucceeded($result);

    expect($result->getOutput())->toContain('Release succeeded, but local cleanup stopped')
        ->and(runBranchWorkflowGit($this->workflowPc, 'branch', '--show-current'))->toBe('feature/new-function')
        ->and(file_get_contents($this->workflowPc.'/new-edit.txt'))->toBe('New unsaved work')
        ->and(runBranchWorkflowGit($this->workflowRemote, 'show', 'main:feature.txt'))->toBe('Ready work');
});

it('lets the other device leave a safely merged deleted feature but preserves unpublished work', function (bool $unpublished): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"'));
    file_put_contents($this->workflowPc.'/feature.txt', "Ready work\n");
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitsave "Save feature"'));
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowLaptop, 'gitwork'));
    if ($unpublished) {
        commitBranchWorkflowFile($this->workflowLaptop, 'laptop.txt', "Unpublished laptop work\n");
    }
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, branchWorkflowReleaseMocks()."\n".'gitrelease "Release feature"'));
    $result = runBranchWorkflowCommand($this->workflowLaptop, 'gitmain');

    if ($unpublished) {
        expect($result->isSuccessful())->toBeFalse()
            ->and(runBranchWorkflowGit($this->workflowLaptop, 'branch', '--show-current'))->toBe('feature/new-function')
            ->and(file_get_contents($this->workflowLaptop.'/laptop.txt'))->toBe("Unpublished laptop work\n");

        return;
    }

    assertBranchWorkflowSucceeded($result);
    expect(runBranchWorkflowGit($this->workflowLaptop, 'branch', '--show-current'))->toBe('main');
})->with(['fully merged' => false, 'additional local commits' => true]);

it('atomically preserves newer feature commits and the reservation during release confirmation', function (): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"'));
    file_put_contents($this->workflowPc.'/feature.txt', "Ready work\n");
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitsave "Save feature"'));
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowLaptop, 'gitwork'));
    $newHead = commitBranchWorkflowFile($this->workflowLaptop, 'laptop.txt', "Newer feature work\n");
    $reservation = runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'codex/active-feature');
    $laptop = str_replace("'", "''", $this->workflowLaptop);
    $command = branchWorkflowReleaseMocks()."\n".'$raceLaptop = \''.$laptop."'\n".<<<'POWERSHELL'
function Read-Host {
    Invoke-SchooltoolGit -C $raceLaptop push origin feature/new-function
    'RELEASE'
}
gitrelease 'Release feature' '3.48.0'
POWERSHELL;
    $result = runBranchWorkflowCommand($this->workflowPc, $command);

    expect($result->isSuccessful())->toBeFalse()
        ->and($result->getOutput())->toContain('atomic release push failed')
        ->and(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'main'))->toBe($this->workflowMain)
        ->and(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'feature/new-function'))->toBe($newHead)
        ->and(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'codex/active-feature'))->toBe($reservation)
        ->and(runBranchWorkflowGit($this->workflowRemote, 'tag', '--list'))->toBe('');
});

it('does not publish files edited while main release checks are running', function (): void {
    file_put_contents($this->workflowPc.'/fix.txt', "Original correction\n");
    $command = branchWorkflowReleaseMocks()."\n".<<<'POWERSHELL'
function Invoke-SchooltoolReleaseChecks {
    [System.IO.File]::WriteAllText((Join-Path (Get-Location) 'fix.txt'), 'Changed during tests')
}
gitsave 'Save correction'
POWERSHELL;
    $result = runBranchWorkflowCommand($this->workflowPc, $command);

    expect($result->isSuccessful())->toBeFalse()
        ->and($result->getOutput())->toContain('changed during checks')
        ->and(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'main'))->toBe($this->workflowMain)
        ->and(file_get_contents($this->workflowPc.'/fix.txt'))->toBe('Changed during tests');
});

it('runs main full checks in an isolated staged-source worktree and restores the original environment', function (bool $failChecks): void {
    file_put_contents($this->workflowPc.'/fix.txt', "Checked correction\n");
    $originalEnvironment = "APP_KEY=original-local-key\nDB_URL=mysql://unsafe-local-fixture\n";
    file_put_contents($this->workflowPc.'/.env', $originalEnvironment);
    $pc = str_replace("'", "''", $this->workflowPc);
    $command = '$actualReleaseChecks = (Get-Item Function:Invoke-SchooltoolReleaseChecks).ScriptBlock'."\n".branchWorkflowReleaseMocks()."\n".'$originalCheckout = [System.IO.Path]::GetFullPath(\''.$pc."')\n".'$failChecks = '.($failChecks ? '$true' : '$false')."\n".<<<'POWERSHELL'
Set-Item Function:Invoke-SchooltoolReleaseChecks $actualReleaseChecks
$env:DB_URL = 'original-environment-url'
$env:APP_KEY = 'original-environment-key'
$env:LOG_CHANNEL = 'slack'
$env:LOG_SLACK_WEBHOOK_URL = 'original-webhook-fixture'
function Invoke-SchooltoolReleaseCheckProcesses {
    param([switch]$Full)
    if (-not $Full) { throw 'Full checks missing.' }
    if ((Get-Location).Path -eq $originalCheckout) { throw 'Tests used the original checkout.' }
    if ($env:DB_DATABASE -ne 'pest_test_test_123456789012345678901234' -or $env:DB_DATABASE_TEST -ne 'pest_test') { throw 'Tests did not use the owned schema.' }
    if ($env:DB_URL -ne '(null)' -or $env:DB_PASSWORD -ne '(empty)') { throw 'Dotenv fallback was not neutralized.' }
    if ($env:LOG_CHANNEL -ne 'single' -or $env:LOG_SLACK_WEBHOOK_URL) { throw 'Inherited notification settings remain active.' }
    if ($env:APP_KEY -notlike 'base64:*' -or -not $env:TEST_TOKEN) { throw 'Fresh test identity is missing.' }
    if ($env:APP_CONFIG_CACHE -notmatch '^bootstrap/cache/schooltool-test-db-[a-zA-Z0-9-]+\.config\.php$') { throw 'Cache paths must resolve inside the candidate on Windows.' }
    if (Test-Path -LiteralPath $env:APP_CONFIG_CACHE) { throw 'Tests may reuse cached configuration.' }
    if ([System.IO.File]::ReadAllText((Join-Path (Get-Location) 'fix.txt')) -ne "Checked correction`n") { throw 'The staged source was not copied.' }
    if ([System.IO.File]::ReadAllText((Join-Path (Get-Location) '.env')) -match 'original-local-key|unsafe-local-fixture') { throw 'The original environment was copied.' }
    Write-Host 'ISOLATED_MAIN_CHECKS'
    if ($failChecks) { throw 'SIMULATED_ISOLATED_FAILURE' }
}
function npm {
    if ((Get-Location).Path -ne $originalCheckout -or $env:APP_KEY -ne 'original-environment-key') { throw 'Publication build did not restore the original checkout environment.' }
    Write-Host 'ORIGINAL_SOURCE_BUILD'
    $global:LASTEXITCODE = 0
}
try { gitsave 'Publish checked correction' }
finally {
    if ($env:DB_URL -ne 'original-environment-url' -or $env:LOG_SLACK_WEBHOOK_URL -ne 'original-webhook-fixture') { throw 'Original environment was not restored.' }
    Write-Host 'ORIGINAL_ENVIRONMENT_RESTORED'
}
POWERSHELL;
    $result = runBranchWorkflowCommand($this->workflowPc, $command);

    expect($result->getOutput())->toContain('ISOLATED_MAIN_CHECKS', 'MOCK_TEST_DATABASE_REMOVED', 'ORIGINAL_ENVIRONMENT_RESTORED')
        ->and(file_get_contents($this->workflowPc.'/.env'))->toBe($originalEnvironment)
        ->and(runBranchWorkflowGit($this->workflowPc, 'branch', '--show-current'))->toBe('main');

    if ($failChecks) {
        expect($result->isSuccessful())->toBeFalse()
            ->and($result->getOutput())->toContain('SIMULATED_ISOLATED_FAILURE')
            ->and($result->getOutput())->not->toContain('ORIGINAL_SOURCE_BUILD')
            ->and(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'main'))->toBe($this->workflowMain);

        return;
    }

    assertBranchWorkflowSucceeded($result);
    expect($result->getOutput())->toContain('ORIGINAL_SOURCE_BUILD')
        ->and(runBranchWorkflowGit($this->workflowRemote, 'show', 'main:fix.txt'))->toBe('Checked correction');
})->with(['successful checks' => false, 'failed checks' => true]);

it('runs every PHP test file once in sequential fresh batches and stops safely on a failed batch', function (string $mode): void {
    $expectedFiles = [];
    foreach (range(1, 23) as $index) {
        $directory = $index % 2 === 0 ? 'tests/Unit/Nested' : 'tests/Feature';
        if (! is_dir($this->workflowPc.'/'.$directory)) {
            mkdir($this->workflowPc.'/'.$directory, 0777, true);
        }
        $file = $directory.'/'.sprintf('Batch%02dTest.php', $index);
        $expectedFiles[] = $file;
        file_put_contents($this->workflowPc.'/'.$file, '<?php');
    }
    file_put_contents($this->workflowPc.'/tests/Unit/NotATest.txt', 'Must not run');
    runBranchWorkflowGit($this->workflowPc, 'add', '.');
    runBranchWorkflowGit($this->workflowPc, 'commit', '-m', 'Add batch test fixture');
    sort($expectedFiles, SORT_STRING);
    $command = branchWorkflowBatchEnvironment()."\n".'$mode = \''.$mode."'\n".<<<'POWERSHELL'
$script:batchCalls = 0
function Start-SchooltoolPhpTestProcess {
    param([string[]]$Files, [string]$OutputPath, [string]$ErrorPath, [string]$WorkingDirectory)
    if ($script:previousProcess -and -not $script:previousProcess.Waited) { throw 'The previous PHP process is still running.' }
    $script:batchCalls++
    $entry = @{ files = $Files; database = $env:DB_DATABASE; directory = $WorkingDirectory; output = $OutputPath; error = $ErrorPath } | ConvertTo-Json -Compress
    [System.IO.File]::AppendAllText((Join-Path $env:TEMP 'batch-calls.jsonl'), $entry + "`n")
    [System.IO.File]::WriteAllText($OutputPath, "BATCH_OUTPUT_$script:batchCalls")
    $failed = $mode -eq 'failure' -and $script:batchCalls -eq 2
    [System.IO.File]::WriteAllText($ErrorPath, $(if ($failed) { 'BATCH_FAILURE_2' } else { '' }))
    $process = [pscustomobject]@{ HasExited = $true; ExitCode = $(if ($failed) { 7 } else { 0 }); Waited = $false }
    $process | Add-Member ScriptMethod WaitForExit { $this.Waited = $true }
    $process | Add-Member ScriptMethod Refresh {
        if (-not $this.Waited) { throw 'A process result was inspected before exit.' }
        if ($mode -eq 'environment-changes') { $env:DB_HOST = 'unsafe.example.test' }
    }
    $script:previousProcess = $process
    $process
}
Push-Location -LiteralPath $candidate.Path
try { Invoke-SchooltoolPhpTestBatches -LogPrefix $logPrefix }
finally { Pop-Location; Restore-SchooltoolCandidateEnvironment $state }
Write-Host 'ALL_BATCHES_COMPLETED'
POWERSHELL;
    $result = runBranchWorkflowCommand($this->workflowPc, $command);
    $calls = array_map(fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR), file($this->workflowDirectory.'/batch-calls.jsonl', FILE_IGNORE_NEW_LINES));
    $expectedBatchCount = match ($mode) {
        'success' => 3,
        'failure' => 2,
        'environment-changes' => 1,
    };

    expect($calls)->toHaveCount($expectedBatchCount)
        ->and(array_merge(...array_column($calls, 'files')))->toBe(array_slice($expectedFiles, 0, $expectedBatchCount * 10));
    foreach ($calls as $index => $call) {
        expect($call['database'])->toBe('pest_test_test_123456789012345678901234')
            ->and(count($call['files']))->toBeLessThanOrEqual(10)
            ->and(is_file($call['output']))->toBeTrue()
            ->and(is_file($call['error']))->toBeTrue()
            ->and(file($this->workflowDirectory.'/batch-check-php-'.($index + 1).'.files.txt', FILE_IGNORE_NEW_LINES))->toBe($call['files']);
    }
    expect($result->getOutput())->toContain('MOCK_TEST_DATABASE_REMOVED');
    if ($mode === 'success') {
        assertBranchWorkflowSucceeded($result);
        expect($result->getOutput())->toContain('ALL_BATCHES_COMPLETED');
    } else {
        expect($result->isSuccessful())->toBeFalse()
            ->and($result->getOutput())->not->toContain('ALL_BATCHES_COMPLETED');
        if ($mode === 'failure') {
            expect($result->getOutput())->toContain('exit code 7', 'BATCH_FAILURE_2', 'Logs retained');
        } else {
            expect($result->getOutput())->toContain('self-created local database');
        }
    }
})->with(['success', 'failure', 'environment-changes']);

it('refuses PHP batch execution before any process starts when its owned environment is invalid', function (string $invalid): void {
    mkdir($this->workflowPc.'/tests/Unit', 0777, true);
    mkdir($this->workflowPc.'/tests/Feature', 0777, true);
    file_put_contents($this->workflowPc.'/tests/Unit/GuardTest.php', '<?php');
    file_put_contents($this->workflowPc.'/tests/Feature/GuardTest.php', '<?php');
    runBranchWorkflowGit($this->workflowPc, 'add', '.');
    runBranchWorkflowGit($this->workflowPc, 'commit', '-m', 'Add test environment guard fixture');
    $command = branchWorkflowBatchEnvironment()."\n".'$invalid = \''.$invalid."'\n".<<<'POWERSHELL'
function Start-SchooltoolPhpTestProcess { Write-Host 'UNSAFE_PROCESS_STARTED'; throw 'Process must not start.' }
Push-Location -LiteralPath $candidate.Path
try {
    switch ($invalid) {
        'database' { $env:DB_DATABASE = 'pest_test' }
        'host' { $env:DB_HOST = 'live.example.test' }
        'receipt' { [System.IO.File]::WriteAllText($state.ReceiptPath, '{"state":"pending"}') }
        'worktree' { $state.CandidatePath = $env:TEMP }
        'cached-config' {
            [System.IO.Directory]::CreateDirectory((Join-Path $candidate.Path 'bootstrap/cache')) | Out-Null
            [System.IO.File]::WriteAllText((Join-Path $candidate.Path $env:APP_CONFIG_CACHE), '<?php return [];')
        }
    }
    Invoke-SchooltoolPhpTestBatches -LogPrefix $logPrefix
} finally { Pop-Location; Restore-SchooltoolCandidateEnvironment $state }
POWERSHELL;
    $result = runBranchWorkflowCommand($this->workflowPc, $command);

    expect($result->isSuccessful())->toBeFalse()
        ->and($result->getOutput())->not->toContain('UNSAFE_PROCESS_STARTED')
        ->and($result->getOutput())->toContain('MOCK_TEST_DATABASE_REMOVED');
})->with(['database', 'host', 'receipt', 'worktree', 'cached-config']);

it('starts PHP test processes natively with literal file arguments and preserves their exit status', function (int $exitCode, string $powershell): void {
    file_put_contents($this->workflowPc.'/artisan', <<<'PHP'
<?php
usleep(500000);
echo json_encode($argv, JSON_THROW_ON_ERROR);
fwrite(STDERR, 'Expected native child failure');
exit((int) getenv('SCHOOLTOOL_NATIVE_FIXTURE_EXIT'));
PHP);
    $command = '$env:SCHOOLTOOL_NATIVE_FIXTURE_EXIT = \''.$exitCode."'\n".<<<'POWERSHELL'
$output = Join-Path $env:TEMP 'native-php.out'
$errorLog = Join-Path $env:TEMP 'native-php.err'
$env:SHOULD_STAY_LITERAL = 'DO_NOT_EXPAND'
$process = Start-SchooltoolPhpTestProcess -Files @('tests/Unit/File With SpacesTest.php', 'tests/Feature/%SHOULD_STAY_LITERAL%Test.php') -OutputPath $output -ErrorPath $errorLog -WorkingDirectory (Get-Location).Path
$watch = [System.Diagnostics.Stopwatch]::StartNew()
try {
    Wait-SchooltoolCheckProcess -Name 'Native PHP fixture' -Process $process -OutputPath $output -ErrorPath $errorLog
} finally {
    $watch.Stop()
    [System.IO.File]::WriteAllText((Join-Path $env:TEMP 'native-wait-duration.txt'), $watch.ElapsedMilliseconds.ToString())
}
POWERSHELL;
    $result = runBranchWorkflowCommand($this->workflowPc, $command, $powershell);
    $output = file_get_contents($this->workflowDirectory.'/native-php.out');
    expect(json_validate($output))->toBeTrue($result->getOutput().$result->getErrorOutput());
    $arguments = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

    expect($result->isSuccessful())->toBe($exitCode === 0)
        ->and((int) file_get_contents($this->workflowDirectory.'/native-wait-duration.txt'))->toBeLessThan(8000)
        ->and(file_get_contents($this->workflowDirectory.'/native-php.err'))->toContain('Expected native child failure')
        ->and($arguments)->toContain('--stop-on-failure', '--stop-on-error', '--exclude-group=integration', 'tests/Unit/File With SpacesTest.php', 'tests/Feature/%SHOULD_STAY_LITERAL%Test.php')
        ->and($arguments)->not->toContain('DO_NOT_EXPAND');
    if ($exitCode !== 0) {
        expect($result->getOutput())->toContain('exit code 7', 'Expected native child failure');
    }
})->with([0, 7])->with(['Windows PowerShell' => 'powershell', 'PowerShell 7' => 'pwsh']);

it('refuses a temporary candidate directory inside git metadata before creating a branch', function (): void {
    $command = <<<'POWERSHELL'
$env:TEMP = Join-Path (Get-Location) '.git'
$env:TMP = $env:TEMP
New-SchooltoolCandidateWorktree -Kind release -SourceCommit HEAD
POWERSHELL;
    $result = runBranchWorkflowCommand($this->workflowPc, $command);

    expect($result->isSuccessful())->toBeFalse()
        ->and($result->getOutput())->toContain('temporary directory is inside .git')
        ->and(runBranchWorkflowGit($this->workflowPc, 'for-each-ref', '--format=%(refname)', 'refs/heads/codex/'))->toBe('');
});

it('loads candidate setup files through real Vitest without weakening the default git-directory deny rule', function (): void {
    mkdir($this->workflowPc.'/tests/ui', 0777, true);
    file_put_contents($this->workflowPc.'/tests/ui/setup.ts', 'globalThis.workflowSetupLoaded = true');
    file_put_contents($this->workflowPc.'/tests/ui/fixture.test.ts', <<<'JAVASCRIPT'
it('loads the candidate setup file', () => {
    expect(globalThis.workflowSetupLoaded).toBe(true)
})
JAVASCRIPT);
    file_put_contents($this->workflowPc.'/vitest.config.mjs', <<<'JAVASCRIPT'
export default {
    test: {
        environment: 'node',
        globals: true,
        setupFiles: ['./tests/ui/setup.ts'],
        include: ['tests/ui/fixture.test.ts'],
    },
}
JAVASCRIPT);
    runBranchWorkflowGit($this->workflowPc, 'add', '.');
    runBranchWorkflowGit($this->workflowPc, 'commit', '-m', 'Add isolated Vitest regression fixture');
    $vitest = str_replace("'", "''", dirname(__DIR__, 2).'/node_modules/vitest/vitest.mjs');
    $command = '$vitest = \''.$vitest."'\n".<<<'POWERSHELL'
$candidate = New-SchooltoolCandidateWorktree -Kind release -SourceCommit HEAD
if ($candidate.Path -match '(^|[\\/])\.git([\\/]|$)') { throw 'Candidate source remains hidden inside Git metadata.' }
Push-Location -LiteralPath $candidate.Path
try {
    & node $vitest run --configLoader native --reporter=default
    if ($LASTEXITCODE -ne 0) { throw 'The isolated Vitest setup could not be loaded.' }
} finally { Pop-Location }
POWERSHELL;

    $result = runBranchWorkflowCommand($this->workflowPc, $command);

    assertBranchWorkflowSucceeded($result);
    expect($result->getOutput())->toContain('1 passed');
});

it('resolves every isolated cache through the real Laravel application inside the candidate on Windows', function (): void {
    $probePath = $this->workflowDirectory.'/cache-path-probe.php';
    file_put_contents($probePath, <<<'PHP'
<?php
require $argv[1];
$application = new Illuminate\Foundation\Application(getcwd());
foreach ([
    'APP_CONFIG_CACHE' => 'getCachedConfigPath',
    'APP_ROUTES_CACHE' => 'getCachedRoutesPath',
    'APP_PACKAGES_CACHE' => 'getCachedPackagesPath',
    'APP_SERVICES_CACHE' => 'getCachedServicesPath',
    'APP_EVENTS_CACHE' => 'getCachedEventsPath',
] as $key => $method) {
    $expected = str_replace('\\', '/', getcwd()).'/bootstrap/cache/'.basename(getenv($key));
    if (str_replace('\\', '/', $application->{$method}()) !== $expected) {
        throw new RuntimeException('Laravel resolved a cache outside its isolated cache directory: '.$key);
    }
}
echo "LARAVEL_CACHE_PATHS_VERIFIED\n";
PHP);
    $probePath = str_replace("'", "''", $probePath);
    $autoload = str_replace("'", "''", dirname(__DIR__, 2).'/vendor/autoload.php');
    $command = '$probePath = \''.$probePath."'\n".'$autoload = \''.$autoload."'\n".<<<'POWERSHELL'
$candidate = New-SchooltoolCandidateWorktree -Kind release -SourceCommit HEAD
$state = Enter-SchooltoolCandidateEnvironment -Candidate $candidate
try {
    Push-Location -LiteralPath $candidate.Path
    try {
        & php $probePath $autoload
        if ($LASTEXITCODE -ne 0) { throw 'Laravel cache path verification failed.' }
        foreach ($path in $state.CachePaths) {
            if (-not [System.IO.Path]::IsPathRooted($path) -or -not $path.StartsWith($candidate.Path + [System.IO.Path]::DirectorySeparatorChar)) {
                throw 'Cleanup must target the same candidate with absolute paths.'
            }
        }
    } finally { Pop-Location }
} finally { Restore-SchooltoolCandidateEnvironment $state }
POWERSHELL;

    $result = runBranchWorkflowCommand($this->workflowPc, $command);

    assertBranchWorkflowSucceeded($result);
    expect($result->getOutput())->toContain('LARAVEL_CACHE_PATHS_VERIFIED', 'MOCK_TEST_DATABASE_REMOVED');
});

it('clears inherited candidate settings and restores missing empty and nonempty environment values', function (string $powershell): void {
    if ((new ExecutableFinder)->find($powershell) === null) {
        $this->markTestSkipped($powershell.' is unavailable.');
    }

    $command = <<<'POWERSHELL'
$candidate = New-SchooltoolCandidateWorktree -Kind release -SourceCommit HEAD
Remove-Item -LiteralPath Env:APP_CONFIG_CACHE -ErrorAction SilentlyContinue
$env:APP_KEY = 'original-test-key'
$env:MAIL_TEST_NONEMPTY = 'original-mail-setting'
[Environment]::SetEnvironmentVariable('APP_URL', '', 'Process')
$emptyWasPresent = Test-Path -LiteralPath Env:APP_URL
$state = Enter-SchooltoolCandidateEnvironment -Candidate $candidate
try {
    if (Test-Path -LiteralPath Env:MAIL_TEST_NONEMPTY) { throw 'Inherited mail setting was not removed.' }
    if ($env:APP_KEY -eq 'original-test-key' -or $env:APP_URL -ne 'http://localhost') { throw 'Candidate settings were not installed.' }
    if (-not (Test-Path -LiteralPath Env:APP_CONFIG_CACHE)) { throw 'Candidate cache setting is absent.' }
} finally { Restore-SchooltoolCandidateEnvironment $state }
if (Test-Path -LiteralPath Env:APP_CONFIG_CACHE) { throw 'Originally missing setting was not removed.' }
if ($env:APP_KEY -ne 'original-test-key' -or $env:MAIL_TEST_NONEMPTY -ne 'original-mail-setting') { throw 'Nonempty settings were not restored.' }
if ((Test-Path -LiteralPath Env:APP_URL) -ne $emptyWasPresent) { throw 'Empty setting presence was not restored.' }
if ($emptyWasPresent -and [Environment]::GetEnvironmentVariable('APP_URL', 'Process') -cne '') { throw 'Empty setting value was not restored.' }
if ($script:SchooltoolActiveCandidateEnvironment) { throw 'Candidate environment is still active.' }
Write-Output "CANDIDATE_ENVIRONMENT_RESTORED:PS$($PSVersionTable.PSVersion):EMPTY_PRESENT=$emptyWasPresent"
POWERSHELL;

    $result = runBranchWorkflowCommand($this->workflowPc, $command, $powershell);

    assertBranchWorkflowSucceeded($result);
    expect($result->getOutput())->toContain('CANDIDATE_ENVIRONMENT_RESTORED:', 'MOCK_TEST_DATABASE_REMOVED');
})->with(['powershell', 'pwsh']);

it('reports owned database cleanup failure while still restoring the original environment', function (): void {
    $command = <<<'POWERSHELL'
$candidate = New-SchooltoolCandidateWorktree -Kind release -SourceCommit HEAD
$env:APP_KEY = 'original-test-key'
$state = Enter-SchooltoolCandidateEnvironment -Candidate $candidate
function Remove-SchooltoolCandidateTestDatabase { throw 'OWNED_DATABASE_CLEANUP_FAILED' }
try { Restore-SchooltoolCandidateEnvironment $state }
finally {
    if ($env:APP_KEY -ne 'original-test-key' -or $script:SchooltoolActiveCandidateEnvironment) { throw 'Environment was not restored after cleanup failure.' }
    Write-Host 'CLEANUP_FAILURE_ENVIRONMENT_RESTORED'
}
POWERSHELL;
    $result = runBranchWorkflowCommand($this->workflowPc, $command);

    expect($result->isSuccessful())->toBeFalse()
        ->and($result->getOutput())->toContain('OWNED_DATABASE_CLEANUP_FAILED', 'CLEANUP_FAILURE_ENVIRONMENT_RESTORED');
});

it('keeps merge conflicts inside the release worktree', function (): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"'));
    file_put_contents($this->workflowPc.'/shared.txt', "Feature replacement\n");
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitsave "Save feature"'));
    $feature = runBranchWorkflowGit($this->workflowPc, 'rev-parse', 'HEAD');
    $main = commitBranchWorkflowFile($this->workflowLaptop, 'shared.txt', "Main replacement\n");
    runBranchWorkflowGit($this->workflowLaptop, 'push', 'origin', 'main');
    $result = runBranchWorkflowCommand($this->workflowPc, branchWorkflowReleaseMocks()."\n".'gitrelease "Release feature"');

    expect($result->isSuccessful())->toBeFalse()
        ->and($result->getOutput())->toContain('Candidate retained at')
        ->and(runBranchWorkflowGit($this->workflowPc, 'branch', '--show-current'))->toBe('feature/new-function')
        ->and(runBranchWorkflowGit($this->workflowPc, 'rev-parse', 'HEAD'))->toBe($feature)
        ->and(runBranchWorkflowGit($this->workflowPc, 'status', '--porcelain'))->toBe('')
        ->and(file_exists($this->workflowPc.'/.git/MERGE_HEAD'))->toBeFalse()
        ->and(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'main'))->toBe($main);
});

it('rejects any changed main or feature reservation at the atomic release boundary', function (string $changedRef): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"'));
    file_put_contents($this->workflowPc.'/feature.txt', "Completed feature\n");
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitsave "Save feature"'));
    $feature = runBranchWorkflowGit($this->workflowPc, 'rev-parse', 'HEAD');
    $origin = str_replace("'", "''", $this->workflowRemote);
    $command = branchWorkflowReleaseMocks()."\n".'$raceOrigin = \''.$origin."'\n".'$raceRef = \''.$changedRef."'\n".'$featureHead = \''.$feature."'\n".<<<'POWERSHELL'
function Read-Host {
    if ($raceRef -eq 'main') {
        Invoke-SchooltoolGit -C $raceOrigin update-ref refs/heads/main $featureHead
    }
    else {
        $replacement = New-SchooltoolFeatureReservation 'feature/new-function'
        $previous = Invoke-SchooltoolGit rev-parse refs/remotes/origin/codex/active-feature
        Invoke-SchooltoolGit push "--force-with-lease=refs/heads/codex/active-feature:$previous" origin "$($replacement.ReservationCommit):refs/heads/codex/active-feature"
    }
    'RELEASE'
}
gitrelease 'Release feature' '3.48.0'
POWERSHELL;
    $result = runBranchWorkflowCommand($this->workflowPc, $command);

    expect($result->isSuccessful())->toBeFalse()
        ->and($result->getOutput())->toContain('atomic release push failed')
        ->and(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'feature/new-function'))->toBe($feature)
        ->and(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'main'))->toBe($changedRef === 'main' ? $feature : $this->workflowMain)
        ->and(runBranchWorkflowGit($this->workflowRemote, 'tag', '--list'))->toBe('');
})->with(['main moves inside the candidate ancestry' => 'main', 'feature reservation replaced' => 'reservation']);

it('preserves a local feature advanced during cleanup using an expected commit deletion', function (): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"'));
    file_put_contents($this->workflowPc.'/feature.txt', "Completed feature\n");
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitsave "Save feature"'));
    $feature = runBranchWorkflowGit($this->workflowPc, 'rev-parse', 'HEAD');
    $command = branchWorkflowReleaseMocks()."\n".<<<'POWERSHELL'
$nativeGit = (Get-Command git -CommandType Application | Select-Object -First 1).Source
function git {
    if ($args[0] -eq 'update-ref' -and $args[1] -eq '-d') {
        $old = & $nativeGit rev-parse refs/heads/feature/new-function
        $tree = & $nativeGit rev-parse 'feature/new-function^{tree}'
        $new = & $nativeGit commit-tree $tree -p $old -m 'Concurrent local work'
        & $nativeGit update-ref refs/heads/feature/new-function $new $old
    }
    & $nativeGit @args
}
gitrelease 'Release feature'
POWERSHELL;
    $result = runBranchWorkflowCommand($this->workflowPc, $command);
    assertBranchWorkflowSucceeded($result);

    expect($result->getOutput())->toContain('Release succeeded, but local cleanup stopped')
        ->and(runBranchWorkflowGit($this->workflowPc, 'rev-parse', 'feature/new-function'))->not->toBe($feature)
        ->and(runBranchWorkflowGit($this->workflowPc, 'log', '-1', '--format=%s', 'feature/new-function'))->toBe('Concurrent local work')
        ->and(runBranchWorkflowGit($this->workflowRemote, 'show', 'main:feature.txt'))->toBe('Completed feature');
});

it('binds refresh and deployment through the real dispatcher without evaluating argument text', function (): void {
    copy(dirname(__DIR__, 2).'/scripts/git_workflow.ps1', $this->workflowPc.'/scripts/git_workflow.ps1');
    file_put_contents($this->workflowPc.'/scripts/git_helpers.ps1', <<<'POWERSHELL'
function gitpreview {
    param([string]$Mode = 'deploy', [string]$BundleId, [switch]$RefreshData)
    Write-Output "PREVIEW_MODE=$Mode REFRESH=$RefreshData BUNDLE=$BundleId"
}
function gitdeploy { Write-Output 'LIVE_DEPLOY_REQUESTED' }
POWERSHELL);
    $result = runBranchWorkflowCommand($this->workflowPc, <<<'POWERSHELL'
& ./scripts/git_workflow.ps1 -Command gitpreview -CommandArguments @('prepare', '-RefreshData')
& ./scripts/git_workflow.ps1 -Command gitpreview -CommandArguments @('resume', '0123456789abcdef0123456789abcdef', '-RefreshData')
& ./scripts/git_workflow.ps1 -Command gitdeploy
try {
    & ./scripts/git_workflow.ps1 -Command gitpreview -CommandArguments @('$(throw "EVALUATED_ARGUMENT")')
    throw 'UNEXPECTED_ARGUMENT_ACCEPTED'
}
catch {
    if ($_.Exception.Message -match 'EVALUATED_ARGUMENT|UNEXPECTED_ARGUMENT_ACCEPTED') { throw }
    Write-Output 'INVALID_ARGUMENT_REJECTED'
}
POWERSHELL);
    assertBranchWorkflowSucceeded($result);

    expect($result->getOutput())->toContain('PREVIEW_MODE=prepare REFRESH=True', 'PREVIEW_MODE=resume REFRESH=True BUNDLE=0123456789abcdef0123456789abcdef', 'LIVE_DEPLOY_REQUESTED', 'INVALID_ARGUMENT_REJECTED');
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

it('prepares or publishes a preview without modifying main tags or the source feature', function (string $mode): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"'));
    file_put_contents($this->workflowPc.'/feature.txt', "Preview work\n");
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitsave "Save preview work"'));
    $feature = runBranchWorkflowGit($this->workflowPc, 'rev-parse', 'HEAD');
    $command = branchWorkflowReleaseMocks()."\n".<<<'POWERSHELL'
$env:SCHOOLTOOL_PREVIEW_PATH = '/home/example/applications/preview/public_html'
function Read-Host { 'PREVIEW' }
function Get-SchooltoolPreviewTarget { [pscustomobject]@{ Ssh = 'schooltool-feature@example.test'; Path = '/home/example/applications/preview/public_html' } }
function Invoke-SchooltoolRemoteJson { [pscustomobject]@{ public_key = ('a' * 64); needs_snapshot = $false } }
function Send-SchooltoolPreview { Write-Host 'PREVIEW_UPLOAD_REQUESTED' }
POWERSHELL;
    $result = runBranchWorkflowCommand($this->workflowPc, $command."\n"."gitpreview '$mode'");
    assertBranchWorkflowSucceeded($result);

    expect($result->getOutput())->toContain('FULL_CHECKS_REQUESTED')
        ->and(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'main'))->toBe($this->workflowMain)
        ->and(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'feature/new-function'))->toBe($feature)
        ->and(runBranchWorkflowGit($this->workflowRemote, 'tag', '--list'))->toBe('')
        ->and(runBranchWorkflowGit($this->workflowPc, 'branch', '--show-current'))->toBe('feature/new-function')
        ->and(glob($this->workflowPc.'/.git/schooltool-preview/*.tar.gz'))->toHaveCount(1);

    $previewRefs = runBranchWorkflowGit($this->workflowRemote, 'for-each-ref', '--format=%(refname)', 'refs/heads/preview/');
    if ($mode === 'deploy') {
        expect($previewRefs)->toStartWith('refs/heads/preview/')
            ->and($result->getOutput())->toContain('PREVIEW_UPLOAD_REQUESTED');
    } else {
        expect($previewRefs)->toBe('')
            ->and($result->getOutput())->not->toContain('PREVIEW_UPLOAD_REQUESTED');
    }
})->with(['prepare', 'deploy']);

it('prepares schema changes for the isolated preview database without modifying the original branch', function (): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"'));
    mkdir($this->workflowPc.'/database/migrations', 0777, true);
    commitBranchWorkflowFile($this->workflowPc, 'database/migrations/new-table.php', '<?php');
    runBranchWorkflowGit($this->workflowPc, 'push', 'origin', 'HEAD:feature/new-function');
    $result = runBranchWorkflowCommand($this->workflowPc, branchWorkflowReleaseMocks()."\n".'gitpreview prepare');
    assertBranchWorkflowSucceeded($result);

    expect($result->getOutput())->toContain('FULL_CHECKS_REQUESTED')
        ->and(runBranchWorkflowGit($this->workflowPc, 'branch', '--show-current'))->toBe('feature/new-function')
        ->and(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'main'))->toBe($this->workflowMain);
});

it('stops a preview when full checks fail without uploading or publishing', function (): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"'));
    $command = branchWorkflowReleaseMocks()."\n".<<<'POWERSHELL'
function Invoke-SchooltoolReleaseChecks { throw 'PREVIEW_CHECKS_FAILED' }
function Send-SchooltoolPreview { throw 'UNEXPECTED_UPLOAD' }
gitpreview prepare
POWERSHELL;
    $result = runBranchWorkflowCommand($this->workflowPc, $command);

    expect($result->isSuccessful())->toBeFalse()
        ->and($result->getOutput())->toContain('PREVIEW_CHECKS_FAILED')
        ->and(glob($this->workflowPc.'/.git/schooltool-preview/*.receipt'))->toBe([])
        ->and($result->getOutput())->not->toContain('UNEXPECTED_UPLOAD')
        ->and(runBranchWorkflowGit($this->workflowRemote, 'for-each-ref', '--format=%(refname)', 'refs/heads/preview/'))->toBe('')
        ->and(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'main'))->toBe($this->workflowMain)
        ->and(runBranchWorkflowGit($this->workflowPc, 'branch', '--show-current'))->toBe('feature/new-function');
});

it('pins complete single preview origin URLs for fetch and push', function (string $powershell): void {
    if (! (new ExecutableFinder)->find($powershell)) {
        $this->markTestSkipped("$powershell is not available.");
    }
    runBranchWorkflowGit($this->workflowPc, 'remote', 'set-url', 'origin', 'https://example.test/Schooltool/project.git');
    runBranchWorkflowGit($this->workflowPc, 'remote', 'set-url', '--push', 'origin', 'git@example.test:Schooltool/project.git');
    $result = runBranchWorkflowCommand($this->workflowPc, <<<'POWERSHELL'
Write-Output ('FETCH=' + (Get-SchooltoolPreviewOrigin))
Write-Output ('PUSH=' + (Get-SchooltoolPreviewOrigin -Push))
POWERSHELL, $powershell);
    assertBranchWorkflowSucceeded($result);
    expect($result->getOutput())->toContain('FETCH=https://example.test/Schooltool/project.git', 'PUSH=git@example.test:Schooltool/project.git');
})->with(['powershell', 'pwsh']);

it('rejects multiple preview origin URLs for fetch or push', function (string $direction, string $powershell): void {
    if (! (new ExecutableFinder)->find($powershell)) {
        $this->markTestSkipped("$powershell is not available.");
    }
    $key = $direction === 'push' ? 'remote.origin.pushurl' : 'remote.origin.url';
    runBranchWorkflowGit($this->workflowPc, 'config', '--replace-all', $key, 'https://example.test/Schooltool/first.git');
    runBranchWorkflowGit($this->workflowPc, 'config', '--add', $key, 'https://example.test/Schooltool/second.git');
    $result = runBranchWorkflowCommand($this->workflowPc, 'Get-SchooltoolPreviewOrigin'.($direction === 'push' ? ' -Push' : ''), $powershell);
    expect($result->isSuccessful())->toBeFalse()
        ->and($result->getOutput())->toContain('requires exactly one origin fetch URL and one push URL');
})->with(['fetch', 'push'])->with(['powershell', 'pwsh']);

it('resumes a checked preview after preparation or cancellation without repeating checks', function (string $initial, string $powershell): void {
    if (! (new ExecutableFinder)->find($powershell)) {
        $this->markTestSkipped("$powershell is not available.");
    }
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"'));
    $command = branchWorkflowPreviewMocks()."\nfunction Read-Host { '' }\ngitpreview $initial\n";
    $prepared = runBranchWorkflowCommand($this->workflowPc, $command, $powershell);
    assertBranchWorkflowSucceeded($prepared);
    $receipts = glob($this->workflowPc.'/.git/schooltool-preview/*.receipt');
    expect($receipts)->toHaveCount(1)
        ->and(runBranchWorkflowGit($this->workflowRemote, 'for-each-ref', '--format=%(refname)', 'refs/heads/preview/'))->toBe('');
    $id = basename($receipts[0], '.receipt');
    expect($prepared->getOutput())->toContain("gitpreview resume $id");
    if ($initial === 'deploy') {
        expect($prepared->getOutput())->toContain('Preview cancelled. Nothing published.');
    }
    $resume = branchWorkflowPreviewMocks()."\n".<<<'POWERSHELL'
function Invoke-SchooltoolReleaseChecks { throw 'CHECKS_MUST_NOT_REPEAT' }
function New-SchooltoolCandidateWorktree { throw 'CANDIDATE_MUST_NOT_REPEAT' }
function Enter-SchooltoolCandidateEnvironment { throw 'DATABASE_MUST_NOT_REPEAT' }
POWERSHELL;
    $result = runBranchWorkflowCommand($this->workflowPc, $resume."\ngitpreview resume $id", $powershell);
    assertBranchWorkflowSucceeded($result);
    expect($result->getOutput())->toContain('PREVIEW_UPLOAD_REQUESTED')
        ->and($result->getOutput())->not->toContain('FULL_CHECKS_REQUESTED')
        ->and(runBranchWorkflowGit($this->workflowRemote, 'for-each-ref', '--format=%(refname)', 'refs/heads/preview/'))->toBe("refs/heads/preview/$id")
        ->and(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'main'))->toBe($this->workflowMain)
        ->and(file_exists($this->workflowPc."/.git/schooltool-preview/$id.started"))->toBeTrue();
})->with([
    'Windows PowerShell prepare' => ['prepare', 'powershell'],
    'Windows PowerShell cancellation' => ['deploy', 'powershell'],
    'PowerShell 7 prepare' => ['prepare', 'pwsh'],
]);

it('rejects unsafe preview resume evidence before publishing or repeating checks', function (string $mutation, string $message): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"'));
    $command = branchWorkflowPreviewMocks()."\n".<<<'POWERSHELL'
gitpreview prepare
$id = (Get-ChildItem -LiteralPath (Get-SchooltoolPreviewDirectory) -Filter '*.receipt').BaseName
$receipt = Read-SchooltoolPreviewReceipt $id
$receiptPath = Join-Path (Get-SchooltoolPreviewDirectory) "$id.receipt"
function Invoke-SchooltoolReleaseChecks { throw 'CHECKS_MUST_NOT_REPEAT' }
function Send-SchooltoolPreview { throw 'UPLOAD_MUST_NOT_HAPPEN' }
POWERSHELL;
    $result = runBranchWorkflowCommand($this->workflowPc, $command."\n".$mutation."\ngitpreview resume \$id");
    expect($result->isSuccessful())->toBeFalse()
        ->and($result->getOutput())->toContain($message)
        ->and($result->getOutput())->not->toContain('CHECKS_MUST_NOT_REPEAT', 'UPLOAD_MUST_NOT_HAPPEN')
        ->and(runBranchWorkflowGit($this->workflowRemote, 'for-each-ref', '--format=%(refname)', 'refs/heads/preview/'))->toBe('');
})->with([
    'missing receipt' => ['[System.IO.File]::Delete($receiptPath)', 'No source-bound successful-check receipt'],
    'tampered receipt' => ['[System.IO.File]::WriteAllText($receiptPath, "forged")', 'receipt is invalid'],
    'invalid check result' => ['$receipt.Checks = "failed"; [System.IO.File]::Delete($receiptPath); Write-SchooltoolPreviewReceipt $receipt', 'Invalid preview check receipt'],
    'tampered bundle' => ['[System.IO.File]::AppendAllText((Join-Path (Get-SchooltoolPreviewDirectory) "$id.tar.gz"), "changed")', 'bundle checksum differs'],
    'dirty candidate' => ['[System.IO.File]::WriteAllText((Join-Path $receipt.Candidate.Path "uncommitted.txt"), "changed")', 'Unsaved changes'],
    'dirty original checkout' => ['[System.IO.File]::WriteAllText((Join-Path (Get-Location) "uncommitted.txt"), "changed")', 'Unsaved changes'],
    'already attempted publication' => ['Start-SchooltoolPreviewPublication $id', 'Publication was already attempted'],
    'changed source identity' => ['$receipt.SourceCommit = ("a" * 40); [System.IO.File]::Delete($receiptPath); Write-SchooltoolPreviewReceipt $receipt', 'artifact source does not match'],
    'changed fetch origin' => ['Invoke-SchooltoolGit remote set-url origin https://example.test/changed.git', 'checkout or origin differs'],
    'changed push origin' => ['Invoke-SchooltoolGit remote set-url --push origin https://example.test/changed.git', 'checkout or origin differs'],
    'changed artifact identity' => ['$receipt.ArtifactCommit = ("a" * 40); [System.IO.File]::Delete($receiptPath); Write-SchooltoolPreviewReceipt $receipt', 'Invalid detached preview candidate identity'],
    'changed checked tree' => ['$receipt.SourceTree = ("a" * 64); [System.IO.File]::Delete($receiptPath); Write-SchooltoolPreviewReceipt $receipt', 'Source files or the active branch changed'],
    'new receipt cannot use legacy evidence' => ['$receipt.EvidenceKind = "legacy-reviewed"; [System.IO.File]::Delete($receiptPath); Write-SchooltoolPreviewReceipt $receipt', 'require the complete inline full checks'],
    'new receipt cannot use patch evidence' => ['$receipt.EvidenceKind = "reviewed-patch"; [System.IO.File]::Delete($receiptPath); Write-SchooltoolPreviewReceipt $receipt', 'require the complete inline full checks'],
    'advanced main' => ['$next = Invoke-SchooltoolGit commit-tree "HEAD^{tree}" -p HEAD -m "Concurrent main"; Invoke-SchooltoolGit push origin "${next}:refs/heads/main"', 'main changed'],
    'advanced feature' => ['$next = Invoke-SchooltoolGit commit-tree "HEAD^{tree}" -p HEAD -m "Concurrent feature"; Invoke-SchooltoolGit push origin "${next}:refs/heads/feature/new-function"', 'feature changed'],
    'changed reservation' => ['$ref = "refs/remotes/origin/codex/active-feature"; $next = Invoke-SchooltoolGit commit-tree "${ref}^{tree}" -m "Concurrent reservation"; Invoke-SchooltoolGit push origin "${next}:refs/heads/test-reservation-transfer"; $remote = Invoke-SchooltoolGit remote get-url origin; Invoke-SchooltoolGit -C $remote update-ref refs/heads/codex/active-feature $next', 'active feature changed'],
    'missing legacy evidence' => ['ConvertTo-LegacyPreviewTestReceipt $receipt; $receipt.EvidenceKind = "legacy-reviewed"; [System.IO.File]::Delete($receiptPath); Write-SchooltoolPreviewReceipt $receipt', 'Missing reviewed legacy evidence'],
    'tampered legacy evidence' => [
        'ConvertTo-LegacyPreviewTestReceipt $receipt; $evidencePath = Join-Path (Get-SchooltoolPreviewDirectory) "checks.log"; [System.IO.File]::WriteAllText($evidencePath, "successful mock checks"); $receipt.EvidenceKind = "legacy-reviewed"; $receipt | Add-Member -NotePropertyName Evidence -NotePropertyValue ([pscustomobject]@{ ReviewedAt = [DateTime]::UtcNow.ToString("o"); Basis = "isolated test evidence"; Files = @([pscustomobject]@{ Path = $evidencePath; Sha256 = (Get-SchooltoolFileChecksum $evidencePath) }) }); [System.IO.File]::Delete($receiptPath); Write-SchooltoolPreviewReceipt $receipt; [System.IO.File]::AppendAllText($evidencePath, "changed")',
        'reviewed legacy preview evidence changed',
    ],
]);

it('binds reviewed patch preview receipts to their exact reviewed source delta', function (string $mutation, string $message, string $scope = ''): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"'));
    $command = branchWorkflowPreviewMocks()."\n".'$snapshotLifetime = '.($scope === 'snapshot-lifetime' ? '$true' : '$false')."\n".'$snapshotTargetConfig = '.($scope === 'snapshot-target-config' ? '$true' : '$false')."\n".<<<'POWERSHELL'
$baseline = Invoke-SchooltoolGit rev-parse HEAD
$reviewedPaths = @('app/Services/FeaturePreviewDatabaseGuard.php', 'tests/Unit/FeaturePreviewDatabaseGuardTest.php')
if ($snapshotLifetime) {
    $reviewedPaths = @('app/Services/FeaturePreviewDatabaseGuard.php', 'app/Services/FeaturePreviewSnapshotService.php', 'scripts/deploy_preview_cloudways.sh', 'tests/Feature/FeaturePreviewReadOnlySourceTest.php', 'tests/Feature/FeaturePreviewSnapshotTest.php', 'tests/Unit/PreviewDeploymentTest.php')
}
if ($snapshotTargetConfig) {
    $reviewedPaths = @('app/Services/FeaturePreviewDatabaseGuard.php', 'tests/Feature/FeaturePreviewReadOnlySourceTest.php')
}
foreach ($path in $reviewedPaths) {
    [System.IO.Directory]::CreateDirectory((Split-Path -Parent (Join-Path (Get-Location) $path))) | Out-Null
    [System.IO.File]::WriteAllText((Join-Path (Get-Location) $path), "<?php // reviewed fixture patch")
}
Invoke-SchooltoolGit add -- @reviewedPaths | Out-Null
Invoke-SchooltoolGit commit -m 'Reviewed fixture patch' | Out-Null
Invoke-SchooltoolGit push origin HEAD:feature/new-function | Out-Null
gitpreview prepare
$id = (Get-ChildItem -LiteralPath (Get-SchooltoolPreviewDirectory) -Filter '*.receipt').BaseName
$receipt = Read-SchooltoolPreviewReceipt $id
$receiptPath = Join-Path (Get-SchooltoolPreviewDirectory) "$id.receipt"
$evidencePath = Join-Path (Get-SchooltoolPreviewDirectory) 'patch-review.log'
[System.IO.File]::WriteAllText($evidencePath, 'Isolated fixture: reviewed baseline and targeted patch validation')
ConvertTo-LegacyPreviewTestReceipt $receipt
$receipt.EvidenceKind = 'reviewed-patch'
$receipt.Checks = 'reviewed-patch-success'
$receipt | Add-Member -NotePropertyName Evidence -NotePropertyValue ([pscustomobject]@{
    ReviewedAt = [DateTime]::UtcNow.ToString('o'); Basis = 'Isolated fixture only'; BaselineSource = $baseline
    Files = @([pscustomobject]@{ Path = $evidencePath; Sha256 = (Get-SchooltoolFileChecksum $evidencePath) })
    ReviewedFiles = @($reviewedPaths | ForEach-Object { [pscustomobject]@{ Path = $_; Sha256 = (Get-SchooltoolFileChecksum (Join-Path $receipt.Candidate.Path $_)) } })
})
if ($snapshotLifetime) { $receipt.Evidence | Add-Member -NotePropertyName Scope -NotePropertyValue 'snapshot-lifetime' }
if ($snapshotTargetConfig) { $receipt.Evidence | Add-Member -NotePropertyName Scope -NotePropertyValue 'snapshot-target-config' }
function Invoke-SchooltoolReleaseChecks { throw 'CHECKS_MUST_NOT_REPEAT' }
function New-SchooltoolCandidateWorktree { throw 'CANDIDATE_MUST_NOT_REPEAT' }
function Read-Host { '' }
POWERSHELL;
    $command .= "\n".$mutation."\n".<<<'POWERSHELL'
[System.IO.File]::Delete($receiptPath)
Write-SchooltoolPreviewReceipt $receipt
gitpreview resume $id
POWERSHELL;
    $result = runBranchWorkflowCommand($this->workflowPc, $command);
    expect($result->isSuccessful())->toBe($message === '', $result->getOutput().$result->getErrorOutput())
        ->and($result->getOutput())->toContain($message === '' ? 'Preview cancelled. Nothing published.' : $message)
        ->and($result->getOutput())->not->toContain('CHECKS_MUST_NOT_REPEAT', 'CANDIDATE_MUST_NOT_REPEAT', 'PREVIEW_UPLOAD_REQUESTED')
        ->and(glob($this->workflowPc.'/.git/schooltool-preview/*.started'))->toBe([])
        ->and(runBranchWorkflowGit($this->workflowRemote, 'for-each-ref', '--format=%(refname)', 'refs/heads/preview/'))->toBe('');
})->with([
    'approved exact patch' => ['', ''],
    'must not claim full checks' => ['$receipt.Checks = "full-success"', 'Invalid preview check receipt'],
    'missing reviewed metadata' => ['$receipt.Evidence.Basis = ""', 'Missing reviewed legacy evidence or reviewed patch evidence'],
    'missing baseline' => ['$receipt.Evidence.BaselineSource = ""', 'Invalid reviewed patch scope'],
    'unrelated baseline' => ['$receipt.Evidence.BaselineSource = Invoke-SchooltoolGit commit-tree "HEAD^{tree}" -m "Unrelated baseline"', 'Git failed'],
    'source hash mismatch' => ['$receipt.Evidence.ReviewedFiles[0].Sha256 = ("a" * 64)', 'reviewed patch source checksum changed'],
    'unapproved path' => ['$receipt.Evidence.ReviewedFiles[0].Path = "unreviewed.php"', 'Invalid reviewed patch scope'],
    'extra reviewed path' => ['$receipt.Evidence.ReviewedFiles += $receipt.Evidence.ReviewedFiles[0]', 'Invalid reviewed patch scope'],
    'unreviewed source delta' => [
        '$tree = Invoke-SchooltoolGit rev-parse "$baseline`^{tree}"; $oldIndex = $env:GIT_INDEX_FILE; try { $env:GIT_INDEX_FILE = Join-Path (Get-SchooltoolPreviewDirectory) "fixture.index"; Invoke-SchooltoolGit read-tree $tree; Invoke-SchooltoolGit update-index --force-remove shared.txt; $changedTree = Invoke-SchooltoolGit write-tree; $receipt.Evidence.BaselineSource = Invoke-SchooltoolGit commit-tree $changedTree -m "Earlier fixture"; Invoke-SchooltoolGit replace --graft $baseline $receipt.Evidence.BaselineSource } finally { $env:GIT_INDEX_FILE = $oldIndex }',
        'changes outside the reviewed patch',
    ],
    'changed evidence log' => ['[System.IO.File]::AppendAllText($evidencePath, "changed")', 'reviewed patch evidence changed'],
    'snapshot-lifetime exact patch' => ['', '', 'snapshot-lifetime'],
    'snapshot-lifetime missing scope' => ['$receipt.Evidence.PSObject.Properties.Remove("Scope")', 'Invalid reviewed patch scope', 'snapshot-lifetime'],
    'snapshot-lifetime unknown scope' => ['$receipt.Evidence.Scope = "anything"', 'Invalid reviewed patch scope', 'snapshot-lifetime'],
    'snapshot-lifetime missing file' => ['$receipt.Evidence.ReviewedFiles = @($receipt.Evidence.ReviewedFiles | Select-Object -First 5)', 'Invalid reviewed patch scope', 'snapshot-lifetime'],
    'snapshot-lifetime extra file' => ['$receipt.Evidence.ReviewedFiles += $receipt.Evidence.ReviewedFiles[0]', 'Invalid reviewed patch scope', 'snapshot-lifetime'],
    'snapshot-lifetime unapproved path' => ['$receipt.Evidence.ReviewedFiles[0].Path = "unreviewed.php"', 'Invalid reviewed patch scope', 'snapshot-lifetime'],
    'snapshot-lifetime source hash mismatch' => ['$receipt.Evidence.ReviewedFiles[0].Sha256 = ("a" * 64)', 'reviewed patch source checksum changed', 'snapshot-lifetime'],
    'snapshot-target-config exact patch' => ['', '', 'snapshot-target-config'],
    'snapshot-target-config missing scope' => ['$receipt.Evidence.PSObject.Properties.Remove("Scope")', 'Invalid reviewed patch scope', 'snapshot-target-config'],
    'snapshot-target-config unknown scope' => ['$receipt.Evidence.Scope = "anything"', 'Invalid reviewed patch scope', 'snapshot-target-config'],
    'snapshot-target-config missing file' => ['$receipt.Evidence.ReviewedFiles = @($receipt.Evidence.ReviewedFiles | Select-Object -First 1)', 'Invalid reviewed patch scope', 'snapshot-target-config'],
    'snapshot-target-config extra file' => ['$receipt.Evidence.ReviewedFiles += $receipt.Evidence.ReviewedFiles[0]', 'Invalid reviewed patch scope', 'snapshot-target-config'],
    'snapshot-target-config unapproved path' => ['$receipt.Evidence.ReviewedFiles[1].Path = "tests/Unit/FeaturePreviewDatabaseGuardTest.php"', 'Invalid reviewed patch scope', 'snapshot-target-config'],
]);

it('revalidates preview resume evidence after the publication confirmation', function (): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"'));
    $command = branchWorkflowPreviewMocks()."\n".<<<'POWERSHELL'
gitpreview prepare
$id = (Get-ChildItem -LiteralPath (Get-SchooltoolPreviewDirectory) -Filter '*.receipt').BaseName
$resumeReceipt = Read-SchooltoolPreviewReceipt $id
function Invoke-SchooltoolReleaseChecks { throw 'CHECKS_MUST_NOT_REPEAT' }
function Send-SchooltoolPreview { throw 'UPLOAD_MUST_NOT_HAPPEN' }
function Read-Host {
    [System.IO.File]::AppendAllText((Join-Path $resumeReceipt.Directory "$($resumeReceipt.Id).tar.gz"), 'changed at confirmation')
    'PREVIEW'
}
gitpreview resume $id
POWERSHELL;
    $result = runBranchWorkflowCommand($this->workflowPc, $command);
    expect($result->isSuccessful())->toBeFalse()
        ->and($result->getOutput())->toContain('bundle checksum differs')
        ->and($result->getOutput())->not->toContain('CHECKS_MUST_NOT_REPEAT', 'UPLOAD_MUST_NOT_HAPPEN')
        ->and(glob($this->workflowPc.'/.git/schooltool-preview/*.started'))->toBe([])
        ->and(runBranchWorkflowGit($this->workflowRemote, 'for-each-ref', '--format=%(refname)', 'refs/heads/preview/'))->toBe('');
});

it('retains a preview resume receipt when refresh confirmation is cancelled', function (): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"'));
    $command = branchWorkflowPreviewMocks()."\n".<<<'POWERSHELL'
gitpreview prepare
$id = (Get-ChildItem -LiteralPath (Get-SchooltoolPreviewDirectory) -Filter '*.receipt').BaseName
function Invoke-SchooltoolReleaseChecks { throw 'CHECKS_MUST_NOT_REPEAT' }
function Send-SchooltoolPreview { throw 'UPLOAD_MUST_NOT_HAPPEN' }
function Read-Host { '' }
gitpreview resume $id -RefreshData
POWERSHELL;
    $result = runBranchWorkflowCommand($this->workflowPc, $command);
    assertBranchWorkflowSucceeded($result);
    expect($result->getOutput())->toContain('Data refresh cancelled. Nothing published.', '-RefreshData')
        ->and($result->getOutput())->not->toContain('CHECKS_MUST_NOT_REPEAT', 'UPLOAD_MUST_NOT_HAPPEN')
        ->and(glob($this->workflowPc.'/.git/schooltool-preview/*.receipt'))->toHaveCount(1)
        ->and(glob($this->workflowPc.'/.git/schooltool-preview/*.started'))->toBe([])
        ->and(runBranchWorkflowGit($this->workflowRemote, 'for-each-ref', '--format=%(refname)', 'refs/heads/preview/'))->toBe('');
});

it('retains detached candidate commits without adding visible branches', function (string $kind): void {
    $command = '$kind = "'.$kind.'"; '.<<<'POWERSHELL'
$candidate = New-SchooltoolCandidateWorktree -Kind $kind -SourceCommit HEAD
Push-Location $candidate.Path
try {
    Assert-SchooltoolRepository -Candidate $candidate
    if (Invoke-SchooltoolGit branch --show-current) { throw 'Candidate is not detached.' }
    Invoke-SchooltoolGit commit --allow-empty -m 'Retained candidate work' | Out-Null
    Save-SchooltoolCandidate $candidate
    Assert-SchooltoolRepository -Candidate $candidate
    Write-Output ('RECOVERY=' + (Invoke-SchooltoolGit rev-parse $candidate.RecoveryRef))
    Write-Output ('HEAD=' + (Invoke-SchooltoolGit rev-parse HEAD))
    try { Assert-SchooltoolRepository; throw 'DETACHED_USER_COMMAND_ALLOWED' }
    catch { if ($_.Exception.Message -notmatch 'Detached HEAD') { throw } }
} finally { Pop-Location }
POWERSHELL;
    $result = runBranchWorkflowCommand($this->workflowPc, $command);
    assertBranchWorkflowSucceeded($result);
    expect(runBranchWorkflowGit($this->workflowPc, 'for-each-ref', '--format=%(refname)', 'refs/heads/'))->toBe('refs/heads/main')
        ->and(runBranchWorkflowGit($this->workflowPc, 'log', '-1', '--format=%s', runBranchWorkflowGit($this->workflowPc, 'for-each-ref', '--format=%(refname)', 'refs/schooltool/candidates/')))->toBe('Retained candidate work');
})->with(['preview', 'release']);

it('blocks preview replay after an actual publication or transfer failure', function (string $failure): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"'));
    $command = branchWorkflowPreviewMocks()."\n".'$failure = "'.$failure.'"; '.<<<'POWERSHELL'
gitpreview prepare
$id = (Get-ChildItem -LiteralPath (Get-SchooltoolPreviewDirectory) -Filter '*.receipt').BaseName
$script:publicationCalls = 0
$script:transferCalls = 0
$script:originalGit = (Get-Command Invoke-SchooltoolGit).ScriptBlock
function Invoke-SchooltoolGit {
    if ($args[0] -ceq 'push') {
        $script:publicationCalls++
        if ($failure -ceq 'push') { throw 'EXPECTED_PUSH_FAILURE' }
    }
    & $script:originalGit @args
}
function Send-SchooltoolPreview { $script:transferCalls++; throw 'EXPECTED_TRANSFER_FAILURE' }
function Invoke-SchooltoolReleaseChecks { throw 'CHECKS_MUST_NOT_REPEAT' }
try { gitpreview resume $id; throw 'FIRST_ATTEMPT_SHOULD_FAIL' }
catch { if ($_.Exception.Message -cnotmatch '^EXPECTED_(PUSH|TRANSFER)_FAILURE$') { throw } }
if (-not (Test-Path (Join-Path (Get-SchooltoolPreviewDirectory) "$id.started"))) { throw 'Publication marker was lost.' }
try { gitpreview resume $id; throw 'REPLAY_WAS_ALLOWED' }
catch { if ($_.Exception.Message -notmatch 'Publication was already attempted') { throw } }
Write-Output "PUBLICATION_CALLS=$script:publicationCalls TRANSFER_CALLS=$script:transferCalls"
POWERSHELL;
    $result = runBranchWorkflowCommand($this->workflowPc, $command);
    assertBranchWorkflowSucceeded($result);
    expect($result->getOutput())->toContain('PUBLICATION_CALLS=1 TRANSFER_CALLS='.($failure === 'push' ? '0' : '1'))
        ->and($result->getOutput())->not->toContain('CHECKS_MUST_NOT_REPEAT')
        ->and(glob($this->workflowPc.'/.git/schooltool-preview/*.started'))->toHaveCount(1)
        ->and(runBranchWorkflowGit($this->workflowRemote, 'rev-parse', 'main'))->toBe($this->workflowMain);
})->with(['push', 'transfer']);

it('refuses changed detached candidate identity without changing user branches', function (string $mutation, string $message): void {
    $command = <<<'POWERSHELL'
$candidate = New-SchooltoolCandidateWorktree -Kind release -SourceCommit HEAD
Push-Location $candidate.Path
try {
POWERSHELL;
    $result = runBranchWorkflowCommand($this->workflowPc, $command."\n".$mutation."\nSave-SchooltoolCandidate \$candidate\n} finally { Pop-Location }");
    expect($result->isSuccessful())->toBeFalse()
        ->and($result->getOutput())->toContain($message)
        ->and(runBranchWorkflowGit($this->workflowPc, 'rev-parse', 'main'))->toBe($this->workflowMain)
        ->and(runBranchWorkflowGit($this->workflowPc, 'for-each-ref', '--format=%(refname)', 'refs/heads/'))->toBe('refs/heads/main');
})->with([
    'foreign repository' => ['$candidate.Repository = $env:TEMP', 'belongs to another repository'],
    'wrong kind' => ['$candidate.Kind = "preview"', 'Invalid candidate recovery reference'],
    'moved recovery ref' => ['$next = Invoke-SchooltoolGit commit-tree "HEAD^{tree}" -p HEAD -m "Other writer"; Invoke-SchooltoolGit update-ref $candidate.RecoveryRef $next', 'recovery reference changed'],
    'symbolic recovery ref' => ['Invoke-SchooltoolGit symbolic-ref $candidate.RecoveryRef refs/heads/main', 'must be a direct ref'],
    'rewritten candidate history' => ['$next = Invoke-SchooltoolGit commit-tree "HEAD^{tree}" -m "Unrelated"; Invoke-SchooltoolGit checkout --detach $next', 'history changed unexpectedly'],
]);

it('recovers unchanged legacy receipts from archived detached candidates', function (string $mutation, bool $accepted): void {
    assertBranchWorkflowSucceeded(runBranchWorkflowCommand($this->workflowPc, 'gitstart "new-function"'));
    $command = branchWorkflowPreviewMocks()."\n".<<<'POWERSHELL'
gitpreview prepare
$id = (Get-ChildItem -LiteralPath (Get-SchooltoolPreviewDirectory) -Filter '*.receipt').BaseName
$receipt = Read-SchooltoolPreviewReceipt $id
ConvertTo-LegacyPreviewTestReceipt $receipt
$path = Join-Path (Get-SchooltoolPreviewDirectory) "$id.receipt"
[System.IO.File]::Delete($path)
Write-SchooltoolPreviewReceipt $receipt
$before = Get-SchooltoolFileChecksum $path
$archive = 'refs/schooltool/archived-heads/20260921-' + ('a' * 32) + '/' + $receipt.Candidate.Branch
function Invoke-SchooltoolReleaseChecks { throw 'CHECKS_MUST_NOT_REPEAT' }
function Read-Host { '' }
POWERSHELL;
    $command .= "\n".$mutation."\n".<<<'POWERSHELL'
try { gitpreview resume $id }
finally {
    if ((Get-SchooltoolFileChecksum $path) -cne $before) { throw 'Legacy receipt was rewritten.' }
    Write-Output 'LEGACY_RECEIPT_UNCHANGED'
}
POWERSHELL;
    $result = runBranchWorkflowCommand($this->workflowPc, $command);
    expect($result->isSuccessful())->toBe($accepted, $result->getOutput().$result->getErrorOutput())
        ->and($result->getOutput())->toContain('LEGACY_RECEIPT_UNCHANGED')
        ->and($result->getOutput())->not->toContain('CHECKS_MUST_NOT_REPEAT', 'PREVIEW_UPLOAD_REQUESTED')
        ->and(runBranchWorkflowGit($this->workflowRemote, 'for-each-ref', '--format=%(refname)', 'refs/heads/preview/'))->toBe('');
})->with([
    'exact archived identity' => ['', true],
    'original named candidate' => ['Invoke-SchooltoolGit -C $receipt.Candidate.Path switch -c $receipt.Candidate.Branch; Invoke-SchooltoolGit update-ref -d $archive', true],
    'missing archive' => ['Invoke-SchooltoolGit update-ref -d $archive', false],
    'moved archive' => ['Invoke-SchooltoolGit update-ref $archive $receipt.SourceCommit', false],
    'unrelated archive name' => ['Invoke-SchooltoolGit update-ref ($archive + "-other") $receipt.ArtifactCommit; Invoke-SchooltoolGit update-ref -d $archive', false],
]);

it('preserves a concurrent recovery ref update through compare and swap', function (): void {
    $result = runBranchWorkflowCommand($this->workflowPc, <<<'POWERSHELL'
$candidate = New-SchooltoolCandidateWorktree -Kind release -SourceCommit HEAD
Push-Location $candidate.Path
try {
    Invoke-SchooltoolGit commit --allow-empty -m 'Candidate work' | Out-Null
    $otherCommit = Invoke-SchooltoolGit commit-tree "HEAD^{tree}" -p $candidate.Commit -m 'Concurrent recovery'
    $originalGit = (Get-Command Invoke-SchooltoolGit).ScriptBlock
    function Invoke-SchooltoolGit {
        if ($args[0] -ceq 'update-ref' -and $args[2] -ceq $candidate.RecoveryRef) {
            & git update-ref --no-deref $candidate.RecoveryRef $otherCommit $candidate.Commit
            if ($LASTEXITCODE -ne 0) { throw 'Fixture could not create the concurrent update.' }
        }
        & $originalGit @args
    }
    try { Save-SchooltoolCandidate $candidate; throw 'CONCURRENT_REF_WAS_OVERWRITTEN' }
    catch { if ($_.Exception.Message -notmatch '^Git failed: git update-ref') { throw } }
    if ((Invoke-SchooltoolGit rev-parse $candidate.RecoveryRef) -cne $otherCommit) { throw 'Concurrent recovery was lost.' }
    Write-Output 'CONCURRENT_RECOVERY_PRESERVED'
} finally { Pop-Location }
POWERSHELL);
    assertBranchWorkflowSucceeded($result);
    expect($result->getOutput())->toContain('CONCURRENT_RECOVERY_PRESERVED')
        ->and(runBranchWorkflowGit($this->workflowPc, 'rev-parse', 'main'))->toBe($this->workflowMain);
});
