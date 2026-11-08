#Requires -Version 7.4
param([switch]$VerifyOnly)
$ErrorActionPreference = 'Stop'
if (-not $IsWindows) { throw 'The isolated services package supports Windows x64.' }
$packageRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$packageTools = Join-Path $packageRoot '.tools'
$packages = Get-Content -Raw -LiteralPath (Join-Path $PSScriptRoot 'service-artifacts.json') | ConvertFrom-Json
New-Item -ItemType Directory -Path (Join-Path $packageTools 'downloads') -Force | Out-Null
foreach ($name in @('mysql', 'mailpit')) {
    $package = $packages.$name
    $archive = Join-Path $packageTools "downloads/$name.zip"
    if (-not (Test-Path -LiteralPath $archive)) {
        if ($VerifyOnly) { throw "Missing verified archive: $name" }
        Invoke-WebRequest -Uri $package.url -OutFile $archive
    }
    if ((Get-FileHash -LiteralPath $archive -Algorithm SHA256).Hash -ne $package.sha256) { throw "Package hash mismatch: $name" }
    $destination = Join-Path $packageTools $name
    $executable = if ($name -eq 'mysql') { Join-Path $destination 'mysql-8.4.13-winx64/bin/mysqld.exe' } else { Join-Path $destination 'mailpit.exe' }
    $zip = [IO.Compression.ZipFile]::OpenRead($archive)
    try {
        foreach ($entry in $zip.Entries) {
            $entryTarget = [IO.Path]::GetFullPath((Join-Path $destination $entry.FullName))
            if (-not $entryTarget.StartsWith($destination + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) { throw 'Archive path escapes the service directory.' }
        }
        if (-not (Test-Path -LiteralPath $executable)) {
            if ($VerifyOnly) { throw "Missing installed service: $name" }
            if ((Test-Path -LiteralPath $destination) -and @(Get-ChildItem -Force -LiteralPath $destination).Count) { throw "Refusing to overwrite an unknown service directory: $destination" }
            Expand-Archive -LiteralPath $archive -DestinationPath $destination
        }
        foreach ($entry in $zip.Entries | Where-Object { $_.Name -in @('mysqld.exe', 'mysql.exe', 'mailpit.exe') }) {
            $stream = $entry.Open()
            try { $expectedHash = [Convert]::ToHexString([Security.Cryptography.SHA256]::HashData($stream)) } finally { $stream.Dispose() }
            $installedPath = Join-Path $destination $entry.FullName
            if ((Get-FileHash -LiteralPath $installedPath -Algorithm SHA256).Hash -ne $expectedHash) { throw "Installed service differs from verified archive: $($entry.Name)" }
        }
    } finally { $zip.Dispose() }
    Write-Host "Verified $name $($package.version) archive and service executable."
}
if (-not $VerifyOnly) { & (Join-Path $PSScriptRoot 'start-services.ps1') -Initialize }
