param(
    [string]$DocumentsDirectory = [Environment]::GetFolderPath('MyDocuments')
)

$ErrorActionPreference = 'Stop'
$profilePaths = @(
    $PROFILE.CurrentUserCurrentHost
    (Join-Path $DocumentsDirectory 'WindowsPowerShell/Microsoft.PowerShell_profile.ps1')
    (Join-Path $DocumentsDirectory 'PowerShell/Microsoft.PowerShell_profile.ps1')
) | Select-Object -Unique
$legacyStartMarker = '# >>> schooltool managed helpers >>>'
$legacyEndMarker = '# <<< schooltool managed helpers <<<'
$startMarker = '# >>> project git dispatcher >>>'
$endMarker = '# <<< project git dispatcher <<<'
$managedBlock = @"
$startMarker
function gitpush {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = `$true)]
        [string]`$message,

        [Parameter(Mandatory = `$false)]
        [string]`$version,

        [Parameter(Mandatory = `$false)]
        [switch]`$WaitForCI,

        [Parameter(Mandatory = `$false)]
        [switch]`$Full
    )

    `$repositoryRoot = git rev-parse --show-toplevel 2>`$null

    if (`$LASTEXITCODE -ne 0 -or -not `$repositoryRoot) {
        throw 'gitpush must be run inside a Git repository.'
    }

    `$repositoryRoot = `$repositoryRoot.Trim()
    `$remoteUrl = git -C `$repositoryRoot remote get-url origin 2>`$null

    if (`$LASTEXITCODE -ne 0 -or -not `$remoteUrl) {
        throw 'gitpush requires an origin remote.'
    }

    `$remoteUrl = `$remoteUrl.Trim()
    `$trustedRemotePattern = '^(?:https://github\.com/|git@github\.com:|ssh://git@github\.com/)(?<repository>ITStudioAT/(?:schooltool|stocks))(?:\.git)?/?$'
    `$remoteMatch = [regex]::Match(`$remoteUrl, `$trustedRemotePattern, [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)

    if (-not `$remoteMatch.Success) {
        throw "gitpush does not trust the origin remote: `$remoteUrl"
    }

    `$pushUrls = @(git -C `$repositoryRoot remote get-url --all --push origin 2>`$null)

    if (`$LASTEXITCODE -ne 0 -or `$pushUrls.Count -eq 0) {
        throw 'gitpush requires an origin push URL.'
    }

    foreach (`$pushUrl in `$pushUrls) {
        `$pushUrl = `$pushUrl.Trim()
        `$pushMatch = [regex]::Match(`$pushUrl, `$trustedRemotePattern, [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)

        if (-not `$pushMatch.Success) {
            throw "gitpush does not trust the origin push URL: `$pushUrl"
        }
    }

    `$projectGitPush = Join-Path `$repositoryRoot 'scripts/gitpush.ps1'

    if (-not (Test-Path -LiteralPath `$projectGitPush -PathType Leaf)) {
        throw "The trusted repository does not provide scripts/gitpush.ps1: `$repositoryRoot"
    }

    Push-Location -LiteralPath `$repositoryRoot

    try {
        & `$projectGitPush @PSBoundParameters
    }
    finally {
        Pop-Location
    }
}
function gitpull {
    git pull @args
    if (`$LASTEXITCODE -ne 0) {
        throw "git pull failed with exit code `$LASTEXITCODE."
    }
    `$viennaTimeZone = [TimeZoneInfo]::FindSystemTimeZoneById('W. Europe Standard Time')
    `$finishedAt = [TimeZoneInfo]::ConvertTime([DateTimeOffset]::UtcNow, `$viennaTimeZone)
    Write-Host ("Abgeschlossen: {0} (Europe/Vienna)" -f `$finishedAt.ToString('dd.MM.yyyy HH:mm:ss zzz')) -ForegroundColor Green
}

function Invoke-ProjectGitWorkflow {
    param([string]`$Command, [string[]]`$CommandArguments)
    `$repositoryRoot = git rev-parse --show-toplevel 2>`$null
    if (`$LASTEXITCODE -ne 0 -or -not `$repositoryRoot) {
        throw 'Run the workflow command inside the schooltool repository.'
    }
    `$repositoryRoot = `$repositoryRoot.Trim()
    `$remoteUrls = @(git -C `$repositoryRoot remote get-url origin 2>`$null)
    if (`$LASTEXITCODE -ne 0 -or `$remoteUrls.Count -ne 1) {
        throw 'The workflow requires an origin remote.'
    }
    `$pushUrls = @(git -C `$repositoryRoot remote get-url --all --push origin 2>`$null)
    if (`$LASTEXITCODE -ne 0 -or `$pushUrls.Count -ne 1) {
        throw 'The workflow requires exactly one origin push URL.'
    }
    foreach (`$url in @(`$remoteUrls + `$pushUrls)) {
        if (`$url.Trim() -notmatch '^(?:https://github\.com/|git@github\.com:|ssh://git@github\.com/)ITStudioAT/schooltool(?:\.git)?/?$') {
            throw 'The branch workflow only trusts ITStudioAT/schooltool on GitHub.'
        }
    }
    `$workflow = Join-Path `$repositoryRoot 'scripts/git_workflow.ps1'
    if (-not (Test-Path -LiteralPath `$workflow -PathType Leaf)) {
        throw 'This branch does not contain the workflow helpers yet. Update main and incorporate it into the feature.'
    }
    Push-Location -LiteralPath `$repositoryRoot
    try {
        & `$workflow -Command `$Command -CommandArguments `$CommandArguments
    }
    finally {
        Pop-Location
    }
}
function gitstart { Invoke-ProjectGitWorkflow 'gitstart' `$args }
function gitwork { Invoke-ProjectGitWorkflow 'gitwork' `$args }
function gitmain { Invoke-ProjectGitWorkflow 'gitmain' `$args }
function gitsave { Invoke-ProjectGitWorkflow 'gitsave' `$args }
function gitupdate { Invoke-ProjectGitWorkflow 'gitupdate' `$args }
function gitrelease { Invoke-ProjectGitWorkflow 'gitrelease' `$args }
function gitcheck { Invoke-ProjectGitWorkflow 'gitcheck' `$args }
function gitpreview { Invoke-ProjectGitWorkflow 'gitpreview' `$args }
function gitdeploy { Invoke-ProjectGitWorkflow 'gitdeploy' `$args }

$endMarker
"@

foreach ($profilePath in $profilePaths) {
    $profileDirectory = Split-Path -Parent $profilePath
    if (-not (Test-Path -LiteralPath $profileDirectory)) {
        [System.IO.Directory]::CreateDirectory($profileDirectory) | Out-Null
    }

    $profileContent = if (Test-Path -LiteralPath $profilePath) {
        [System.IO.File]::ReadAllText($profilePath)
    }
    else {
        ''
    }

    foreach ($markers in @(
        @($legacyStartMarker, $legacyEndMarker),
        @($startMarker, $endMarker)
    )) {
        $pattern = [regex]::Escape($markers[0]) + '.*?' + [regex]::Escape($markers[1])
        $profileContent = [regex]::Replace(
            $profileContent,
            $pattern,
            '',
            [System.Text.RegularExpressions.RegexOptions]::Singleline
        ).TrimEnd()
    }

    if ($profileContent) {
        $profileContent += [Environment]::NewLine + [Environment]::NewLine
    }

    $profileContent += $managedBlock + [Environment]::NewLine
    $windowsPowerShellUtf8 = New-Object System.Text.UTF8Encoding($true)
    [System.IO.File]::WriteAllText($profilePath, $profileContent, $windowsPowerShellUtf8)
    Write-Host "Project-aware Git helpers installed in $profilePath" -ForegroundColor Green
}

git config core.hooksPath .githooks
if ($LASTEXITCODE -ne 0) {
    throw 'Could not configure the repository hooks path.'
}

Write-Host 'Open a new PowerShell terminal to use gitstart, gitwork, gitmain, gitsave, gitupdate, gitrelease, gitcheck, gitpreview and gitdeploy.' -ForegroundColor Cyan
Write-Host 'Or reload the profile in your current terminal with: . $PROFILE' -ForegroundColor Cyan
