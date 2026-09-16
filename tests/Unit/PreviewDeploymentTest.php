<?php

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

function previewBashExecutable(): string
{
    return PHP_OS_FAMILY === 'Windows' ? 'C:/Program Files/Git/bin/bash.exe' : 'bash';
}

function previewBashPath(string $path): string
{
    $path = str_replace('\\', '/', $path);

    return preg_replace_callback('/^([A-Za-z]):\//', fn (array $match): string => '/'.strtolower($match[1]).'/', $path);
}

it('keeps preview and production shell deployment entrypoints syntactically valid', function (string $script): void {
    $process = new Process([previewBashExecutable(), '-n', 'scripts/'.$script], dirname(__DIR__, 2));
    $process->run();

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
})->with(['deploy_preview_cloudways.sh', 'deploy_cloudways.sh', 'pdeploy_cloudways.sh']);

it('refuses every normal deployment launcher on a configured preview instance', function (string $script): void {
    $directory = sys_get_temp_dir().'/schooltool-preview-guard-'.bin2hex(random_bytes(8));
    mkdir($directory.'/scripts', 0777, true);
    copy(dirname(__DIR__, 2).'/scripts/'.$script, $directory.'/scripts/'.$script);
    file_put_contents($directory.'/.env', "APP_ENV=production\nSCHOOLTOOL_PREVIEW_INSTANCE=\"true\"\n");

    try {
        $arguments = str_ends_with($script, '.php') ? [PHP_BINARY, 'scripts/'.$script, '--dry-run'] : [previewBashExecutable(), 'scripts/'.$script];
        $process = new Process($arguments, $directory);
        $process->run();
        expect($process->isSuccessful())->toBeFalse()
            ->and($process->getErrorOutput())->toContain('disabled on the preview instance');
    } finally {
        (new Filesystem)->deleteDirectory($directory);
    }
})->with(['update.php', 'deploy_cloudways.sh', 'pdeploy_cloudways.sh']);

it('guards preview identity and runs only the isolated deployment commands', function (string $scenario): void {
    $directory = sys_get_temp_dir().'/schooltool-preview-deploy-'.bin2hex(random_bytes(8));
    $candidate = $directory.'/candidate';
    $target = $directory.'/application/public_html';
    mkdir($candidate.'/scripts', 0777, true);
    mkdir($candidate.'/deployment');
    mkdir($target, 0777, true);
    if ($scenario === 'public storage exposed') {
        mkdir($target.'/public/storage', 0777, true);
    }
    mkdir($directory.'/bin');
    copy(dirname(__DIR__, 2).'/scripts/deploy_preview_cloudways.sh', $candidate.'/scripts/deploy_preview_cloudways.sh');
    file_put_contents($candidate.'/deployment/source-commit', str_repeat('a', 40));
    file_put_contents($target.'/.env', 'SCHOOLTOOL_PREVIEW_INSTANCE='.($scenario === 'wrong instance' ? 'false' : 'true')."\n");
    $executables = [
        'id' => "#!/bin/bash\nprintf '%s\\n' \"\$PREVIEW_TEST_ACCOUNT\"\n",
        'php' => <<<'BASH'
#!/bin/bash
printf 'php %s\n' "$*" >> "$PREVIEW_TEST_LOG"
if [ "$PREVIEW_TEST_FAILURE" = check ] && [ "${2:-}" = preview:check ]; then exit 1; fi
BASH,
        'composer' => "#!/bin/bash\nprintf 'composer %s\\n' \"\$*\" >> \"\$PREVIEW_TEST_LOG\"\n",
        'rsync' => "#!/bin/bash\nprintf 'rsync %s\\n' \"\$*\" >> \"\$PREVIEW_TEST_LOG\"\n",
        'flock' => "#!/bin/bash\nshift 4\nexec \"\$@\"\n",
    ];
    foreach ($executables as $name => $contents) {
        file_put_contents($directory.'/bin/'.$name, str_replace("\r\n", "\n", $contents));
        chmod($directory.'/bin/'.$name, 0777);
    }
    $environment = [
        'PATH' => previewBashPath($directory.'/bin').':/usr/bin:/bin',
        'PREVIEW_TEST_LOG' => previewBashPath($directory.'/commands.log'),
        'PREVIEW_TEST_ACCOUNT' => $scenario === 'wrong account' ? 'sftp_schooltool_at' : 'schooltool-feature',
        'PREVIEW_TEST_FAILURE' => $scenario === 'runtime check failure' ? 'check' : '',
    ];

    try {
        $process = new Process([previewBashExecutable(), '-c', 'export PATH="$1:/usr/bin:/bin"; shift; exec bash "$@"', 'preview-test', previewBashPath($directory.'/bin'), previewBashPath($candidate.'/scripts/deploy_preview_cloudways.sh'), previewBashPath($target), 'feature/new-function', str_repeat('b', 64)], $directory, $environment);
        $process->run();
        $commands = is_file($directory.'/commands.log') ? file_get_contents($directory.'/commands.log') : '';
        expect($commands)->not->toContain('app:update', 'migrate', 'db:seed', 'horizon', 'queue:restart', 'cache:clear', 'optimize:clear', 'schedule:');

        if ($scenario === 'success') {
            expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput())
                ->and($commands)->toContain('preview:check', '--no-scripts', 'config:cache', 'view:cache', 'artisan up')
                ->and($commands)->toContain('--exclude=/.env', '--exclude=/storage');
        } else {
            expect($process->isSuccessful())->toBeFalse()
                ->and($commands)->not->toContain('rsync ', 'artisan up');
        }
    } finally {
        (new Filesystem)->deleteDirectory($directory);
    }
})->with(['success', 'wrong account', 'wrong instance', 'runtime check failure', 'public storage exposed']);

it('uses interactive SSH without storing credentials and validates the preview destination', function (): void {
    if (PHP_OS_FAMILY !== 'Windows') {
        $this->markTestSkipped('Windows PowerShell preview transport verification.');
    }
    $command = <<<'POWERSHELL'
$ErrorActionPreference = 'Stop'
. $env:PREVIEW_TEST_HELPERS
function ssh { Write-Output ('SSH ' + ($args -join '|')); $global:LASTEXITCODE = 0 }
function scp { Write-Output ('SCP ' + ($args -join '|')); $global:LASTEXITCODE = 0 }
function Get-SchooltoolPreviewExecutable { param([string]$Name) $Name }
$env:SCHOOLTOOL_PREVIEW_PATH = '/home/example/applications/preview/public_html'
$env:SCHOOLTOOL_PREVIEW_SSH = 'schooltool-feature@165.227.156.99'
Send-SchooltoolPreview -Archive 'C:/preview.tar.gz' -Checksum ('b' * 64) -Id ('a' * 32) -SourceBranch 'feature/new-function'
$env:SCHOOLTOOL_PREVIEW_SSH = 'sftp_schooltool_at@165.227.156.99'
try { Get-SchooltoolPreviewTarget; exit 2 } catch { Write-Output 'PRODUCTION_ACCOUNT_REJECTED' }
POWERSHELL;
    $process = new Process(['powershell', '-NoProfile', '-NonInteractive', '-Command', $command], dirname(__DIR__, 2), ['PREVIEW_TEST_HELPERS' => dirname(__DIR__, 2).'/scripts/git_helpers.ps1']);
    $process->run();

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
        ->and($process->getOutput())->toContain('SSH -t', 'SCP ', '$(id -un)', 'sha256sum -c', 'PRODUCTION_ACCOUNT_REJECTED')
        ->and($process->getOutput())->not->toContain('BatchMode=yes', 'StrictHostKeyChecking=no', 'sshpass');
});

it('resolves the bundled Windows SSH tools before uploading anything', function (): void {
    if (PHP_OS_FAMILY !== 'Windows') {
        $this->markTestSkipped('Windows SSH executable resolution.');
    }

    $command = <<<'POWERSHELL'
$ErrorActionPreference = 'Stop'
. $env:PREVIEW_TEST_HELPERS
function Get-Command { $null }
function Test-Path { param($LiteralPath, $PathType) $LiteralPath -match 'Git[\\/]usr[\\/]bin[\\/](ssh|scp)\.exe$' }
foreach ($name in @('ssh', 'scp')) {
    $resolved = Get-SchooltoolPreviewExecutable $name
    if (-not $resolved.EndsWith("$name.exe")) { throw 'Bundled executable not resolved.' }
    Write-Output "BUNDLED_$name"
}
function Test-Path { $false }
try { Get-SchooltoolPreviewExecutable 'ssh'; exit 2 } catch { Write-Output 'MISSING_CLIENT_REJECTED' }
POWERSHELL;
    $process = new Process(['powershell', '-NoProfile', '-NonInteractive', '-Command', $command], dirname(__DIR__, 2), ['PREVIEW_TEST_HELPERS' => dirname(__DIR__, 2).'/scripts/git_preview_helpers.ps1']);
    $process->run();

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
        ->and($process->getOutput())->toContain('BUNDLED_ssh', 'BUNDLED_scp', 'MISSING_CLIENT_REJECTED');
});
