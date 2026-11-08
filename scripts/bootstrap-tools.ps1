#Requires -Version 7.4
[CmdletBinding()]
param([switch]$VerifyOnly)

$ErrorActionPreference = 'Stop'
if (-not $IsWindows) { throw 'This isolated toolchain supports Windows x64 only.' }
$toolProject = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$toolRoot = Join-Path $toolProject '.tools'
$toolInputs = Join-Path $PSScriptRoot 'toolchain'
$toolManifest = Get-Content -Raw -LiteralPath (Join-Path $toolInputs 'artifacts.json') | ConvertFrom-Json
$toolBuild = Join-Path $toolRoot 'composer-build'
$toolPhp = Join-Path $toolRoot 'php/php.exe'
$toolNodeHome = Join-Path $toolRoot 'node/node-v24.22.2-win-x64'
$toolGit = Join-Path $toolRoot 'git/cmd/git.exe'
$toolComposer = Join-Path $toolRoot 'composer-patched.phar'
$toolReceiptPath = Join-Path $toolRoot 'toolchain-receipt.json'

function Invoke-Tool([string]$Executable, [string[]]$Arguments) {
    & $Executable @Arguments
    if ($LASTEXITCODE -ne 0) { throw "Tool failed ($LASTEXITCODE): $Executable $($Arguments -join ' ')" }
}
function Assert-Hash([string]$File, [string]$Expected) {
    if (-not (Test-Path -LiteralPath $File -PathType Leaf)) { throw "Missing file: $File" }
    if ((Get-FileHash -LiteralPath $File -Algorithm SHA256).Hash -ne $Expected) { throw "SHA-256 mismatch: $File" }
}
function Get-ToolPath([string]$Relative) {
    $candidate = [IO.Path]::GetFullPath((Join-Path $toolRoot $Relative))
    if (-not $candidate.StartsWith($toolRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) { throw 'Tool path escapes .tools.' }
    return $candidate
}
function Assert-Archive([string]$Archive, [string]$Destination) {
    $zip = [IO.Compression.ZipFile]::OpenRead($Archive)
    try {
        foreach ($entry in $zip.Entries) {
            if (-not $entry.Name) { continue }
            $file = [IO.Path]::GetFullPath((Join-Path $Destination $entry.FullName))
            if (-not $file.StartsWith($Destination + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) { throw 'Unsafe archive entry.' }
            $stream = $entry.Open()
            try { $digest = [Convert]::ToHexString([Security.Cryptography.SHA256]::HashData($stream)) } finally { $stream.Dispose() }
            Assert-Hash $file $digest
        }
    } finally { $zip.Dispose() }
}
function Assert-BuildSource {
    if ((Get-Item -LiteralPath $toolBuild).Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Composer checkout must not be a junction or symbolic link.' }
    $head = (& $toolGit -C $toolBuild rev-parse HEAD) -join ''
    if ($LASTEXITCODE -ne 0 -or $head -ne $toolManifest.composer_source.commit) { throw 'Unknown Composer source commit.' }
    $remote = (& $toolGit -C $toolBuild remote get-url origin) -join ''
    if ($LASTEXITCODE -ne 0 -or $remote -ne $toolManifest.composer_source.repository) { throw 'Unknown Composer source origin.' }
    $changed = @(& $toolGit -C $toolBuild diff --name-only HEAD)
    if ($LASTEXITCODE -ne 0 -or @($changed | Where-Object { $_ -notin @('composer.json', 'composer.lock', 'src/Composer/Compiler.php') }).Count) { throw 'Unexpected Composer source modifications.' }
    $untracked = @(& $toolGit -C $toolBuild ls-files --others --exclude-standard)
    if ($LASTEXITCODE -ne 0 -or $untracked.Count) { throw 'Unexpected untracked files in Composer source checkout.' }
}
function Get-JsonCanonical([string]$File) {
    return (Get-Content -Raw -LiteralPath $File | ConvertFrom-Json -AsHashtable | ConvertTo-Json -Depth 100 -Compress)
}

$toolSavedEnvironment = @{}
foreach ($toolName in @('PATH', 'COMPOSER_HOME', 'COMPOSER_CACHE_DIR', 'COMPOSER_CAFILE', 'PHPRC', 'PHP_INI_SCAN_DIR')) {
    $toolSavedEnvironment[$toolName] = [Environment]::GetEnvironmentVariable($toolName, 'Process')
}
Push-Location $toolProject
try {
    if (Test-Path -LiteralPath $toolRoot) {
        if ((Get-Item -LiteralPath $toolRoot).Attributes -band [IO.FileAttributes]::ReparsePoint) { throw '.tools must not be a junction or symbolic link.' }
    } elseif ($VerifyOnly) { throw 'No isolated tools found. Run bootstrap without -VerifyOnly first.' }
    else { New-Item -ItemType Directory -Path $toolRoot | Out-Null }
    foreach ($toolArtifact in $toolManifest.artifacts) {
        $toolArchive = Get-ToolPath $toolArtifact.file
        if (-not (Test-Path -LiteralPath $toolArchive)) {
            if ($VerifyOnly) { throw "Missing pinned artifact: $toolArchive" }
            New-Item -ItemType Directory -Path (Split-Path $toolArchive) -Force | Out-Null
            Write-Host "Downloading $($toolArtifact.name)"
            Invoke-WebRequest -Uri $toolArtifact.url -OutFile $toolArchive -TimeoutSec 120
        }
        Assert-Hash $toolArchive $toolArtifact.sha256
        if ($toolArtifact.extract_to) {
            $toolDestination = Get-ToolPath $toolArtifact.extract_to
            if (-not (Test-Path -LiteralPath $toolDestination)) {
                if ($VerifyOnly) { throw "Missing extracted tool: $toolDestination" }
                Expand-Archive -LiteralPath $toolArchive -DestinationPath $toolDestination
            }
            if ((Get-Item -LiteralPath $toolDestination).Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Extracted tool must not be a junction or symbolic link.' }
            Assert-Archive $toolArchive $toolDestination
        }
    }
    $toolIni = Join-Path $toolRoot 'php/php.ini'
    if (-not $VerifyOnly) {
        $toolPhpPath = (Join-Path $toolRoot 'php').Replace('\', '/')
        $toolIniLines = @('[PHP]', "extension_dir=`"$toolPhpPath/ext`"", 'extension=curl', 'extension=fileinfo', 'extension=gd', 'extension=intl', 'extension=mbstring', 'extension=openssl', 'extension=pdo_mysql', 'extension=pdo_sqlite', 'extension=zip', 'date.timezone=UTC', 'memory_limit=512M', 'display_errors=On', 'log_errors=On', 'zend.exception_ignore_args=1', 'expose_php=Off', 'session.upload_progress.enabled=0', 'session.use_strict_mode=1', 'session.cookie_httponly=1', 'session.cookie_samesite=Lax', 'zend.assertions=1', "curl.cainfo=`"$toolPhpPath/cacert.pem`"", "openssl.cafile=`"$toolPhpPath/cacert.pem`"")
        [IO.File]::WriteAllLines($toolIni, $toolIniLines, [Text.UTF8Encoding]::new($false))
    }
    $env:PATH = "$(Join-Path $toolRoot 'php');$toolNodeHome;$(Join-Path $toolRoot 'git/cmd');$($toolSavedEnvironment.PATH)"
    $env:COMPOSER_HOME = Join-Path $toolRoot 'composer-home'
    $env:COMPOSER_CACHE_DIR = Join-Path $toolRoot 'composer-cache'
    $env:COMPOSER_CAFILE = Join-Path $toolRoot 'php/cacert.pem'
    $env:PHPRC = $toolIni
    $env:PHP_INI_SCAN_DIR = ''
    if (-not $VerifyOnly) {
        New-Item -ItemType Directory -Path $env:COMPOSER_HOME -Force | Out-Null
        foreach ($toolKey in @('tags', 'dev')) { Copy-Item -LiteralPath (Join-Path $toolInputs "keys.$toolKey.pub") -Destination (Join-Path $env:COMPOSER_HOME "keys.$toolKey.pub") }
    }
    foreach ($toolKey in @('tags', 'dev')) {
        Assert-Hash (Join-Path $env:COMPOSER_HOME "keys.$toolKey.pub") (Get-FileHash -LiteralPath (Join-Path $toolInputs "keys.$toolKey.pub") -Algorithm SHA256).Hash
    }
    $toolRuntimeCheck = 'foreach (["curl","fileinfo","gd","intl","mbstring","openssl","pdo_mysql","pdo_sqlite","zip"] as $e) { if (!extension_loaded($e)) { fwrite(STDERR,"Missing extension: $e\n"); exit(1); } } if (PHP_VERSION !== "8.5.12" || PHP_ZTS || ini_get("session.upload_progress.enabled") !== "0" || php_ini_scanned_files()) { exit(2); } echo "PHP ",PHP_VERSION," NTS; matching extensions; upload progress disabled\n";'
    Invoke-Tool $toolPhp @('-r', $toolRuntimeCheck)
    $toolNodeVersion = (& (Join-Path $toolNodeHome 'node.exe') -p 'process.version') -join ''
    if ($LASTEXITCODE -ne 0 -or $toolNodeVersion -ne 'v24.22.2') { throw 'Unexpected Node version.' }
    Invoke-Tool (Join-Path $toolNodeHome 'node.exe') @((Join-Path $toolNodeHome 'node_modules/npm/bin/npm-cli.js'), '--version')
    $toolGitVersion = (& $toolGit --version) -join ''
    if ($LASTEXITCODE -ne 0 -or $toolGitVersion -ne 'git version 2.56.0.windows.4') { throw 'Unexpected Git version.' }
    if (-not (Test-Path -LiteralPath $toolBuild)) {
        if ($VerifyOnly) { throw 'Composer source checkout is missing.' }
        Invoke-Tool $toolGit @('-c', 'core.autocrlf=false', 'clone', '--depth', '1', '--branch', $toolManifest.composer_source.tag, $toolManifest.composer_source.repository, $toolBuild)
    }
    Assert-BuildSource
    $toolPatch = Join-Path $toolInputs 'compiler-completions.patch'
    if (-not $VerifyOnly) {
        foreach ($toolFile in @('composer.json', 'composer.lock')) {
            $toolInputDiff = (& $toolGit -C $toolBuild diff HEAD -- $toolFile) -join "`n"
            if ($LASTEXITCODE -ne 0 -or ($toolInputDiff -and (Get-JsonCanonical (Join-Path $toolInputs $toolFile)) -ne (Get-JsonCanonical (Join-Path $toolBuild $toolFile)))) { throw "Refusing to overwrite unrecognized build input: $toolFile" }
            Copy-Item -LiteralPath (Join-Path $toolInputs $toolFile) -Destination (Join-Path $toolBuild $toolFile)
        }
        $toolCurrentPatch = (& $toolGit -C $toolBuild diff HEAD -- src/Composer/Compiler.php) -join "`n"
        if (-not $toolCurrentPatch) { Invoke-Tool $toolGit @('-C', $toolBuild, 'apply', '--check', $toolPatch); Invoke-Tool $toolGit @('-C', $toolBuild, 'apply', $toolPatch) }
    }
    $toolActualPatch = ((& $toolGit -C $toolBuild diff HEAD -- src/Composer/Compiler.php) -join "`n").TrimEnd()
    if ($LASTEXITCODE -ne 0 -or $toolActualPatch -ne (Get-Content -Raw -LiteralPath $toolPatch).TrimEnd()) { throw 'Compiler differs from the exact reviewed two-line packaging patch.' }
    foreach ($toolFile in @('composer.json', 'composer.lock')) {
        if ((Get-JsonCanonical (Join-Path $toolInputs $toolFile)) -ne (Get-JsonCanonical (Join-Path $toolBuild $toolFile))) { throw "Composer build input mismatch: $toolFile" }
    }
    if (-not $VerifyOnly) {
        Invoke-Tool $toolPhp @((Join-Path $toolRoot 'composer.phar'), 'install', '--working-dir', $toolBuild, '--no-dev', '--no-plugins', '--no-scripts', '--no-interaction', '--prefer-dist')
        Push-Location $toolBuild
        try { Invoke-Tool $toolPhp @('-d', 'phar.readonly=0', 'bin/compile') } finally { Pop-Location }
        Copy-Item -LiteralPath (Join-Path $toolBuild 'composer.phar') -Destination $toolComposer -Force
    } elseif (Test-Path -LiteralPath $toolReceiptPath) {
        $toolPreviousReceipt = Get-Content -Raw -LiteralPath $toolReceiptPath | ConvertFrom-Json
        Assert-Hash $toolComposer $toolPreviousReceipt.composer_phar_sha256
    }
    # Inspect embedded package metadata, rather than accepting only --version output.
    $toolPharCheck = '$p = new Phar($argv[1]); if ($p->getSignature()["hash_type"] !== "SHA-512") exit(1); $j = json_decode($p["vendor/composer/installed.json"]->getContent(),true,512,JSON_THROW_ON_ERROR); $ok=false; foreach ($j["packages"] as $v) { if ($v["name"] === "symfony/filesystem" && ltrim($v["version"],"v") === "7.4.23") $ok=true; } if (!$ok) exit(2); echo "Local PHAR signature and patched dependency metadata verified\n";'
    Invoke-Tool $toolPhp @('-r', $toolPharCheck, $toolComposer)
    Invoke-Tool $toolPhp @($toolComposer, 'audit', '--working-dir', $toolBuild, '--no-dev', '--no-plugins', '--no-scripts', '--no-interaction')
    Invoke-Tool $toolPhp @($toolComposer, 'diagnose', '--working-dir', $toolBuild, '--no-plugins', '--no-scripts', '--no-interaction')
    $toolReceipt = [ordered]@{ verified_at_utc = [DateTime]::UtcNow.ToString('o'); composer_source_commit = $toolManifest.composer_source.commit; composer_phar_sha256 = (Get-FileHash -LiteralPath $toolComposer -Algorithm SHA256).Hash; build_manifest_sha256 = (Get-FileHash -LiteralPath (Join-Path $toolInputs 'composer.json') -Algorithm SHA256).Hash; build_lock_sha256 = (Get-FileHash -LiteralPath (Join-Path $toolInputs 'composer.lock') -Algorithm SHA256).Hash; compiler_patch_sha256 = (Get-FileHash -LiteralPath $toolPatch -Algorithm SHA256).Hash; mode = $(if ($VerifyOnly) { 'verify-only' } else { 'provision-and-verify' }); artifacts = $toolManifest.artifacts }
    $toolReceipt | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath $toolReceiptPath -Encoding utf8
    Write-Host 'Isolated tools verified. No application dependencies or databases were installed.'
} finally {
    foreach ($toolName in $toolSavedEnvironment.Keys) { [Environment]::SetEnvironmentVariable($toolName, $toolSavedEnvironment[$toolName], 'Process') }
    Pop-Location
}
