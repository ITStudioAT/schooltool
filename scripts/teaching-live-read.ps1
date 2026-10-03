param([Parameter(Mandatory = $true)][string]$Request)
$ErrorActionPreference = 'Stop'
if ([Environment]::OSVersion.Platform -ne [PlatformID]::Win32NT -or $Request -cnotmatch '^[A-Za-z0-9+/]+={0,2}$') {
    throw 'Teaching live reads require Windows and an encoded request.'
}
$stage = 'configuration'
try {
    . (Join-Path $PSScriptRoot 'git_ssh_helpers.ps1')
    $target = Get-SchooltoolDeploymentTarget -Site MAIN
    $target.Options = @($target.Options) + @('-n')
    $stage = 'program'
    $program = Get-Content -LiteralPath (Join-Path $PSScriptRoot 'teaching-live-export.php') -Encoding UTF8 -Raw
    $program = $program.Replace('__TEACHING_REQUEST__', $Request)
    foreach ($entry in @(@('GRAPH', 'TeachingSynchronisationGraph.php'), @('FILES', 'TeachingSynchronisationFiles.php'))) {
        $source = Get-Content -LiteralPath (Join-Path $PSScriptRoot ('../app/Services/' + $entry[1])) -Encoding UTF8 -Raw
        $program = $program.Replace(('__TEACHING_' + $entry[0] + '__'), [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($source)))
    }
    # Compression keeps the reviewed reader below Windows' SSH command-line limit.
    $buffer = New-Object System.IO.MemoryStream
    $compressor = New-Object System.IO.Compression.GZipStream($buffer, [System.IO.Compression.CompressionMode]::Compress, $true)
    try {
        $bytes = [Text.Encoding]::UTF8.GetBytes($program)
        $compressor.Write($bytes, 0, $bytes.Length)
    }
    finally { $compressor.Dispose() }
    $encoded = [Convert]::ToBase64String($buffer.ToArray())
    $buffer.Dispose()
    $stage = 'ssh'
    Invoke-SchooltoolRemote -Target $target -Command ('printf %s ''' + $encoded + ''' | base64 --decode | php -d memory_limit=1536M -d display_errors=0 -d log_errors=0 -r ''eval(substr(gzdecode(stream_get_contents(STDIN)), 5));''')
}
catch {
    [Console]::Error.WriteLine("Teaching local read failed at $stage.")
    exit 1
}
