#Requires -Version 7.4
$ErrorActionPreference = 'Stop'
$testProject = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$testTools = Join-Path $testProject '.tools'
$fixtureRoot = Join-Path $testTools ('setup-check-' + [Guid]::NewGuid().ToString('N'))
$shell = Join-Path $PSHOME 'pwsh.exe'
$checks = 0

function Assert-Setup([bool]$Condition, [string]$Message) {
    if (-not $Condition) { throw $Message }
    $script:checks++
}
function New-Fixture([string]$Name) {
    $root = Join-Path $fixtureRoot $Name
    $scripts = Join-Path $root 'scripts'
    New-Item -ItemType Directory -Path $scripts -Force | Out-Null
    Copy-Item -LiteralPath (Join-Path $testProject 'scripts/setup-local.ps1') -Destination (Join-Path $scripts 'setup-local.ps1')
    Set-Content -LiteralPath (Join-Path $root '.env.example') -Value 'APP_KEY=' -Encoding utf8
    Set-Content -LiteralPath (Join-Path $scripts 'bootstrap-tools.ps1') -Value 'Add-Content -LiteralPath (Join-Path $PSScriptRoot "../calls.log") -Value tools' -Encoding utf8
    Set-Content -LiteralPath (Join-Path $scripts 'bootstrap-services.ps1') -Value @'
$root = Split-Path -Parent $PSScriptRoot
Add-Content -LiteralPath (Join-Path $root 'calls.log') -Value database
New-Item -ItemType Directory -Path (Join-Path $root '.tools/secrets') -Force | Out-Null
Set-Content -LiteralPath (Join-Path $root '.tools/secrets/database.json') -Value '{"fixture":true}'
'@ -Encoding utf8
    Set-Content -LiteralPath (Join-Path $scripts 'dev.ps1') -Value @'
param([Parameter(Position=0)][string]$Action, [Parameter(ValueFromRemainingArguments=$true)][string[]]$ToolArguments)
$root = Split-Path -Parent $PSScriptRoot
$call = if ($Action -eq 'artisan') { $ToolArguments[0] } else { $Action }
Add-Content -LiteralPath (Join-Path $root 'calls.log') -Value $call
if (Test-Path -LiteralPath (Join-Path $root ('fail-' + $call))) { throw 'Simulated interrupted setup step.' }
if ($call -eq 'key:generate') { Set-Content -LiteralPath (Join-Path $root '.env') -Value 'APP_KEY=fixture-key' }
if ($call -eq 'nfcheck:provision-user') { Set-Content -LiteralPath (Join-Path $root '.tools/secrets/initial-accounts.json') -Value '{"admin@nfcheck.test":{"fixture":true}}' }
'@ -Encoding utf8
    return $root
}
function Invoke-Fixture([string]$Root, [string[]]$Arguments = @()) {
    $previousPreference = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $output = & $shell -NoProfile -File (Join-Path $Root 'scripts/setup-local.ps1') @Arguments 2>&1
        return @{ Code = $LASTEXITCODE; Output = ($output -join "`n") }
    } finally { $ErrorActionPreference = $previousPreference }
}
function Calls([string]$Root) { return @(Get-Content -LiteralPath (Join-Path $Root 'calls.log')) }

try {
    $fresh = New-Fixture 'fresh folder with spaces'
    Assert-Setup ((Invoke-Fixture $fresh @('-Check')).Code -eq 0) 'Preflight failed.'
    Assert-Setup (-not (Test-Path -LiteralPath (Join-Path $fresh '.tools'))) 'Preflight modified the installation.'
    Assert-Setup ((Invoke-Fixture $fresh).Code -eq 0) 'Fresh setup failed.'
    $state = Get-Content -Raw -LiteralPath (Join-Path $fresh '.tools/runtime/local-setup.json') | ConvertFrom-Json
    Assert-Setup ($state.completed_steps.Count -eq 9) 'Fresh setup did not finish all steps.'
    Assert-Setup (((Calls $fresh) -join ',') -eq 'tools,composer,npm,key:generate,database,migrate,nfcheck:provision-user,build') 'Setup step order is incorrect.'
    Assert-Setup ((Invoke-Fixture $fresh @('-Resume')).Code -eq 0) 'Completed setup did not recognize its checkpoint.'
    Assert-Setup ((Calls $fresh).Count -eq 8) 'Completed setup repeated commands.'

    $early = New-Fixture 'early interruption'
    Set-Content -LiteralPath (Join-Path $early 'fail-npm') -Value fixture
    Assert-Setup ((Invoke-Fixture $early).Code -ne 0) 'Dependency failure was ignored.'
    Assert-Setup (-not (Test-Path -LiteralPath (Join-Path $early '.env'))) 'Setup continued after dependency failure.'
    Remove-Item -LiteralPath (Join-Path $early 'fail-npm')
    Assert-Setup ((Invoke-Fixture $early @('-Resume')).Code -eq 0) 'Interrupted dependency installation could not resume.'
    Assert-Setup (@((Calls $early) | Where-Object { $_ -eq 'tools' }).Count -eq 1) 'Resume repeated completed tool setup.'
    Assert-Setup (@((Calls $early) | Where-Object { $_ -eq 'npm' }).Count -eq 2) 'Resume did not retry the failed dependency step.'

    $late = New-Fixture 'late interruption'
    Set-Content -LiteralPath (Join-Path $late 'fail-build') -Value fixture
    Assert-Setup ((Invoke-Fixture $late).Code -ne 0) 'Build failure was ignored.'
    $before = (Get-FileHash -LiteralPath (Join-Path $late '.env')).Hash
    $accountBefore = (Get-FileHash -LiteralPath (Join-Path $late '.tools/secrets/initial-accounts.json')).Hash
    Remove-Item -LiteralPath (Join-Path $late 'fail-build')
    Assert-Setup ((Invoke-Fixture $late @('-Resume')).Code -eq 0) 'Interrupted build could not resume.'
    Assert-Setup ((Get-FileHash -LiteralPath (Join-Path $late '.env')).Hash -eq $before) 'Resume replaced the application key.'
    Assert-Setup ((Get-FileHash -LiteralPath (Join-Path $late '.tools/secrets/initial-accounts.json')).Hash -eq $accountBefore) 'Resume replaced account credentials.'
    Assert-Setup (@((Calls $late) | Where-Object { $_ -eq 'database' }).Count -eq 1) 'Resume repeated completed database setup.'
    Assert-Setup (@((Calls $late) | Where-Object { $_ -eq 'nfcheck:provision-user' }).Count -eq 1) 'Resume reprovisioned the administrator.'

    $existing = New-Fixture 'existing installation'
    Set-Content -LiteralPath (Join-Path $existing '.env') -Value 'APP_KEY=existing-fixture'
    $before = (Get-FileHash -LiteralPath (Join-Path $existing '.env')).Hash
    Assert-Setup ((Invoke-Fixture $existing).Code -ne 0) 'Fresh setup accepted an existing installation.'
    Assert-Setup ((Invoke-Fixture $existing @('-Resume')).Code -ne 0) 'Resume accepted an installation without an owned checkpoint.'
    Assert-Setup ((Get-FileHash -LiteralPath (Join-Path $existing '.env')).Hash -eq $before) 'Existing environment was changed.'
    Assert-Setup (-not (Test-Path -LiteralPath (Join-Path $existing 'calls.log'))) 'Existing installation ran provisioning commands.'

    $checkpoint = Join-Path $fresh '.tools/runtime/local-setup.json'
    $state = Get-Content -Raw -LiteralPath $checkpoint | ConvertFrom-Json -AsHashtable
    $state.project_root = Join-Path $fixtureRoot 'another folder'
    $state | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $checkpoint
    Assert-Setup ((Invoke-Fixture $fresh @('-Resume')).Code -ne 0) 'Resume accepted another folder checkpoint.'
    $state.project_root = $fresh
    $state.completed_steps = @('unknown')
    $state | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $checkpoint
    Assert-Setup ((Invoke-Fixture $fresh @('-Resume')).Code -ne 0) 'Resume accepted an invalid checkpoint order.'
    Write-Host "$checks setup checks passed. No database, server, download or application dependency was used."
} finally {
    $resolvedFixture = [IO.Path]::GetFullPath($fixtureRoot)
    $expectedPrefix = [IO.Path]::GetFullPath($testTools) + [IO.Path]::DirectorySeparatorChar + 'setup-check-'
    if (-not $resolvedFixture.StartsWith($expectedPrefix, [StringComparison]::OrdinalIgnoreCase)) { throw 'Refusing fixture cleanup outside the owned test directory.' }
    if (Test-Path -LiteralPath $resolvedFixture) { Remove-Item -LiteralPath $resolvedFixture -Recurse -Force }
}
