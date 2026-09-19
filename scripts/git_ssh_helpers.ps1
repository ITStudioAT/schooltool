function Get-SchooltoolPreviewExecutable {
    param([ValidateSet('ssh', 'scp')][string]$Name)

    $windowsDirectory = [Environment]::GetEnvironmentVariable('WINDIR')
    if ($windowsDirectory) {
        foreach ($systemDirectory in @('System32', 'Sysnative')) {
            $nativeExecutable = Join-Path $windowsDirectory "$systemDirectory/OpenSSH/$Name.exe"
            if (Test-Path -LiteralPath $nativeExecutable -PathType Leaf) { return $nativeExecutable }
        }
    }

    $command = Get-Command $Name -CommandType Application -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($command) { return $command.Source }

    $bundledExecutable = Join-Path $env:ProgramFiles "Git/usr/bin/$Name.exe"
    if (Test-Path -LiteralPath $bundledExecutable -PathType Leaf) { return $bundledExecutable }
    throw "$Name was not found. Install Windows OpenSSH Client or Git for Windows."
}

function Get-SchooltoolDeploymentTarget {
    param([ValidateSet('MAIN', 'PREVIEW')][string]$Site)

    $prefix = "SCHOOLTOOL_${Site}"
    $scope = if ([Environment]::GetEnvironmentVariable("${prefix}_SSH", 'Process')) { 'Process' } else { 'User' }
    $address = [Environment]::GetEnvironmentVariable("${prefix}_SSH", $scope)
    $path = [Environment]::GetEnvironmentVariable("${prefix}_PATH", $scope)
    $key = [Environment]::GetEnvironmentVariable("${prefix}_KEY", $scope)
    $unixUser = [Environment]::GetEnvironmentVariable("${prefix}_UNIX_USER", $scope)
    $knownHosts = [Environment]::GetEnvironmentVariable("${prefix}_KNOWN_HOSTS", $scope)
    if (-not $knownHosts) { $knownHosts = Join-Path $env:USERPROFILE '.ssh/known_hosts' }

    if ($address -cnotmatch '^(?<user>[a-z_][a-z0-9_-]*)@(?<host>[a-zA-Z0-9][a-zA-Z0-9.-]*)$') {
        throw "Set ${prefix}_SSH to the verified application account in user@host form."
    }
    $account = $Matches.user
    if (($Site -eq 'PREVIEW' -and $account -ne 'schooltool-feature') -or ($Site -eq 'MAIN' -and $account -eq 'schooltool-feature')) {
        throw 'Production and preview must use separate application SSH accounts; preview requires schooltool-feature.'
    }
    if (-not $unixUser) { $unixUser = $account }
    if ($unixUser -cnotmatch '^[a-z_][a-z0-9_-]*$') {
        throw "Set ${prefix}_UNIX_USER to the verified application owner returned by id -un."
    }
    if (-not $path -or $path -cnotmatch '^/(?:[a-zA-Z0-9_-][a-zA-Z0-9_.-]*/)+public_html$') {
        throw "Set ${prefix}_PATH to the verified canonical application public_html directory."
    }
    foreach ($file in @(@{ Name = "${prefix}_KEY"; Path = $key }, @{ Name = "${prefix}_KNOWN_HOSTS"; Path = $knownHosts })) {
        if (-not $file.Path -or -not [System.IO.Path]::IsPathRooted($file.Path) -or $file.Path -match '[\r\n\x00]' -or -not (Test-Path -LiteralPath $file.Path -PathType Leaf)) {
            throw "Configure $($file.Name) with an existing absolute local file path. No server was changed."
        }
    }
    if ($key.EndsWith('.pub', [StringComparison]::OrdinalIgnoreCase)) {
        throw "${prefix}_KEY must name the private key file, not its public .pub file."
    }

    [pscustomobject]@{
        Site = $Site
        Ssh = $address
        User = $unixUser
        LoginUser = $account
        Path = $path
        Options = @(
            '-F', 'none',
            '-o', 'BatchMode=yes', '-o', 'PasswordAuthentication=no', '-o', 'KbdInteractiveAuthentication=no',
            '-o', 'PreferredAuthentications=publickey', '-o', 'IdentitiesOnly=yes',
            '-o', 'StrictHostKeyChecking=yes', '-o', 'UpdateHostKeys=no', '-o', 'ForwardAgent=no',
            '-o', 'ClearAllForwardings=yes', '-o', 'ConnectTimeout=15',
            '-o', 'ServerAliveInterval=15', '-o', 'ServerAliveCountMax=3',
            '-o', 'GlobalKnownHostsFile=none', '-o', "UserKnownHostsFile=$knownHosts", '-i', $key
        )
    }
}

function Get-SchooltoolRemoteGuard {
    param([object]$Target)

    # Values originate only from the narrow configuration allowlists above.
    "set -eu; test `"`$(id -un)`" = '$($Target.User)'; cd '$($Target.Path)'; test `"`$(pwd -P)`" = '$($Target.Path)'; test -f artisan; test -f composer.json; "
}

function Invoke-SchooltoolRemote {
    param([object]$Target, [string]$Command)

    $executable = Get-SchooltoolPreviewExecutable 'ssh'
    $options = @($Target.Options)
    & $executable @options -T -- $Target.Ssh ((Get-SchooltoolRemoteGuard $Target) + $Command)
    if ($LASTEXITCODE -ne 0) {
        throw "SSH operation for $($Target.Site) failed (exit $LASTEXITCODE). Check the application account, unlocked key and verified host key. A failed deployment may require recovery; no rollback is assumed."
    }
}

function Invoke-SchooltoolRemoteJson {
    param([object]$Target, [string]$Command)

    $output = @(Invoke-SchooltoolRemote -Target $Target -Command $Command) -join "`n"
    try { $output | ConvertFrom-Json -ErrorAction Stop }
    catch { throw "The $($Target.Site) application did not return valid deployment metadata. Install the new server commands first." }
}

function Copy-SchooltoolRemoteFile {
    param([object]$Target, [string]$LocalPath, [string]$RemotePath, [switch]$Download)

    if ($RemotePath -cnotmatch '^/(?:[a-zA-Z0-9_-][a-zA-Z0-9_.-]*/)*[a-zA-Z0-9_-][a-zA-Z0-9_.-]*$') {
        throw 'The remote transfer path is invalid.'
    }
    # SSH and SFTP can see different roots on Cloudways. Keep canonical SSH paths and byte streams.
    $remoteProgram = @'
$path = $argv[1];
$upload = $argv[2] === 'upload';
$parent = dirname($path);
if (realpath($parent) !== $parent || ! is_dir($parent) || is_link($path)
    || fileowner($parent) !== posix_geteuid() || (fileperms($parent) & 0022) !== 0) { exit(21); }
$handle = null;
$created = false;
$complete = false;
try {
    if ($upload) {
        umask(0077);
        $handle = fopen($path, 'x+b');
        if ($handle === false) { exit(22); }
        $created = true;
        if (! chmod($path, 0600)) { throw new RuntimeException(); }
        $bytes = stream_copy_to_stream(STDIN, $handle);
        if ($bytes === false || ! fflush($handle) || $bytes !== (int) $argv[3]
            || ! hash_equals($argv[4], hash_file('sha256', $path))) { throw new RuntimeException(); }
    } else {
        $info = lstat($path);
        if ($info === false || ($info['mode'] & 0170000) !== 0100000
            || $info['uid'] !== posix_geteuid() || $info['nlink'] !== 1) { exit(23); }
        $handle = fopen($path, 'rb');
        if ($handle === false) { exit(24); }
        $opened = fstat($handle);
        if ($opened['ino'] !== $info['ino'] || $opened['dev'] !== $info['dev']) { throw new RuntimeException(); }
        $bytes = stream_copy_to_stream($handle, STDOUT);
        if ($bytes === false || $bytes !== $opened['size']) { throw new RuntimeException(); }
    }
    $complete = true;
} catch (Throwable $exception) {
    // Do not emit remote paths, credentials or file bytes as diagnostics.
} finally {
    if (is_resource($handle)) { fclose($handle); }
    if ($created && ! $complete) { unlink($path); }
}
exit($complete ? 0 : 25);
'@
    $encodedProgram = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($remoteProgram))
    $process = New-Object System.Diagnostics.Process
    $localStream = $null
    $temporary = $null
    $started = $false
    try {
        $localFile = [System.IO.Path]::GetFullPath($LocalPath)
        if ($Download) {
            if (Test-Path -LiteralPath $localFile) { throw 'The local download destination already exists.' }
            $temporary = "$localFile.$([guid]::NewGuid().ToString('N')).partial"
            $localStream = [System.IO.File]::Open($temporary, [System.IO.FileMode]::CreateNew, [System.IO.FileAccess]::Write, [System.IO.FileShare]::None)
            $mode = 'download'
            $length = '0'
            $checksum = '-'
        }
        else {
            $localStream = [System.IO.File]::Open($localFile, [System.IO.FileMode]::Open, [System.IO.FileAccess]::Read, [System.IO.FileShare]::Read)
            $hasher = [System.Security.Cryptography.SHA256]::Create()
            try { $checksum = [BitConverter]::ToString($hasher.ComputeHash($localStream)).Replace('-', '').ToLowerInvariant() }
            finally { $hasher.Dispose() }
            $localStream.Position = 0
            $length = $localStream.Length.ToString([Globalization.CultureInfo]::InvariantCulture)
            $mode = 'upload'
        }
        $command = (Get-SchooltoolRemoteGuard $Target) + "php -d display_errors=0 -d log_errors=0 -r 'eval(base64_decode(`"$encodedProgram`"));' -- '$RemotePath' '$mode' '$length' '$checksum'"
        $arguments = @($Target.Options) + @('-T', '--', $Target.Ssh, $command)
        $process.StartInfo.FileName = Get-SchooltoolPreviewExecutable 'ssh'
        # ProcessStartInfo.ArgumentList is unavailable in Windows PowerShell 5.1.
        $process.StartInfo.Arguments = ($arguments | ForEach-Object {
            '"' + [regex]::Replace([regex]::Replace([string]$_, '(\\*)"', '$1$1\"'), '(\\+)$', '$1$1') + '"'
        }) -join ' '
        $process.StartInfo.UseShellExecute = $false
        $process.StartInfo.CreateNoWindow = $true
        $process.StartInfo.RedirectStandardInput = $true
        $process.StartInfo.RedirectStandardOutput = $true
        $process.StartInfo.RedirectStandardError = $true
        # .NET Framework derives its input writer from Console.InputEncoding and emits its BOM at Start.
        $inputEncoding = [Console]::InputEncoding
        try {
            [Console]::InputEncoding = New-Object System.Text.UTF8Encoding($false)
            $started = $process.Start()
        }
        finally { [Console]::InputEncoding = $inputEncoding }
        if (-not $started) { throw 'Cannot start the SSH transfer.' }
        $errors = $process.StandardError.BaseStream.CopyToAsync([System.IO.Stream]::Null)
        $destination = if ($Download) { $localStream } else { [System.IO.Stream]::Null }
        $output = $process.StandardOutput.BaseStream.CopyToAsync($destination)
        if (-not $Download) { $null = $localStream.CopyToAsync($process.StandardInput.BaseStream).GetAwaiter().GetResult() }
        # Close the raw pipe: StreamWriter.Close can append a UTF-8 BOM in PowerShell 5.1.
        $process.StandardInput.BaseStream.Close()
        $null = $output.GetAwaiter().GetResult()
        $null = $errors.GetAwaiter().GetResult()
        $process.WaitForExit()
        if ($process.ExitCode -ne 0) { throw "SSH transfer failed (exit $($process.ExitCode))." }
        $localStream.Dispose()
        $localStream = $null
        if ($Download) {
            [System.IO.File]::Move($temporary, $localFile)
            $temporary = $null
        }
    }
    catch {
        throw "Encrypted file transfer for $($Target.Site) failed. Deployment was not completed. $($_.Exception.Message)"
    }
    finally {
        if ($started -and -not $process.HasExited) { $process.Kill(); $process.WaitForExit() }
        $process.Dispose()
        if ($localStream) { $localStream.Dispose() }
        if ($temporary -and [System.IO.File]::Exists($temporary)) { [System.IO.File]::Delete($temporary) }
    }
}

function Get-SchooltoolFileChecksum {
    param([string]$Path)

    $stream = [System.IO.File]::OpenRead($Path)
    $hasher = [System.Security.Cryptography.SHA256]::Create()
    try { [BitConverter]::ToString($hasher.ComputeHash($stream)).Replace('-', '').ToLowerInvariant() }
    finally { $stream.Dispose(); $hasher.Dispose() }
}

function gitdeploy {
    Assert-SchooltoolRepository
    Assert-SchooltoolClean
    $target = Get-SchooltoolDeploymentTarget 'MAIN'
    Update-SchooltoolRemote
    $mainCommit = Invoke-SchooltoolGit rev-parse refs/remotes/origin/main
    if ($mainCommit -cnotmatch '^[a-f0-9]{40,64}$') { throw 'No verified main commit is available.' }
    $sourceCommit = (Invoke-SchooltoolGit show "${mainCommit}:deployment/source-commit").Trim()
    $archiveHash = ((Invoke-SchooltoolGit show "${mainCommit}:deployment/frontend-build.sha256") -split '\s+')[0]
    $manifestBlob = Invoke-SchooltoolGit rev-parse "${mainCommit}:deployment/source-manifest.sha256"
    if ($sourceCommit -cnotmatch '^[a-f0-9]{40,64}$' -or $archiveHash -cnotmatch '^[a-f0-9]{64}$' -or $manifestBlob -cnotmatch '^(?:[a-f0-9]{40}|[a-f0-9]{64})$') {
        throw 'main does not contain a valid release artifact. Publish it with gitsave or gitrelease first.'
    }
    Invoke-SchooltoolRemote -Target $target -Command 'test -f scripts/pdeploy_cloudways.sh; grep -q SCHOOLTOOL_EXPECTED_SOURCE_MANIFEST_BLOB scripts/pdeploy_cloudways.sh; command -v composer >/dev/null'
    Write-Host "Live target: $($target.Ssh) $($target.Path)" -ForegroundColor Yellow
    Write-Host "GitHub main: $mainCommit. The application update includes its planned live database migrations." -ForegroundColor Yellow
    if ((Read-Host 'Deploy main to the live application? Type LIVE') -cne 'LIVE') { throw 'Live deployment cancelled.' }
    Update-SchooltoolRemote
    if ((Invoke-SchooltoolGit rev-parse refs/remotes/origin/main) -ne $mainCommit) { throw 'main changed during confirmation. Review the new version and rerun gitdeploy.' }
    Invoke-SchooltoolRemote -Target $target -Command "SCHOOLTOOL_EXPECTED_MAIN_COMMIT='$mainCommit' SCHOOLTOOL_EXPECTED_SOURCE_COMMIT='$sourceCommit' SCHOOLTOOL_EXPECTED_FRONTEND_SHA256='$archiveHash' SCHOOLTOOL_EXPECTED_SOURCE_MANIFEST_BLOB='$manifestBlob' composer pdeploy"
    Write-Host "Live deployment completed for the confirmed main $mainCommit." -ForegroundColor Green
}
