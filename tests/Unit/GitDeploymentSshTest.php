<?php

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

function deploymentSshProcess(string $code, string $shell = 'powershell'): Process
{
    $setup = <<<'POWERSHELL'
$ErrorActionPreference = 'Stop'
. $env:SCHOOLTOOL_SSH_TEST_HELPER
$env:SCHOOLTOOL_MAIN_SSH = 'schooltool-main@example.test'
$env:SCHOOLTOOL_MAIN_PATH = '/home/applications/main/public_html'
$env:SCHOOLTOOL_PREVIEW_SSH = 'schooltool-feature@example.test'
$env:SCHOOLTOOL_MAIN_UNIX_USER = ''
$env:SCHOOLTOOL_PREVIEW_UNIX_USER = ''
$env:SCHOOLTOOL_PREVIEW_PATH = '/home/applications/preview/public_html'
$env:SCHOOLTOOL_MAIN_KEY = Join-Path $env:SCHOOLTOOL_SSH_TEST_DIRECTORY 'id_test'
$env:SCHOOLTOOL_PREVIEW_KEY = $env:SCHOOLTOOL_MAIN_KEY
$env:SCHOOLTOOL_MAIN_KNOWN_HOSTS = Join-Path $env:SCHOOLTOOL_SSH_TEST_DIRECTORY 'known_hosts'
$env:SCHOOLTOOL_PREVIEW_KNOWN_HOSTS = $env:SCHOOLTOOL_MAIN_KNOWN_HOSTS
POWERSHELL;

    $directory = sys_get_temp_dir().'/schooltool-ssh-test-'.bin2hex(random_bytes(8));
    mkdir($directory);
    file_put_contents($directory.'/id_test', 'FAKE TEST KEY NEVER CONNECT');
    file_put_contents($directory.'/known_hosts', 'FAKE TEST HOST');

    try {
        $process = new Process(
            [$shell, '-NoProfile', '-NonInteractive', '-Command', $setup."\n".$code],
            dirname(__DIR__, 2),
            ['SCHOOLTOOL_SSH_TEST_HELPER' => dirname(__DIR__, 2).'/scripts/git_ssh_helpers.ps1', 'SCHOOLTOOL_SSH_TEST_DIRECTORY' => $directory, 'SCHOOLTOOL_SSH_TEST_PHP' => PHP_BINARY],
            timeout: 30,
        );
        $process->run();

        return $process;
    } finally {
        expect(str_starts_with($directory, sys_get_temp_dir().'/schooltool-ssh-test-'))->toBeTrue();
        (new Filesystem)->deleteDirectory($directory);
    }
}

beforeEach(function (): void {
    if (PHP_OS_FAMILY !== 'Windows') {
        $this->markTestSkipped('Windows-native PowerShell deployment helpers.');
    }
});

it('requires isolated SSH accounts and strict noninteractive key authentication', function (): void {
    $process = deploymentSshProcess(<<<'POWERSHELL'
$target = Get-SchooltoolDeploymentTarget 'MAIN'
foreach ($required in @('BatchMode=yes', 'PasswordAuthentication=no', 'KbdInteractiveAuthentication=no', 'PreferredAuthentications=publickey', 'IdentitiesOnly=yes', 'StrictHostKeyChecking=yes', 'UpdateHostKeys=no', 'ForwardAgent=no', 'ClearAllForwardings=yes', 'GlobalKnownHostsFile=none')) {
    if ($target.Options -notcontains $required) { throw "Missing $required" }
}
$preview = Get-SchooltoolDeploymentTarget 'PREVIEW'
if ($target.Options[0] -cne '-F' -or $target.Options[1] -cne 'none' -or $preview.Options[0] -cne '-F' -or $preview.Options[1] -cne 'none') { throw 'Inherited SSH configuration is not disabled.' }
if ($target.User -eq $preview.User) { throw 'Same account used.' }
if ((Get-SchooltoolRemoteGuard $preview) -notmatch 'pwd -P') { throw 'Canonical directory guard missing.' }
Write-Output 'STRICT_AUTHENTICATION'
POWERSHELL);
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
        ->and($process->getOutput())->toContain('STRICT_AUTHENTICATION');
});

it('prefers native Windows OpenSSH including the 32 bit Sysnative redirect before PATH clients', function (): void {
    $process = deploymentSshProcess(<<<'POWERSHELL'
$env:WINDIR = 'C:\Windows'
function Test-Path { param($LiteralPath, $PathType); return $LiteralPath -like '*Sysnative*OpenSSH*' }
function Get-Command { throw 'PATH_CLIENT_MUST_NOT_WIN' }
foreach ($name in @('ssh', 'scp')) {
    $executable = Get-SchooltoolPreviewExecutable $name
    if ($executable -notlike "*Sysnative*OpenSSH*$name.exe") { throw 'Native OpenSSH not selected.' }
}
Write-Output 'NATIVE_OPENSSH'
POWERSHELL);
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
        ->and($process->getOutput())->toContain('NATIVE_OPENSSH');
});

it('rejects unsafe or incomplete deployment configuration before connecting', function (string $assignment): void {
    $process = deploymentSshProcess($assignment."\n".<<<'POWERSHELL'
try { Get-SchooltoolDeploymentTarget 'PREVIEW'; throw 'UNSAFE_CONFIGURATION_ACCEPTED' }
catch { if ($_.Exception.Message -eq 'UNSAFE_CONFIGURATION_ACCEPTED') { throw }; Write-Output 'REJECTED' }
POWERSHELL);
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
        ->and($process->getOutput())->toContain('REJECTED');
})->with([
    'missing key' => '$env:SCHOOLTOOL_PREVIEW_KEY = ""',
    'missing host trust' => '$env:SCHOOLTOOL_PREVIEW_KNOWN_HOSTS = "C:/missing/known_hosts"',
    'relative key' => '$env:SCHOOLTOOL_PREVIEW_KEY = "id_test"',
    'wrong account' => '$env:SCHOOLTOOL_PREVIEW_SSH = "schooltool-main@example.test"',
    'unsafe unix owner' => '$env:SCHOOLTOOL_PREVIEW_UNIX_USER = "preview;whoami"',
    'shell injection' => '$env:SCHOOLTOOL_PREVIEW_SSH = "schooltool-feature@example.test;whoami"',
    'unsafe path' => '$env:SCHOOLTOOL_PREVIEW_PATH = "/home/applications/../main/public_html"',
    'noncanonical path' => '$env:SCHOOLTOOL_PREVIEW_PATH = "/home//applications/preview/public_html"',
    'wrong root' => '$env:SCHOOLTOOL_PREVIEW_PATH = "/home/applications/preview"',
]);

it('accepts canonical Cloudways domain directories while rejecting traversal segments', function (): void {
    $process = deploymentSshProcess(<<<'POWERSHELL'
$env:SCHOOLTOOL_PREVIEW_PATH = '/home/example.cloudwaysapps.com/preview/public_html'
$env:SCHOOLTOOL_PREVIEW_UNIX_USER = 'previewapp'
$target = Get-SchooltoolDeploymentTarget 'PREVIEW'
if ($target.Path -ne $env:SCHOOLTOOL_PREVIEW_PATH) { throw 'Canonical path changed.' }
if ($target.LoginUser -ne 'schooltool-feature' -or $target.User -ne 'previewapp') { throw 'Login and Unix identities were conflated.' }
if ((Get-SchooltoolRemoteGuard $target) -notmatch "= 'previewapp'") { throw 'Verified Unix owner not enforced.' }
function Get-SchooltoolPreviewExecutable { throw 'SCP_MUST_NOT_RUN' }
foreach ($path in @('/tmp/..', '/tmp/.', '/home/example.cloudwaysapps.com/../archive.stpreview', '/tmp//archive.stpreview')) {
    try { Copy-SchooltoolRemoteFile $target 'local' $path; throw 'UNSAFE_PATH_ACCEPTED' }
    catch { if ($_.Exception.Message -in @('SCP_MUST_NOT_RUN','UNSAFE_PATH_ACCEPTED')) { throw } }
}
Write-Output 'CANONICAL_PATH_CHECKED'
POWERSHELL);
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
        ->and($process->getOutput())->toContain('CANONICAL_PATH_CHECKED');
});

it('propagates SSH errors without treating them as deployment success', function (): void {
    $process = deploymentSshProcess(<<<'POWERSHELL'
function Get-SchooltoolPreviewExecutable { 'Mock-Ssh' }
function Mock-Ssh { $global:LASTEXITCODE = 23 }
try { Invoke-SchooltoolRemote (Get-SchooltoolDeploymentTarget 'PREVIEW') 'true'; throw 'FAILED_SSH_ACCEPTED' }
catch { if ($_.Exception.Message -eq 'FAILED_SSH_ACCEPTED') { throw }; Write-Output $_.Exception.Message }
POWERSHELL);
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
        ->and($process->getOutput())->toContain('failed (exit 23)', 'no rollback is assumed');
});

it('forbids remote path injection before invoking a transfer process', function (): void {
    $process = deploymentSshProcess(<<<'POWERSHELL'
function Get-SchooltoolPreviewExecutable { throw 'SCP_MUST_NOT_RUN' }
try { Copy-SchooltoolRemoteFile (Get-SchooltoolDeploymentTarget 'PREVIEW') 'local' '/tmp/file;whoami'; throw 'UNSAFE_PATH_ACCEPTED' }
catch { if ($_.Exception.Message -in @('SCP_MUST_NOT_RUN','UNSAFE_PATH_ACCEPTED')) { throw }; Write-Output 'TRANSFER_REJECTED' }
POWERSHELL);
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
        ->and($process->getOutput())->toContain('TRANSFER_REJECTED');
});

function deploymentBinaryTransferFixture(): string
{
    return <<<'POWERSHELL'
$mock = Join-Path $env:SCHOOLTOOL_SSH_TEST_DIRECTORY 'fake ssh.php'
$child = @'
<?php
file_put_contents(getenv('SCHOOLTOOL_SSH_TEST_DIRECTORY').'/arguments.json', json_encode($argv));
$source = getenv('SCHOOLTOOL_SSH_TEST_DIRECTORY').'/bytes.bin';
$mode = getenv('SCHOOLTOOL_TRANSFER_TEST_MODE');
fwrite(STDERR, str_repeat('PRIVATE_DIAGNOSTIC', 16384));
if (in_array($mode, ['upload', 'failure-upload'], true)) {
    $output = fopen(getenv('SCHOOLTOOL_SSH_TEST_DIRECTORY').'/uploaded.bin', 'wb');
    stream_copy_to_stream(STDIN, $output);
    fclose($output);
} else {
    $input = fopen($source, 'rb');
    stream_copy_to_stream($input, STDOUT);
    fclose($input);
}
exit(in_array($mode, ['failure', 'failure-upload'], true) ? 23 : 0);
'@
[System.IO.File]::WriteAllText($mock, $child, (New-Object System.Text.UTF8Encoding($false)))
function Get-SchooltoolPreviewExecutable { param($Name); if ($Name -ne 'ssh') { throw 'SFTP_MUST_NOT_RUN' }; $env:SCHOOLTOOL_SSH_TEST_PHP }
$target = Get-SchooltoolDeploymentTarget 'PREVIEW'
$target.Options = @($mock) + @($target.Options)
$bytes = New-Object byte[] (2 * 1024 * 1024 + 31)
for ($i = 0; $i -lt $bytes.Length; $i++) { $bytes[$i] = $i % 256 }
$source = Join-Path $env:SCHOOLTOOL_SSH_TEST_DIRECTORY 'bytes.bin'
[System.IO.File]::WriteAllBytes($source, $bytes)
$expected = Get-SchooltoolFileChecksum $source
POWERSHELL;
}

it('streams binary uploads and downloads through native SSH without text conversion or diagnostic leakage', function (string $shell): void {
    $process = deploymentSshProcess(deploymentBinaryTransferFixture()."\n".<<<'POWERSHELL'
$env:SCHOOLTOOL_TRANSFER_TEST_MODE = 'upload'
if (@(Copy-SchooltoolRemoteFile $target $source '/tmp/private-transfer/release.tar.gz').Count -ne 0) { throw 'UPLOAD_POLLUTED_PIPELINE' }
if ((Get-SchooltoolFileChecksum (Join-Path $env:SCHOOLTOOL_SSH_TEST_DIRECTORY 'uploaded.bin')) -ne $expected) { throw 'UPLOAD_BYTES_CHANGED' }
$arguments = Get-Content -LiteralPath (Join-Path $env:SCHOOLTOOL_SSH_TEST_DIRECTORY 'arguments.json') -Raw -Encoding UTF8 | ConvertFrom-Json
foreach ($required in @('BatchMode=yes', 'StrictHostKeyChecking=yes', 'ForwardAgent=no', '-T', '--', 'schooltool-feature@example.test')) {
    if ($arguments -notcontains $required) { throw "Missing transfer option $required" }
}
$command = $arguments[-1]
if (-not $command.Contains((Get-SchooltoolRemoteGuard $target)) -or -not $command.Contains("'$expected'")) { throw 'TRANSFER_GUARD_MISSING' }
if ($command -notmatch 'base64_decode\("([A-Za-z0-9+/=]+)"\)') { throw 'REMOTE_PROGRAM_QUOTING_CHANGED' }
$remoteProgram = [Text.Encoding]::UTF8.GetString([Convert]::FromBase64String($Matches[1]))
foreach ($required in @("'x+b'", 'realpath($parent)', 'posix_geteuid()', 'hash_equals', 'stream_copy_to_stream')) {
    if (-not $remoteProgram.Contains($required)) { throw "Remote safeguard missing: $required" }
}
$env:SCHOOLTOOL_TRANSFER_TEST_MODE = 'download'
$destination = Join-Path $env:SCHOOLTOOL_SSH_TEST_DIRECTORY 'download with spaces.bin'
if (@(Copy-SchooltoolRemoteFile $target $destination '/private/archive.stpreview' -Download).Count -ne 0) { throw 'DOWNLOAD_POLLUTED_PIPELINE' }
if ((Get-SchooltoolFileChecksum $destination) -ne $expected) { throw 'DOWNLOAD_BYTES_CHANGED' }
if (@(Get-ChildItem -LiteralPath $env:SCHOOLTOOL_SSH_TEST_DIRECTORY -Filter '*.partial').Count -ne 0) { throw 'TEMPORARY_FILE_RETAINED' }
Write-Output 'BINARY_TRANSFER_VERIFIED'
POWERSHELL, $shell);
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
        ->and($process->getOutput())->toContain('BINARY_TRANSFER_VERIFIED')->not->toContain('PRIVATE_DIAGNOSTIC')
        ->and($process->getErrorOutput())->not->toContain('PRIVATE_DIAGNOSTIC');
})->with(['powershell', 'pwsh']);

it('rejects failed binary transfers and preserves existing local files', function (string $shell): void {
    $process = deploymentSshProcess(deploymentBinaryTransferFixture()."\n".<<<'POWERSHELL'
$env:SCHOOLTOOL_TRANSFER_TEST_MODE = 'failure'
$destination = Join-Path $env:SCHOOLTOOL_SSH_TEST_DIRECTORY 'failed-download.bin'
try { Copy-SchooltoolRemoteFile $target $destination '/private/archive.stpreview' -Download; throw 'FAILED_TRANSFER_ACCEPTED' }
catch { if ($_.Exception.Message -notmatch 'exit 23') { throw } }
if (Test-Path -LiteralPath $destination) { throw 'FAILED_DOWNLOAD_PUBLISHED' }
if (@(Get-ChildItem -LiteralPath $env:SCHOOLTOOL_SSH_TEST_DIRECTORY -Filter '*.partial').Count -ne 0) { throw 'FAILED_DOWNLOAD_RETAINED' }
$env:SCHOOLTOOL_TRANSFER_TEST_MODE = 'failure-upload'
try { Copy-SchooltoolRemoteFile $target $source '/private/archive.stpreview'; throw 'FAILED_UPLOAD_ACCEPTED' }
catch { if ($_.Exception.Message -notmatch 'exit 23') { throw } }
$env:SCHOOLTOOL_TRANSFER_TEST_MODE = 'download'
try { Copy-SchooltoolRemoteFile $target $source '/private/archive.stpreview' -Download; throw 'EXISTING_FILE_OVERWRITTEN' }
catch { if ($_.Exception.Message -notmatch 'already exists') { throw } }
if ((Get-SchooltoolFileChecksum $source) -ne $expected) { throw 'EXISTING_FILE_CHANGED' }
Write-Output 'TRANSFER_FAILURE_HANDLED'
POWERSHELL, $shell);
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
        ->and($process->getOutput())->toContain('TRANSFER_FAILURE_HANDLED')->not->toContain('PRIVATE_DIAGNOSTIC');
})->with(['powershell', 'pwsh']);

it('refuses malformed remote JSON instead of assuming a snapshot is ready', function (): void {
    $process = deploymentSshProcess(<<<'POWERSHELL'
function Invoke-SchooltoolRemote { 'not-json' }
try { Invoke-SchooltoolRemoteJson (Get-SchooltoolDeploymentTarget 'PREVIEW') 'true'; throw 'BAD_METADATA_ACCEPTED' }
catch { if ($_.Exception.Message -eq 'BAD_METADATA_ACCEPTED') { throw }; Write-Output $_.Exception.Message }
POWERSHELL);
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
        ->and($process->getOutput())->toContain('did not return valid deployment metadata');
});

it('rejects invalid release proof responses without treating saved releases as approved', function (string $mutation): void {
    $command = <<<'POWERSHELL'
$script:proofResponse = @{commit=('a'*40);run_id=123;run_attempt=1;lane='full';url='https://github.com/ITStudioAT/schooltool/actions/runs/123'}
$script:proofExit = 0
$script:malformed = $false
function php {
    $global:LASTEXITCODE = $script:proofExit
    if ($script:malformed) { 'not-json' } else { $script:proofResponse | ConvertTo-Json -Compress }
}
POWERSHELL;
    $command .= "\n".$mutation."\n".<<<'POWERSHELL'
try { Assert-SchooltoolCiRelease -Commit ('a'*40); throw 'INVALID_PROOF_ACCEPTED' }
catch { if ($_.Exception.Message -ceq 'INVALID_PROOF_ACCEPTED') { throw }; Write-Output 'INVALID_PROOF_BLOCKED' }
POWERSHELL;
    $process = deploymentSshProcess($command);
    expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput())
        ->and($process->getOutput())->toContain('INVALID_PROOF_BLOCKED');
})->with([
    'failed proof command' => '$script:proofExit = 1',
    'invalid JSON' => '$script:malformed = $true',
    'stale commit' => '$script:proofResponse.commit = ("b"*40)',
    'missing run' => '$script:proofResponse.Remove("run_id")',
    'missing attempt' => '$script:proofResponse.Remove("run_attempt")',
    'unknown lane' => '$script:proofResponse.lane = "skipped"',
    'foreign repository URL' => '$script:proofResponse.url = "https://github.com/other/repository/actions/runs/123"',
    'shell syntax in run ID' => '$script:proofResponse.run_id = "123;echo"',
]);

it('deploys only an unchanged release with the same successful CI attempt after confirmation', function (string $scenario, bool $allowed): void {
    $command = '$script:scenario = '.var_export($scenario, true)."\n".<<<'POWERSHELL'
$script:confirmation = $false
$script:proofCalls = 0
function Assert-SchooltoolRepository {}
function Assert-SchooltoolClean {}
function Update-SchooltoolRemote {}
function Invoke-SchooltoolGit {
    $arguments = $args -join ' '
    if ($arguments -like '*:deployment/*') {
        if ($args[0] -cne '--no-replace-objects') { throw 'Release metadata may be substituted through Git replace refs.' }
        $arguments = $args[1..($args.Count-1)] -join ' '
    }
    switch ($arguments) {
        'branch --show-current' { 'main'; return }
        'rev-parse HEAD' { if ($script:confirmation -and $script:scenario -eq 'checkout changed') { 'e'*40 } else { 'a'*40 }; return }
        'rev-parse refs/remotes/origin/main' { if ($script:confirmation -and $script:scenario -eq 'main changed') { 'f'*40 } else { 'a'*40 }; return }
    }
    if ($arguments -like 'show *:deployment/source-commit') { 'b'*40; return }
    if ($arguments -like 'show *:deployment/frontend-build.sha256') { 'c'*64; return }
    if ($arguments -like 'rev-parse *:deployment/source-manifest.sha256') { 'd'*40; return }
    throw "Unexpected Git fixture: $arguments"
}
function Assert-SchooltoolCiRelease {
    param([string]$Commit)
    if ($Commit -cne ('a'*40)) { throw 'Unpinned proof request.' }
    $script:proofCalls++
    if ($script:scenario -eq 'not approved' -or ($script:proofCalls -eq 2 -and $script:scenario -eq 'approval revoked')) { throw 'GitHub checks not successful.' }
    $attempt = if ($script:proofCalls -eq 2 -and $script:scenario -eq 'attempt changed') { 2 } else { 1 }
    [pscustomobject]@{commit=$Commit;run_id=123;run_attempt=$attempt;lane='full';url='https://github.com/ITStudioAT/schooltool/actions/runs/123'}
}
function Read-Host { $script:confirmation=$true; if ($script:scenario -eq 'cancelled') { '' } else { 'LIVE' } }
function Invoke-SchooltoolRemote {
    param($Target,[string]$Command)
    if ($Command -like '*composer pdeploy') {
        if ($script:proofCalls -ne 2 -or $Command -notlike "*SCHOOLTOOL_CI_RUN_ID='123' SCHOOLTOOL_CI_RUN_ATTEMPT='1' composer pdeploy") { throw 'Deployment lost its checked proof.' }
        Write-Output 'PINNED_LIVE_DEPLOYMENT'
    } else { Write-Output 'READ_ONLY_PREFLIGHT' }
}
try { gitdeploy }
catch { Write-Output ('DEPLOYMENT_BLOCKED: ' + $_.Exception.Message) }
POWERSHELL;
    $process = deploymentSshProcess($command);
    expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
    if ($allowed) {
        expect($process->getOutput())->toContain('PINNED_LIVE_DEPLOYMENT')->not->toContain('DEPLOYMENT_BLOCKED');
    } else {
        expect($process->getOutput())->toContain('DEPLOYMENT_BLOCKED')->not->toContain('PINNED_LIVE_DEPLOYMENT');
    }
    if ($scenario === 'not approved') {
        expect($process->getOutput())->not->toContain('READ_ONLY_PREFLIGHT');
    }
})->with([
    'same approved attempt' => ['approved', true],
    'pending or failed first proof' => ['not approved', false],
    'approval revoked during confirmation' => ['approval revoked', false],
    'rerun started during confirmation' => ['attempt changed', false],
    'remote main advanced' => ['main changed', false],
    'local checkout changed' => ['checkout changed', false],
    'operator cancelled' => ['cancelled', false],
]);
