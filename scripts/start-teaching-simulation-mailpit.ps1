$ErrorActionPreference = 'Stop'
$simulationProjectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$simulationDirectory = Join-Path $simulationProjectRoot 'storage\app\private\teaching-simulator'
$simulationDatabase = Join-Path $simulationDirectory 'mailpit.sqlite'
$simulationProof = Join-Path $simulationDirectory 'mailpit-safety.json'
if (Test-Path -LiteralPath $simulationProof) {
    $prior = Get-Content -LiteralPath $simulationProof -Encoding UTF8 -Raw | ConvertFrom-Json
    $running = Get-Process -Id $prior.process_id -ErrorAction SilentlyContinue
    if ($running -and $running.ProcessName -eq 'mailpit' -and $running.StartTime.ToUniversalTime().ToString('o') -eq $prior.started_at) {
        Write-Output 'Die eigene lokale Simulator-Mailpit-Instanz läuft bereits.'
        exit 0
    }
}
$listeners = Get-NetTCPConnection -State Listen -ErrorAction SilentlyContinue | Where-Object LocalPort -in @(1025,8025)
if ($listeners) { throw 'Die Mailpit-Ports werden bereits verwendet. Bitte die vorhandene Instanz zuerst in Laragon beenden.' }
$mailpitExecutable = (Get-Command mailpit.exe -ErrorAction SilentlyContinue).Source
if (-not $mailpitExecutable) {
    $mailpitExecutable = Get-ChildItem -Path 'C:\laragon\bin\mailpit\*\mailpit.exe' -File -ErrorAction SilentlyContinue |
        Sort-Object FullName -Descending | Select-Object -First 1 -ExpandProperty FullName
}
if (-not $mailpitExecutable) { throw 'Die bereits installierte Mailpit-Anwendung wurde nicht gefunden.' }
[IO.Directory]::CreateDirectory($simulationDirectory) | Out-Null
foreach ($relayVariable in @(Get-ChildItem Env: | Where-Object Name -match '^MP_SMTP_(RELAY|FORWARD)')) {
    [Environment]::SetEnvironmentVariable($relayVariable.Name, $null, 'Process')
}
$simulationArguments = @('--listen','127.0.0.1:8025','--smtp','127.0.0.1:1025','--database',('"'+$simulationDatabase+'"'),'--label','Schooltool-Simulator')
$simulationProcess = Start-Process -FilePath $mailpitExecutable -ArgumentList $simulationArguments -WindowStyle Hidden -PassThru
$simulationProcess.Refresh()
$proof = @{ schema_version=1; kind='schooltool.mailpit-safety'; process_id=$simulationProcess.Id;
    started_at=$simulationProcess.StartTime.ToUniversalTime().ToString('o'); database=$simulationDatabase;
    relay_environment_removed=$true; forwarding_environment_removed=$true; loopback_only=$true }
[IO.File]::WriteAllText($simulationProof, ($proof | ConvertTo-Json), [Text.UTF8Encoding]::new($false))
Write-Output 'Eigene Mailpit-Instanz ohne Relay und Weiterleitung auf Loopback gestartet.'
