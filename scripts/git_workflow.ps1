[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('gitstart', 'gitwork', 'gitmain', 'gitsave', 'gitupdate', 'gitrelease', 'gitcheck')]
    [string]$Command,

    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$CommandArguments
)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'git_helpers.ps1')
if ($CommandArguments.Count -gt 0) {
    & $Command @CommandArguments
}
else {
    & $Command
}
