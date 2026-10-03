param([string]$PhpExecutable = 'php')

$ErrorActionPreference = 'Stop'

function Assert-SchooltoolFrontendPath {
    param([string]$Path, [string]$Project)

    $resolved = [IO.Path]::GetFullPath($Path)
    if (-not $resolved.StartsWith($Project + '\', [StringComparison]::OrdinalIgnoreCase)) {
        throw 'Frontend process path is outside this project.'
    }
    for ($current = $resolved; $current -ne $Project; $current = Split-Path -Parent $current) {
        if ((Get-Item -LiteralPath $current -ErrorAction Stop).Attributes -band [IO.FileAttributes]::ReparsePoint) {
            throw 'Frontend process paths may not cross junctions or symbolic links.'
        }
    }
    $resolved
}

function Get-SchooltoolFrontendPausePlan {
    param([string]$Project)

    $modules = (Join-Path $Project 'node_modules').TrimEnd('\') + '\'
    $processes = @(Get-CimInstance Win32_Process)
    $blockers = @($processes | Where-Object { $_.ExecutablePath -and $_.ExecutablePath.StartsWith($modules, [StringComparison]::OrdinalIgnoreCase) })
    $plan = @{}
    foreach ($blocker in $blockers) {
        $executable = Assert-SchooltoolFrontendPath -Path $blocker.ExecutablePath -Project $Project
        if ($executable -notmatch '\\node_modules\\@esbuild\\win32-(?:x64|arm64)\\esbuild\.exe$' -or -not $blocker.CreationDate) {
            throw "Unsupported frontend blocker PID $($blocker.ProcessId); no process was stopped."
        }
        $parent = $processes | Where-Object ProcessId -EQ $blocker.ParentProcessId | Select-Object -First 1
        if (-not $parent -or -not $parent.CreationDate -or -not $parent.ExecutablePath -or (Split-Path -Leaf $parent.ExecutablePath) -ne 'node.exe' -or
            $parent.CommandLine -notmatch '^(?:"[^"]+"|\S+)\s+(?:"(?<entry>[^"]+)"|(?<entry>\S+))(?<arguments>.*)$') {
            throw 'The frontend blocker is not owned by a verifiable Vite process; no process was stopped.'
        }
        $entry = $Matches.entry
        $arguments = $Matches.arguments.Trim()
        if (-not [IO.Path]::IsPathRooted($entry)) { $entry = Join-Path $Project $entry }
        $entry = Assert-SchooltoolFrontendPath -Path $entry -Project $Project
        if ($entry -ine [IO.Path]::GetFullPath((Join-Path $Project 'node_modules/vite/bin/vite.js')) -or
            $arguments -notmatch '^(?:(?:--clearScreen\s+(?:false|true)|--host(?:\s+(?:localhost|127\.0\.0\.1|0\.0\.0\.0|::1))?|--port\s+\d{1,5}|--strictPort|--force)\s*)*$') {
            throw 'Only this project''s ordinary Vite launcher can be paused automatically; no process was stopped.'
        }
        $wrapper = $processes | Where-Object ProcessId -EQ $parent.ParentProcessId | Select-Object -First 1
        $controllers = @(Get-SchooltoolManagedVite -Project $Project -Processes $processes -WrapperId $wrapper.ProcessId -ViteId $parent.ProcessId)
        if ($controllers.Count -gt 1) { throw 'Managed Vite ownership is ambiguous.' }
        $controller = $controllers | Select-Object -First 1
        $ancestor = $parent
        $seen = @{}
        while ($ancestor) {
            if ($controller) { break }
            if ($seen.ContainsKey($ancestor.ProcessId)) { throw 'Ambiguous process ancestry; no process was stopped.' }
            $seen[$ancestor.ProcessId] = $true
            if (-not $ancestor.ExecutablePath -or -not $ancestor.CommandLine) { throw 'Process ancestry is unavailable; no process was stopped.' }
            if ($ancestor.CommandLine -match '(?i)\bconcurrently\b') {
                throw 'Vite belongs to a shared concurrently dev session. Stop that session yourself before gitsave; other services were left running.'
            }
            if ((Split-Path -Leaf $ancestor.ExecutablePath) -notin @('node.exe', 'cmd.exe', 'powershell.exe', 'pwsh.exe', 'php.exe', 'conhost.exe')) { break }
            $next = $processes | Where-Object ProcessId -EQ $ancestor.ParentProcessId | Select-Object -First 1
            if ($next -and $next.CreationDate -gt $ancestor.CreationDate) { break }
            $ancestor = $next
        }
        if (-not $plan.ContainsKey($parent.ProcessId)) {
            $plan[$parent.ProcessId] = [pscustomobject]@{ Process = $parent; Entry = $entry; Arguments = $arguments; Children = @(); Controller = $controller }
        }
        $plan[$parent.ProcessId].Children += $blocker
    }
    foreach ($controller in @(Get-SchooltoolManagedVite -Project $Project -Processes $processes -Paused)) {
        $plan['paused-'+$controller.Process.ProcessId] = [pscustomobject]@{ Process = $null; Entry = ''; Arguments = ''; Children = @(); Controller = $controller }
    }
    @($plan.Values)
}

function Get-SchooltoolManagedVite {
    param([string]$Project, [array]$Processes, [int]$WrapperId, [int]$ViteId, [switch]$Paused)

    foreach ($file in @(Get-ChildItem -LiteralPath (Join-Path $Project 'storage/framework') -Filter 'vite-dev-*.json' -ErrorAction SilentlyContinue)) {
        if ($file.Name -notmatch '^vite-dev-(?<nonce>[a-f0-9]{32})\.json$') { continue }
        $nonce = $Matches.nonce
        $manifest = Get-Content -LiteralPath $file.FullName -Encoding UTF8 -Raw | ConvertFrom-Json
        if ($manifest.format -ne 'schooltool-vite-dev-v1' -or $manifest.nonce -cne $nonce -or $manifest.project -ine $Project) { continue }
        if ($Paused) {
            if ($manifest.status -ne 'paused') { continue }
        } elseif ($manifest.wrapper_pid -ne $WrapperId -or $manifest.vite_pid -ne $ViteId -or $manifest.status -ne 'running') { continue }
        $wrapper = $Processes | Where-Object ProcessId -EQ $manifest.wrapper_pid | Select-Object -First 1
        if (-not $wrapper) { continue }
        if (-not $wrapper.ExecutablePath -or (Split-Path -Leaf $wrapper.ExecutablePath) -ne 'node.exe' -or
            $wrapper.CommandLine -notmatch '^(?:"[^"]+"|\S+)\s+(?:"(?<entry>[^"]+)"|(?<entry>\S+))(?:\s.*)?$') { throw 'Managed Vite wrapper identity is unavailable.' }
        $wrapperEntry = $Matches.entry
        if (-not [IO.Path]::IsPathRooted($wrapperEntry)) { $wrapperEntry = Join-Path $Project $wrapperEntry }
        if ((Assert-SchooltoolFrontendPath $wrapperEntry $Project) -ine [IO.Path]::GetFullPath((Join-Path $Project 'scripts/vite-dev.mjs')) -or
            [Math]::Abs(($wrapper.CreationDate.ToUniversalTime() - [DateTime]::Parse($manifest.started_at).ToUniversalTime()).TotalSeconds) -gt 3) { throw 'Managed Vite wrapper start identity differs.' }
        $manifestPath = Assert-SchooltoolFrontendPath $file.FullName $Project
        if ($Paused) {
            $pausePath = Assert-SchooltoolFrontendPath ([IO.Path]::ChangeExtension($manifestPath, '.pause')) $Project
            $request = Get-Content -LiteralPath $pausePath -Encoding UTF8 -Raw | ConvertFrom-Json
            if ($request.nonce -cne $nonce -or $request.wrapper_pid -ne $wrapper.ProcessId -or
                $request.vite_pid -ne $manifest.pause_request.vite_pid) { throw 'Paused Vite recovery ownership differs.' }
        }
        [pscustomobject]@{ Process = $wrapper; Manifest = $manifestPath; Pause = [IO.Path]::ChangeExtension($manifestPath, '.pause'); Nonce = $nonce; StartedAt = $manifest.started_at; OriginalViteId = $manifest.vite_pid }
    }
}

function Wait-SchooltoolManagedVite {
    param($Controller, [string]$Status)

    $deadline = [DateTime]::UtcNow.AddSeconds(10)
    do {
        Assert-SchooltoolFrontendProcess $Controller.Process
        $manifest = Get-Content -LiteralPath $Controller.Manifest -Encoding UTF8 -Raw | ConvertFrom-Json
        if ($manifest.nonce -cne $Controller.Nonce -or $manifest.started_at -cne $Controller.StartedAt -or $manifest.wrapper_pid -ne $Controller.Process.ProcessId) { throw 'Managed Vite ownership changed.' }
        if ($manifest.status -eq $Status) {
            if ($Status -eq 'running') {
                $child = Get-CimInstance Win32_Process -Filter "ProcessId=$($manifest.vite_pid)"
                if (-not $child -or $child.ParentProcessId -ne $Controller.Process.ProcessId) { throw 'Restored Vite has no proven wrapper parent.' }
            }
            return
        }
        Start-Sleep -Milliseconds 100
    } while ([DateTime]::UtcNow -lt $deadline)
    throw "Managed Vite did not acknowledge $Status."
}

function Assert-SchooltoolFrontendProcess {
    param($Expected)

    $actual = Get-CimInstance Win32_Process -Filter "ProcessId=$($Expected.ProcessId)"
    if (-not $actual -or $actual.CreationDate -ne $Expected.CreationDate -or $actual.ExecutablePath -ine $Expected.ExecutablePath -or
        $actual.CommandLine -cne $Expected.CommandLine -or $actual.ParentProcessId -ne $Expected.ParentProcessId) {
        throw 'Frontend process identity changed. No replacement process will be stopped.'
    }
}

function Invoke-SchooltoolPausedFrontendInstallation {
    param([string]$Project, [string]$Php)

    $Project = (Resolve-Path -LiteralPath $Project).Path.TrimEnd('\')
    $plan = @(Get-SchooltoolFrontendPausePlan -Project $Project)
    foreach ($item in $plan) {
        if ($item.Process) { Assert-SchooltoolFrontendProcess $item.Process }
        if ($item.Controller) { Assert-SchooltoolFrontendProcess $item.Controller.Process }
        foreach ($child in $item.Children) { Assert-SchooltoolFrontendProcess $child }
    }
    $paused = @()
    $exitCode = 1
    Push-Location -LiteralPath $Project
    try {
        foreach ($item in $plan) {
            if ($item.Controller) {
                $controller = $item.Controller
                Assert-SchooltoolFrontendProcess $controller.Process
                if ($item.Process) {
                    Assert-SchooltoolFrontendProcess $item.Process
                    $request = @{ nonce = $controller.Nonce; wrapper_pid = $controller.Process.ProcessId; vite_pid = $item.Process.ProcessId } | ConvertTo-Json -Compress
                    $stream = [IO.File]::Open($controller.Pause, [IO.FileMode]::CreateNew, [IO.FileAccess]::Write, [IO.FileShare]::Read)
                    try { $bytes = [Text.Encoding]::UTF8.GetBytes($request); $stream.Write($bytes, 0, $bytes.Length); $stream.Flush() }
                    finally { $stream.Dispose() }
                }
                $paused += $item
                Wait-SchooltoolManagedVite $controller 'paused'
                Write-Host "Paused only Vite; dev supervisor PID $($controller.Process.ProcessId) remains running."
                continue
            }
            Assert-SchooltoolFrontendProcess $item.Process
            Write-Host "Pausing this project's Vite PID $($item.Process.ProcessId) for locked package preparation."
            Stop-Process -Id $item.Process.ProcessId -ErrorAction Stop
            $paused += $item
            foreach ($child in $item.Children) {
                $running = Get-Process -Id $child.ProcessId -ErrorAction SilentlyContinue
                if ($running -and -not $running.WaitForExit(3000)) {
                    Assert-SchooltoolFrontendProcess $child
                    Stop-Process -Id $child.ProcessId -ErrorAction Stop
                    $running.WaitForExit(3000) | Out-Null
                }
            }
        }
        # The existing installer rechecks package identity and its idle-process guard,
        # and owns the incomplete-install receipt and exact npm ci invocation.
        & $Php -r 'require ''scripts/update.php''; exit(installFrontendDependencies());' | Out-Host
        $exitCode = $LASTEXITCODE
    }
    finally {
        try {
            foreach ($item in $paused) {
                if ($item.Controller) {
                    if (Test-Path -LiteralPath (Join-Path $Project 'storage/framework/frontend-dependencies.sha256.installing')) {
                        Write-Warning 'Frontend installation is incomplete. Only Vite remains paused; other dev services are still running.'
                        continue
                    }
                    Assert-SchooltoolFrontendProcess $item.Controller.Process
                    Remove-Item -LiteralPath $item.Controller.Pause
                    Wait-SchooltoolManagedVite $item.Controller 'running'
                    Write-Host 'Restored Vite inside the existing dev session.'
                    continue
                }
                if ((Test-Path -LiteralPath (Join-Path $Project 'storage/framework/frontend-dependencies.sha256.installing')) -or
                    -not (Test-Path -LiteralPath $item.Entry -PathType Leaf)) {
                    Write-Warning 'Frontend installation is incomplete. Vite remains stopped; repair preparation before restarting it.'
                    continue
                }
                $logDirectory = Join-Path $Project 'storage/logs'
                [IO.Directory]::CreateDirectory($logDirectory) | Out-Null
                $logPrefix = Join-Path $logDirectory ('vite-prepare-' + [guid]::NewGuid().ToString('N'))
                $argumentList = '"' + $item.Entry + '"' + $(if ($item.Arguments) { ' ' + $item.Arguments } else { '' })
                $restarted = Start-Process -FilePath $item.Process.ExecutablePath -ArgumentList $argumentList -WorkingDirectory $Project -WindowStyle Hidden -PassThru -RedirectStandardOutput ($logPrefix+'.out') -RedirectStandardError ($logPrefix+'.err')
                $null = $restarted.Handle
                if ($restarted.WaitForExit(1000)) { throw "Vite could not restart. See $logPrefix.err." }
                Write-Host "Restored this project's Vite as PID $($restarted.Id)."
            }
        }
        finally { Pop-Location }
    }
    $exitCode
}

if ($MyInvocation.InvocationName -ne '.') {
    try { exit (Invoke-SchooltoolPausedFrontendInstallation -Project (Split-Path -Parent $PSScriptRoot) -Php $PhpExecutable) }
    catch { [Console]::Error.WriteLine($_.Exception.Message); exit 1 }
}
