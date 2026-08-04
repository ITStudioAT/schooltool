$profilePath = $PROFILE.CurrentUserCurrentHost
$profileDirectory = Split-Path -Parent $profilePath
$helpersPath = Join-Path $PSScriptRoot 'git_helpers.ps1'
$startMarker = '# >>> schooltool managed helpers >>>'
$endMarker = '# <<< schooltool managed helpers <<<'
$managedBlock = @"
$startMarker
. '$($helpersPath.Replace("'", "''"))'
$endMarker
"@

if (-not (Test-Path -LiteralPath $profileDirectory)) {
    [System.IO.Directory]::CreateDirectory($profileDirectory) | Out-Null
}

$profileContent = if (Test-Path -LiteralPath $profilePath) {
    [System.IO.File]::ReadAllText($profilePath)
}
else {
    ''
}

$pattern = [regex]::Escape($startMarker) + '.*?' + [regex]::Escape($endMarker)
$profileContent = [regex]::Replace(
    $profileContent,
    $pattern,
    '',
    [System.Text.RegularExpressions.RegexOptions]::Singleline
).TrimEnd()

if ($profileContent) {
    $profileContent += [Environment]::NewLine + [Environment]::NewLine
}

$profileContent += $managedBlock + [Environment]::NewLine
$utf8WithoutBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($profilePath, $profileContent, $utf8WithoutBom)

git config core.hooksPath .githooks
if ($LASTEXITCODE -ne 0) {
    throw 'Could not configure the repository hooks path.'
}

Write-Host "Schooltool PowerShell helpers installed in $profilePath" -ForegroundColor Green
Write-Host 'Open a new PowerShell terminal before using gitpush.' -ForegroundColor Cyan
