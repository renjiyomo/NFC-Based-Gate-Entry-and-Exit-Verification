param(
    [Parameter(Mandatory=$true)]
    [ValidateSet('simulator','hardware')]
    [string]$Workspace
)
$ErrorActionPreference='Stop'
$projectRoot=Split-Path -Parent $PSScriptRoot
$phpPath=Join-Path $projectRoot '.tools/php/php.exe'
$workerPath=Join-Path $PSScriptRoot 'run-summary-worker.php'
$runtimeFolder=Join-Path $projectRoot '.tools/runtime'
New-Item -ItemType Directory -Path $runtimeFolder -Force | Out-Null
$statePath=Join-Path $runtimeFolder "summary-$Workspace.json"
if(Test-Path -LiteralPath $statePath) {
    $state=Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json
    $running=Get-CimInstance Win32_Process -Filter "ProcessId = $([int]$state.pid)"
    if($running) {
        if($running.CommandLine -notmatch [regex]::Escape($workerPath) -or $running.CommandLine -notmatch "\b$Workspace\b.*--watch") { throw 'Stored summary PID belongs to another process. No process was changed.' }
        Write-Output "Reusing the $Workspace summary worker."
        return
    }
}
$workerArguments=@(('"'+$workerPath+'"'),$Workspace,'--watch')
$process=Start-Process -FilePath $phpPath -ArgumentList $workerArguments -WorkingDirectory $projectRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runtimeFolder "summary-$Workspace.log") -RedirectStandardError (Join-Path $runtimeFolder "summary-$Workspace.error.log") -PassThru
Start-Sleep -Milliseconds 300
$process.Refresh()
if($process.HasExited) { throw 'The summary worker did not stay running. Check its private runtime error log.' }
@{pid=$process.Id;workspace=$Workspace;started_utc=[DateTime]::UtcNow.ToString('o')} | ConvertTo-Json | Set-Content -LiteralPath $statePath
Write-Output "Started the $Workspace summary worker. No network listener or system service was installed."
