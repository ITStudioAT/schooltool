[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('gitstart', 'gitwork', 'gitmain', 'gitsave', 'gitupdate', 'gitrelease', 'gitdiscard', 'gitcheck', 'gitpreview', 'gitdeploy')]
    [string]$Command,

    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$CommandArguments
)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'git_helpers.ps1')
$parameters = @{}
$arguments = @($CommandArguments | Where-Object { $null -ne $_ })
switch ($Command) {
    { $_ -in @('gitstart', 'gitdiscard') } {
        if ($arguments.Count -ne 1) { throw "Usage: $Command NAME" }
        $parameters.Name = $arguments[0]
    }
    'gitwork' {
        if ($arguments.Count -gt 1) { throw 'Usage: gitwork [NAME]' }
        if ($arguments.Count -eq 1) { $parameters.Name = $arguments[0] }
    }
    { $_ -in @('gitsave', 'gitrelease') } {
        $fullArguments = @($arguments | Where-Object { $_ -ceq '-Full' })
        if ($fullArguments.Count -gt 1 -or ($fullArguments.Count -gt 0 -and $Command -cne 'gitsave')) { throw "Usage: $Command DESCRIPTION [VERSION]" }
        if ($fullArguments.Count -eq 1) { $parameters.Full = $true }
        $positionals = @($arguments | Where-Object { $_ -cne '-Full' })
        if ($positionals.Count -lt 1 -or $positionals.Count -gt 2) { throw "Usage: $Command DESCRIPTION [VERSION]" }
        $parameters.Message = $positionals[0]
        if ($positionals.Count -eq 2) { $parameters.Version = $positionals[1] }
    }
    'gitpreview' {
        $expectFeature = $false
        foreach ($argument in $arguments) {
            if ($expectFeature) {
                $parameters.FeatureName = Get-SchooltoolFeatureBranch $argument
                $expectFeature = $false
            }
            elseif ($argument -ceq '-Feature' -and -not $parameters.ContainsKey('FeatureName')) {
                $expectFeature = $true
            }
            elseif ($argument -eq '-RefreshData' -and -not $parameters.ContainsKey('RefreshData')) {
                $parameters.RefreshData = $true
            }
            elseif ($argument -in @('deploy', 'prepare', 'resume') -and -not $parameters.ContainsKey('Mode')) {
                $parameters.Mode = $argument
            }
            elseif ($parameters.Mode -eq 'resume' -and $argument -cmatch '^[a-f0-9]{32}$' -and -not $parameters.ContainsKey('BundleId')) {
                $parameters.BundleId = $argument
            }
            else { throw 'Usage: gitpreview [deploy|prepare] or gitpreview resume BUNDLE_ID [-RefreshData]' }
        }
        if ($expectFeature) { throw 'Usage: gitpreview [deploy|prepare|resume BUNDLE_ID] [-Feature NAME] [-RefreshData]' }
        if ($parameters.Mode -eq 'resume' -and -not $parameters.ContainsKey('BundleId')) { throw 'Usage: gitpreview resume BUNDLE_ID [-RefreshData]' }
    }
    default {
        if ($arguments.Count -gt 0) { throw "Usage: $Command" }
    }
}
& $Command @parameters
