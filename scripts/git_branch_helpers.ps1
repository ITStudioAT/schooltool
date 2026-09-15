function Invoke-SchooltoolGit {
    $result = & git @args
    if ($LASTEXITCODE -ne 0) {
        throw "Git failed: git $($args -join ' '). No changes were discarded."
    }
    $result
}

function Assert-SchooltoolRepository {
    $root = Invoke-SchooltoolGit rev-parse --show-toplevel
    if ((Get-Location).Path.TrimEnd('\', '/') -ne $root.TrimEnd('\', '/').Replace('/', '\')) {
        throw 'Run this command from the project root.'
    }
    foreach ($state in @('MERGE_HEAD', 'CHERRY_PICK_HEAD', 'REVERT_HEAD', 'rebase-merge', 'rebase-apply', 'BISECT_LOG')) {
        $path = Invoke-SchooltoolGit rev-parse --git-path $state
        if (Test-Path -LiteralPath $path) {
            throw 'A Git operation is unfinished. Resolve or abort it before continuing.'
        }
    }
    $branch = Invoke-SchooltoolGit branch --show-current
    if (-not $branch) {
        throw 'Detached HEAD: switch to a named branch first.'
    }
}

function Assert-SchooltoolClean {
    if (Invoke-SchooltoolGit status --porcelain --untracked-files=all) {
        throw 'Unsaved changes exist. Use gitsave on a feature branch or gitpush on main first.'
    }
}

function Get-SchooltoolFeatureBranch {
    param([Parameter(Mandatory = $true)][string]$Name)
    $shortName = $Name -replace '^feature/', ''
    if ($shortName -cnotmatch '^[a-z0-9]+(?:-[a-z0-9]+)*$') {
        throw 'Use a feature name such as neue-funktion (lowercase letters, numbers and hyphens).'
    }
    "feature/$shortName"
}

function Assert-SchooltoolFeature {
    $branch = Invoke-SchooltoolGit branch --show-current
    if ($branch -notlike 'feature/*') {
        throw 'This command requires a feature branch. Use gitwork NAME first.'
    }
    $branch
}

function Test-SchooltoolRef {
    param([string]$Ref)
    & git show-ref --verify --quiet $Ref
    if ($LASTEXITCODE -gt 1) {
        throw "Could not inspect $Ref."
    }
    $LASTEXITCODE -eq 0
}

function Update-SchooltoolRemote {
    Invoke-SchooltoolGit fetch origin '+refs/heads/*:refs/remotes/origin/*' --prune
}

function Assert-SchooltoolSaved {
    $branch = Invoke-SchooltoolGit branch --show-current
    if (-not (Test-SchooltoolRef "refs/remotes/origin/$branch")) {
        throw "Branch $branch is not on origin. Push it before switching devices or branches."
    }
    & git merge-base --is-ancestor HEAD "refs/remotes/origin/$branch"
    if ($LASTEXITCODE -ne 0) {
        throw "Branch $branch has local commits not saved on origin. Publish or synchronize them first."
    }
}

function Invoke-SchooltoolLocalPreparation {
    Invoke-SchooltoolCommand 'Preparing dependencies for the selected branch...' {
        php scripts/update.php --target=local --prepare
    }
    Invoke-SchooltoolCommand 'Clearing local configuration cache...' {
        php artisan config:clear --no-interaction
    }
    Invoke-SchooltoolCommand 'Clearing compiled views...' {
        php artisan view:clear --no-interaction
    }
    Invoke-SchooltoolCommand 'Building the selected branch...' { npm run build }
    Write-Host 'Local files are ready. No migrations or seeders were run. Restart running development servers/workers.' -ForegroundColor Green
    Write-Host 'Database schema changes require a separate feature database; Git does not switch databases.' -ForegroundColor Yellow
}

function Switch-SchooltoolBranch {
    param([string]$Branch)
    Assert-SchooltoolRepository
    Assert-SchooltoolClean
    Update-SchooltoolRemote
    Assert-SchooltoolSaved
    if (-not (Test-SchooltoolRef "refs/remotes/origin/$Branch")) {
        throw "Branch $Branch does not exist on origin."
    }
    if (Test-SchooltoolRef "refs/heads/$Branch") {
        & git merge-base --is-ancestor "refs/heads/$Branch" "refs/remotes/origin/$Branch"
        if ($LASTEXITCODE -ne 0) {
            throw "Local $Branch has unpublished or divergent commits. Synchronize it before switching."
        }
        Invoke-SchooltoolGit switch $Branch
    }
    else {
        Invoke-SchooltoolGit switch --track -c $Branch "refs/remotes/origin/$Branch"
    }
    Invoke-SchooltoolGit merge --ff-only "refs/remotes/origin/$Branch"
    Invoke-SchooltoolLocalPreparation
    Write-SchooltoolCompletionTime
}

function gitstart {
    param([Parameter(Mandatory = $true)][string]$Name)
    $branch = Get-SchooltoolFeatureBranch $Name
    Assert-SchooltoolRepository
    Assert-SchooltoolClean
    Update-SchooltoolRemote
    Assert-SchooltoolSaved
    if ((Test-SchooltoolRef "refs/heads/$branch") -or (Test-SchooltoolRef "refs/remotes/origin/$branch")) {
        throw "$branch already exists. Use gitwork instead."
    }
    Invoke-SchooltoolGit switch --no-track -c $branch refs/remotes/origin/main
    Invoke-SchooltoolGit push --set-upstream origin "HEAD:refs/heads/$branch"
    Invoke-SchooltoolLocalPreparation
    Write-SchooltoolCompletionTime
}

function gitwork {
    param([Parameter(Mandatory = $true)][string]$Name)
    Switch-SchooltoolBranch (Get-SchooltoolFeatureBranch $Name)
}

function gitmain {
    [CmdletBinding()]
    param()
    Switch-SchooltoolBranch main
}

function gitsave {
    param([Parameter(Mandatory = $true)][ValidateNotNullOrEmpty()][string]$Message)
    Assert-SchooltoolRepository
    $branch = Assert-SchooltoolFeature
    Update-SchooltoolRemote
    if (Invoke-SchooltoolGit status --porcelain --untracked-files=all) {
        Invoke-SchooltoolCommand 'Checking UTF-8 source files...' { php scripts/check-encoding.php }
        Invoke-SchooltoolGit add -A
        Invoke-SchooltoolGit commit -m $Message
    }
    Assert-SchooltoolClean
    if (Test-SchooltoolRef "refs/remotes/origin/$branch") {
        Invoke-SchooltoolGit merge --no-edit "refs/remotes/origin/$branch"
    }
    Invoke-SchooltoolGit push --set-upstream origin "HEAD:refs/heads/$branch"
    Write-Host 'Feature saved on origin. main and Cloudways are unchanged.' -ForegroundColor Green
    Write-SchooltoolCompletionTime
}

function gitupdate {
    [CmdletBinding()]
    param()
    Assert-SchooltoolRepository
    $branch = Assert-SchooltoolFeature
    Assert-SchooltoolClean
    Update-SchooltoolRemote
    Assert-SchooltoolSaved
    Invoke-SchooltoolGit merge --ff-only "refs/remotes/origin/$branch"
    Invoke-SchooltoolGit merge --no-edit refs/remotes/origin/main
    Invoke-SchooltoolLocalPreparation
    Write-Host 'main was incorporated. Test the feature, then use gitsave to share this state.' -ForegroundColor Green
    Write-SchooltoolCompletionTime
}

function gitcheck {
    [CmdletBinding()]
    param()
    Assert-SchooltoolRepository
    Update-SchooltoolRemote
    $branch = Invoke-SchooltoolGit branch --show-current
    Write-Host "Branch: $branch" -ForegroundColor Cyan
    Invoke-SchooltoolGit status --short
    if (Test-SchooltoolRef "refs/remotes/origin/$branch") {
        $counts = (Invoke-SchooltoolGit rev-list --left-right --count "HEAD...refs/remotes/origin/$branch") -split '\s+'
        Write-Host "Only local / not yet available on other devices: $($counts[0]) commits"
        Write-Host "Only on origin / not yet downloaded: $($counts[1]) commits"
    }
    else {
        Write-Host 'This branch is not saved on origin.' -ForegroundColor Yellow
    }
    if ($branch -like 'feature/*') {
        $missing = Invoke-SchooltoolGit rev-list --count HEAD..refs/remotes/origin/main
        Write-Host "Commits missing from main: $missing (gitupdate incorporates them)"
        $schemaChanges = Invoke-SchooltoolGit diff --name-only refs/remotes/origin/main...HEAD -- database/migrations config/database.php
        if ($schemaChanges) {
            Write-Host 'Database changes: use a separate local feature database before running migrations.' -ForegroundColor Yellow
            $schemaChanges
        }
    }
    Write-Host 'Cloudways only deploys main. gitcheck does not inspect the live server.' -ForegroundColor Cyan
}

function Assert-SchooltoolVersion {
    param([string]$Version)
    if (-not $Version) { return }
    if ($Version -notmatch '^\d+\.\d+\.\d+$') {
        throw 'Use a version such as 3.48.0, without a v prefix.'
    }
    if (Test-SchooltoolRef "refs/tags/v$Version") {
        throw "Tag v$Version already exists locally. Use a new version."
    }
    $remoteTag = Invoke-SchooltoolGit ls-remote --tags origin "refs/tags/v$Version"
    if ($remoteTag) {
        throw "Tag v$Version already exists on origin. Use a new version."
    }
}

function gitrelease {
    param(
        [Parameter(Mandatory = $true)][ValidateNotNullOrEmpty()][string]$Message,
        [string]$Version
    )
    Assert-SchooltoolRepository
    $feature = Assert-SchooltoolFeature
    Assert-SchooltoolClean
    Update-SchooltoolRemote
    Assert-SchooltoolSaved
    $featureHead = Invoke-SchooltoolGit rev-parse HEAD
    $remoteFeature = Invoke-SchooltoolGit rev-parse "refs/remotes/origin/$feature"
    if ($featureHead -ne $remoteFeature) {
        throw 'New feature commits exist on origin. Run gitwork and test them first.'
    }
    $mainHead = Invoke-SchooltoolGit rev-parse refs/remotes/origin/main
    & git merge-base --is-ancestor $mainHead HEAD
    if ($LASTEXITCODE -ne 0) {
        throw 'main has newer changes. Run gitupdate, test and gitsave before releasing.'
    }
    if (Test-SchooltoolRef refs/heads/main) {
        & git merge-base --is-ancestor refs/heads/main $mainHead
        if ($LASTEXITCODE -ne 0) {
            throw 'Local main has unpublished commits. Publish or resolve them before releasing.'
        }
    }
    if ($mainHead -eq $featureHead) {
        throw 'The feature contains no new commits to release.'
    }
    Assert-SchooltoolVersion $Version
    $releaseBranch = 'codex/release-' + [guid]::NewGuid().ToString('N').Substring(0, 12)
    Invoke-SchooltoolGit switch --no-track -c $releaseBranch $featureHead
    try {
        Invoke-SchooltoolPublish -message $Message -version $Version -Full -ExpectedMainCommit $mainHead
        $releaseHead = Invoke-SchooltoolGit rev-parse HEAD
        Update-SchooltoolRemote
        $publishedHead = Invoke-SchooltoolGit rev-parse refs/remotes/origin/main
        & git merge-base --is-ancestor $releaseHead $publishedHead
        if ($LASTEXITCODE -ne 0) {
            throw 'The candidate was not published to origin/main.'
        }
        if (Test-SchooltoolRef refs/heads/main) {
            Invoke-SchooltoolGit switch main
        }
        else {
            Invoke-SchooltoolGit switch --track -c main refs/remotes/origin/main
        }
        Invoke-SchooltoolGit merge --ff-only $publishedHead
        Write-Host "Release published. You are on main. Feature $feature and candidate $releaseBranch are preserved." -ForegroundColor Green
        Write-Host 'Cloudways can now be updated with composer pdeploy.' -ForegroundColor Green
    }
    catch {
        Write-Host "Release stopped. Candidate preserved on $releaseBranch; original feature: $feature. Run gitcheck to inspect." -ForegroundColor Yellow
        if (-not (Invoke-SchooltoolGit status --porcelain --untracked-files=all)) {
            Invoke-SchooltoolGit switch $feature
        }
        throw
    }
}
