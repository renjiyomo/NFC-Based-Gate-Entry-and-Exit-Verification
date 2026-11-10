#Requires -Version 7.4
param([switch]$Check, [switch]$Resume)

$ErrorActionPreference = 'Stop'
$setupRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
if (-not $IsWindows -or [Runtime.InteropServices.RuntimeInformation]::OSArchitecture.ToString() -ne 'X64') {
    throw 'This installer supports Windows x64 (Intel/AMD).'
}
foreach ($file in @('bootstrap-tools.ps1', 'bootstrap-services.ps1', 'dev.ps1')) {
    if (-not (Test-Path -LiteralPath (Join-Path $PSScriptRoot $file))) { throw "Missing setup script: $file" }
}
if ($Check) {
    Write-Host 'Windows x64 and setup scripts verified. Nothing was downloaded, installed or started.'
    Write-Host 'Fresh setup downloads private tools, installs locked dependencies, creates local databases and one administrator, then builds the website.'
    return
}

$setupTools = Join-Path $setupRoot '.tools'
$setupRuntime = Join-Path $setupTools 'runtime'
$setupStatePath = Join-Path $setupRuntime 'local-setup.json'
$setupDatabasePath = Join-Path $setupTools 'secrets/database.json'
foreach ($directory in @($setupTools, $setupRuntime)) {
    if ((Test-Path -LiteralPath $directory) -and ((Get-Item -LiteralPath $directory).Attributes -band [IO.FileAttributes]::ReparsePoint)) {
        throw 'Setup directories must not be junctions or symbolic links.'
    }
}
if ($Resume) {
    if (-not (Test-Path -LiteralPath $setupStatePath)) { throw 'No interrupted setup was found. Existing installations use .\scripts\dev.ps1 npm run dev.' }
    $setupState = Get-Content -Raw -LiteralPath $setupStatePath | ConvertFrom-Json -AsHashtable
    if ($setupState.version -ne 1 -or -not [string]::Equals($setupState.project_root, $setupRoot, [StringComparison]::OrdinalIgnoreCase)) {
        throw 'The setup checkpoint belongs to another folder or version. Existing files were not changed.'
    }
} else {
    if ((Test-Path -LiteralPath (Join-Path $setupRoot '.env')) -or (Test-Path -LiteralPath $setupDatabasePath)) {
        throw 'An existing installation was found. Use .\scripts\dev.ps1 npm run dev. For an interrupted setup created by this installer, use -Resume.'
    }
    if (Test-Path -LiteralPath $setupStatePath) { throw 'An interrupted setup was found. Run this command again with -Resume.' }
    $setupState = @{ version = 1; project_root = $setupRoot; completed_steps = @() }
}

$setupSteps = @(
    @{ Id = 'tools'; Title = 'Download and verify private runtimes'; Run = { & (Join-Path $PSScriptRoot 'bootstrap-tools.ps1') } }
    @{ Id = 'composer'; Title = 'Install PHP dependencies'; Run = { & (Join-Path $PSScriptRoot 'dev.ps1') composer install --no-interaction --prefer-dist } }
    @{ Id = 'node'; Title = 'Install frontend dependencies'; Run = { & (Join-Path $PSScriptRoot 'dev.ps1') npm ci } }
    @{ Id = 'environment'; Title = 'Create the local environment'; Run = {
        if (-not (Test-Path -LiteralPath .env)) { Copy-Item -LiteralPath .env.example -Destination .env }
    } }
    @{ Id = 'key'; Title = 'Create the application key'; Run = {
        if (-not (Select-String -LiteralPath .env -Pattern '^APP_KEY=.+$' -Quiet)) { & (Join-Path $PSScriptRoot 'dev.ps1') artisan key:generate }
    } }
    @{ Id = 'database'; Title = 'Prepare the private database and mail inbox'; Run = { & (Join-Path $PSScriptRoot 'bootstrap-services.ps1') } }
    @{ Id = 'migrations'; Title = 'Create tables and apply runtime permissions'; Run = { & (Join-Path $PSScriptRoot 'dev.ps1') migrate } }
    @{ Id = 'administrator'; Title = 'Create the first administrator'; Run = {
        $handoff = Join-Path $setupTools 'secrets/initial-accounts.json'
        $accounts = if (Test-Path -LiteralPath $handoff) { Get-Content -Raw -LiteralPath $handoff | ConvertFrom-Json -AsHashtable } else { @{} }
        if (-not $accounts.ContainsKey('admin@nfcheck.test')) {
            & (Join-Path $PSScriptRoot 'dev.ps1') artisan nfcheck:provision-user admin@nfcheck.test --name=Administrator --role=admin --generate
        }
    } }
    @{ Id = 'assets'; Title = 'Build the website'; Run = { & (Join-Path $PSScriptRoot 'dev.ps1') build } }
)
$completed = @($setupState.completed_steps)
if ($completed.Count -gt $setupSteps.Count) { throw 'Invalid setup checkpoint. Existing files were not changed.' }
for ($index = 0; $index -lt $completed.Count; $index++) {
    if ($completed[$index] -ne $setupSteps[$index].Id) { throw 'Invalid setup checkpoint order. Existing files were not changed.' }
}
New-Item -ItemType Directory -Path $setupRuntime -Force | Out-Null
$setupState | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $setupStatePath -Encoding utf8
Push-Location -LiteralPath $setupRoot
try {
    for ($index = $completed.Count; $index -lt $setupSteps.Count; $index++) {
        $step = $setupSteps[$index]
        Write-Host "[$($index + 1)/$($setupSteps.Count)] $($step.Title)..."
        & $step.Run
        $setupState.completed_steps = @($setupState.completed_steps) + $step.Id
        $setupState | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $setupStatePath -Encoding utf8
    }
    Write-Host 'Setup complete. Username: admin. Read its generated password privately from .tools/secrets/initial-accounts.json.'
    Write-Host 'Start with .\scripts\dev.ps1 npm run dev, then open http://127.0.0.1:8000/login. Keep the terminal open.'
} catch {
    Write-Host 'Setup stopped. Keep this folder and resolve the error below.'
    Write-Host 'Continue with: pwsh -NoProfile -File .\scripts\setup-local.ps1 -Resume'
    throw
} finally {
    Pop-Location
}
