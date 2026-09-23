function Invoke-SchooltoolGit {
    $result = & git @args
    if ($LASTEXITCODE -ne 0) {
        throw "Git failed: git $($args -join ' '). No changes were discarded."
    }
    $result
}

function Assert-SchooltoolRepository {
    param([object]$Candidate)
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
    if ($Candidate) { Assert-SchooltoolCandidate $Candidate }
    if (-not $branch -and -not $Candidate) {
        throw 'Detached HEAD: switch to a named branch first.'
    }
}

function Assert-SchooltoolClean {
    if (Invoke-SchooltoolGit status --porcelain --untracked-files=all) {
        throw 'Unsaved changes exist. Use gitsave before continuing.'
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
        throw 'This command requires a feature branch. Use gitwork first.'
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

function Test-SchooltoolAncestor {
    param([string]$Ancestor, [string]$Descendant)
    & git merge-base --is-ancestor $Ancestor $Descendant
    if ($LASTEXITCODE -gt 1) { throw 'Could not inspect Git ancestry.' }
    $LASTEXITCODE -eq 0
}

function Get-SchooltoolFeatureReservationRef {
    param([string]$Branch)
    $branchName = Get-SchooltoolFeatureBranch $Branch
    'refs/heads/codex/features/' + $branchName.Substring('feature/'.Length)
}

function Read-SchooltoolFeatureReservation {
    param([string]$ReservationRef)
    $trackingRef = $ReservationRef.Replace('refs/heads/', 'refs/remotes/origin/')
    $reservationCommit = Invoke-SchooltoolGit rev-parse $trackingRef
    $parents = (Invoke-SchooltoolGit rev-list --parents -n 1 $reservationCommit) -split ' '
    if ($parents.Count -ne 1) { throw 'The active feature reservation is invalid; nothing was changed.' }
    try {
        $metadata = (Invoke-SchooltoolGit show "${reservationCommit}:feature.json") | ConvertFrom-Json -ErrorAction Stop
    }
    catch { throw 'The active feature reservation cannot be read; nothing was changed.' }
    if ($metadata.branch -isnot [string] -or $metadata.id -isnot [string] -or $metadata.branch -cnotmatch '^feature/[a-z0-9]+(?:-[a-z0-9]+)*$' -or $metadata.id -cnotmatch '^[a-f0-9]{32}$') {
        throw 'The active feature reservation contains invalid metadata.'
    }
    if (-not (Test-SchooltoolRef "refs/remotes/origin/$($metadata.branch)")) {
        throw 'The active feature reservation and remote branches disagree. No branches were changed.'
    }
    [pscustomobject]@{ Branch = [string]$metadata.branch; Id = [string]$metadata.id; ReservationCommit = $reservationCommit; ReservationRef = $ReservationRef }
}

function Get-SchooltoolActiveFeature {
    param([string]$Branch, [switch]$AllowMissing)
    if (-not $Branch) { $Branch = Assert-SchooltoolFeature }
    $Branch = Get-SchooltoolFeatureBranch $Branch
    $reservationRef = Get-SchooltoolFeatureReservationRef $Branch
    $feature = $null
    if (Test-SchooltoolRef $reservationRef.Replace('refs/heads/', 'refs/remotes/origin/')) {
        $feature = Read-SchooltoolFeatureReservation $reservationRef
        if ($feature.Branch -cne $Branch) { throw 'The feature reservation belongs to another branch.' }
    }
    if (Test-SchooltoolRef refs/remotes/origin/codex/active-feature) {
        $legacy = Read-SchooltoolFeatureReservation 'refs/heads/codex/active-feature'
        if ($legacy.Branch -ceq $Branch) {
            if ($feature) { throw 'This feature has conflicting legacy and branch reservations. Resolve them without changing its lifecycle identity.' }
            $feature = $legacy
        }
    }
    if ($feature) { return $feature }
    if ($AllowMissing) { return $null }
    throw "Feature $Branch has no workflow reservation. Use gitwork NAME to register an existing branch, or gitstart NAME to begin one."
}

function New-SchooltoolFeatureReservation {
    param([string]$Branch)
    $id = [guid]::NewGuid().ToString('N')
    $metadata = @{ branch = $Branch; id = $id } | ConvertTo-Json -Compress
    $metadataPath = Join-Path ([System.IO.Path]::GetTempPath()) "schooltool-feature-$id.json"
    $indexPath = Join-Path ([System.IO.Path]::GetTempPath()) "schooltool-feature-$id.index"
    $previousIndex = $env:GIT_INDEX_FILE
    try {
        [System.IO.File]::WriteAllText($metadataPath, "$metadata`n", (New-Object System.Text.UTF8Encoding($false)))
        $blob = Invoke-SchooltoolGit hash-object -w -- $metadataPath
        $env:GIT_INDEX_FILE = $indexPath
        Invoke-SchooltoolGit read-tree --empty
        Invoke-SchooltoolGit update-index --add --cacheinfo "100644,$blob,feature.json"
        $tree = Invoke-SchooltoolGit write-tree
        $commit = Invoke-SchooltoolGit commit-tree $tree -m "Reserve $Branch ($id)"
    }
    finally {
        $env:GIT_INDEX_FILE = $previousIndex
        foreach ($path in @($metadataPath, $indexPath)) {
            if (Test-Path -LiteralPath $path) { [System.IO.File]::Delete($path) }
        }
    }
    [pscustomobject]@{ Branch = $Branch; Id = $id; ReservationCommit = $commit; ReservationRef = (Get-SchooltoolFeatureReservationRef $Branch) }
}

function Assert-SchooltoolFeatureSnapshot {
    param($Feature, [string]$FeatureCommit, [string]$MainCommit)
    Update-SchooltoolRemote
    $current = Get-SchooltoolActiveFeature -Branch $Feature.Branch
    if ($current.ReservationCommit -ne $Feature.ReservationCommit -or $current.Branch -cne $Feature.Branch -or $current.Id -cne $Feature.Id -or
        ($Feature.ReservationRef -and $current.ReservationRef -cne $Feature.ReservationRef)) {
        throw 'The active feature changed during preparation. Nothing was published.'
    }
    if ((Invoke-SchooltoolGit rev-parse "refs/remotes/origin/$($Feature.Branch)") -ne $FeatureCommit) {
        throw 'The feature changed on origin during preparation. Nothing was published.'
    }
    if ($MainCommit -and (Invoke-SchooltoolGit rev-parse refs/remotes/origin/main) -ne $MainCommit) {
        throw 'main changed during preparation. Nothing was published.'
    }
}

function New-SchooltoolCandidateWorktree {
    param([ValidateSet('preview', 'release')][string]$Kind, [string]$SourceCommit)
    $id = [guid]::NewGuid().ToString('N')
    $recoveryRef = "refs/schooltool/candidates/$Kind/$id"
    $commit = Invoke-SchooltoolGit rev-parse "$SourceCommit^{commit}"
    $repository = [System.IO.Path]::GetFullPath((Invoke-SchooltoolGit rev-parse --git-common-dir))
    $temporaryDirectory = [System.IO.Path]::GetFullPath([System.IO.Path]::GetTempPath())
    $candidatePath = Join-Path $temporaryDirectory "schooltool-worktrees/$id"
    if ($candidatePath -match '(^|[\\/])\.git([\\/]|$)') {
        throw 'The temporary directory is inside .git. Configure a normal user temporary directory before preparing a candidate.'
    }
    if (Test-Path -LiteralPath $candidatePath) { throw 'The candidate directory already exists. Nothing was replaced.' }
    Invoke-SchooltoolGit update-ref --no-deref $recoveryRef $commit ('0' * 40)
    Invoke-SchooltoolGit worktree add --detach $candidatePath $commit | Out-Host
    [pscustomobject]@{ Id = $id; Kind = $Kind; Path = $candidatePath; Repository = $repository; RecoveryRef = $recoveryRef; Commit = $commit }
}

function Assert-SchooltoolCandidate {
    param([object]$Candidate, [switch]$AllowAdvancedHead)
    if ($Candidate.Id -cnotmatch '^[a-f0-9]{32}$' -or $Candidate.Kind -cnotin @('preview', 'release') -or
        $Candidate.Commit -cnotmatch '^[a-f0-9]{40}$') { throw 'Invalid detached candidate identity.' }
    $expectedRef = "refs/schooltool/candidates/$($Candidate.Kind)/$($Candidate.Id)"
    $archivedRef = '^refs/schooltool/archived-heads/[0-9]{8}-[a-f0-9]{32}/codex/preview-' + $Candidate.Id + '$'
    if ($Candidate.RecoveryRef -cne $expectedRef -and -not ($Candidate.Kind -ceq 'preview' -and $Candidate.RecoveryRef -cmatch $archivedRef)) {
        throw 'Invalid candidate recovery reference.'
    }
    if ([System.IO.Path]::GetFullPath($Candidate.Path) -cne (Get-Location).Path -or
        -not (Test-Path -LiteralPath (Join-Path $Candidate.Path '.git') -PathType Leaf) -or
        [System.IO.Path]::GetFullPath((Invoke-SchooltoolGit rev-parse --git-common-dir)) -cne $Candidate.Repository -or
        (Invoke-SchooltoolGit branch --show-current)) { throw 'The detached candidate checkout changed or belongs to another repository.' }
    & git symbolic-ref --quiet $Candidate.RecoveryRef | Out-Null
    if ($LASTEXITCODE -ne 1) { throw 'The candidate recovery reference must be a direct ref.' }
    if ((Invoke-SchooltoolGit rev-parse --verify $Candidate.RecoveryRef) -cne $Candidate.Commit) { throw 'The candidate recovery reference changed.' }
    $head = Invoke-SchooltoolGit rev-parse HEAD
    if ($AllowAdvancedHead) {
        if (-not (Test-SchooltoolAncestor $Candidate.Commit $head)) { throw 'The candidate history changed unexpectedly.' }
    }
    elseif ($head -cne $Candidate.Commit) { throw 'The detached candidate commit changed.' }
}

function Save-SchooltoolCandidate {
    param([object]$Candidate)
    Push-Location -LiteralPath $Candidate.Path
    try {
        Assert-SchooltoolCandidate -Candidate $Candidate -AllowAdvancedHead
        $head = Invoke-SchooltoolGit rev-parse HEAD
        Invoke-SchooltoolGit update-ref --no-deref $Candidate.RecoveryRef $head $Candidate.Commit
        $Candidate.Commit = $head
    }
    finally { Pop-Location }
}

function New-SchooltoolCandidateTestDatabase {
    $commonDirectory = [System.IO.Path]::GetFullPath((Invoke-SchooltoolGit rev-parse --git-common-dir))
    $receipt = Join-Path $commonDirectory ('schooltool-test-db-' + [guid]::NewGuid().ToString('N') + '.json')
    $result = & php (Join-Path $PSScriptRoot 'workflow-test-database.php') create $receipt
    if ($LASTEXITCODE -ne 0) { throw 'Could not create an owned local test database. Existing databases were not used.' }
    $owned = ($result -join "`n") | ConvertFrom-Json -ErrorAction Stop
    if ($owned.database -cnotmatch '^pest_test_test_[0-9]{24}$' -or [System.IO.Path]::GetFullPath($owned.receipt) -ne $receipt) {
        throw "The test database helper returned an invalid ownership receipt. Inspect $receipt before retrying."
    }
    [pscustomobject]@{ Database = $owned.database; ReceiptPath = $receipt }
}

function Remove-SchooltoolCandidateTestDatabase {
    param([string]$Database, [string]$ReceiptPath)
    & php (Join-Path $PSScriptRoot 'workflow-test-database.php') remove $ReceiptPath $Database
    if ($LASTEXITCODE -ne 0) { throw "Owned test database cleanup failed for $Database. Ownership receipt retained at $ReceiptPath." }
}

function Enter-SchooltoolCandidateEnvironment {
    [CmdletBinding()]
    param($Candidate)
    if ($script:SchooltoolActiveCandidateEnvironment) { throw 'A workflow test environment is already active.' }
    if (-not (Test-Path -LiteralPath (Join-Path $Candidate.Path '.git') -PathType Leaf)) {
        throw 'Workflow tests require an isolated Git worktree; the ordinary project environment will not be used.'
    }
    $owned = New-SchooltoolCandidateTestDatabase
    $previous = @{}
    $cachePrefix = 'bootstrap/cache/' + [System.IO.Path]::GetFileNameWithoutExtension($owned.ReceiptPath)
    $relativeCachePaths = @("$cachePrefix.config.php", "$cachePrefix.routes.php", "$cachePrefix.packages.php", "$cachePrefix.services.php", "$cachePrefix.events.php")
    $cachePaths = @($relativeCachePaths | ForEach-Object { Join-Path $Candidate.Path $_ })
    $snapshot = [pscustomobject]@{ CandidatePath = [System.IO.Path]::GetFullPath($Candidate.Path); CreatedDatabase = $owned.Database; ReceiptPath = $owned.ReceiptPath; PreviousEnvironment = $previous; CachePaths = $cachePaths }
    try {
        $random = [System.Security.Cryptography.RandomNumberGenerator]::Create()
        $keyBytes = New-Object byte[] 32
        try { $random.GetBytes($keyBytes) } finally { $random.Dispose() }
        $settings = [ordered]@{
            APP_ENV = 'testing'; APP_KEY = ('base64:' + [Convert]::ToBase64String($keyBytes)); APP_URL = 'http://localhost'; APP_DEBUG = 'false'
            APP_MAINTENANCE_DRIVER = 'file'; DB_CONNECTION = 'mysql'; DB_HOST = '127.0.0.1'; DB_PORT = '3306'
            DB_DATABASE = $owned.Database; DB_DATABASE_TEST = 'pest_test'; DB_CONNECTION_TEST = 'mysql'; DB_USERNAME = 'root'; DB_PASSWORD = '(empty)'; DB_URL = '(null)'; DB_SOCKET = '(empty)'
            MAIL_MAILER = 'array'; QUEUE_CONNECTION = 'sync'; CACHE_STORE = 'array'; SESSION_DRIVER = 'array'
            BROADCAST_CONNECTION = 'log'; FILESYSTEM_DISK = 'local'; REDIS_HOST = '127.0.0.1'; REDIS_PORT = '6379'; REDIS_DB = '15'; REDIS_CACHE_DB = '15'
            PULSE_ENABLED = 'false'; TELESCOPE_ENABLED = 'false'; NIGHTWATCH_ENABLED = 'false'; SCHOOLTOOL_PREVIEW_INSTANCE = 'false'
            LOG_CHANNEL = 'single'; LOG_LEVEL = 'error'; TEST_TOKEN = [guid]::NewGuid().ToString('N')
            APP_CONFIG_CACHE = $relativeCachePaths[0]; APP_ROUTES_CACHE = $relativeCachePaths[1]; APP_PACKAGES_CACHE = $relativeCachePaths[2]; APP_SERVICES_CACHE = $relativeCachePaths[3]; APP_EVENTS_CACHE = $relativeCachePaths[4]
        }
        $applicationKeys = @([Environment]::GetEnvironmentVariables('Process').Keys | Where-Object {
            $_ -match '^(APP_|DB_|DATABASE_|LARAVEL_|CACHE_|SESSION_|QUEUE_|REDIS_|MAIL_|BROADCAST_|REVERB_|PUSHER_|CLOUDWAYS_|AWS_|LOG_|LEGACY_RESTAURANT_|OPENAI_|ANTHROPIC_|GEMINI_|GROQ_|COHERE_|DEEPSEEK_|MISTRAL_|OPENROUTER_|JINA_|XAI_|ELEVENLABS_|SCHOOLTOOL_PREVIEW_(INSTANCE|CONTROL_|DATA_|SNAPSHOT_|MAIL_))'
        })
        foreach ($name in @($applicationKeys + @($settings.Keys)) | Select-Object -Unique) {
            $previous[$name] = [Environment]::GetEnvironmentVariable($name, 'Process')
            Remove-Item -LiteralPath ('Env:' + $name) -ErrorAction SilentlyContinue
        }
        foreach ($entry in $settings.GetEnumerator()) {
            [Environment]::SetEnvironmentVariable($entry.Key, $entry.Value, 'Process')
        }
        $environmentFile = Join-Path $Candidate.Path '.env'
        & git -C $Candidate.Path check-ignore --quiet .env
        if ($LASTEXITCODE -ne 0) { throw 'The candidate must ignore its private .env file before tests can be configured.' }
        $contents = @($settings.GetEnumerator() | ForEach-Object { $_.Key + '="' + $_.Value + '"' }) -join "`n"
        $file = [System.IO.File]::Open($environmentFile, [System.IO.FileMode]::CreateNew, [System.IO.FileAccess]::Write, [System.IO.FileShare]::None)
        try {
            $bytes = (New-Object System.Text.UTF8Encoding($false)).GetBytes($contents + "`n")
            $file.Write($bytes, 0, $bytes.Length)
        }
        finally { $file.Dispose() }
        $script:SchooltoolActiveCandidateEnvironment = $snapshot
        $snapshot
    }
    catch {
        Restore-SchooltoolCandidateEnvironment $snapshot
        throw
    }
}

function Restore-SchooltoolCandidateEnvironment {
    param($Snapshot)
    try {
        Remove-SchooltoolCandidateTestDatabase -Database $Snapshot.CreatedDatabase -ReceiptPath $Snapshot.ReceiptPath
        foreach ($path in $Snapshot.CachePaths) {
            if (Test-Path -LiteralPath $path) { [System.IO.File]::Delete($path) }
        }
    }
    finally {
        foreach ($entry in $Snapshot.PreviousEnvironment.GetEnumerator()) {
            if ($null -eq $entry.Value) {
                Remove-Item -LiteralPath ('Env:' + $entry.Key) -ErrorAction SilentlyContinue
            }
            else {
                [Environment]::SetEnvironmentVariable($entry.Key, $entry.Value, 'Process')
            }
        }
        $script:SchooltoolActiveCandidateEnvironment = $null
    }
}

function Get-SchooltoolSourceTree {
    $paths = @('.', ':(exclude)deployment/frontend-build.sha256', ':(exclude)deployment/frontend-build.tar.gz', ':(exclude)deployment/source-commit', ':(exclude)deployment/source-manifest.sha256')
    Invoke-SchooltoolGit add -A -- @paths | Out-Host
    $entries = @(Invoke-SchooltoolGit ls-files --stage -- @paths)
    $hasher = [System.Security.Cryptography.SHA256]::Create()
    try {
        $bytes = [System.Text.Encoding]::UTF8.GetBytes(($entries -join "`n"))
        [BitConverter]::ToString($hasher.ComputeHash($bytes)).Replace('-', '').ToLowerInvariant()
    }
    finally { $hasher.Dispose() }
}

function Assert-SchooltoolCheckedSource {
    param([Parameter(Position = 0)][string]$Tree, [string]$Branch, [string]$Head)
    if (($Branch -and (Invoke-SchooltoolGit branch --show-current) -cne $Branch) -or ($Head -and (Invoke-SchooltoolGit rev-parse HEAD) -ne $Head) -or (Get-SchooltoolSourceTree) -ne $Tree) {
        throw 'Source files or the active branch changed during checks. Nothing was published; all edits are preserved.'
    }
}

function Assert-SchooltoolSaved {
    $branch = Invoke-SchooltoolGit branch --show-current
    if (-not (Test-SchooltoolRef "refs/remotes/origin/$branch")) {
        if ($branch -like 'feature/*' -and (Test-SchooltoolAncestor HEAD refs/remotes/origin/main)) {
            Write-Host 'This feature is already included in main and was closed on origin.' -ForegroundColor Yellow
            return
        }
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
    if ($Branch -like 'feature/*') {
        Get-SchooltoolActiveFeature -Branch $Branch | Out-Null
    }
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
    $active = Get-SchooltoolActiveFeature -Branch $branch -AllowMissing
    if ($active) { throw "Feature $branch already exists. Use gitwork NAME." }
    if ((Test-SchooltoolRef "refs/heads/$branch") -or (Test-SchooltoolRef "refs/remotes/origin/$branch")) {
        throw "$branch already exists. Use gitwork instead."
    }
    $mainHead = Invoke-SchooltoolGit rev-parse refs/remotes/origin/main
    $reservation = New-SchooltoolFeatureReservation $branch
    Invoke-SchooltoolGit push --atomic "--force-with-lease=$($reservation.ReservationRef):" "--force-with-lease=refs/heads/${branch}:" origin "$($reservation.ReservationCommit):$($reservation.ReservationRef)" "${mainHead}:refs/heads/$branch"
    Update-SchooltoolRemote
    Invoke-SchooltoolGit switch --track -c $branch "refs/remotes/origin/$branch"
    Invoke-SchooltoolLocalPreparation
    Write-SchooltoolCompletionTime
}

function gitwork {
    param([string]$Name)
    Assert-SchooltoolRepository
    Assert-SchooltoolClean
    Update-SchooltoolRemote
    Assert-SchooltoolSaved
    $features = @(Invoke-SchooltoolGit for-each-ref '--format=%(refname:strip=3)' refs/remotes/origin/feature/)
    if (-not $Name) {
        if ($features.Count -eq 0) { throw 'There is no open feature. Use gitstart NAME.' }
        if ($features.Count -ne 1) { throw "Choose a feature explicitly with gitwork NAME. Open features: $($features -join ', ')" }
        $Name = $features[0]
    }
    $branch = Get-SchooltoolFeatureBranch $Name
    if ($branch -cnotin $features) { throw "Feature $branch does not exist on origin. Use gitstart NAME." }
    $active = Get-SchooltoolActiveFeature -Branch $branch -AllowMissing
    if (-not $active) {
        $head = Invoke-SchooltoolGit rev-parse "refs/remotes/origin/$branch"
        $reservation = New-SchooltoolFeatureReservation $branch
        Invoke-SchooltoolGit push --atomic "--force-with-lease=$($reservation.ReservationRef):" "--force-with-lease=refs/heads/${branch}:$head" origin "$($reservation.ReservationCommit):$($reservation.ReservationRef)" "${head}:refs/heads/$branch"
        Assert-SchooltoolFeatureSnapshot -Feature $reservation -FeatureCommit $head
        Write-Host "Existing feature $branch registered for this workflow." -ForegroundColor Cyan
    }
    Switch-SchooltoolBranch $branch
}

function gitmain {
    [CmdletBinding()]
    param()
    Switch-SchooltoolBranch main
}

function gitsave {
    param([Parameter(Mandatory = $true)][ValidateNotNullOrEmpty()][string]$Message, [string]$Version, [switch]$Full)
    Assert-SchooltoolRepository
    $currentBranch = Invoke-SchooltoolGit branch --show-current
    if ($currentBranch -eq 'main') {
        Invoke-SchooltoolPublish -message $Message -version $Version -Full:$Full
        return
    }
    $branch = Assert-SchooltoolFeature
    if ($Full) { throw 'Full local release checks are optional on main; saved features use background CI.' }
    if ($Version) { throw 'Versions can only be published on main or with gitrelease.' }
    Update-SchooltoolRemote
    $active = Get-SchooltoolActiveFeature
    if ($active.Branch -cne $branch) { throw 'This feature is no longer active. Nothing was published.' }
    $remoteHead = Invoke-SchooltoolGit rev-parse "refs/remotes/origin/$branch"
    if (-not (Test-SchooltoolAncestor $remoteHead HEAD)) {
        throw 'Origin contains feature commits missing locally. Use gitwork for a fast-forward, or resolve divergent commits explicitly. Nothing was merged or committed.'
    }
    if (Invoke-SchooltoolGit status --porcelain --untracked-files=all) {
        Invoke-SchooltoolCommand 'Checking UTF-8 source files...' { php scripts/check-encoding.php }
        Invoke-SchooltoolGit add -A
        Invoke-SchooltoolGit commit -m $Message
    }
    Assert-SchooltoolClean
    if ((Invoke-SchooltoolGit branch --show-current) -cne $branch) { throw 'The active branch changed. Nothing was pushed.' }
    $savedHead = Invoke-SchooltoolGit rev-parse HEAD
    try {
        Assert-SchooltoolFeatureSnapshot -Feature $active -FeatureCommit $remoteHead
        if (-not (Test-SchooltoolAncestor $remoteHead $savedHead)) { throw 'Saving cannot rewrite feature history.' }
        Invoke-SchooltoolGit push --set-upstream "--force-with-lease=refs/heads/${branch}:$remoteHead" origin "${savedHead}:refs/heads/$branch"
    }
    catch {
        Write-Host 'Your changes remain committed locally, but saving to GitHub failed.' -ForegroundColor Yellow
        throw
    }
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
    $active = Get-SchooltoolActiveFeature
    if ($active.Branch -cne $branch) { throw 'This is not the active feature.' }
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
    $features = @(Invoke-SchooltoolGit for-each-ref '--format=%(refname:strip=3)' refs/remotes/origin/feature/)
    Write-Host "Open features: $($features -join ', '). Select with gitwork NAME; preview with gitpreview -Feature NAME." -ForegroundColor Cyan
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
    Write-Host 'Live deploys main; the isolated preview deploys the feature. gitcheck does not inspect either server.' -ForegroundColor Cyan
}

function Assert-SchooltoolVersion {
    param([string]$Version, [string]$AllowRetryCommit)
    if (-not $Version) { return }
    if ($Version -notmatch '^\d+\.\d+\.\d+$') {
        throw 'Use a version such as 3.48.0, without a v prefix.'
    }
    if (Test-SchooltoolRef "refs/tags/v$Version") {
        $tagCommit = Invoke-SchooltoolGit rev-list -n 1 "refs/tags/v$Version"
        if (-not $AllowRetryCommit -or $tagCommit -ne $AllowRetryCommit) {
            throw "Tag v$Version already exists locally. Use a new version."
        }
    }
    $remoteTag = Invoke-SchooltoolGit ls-remote --tags origin "refs/tags/v$Version"
    if ($remoteTag) {
        throw "Tag v$Version already exists on origin. Use a new version."
    }
}

function Get-SchooltoolDiscardPreviewState {
    param($Feature)
    $target = Get-SchooltoolPreviewTarget
    $status = Invoke-SchooltoolRemoteJson -Target $target -Command "php artisan preview:snapshot status --feature='$($Feature.Id)' --no-interaction"
    if ($status.state_token -cnotmatch '^[a-f0-9]{64}$' -or 'feature_id' -cnotin @($status.PSObject.Properties.Name) -or
        ($null -ne $status.feature_id -and $status.feature_id -cnotmatch '^[a-f0-9]{32}$')) {
        throw 'Cannot verify the shared preview. No feature was discarded.'
    }
    if ($status.feature_id -ceq $Feature.Id) {
        throw 'This feature is active in the shared preview. Select another preview and complete its deployment before discarding this feature.'
    }
    $status.state_token
}

function Assert-SchooltoolDiscardCheckout {
    param([string]$Branch, [string]$FeatureCommit, [string]$LocalCommit, [string]$MainCommit)
    Assert-SchooltoolRepository
    Assert-SchooltoolClean
    if ((Invoke-SchooltoolGit branch --show-current) -cne 'main' -or (Invoke-SchooltoolGit rev-parse HEAD) -cne $MainCommit) {
        throw 'Run gitdiscard from clean main. Use gitmain first; no checkout is switched automatically.'
    }
    if (@(Invoke-SchooltoolGit worktree list --porcelain) -ccontains "branch refs/heads/$Branch") {
        throw 'The feature is checked out in a worktree. Leave that worktree intact and switch it away from the feature first.'
    }
    $localExists = Test-SchooltoolRef "refs/heads/$Branch"
    if ($localExists -ne (-not [string]::IsNullOrEmpty($LocalCommit))) { throw 'The local feature changed. Nothing was discarded locally.' }
    if ($localExists) {
        & git symbolic-ref --quiet "refs/heads/$Branch" | Out-Null
        if ($LASTEXITCODE -ne 1) { throw 'The local feature must be a direct reference.' }
        if ((Invoke-SchooltoolGit rev-parse "refs/heads/$Branch") -cne $LocalCommit -or $LocalCommit -cne $FeatureCommit) {
            throw 'Local and remote feature commits differ. Save or reconcile the feature before discarding it.'
        }
    }
}

function gitdiscard {
    param([Parameter(Mandatory = $true)][string]$Name)
    $branch = Get-SchooltoolFeatureBranch $Name
    Assert-SchooltoolRepository
    Assert-SchooltoolClean
    if ((Invoke-SchooltoolGit branch --show-current) -cne 'main') { throw 'Run gitdiscard from clean main. Use gitmain first.' }
    Update-SchooltoolRemote
    $main = Invoke-SchooltoolGit rev-parse HEAD
    if ($main -cne (Invoke-SchooltoolGit rev-parse refs/remotes/origin/main)) { throw 'Local main must match origin/main. Run gitmain first.' }
    $feature = Get-SchooltoolActiveFeature -Branch $branch
    $remote = Invoke-SchooltoolGit rev-parse "refs/remotes/origin/$branch"
    $local = if (Test-SchooltoolRef "refs/heads/$branch") { Invoke-SchooltoolGit rev-parse "refs/heads/$branch" } else { '' }
    $base = Invoke-SchooltoolGit merge-base $main $remote
    if (-not $base -or -not (Test-SchooltoolAncestor $base $remote) -or -not (Test-SchooltoolAncestor $base $main)) {
        throw 'Feature and main do not share verified history.'
    }
    Assert-SchooltoolDiscardCheckout $branch $remote $local $main
    $previewState = Get-SchooltoolDiscardPreviewState $feature
    Write-Host "Discard $branch at $remote; lifecycle $($feature.Id). main will not receive these commits." -ForegroundColor Yellow
    Write-Host 'The matching remote reservation will close. Local recovery refs, preview bundles and other features remain.' -ForegroundColor Yellow
    if ((Read-Host "Type DISCARD $branch to continue") -cne "DISCARD $branch") { Write-Host 'Discard cancelled. No feature was removed.'; return }
    Assert-SchooltoolFeatureSnapshot -Feature $feature -FeatureCommit $remote -MainCommit $main
    Assert-SchooltoolDiscardCheckout $branch $remote $local $main
    if ((Get-SchooltoolDiscardPreviewState $feature) -cne $previewState) { throw 'The shared preview changed. Nothing was discarded.' }
    $recovery = "refs/schooltool/discarded/$($feature.Id)/$([guid]::NewGuid().ToString('N'))"
    Invoke-SchooltoolGit update-ref --no-deref "$recovery/feature" $remote ('0' * 40)
    Invoke-SchooltoolGit update-ref --no-deref "$recovery/reservation" $feature.ReservationCommit ('0' * 40)
    Write-Host "Recovery: $recovery/feature and $recovery/reservation" -ForegroundColor Cyan
    Invoke-SchooltoolGit push --atomic "--force-with-lease=refs/heads/${branch}:$remote" "--force-with-lease=$($feature.ReservationRef):$($feature.ReservationCommit)" origin ":refs/heads/$branch" ":$($feature.ReservationRef)"
    try {
        Assert-SchooltoolDiscardCheckout $branch $remote $local $main
        if ($local) { Invoke-SchooltoolGit update-ref --no-deref -d "refs/heads/$branch" $local }
    }
    catch {
        Write-Warning "The remote feature and reservation are already closed; local cleanup stopped and recovery refs remain: $($_.Exception.Message)"
        return
    }
    Write-Host "Discarded $branch locally and on origin without merging. main, databases and preview files are unchanged." -ForegroundColor Green
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
    $active = Get-SchooltoolActiveFeature
    if ($active.Branch -cne $feature) { throw 'This is not the active feature.' }
    Assert-SchooltoolSaved
    $featureHead = Invoke-SchooltoolGit rev-parse HEAD
    $remoteFeature = Invoke-SchooltoolGit rev-parse "refs/remotes/origin/$feature"
    if ($featureHead -ne $remoteFeature) {
        throw 'New feature commits exist on origin. Run gitwork and test them first.'
    }
    $mainHead = Invoke-SchooltoolGit rev-parse refs/remotes/origin/main
    if (Test-SchooltoolRef refs/heads/main) {
        & git merge-base --is-ancestor refs/heads/main $mainHead
        if ($LASTEXITCODE -ne 0) {
            throw 'Local main has unpublished commits. Publish or resolve them before releasing.'
        }
    }
    if (Test-SchooltoolAncestor $featureHead $mainHead) {
        throw 'The feature contains no new commits to release.'
    }
    Assert-SchooltoolVersion $Version
    $candidate = New-SchooltoolCandidateWorktree -Kind release -SourceCommit $mainHead
    $candidateEnvironment = Enter-SchooltoolCandidateEnvironment -Candidate $candidate
    try {
        Push-Location -LiteralPath $candidate.Path
        try {
            Invoke-SchooltoolGit merge --no-ff --no-edit -m $Message $featureHead
            Save-SchooltoolCandidate $candidate
            Invoke-SchooltoolPublish -message $Message -version $Version -ExpectedMainCommit $mainHead -Feature $active -ExpectedFeatureCommit $featureHead -Candidate $candidate
            $releaseHead = Invoke-SchooltoolGit rev-parse HEAD
        }
        finally { Pop-Location }
    }
    catch {
        Write-Host "Release stopped. Your working branch is unchanged. Candidate retained at $($candidate.Path) ($($candidate.RecoveryRef))." -ForegroundColor Yellow
        throw
    }
    finally {
        try { Save-SchooltoolCandidate $candidate }
        finally { Restore-SchooltoolCandidateEnvironment $candidateEnvironment }
    }
    Write-Host "Release published to main. Candidate retained at $($candidate.Path)." -ForegroundColor Green
    try {
        Update-SchooltoolRemote
        $publishedHead = Invoke-SchooltoolGit rev-parse refs/remotes/origin/main
        if (-not (Test-SchooltoolAncestor $releaseHead $publishedHead)) { throw 'Could not verify the published main.' }
        Assert-SchooltoolClean
        if ((Invoke-SchooltoolGit branch --show-current) -cne $feature -or (Invoke-SchooltoolGit rev-parse HEAD) -ne $featureHead) {
            throw 'Your working branch changed during release checks; it will be preserved.'
        }
        if (Test-SchooltoolRef refs/heads/main) {
            if (-not (Test-SchooltoolAncestor refs/heads/main $publishedHead)) { throw 'Local main contains unpublished work.' }
            Invoke-SchooltoolGit switch main
            Invoke-SchooltoolGit merge --ff-only $publishedHead
        } else {
            Invoke-SchooltoolGit switch --track -c main refs/remotes/origin/main
        }
        try { Invoke-SchooltoolLocalPreparation }
        catch {
            Write-Host "Release is already published. Local main preparation failed: $($_.Exception.Message)" -ForegroundColor Yellow
            Write-Host 'The integrated local feature branch is preserved. Fix the local preparation problem, then run gitmain to retry. Do not publish the release again.' -ForegroundColor Yellow
            Write-Host "After gitmain succeeds, remove the integrated local branch if still present: git branch -d $feature" -ForegroundColor Yellow
            return
        }
        $worktrees = @(Invoke-SchooltoolGit worktree list --porcelain)
        if ($worktrees -ccontains "branch refs/heads/$feature") { throw 'The feature is used by another worktree and will be preserved locally.' }
        if (-not (Test-SchooltoolAncestor $featureHead HEAD)) { throw 'The feature is not fully included in local main.' }
        Invoke-SchooltoolGit update-ref -d "refs/heads/$feature" $featureHead
        Write-Host "You are on main. Integrated feature $feature was removed locally and from GitHub." -ForegroundColor Green
    }
    catch {
        Write-Host "Release succeeded, but local cleanup stopped: $($_.Exception.Message)" -ForegroundColor Yellow
    }
    Write-Host 'Use gitdeploy when you want to update the live application.' -ForegroundColor Green
}
