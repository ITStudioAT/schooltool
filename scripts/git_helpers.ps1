function Invoke-SchooltoolCommand {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Description,

        [Parameter(Mandatory = $true)]
        [scriptblock]$Command
    )

    Write-Host $Description -ForegroundColor Cyan
    & $Command

    if ($LASTEXITCODE -ne 0) {
        throw "$Description failed with exit code $LASTEXITCODE."
    }
}

function Start-SchooltoolCheckProcess {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Command,

        [Parameter(Mandatory = $true)]
        [string]$OutputPath,

        [Parameter(Mandatory = $true)]
        [string]$ErrorPath,

        [Parameter(Mandatory = $true)]
        [string]$WorkingDirectory
    )

    $commandShell = $env:COMSPEC
    if (-not $commandShell) {
        $commandShell = 'cmd.exe'
    }

    $redirectedCommand = '(' + $Command + ') 1>"' + $OutputPath + '" 2>"' + $ErrorPath + '"'
    $commandArguments = '/d /s /c "' + $redirectedCommand + '"'

    $process = Start-Process `
        -FilePath $commandShell `
        -ArgumentList $commandArguments `
        -WorkingDirectory $WorkingDirectory `
        -WindowStyle Hidden `
        -PassThru
    $null = $process.Handle
    $process
}

function Invoke-SchooltoolReleaseChecks {
    param([switch]$Full)
    if (-not $Full -or $script:SchooltoolActiveCandidateEnvironment) {
        Invoke-SchooltoolReleaseCheckProcesses -Full:$Full
        return
    }

    $sourceTree = Invoke-SchooltoolGit write-tree
    $sourceParent = Invoke-SchooltoolGit rev-parse HEAD
    $snapshotCommit = Invoke-SchooltoolGit commit-tree $sourceTree -p $sourceParent -m ('Check main source snapshot ' + [guid]::NewGuid().ToString('N'))
    $candidate = New-SchooltoolCandidateWorktree -Kind release -SourceCommit $snapshotCommit
    $candidateEnvironment = Enter-SchooltoolCandidateEnvironment -Candidate $candidate
    try {
        Push-Location -LiteralPath $candidate.Path
        try {
            Invoke-SchooltoolCommand 'Preparing isolated main test dependencies...' { php scripts/update.php --target=local --prepare }
            Invoke-SchooltoolReleaseCheckProcesses -Full
            Assert-SchooltoolClean
        }
        finally { Pop-Location }
    }
    finally {
        try { Save-SchooltoolCandidate $candidate }
        finally { Restore-SchooltoolCandidateEnvironment $candidateEnvironment }
        Write-Host "Main test candidate retained at $($candidate.Path)." -ForegroundColor DarkGray
    }
    Invoke-SchooltoolCommand 'Building the checked main source for publication...' { npm run build }
}

function Invoke-SchooltoolReleaseCheckProcesses {
    param(
        [Parameter(Mandatory = $false)]
        [switch]$Full
    )

    $temporaryPrefix = Join-Path ([System.IO.Path]::GetTempPath()) ("schooltool-release-" + [guid]::NewGuid().ToString('N'))
    $frontendOutput = "$temporaryPrefix-frontend.out"
    $frontendError = "$temporaryPrefix-frontend.err"
    $analysisOutput = "$temporaryPrefix-analysis.out"
    $analysisError = "$temporaryPrefix-analysis.err"

    $frontendCommand = if ($Full) { 'npm run test:ui && npm run build' } else { 'npm run build' }
    $scope = if ($Full) { 'full local tests and release build' } else { 'release build; GitHub checks are required before live deployment' }
    Write-Host "Running $scope..." -ForegroundColor Cyan

    $workingDirectory = (Get-Location).Path
    if ($Full) {
        Invoke-SchooltoolPhpTestBatches -LogPrefix $temporaryPrefix
        $analysisProcess = Start-SchooltoolCheckProcess `
            -Command 'composer analyse' `
            -OutputPath $analysisOutput `
            -ErrorPath $analysisError `
            -WorkingDirectory $workingDirectory
        Wait-SchooltoolCheckProcess -Name 'Static analysis' -Process $analysisProcess -OutputPath $analysisOutput -ErrorPath $analysisError
    }
    $frontendProcess = Start-SchooltoolCheckProcess `
        -Command $frontendCommand `
        -OutputPath $frontendOutput `
        -ErrorPath $frontendError `
        -WorkingDirectory $workingDirectory
    Wait-SchooltoolCheckProcess -Name 'Frontend tests and release build' -Process $frontendProcess -OutputPath $frontendOutput -ErrorPath $frontendError
    Write-Host "Check logs retained at $temporaryPrefix-*" -ForegroundColor DarkGray
}

function Wait-SchooltoolCheckProcess {
    param([string]$Name, $Process, [string]$OutputPath, [string]$ErrorPath)
    $startedAt = Get-Date
    while (-not $Process.HasExited) {
        $elapsed = [math]::Floor(((Get-Date) - $startedAt).TotalSeconds)
        Write-Host "  $Name is running ($elapsed seconds)..." -ForegroundColor DarkGray
        $null = $Process.WaitForExit(10000)
    }
    $Process.WaitForExit()
    $Process.Refresh()
    if ($Process.ExitCode -ne 0) {
        foreach ($path in @($OutputPath, $ErrorPath)) {
            if (Test-Path -LiteralPath $path) { Get-Content -LiteralPath $path -Encoding UTF8 | Out-Host }
        }
        throw "$Name failed with exit code $($Process.ExitCode). Nothing was pushed. Logs retained at $OutputPath and $ErrorPath."
    }
    Write-Host "  OK: $Name" -ForegroundColor Green
}

function Assert-SchooltoolOwnedTestEnvironment {
    $snapshot = $script:SchooltoolActiveCandidateEnvironment
    $directory = [System.IO.Path]::GetFullPath((Get-Location).Path)
    if (-not $snapshot -or $snapshot.CandidatePath -ne $directory -or -not (Test-Path -LiteralPath (Join-Path $directory '.git') -PathType Leaf)) {
        throw 'PHP release tests require the active isolated candidate worktree.'
    }
    if ($snapshot.CreatedDatabase -cnotmatch '^pest_test_test_[0-9]{24}$' -or $env:DB_DATABASE -cne $snapshot.CreatedDatabase -or
        $env:APP_ENV -cne 'testing' -or $env:DB_CONNECTION -cne 'mysql' -or $env:DB_HOST -cne '127.0.0.1' -or $env:DB_PORT -cne '3306' -or
        $env:DB_USERNAME -cne 'root' -or $env:DB_PASSWORD -cne '(empty)' -or $env:DB_URL -cne '(null)' -or $env:DB_DATABASE_TEST -cne 'pest_test') {
        throw 'PHP release tests require the self-created local database and isolated testing configuration.'
    }
    $expectedCache = 'bootstrap/cache/' + [System.IO.Path]::GetFileNameWithoutExtension($snapshot.ReceiptPath) + '.config.php'
    if ($env:APP_CONFIG_CACHE -cne $expectedCache -or (Test-Path -LiteralPath (Join-Path $directory $expectedCache))) {
        throw 'PHP release tests cannot reuse cached application configuration.'
    }
    $receipt = Get-Content -LiteralPath $snapshot.ReceiptPath -Encoding UTF8 -Raw -ErrorAction Stop | ConvertFrom-Json -ErrorAction Stop
    if ($receipt.format -cne 'schooltool-owned-test-database-v1' -or $receipt.state -cne 'created' -or
        $receipt.database -cne $snapshot.CreatedDatabase -or $receipt.host -cne '127.0.0.1' -or $receipt.port -ne 3306) {
        throw 'PHP release tests require a matching successful local database ownership receipt.'
    }
}

function Start-SchooltoolPhpTestProcess {
    param([string[]]$Files, [string]$OutputPath, [string]$ErrorPath, [string]$WorkingDirectory)
    $php = (Get-Command php -CommandType Application -ErrorAction Stop | Select-Object -First 1).Source
    $arguments = @('artisan', 'test', '--compact', '--exclude-group=integration', '--stop-on-failure', '--stop-on-error') + $Files
    $quotedArguments = @($arguments | ForEach-Object {
        if ($_ -match '["\r\n]') { throw 'Unsupported character in a PHP test path.' }
        '"' + $_ + '"'
    })
    $process = Start-Process -FilePath $php -ArgumentList $quotedArguments -WorkingDirectory $WorkingDirectory -WindowStyle Hidden -PassThru `
        -RedirectStandardOutput $OutputPath -RedirectStandardError $ErrorPath
    $null = $process.Handle
    $process
}

function Invoke-SchooltoolPhpTestBatches {
    param([Parameter(Mandatory = $true)][string]$LogPrefix)
    Assert-SchooltoolOwnedTestEnvironment
    $directory = (Get-Location).Path
    $files = @(Get-ChildItem -LiteralPath (Join-Path $directory 'tests/Unit'), (Join-Path $directory 'tests/Feature') -Recurse -File -Filter '*Test.php' -ErrorAction Stop | ForEach-Object {
        $_.FullName.Substring($directory.Length + 1).Replace('\', '/')
    })
    if ($files.Count -eq 0) { throw 'No Unit or Feature test files were found. The release cannot be checked.' }
    [Array]::Sort($files, [StringComparer]::Ordinal)
    $batchSize = 10
    $batchCount = [int][math]::Ceiling($files.Count / $batchSize)
    Write-Host "Checking all $($files.Count) PHP test files in $batchCount sequential fresh processes..." -ForegroundColor Cyan
    for ($offset = 0; $offset -lt $files.Count; $offset += $batchSize) {
        Assert-SchooltoolOwnedTestEnvironment
        $batchNumber = [int]($offset / $batchSize) + 1
        $last = [math]::Min($offset + $batchSize - 1, $files.Count - 1)
        $batchFiles = @($files[$offset..$last])
        $outputPath = "$LogPrefix-php-$batchNumber.out"
        $errorPath = "$LogPrefix-php-$batchNumber.err"
        [System.IO.File]::WriteAllLines("$LogPrefix-php-$batchNumber.files.txt", $batchFiles, (New-Object System.Text.UTF8Encoding($false)))
        $process = Start-SchooltoolPhpTestProcess -Files $batchFiles -OutputPath $outputPath -ErrorPath $errorPath -WorkingDirectory $directory
        Wait-SchooltoolCheckProcess -Name "PHP test batch $batchNumber/$batchCount" -Process $process -OutputPath $outputPath -ErrorPath $errorPath
    }
}

function Wait-SchooltoolCi {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Commit
    )

    if (-not (Get-Command gh -ErrorAction SilentlyContinue)) {
        Write-Host 'GitHub CLI is unavailable; CI continues in GitHub Actions.' -ForegroundColor Yellow
        return
    }

    Write-Host 'Waiting for GitHub CI...' -ForegroundColor Cyan
    $run = $null

    for ($attempt = 1; $attempt -le 30; $attempt++) {
        $json = gh run list --repo ITStudioAT/schooltool --workflow ci.yml --branch main --commit $Commit --event push --limit 1 --json databaseId,url,status,conclusion

        if ($LASTEXITCODE -eq 0 -and $json) {
            $run = $json | ConvertFrom-Json | Select-Object -First 1
        }

        if ($run) {
            break
        }

        Start-Sleep -Seconds 2
    }

    if (-not $run) {
        Write-Host 'The release is pushed, but its GitHub run was not found yet.' -ForegroundColor Yellow
        return
    }

    gh run watch $run.databaseId --repo ITStudioAT/schooltool --exit-status --interval 10

    if ($LASTEXITCODE -ne 0) {
        throw "GitHub CI failed: $($run.url)"
    }

    $proof = Assert-SchooltoolCiRelease -Commit $Commit
    Write-Host "GitHub CI verified the exact release: $($proof.url)" -ForegroundColor Green
}

function Write-SchooltoolCompletionTime {
    $viennaTimeZone = [TimeZoneInfo]::FindSystemTimeZoneById('W. Europe Standard Time')
    $finishedAt = [TimeZoneInfo]::ConvertTime([DateTimeOffset]::UtcNow, $viennaTimeZone)
    Write-Host ("Abgeschlossen: {0} (Europe/Vienna)" -f $finishedAt.ToString('dd.MM.yyyy HH:mm:ss zzz')) -ForegroundColor Green
}

function gitpull {
    git pull @args
    if ($LASTEXITCODE -ne 0) {
        throw "git pull failed with exit code $LASTEXITCODE."
    }
    $viennaTimeZone = [TimeZoneInfo]::FindSystemTimeZoneById('W. Europe Standard Time')
    $finishedAt = [TimeZoneInfo]::ConvertTime([DateTimeOffset]::UtcNow, $viennaTimeZone)
    Write-Host ("Abgeschlossen: {0} (Europe/Vienna)" -f $finishedAt.ToString('dd.MM.yyyy HH:mm:ss zzz')) -ForegroundColor Green
}

function Invoke-SchooltoolPublish {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)]
        [string]$message,

        [Parameter(Mandatory = $false)]
        [string]$version,

        [Parameter(Mandatory = $false)]
        [switch]$WaitForCI,

        [Parameter(Mandatory = $false)]
        [switch]$Full,

        [string]$ExpectedMainCommit,
        $Feature,
        [string]$ExpectedFeatureCommit,
        [object]$Candidate
    )

    try {
        Assert-SchooltoolRepository -Candidate $Candidate
        $branch = Invoke-SchooltoolGit branch --show-current

        if ($LASTEXITCODE -ne 0 -or (-not $ExpectedMainCommit -and $branch -ne 'main')) {
            throw "Saving a release requires main. Current branch: $branch"
        }

        if ($ExpectedMainCommit) {
            if (-not $Candidate -or $Candidate.Kind -cne 'release' -or -not $Full -or -not $Feature -or -not $ExpectedFeatureCommit) {
                throw 'Feature releases require an isolated detached release candidate and full checks.'
            }
            Assert-SchooltoolClean
            Assert-SchooltoolFeatureSnapshot -Feature $Feature -FeatureCommit $ExpectedFeatureCommit -MainCommit $ExpectedMainCommit
        }
        else {
            Update-SchooltoolRemote
        }
        $mainBeforeChecks = Invoke-SchooltoolGit rev-parse refs/remotes/origin/main
        if (-not (Test-SchooltoolAncestor $mainBeforeChecks HEAD)) {
            throw 'main contains remote changes missing locally. Use gitmain before editing, or resolve divergent commits explicitly. Nothing was merged.'
        }
        if ($version) { Assert-SchooltoolVersion -Version $version -AllowRetryCommit (Invoke-SchooltoolGit rev-parse HEAD) }
        Invoke-SchooltoolCommand 'Preparing local dependencies...' {
            php scripts/update.php --target=local --prepare
        }

        if ($version) {
            Invoke-SchooltoolCommand 'Updating the changelog from UPDATES.md...' {
                node scripts/update-changelog.mjs $version
            }
        }

        Invoke-SchooltoolCommand 'Formatting changed PHP files...' {
            php vendor/bin/pint --dirty --format agent
        }

        Invoke-SchooltoolCommand 'Checking UTF-8 source files...' {
            php scripts/check-encoding.php
        }
        $checkedHead = Invoke-SchooltoolGit rev-parse HEAD
        $checkedTree = Get-SchooltoolSourceTree

        $sourceChanges = git status --porcelain --untracked-files=all | Where-Object {
            $_ -notmatch '^.. deployment/(frontend-build\.sha256|frontend-build\.tar\.gz|source-commit|source-manifest\.sha256)$'
        }
        $localHead = git rev-parse HEAD
        $remoteHead = git rev-parse origin/main
        $sourceCommit = $null
        $releaseCommit = $null

        if (-not $sourceChanges) {
            if ($localHead -eq $remoteHead -and -not $version) {
                Write-Host 'No source changes to publish.' -ForegroundColor Yellow
                Write-SchooltoolCompletionTime
                return
            }

            git merge-base --is-ancestor $remoteHead $localHead
            if ($LASTEXITCODE -ne 0) {
                throw 'Local main does not continue origin/main. Synchronize the branch before publishing.'
            }

            $parentCommit = git rev-parse "$localHead^"
            $releaseFilesExist =
                (Test-Path -LiteralPath 'deployment/frontend-build.sha256') -and
                (Test-Path -LiteralPath 'deployment/frontend-build.tar.gz') -and
                (Test-Path -LiteralPath 'deployment/source-commit') -and
                (Test-Path -LiteralPath 'deployment/source-manifest.sha256')

            if ($releaseFilesExist) {
                & php scripts/frontend-release.php verify $parentCommit *> $null

                if ($LASTEXITCODE -eq 0) {
                    $releaseCommit = $localHead
                    Write-Host "Resuming the completed local release $releaseCommit." -ForegroundColor Cyan
                }
            }

            if (-not $releaseCommit) {
                $sourceCommit = $localHead
                Write-Host "Completing the unpushed source commit $sourceCommit." -ForegroundColor Cyan
            }
        }

        if ($releaseCommit -and $Full) {
            Invoke-SchooltoolReleaseChecks -Full
            Assert-SchooltoolCheckedSource -Branch $branch -Head $checkedHead -Tree $checkedTree
            Invoke-SchooltoolCommand 'Verifying the existing release after full checks...' {
                php scripts/frontend-release.php verify $parentCommit
            }
        }

        if (-not $releaseCommit) {
            Invoke-SchooltoolReleaseChecks -Full:$Full
            Assert-SchooltoolCheckedSource -Branch $branch -Head $checkedHead -Tree $checkedTree

            $sourceChanges = git status --porcelain --untracked-files=all | Where-Object {
                $_ -notmatch '^.. deployment/(frontend-build\.sha256|frontend-build\.tar\.gz|source-commit|source-manifest\.sha256)$'
            }

            if ($sourceChanges) {
                foreach ($releasePath in @(
                    'deployment/frontend-build.sha256',
                    'deployment/frontend-build.tar.gz',
                    'deployment/source-commit',
                    'deployment/source-manifest.sha256'
                )) {
                    if (Test-Path -LiteralPath $releasePath) {
                        [System.IO.File]::Delete((Resolve-Path -LiteralPath $releasePath).Path)
                    }
                }

                git add -A
                if ($LASTEXITCODE -ne 0) {
                    throw 'Could not stage the source changes.'
                }

                git commit -m $message
                if ($LASTEXITCODE -ne 0) {
                    throw 'Could not create the source commit.'
                }

                $postCommitChanges = git status --porcelain --untracked-files=all | Where-Object {
                    $_ -notmatch '^.. deployment/(frontend-build\.sha256|frontend-build\.tar\.gz|source-commit|source-manifest\.sha256)$'
                }

                if ($postCommitChanges) {
                    throw 'The source commit left additional changes in the worktree. Review them before publishing.'
                }

                $sourceCommit = git rev-parse HEAD
                Write-Host "Source commit: $sourceCommit" -ForegroundColor Cyan
            }

            if (-not $sourceCommit) {
                $sourceCommit = git rev-parse HEAD
            }

            Invoke-SchooltoolCommand 'Creating the commit-bound frontend release...' {
                php scripts/frontend-release.php create $sourceCommit
            }

            Invoke-SchooltoolCommand 'Verifying the commit-bound frontend release...' {
                php scripts/frontend-release.php verify $sourceCommit
            }

            git add -f deployment/frontend-build.sha256 deployment/frontend-build.tar.gz deployment/source-commit deployment/source-manifest.sha256
            if ($LASTEXITCODE -ne 0) {
                throw 'Could not stage the deployment release.'
            }

            git commit -m "Build deployment release for $sourceCommit"
            if ($LASTEXITCODE -ne 0) {
                throw 'Could not create the deployment release commit.'
            }

            $releaseCommit = git rev-parse HEAD
        }

        if ($ExpectedMainCommit) {
            Save-SchooltoolCandidate $Candidate
            Assert-SchooltoolClean
            $currentBranch = Invoke-SchooltoolGit branch --show-current
            if ($currentBranch -ne $branch) {
                throw 'The active branch changed during release checks. Nothing was pushed.'
            }
            Assert-SchooltoolFeatureSnapshot -Feature $Feature -FeatureCommit $ExpectedFeatureCommit -MainCommit $ExpectedMainCommit
            Write-Host "Ready to publish $releaseCommit to main." -ForegroundColor Cyan
            Invoke-SchooltoolGit diff --stat $ExpectedMainCommit $releaseCommit
            $versionLabel = if ($version) { "v$version" } else { 'without changing the version' }
            $confirmation = Read-Host "Publish $versionLabel? Type RELEASE to confirm"
            if ($confirmation -cne 'RELEASE') {
                throw 'Release cancelled. Nothing was pushed.'
            }
        }

        Assert-SchooltoolClean
        if ((Invoke-SchooltoolGit branch --show-current) -cne $branch -or (Invoke-SchooltoolGit rev-parse HEAD) -ne $releaseCommit) {
            throw 'The checked release changed before publication. Nothing was pushed.'
        }
        if ($Candidate) { Assert-SchooltoolCandidate $Candidate }
        if (-not (Test-SchooltoolAncestor $mainBeforeChecks $releaseCommit)) {
            throw 'Release publication can never rewrite main history.'
        }
        $pushArguments = @('--atomic', "--force-with-lease=refs/heads/main:$mainBeforeChecks")
        if ($Feature) {
            if (-not (Test-SchooltoolAncestor $ExpectedFeatureCommit $releaseCommit)) { throw 'The feature is not fully included in the release.' }
            $pushArguments += @(
                "--force-with-lease=refs/heads/$($Feature.Branch):$ExpectedFeatureCommit",
                "--force-with-lease=refs/heads/codex/active-feature:$($Feature.ReservationCommit)"
            )
        }
        $pushArguments += @('origin', "${releaseCommit}:refs/heads/main")
        if ($Feature) {
            $pushArguments += @(':refs/heads/' + $Feature.Branch)
            $pushArguments += ':refs/heads/codex/active-feature'
        }

        if ($version) {
            $tag = "v$version"

            $existingTagCommit = $null
            if (Test-SchooltoolRef "refs/tags/$tag") {
                $existingTagCommit = Invoke-SchooltoolGit rev-list -n 1 $tag
            }

            if ($existingTagCommit) {
                if ($existingTagCommit -ne $releaseCommit) {
                    throw "Tag $tag already belongs to another commit."
                }

                Write-Host "Reusing local tag $tag." -ForegroundColor Cyan
            }
            else {
                git tag -a $tag -m ("Version {0}: {1}" -f $version, $message)
                if ($LASTEXITCODE -ne 0) {
                    throw "Could not create tag $tag."
                }
            }

            $pushArguments += "refs/tags/$tag"
        }

        Write-Host 'Pushing the complete release to main...' -ForegroundColor Cyan
        git push @pushArguments
        if ($LASTEXITCODE -ne 0) {
            throw 'The atomic release push failed. Your local commits and candidate are preserved. Check GitHub before retrying if the connection was interrupted.'
        }

        if ($WaitForCI) {
            Wait-SchooltoolCi -Commit $releaseCommit
        }

        Write-Host ''
        Write-Host 'SAVED ON GITHUB.' -ForegroundColor Green
        Write-Host 'Windows PCs may pull main and run: composer deploy' -ForegroundColor Green
        Write-Host 'Use gitdeploy for live publication; it requires successful CI for this exact release.' -ForegroundColor Cyan

        if (-not $WaitForCI) {
            Write-Host 'GitHub selects the required checks automatically. Application changes require the full background checks; only verified documentation/version changes use the fast checks.' -ForegroundColor DarkGray
            Write-Host 'CI pending is not live-ready. gitdeploy waits for running checks with progress, then asks for LIVE. Failed or untrusted checks block deployment.' -ForegroundColor Yellow
        }
        Write-SchooltoolCompletionTime
    }
    catch {
        Write-Host $_.Exception.Message -ForegroundColor Red
        throw
    }
}

. (Join-Path $PSScriptRoot 'git_branch_helpers.ps1')
. (Join-Path $PSScriptRoot 'git_preview_helpers.ps1')

function gitpush {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)][ValidateNotNullOrEmpty()][string]$message,
        [string]$version,
        [switch]$WaitForCI,
        [switch]$Full
    )
    Invoke-SchooltoolPublish @PSBoundParameters
}
