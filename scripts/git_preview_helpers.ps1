. (Join-Path $PSScriptRoot 'git_ssh_helpers.ps1')

function Get-SchooltoolPreviewTarget {
    Get-SchooltoolDeploymentTarget 'PREVIEW'
}

function Send-SchooltoolPreview {
    param(
        [string]$Archive, [string]$Checksum, [string]$Id, [string]$SourceBranch,
        [string]$FeatureId, [object]$Target, [object]$SnapshotStatus, [switch]$RefreshData
    )
    if ($Id -cnotmatch '^[a-f0-9]{32}$' -or $FeatureId -cnotmatch '^[a-f0-9]{32}$' -or $Checksum -cnotmatch '^[a-f0-9]{64}$' -or $SourceBranch -cnotmatch '^feature/[a-z0-9]+(?:-[a-z0-9]+)*$') {
        throw 'Invalid preview bundle identity.'
    }
    $needsSnapshot = $RefreshData -or $SnapshotStatus.needs_snapshot
    $incoming = "/tmp/schooltool-preview-$Id"
    Invoke-SchooltoolRemote -Target $Target -Command "umask 077; mkdir '$incoming'"
    Copy-SchooltoolRemoteFile -Target $Target -LocalPath $Archive -RemotePath "$incoming/release.tar.gz"
    $snapshotPath = '-'
    $snapshotHash = '-'
    if ($needsSnapshot) {
        $mainTarget = Get-SchooltoolDeploymentTarget 'MAIN'
        $snapshotId = [guid]::NewGuid().ToString('N')
        $publicKey = $SnapshotStatus.public_key
        if ($publicKey -cnotmatch '^[a-f0-9]{64}$') { throw 'The preview snapshot recipient key is invalid.' }
        Write-Host 'Creating an encrypted, read-only snapshot in the live application...' -ForegroundColor Cyan
        $snapshot = Invoke-SchooltoolRemoteJson -Target $mainTarget -Command "php artisan preview:snapshot export --feature='$FeatureId' --recipient='$publicKey' --artifact='$snapshotId' --no-interaction"
        if ($snapshot.artifact -ne $snapshotId -or $snapshot.sha256 -cnotmatch '^[a-f0-9]{64}$' -or $snapshot.path -cnotmatch "^/(?:[a-zA-Z0-9_-][a-zA-Z0-9_.-]*/)+$snapshotId\.stpreview$" -or $snapshot.path -match '/public_html(?:/|$)') {
            throw 'The live snapshot did not provide a verified private artifact identity.'
        }
        $localSnapshot = Join-Path (Split-Path -Parent $Archive) "$snapshotId.stpreview"
        Copy-SchooltoolRemoteFile -Target $mainTarget -LocalPath $localSnapshot -RemotePath $snapshot.path -Download
        if ((Get-SchooltoolFileChecksum $localSnapshot) -ne $snapshot.sha256) { throw 'Snapshot transfer checksum mismatch. The preview database was not changed.' }
        $snapshotPath = "$incoming/$snapshotId.stpreview"
        $snapshotHash = $snapshot.sha256
        Copy-SchooltoolRemoteFile -Target $Target -LocalPath $localSnapshot -RemotePath $snapshotPath
        Invoke-SchooltoolRemote -Target $Target -Command "test -f '$snapshotPath'; test ! -L '$snapshotPath'; chmod 600 '$snapshotPath'"
        try {
            Invoke-SchooltoolRemote -Target $mainTarget -Command "php artisan preview:snapshot delete --artifact='$snapshotId' --no-interaction"
            [System.IO.File]::Delete($localSnapshot)
        }
        catch { Write-Warning 'The encrypted source snapshot was retained in its private directory; review snapshot cleanup after deployment.' }
    }
    $bootstrap = "cd '$incoming'; echo '$Checksum  release.tar.gz' | sha256sum -c -; mkdir candidate; tar -xzf release.tar.gz -C candidate; bash candidate/scripts/deploy_preview_cloudways.sh '$($Target.Path)' '$SourceBranch' '$Checksum' '$FeatureId' '$snapshotPath' '$snapshotHash' '$($Target.User)'"
    Write-Host 'Deploying the verified candidate into the isolated preview application...' -ForegroundColor Cyan
    Invoke-SchooltoolRemote -Target $Target -Command $bootstrap
    try { Invoke-SchooltoolRemote -Target $Target -Command "test -d '$incoming'; test ! -L '$incoming'; rm -rf -- '$incoming'" }
    catch { Write-Warning 'Preview deployment completed, but its private transfer directory could not be cleaned up. Remove the reported transfer directory after checking the server.' }
}

function gitpreview {
    param([ValidateSet('deploy', 'prepare')][string]$Mode = 'deploy', [switch]$RefreshData)
    Assert-SchooltoolRepository
    Assert-SchooltoolClean
    $root = (Get-Location).Path
    $originalBranch = Assert-SchooltoolFeature
    $prepareOnly = $Mode -eq 'prepare'
    if ($prepareOnly -and $RefreshData) { throw 'RefreshData requires an online preview deployment.' }
    Update-SchooltoolRemote
    Assert-SchooltoolSaved
    $feature = Get-SchooltoolActiveFeature
    if ($feature.Branch -ne $originalBranch) { throw 'The current branch is not the registered active feature.' }
    $featureCommit = Invoke-SchooltoolGit rev-parse HEAD
    $mainCommit = Invoke-SchooltoolGit rev-parse refs/remotes/origin/main
    if ($featureCommit -ne (Invoke-SchooltoolGit rev-parse "refs/remotes/origin/$originalBranch")) {
        throw 'Use gitwork to obtain the latest saved feature before creating a preview.'
    }
    $target = $null
    $snapshotStatus = $null
    if (-not $prepareOnly) {
        $target = Get-SchooltoolPreviewTarget
        $snapshotStatus = Invoke-SchooltoolRemoteJson -Target $target -Command "php artisan preview:snapshot status --feature='$($feature.Id)' --no-interaction"
        if ($snapshotStatus.public_key -cnotmatch '^[a-f0-9]{64}$' -or $snapshotStatus.needs_snapshot -isnot [bool]) {
            throw 'The preview did not return a safe snapshot plan.'
        }
        if ($RefreshData -or $snapshotStatus.needs_snapshot) { Get-SchooltoolDeploymentTarget 'MAIN' | Out-Null }
    }
    $id = [guid]::NewGuid().ToString('N')
    $bundleDirectory = [System.IO.Path]::GetFullPath((Join-Path (Invoke-SchooltoolGit rev-parse --git-common-dir) 'schooltool-preview'))
    [System.IO.Directory]::CreateDirectory($bundleDirectory) | Out-Null
    $candidate = New-SchooltoolCandidateWorktree -Kind 'preview' -SourceCommit $featureCommit
    $published = $false
    $candidateEnvironment = $null
    Push-Location -LiteralPath $candidate.Path
    try {
        Invoke-SchooltoolGit merge --no-edit $mainCommit | Out-Host
        $sourceCommit = Invoke-SchooltoolGit rev-parse HEAD
        $candidateEnvironment = Enter-SchooltoolCandidateEnvironment -Candidate $candidate
        Invoke-SchooltoolCommand 'Preparing preview dependencies...' { php scripts/update.php --target=local --prepare }
        Invoke-SchooltoolCommand 'Formatting preview PHP files...' { php vendor/bin/pint --dirty --format agent }
        Invoke-SchooltoolCommand 'Checking preview source encoding...' { php scripts/check-encoding.php }
        Assert-SchooltoolClean
        $checkedTree = Get-SchooltoolSourceTree
        Invoke-SchooltoolReleaseChecks -Full
        Assert-SchooltoolClean
        Assert-SchooltoolCheckedSource $checkedTree
        Invoke-SchooltoolCommand 'Creating the preview artifact...' { php scripts/frontend-release.php create $sourceCommit }
        Invoke-SchooltoolCommand 'Verifying the preview artifact...' { php scripts/frontend-release.php verify $sourceCommit }
        Invoke-SchooltoolGit add -f deployment/frontend-build.sha256 deployment/frontend-build.tar.gz deployment/source-commit deployment/source-manifest.sha256
        Invoke-SchooltoolGit commit --allow-empty -m "Build preview for $sourceCommit" | Out-Host
        Assert-SchooltoolClean
        Assert-SchooltoolCheckedSource $checkedTree
        Assert-SchooltoolFeatureSnapshot -Feature $feature -FeatureCommit $featureCommit -MainCommit $mainCommit
        $archive = Join-Path $bundleDirectory "$id.tar.gz"
        Invoke-SchooltoolGit archive --format=tar.gz "--output=$archive" HEAD
        $checksum = Get-SchooltoolFileChecksum $archive
        $completedEnvironment = $candidateEnvironment
        $candidateEnvironment = $null
        Restore-SchooltoolCandidateEnvironment $completedEnvironment
        Write-Host "Preview source: $originalBranch ($sourceCommit)" -ForegroundColor Cyan
        Write-Host "Prepared bundle: $archive" -ForegroundColor Cyan
        if ($prepareOnly) {
            Write-Host 'Candidate prepared and checked. Feature, main and servers are unchanged.' -ForegroundColor Green
            return
        }
        Write-Host "Preview target: $($target.Ssh) $($target.Path)" -ForegroundColor Cyan
        if ($RefreshData -or $snapshotStatus.needs_snapshot) {
            Write-Host 'Preview data will be backed up and replaced with a new isolated live snapshot. Current preview test entries will be removed from the active preview.' -ForegroundColor Yellow
        }
        else { Write-Host 'Existing preview test data will be retained; pending feature migrations will run only on its isolated database.' -ForegroundColor Cyan }
        if ($RefreshData -and (Read-Host 'Replace the current preview test data? Type REFRESH') -cne 'REFRESH') { throw 'Data refresh cancelled.' }
        if ((Read-Host 'Publish and deploy this verified preview? Type PREVIEW') -cne 'PREVIEW') { throw 'Preview cancelled. The prepared candidate is retained.' }
        Push-Location -LiteralPath $root
        try {
            Assert-SchooltoolRepository
            Assert-SchooltoolClean
            if ((Invoke-SchooltoolGit branch --show-current) -ne $originalBranch -or (Invoke-SchooltoolGit rev-parse HEAD) -ne $featureCommit) {
                throw 'The original checkout changed during preview checks. No branch was overwritten.'
            }
        }
        finally { Pop-Location }
        Assert-SchooltoolFeatureSnapshot -Feature $feature -FeatureCommit $featureCommit -MainCommit $mainCommit
        Invoke-SchooltoolGit merge-base --is-ancestor $featureCommit $sourceCommit | Out-Null
        Invoke-SchooltoolGit push --atomic "--force-with-lease=refs/heads/${originalBranch}:$featureCommit" origin "${sourceCommit}:refs/heads/$originalBranch" "HEAD:refs/heads/preview/$id" | Out-Host
        $published = $true
        Push-Location -LiteralPath $root
        try { Invoke-SchooltoolGit merge --ff-only $sourceCommit | Out-Host }
        finally { Pop-Location }
        Assert-SchooltoolFeatureSnapshot -Feature $feature -FeatureCommit $sourceCommit -MainCommit $mainCommit
        Send-SchooltoolPreview -Archive $archive -Checksum $checksum -Id $id -SourceBranch $originalBranch -FeatureId $feature.Id -Target $target -SnapshotStatus $snapshotStatus -RefreshData:$RefreshData
        Write-Host 'Preview deployed. The live application was not deployed or modified.' -ForegroundColor Green
    }
    catch {
        if ($published) { Write-Warning 'The checked feature was saved on GitHub, but preview deployment did not complete. No Git history was rolled back.' }
        throw
    }
    finally {
        try {
            if ($candidateEnvironment) { Restore-SchooltoolCandidateEnvironment $candidateEnvironment }
        }
        finally {
            Pop-Location
            Write-Host "Candidate retained: $($candidate.Branch) at $($candidate.Path)" -ForegroundColor DarkGray
        }
    }
}
