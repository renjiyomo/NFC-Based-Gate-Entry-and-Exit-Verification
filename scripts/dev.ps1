param(
    [Parameter(Position = 0)]
    [ValidateSet('serve', 'vite', 'build', 'typecheck', 'test', 'format', 'composer', 'npm', 'git', 'check', 'artisan', 'services', 'migrate')]
    [string]$Action = 'serve',
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$ToolArguments = @()
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$phpPath = Join-Path $projectRoot '.tools\php\php.exe'
$nodeDirectory = Join-Path $projectRoot '.tools\node\node-v24.22.2-win-x64'
$gitPath = Join-Path $projectRoot '.tools\git\cmd\git.exe'
$composerPath = Join-Path $projectRoot '.tools\composer-patched.phar'
$npmPath = Join-Path $nodeDirectory 'npm.cmd'
$previousPath = $env:PATH
$previousComposerHome = $env:COMPOSER_HOME

function Invoke-Checked {
    param([string]$Executable, [string[]]$Arguments)
    & $Executable @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Command failed with exit code $LASTEXITCODE."
    }
}

foreach ($requiredPath in @($phpPath, $npmPath, $gitPath, $composerPath)) {
    if (-not (Test-Path -LiteralPath $requiredPath)) {
        throw "Missing isolated tool: $requiredPath. See docs/development.md."
    }
}

Push-Location -LiteralPath $projectRoot
try {
    $env:PATH = "$(Split-Path -Parent $phpPath);$nodeDirectory;$(Split-Path -Parent $gitPath);$previousPath"
    $env:COMPOSER_HOME = Join-Path $projectRoot '.tools\composer-home'
    switch ($Action) {
        'serve' { Invoke-Checked $phpPath @('artisan', 'serve', '--host=127.0.0.1', '--port=8000', '--tries=1') }
        'services' { & (Join-Path $PSScriptRoot 'start-services.ps1') }
        'migrate' { Invoke-Checked $phpPath @('scripts/migrate-database.php') }
        'artisan' { Invoke-Checked $phpPath (@('artisan') + $ToolArguments) }
        'vite' { Invoke-Checked $npmPath @('run', 'dev') }
        'build' { Invoke-Checked $npmPath @('run', 'build') }
        'typecheck' { Invoke-Checked $npmPath @('run', 'typecheck') }
        'test' { Invoke-Checked $phpPath (@('artisan', 'test') + $ToolArguments) }
        'format' { Invoke-Checked $phpPath (@('vendor/bin/pint') + $ToolArguments) }
        'composer' { Invoke-Checked $phpPath (@($composerPath) + $ToolArguments) }
        'npm' { Invoke-Checked $npmPath $ToolArguments }
        'git' { Invoke-Checked $gitPath $ToolArguments }
        'check' {
            Invoke-Checked $phpPath @($composerPath, 'validate', '--strict')
            Invoke-Checked $phpPath @($composerPath, 'audit')
            Invoke-Checked $npmPath @('audit')
            Invoke-Checked $npmPath @('run', 'format:check')
            Invoke-Checked $npmPath @('run', 'build')
            Invoke-Checked $phpPath @('vendor/bin/pint', '--test')
            Invoke-Checked $phpPath @('artisan', 'test')
        }
    }
} finally {
    $env:PATH = $previousPath
    $env:COMPOSER_HOME = $previousComposerHome
    Pop-Location
}
