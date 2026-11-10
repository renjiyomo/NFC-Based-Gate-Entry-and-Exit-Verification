#Requires -Version 7.4
param([switch]$Check)
$ErrorActionPreference='Stop'
$setupRoot=[IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
if($Check) {
    foreach($file in @('bootstrap-tools.ps1','bootstrap-services.ps1','dev.ps1')) {
        if(-not(Test-Path -LiteralPath (Join-Path $PSScriptRoot $file))){throw "Missing setup script: $file"}
    }
    Write-Host 'Setup scripts present. A fresh installation downloads tools, installs dependencies, creates local databases, provisions one administrator and builds assets. No setup action was run.'
    return
}
if(-not $IsWindows){throw 'This setup supports Windows x64.'}
if((Test-Path -LiteralPath (Join-Path $setupRoot '.env')) -or (Test-Path -LiteralPath (Join-Path $setupRoot '.tools/secrets/database.json'))){throw 'An existing installation was found. Use npm run dev; this fresh setup will not replace its environment or database credentials.'}
Push-Location -LiteralPath $setupRoot
try {
    & (Join-Path $PSScriptRoot 'bootstrap-tools.ps1')
    & (Join-Path $PSScriptRoot 'dev.ps1') composer install --no-interaction --prefer-dist
    & (Join-Path $PSScriptRoot 'dev.ps1') npm ci
    Copy-Item -LiteralPath .env.example -Destination .env
    & (Join-Path $PSScriptRoot 'dev.ps1') artisan key:generate
    & (Join-Path $PSScriptRoot 'bootstrap-services.ps1')
    & (Join-Path $PSScriptRoot 'dev.ps1') migrate
    & (Join-Path $PSScriptRoot 'dev.ps1') artisan nfcheck:provision-user admin@nfcheck.test --name=Administrator --role=admin --generate
    & (Join-Path $PSScriptRoot 'dev.ps1') build
    Write-Host 'Setup complete. Username: admin. Read its generated password privately from .tools/secrets/initial-accounts.json.'
    Write-Host 'Start the website with .\scripts\dev.ps1 npm run dev. This uses the private toolchain without requiring global Node/npm.'
} finally { Pop-Location }
