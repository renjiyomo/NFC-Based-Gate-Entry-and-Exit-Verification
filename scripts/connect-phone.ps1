#Requires -Version 7.4
param([ValidateSet('Status', 'Connect', 'Disconnect')][string]$Action = 'Status')
$ErrorActionPreference = 'Stop'
$phoneRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$phoneAdb = Join-Path $env:LOCALAPPDATA 'Android/Sdk/platform-tools/adb.exe'
$phoneReceiptPath = Join-Path $phoneRoot '.tools/artifacts/phone-usb-forward.json'
if (-not (Test-Path -LiteralPath $phoneAdb)) { throw 'Android SDK platform-tools were not found. No software was installed.' }
$phoneSignature = Get-AuthenticodeSignature -LiteralPath $phoneAdb
if ($phoneSignature.Status -ne 'Valid' -or $phoneSignature.SignerCertificate.Subject -notmatch 'O=Google LLC') { throw 'ADB must have a valid Google signature.' }
foreach ($phoneListener in @(Get-NetTCPConnection -LocalPort 5037 -State Listen -ErrorAction SilentlyContinue)) {
    $phoneProcess = Get-CimInstance Win32_Process -Filter "ProcessId=$($phoneListener.OwningProcess)"
    if ($phoneProcess.ExecutablePath -ne $phoneAdb -or $phoneListener.LocalAddress -notin @('127.0.0.1', '::1')) { throw 'An unexpected ADB server is present. Nothing was restarted or stopped.' }
}
function Invoke-PhoneAdb([string[]]$PhoneArguments) {
    $phoneResult = @(& $phoneAdb @PhoneArguments 2>&1 | ForEach-Object { [string]$_ })
    if ($LASTEXITCODE -ne 0) { throw 'ADB could not finish the requested USB operation. Check the phone connection and authorization.' }
    return $phoneResult
}
$phoneDevices = @(Invoke-PhoneAdb @('devices', '-l') | Where-Object { $_ -match '^\S+\s+(device|unauthorized|offline)\b' })
if ($phoneDevices.Count -eq 0) { if ($Action -eq 'Connect') { throw 'No debugging device detected. Enable USB debugging and allow this laptop on the unlocked phone.' }; Write-Host 'No debugging device detected. Enable USB debugging and allow this laptop on the unlocked phone.'; return }
if ($phoneDevices.Count -ne 1) { throw 'Connect only the intended test phone, then retry. No forwarding was changed.' }
$phoneParts = $phoneDevices[0] -split '\s+'
if ($phoneParts[1] -ne 'device') { if ($Action -eq 'Connect') { throw "Phone debugging status: $($phoneParts[1]). Unlock the phone and allow this laptop, then retry." }; Write-Host "Phone debugging status: $($phoneParts[1]). Unlock the phone and allow this laptop, then retry."; return }
$phoneSerial = $phoneParts[0]
if ($phoneSerial -match '[:.]' -or ((Invoke-PhoneAdb @('-d', 'get-serialno')) -join '').Trim() -ne $phoneSerial) { throw 'Only a directly attached USB device is supported. No wireless debugging was enabled.' }
$phoneHash = [Convert]::ToHexString([Security.Cryptography.SHA256]::HashData([Text.Encoding]::UTF8.GetBytes($phoneSerial)))
$phoneProperties = @{}
foreach ($phoneProperty in @('ro.product.model', 'ro.build.version.release', 'ro.build.version.security_patch')) {
    $phoneProperties[$phoneProperty] = ((Invoke-PhoneAdb @('-s', $phoneSerial, 'shell', 'getprop', $phoneProperty)) -join '').Trim()
}
Write-Host "USB phone authorized: $($phoneProperties['ro.product.model']); Android $($phoneProperties['ro.build.version.release']); security patch $($phoneProperties['ro.build.version.security_patch'])."
$phoneMappings = @(Invoke-PhoneAdb @('-s', $phoneSerial, 'reverse', '--list'))
$phoneExisting = @($phoneMappings | Where-Object { $_ -match '\stcp:8003\s' })
$phoneReceipt = if (Test-Path -LiteralPath $phoneReceiptPath) { Get-Content -LiteralPath $phoneReceiptPath -Raw | ConvertFrom-Json } else { $null }
if ($phoneExisting.Count -and ($phoneExisting.Count -ne 1 -or $phoneExisting[0] -notmatch '\stcp:8003\s+tcp:8003\s*$' -or $phoneReceipt.deviceHash -ne $phoneHash)) { throw 'An existing port 8003 mapping is not owned by this setup. Nothing was changed.' }
if ($Action -eq 'Connect') {
    $phoneAppListeners = @(Get-NetTCPConnection -LocalPort 8003 -State Listen -ErrorAction SilentlyContinue)
    if ($phoneAppListeners.Count -ne 1 -or $phoneAppListeners[0].LocalAddress -ne '127.0.0.1') { throw 'The loopback-only hardware workspace is not ready. Run scripts/dev.ps1 hardware.' }
    $phoneAppProcess = Get-CimInstance Win32_Process -Filter "ProcessId=$($phoneAppListeners[0].OwningProcess)"
    if ($phoneAppProcess.ExecutablePath -ne (Join-Path $phoneRoot '.tools/php/php.exe') -or -not $phoneAppProcess.CommandLine.Replace('/', '\').Contains((Join-Path $phoneRoot 'server.php'))) { throw 'Port 8003 belongs to an unexpected process. Nothing was changed.' }
    if (-not $phoneExisting.Count) {
        Invoke-PhoneAdb @('-s', $phoneSerial, 'reverse', '--no-rebind', 'tcp:8003', 'tcp:8003') | Out-Null
        @{ deviceHash = $phoneHash; port = 8003; connectedAt = [DateTime]::UtcNow.ToString('o') } | ConvertTo-Json | Set-Content -LiteralPath $phoneReceiptPath -Encoding utf8NoBOM
    }
    $phoneVerifiedMappings = @(Invoke-PhoneAdb @('-s', $phoneSerial, 'reverse', '--list'))
    if (@($phoneVerifiedMappings | Where-Object { $_ -match '\stcp:8003\s+tcp:8003\s*$' }).Count -ne 1) { throw 'The USB mapping did not stay active. Keep the cable connected, then run Connect again. No Wi-Fi exposure was enabled.' }
    Write-Host 'USB port 8003 forwarding verified. Open Chrome on the phone: http://localhost:8003/login . Keep USB connected. Wi-Fi exposure is not enabled.'
} elseif ($Action -eq 'Disconnect') {
    if ($phoneExisting.Count) { Invoke-PhoneAdb @('-s', $phoneSerial, 'reverse', '--remove', 'tcp:8003') | Out-Null }
    Write-Host 'The owned USB port 8003 mapping is disconnected. Other mappings and the ADB server were left running.'
} else { Write-Host $(if ($phoneExisting.Count) { 'Owned USB port 8003 mapping is active.' } else { 'USB is authorized; port 8003 has not been forwarded yet.' }) }
