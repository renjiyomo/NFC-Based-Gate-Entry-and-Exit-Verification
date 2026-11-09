#Requires -Version 7.4
$ErrorActionPreference = 'Stop'
$simRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$simPhp = Join-Path $simRoot '.tools/php/php.exe'
& (Join-Path $PSScriptRoot 'start-services.ps1')
foreach ($simScript in @('bootstrap-simulator.php', 'migrate-database.php', 'seed-simulator.php')) {
    & $simPhp (Join-Path $PSScriptRoot $simScript)
    if ($LASTEXITCODE -ne 0) { throw "Simulator setup failed: $simScript" }
}
& $simPhp (Join-Path $PSScriptRoot 'initialize-gate-accounts.php') simulator
if ($LASTEXITCODE -ne 0) { throw 'Simulator gate-account initialization failed.' }
# Apply private-file ACLs after creating the environment.
& (Join-Path $PSScriptRoot 'start-services.ps1')
$simListeners = @(Get-NetTCPConnection -LocalPort 8002 -State Listen -ErrorAction SilentlyContinue)
$simRouter = Join-Path $simRoot 'server.php'
if ($simListeners.Count) {
    if (@($simListeners | Where-Object LocalAddress -ne '127.0.0.1').Count) { throw 'Simulator listener must be loopback only.' }
    $simProcess = Get-CimInstance Win32_Process -Filter "ProcessId=$($simListeners[0].OwningProcess)"
    if ($simProcess.ExecutablePath -ne $simPhp -or -not $simProcess.CommandLine.Contains($simRouter)) { throw 'Port 8002 belongs to another process; nothing was stopped.' }
} else {
    # PHP loads the dedicated environment in the launcher before serving public files.
    Start-Process -FilePath $simPhp -ArgumentList @(('"'+(Join-Path $PSScriptRoot 'serve-simulator.php')+'"')) -WorkingDirectory $simRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $simRoot '.tools/artifacts/simulator.stdout.log') -RedirectStandardError (Join-Path $simRoot '.tools/artifacts/simulator.stderr.log') | Out-Null
}
Write-Host 'Isolated simulator: http://localhost:8002/login; private accounts: .tools/secrets/operator-accounts.json'
