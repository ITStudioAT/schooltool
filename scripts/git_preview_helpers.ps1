function Get-SchooltoolPreviewExecutable {
    param([ValidateSet('ssh', 'scp')][string]$Name)

    $command = Get-Command $Name -CommandType Application -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($command) { return $command.Source }

    $bundledExecutable = Join-Path $env:ProgramFiles "Git/usr/bin/$Name.exe"
    if (Test-Path -LiteralPath $bundledExecutable -PathType Leaf) { return $bundledExecutable }

    throw "$Name was not found. Install Windows OpenSSH Client or Git for Windows."
}

function Get-SchooltoolPreviewTarget {
    $target = if ($env:SCHOOLTOOL_PREVIEW_SSH) { $env:SCHOOLTOOL_PREVIEW_SSH } else { 'schooltool-feature@165.227.156.99' }
    $path = $env:SCHOOLTOOL_PREVIEW_PATH
    if ($target -notmatch '^schooltool-feature@[a-zA-Z0-9.-]+$') {
        throw 'SCHOOLTOOL_PREVIEW_SSH must use the dedicated schooltool-feature account.'
    }
    if (-not $path -or $path -notmatch '^/[a-zA-Z0-9_./-]+/public_html$' -or $path -match '/\.\.?/') {
        throw 'Set SCHOOLTOOL_PREVIEW_PATH to the verified absolute public_html directory of Schooltool Feature. No server was changed.'
    }
    @{ Ssh = $target; Path = $path }
}

function Send-SchooltoolPreview {
    param([string]$Archive, [string]$Checksum, [string]$Id, [string]$SourceBranch)
    if ($Id -cnotmatch '^[a-f0-9]{32}$' -or $Checksum -cnotmatch '^[a-f0-9]{64}$' -or $SourceBranch -cnotmatch '^(main|feature/[a-z0-9]+(?:-[a-z0-9]+)*)$') {
        throw 'Invalid preview bundle identity.'
    }
    $target = Get-SchooltoolPreviewTarget
    $sshExecutable = Get-SchooltoolPreviewExecutable 'ssh'
    $scpExecutable = Get-SchooltoolPreviewExecutable 'scp'
    $incoming = "/tmp/schooltool-preview-$Id"
    Invoke-SchooltoolCommand 'Preparing the dedicated preview upload (SSH may ask for your password)...' {
        & $sshExecutable -t -- $target.Ssh "test `"`$(id -un)`" = schooltool-feature && umask 077 && mkdir '$incoming'"
    }
    Invoke-SchooltoolCommand 'Uploading the verified preview bundle...' {
        & $scpExecutable -- $Archive "$($target.Ssh):$incoming/release.tar.gz"
    }
    $bootstrap = "set -eu; test `"`$(id -un)`" = schooltool-feature; cd '$incoming'; echo '$Checksum  release.tar.gz' | sha256sum -c -; mkdir candidate; tar -xzf release.tar.gz -C candidate; bash candidate/scripts/deploy_preview_cloudways.sh '$($target.Path)' '$SourceBranch' '$Checksum'; cd /tmp; test -d '$incoming' && test ! -L '$incoming' && rm -rf -- '$incoming'"
    Invoke-SchooltoolCommand 'Deploying only Schooltool Feature (SSH may ask for your password)...' {
        & $sshExecutable -t -- $target.Ssh $bootstrap
    }
}

function gitpreview {
    param([ValidateSet('deploy', 'prepare', 'baseline', 'baseline-prepare')][string]$Mode = 'deploy')
    $prepareOnly = $Mode -in @('prepare', 'baseline-prepare')
    $baseline = $Mode -in @('baseline', 'baseline-prepare')
    Assert-SchooltoolRepository
    Assert-SchooltoolClean
    $originalBranch = Invoke-SchooltoolGit branch --show-current
    if ($baseline) {
        if ($originalBranch -ne 'main') { throw 'Baseline preview requires main.' }
    }
    else {
        Assert-SchooltoolFeature | Out-Null
        Get-SchooltoolFeatureBranch $originalBranch | Out-Null
    }
    if (-not $prepareOnly) { Get-SchooltoolPreviewTarget | Out-Null }
    Update-SchooltoolRemote
    Assert-SchooltoolSaved
    $sourceCommit = Invoke-SchooltoolGit rev-parse HEAD
    $remoteCommit = Invoke-SchooltoolGit rev-parse "refs/remotes/origin/$originalBranch"
    if ($sourceCommit -ne $remoteCommit) { throw 'Download and test the latest saved branch before creating a preview.' }
    $mainCommit = Invoke-SchooltoolGit rev-parse refs/remotes/origin/main
    & git merge-base --is-ancestor $mainCommit HEAD
    if ($LASTEXITCODE -ne 0) { throw 'Incorporate current main with gitupdate, test and gitsave before creating a preview.' }
    $schemaChanges = Invoke-SchooltoolGit diff --name-only "$mainCommit...HEAD" '--' database/migrations database/schema
    if ($schemaChanges) { throw 'Preview shares the live schema. Release compatible schema changes on main first; preview never runs migrations.' }
    $id = [guid]::NewGuid().ToString('N')
    $candidate = "codex/preview-$id"
    $previewRef = "preview/$id"
    Invoke-SchooltoolGit switch --no-track -c $candidate $sourceCommit
    try {
        Invoke-SchooltoolCommand 'Preparing preview dependencies...' { php scripts/update.php --target=local --prepare }
        Invoke-SchooltoolCommand 'Formatting preview PHP files...' { php vendor/bin/pint --dirty --format agent }
        Invoke-SchooltoolCommand 'Checking preview source encoding...' { php scripts/check-encoding.php }
        Assert-SchooltoolClean
        Invoke-SchooltoolReleaseChecks -Full
        Assert-SchooltoolClean
        Invoke-SchooltoolCommand 'Creating the preview artifact...' { php scripts/frontend-release.php create $sourceCommit }
        Invoke-SchooltoolCommand 'Verifying the preview artifact...' { php scripts/frontend-release.php verify $sourceCommit }
        Invoke-SchooltoolGit add -f deployment/frontend-build.sha256 deployment/frontend-build.tar.gz deployment/source-commit deployment/source-manifest.sha256
        Invoke-SchooltoolGit commit -m "Build preview for $sourceCommit"
        Assert-SchooltoolClean
        Update-SchooltoolRemote
        if ((Invoke-SchooltoolGit rev-parse refs/remotes/origin/main) -ne $mainCommit) {
            throw 'main changed during checks. Incorporate the new baseline and create the preview again.'
        }
        $bundleDirectory = Invoke-SchooltoolGit rev-parse --git-path schooltool-preview
        [System.IO.Directory]::CreateDirectory([System.IO.Path]::GetFullPath($bundleDirectory)) | Out-Null
        $archive = [System.IO.Path]::GetFullPath((Join-Path $bundleDirectory "$id.tar.gz"))
        Invoke-SchooltoolGit archive --format=tar.gz "--output=$archive" HEAD
        $archiveStream = [System.IO.File]::OpenRead($archive)
        $hasher = [System.Security.Cryptography.SHA256]::Create()
        try {
            $checksum = [BitConverter]::ToString($hasher.ComputeHash($archiveStream)).Replace('-', '').ToLowerInvariant()
        }
        finally {
            $archiveStream.Dispose()
            $hasher.Dispose()
        }
        Write-Host "Preview source: $originalBranch ($sourceCommit)" -ForegroundColor Cyan
        Write-Host "Bundle: $archive" -ForegroundColor Cyan
        Write-Host "SHA256: $checksum" -ForegroundColor Cyan
        if ($prepareOnly) {
            Write-Host 'Bundle prepared. Nothing was uploaded or deployed; main, tags and feature are unchanged.' -ForegroundColor Green
            return
        }
        Write-Host 'Preview uses the configured database. Changes made in its UI may change live data.' -ForegroundColor Yellow
        if ((Read-Host 'Publish this candidate to Schooltool Feature? Type PREVIEW') -cne 'PREVIEW') {
            throw 'Preview cancelled. Nothing was uploaded or deployed.'
        }
        Invoke-SchooltoolGit push origin "HEAD:refs/heads/$previewRef"
        Send-SchooltoolPreview -Archive $archive -Checksum $checksum -Id $id -SourceBranch $originalBranch
        Write-Host 'Preview deployed. Production main and production deployment are unchanged.' -ForegroundColor Green
    }
    finally {
        if (-not (Invoke-SchooltoolGit status --porcelain --untracked-files=all)) {
            Invoke-SchooltoolGit switch $originalBranch
        }
        Write-Host "Candidate preserved: $candidate" -ForegroundColor DarkGray
    }
}
