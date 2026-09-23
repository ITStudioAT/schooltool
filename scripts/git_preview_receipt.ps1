function Get-SchooltoolPreviewDirectory {
    [System.IO.Path]::GetFullPath((Join-Path (Invoke-SchooltoolGit rev-parse --git-common-dir) 'schooltool-preview'))
}

function Get-SchooltoolPreviewOrigin {
    param([switch]$Push)
    $urls = @(if ($Push) { Invoke-SchooltoolGit remote get-url --push --all origin } else { Invoke-SchooltoolGit remote get-url --all origin })
    if ($urls.Count -ne 1) { throw 'Preview publication requires exactly one origin fetch URL and one push URL.' }
    $urls[0]
}

function Assert-SchooltoolPreviewPlainPath {
    param([string]$Path)
    $item = Get-Item -LiteralPath $Path -Force -ErrorAction Stop
    while ($item) {
        if ($item.Attributes -band [System.IO.FileAttributes]::ReparsePoint) { throw 'Preview recovery paths must not contain links or junctions.' }
        $item = if ($item -is [System.IO.DirectoryInfo]) { $item.Parent } else { $item.Directory }
    }
}

function Write-SchooltoolPreviewReceipt {
    param([object]$Receipt)
    $directory = Get-SchooltoolPreviewDirectory
    Assert-SchooltoolPreviewPlainPath $directory
    Add-Type -AssemblyName System.Security
    $bytes = [System.Text.Encoding]::UTF8.GetBytes(($Receipt | ConvertTo-Json -Depth 6 -Compress))
    $entropy = [System.Text.Encoding]::UTF8.GetBytes('schooltool-preview-receipt-v1')
    $protected = [System.Security.Cryptography.ProtectedData]::Protect($bytes, $entropy, [System.Security.Cryptography.DataProtectionScope]::CurrentUser)
    $path = Join-Path $directory "$($Receipt.Id).receipt"
    $stream = [System.IO.File]::Open($path, [System.IO.FileMode]::CreateNew, [System.IO.FileAccess]::Write, [System.IO.FileShare]::None)
    try { $stream.Write($protected, 0, $protected.Length); $stream.Flush($true) }
    finally { $stream.Dispose() }
}

function Read-SchooltoolPreviewReceipt {
    param([string]$Id)
    if ($Id -cnotmatch '^[a-f0-9]{32}$') { throw 'Usage: gitpreview resume BUNDLE_ID [-RefreshData]' }
    $directory = Get-SchooltoolPreviewDirectory
    $path = Join-Path $directory "$Id.receipt"
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) {
        throw 'No source-bound successful-check receipt exists for this bundle. Older logs cannot certify the tested source retrospectively. Nothing was published and no checks were restarted.'
    }
    Assert-SchooltoolPreviewPlainPath $path
    if ((Get-Item -LiteralPath $path).Length -gt 65536) { throw 'Invalid preview receipt size.' }
    try {
        Add-Type -AssemblyName System.Security
        $entropy = [System.Text.Encoding]::UTF8.GetBytes('schooltool-preview-receipt-v1')
        $bytes = [System.Security.Cryptography.ProtectedData]::Unprotect([System.IO.File]::ReadAllBytes($path), $entropy, [System.Security.Cryptography.DataProtectionScope]::CurrentUser)
        $receipt = [System.Text.Encoding]::UTF8.GetString($bytes) | ConvertFrom-Json -ErrorAction Stop
    }
    catch { throw 'The preview receipt is invalid or belongs to another Windows account/computer. Nothing was published.' }
    if ($receipt.Format -cnotin @('schooltool-preview-v1', 'schooltool-preview-v2', 'schooltool-preview-v3') -or $receipt.Id -cne $Id) { throw 'Invalid preview check receipt.' }
    Assert-SchooltoolPreviewEvidence $receipt
    foreach ($field in @('FeatureCommit', 'MainCommit', 'SourceCommit', 'ArtifactCommit')) {
        if ($receipt.$field -cnotmatch '^[a-f0-9]{40}$') { throw 'Invalid preview commit identity.' }
    }
    if ($receipt.Checksum -cnotmatch '^[a-f0-9]{64}$' -or $receipt.SourceTree -cnotmatch '^[a-f0-9]{64}$' -or
        $receipt.Feature.Id -cnotmatch '^[a-f0-9]{32}$' -or $receipt.Feature.ReservationCommit -cnotmatch '^[a-f0-9]{40}$' -or
        $receipt.Feature.Branch -cnotmatch '^feature/[a-z0-9]+(?:-[a-z0-9]+)*$') {
        throw 'Invalid preview receipt identity.'
    }
    if ($receipt.Format -ceq 'schooltool-preview-v1') {
        if ($receipt.Candidate.Branch -cnotmatch '^codex/preview-[a-f0-9]{32}$') { throw 'Invalid legacy preview candidate identity.' }
    }
    elseif ($receipt.Candidate.Id -cnotmatch '^[a-f0-9]{32}$' -or $receipt.Candidate.Kind -cne 'preview' -or
        $receipt.Candidate.RecoveryRef -cne "refs/schooltool/candidates/preview/$($receipt.Candidate.Id)" -or
        $receipt.Candidate.Commit -cne $receipt.ArtifactCommit) { throw 'Invalid detached preview candidate identity.' }
    if (Test-Path -LiteralPath (Join-Path $directory "$Id.started")) {
        throw 'Publication was already attempted for this bundle. Inspect Git and server state before recovery; automatic replay is blocked.'
    }
    $receipt
}

function Assert-SchooltoolPreviewEvidence {
    param([object]$Receipt)
    if ($Receipt.Format -ceq 'schooltool-preview-v3') {
        if ($Receipt.EvidenceKind -cne 'inline-build-and-integrity') { throw 'V3 preview receipts require inline build and integrity evidence.' }
        if ($Receipt.Checks -cne 'preflight-success') { throw 'Invalid preview check receipt.' }
        return
    }
    if ($Receipt.Format -ceq 'schooltool-preview-v2' -and $Receipt.EvidenceKind -cne 'inline-full-checks') {
        throw 'New preview receipts require the complete inline full checks.'
    }
    if ($Receipt.EvidenceKind -cnotin @('inline-full-checks', 'legacy-reviewed', 'reviewed-patch')) { throw 'Unknown preview evidence provenance.' }
    $expectedChecks = if ($Receipt.EvidenceKind -ceq 'reviewed-patch') { 'reviewed-patch-success' } else { 'full-success' }
    if ($Receipt.Checks -cne $expectedChecks) { throw 'Invalid preview check receipt.' }
    if ($Receipt.EvidenceKind -ceq 'inline-full-checks') { return }
    if (-not $Receipt.Evidence.ReviewedAt -or -not $Receipt.Evidence.Basis -or @($Receipt.Evidence.Files).Count -lt 1) { throw 'Missing reviewed legacy evidence or reviewed patch evidence.' }
    foreach ($file in $Receipt.Evidence.Files) {
        Assert-SchooltoolPreviewPlainPath $file.Path
        if ($file.Sha256 -cnotmatch '^[a-f0-9]{64}$' -or (Get-SchooltoolFileChecksum $file.Path) -cne $file.Sha256) { throw 'The reviewed legacy preview evidence changed or reviewed patch evidence changed.' }
    }
    if ($Receipt.EvidenceKind -ceq 'reviewed-patch') {
        $approved = @('app/Services/FeaturePreviewDatabaseGuard.php', 'tests/Unit/FeaturePreviewDatabaseGuardTest.php')
        if ($null -ne $Receipt.Evidence.Scope) {
            if ($Receipt.Evidence.Scope -ceq 'snapshot-lifetime') {
                $approved = @(
                    'app/Services/FeaturePreviewDatabaseGuard.php',
                    'app/Services/FeaturePreviewSnapshotService.php',
                    'scripts/deploy_preview_cloudways.sh',
                    'tests/Feature/FeaturePreviewReadOnlySourceTest.php',
                    'tests/Feature/FeaturePreviewSnapshotTest.php',
                    'tests/Unit/PreviewDeploymentTest.php'
                )
            }
            elseif ($Receipt.Evidence.Scope -ceq 'snapshot-target-config') {
                $approved = @('app/Services/FeaturePreviewDatabaseGuard.php', 'tests/Feature/FeaturePreviewReadOnlySourceTest.php')
            }
            else { throw 'Invalid reviewed patch scope.' }
        }
        if ($Receipt.Evidence.BaselineSource -cnotmatch '^[a-f0-9]{40}$' -or @($Receipt.Evidence.ReviewedFiles).Count -ne $approved.Count) { throw 'Invalid reviewed patch scope.' }
        foreach ($path in $approved) {
            $matches = @($Receipt.Evidence.ReviewedFiles | Where-Object { $_.Path -ceq $path -and $_.Sha256 -cmatch '^[a-f0-9]{64}$' })
            if ($matches.Count -ne 1) { throw 'Invalid reviewed patch scope.' }
        }
    }
}

function Assert-SchooltoolPreviewReceipt {
    param([object]$Receipt, [string]$Root)
    Assert-SchooltoolPreviewEvidence $Receipt
    Push-Location -LiteralPath $Root
    try {
        Assert-SchooltoolRepository
        Assert-SchooltoolClean
        if ($Receipt.Root -cne (Get-Location).Path -or $Receipt.Directory -cne (Get-SchooltoolPreviewDirectory) -or
            $Receipt.Origin -cne (Get-SchooltoolPreviewOrigin) -or $Receipt.PushOrigin -cne (Get-SchooltoolPreviewOrigin -Push) -or
            (Invoke-SchooltoolGit branch --show-current) -cne $Receipt.Feature.Branch -or (Invoke-SchooltoolGit rev-parse HEAD) -cne $Receipt.FeatureCommit) {
            throw 'The original checkout or origin differs from the checked preview receipt.'
        }
        Assert-SchooltoolFeatureSnapshot -Feature $Receipt.Feature -FeatureCommit $Receipt.FeatureCommit -MainCommit $Receipt.MainCommit
        if (Test-SchooltoolRef "refs/remotes/origin/preview/$($Receipt.Id)") { throw 'The preview bundle was already published. Automatic replay is blocked.' }
    }
    finally { Pop-Location }
    Assert-SchooltoolPreviewPlainPath $Receipt.Candidate.Path
    Push-Location -LiteralPath $Receipt.Candidate.Path
    try {
        if ($Receipt.Format -cin @('schooltool-preview-v2', 'schooltool-preview-v3')) {
            Assert-SchooltoolRepository -Candidate $Receipt.Candidate
        }
        elseif (Invoke-SchooltoolGit branch --show-current) {
            Assert-SchooltoolRepository
            if ((Invoke-SchooltoolGit branch --show-current) -cne $Receipt.Candidate.Branch) { throw 'The legacy preview candidate branch changed.' }
        }
        else {
            $archivePattern = '^refs/schooltool/archived-heads/[0-9]{8}-[a-f0-9]{32}/' + [regex]::Escape($Receipt.Candidate.Branch) + '$'
            $archives = @(Invoke-SchooltoolGit for-each-ref '--format=%(refname)' refs/schooltool/archived-heads/ | Where-Object { $_ -cmatch $archivePattern })
            $matchingArchives = @($archives | Where-Object { (Invoke-SchooltoolGit rev-parse --verify $_) -ceq $Receipt.ArtifactCommit })
            if ($matchingArchives.Count -eq 0) { throw 'The legacy preview candidate has no matching archived branch identity.' }
            $legacyCandidate = [pscustomobject]@{
                Id = $Receipt.Candidate.Branch.Substring('codex/preview-'.Length); Kind = 'preview'; Path = $Receipt.Candidate.Path
                Repository = [System.IO.Path]::GetDirectoryName($Receipt.Directory); RecoveryRef = $matchingArchives[0]; Commit = $Receipt.ArtifactCommit
            }
            Assert-SchooltoolRepository -Candidate $legacyCandidate
        }
        Assert-SchooltoolClean
        if ((Get-SchooltoolPreviewDirectory) -cne $Receipt.Directory -or
            (Invoke-SchooltoolGit rev-parse HEAD) -cne $Receipt.ArtifactCommit) { throw 'The preview candidate changed or belongs to another repository.' }
        $parents = (Invoke-SchooltoolGit rev-list --parents -n 1 HEAD) -split ' '
        if ($parents.Count -ne 2 -or $parents[1] -cne $Receipt.SourceCommit) { throw 'The preview artifact source does not match its receipt.' }
        Invoke-SchooltoolGit merge-base --is-ancestor $Receipt.FeatureCommit $Receipt.SourceCommit | Out-Null
        Invoke-SchooltoolGit merge-base --is-ancestor $Receipt.MainCommit $Receipt.SourceCommit | Out-Null
        $allowed = @('deployment/frontend-build.sha256', 'deployment/frontend-build.tar.gz', 'deployment/source-commit', 'deployment/source-manifest.sha256')
        if ($Receipt.EvidenceKind -ceq 'reviewed-patch') {
            Invoke-SchooltoolGit merge-base --is-ancestor $Receipt.Evidence.BaselineSource $Receipt.SourceCommit | Out-Null
            $reviewedPaths = @($Receipt.Evidence.ReviewedFiles | ForEach-Object { $_.Path })
            $changes = @(Invoke-SchooltoolGit diff --no-renames --name-only $Receipt.Evidence.BaselineSource $Receipt.SourceCommit | Where-Object { $_ -cnotin $allowed })
            if ($changes.Count -ne $reviewedPaths.Count -or @($changes | Where-Object { $_ -cnotin $reviewedPaths }).Count -ne 0) { throw 'The preview source contains changes outside the reviewed patch.' }
            foreach ($file in $Receipt.Evidence.ReviewedFiles) {
                $path = Join-Path $Receipt.Candidate.Path $file.Path
                Assert-SchooltoolPreviewPlainPath $path
                if ((Get-SchooltoolFileChecksum $path) -cne $file.Sha256) { throw 'The reviewed patch source checksum changed.' }
            }
        }
        foreach ($path in @(Invoke-SchooltoolGit diff --name-only $Receipt.SourceCommit $Receipt.ArtifactCommit)) {
            if ($path -cnotin $allowed) { throw 'The preview artifact contains unchecked source changes.' }
        }
        Assert-SchooltoolCheckedSource $Receipt.SourceTree
        $archive = Join-Path $Receipt.Directory "$($Receipt.Id).tar.gz"
        Assert-SchooltoolPreviewPlainPath $archive
        if ((Get-SchooltoolFileChecksum $archive) -cne $Receipt.Checksum) { throw 'The preview bundle checksum differs from its checked receipt.' }
        Invoke-SchooltoolCommand 'Verifying the retained preview artifact...' { php scripts/frontend-release.php verify $Receipt.SourceCommit }
        Assert-SchooltoolClean
    }
    finally { Pop-Location }
}

function Start-SchooltoolPreviewPublication {
    param([string]$Id)
    $directory = Get-SchooltoolPreviewDirectory
    Assert-SchooltoolPreviewPlainPath $directory
    $stream = [System.IO.File]::Open((Join-Path $directory "$Id.started"), [System.IO.FileMode]::CreateNew, [System.IO.FileAccess]::Write, [System.IO.FileShare]::None)
    try { $stream.Flush($true) }
    finally { $stream.Dispose() }
}
