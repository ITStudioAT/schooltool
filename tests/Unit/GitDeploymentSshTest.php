<?php

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

function deploymentSshProcess(string $code): Process
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
            ['powershell', '-NoProfile', '-NonInteractive', '-Command', $setup."\n".$code],
            dirname(__DIR__, 2),
            ['SCHOOLTOOL_SSH_TEST_HELPER' => dirname(__DIR__, 2).'/scripts/git_ssh_helpers.ps1', 'SCHOOLTOOL_SSH_TEST_DIRECTORY' => $directory],
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

it('forbids remote path injection before invoking scp', function (): void {
    $process = deploymentSshProcess(<<<'POWERSHELL'
function Get-SchooltoolPreviewExecutable { throw 'SCP_MUST_NOT_RUN' }
try { Copy-SchooltoolRemoteFile (Get-SchooltoolDeploymentTarget 'PREVIEW') 'local' '/tmp/file;whoami'; throw 'UNSAFE_PATH_ACCEPTED' }
catch { if ($_.Exception.Message -in @('SCP_MUST_NOT_RUN','UNSAFE_PATH_ACCEPTED')) { throw }; Write-Output 'TRANSFER_REJECTED' }
POWERSHELL);
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
        ->and($process->getOutput())->toContain('TRANSFER_REJECTED');
});

it('refuses malformed remote JSON instead of assuming a snapshot is ready', function (): void {
    $process = deploymentSshProcess(<<<'POWERSHELL'
function Invoke-SchooltoolRemote { 'not-json' }
try { Invoke-SchooltoolRemoteJson (Get-SchooltoolDeploymentTarget 'PREVIEW') 'true'; throw 'BAD_METADATA_ACCEPTED' }
catch { if ($_.Exception.Message -eq 'BAD_METADATA_ACCEPTED') { throw }; Write-Output $_.Exception.Message }
POWERSHELL);
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
        ->and($process->getOutput())->toContain('did not return valid deployment metadata');
});
