. (Join-Path $PSScriptRoot 'git_ssh_helpers.ps1')
. (Join-Path $PSScriptRoot 'git_preview_receipt.ps1')

$script:SchooltoolPreviewDeploymentChecksum = $null
$previewDeploymentScript = Join-Path $PSScriptRoot 'deploy_preview_cloudways.sh'
if (Test-Path -LiteralPath $previewDeploymentScript -PathType Leaf) {
    $previewDeploymentContents = Get-Content -LiteralPath $previewDeploymentScript -Raw -Encoding UTF8
    $planGuard = '(cd "$target_directory" && php artisan preview:snapshot assert-plan --feature="$feature_id" --state-token="$expected_state_token" --no-interaction)'
    if ($previewDeploymentContents.Contains($planGuard) -and $previewDeploymentContents.Contains('if [[ ! "$expected_state_token" =~ ^[a-f0-9]{64}$ ]]; then')) {
        $script:SchooltoolPreviewDeploymentChecksum = Get-SchooltoolFileChecksum $previewDeploymentScript
    }
}

function Get-SchooltoolPreviewTarget {
    Get-SchooltoolDeploymentTarget 'PREVIEW'
}

function Assert-SchooltoolPreviewDeploymentProtocol {
    param([object]$Candidate)
    $candidateScript = Join-Path $Candidate.Path 'scripts/deploy_preview_cloudways.sh'
    if (-not $script:SchooltoolPreviewDeploymentChecksum -or -not (Test-Path -LiteralPath $candidateScript -PathType Leaf) -or
        (Get-SchooltoolFileChecksum $candidateScript) -cne $script:SchooltoolPreviewDeploymentChecksum) {
        throw 'The checked candidate uses an older or different preview deployment protocol. Its receipt is preserved. Incorporate the current workflow and prepare a new candidate with current preflight checks before deployment.'
    }
}

function Send-SchooltoolPreview {
    param(
        [string]$Archive, [string]$Checksum, [string]$Id, [string]$SourceBranch,
        [string]$FeatureId, [object]$Target, [object]$SnapshotStatus, [switch]$RefreshData
    )
    if ($Id -cnotmatch '^[a-f0-9]{32}$' -or $FeatureId -cnotmatch '^[a-f0-9]{32}$' -or $Checksum -cnotmatch '^[a-f0-9]{64}$' -or $SourceBranch -cnotmatch '^feature/[a-z0-9]+(?:-[a-z0-9]+)*$' -or
        $SnapshotStatus.state_token -cnotmatch '^[a-f0-9]{64}$') {
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
    $bootstrap = "cd '$incoming'; echo '$Checksum  release.tar.gz' | sha256sum -c -; mkdir candidate; tar -xzf release.tar.gz -C candidate; bash candidate/scripts/deploy_preview_cloudways.sh '$($Target.Path)' '$SourceBranch' '$Checksum' '$FeatureId' '$snapshotPath' '$snapshotHash' '$($Target.User)' '$($SnapshotStatus.state_token)'"
    Write-Host 'Deploying the verified candidate into the isolated preview application...' -ForegroundColor Cyan
    Invoke-SchooltoolRemote -Target $Target -Command $bootstrap
    try { Invoke-SchooltoolRemote -Target $Target -Command "test -d '$incoming'; test ! -L '$incoming'; rm -rf -- '$incoming'" }
    catch { Write-Warning 'Preview deployment completed, but its private transfer directory could not be cleaned up. Remove the reported transfer directory after checking the server.' }
}

function gitpreview {
    param([ValidateSet('deploy', 'prepare', 'resume')][string]$Mode = 'deploy', [string]$BundleId, [switch]$RefreshData, [Alias('Feature')][string]$FeatureName)
    Assert-SchooltoolRepository
    Assert-SchooltoolClean
    $root = (Get-Location).Path
    $originalBranch = Assert-SchooltoolFeature
    $prepareOnly = $Mode -eq 'prepare'
    if (($Mode -eq 'resume') -ne (-not [string]::IsNullOrEmpty($BundleId))) { throw 'Usage: gitpreview [deploy|prepare] or gitpreview resume BUNDLE_ID [-RefreshData]' }
    $receipt = if ($Mode -eq 'resume') { Read-SchooltoolPreviewReceipt $BundleId } else { $null }
    if ($receipt) { Assert-SchooltoolPreviewReceipt -Receipt $receipt -Root $root }
    if ($prepareOnly -and $RefreshData) { throw 'RefreshData requires an online preview deployment.' }
    Update-SchooltoolRemote
    $features = @(Invoke-SchooltoolGit for-each-ref '--format=%(refname:strip=3)' refs/remotes/origin/feature/)
    if ($FeatureName -and (Get-SchooltoolFeatureBranch $FeatureName) -cne $originalBranch) {
        throw 'The selected preview feature differs from the checkout. Use gitwork NAME first, then gitpreview -Feature NAME.'
    }
    if (-not $FeatureName -and $features.Count -gt 1) { throw 'Choose the shared preview explicitly: gitpreview -Feature NAME (after gitwork NAME).' }
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
        if ($snapshotStatus.public_key -cnotmatch '^[a-f0-9]{64}$' -or $snapshotStatus.state_token -cnotmatch '^[a-f0-9]{64}$' -or $snapshotStatus.needs_snapshot -isnot [bool] -or
            'feature_id' -cnotin @($snapshotStatus.PSObject.Properties.Name) -or
            ($null -ne $snapshotStatus.feature_id -and $snapshotStatus.feature_id -cnotmatch '^[a-f0-9]{32}$')) {
            throw 'The preview did not return a safe snapshot plan.'
        }
        if ($RefreshData -or $snapshotStatus.needs_snapshot) { Get-SchooltoolDeploymentTarget 'MAIN' | Out-Null }
    }
    $id = if ($receipt) { $receipt.Id } else { [guid]::NewGuid().ToString('N') }
    $bundleDirectory = Get-SchooltoolPreviewDirectory
    [System.IO.Directory]::CreateDirectory($bundleDirectory) | Out-Null
    $candidate = if ($receipt) { $receipt.Candidate } else { New-SchooltoolCandidateWorktree -Kind 'preview' -SourceCommit $featureCommit }
    $newCandidate = -not $receipt
    $published = $false
    $operation = $null
    $candidateEnvironment = $null
    Push-Location -LiteralPath $candidate.Path
    try {
        if (-not $receipt) {
            Invoke-SchooltoolGit merge --no-edit $mainCommit | Out-Host
            Save-SchooltoolCandidate $candidate
            $sourceCommit = Invoke-SchooltoolGit rev-parse HEAD
            $candidateEnvironment = Enter-SchooltoolCandidateEnvironment -Candidate $candidate
            Invoke-SchooltoolCommand 'Preparing preview dependencies...' { php scripts/update.php --target=local --prepare }
            Invoke-SchooltoolCommand 'Formatting preview PHP files...' { php vendor/bin/pint --dirty --format agent }
            Invoke-SchooltoolCommand 'Checking preview source encoding...' { php scripts/check-encoding.php }
            Assert-SchooltoolClean
            $checkedTree = Get-SchooltoolSourceTree
            Invoke-SchooltoolReleaseChecks
            Assert-SchooltoolClean
            Assert-SchooltoolCheckedSource $checkedTree
            Invoke-SchooltoolCommand 'Creating the preview artifact...' { php scripts/frontend-release.php create $sourceCommit }
            Invoke-SchooltoolCommand 'Verifying the preview artifact...' { php scripts/frontend-release.php verify $sourceCommit }
            Invoke-SchooltoolGit add -f deployment/frontend-build.sha256 deployment/frontend-build.tar.gz deployment/source-commit deployment/source-manifest.sha256
            Invoke-SchooltoolGit commit --allow-empty -m "Build preview for $sourceCommit" | Out-Host
            Save-SchooltoolCandidate $candidate
            Assert-SchooltoolClean
            Assert-SchooltoolCheckedSource $checkedTree
            Assert-SchooltoolFeatureSnapshot -Feature $feature -FeatureCommit $featureCommit -MainCommit $mainCommit
            $archive = Join-Path $bundleDirectory "$id.tar.gz"
            Invoke-SchooltoolGit archive --format=tar.gz "--output=$archive" HEAD
            $checksum = Get-SchooltoolFileChecksum $archive
            $completedEnvironment = $candidateEnvironment
            $candidateEnvironment = $null
            Restore-SchooltoolCandidateEnvironment $completedEnvironment
            $receipt = [pscustomobject]@{
                Format = 'schooltool-preview-v3'; Id = $id; Checks = 'preflight-success'; EvidenceKind = 'inline-build-and-integrity'; CheckedAt = [DateTime]::UtcNow.ToString('o')
                Root = $root; Directory = $bundleDirectory; Origin = (Get-SchooltoolPreviewOrigin); PushOrigin = (Get-SchooltoolPreviewOrigin -Push)
                Feature = $feature; FeatureCommit = $featureCommit; MainCommit = $mainCommit; Candidate = $candidate
                SourceCommit = $sourceCommit; ArtifactCommit = (Invoke-SchooltoolGit rev-parse HEAD); SourceTree = $checkedTree; Checksum = $checksum
            }
            Write-SchooltoolPreviewReceipt $receipt
        }
        else {
            $sourceCommit = $receipt.SourceCommit
            $archive = Join-Path $bundleDirectory "$id.tar.gz"
            $checksum = $receipt.Checksum
        }
        $resumeCommand = "gitpreview resume $id -Feature $originalBranch" + $(if ($RefreshData) { ' -RefreshData' } else { '' })
        Write-Host "Preview source: $originalBranch ($sourceCommit)" -ForegroundColor Cyan
        Write-Host "Prepared bundle: $archive" -ForegroundColor Cyan
        Write-Host "Continue this checked candidate: $resumeCommand" -ForegroundColor Cyan
        if ($prepareOnly) {
            Write-Host 'Candidate prepared and checked. Feature, main and servers are unchanged.' -ForegroundColor Green
            return
        }
        Assert-SchooltoolPreviewDeploymentProtocol $candidate
        Write-Host "Preview target: $($target.Ssh) $($target.Path)" -ForegroundColor Cyan
        if ($RefreshData -or $snapshotStatus.needs_snapshot) {
            Write-Host 'Preview data will be backed up and replaced with a new isolated live snapshot. Current preview test entries will be removed from the active preview.' -ForegroundColor Yellow
        }
        else { Write-Host 'Existing preview test data will be retained; pending feature migrations will run only on its isolated database.' -ForegroundColor Cyan }
        $replacesExistingData = $snapshotStatus.needs_snapshot -and $null -ne $snapshotStatus.feature_id
        if ($replacesExistingData) {
            Write-Host "The shared preview currently holds lifecycle $($snapshotStatus.feature_id). Its test data will be replaced; returning to this feature later also starts with fresh live data." -ForegroundColor Yellow
        }
        if (($RefreshData -or $replacesExistingData) -and (Read-Host 'Replace the current preview test data? Type REFRESH') -cne 'REFRESH') {
            Write-Host "Data refresh cancelled. Nothing published. Continue with: $resumeCommand" -ForegroundColor Yellow
            return
        }
        if ((Read-Host 'Publish and deploy this verified preview? Type PREVIEW') -cne 'PREVIEW') {
            Write-Host "Preview cancelled. Nothing published. Continue with: $resumeCommand" -ForegroundColor Yellow
            return
        }
        $operation = Lock-SchooltoolFeatureOperation $feature
        Assert-SchooltoolPreviewReceipt -Receipt (Read-SchooltoolPreviewReceipt $id) -Root $root
        Assert-SchooltoolPreviewDeploymentProtocol $candidate
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
        $currentStatus = Invoke-SchooltoolRemoteJson -Target $target -Command "php artisan preview:snapshot status --feature='$($feature.Id)' --no-interaction"
        if ($currentStatus.state_token -cne $snapshotStatus.state_token) { throw 'The shared preview changed during preparation. Nothing was published. Review the new data plan before retrying.' }
        Invoke-SchooltoolGit merge-base --is-ancestor $featureCommit $sourceCommit | Out-Null
        Start-SchooltoolPreviewPublication $id
        Invoke-SchooltoolGit push --atomic "--force-with-lease=refs/heads/${originalBranch}:$featureCommit" "--force-with-lease=refs/heads/preview/${id}:" origin "${sourceCommit}:refs/heads/$originalBranch" "$($receipt.ArtifactCommit):refs/heads/preview/$id" | Out-Host
        $published = $true
        Write-Host "GitHub tests the exact preview artifact $($receipt.ArtifactCommit) in the background; deployment does not wait for the result." -ForegroundColor Cyan
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
        Unlock-SchooltoolFeatureOperation $operation
        try {
            try { if ($newCandidate) { Save-SchooltoolCandidate $candidate } }
            finally { if ($candidateEnvironment) { Restore-SchooltoolCandidateEnvironment $candidateEnvironment } }
        }
        finally {
            Pop-Location
            Write-Host "Candidate retained at $($candidate.Path)." -ForegroundColor DarkGray
        }
    }
}
