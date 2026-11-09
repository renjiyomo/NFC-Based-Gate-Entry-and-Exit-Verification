#Requires -Version 7.4
$ErrorActionPreference = 'Stop'
$hardwareRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$hardwarePhp = Join-Path $hardwareRoot '.tools/php/php.exe'
& (Join-Path $PSScriptRoot 'start-services.ps1')
foreach ($hardwareScript in @('bootstrap-hardware.php','migrate-database.php','seed-hardware.php')) {
    & $hardwarePhp (Join-Path $PSScriptRoot $hardwareScript)
    if ($LASTEXITCODE -ne 0) { throw "Hardware setup failed: $hardwareScript" }
}
& $hardwarePhp (Join-Path $PSScriptRoot 'initialize-gate-accounts.php') hardware
if ($LASTEXITCODE -ne 0) { throw 'Hardware gate-account initialization failed.' }
& (Join-Path $PSScriptRoot 'start-services.ps1')
$hardwareListeners = @(Get-NetTCPConnection -LocalPort 8003 -State Listen -ErrorAction SilentlyContinue)
$hardwareRouter = Join-Path $hardwareRoot 'server.php'
if ($hardwareListeners.Count) {
    if (@($hardwareListeners | Where-Object LocalAddress -ne '127.0.0.1').Count) { throw 'Hardware listener must be loopback only.' }
    $hardwareProcess = Get-CimInstance Win32_Process -Filter "ProcessId=$($hardwareListeners[0].OwningProcess)"
    if ($hardwareProcess.ExecutablePath -ne $hardwarePhp -or -not $hardwareProcess.CommandLine.Replace('/', '\').Contains($hardwareRouter)) { throw 'Port 8003 belongs to another process; nothing was stopped.' }
} else {
    Start-Process -FilePath $hardwarePhp -ArgumentList @(('"'+(Join-Path $PSScriptRoot 'serve-hardware.php')+'"')) -WorkingDirectory $hardwareRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $hardwareRoot '.tools/artifacts/hardware.stdout.log') -RedirectStandardError (Join-Path $hardwareRoot '.tools/artifacts/hardware.stderr.log') | Out-Null
}
Write-Host 'Hardware test workspace: http://localhost:8003/login; private accounts: .tools/secrets/operator-accounts.json'
