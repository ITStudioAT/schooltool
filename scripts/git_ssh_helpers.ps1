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

function Assert-SchooltoolCiRelease {
    param([Parameter(Mandatory = $true)][string]$Commit)
    if ($Commit -cnotmatch '^[a-f0-9]{40}$') { throw 'Invalid release commit for GitHub verification.' }
    $json = & php (Join-Path $PSScriptRoot 'ci-release-proof.php') verify --commit $Commit
    if ($LASTEXITCODE -ne 0) { throw 'This exact release has no successful required GitHub checks. Live deployment is blocked.' }
    try { $proof = $json | ConvertFrom-Json -ErrorAction Stop }
    catch { throw 'GitHub returned no valid release proof. Live deployment is blocked.' }
    if ($proof.commit -cne $Commit -or [string]$proof.run_id -cnotmatch '^[1-9][0-9]*$' -or
        [string]$proof.run_attempt -cnotmatch '^[1-9][0-9]*$' -or $proof.lane -cnotin @('full', 'documentation') -or
        $proof.url -cne "https://github.com/ITStudioAT/schooltool/actions/runs/$($proof.run_id)") {
        throw 'GitHub returned an inconsistent release proof. Live deployment is blocked.'
    }
    $proof
}

function Get-SchooltoolCiStatus {
    param([Parameter(Mandatory = $true)][string]$Commit)
    if ($Commit -cnotmatch '^[a-f0-9]{40}$') { throw 'Invalid release commit for GitHub verification.' }
    $json = & php (Join-Path $PSScriptRoot 'ci-release-proof.php') status --commit $Commit
    if ($LASTEXITCODE -ne 0) { throw 'Cannot read trusted GitHub check status. Check GitHub access and the diagnostic above. https://github.com/ITStudioAT/schooltool/actions/workflows/ci.yml' }
    try { $status = $json | ConvertFrom-Json -ErrorAction Stop }
    catch { throw 'GitHub returned invalid check status. Live deployment is blocked.' }
    if ($status.commit -cne $Commit -or $status.status -cnotin @('missing', 'queued', 'requested', 'waiting', 'pending', 'in_progress', 'completed')) {
        throw 'GitHub returned inconsistent check status. Live deployment is blocked.'
    }
    if ($status.status -cne 'missing' -and ([string]$status.run_id -cnotmatch '^[1-9][0-9]*$' -or
        [string]$status.run_attempt -cnotmatch '^[1-9][0-9]*$' -or
        $status.url -cne "https://github.com/ITStudioAT/schooltool/actions/runs/$($status.run_id)")) {
        throw 'GitHub returned an inconsistent check identity. Live deployment is blocked.'
    }
    $status
}

function Assert-SchooltoolCiMain {
    param([Parameter(Mandatory = $true)][string]$Commit)
    $remoteMain = @(Invoke-SchooltoolGit ls-remote --refs origin refs/heads/main)
    if ($remoteMain.Count -ne 1 -or ($remoteMain[0] -split '\s+')[0] -cne $Commit) {
        throw 'GitHub main changed while waiting for checks. No deployment started. Review the new release with gitcheck, then rerun gitdeploy.'
    }
}

function Wait-SchooltoolCiRelease {
    param(
        [Parameter(Mandatory = $true)][string]$Commit,
        [ValidateRange(1, 7200)][int]$TimeoutSeconds = 7200,
        [ValidateRange(1, 120)][int]$DiscoverySeconds = 120,
        [ValidateRange(1, 60)][int]$PollSeconds = 15
    )
    $clock = [System.Diagnostics.Stopwatch]::StartNew()
    $previousProgress = ''
    $seenRun = $false
    $url = 'https://github.com/ITStudioAT/schooltool/actions/workflows/ci.yml?query=branch%3Amain'
    Write-Host "Checking GitHub CI for release $Commit. Waiting up to $([int]($TimeoutSeconds / 60)) minutes; Ctrl+C cancels without deploying." -ForegroundColor Cyan
    try {
        while ($true) {
            Assert-SchooltoolCiMain -Commit $Commit
            try { $status = Get-SchooltoolCiStatus -Commit $Commit }
            catch { throw "Cannot continue waiting for trusted checks. $($_.Exception.Message) Review GitHub access and the run before retrying. $url" }
            $url = $status.url
            if ($status.status -eq 'missing') {
                Write-Host "CI run not visible yet; waiting for GitHub. $url" -ForegroundColor Yellow
                if ($seenRun -or $clock.Elapsed.TotalSeconds -ge $DiscoverySeconds) {
                    throw "No exact release check is available. Check Actions/push permissions for $Commit before rerunning gitdeploy. $url"
                }
            }
            else {
                $seenRun = $true
                Write-Host ("CI {0}, attempt {1}, elapsed {2:hh\:mm\:ss}. {3}" -f $status.status, $status.run_attempt, $clock.Elapsed, $url) -ForegroundColor Cyan
                $progress = $status.jobs | ConvertTo-Json -Depth 5 -Compress
                if ($progress -cne $previousProgress) {
                    foreach ($job in $status.jobs) {
                        $label = if ($job.status -eq 'completed') { $job.conclusion } else { $job.status }
                        Write-Host "  [$label] $($job.name)"
                    }
                    $previousProgress = $progress
                }
                $failedJobs = @($status.jobs | Where-Object {
                    $_.status -eq 'completed' -and $_.conclusion -notin @('success', 'skipped')
                })
                if ($failedJobs.Count -gt 0 -or ($status.status -eq 'completed' -and $status.conclusion -ne 'success')) {
                    $details = ($failedJobs | ForEach-Object { "$($_.name): $($_.conclusion)" }) -join '; '
                    throw "GitHub checks failed or were cancelled ($($status.conclusion)). $details Open the run, resolve the failed jobs or cancellation, then rerun gitdeploy after successful checks. $url"
                }
                if ($status.status -eq 'completed') {
                    try { $proof = Assert-SchooltoolCiRelease -Commit $Commit }
                    catch { throw "GitHub finished but the required trusted release proof is invalid. $($_.Exception.Message) Review the required jobs before rerunning gitdeploy. $url" }
                    Assert-SchooltoolCiMain -Commit $Commit
                    Write-Host "Required GitHub checks verified. $($proof.url)" -ForegroundColor Green
                    return $proof
                }
            }
            if ($clock.Elapsed.TotalSeconds -ge $TimeoutSeconds) {
                throw "Stopped waiting after $TimeoutSeconds seconds; no deployment started. Checks continue on GitHub. Review the run, then rerun gitdeploy to resume waiting. $url"
            }
            Start-Sleep -Seconds $PollSeconds
        }
    }
    finally { $clock.Stop() }
}

function Assert-SchooltoolReleasePackage {
    param([string]$Commit, [string]$SourceCommit)
    $result = & php (Join-Path $PSScriptRoot 'release-policy.php') assets --base $Commit --head $Commit
    if ($LASTEXITCODE -ne 0) { throw "Release package verification failed: $($result -join ' ')" }
    $integrity = ($result -join "`n") | ConvertFrom-Json -ErrorAction Stop
    if ($integrity.equivalent -ne $true -or $integrity.base -cne $Commit -or $integrity.head -cne $Commit) { throw 'Invalid release package verification result.' }
    $artifacts = @('deployment/frontend-build.sha256', 'deployment/frontend-build.tar.gz', 'deployment/source-commit', 'deployment/source-manifest.sha256')
    foreach ($path in @(Invoke-SchooltoolGit --no-replace-objects diff --no-renames --name-only $SourceCommit $Commit)) {
        if ($path -cnotin $artifacts) { throw 'The release commit contains changes beyond its bound artifacts.' }
    }
}

function Send-SchooltoolLiveRelease {
    param($Target, [string]$Commit, [string]$SourceCommit, [string]$ArchiveHash, [string]$ManifestBlob)
    # Run the exact release launcher, including on servers still using the old CI handoff.
    $launcher = (Invoke-SchooltoolGit --no-replace-objects show "${Commit}:scripts/pdeploy_cloudways.sh") -join "`n"
    if (-not $launcher.Contains('background-ci-v1') -or -not $launcher.Contains('SCHOOLTOOL_DEPLOY_PROJECT_DIRECTORY')) {
        throw 'Publish the background CI workflow to main before using this gitdeploy.'
    }
    $id = [guid]::NewGuid().ToString('N')
    $localPath = Join-Path ([System.IO.Path]::GetTempPath()) "schooltool-pdeploy-$id.sh"
    $remotePath = "$($Target.Path)/storage/framework/schooltool-pdeploy-$id.sh"
    $uploaded = $false
    try {
        [System.IO.File]::WriteAllText($localPath, ($launcher.Replace("`r`n", "`n") + "`n"), (New-Object System.Text.UTF8Encoding($false)))
        $launcherHash = Get-SchooltoolFileChecksum $localPath
        Copy-SchooltoolRemoteFile -Target $Target -LocalPath $localPath -RemotePath $remotePath
        $uploaded = $true
        Invoke-SchooltoolRemote -Target $Target -Command "echo '$launcherHash  $remotePath' | sha256sum -c -; SCHOOLTOOL_DEPLOY_PROJECT_DIRECTORY='$($Target.Path)' SCHOOLTOOL_EXPECTED_MAIN_COMMIT='$Commit' SCHOOLTOOL_EXPECTED_SOURCE_COMMIT='$SourceCommit' SCHOOLTOOL_EXPECTED_FRONTEND_SHA256='$ArchiveHash' SCHOOLTOOL_EXPECTED_SOURCE_MANIFEST_BLOB='$ManifestBlob' SCHOOLTOOL_PUBLICATION_POLICY='background-ci-v1' bash '$remotePath'"
    }
    finally {
        if (Test-Path -LiteralPath $localPath) { [System.IO.File]::Delete($localPath) }
        if ($uploaded) {
            try { Invoke-SchooltoolRemote -Target $Target -Command "rm -f -- '$remotePath'" }
            catch { Write-Warning "Deployment launcher cleanup failed: $remotePath" }
        }
    }
}

function Invoke-SchooltoolReleaseSmoke {
    param([string]$Commit)
    $timer = [Diagnostics.Stopwatch]::StartNew()
    $output = Join-Path ([IO.Path]::GetTempPath()) ('schooltool-smoke-' + [guid]::NewGuid().ToString('N'))
    try {
        $process = Start-Process -FilePath (Get-Command php -CommandType Application).Source -ArgumentList @(
            ('"' + (Join-Path $PSScriptRoot 'release-policy.php') + '"'), 'smoke', '--base', $Commit, '--head', $Commit
        ) -NoNewWindow -PassThru -RedirectStandardOutput $output -RedirectStandardError "$output.err"
        $null = $process.Handle
        if (-not $process.WaitForExit(60000)) { $process.Kill(); $process.WaitForExit(); throw 'Release smoke check exceeded 60 seconds. Nothing was deployed.' }
        $process.WaitForExit()
        $process.Refresh()
        if ($process.ExitCode -ne 0) { throw "Release smoke check failed: $([IO.File]::ReadAllText($output)) $([IO.File]::ReadAllText("$output.err"))" }
        $smoke = [IO.File]::ReadAllText($output) | ConvertFrom-Json -ErrorAction Stop
        if ($smoke.commit -cne $Commit -or $smoke.php_files -lt 1 -or $smoke.runtime -cne 'bootstrap-and-health') { throw 'Release smoke result does not match the exact candidate.' }
        Write-Host "Smoke passed: isolated application bootstrap and /up, $($smoke.php_files) PHP files and frontend references ($([math]::Round($timer.Elapsed.TotalSeconds, 1)) seconds). SQLite memory only." -ForegroundColor Green
    }
    finally {
        if ($process) { $process.Dispose() }
        foreach ($path in @($output, "$output.err")) { if (Test-Path -LiteralPath $path) { [IO.File]::Delete($path) } }
    }
}

function gitdeploy {
    Assert-SchooltoolRepository
    Assert-SchooltoolClean
    $originalBranch = Invoke-SchooltoolGit branch --show-current
    $originalHead = Invoke-SchooltoolGit rev-parse HEAD
    $target = Get-SchooltoolDeploymentTarget 'MAIN'
    Update-SchooltoolRemote
    $mainCommit = Invoke-SchooltoolGit rev-parse refs/remotes/origin/main
    if ($mainCommit -cnotmatch '^[a-f0-9]{40,64}$') { throw 'No verified main commit is available.' }
    $sourceCommit = (Invoke-SchooltoolGit --no-replace-objects show "${mainCommit}:deployment/source-commit").Trim()
    $archiveHash = ((Invoke-SchooltoolGit --no-replace-objects show "${mainCommit}:deployment/frontend-build.sha256") -split '\s+')[0]
    $manifestBlob = Invoke-SchooltoolGit --no-replace-objects rev-parse "${mainCommit}:deployment/source-manifest.sha256"
    if ($sourceCommit -cnotmatch '^[a-f0-9]{40,64}$' -or $archiveHash -cnotmatch '^[a-f0-9]{64}$' -or $manifestBlob -cnotmatch '^(?:[a-f0-9]{40}|[a-f0-9]{64})$') {
        throw 'main does not contain a valid release artifact. Publish it with gitsave or gitrelease first.'
    }
    Assert-SchooltoolReleasePackage -Commit $mainCommit -SourceCommit $sourceCommit
    Invoke-SchooltoolReleaseSmoke -Commit $mainCommit
    Invoke-SchooltoolRemote -Target $target -Command 'test -f scripts/pdeploy_cloudways.sh; test -d storage/framework; command -v php >/dev/null; command -v bash >/dev/null; command -v sha256sum >/dev/null'
    Write-Host "Live target: $($target.Ssh) $($target.Path)" -ForegroundColor Yellow
    Write-Host "GitHub main: $mainCommit. The application update includes its planned live database migrations." -ForegroundColor Yellow
    Write-Host 'Package integrity verified. GitHub tests run in the background; deployment does not wait for their result.' -ForegroundColor Cyan
    Assert-SchooltoolCiMain -Commit $mainCommit
    if ((Read-Host 'Deploy main to the live application? Type LIVE') -cne 'LIVE') { throw 'Live deployment cancelled.' }
    Update-SchooltoolRemote
    if ((Invoke-SchooltoolGit rev-parse refs/remotes/origin/main) -ne $mainCommit) { throw 'main changed during confirmation. Review the new version and rerun gitdeploy.' }
    Assert-SchooltoolRepository
    Assert-SchooltoolClean
    if ((Invoke-SchooltoolGit branch --show-current) -cne $originalBranch -or (Invoke-SchooltoolGit rev-parse HEAD) -cne $originalHead) { throw 'The local checkout changed during confirmation. Rerun gitdeploy.' }
    Send-SchooltoolLiveRelease -Target $target -Commit $mainCommit -SourceCommit $sourceCommit -ArchiveHash $archiveHash -ManifestBlob $manifestBlob
    Write-Host "Live deployment completed for the confirmed main $mainCommit." -ForegroundColor Green
}
