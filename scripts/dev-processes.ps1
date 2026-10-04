param([string]$Ports)
$ErrorActionPreference = 'Stop'
if ($Ports -notmatch '^\d{1,5}(?:,\d{1,5})*$') { throw 'Invalid local development ports.' }
$portNumbers = @($Ports.Split(',') | ForEach-Object { [int]$_ })
$listeners = @(Get-NetTCPConnection -State Listen -ErrorAction SilentlyContinue | Where-Object { $_.LocalPort -in $portNumbers } | Select-Object @{Name='port';Expression={[int]$_.LocalPort}}, @{Name='pid';Expression={[int]$_.OwningProcess}})
$ownerIds = @($listeners | ForEach-Object pid)
$processes = @(Get-CimInstance Win32_Process | Where-Object { $_.Name -in @('php.exe', 'node.exe', 'cmd.exe') -or $_.ProcessId -in $ownerIds } | ForEach-Object {
    [pscustomobject]@{ pid=[int]$_.ProcessId; parent=[int]$_.ParentProcessId; name=$_.Name; started=$_.CreationDate.ToUniversalTime().ToString('o'); command=$_.CommandLine }
})
[pscustomobject]@{ listeners=$listeners; processes=$processes } | ConvertTo-Json -Depth 4 -Compress

