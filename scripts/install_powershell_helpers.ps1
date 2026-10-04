param(
    [string]$DocumentsDirectory = [Environment]::GetFolderPath('MyDocuments'),
    [string]$WorkflowDirectory
)

$ErrorActionPreference = 'Stop'
$workflowOverride = ''
$installedCommon = git -C $PSScriptRoot rev-parse --path-format=absolute --git-common-dir
if ($LASTEXITCODE -ne 0 -or [IO.Path]::GetFileName($installedCommon.Trim()) -cne '.git') { throw 'Install the development helpers from an ordinary Schooltool checkout.' }
$workflowOverrideRoot = Split-Path -Parent $installedCommon.Trim()
if ($WorkflowDirectory) {
    $workflowOverride = (Resolve-Path -LiteralPath $WorkflowDirectory).Path
    if (-not (Test-Path -LiteralPath (Join-Path $workflowOverride 'git_workflow.ps1') -PathType Leaf)) { throw 'The workflow directory must contain git_workflow.ps1.' }
    $commonDirectory = git -C $workflowOverride rev-parse --path-format=absolute --git-common-dir
    if ($LASTEXITCODE -ne 0 -or [System.IO.Path]::GetFileName($commonDirectory.Trim()) -cne '.git') { throw 'The workflow directory must belong to an ordinary Schooltool checkout.' }
    $workflowOverrideRoot = Split-Path -Parent $commonDirectory.Trim()
}
$workflowOverrideLiteral = $workflowOverride.Replace("'", "''")
$workflowOverrideRootLiteral = $workflowOverrideRoot.Replace("'", "''")
$profilePaths = @(
    $PROFILE.CurrentUserCurrentHost
    (Join-Path $DocumentsDirectory 'WindowsPowerShell/Microsoft.PowerShell_profile.ps1')
    (Join-Path $DocumentsDirectory 'PowerShell/Microsoft.PowerShell_profile.ps1')
    foreach ($edition in @('WindowsPowerShell', 'PowerShell')) {
        $hostProfile = Join-Path $DocumentsDirectory "$edition/Microsoft.VSCode_profile.ps1"
        if (Test-Path -LiteralPath $hostProfile -PathType Leaf) { $hostProfile }
    }
) | Select-Object -Unique
$legacyStartMarker = '# >>> schooltool managed helpers >>>'
$legacyEndMarker = '# <<< schooltool managed helpers <<<'
$startMarker = '# >>> project git dispatcher >>>'
$endMarker = '# <<< project git dispatcher <<<'
$managedBlock = @"
$startMarker
function composer {
    `$isDev = `$args.Count -gt 0 -and (`$args[0] -ceq 'dev' -or (`$args.Count -gt 1 -and `$args[0] -cin @('run', 'run-script') -and `$args[1] -ceq 'dev'))
    `$localStarter = '$workflowOverrideLiteral'
    if (`$isDev) {
        `$root = git rev-parse --show-toplevel 2>`$null
        if (`$LASTEXITCODE -eq 0 -and `$root) {
            `$common = git rev-parse --path-format=absolute --git-common-dir 2>`$null
            if (`$LASTEXITCODE -eq 0 -and (Split-Path -Parent `$common.Trim()) -ieq '$workflowOverrideRootLiteral') {
                if (-not `$localStarter) { `$localStarter = Join-Path (Split-Path -Parent `$common.Trim()) 'scripts' }
                `$remoteUrls = @(git remote get-url origin 2>`$null) + @(git remote get-url --all --push origin 2>`$null)
                if (`$LASTEXITCODE -ne 0 -or `$remoteUrls.Count -ne 2 -or @(`$remoteUrls | Where-Object { `$_.Trim() -notmatch '^(?:https://github\.com/|git@github\.com:|ssh://git@github\.com/)ITStudioAT/schooltool(?:\.git)?/?$' }).Count -ne 0) { throw 'Local development requires the trusted Schooltool repository.' }
                `$localCommon = git -C `$localStarter rev-parse --path-format=absolute --git-common-dir 2>`$null
                if (`$LASTEXITCODE -ne 0 -or `$localCommon.Trim() -ine `$common.Trim()) { throw 'The local development starter belongs to another repository.' }
                `$remaining = if (`$args[0] -ceq 'dev') { @(`$args | Select-Object -Skip 1) } else { @(`$args | Select-Object -Skip 2) }
                & node (Join-Path `$localStarter 'local-dev.mjs') --project `$root.Trim() @remaining
                return
            }
        }
    }
    `$nativeComposer = Get-Command composer -CommandType Application -ErrorAction Stop | Select-Object -First 1
    & `$nativeComposer.Source @args
}
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
function Show-ProjectGitWorkspace {
    param([string]`$Directory)
    if (`$env:TERM_PROGRAM -cne 'vscode') { return }
    `$editor = Get-Command code -CommandType Application -ErrorAction SilentlyContinue | Select-Object -First 1
    if (-not `$editor) {
        Write-Warning "Working folder selected: `$Directory. VS Code CLI is unavailable; Git/dev selection succeeded."
        return
    }
    try {
        & `$editor.Source --add `$Directory
        if (`$LASTEXITCODE -ne 0) { throw 'VS Code could not add the working folder.' }
        `$entry = Join-Path `$Directory 'composer.json'
        if (Test-Path -LiteralPath `$entry -PathType Leaf) {
            & `$editor.Source --reuse-window --goto `$entry
            if (`$LASTEXITCODE -ne 0) { throw 'VS Code could not show the selected checkout.' }
        }
        Write-Host "Editor working folder: `$Directory" -ForegroundColor Cyan
    }
    catch { Write-Warning "Git/dev selection succeeded. `$(`$_.Exception.Message) Working folder: `$Directory" }
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
    `$commonDirectory = git -C `$repositoryRoot rev-parse --path-format=absolute --git-common-dir 2>`$null
    if (`$LASTEXITCODE -ne 0 -or -not `$commonDirectory -or [System.IO.Path]::GetFileName(`$commonDirectory.Trim()) -cne '.git') {
        throw 'Cannot identify the shared main workflow helpers.'
    }
    `$workflowRoot = Split-Path -Parent `$commonDirectory.Trim()
    `$workflow = Join-Path `$workflowRoot 'scripts/git_workflow.ps1'
    `$localWorkflowDirectory = '$workflowOverrideLiteral'
    if (`$localWorkflowDirectory -and `$workflowRoot -ieq '$workflowOverrideRootLiteral') {
        `$localCommonDirectory = git -C `$localWorkflowDirectory rev-parse --path-format=absolute --git-common-dir 2>`$null
        if (`$LASTEXITCODE -ne 0 -or `$localCommonDirectory.Trim() -ine `$commonDirectory.Trim()) { throw 'The installed local workflow no longer belongs to this repository.' }
        `$workflow = Join-Path `$localWorkflowDirectory 'git_workflow.ps1'
    }
    if (-not (Test-Path -LiteralPath `$workflow -PathType Leaf)) {
        throw 'This branch does not contain the workflow helpers yet. Update main and incorporate it into the feature.'
    }
    Push-Location -LiteralPath `$repositoryRoot
    `$completed = `$false
    `$selectedLocation = `$null
    try {
        & `$workflow -Command `$Command -CommandArguments `$CommandArguments
        `$selectedLocation = (Get-Location).Path
        `$completed = `$true
    }
    finally {
        Pop-Location
        if (`$completed -and `$Command -cin @('gitstart', 'gitwork', 'gitmain', 'gitrelease')) {
            Set-Location -LiteralPath `$selectedLocation
            if (`$Command -cin @('gitwork', 'gitmain')) { Show-ProjectGitWorkspace `$selectedLocation }
        }
    }
}
function gitstart { Invoke-ProjectGitWorkflow 'gitstart' `$args }
function gitwork { Invoke-ProjectGitWorkflow 'gitwork' `$args }
function gitmain { Invoke-ProjectGitWorkflow 'gitmain' `$args }
function gitpull { Invoke-ProjectGitWorkflow 'gitpull' `$args }
function gitsave { Invoke-ProjectGitWorkflow 'gitsave' `$args }
function gitupdate { Invoke-ProjectGitWorkflow 'gitupdate' `$args }
function gitrelease { Invoke-ProjectGitWorkflow 'gitrelease' `$args }
function gitdiscard { Invoke-ProjectGitWorkflow 'gitdiscard' `$args }
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

Write-Host 'Open a new PowerShell terminal to use gitstart, gitwork, gitmain, gitsave, gitupdate, gitrelease, gitdiscard, gitcheck, gitpreview and gitdeploy.' -ForegroundColor Cyan
Write-Host 'Or reload the profile in your current terminal with: . $PROFILE' -ForegroundColor Cyan
