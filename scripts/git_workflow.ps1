[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('gitstart', 'gitwork', 'gitmain', 'gitsave', 'gitupdate', 'gitrelease', 'gitcheck', 'gitpreview', 'gitdeploy')]
    [string]$Command,

    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$CommandArguments
)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'git_helpers.ps1')
$parameters = @{}
$arguments = @($CommandArguments | Where-Object { $null -ne $_ })
switch ($Command) {
    'gitstart' {
        if ($arguments.Count -ne 1) { throw 'Usage: gitstart NAME' }
        $parameters.Name = $arguments[0]
    }
    'gitwork' {
        if ($arguments.Count -gt 1) { throw 'Usage: gitwork' }
        if ($arguments.Count -eq 1) { $parameters.Name = $arguments[0] }
    }
    { $_ -in @('gitsave', 'gitrelease') } {
        if ($arguments.Count -lt 1 -or $arguments.Count -gt 2) { throw "Usage: $Command DESCRIPTION [VERSION]" }
        $parameters.Message = $arguments[0]
        if ($arguments.Count -eq 2) { $parameters.Version = $arguments[1] }
    }
    'gitpreview' {
        foreach ($argument in $arguments) {
            if ($argument -eq '-RefreshData' -and -not $parameters.ContainsKey('RefreshData')) {
                $parameters.RefreshData = $true
            }
            elseif ($argument -in @('deploy', 'prepare') -and -not $parameters.ContainsKey('Mode')) {
                $parameters.Mode = $argument
            }
            else { throw 'Usage: gitpreview [-RefreshData]' }
        }
    }
    default {
        if ($arguments.Count -gt 0) { throw "Usage: $Command" }
    }
}
& $Command @parameters
