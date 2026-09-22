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

it('normalizes native fixture paths on both local and hosted Windows drives', function (string $path, string $expected): void {
    expect(previewBashPath($path))->toBe($expected);
})->with([
    ['C:/Users/runneradmin/AppData/Local/Temp/application/public_html', '/c/Users/runneradmin/AppData/Local/Temp/application/public_html'],
    ['C:\\Users\\runneradmin\\AppData\\Local\\Temp\\application\\public_html', '/c/Users/runneradmin/AppData/Local/Temp/application/public_html'],
    ['D:/a/_temp/application/public_html', '/d/a/_temp/application/public_html'],
    ['D:\\a\\_temp\\application\\public_html', '/d/a/_temp/application/public_html'],
]);

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
    $temporaryRoot = realpath(sys_get_temp_dir());
    if ($temporaryRoot === false) {
        throw new RuntimeException('The preview fixture requires an existing temporary directory.');
    }
    // Hosted Windows may return an 8.3 alias containing ~, which is not a valid server path.
    $directory = $temporaryRoot.'/schooltool-preview-deploy-'.bin2hex(random_bytes(8));
    $candidate = $directory.'/candidate';
    $target = $directory.'/application/public_html';
    $privateDirectory = $directory.'/application/private_html/schooltool-preview';
    $composerCache = $privateDirectory.'/composer-cache';
    if ((str_contains($scenario, 'symlink') || $scenario === 'composer cache child unsafe mode') && PHP_OS_FAMILY === 'Windows') {
        $this->markTestSkipped('Real POSIX symlink and writable-mode rejection runs on Linux.');
    }
    mkdir($candidate.'/scripts', 0777, true);
    mkdir($candidate.'/deployment');
    mkdir($target, 0777, true);
    mkdir($privateDirectory, 0700, true);
    chmod($privateDirectory, 0700);
    if ($scenario === 'composer cache is file') {
        file_put_contents($composerCache, 'preserve');
    } elseif ($scenario === 'composer cache symlink') {
        symlink($directory, $composerCache);
    } elseif ($scenario === 'composer parent symlink') {
        rmdir($privateDirectory);
        symlink($directory, $privateDirectory);
    } elseif (in_array($scenario, ['composer cache reused', 'composer cache wrong owner', 'composer cache unsafe mode', 'composer cache child symlink', 'composer cache child unsafe mode', 'composer cache child hardlink'], true)) {
        mkdir($composerCache, 0700);
        chmod($composerCache, 0700);
        file_put_contents($composerCache.'/existing-package.zip', 'cached package fixture');
        if ($scenario === 'composer cache child symlink') {
            symlink($directory, $composerCache.'/escape');
        }
        if ($scenario === 'composer cache child unsafe mode') {
            chmod($composerCache.'/existing-package.zip', 0666);
        }
        if ($scenario === 'composer cache child hardlink') {
            link($composerCache.'/existing-package.zip', $composerCache.'/linked-package.zip');
        }
    }
    if ($scenario === 'public storage exposed') {
        mkdir($target.'/public/storage', 0777, true);
    }
    if ($scenario === 'runtime path is file') {
        file_put_contents($target.'/storage', 'do not overwrite');
    }
    if ($scenario === 'config cache is directory') {
        mkdir($target.'/bootstrap/cache/config.php', 0777, true);
    }
    mkdir($directory.'/bin');
    copy(dirname(__DIR__, 2).'/scripts/deploy_preview_cloudways.sh', $candidate.'/scripts/deploy_preview_cloudways.sh');
    file_put_contents($candidate.'/deployment/source-commit', str_repeat('a', 40));
    file_put_contents($target.'/.env', 'SCHOOLTOOL_PREVIEW_INSTANCE='.($scenario === 'wrong instance' ? 'false' : 'true')."\n");
    $executables = [
        'id' => "#!/bin/bash\nif [ \"\$1\" = -u ]; then exec /usr/bin/id -u; fi\nprintf '%s\\n' \"\$PREVIEW_TEST_ACCOUNT\"\n",
        'stat' => <<<'BASH'
#!/bin/bash
path="${@: -1}"
if [[ "$path" == */private_html* ]] || [[ "$path" == */application ]]; then
    if [ "$2" = %U ]; then
        if { [ "$PREVIEW_TEST_FAILURE" = composer-owner ] && [[ "$path" == */composer-cache ]]; } || { [ "$PREVIEW_TEST_FAILURE" = composer-parent-owner ] && [[ "$path" == */private_html ]]; }; then
            printf 'another-app\n'
        elif [[ "$path" == */application ]]; then printf 'root\n';
        else printf '%s\n' "$PREVIEW_TEST_OWNER"; fi
    elif { [ "$PREVIEW_TEST_FAILURE" = composer-mode ] && [[ "$path" == */composer-cache ]]; } || { [ "$PREVIEW_TEST_FAILURE" = composer-parent-mode ] && [[ "$path" == */schooltool-preview ]]; }; then
        printf '755\n'
    elif [ "$PREVIEW_TEST_WINDOWS" = 1 ]; then printf '700\n';
    else /usr/bin/stat "$@"; fi
elif [ "$2" = %a ]; then
    if [ "$PREVIEW_TEST_FAILURE" = cache-mode ]; then printf '644\n';
    elif [ "$PREVIEW_TEST_WINDOWS" = 1 ]; then cat "$PREVIEW_TEST_CACHE_MODE";
    else /usr/bin/stat "$@"; fi
elif [ "$PREVIEW_TEST_FAILURE" = cache-owner ] && [ "${@: -1}" = bootstrap/cache/config.php ]; then
    printf 'another-app\n'
else
    printf '%s\n' "$PREVIEW_TEST_OWNER"
fi
BASH,
        'php' => <<<'BASH'
#!/bin/bash
printf 'php %s\n' "$*" >> "$PREVIEW_TEST_LOG"
if [ "$COMPOSER_CACHE_DIR" != "$PREVIEW_TEST_EXTERNAL_CACHE" ]; then exit 41; fi
if [ "$PREVIEW_TEST_FAILURE" = check ] && [ "${2:-}" = preview:check ]; then exit 1; fi
if [ "$PREVIEW_TEST_FAILURE" = stale-plan ] && [ "${3:-}" = assert-plan ]; then exit 1; fi
if [ "${3:-}" = assert-plan ] && [ "$LARAVEL_STORAGE_PATH" != "$PREVIEW_TEST_TARGET_STORAGE" ]; then exit 42; fi
if [ "$PREVIEW_TEST_FAILURE" = import ] && [ "${3:-}" = import ]; then exit 1; fi
if [ "$PREVIEW_TEST_FAILURE" = migrate ] && [ "${2:-}" = migrate ]; then exit 1; fi
if [ "$PREVIEW_TEST_FAILURE" = activate ] && [ "${3:-}" = activate ]; then exit 1; fi
if [ "${2:-}" = preview:snapshot ] && [ "${3:-}" = receive ]; then printf '/private/incoming.stpreview\n'; fi
if [ "${2:-}" = config:cache ]; then
    printf 'config cache umask %s\n' "$(umask)" >> "$PREVIEW_TEST_LOG"
    if [ "$PREVIEW_TEST_FAILURE" != cache-missing ]; then
        printf 'private configuration fixture\n' > bootstrap/cache/config.php
        # NTFS does not enforce POSIX modes; retain the actual creation mask for the Windows stat fixture.
        printf '%o\n' "$((0666 & ~$(umask)))" > "$PREVIEW_TEST_CACHE_MODE"
    fi
fi
if [ "${2:-}" = view:cache ] || [ "${2:-}" = install ]; then
    printf 'public assets umask %s\n' "$(umask)" >> "$PREVIEW_TEST_LOG"
fi
BASH,
        'composer' => <<<'BASH'
#!/bin/bash
printf 'composer %s\n' "$*" >> "$PREVIEW_TEST_LOG"
printf 'composer cache %s\n' "$COMPOSER_CACHE_DIR" >> "$PREVIEW_TEST_LOG"
printf 'package cache fixture\n' > "$COMPOSER_CACHE_DIR/download.zip"
BASH,
        'mkdir' => <<<'BASH'
#!/bin/bash
if [[ "${@: -1}" == */composer-cache ]]; then
    printf 'composer cache creation umask %s\n' "$(umask)" > "$PREVIEW_TEST_CACHE_CREATION"
fi
exec /usr/bin/mkdir "$@"
BASH,
        'rsync' => "#!/bin/bash\nprintf 'rsync %s\\n' \"\$*\" >> \"\$PREVIEW_TEST_LOG\"\n",
        'flock' => "#!/bin/bash\nprintf 'preview lock acquired\\n' >> \"\$PREVIEW_TEST_LOG\"\nshift 4\nexec \"\$@\"\n",
    ];
    foreach ($executables as $name => $contents) {
        file_put_contents($directory.'/bin/'.$name, str_replace("\r\n", "\n", $contents));
        chmod($directory.'/bin/'.$name, 0777);
    }
    $environment = [
        'PATH' => previewBashPath($directory.'/bin').':/usr/bin:/bin',
        'PREVIEW_TEST_LOG' => previewBashPath($directory.'/commands.log'),
        'PREVIEW_TEST_TARGET_STORAGE' => previewBashPath($target.'/storage'),
        'PREVIEW_TEST_CACHE_MODE' => previewBashPath($directory.'/cache-mode'),
        'PREVIEW_TEST_CACHE_CREATION' => previewBashPath($directory.'/cache-creation'),
        'COMPOSER_CACHE_DIR' => previewBashPath($directory.'/external-cache-must-not-be-used'),
        'PREVIEW_TEST_EXTERNAL_CACHE' => previewBashPath($directory.'/external-cache-must-not-be-used'),
        'PREVIEW_TEST_WINDOWS' => PHP_OS_FAMILY === 'Windows' ? '1' : '0',
        'PREVIEW_TEST_ACCOUNT' => $scenario === 'wrong account' ? 'sftp_schooltool_at' : 'schooltool-feature',
        'PREVIEW_TEST_OWNER' => $scenario === 'wrong owner' ? 'another-app' : 'schooltool-feature',
        'PREVIEW_TEST_FAILURE' => match ($scenario) {
            'runtime check failure' => 'check',
            'stale preview plan', 'stale snapshot plan' => 'stale-plan',
            'snapshot failure' => 'import',
            'migration failure' => 'migrate',
            'activation failure' => 'activate',
            'config cache missing' => 'cache-missing',
            'config cache unsafe mode' => 'cache-mode',
            'config cache wrong owner' => 'cache-owner',
            'composer cache wrong owner' => 'composer-owner',
            'composer parent wrong owner' => 'composer-parent-owner',
            'composer cache unsafe mode' => 'composer-mode',
            'composer parent unsafe mode' => 'composer-parent-mode',
            default => '',
        },
    ];

    try {
        $snapshot = in_array($scenario, ['snapshot success', 'snapshot failure', 'stale snapshot plan'], true);
        $process = new Process([previewBashExecutable(), '-c', 'umask 022; export PATH="$1:/usr/bin:/bin"; shift; exec bash "$@"', 'preview-test', previewBashPath($directory.'/bin'), previewBashPath($candidate.'/scripts/deploy_preview_cloudways.sh'), previewBashPath($target), 'feature/new-function', str_repeat('b', 64), str_repeat('c', 32), $snapshot ? '/tmp/schooltool-preview-'.str_repeat('d', 32).'/'.str_repeat('e', 32).'.stpreview' : '-', $snapshot ? str_repeat('f', 64) : '-', 'schooltool-feature', $scenario === 'missing preview plan' ? '' : str_repeat('a', 64)], $directory, $environment);
        $process->run();
        $commands = is_file($directory.'/commands.log') ? file_get_contents($directory.'/commands.log') : '';
        expect($commands)->not->toContain('app:update', 'db:seed', 'horizon', 'queue:restart', 'cache:clear', 'optimize:clear', 'schedule:');

        if (in_array($scenario, ['success', 'snapshot success', 'composer cache reused'], true)) {
            expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput())
                ->and($commands)->toContain('preview:check', '--no-scripts', 'config:cache', 'view:cache', 'artisan up')
                ->and($commands)->toContain('--exclude=/.env', '--exclude=/storage', 'artisan migrate --force --no-interaction', 'preview:snapshot activate');
            expect(file_get_contents($target.'/bootstrap/cache/config.php'))->toBe("private configuration fixture\n")
                ->and($commands)->toContain('config cache umask 0077')
                ->and(substr_count($commands, 'public assets umask 0022'))->toBe(2);
            expect($commands)->toContain('composer cache '.previewBashPath($composerCache))
                ->and(file_get_contents($composerCache.'/download.zip'))->toBe("package cache fixture\n")
                ->and(is_dir($directory.'/external-cache-must-not-be-used'))->toBeFalse();
            expect($commands)->toContain('preview:snapshot assert-plan', '--state-token='.str_repeat('a', 64))
                ->and(strpos($commands, 'preview lock acquired'))->toBeLessThan(strpos($commands, 'preview:snapshot assert-plan'))
                ->and(strpos($commands, 'preview:snapshot assert-plan'))->toBeLessThan(strpos($commands, 'rsync '));
            if ($scenario === 'composer cache reused') {
                expect(file_get_contents($composerCache.'/existing-package.zip'))->toBe('cached package fixture')
                    ->and(file_exists($directory.'/cache-creation'))->toBeFalse();
            } else {
                expect(file_get_contents($directory.'/cache-creation'))->toBe("composer cache creation umask 0077\n");
            }
            if ($snapshot) {
                expect($commands)->toContain('preview:snapshot receive', 'preview:snapshot import', '--replace')
                    ->and(strpos($commands, 'preview:snapshot import'))->toBeLessThan(strpos($commands, 'artisan migrate'))
                    ->and(strpos($commands, 'artisan migrate'))->toBeLessThan(strpos($commands, 'preview:snapshot activate'))
                    ->and(strpos($commands, 'preview:snapshot activate'))->toBeLessThan(strpos($commands, 'artisan up'));
            } else {
                expect($commands)->toContain('preview:snapshot assert-current', 'preview:snapshot checkpoint')->not->toContain('preview:snapshot import')
                    ->and(strpos($commands, 'preview:snapshot checkpoint'))->toBeLessThan(strpos($commands, 'artisan migrate'));
            }
        } else {
            expect($process->isSuccessful())->toBeFalse()
                ->and($commands)->not->toContain('artisan up');
            if (in_array($scenario, ['snapshot failure', 'migration failure', 'activation failure', 'config cache missing', 'config cache unsafe mode', 'config cache wrong owner'], true)) {
                expect($commands)->toContain('rsync ')->and($process->getErrorOutput())->toContain('remains in maintenance');
            } else {
                expect($commands)->not->toContain('rsync ', 'artisan migrate');
            }
            if ($scenario === 'runtime path is file') {
                expect($commands)->toBe('')->and(file_get_contents($target.'/storage'))->toBe('do not overwrite');
            }
            if (in_array($scenario, ['stale preview plan', 'stale snapshot plan'], true)) {
                expect($commands)->toContain('preview:snapshot assert-plan')
                    ->not->toContain('preview:snapshot receive', 'preview:snapshot import', 'preview:snapshot checkpoint', 'file_put_contents');
            }
            if ($scenario === 'missing preview plan') {
                expect($commands)->toBe('')->and($process->getErrorOutput())->toContain('state token is required');
            }
            if (in_array($scenario, ['config cache missing', 'config cache unsafe mode', 'config cache wrong owner'], true)) {
                expect($commands)->not->toContain('preview:snapshot activate', 'artisan view:cache')
                    ->and($process->getErrorOutput())->toContain('configuration cache must be a private regular file');
            }
            if (str_starts_with($scenario, 'composer ')) {
                expect($commands)->toBe("preview lock acquired\n")
                    ->and($process->getErrorOutput())->toContain('Preview Composer cache');
            }
        }
    } finally {
        (new Filesystem)->deleteDirectory($directory);
    }
})->with(['success', 'snapshot success', 'wrong account', 'wrong owner', 'wrong instance', 'runtime check failure', 'stale preview plan', 'stale snapshot plan', 'missing preview plan', 'public storage exposed', 'runtime path is file', 'config cache is directory', 'snapshot failure', 'migration failure', 'activation failure', 'config cache missing', 'config cache unsafe mode', 'config cache wrong owner', 'composer cache reused', 'composer cache is file', 'composer cache symlink', 'composer parent symlink', 'composer cache wrong owner', 'composer parent wrong owner', 'composer cache unsafe mode', 'composer parent unsafe mode', 'composer cache child symlink', 'composer cache child unsafe mode', 'composer cache child hardlink']);

it('transfers a preview with strict key authentication and private snapshot permissions', function (bool $snapshot): void {
    if (PHP_OS_FAMILY !== 'Windows') {
        $this->markTestSkipped('Windows PowerShell preview transport verification.');
    }
    $directory = sys_get_temp_dir().'/schooltool-preview-transport-'.bin2hex(random_bytes(8));
    mkdir($directory);
    file_put_contents($directory.'/key', 'FAKE KEY');
    file_put_contents($directory.'/hosts', 'FAKE HOST');
    file_put_contents($directory.'/preview.tar.gz', gzencode('Preview bundle fixture'));
    file_put_contents($directory.'/snapshot-source.bin', "\x00\xFF\x80Encrypted snapshot fixture\r\n");
    $command = <<<'POWERSHELL'
$ErrorActionPreference = 'Stop'
. $env:PREVIEW_TEST_HELPERS
function Invoke-PreviewTestSsh { Write-Output ('SSH ' + ($args -join '|')); $global:LASTEXITCODE = 0 }
function Get-SchooltoolPreviewExecutable { param([string]$Name); if ($Name -ne 'ssh') { throw 'UNEXPECTED_TRANSPORT' }; 'Invoke-PreviewTestSsh' }
$env:SCHOOLTOOL_PREVIEW_PATH = '/home/example/applications/preview/public_html'
$env:SCHOOLTOOL_PREVIEW_SSH = 'schooltool-feature@example.test'
$env:SCHOOLTOOL_PREVIEW_UNIX_USER = 'schooltool-feature'
$env:SCHOOLTOOL_MAIN_PATH = '/home/example/applications/main/public_html'
$env:SCHOOLTOOL_MAIN_SSH = 'schooltool-main@example.test'
$env:SCHOOLTOOL_MAIN_UNIX_USER = 'schooltool-main'
$env:SCHOOLTOOL_MAIN_KEY = Join-Path $env:PREVIEW_TEST_DIRECTORY 'key'
$env:SCHOOLTOOL_PREVIEW_KEY = $env:SCHOOLTOOL_MAIN_KEY
$env:SCHOOLTOOL_MAIN_KNOWN_HOSTS = Join-Path $env:PREVIEW_TEST_DIRECTORY 'hosts'
$env:SCHOOLTOOL_PREVIEW_KNOWN_HOSTS = $env:SCHOOLTOOL_MAIN_KNOWN_HOSTS
function Invoke-SchooltoolRemoteJson {
    param($Target, $Command)
    if ($Target.Site -ne 'MAIN') { throw 'Snapshot export must use main.' }
    if ($Command -notmatch "--artifact='([a-f0-9]{32})'") { throw 'Invalid export command.' }
    [pscustomobject]@{ artifact=$Matches[1]; path="/home/main/snapshots/$($Matches[1]).stpreview"; sha256=(Get-SchooltoolFileChecksum (Join-Path $env:PREVIEW_TEST_DIRECTORY 'snapshot-source.bin')) }
}
function Copy-SchooltoolRemoteFile {
    param($Target, [string]$LocalPath, [string]$RemotePath, [switch]$Download)
    foreach ($required in @('BatchMode=yes', 'StrictHostKeyChecking=yes', 'PasswordAuthentication=no', 'IdentitiesOnly=yes', 'ForwardAgent=no')) {
        if ($Target.Options -notcontains $required) { throw "Missing transfer option $required" }
    }
    $snapshotFixture = Join-Path $env:PREVIEW_TEST_DIRECTORY 'snapshot-source.bin'
    if ($Download) {
        if ($Target.Site -ne 'MAIN' -or $RemotePath -notmatch '^/home/main/snapshots/[a-f0-9]{32}\.stpreview$' -or (Test-Path -LiteralPath $LocalPath)) { throw 'Invalid private snapshot download.' }
        [IO.File]::WriteAllBytes($LocalPath, [IO.File]::ReadAllBytes($snapshotFixture))
        Write-Output "TRANSFER DOWNLOAD|MAIN|$RemotePath"
    } else {
        if ($Target.Site -ne 'PREVIEW' -or $RemotePath -notmatch '^/tmp/schooltool-preview-[a-f0-9]{32}/(?:release\.tar\.gz|[a-f0-9]{32}\.stpreview)$') { throw 'Invalid preview upload.' }
        $expected = if ($RemotePath.EndsWith('/release.tar.gz')) { Join-Path $env:PREVIEW_TEST_DIRECTORY 'preview.tar.gz' } else { $snapshotFixture }
        if (-not (Test-Path -LiteralPath $LocalPath -PathType Leaf) -or (Get-SchooltoolFileChecksum $LocalPath) -ne (Get-SchooltoolFileChecksum $expected)) { throw 'Upload fixture bytes changed or missing.' }
        Write-Output "TRANSFER UPLOAD|PREVIEW|$RemotePath"
    }
}
$target = Get-SchooltoolPreviewTarget
$status = [pscustomobject]@{ needs_snapshot=($env:PREVIEW_TEST_SNAPSHOT -eq '1'); public_key=('c' * 64); state_token=('b' * 64) }
$archive = Join-Path $env:PREVIEW_TEST_DIRECTORY 'preview.tar.gz'
Send-SchooltoolPreview -Archive $archive -Checksum (Get-SchooltoolFileChecksum $archive) -Id ('a' * 32) -SourceBranch 'feature/new-function' -FeatureId ('d' * 32) -Target $target -SnapshotStatus $status
$env:SCHOOLTOOL_PREVIEW_SSH = 'schooltool-main@example.test'
try { Get-SchooltoolPreviewTarget; exit 2 } catch { Write-Output 'PRODUCTION_ACCOUNT_REJECTED' }
POWERSHELL;
    try {
        $process = new Process(['powershell', '-NoProfile', '-NonInteractive', '-Command', $command], dirname(__DIR__, 2), [
            'PREVIEW_TEST_HELPERS' => dirname(__DIR__, 2).'/scripts/git_helpers.ps1',
            'PREVIEW_TEST_DIRECTORY' => $directory,
            'PREVIEW_TEST_SNAPSHOT' => $snapshot ? '1' : '0',
        ]);
        $process->run();

        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
            ->and($process->getOutput())->toContain('SSH -F|none', '-T|schooltool-feature@example.test', 'BatchMode=yes', 'StrictHostKeyChecking=yes', 'TRANSFER UPLOAD|PREVIEW|', '$(id -un)', 'sha256sum -c', 'PRODUCTION_ACCOUNT_REJECTED')
            ->and(substr_count($process->getOutput(), 'TRANSFER UPLOAD|PREVIEW|'))->toBe($snapshot ? 2 : 1)
            ->and(substr_count($process->getOutput(), 'TRANSFER DOWNLOAD|MAIN|'))->toBe($snapshot ? 1 : 0)
            ->and(glob($directory.'/*.stpreview'))->toBe([])
            ->and($process->getOutput())->not->toContain('StrictHostKeyChecking=no', 'sshpass');
        if ($snapshot) {
            expect($process->getOutput())->toContain('chmod 600', 'preview:snapshot delete');
        }
    } finally {
        (new Filesystem)->deleteDirectory($directory);
    }
})->with([false, true]);

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
