param([Parameter(Mandatory = $true)][string]$Request)
$ErrorActionPreference = 'Stop'
if ([Environment]::OSVersion.Platform -ne [PlatformID]::Win32NT -or $Request -cnotmatch '^[A-Za-z0-9+/]+={0,2}$') {
    throw 'Restaurant live reads require the local Windows workstation and an encoded request.'
}
$stage = 'configuration'
try {
    . (Join-Path $PSScriptRoot 'git_ssh_helpers.ps1')
    $target = Get-SchooltoolDeploymentTarget -Site MAIN
    # The export travels in the guarded command; SSH never needs the HTTP request's stdin.
    $target.Options = @($target.Options) + @('-n')
    $stage = 'program'
    $program = Get-Content -LiteralPath (Join-Path $PSScriptRoot 'restaurant-live-export.php') -Encoding UTF8 -Raw
    $program = $program.Replace('__RESTAURANT_REQUEST__', $Request)
    $encoded = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($program))
    $stage = 'ssh'
    Invoke-SchooltoolRemote -Target $target -Command ('printf %s ''' + $encoded + ''' | base64 --decode | php -d display_errors=0 -d log_errors=0')
}
catch {
    [Console]::Error.WriteLine("Restaurant local read failed at $stage.")
    exit 1
}
