#Requires -Version 5.1
param([switch]$Check)
$ErrorActionPreference = 'Stop'
$localRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
if ($Check) {
    foreach ($file in @('start-local.ps1', 'start-services.ps1', 'dev.ps1')) {
        $tokens = $null
        $parseErrors = $null
        [void][Management.Automation.Language.Parser]::ParseFile((Join-Path $PSScriptRoot $file), [ref]$tokens, [ref]$parseErrors)
        if ($parseErrors.Count) { throw "PowerShell syntax check failed: $file" }
    }
    Write-Host "Startup syntax verified with PowerShell $($PSVersionTable.PSVersion). The website was not started."
    return
}
$localPhp = Join-Path $localRoot '.tools/php/php.exe'
Push-Location -LiteralPath $localRoot
try {
    $listeners = @(Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue)
    if ($listeners.Count) {
        foreach ($listener in $listeners) {
            $owner = Get-CimInstance Win32_Process -Filter "ProcessId=$($listener.OwningProcess)"
            if ($listener.LocalAddress -ne '127.0.0.1' -or $owner.ExecutablePath -ne $localPhp -or $owner.CommandLine -notmatch '\-S\s+127\.0\.0\.1:8000\b') {
                throw 'Port 8000 belongs to another process. Nothing was stopped.'
            }
        }
        Write-Host 'NFCheck is already running: http://127.0.0.1:8000/login'
        return
    }

    Write-Host '[1/3] Starting or checking the local database and mail inbox...'
    & (Join-Path $PSScriptRoot 'dev.ps1') services

    $hot = Join-Path $localRoot 'public/hot'
    if ((Test-Path -LiteralPath $hot) -and -not (Get-NetTCPConnection -LocalPort 5173 -State Listen -ErrorAction SilentlyContinue)) {
        if ((Get-Content -LiteralPath $hot -Raw).Trim() -match '^http://(127\.0\.0\.1|localhost):5173/?$') {
            Remove-Item -LiteralPath $hot
            Write-Host 'Removed the inactive frontend development-server marker.'
        } else {
            throw 'public/hot points to an unexpected frontend server. Review it before starting.'
        }
    }
    if (-not (Test-Path -LiteralPath (Join-Path $localRoot 'public/build/manifest.json'))) {
        Write-Host '[2/3] Building frontend assets for the first run...'
        & (Join-Path $PSScriptRoot 'dev.ps1') build
    } else {
        Write-Host '[2/3] Using the existing frontend build.'
    }

    Write-Host '[3/3] Starting NFCheck: http://127.0.0.1:8000/login'
    Write-Host 'Leave this terminal open. The server keeps running until you press Ctrl+C.'
    Write-Host 'Optional frontend hot reload: npm run dev:frontend in another terminal.'
    & (Join-Path $PSScriptRoot 'dev.ps1') serve
} finally {
    Pop-Location
}
