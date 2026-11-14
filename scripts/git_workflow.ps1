[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('gitstart', 'gitwork', 'gitmain', 'gitpull', 'gitsave', 'gitupdate', 'gitrelease', 'gitdiscard', 'gitcheck', 'gitdeploy')]
    [string]$Command,

    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$CommandArguments
)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'git_helpers.ps1')
$parameters = @{}
$arguments = @($CommandArguments | Where-Object { $null -ne $_ })
if ($Command -ceq 'gitpull') {
    gitpull @arguments
    return
}
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
    default {
        if ($arguments.Count -gt 0) { throw "Usage: $Command" }
    }
}
& $Command @parameters
