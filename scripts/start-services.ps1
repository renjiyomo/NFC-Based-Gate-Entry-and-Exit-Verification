#Requires -Version 5.1
param([switch]$Initialize)
$ErrorActionPreference = 'Stop'
$serviceRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$serviceTools = Join-Path $serviceRoot '.tools'
$serviceMysqlHome = Join-Path $serviceTools 'mysql/mysql-8.4.13-winx64'
$serviceMysql = Join-Path $serviceMysqlHome 'bin/mysqld.exe'
$serviceMailpit = Join-Path $serviceTools 'mailpit/mailpit.exe'
$servicePhp = Join-Path $serviceTools 'php/php.exe'

function Protect-LocalDirectory([string]$Path) {
    New-Item -ItemType Directory -Path $Path -Force | Out-Null
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent().User
    $directoryInfo = [IO.DirectoryInfo]::new($Path)
    $acl = if ($PSVersionTable.PSVersion.Major -ge 7) { [IO.FileSystemAclExtensions]::GetAccessControl($directoryInfo, [Security.AccessControl.AccessControlSections]::Access) } else { $directoryInfo.GetAccessControl([Security.AccessControl.AccessControlSections]::Access) }
    $acl.SetAccessRuleProtection($true, $false)
    foreach ($existingRule in $acl.GetAccessRules($true, $true, [Security.Principal.SecurityIdentifier])) { $acl.RemoveAccessRuleSpecific($existingRule) }
    foreach ($sid in @($identity, [Security.Principal.SecurityIdentifier]::new('S-1-5-18'))) {
        $acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new($sid, 'FullControl', 'ContainerInherit,ObjectInherit', 'None', 'Allow'))
    }
    if ($PSVersionTable.PSVersion.Major -ge 7) { [IO.FileSystemAclExtensions]::SetAccessControl($directoryInfo, $acl) } else { $directoryInfo.SetAccessControl($acl) }
}

foreach ($privateFolder in @('secrets','mysql-data','mail')) { Protect-LocalDirectory (Join-Path $serviceTools $privateFolder) }
Protect-LocalDirectory (Join-Path $serviceRoot 'storage/app/private/person-photos')
if (-not (Test-Path -LiteralPath $serviceMysql)) { throw 'Verified MySQL 8.4.13 package is required. See docs/database-setup.md.' }
if (-not (Test-Path -LiteralPath $serviceMailpit)) { throw 'Verified Mailpit 1.33.1 package is required. See docs/database-setup.md.' }
$mysqlIni = Join-Path $serviceTools 'mysql/my.ini'
$mysqlData = Join-Path $serviceTools 'mysql-data'
if (-not (Test-Path -LiteralPath $mysqlIni)) {
    @('[mysqld]',('basedir="'+$serviceMysqlHome.Replace('\','/')+'"'),('datadir="'+$mysqlData.Replace('\','/')+'"'),
      'port=3307','bind-address=127.0.0.1','mysqlx=OFF',"default-time-zone='+00:00'",'local-infile=OFF','secure-file-priv=NULL',
      'general-log=OFF','slow-query-log=OFF',('log-error="'+$mysqlData.Replace('\','/')+'/server.err"'),
      'max-connections=50','innodb-buffer-pool-size=67108864') | Set-Content -LiteralPath $mysqlIni -Encoding utf8
}
if (-not (Test-Path -LiteralPath (Join-Path $mysqlData 'mysql'))) {
    if (-not $Initialize) { throw 'Database is not initialized. Use -Initialize only for this new isolated instance.' }
    if (@(Get-ChildItem -Force -LiteralPath $mysqlData).Count) { throw 'Refusing to initialize a nonempty unknown data directory.' }
    & $serviceMysql "--defaults-file=$mysqlIni" --initialize
    if ($LASTEXITCODE -ne 0) { throw 'MySQL initialization failed; inspect the protected server error log.' }
}
$existingMysql = @(Get-NetTCPConnection -LocalPort 3307 -State Listen -ErrorAction SilentlyContinue)
if ($existingMysql.Count) {
    if (@($existingMysql | Where-Object LocalAddress -ne '127.0.0.1').Count) { throw 'The existing MySQL listener is not restricted to loopback.' }
    $existingProcess = Get-CimInstance Win32_Process -Filter "ProcessId=$($existingMysql[0].OwningProcess)"
    if ($existingProcess.ExecutablePath -ne $serviceMysql -or -not $existingProcess.CommandLine.Contains($mysqlIni)) { throw 'Port 3307 belongs to a different process; nothing was stopped.' }
} else {
    Start-Process -FilePath $serviceMysql -ArgumentList @("--defaults-file=`"$mysqlIni`"") -WorkingDirectory $serviceRoot -WindowStyle Hidden | Out-Null
}
for ($attempt = 0; $attempt -lt 80; $attempt++) {
    if (Get-NetTCPConnection -LocalPort 3307 -State Listen -ErrorAction SilentlyContinue) { break }
    Start-Sleep -Milliseconds 250
}
if (-not (Get-NetTCPConnection -LocalPort 3307 -State Listen -ErrorAction SilentlyContinue)) { throw 'MySQL did not become ready; inspect protected logs.' }
if ($Initialize) {
    & $servicePhp (Join-Path $PSScriptRoot 'provision-database.php')
    if ($LASTEXITCODE -ne 0) { throw 'Database/account provisioning failed.' }
}
foreach ($privateFile in @('.env', '.env.testing', '.env.simulator', '.env.hardware')) {
    $privatePath = Join-Path $serviceRoot $privateFile
    if (Test-Path -LiteralPath $privatePath) {
        $privateInfo = [IO.FileInfo]::new($privatePath)
        $fileAcl = if ($PSVersionTable.PSVersion.Major -ge 7) { [IO.FileSystemAclExtensions]::GetAccessControl($privateInfo, [Security.AccessControl.AccessControlSections]::Access) } else { $privateInfo.GetAccessControl([Security.AccessControl.AccessControlSections]::Access) }
        $fileAcl.SetAccessRuleProtection($true, $false)
        foreach ($existingRule in $fileAcl.GetAccessRules($true, $true, [Security.Principal.SecurityIdentifier])) { $fileAcl.RemoveAccessRuleSpecific($existingRule) }
        foreach ($fileSid in @([Security.Principal.WindowsIdentity]::GetCurrent().User, [Security.Principal.SecurityIdentifier]::new('S-1-5-18'))) {
            $fileAcl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new($fileSid, 'FullControl', 'Allow'))
        }
        if ($PSVersionTable.PSVersion.Major -ge 7) { [IO.FileSystemAclExtensions]::SetAccessControl($privateInfo, $fileAcl) } else { $privateInfo.SetAccessControl($fileAcl) }
    }
}
$existingMail = @(Get-NetTCPConnection -LocalPort 1025 -State Listen -ErrorAction SilentlyContinue)
if ($existingMail.Count) {
    if (@($existingMail | Where-Object LocalAddress -ne '127.0.0.1').Count) { throw 'The existing SMTP listener is not restricted to loopback.' }
    $mailProcess = Get-CimInstance Win32_Process -Filter "ProcessId=$($existingMail[0].OwningProcess)"
    if ($mailProcess.ExecutablePath -ne $serviceMailpit) { throw 'SMTP port 1025 belongs to another process.' }
} else {
    if (Get-NetTCPConnection -LocalPort 8025 -State Listen -ErrorAction SilentlyContinue) { throw 'Mail inbox port 8025 is already occupied.' }
    Start-Process -FilePath $serviceMailpit -ArgumentList @('--listen','127.0.0.1:8025','--smtp','127.0.0.1:1025','--database',('"'+(Join-Path $serviceTools 'mail/inbox.db')+'"'),'--disable-version-check','--max','100','--allowed-hosts','127.0.0.1,localhost') -WorkingDirectory $serviceRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $serviceTools 'mail/stdout.log') -RedirectStandardError (Join-Path $serviceTools 'mail/stderr.log') | Out-Null
}
for ($mailAttempt = 0; $mailAttempt -lt 40; $mailAttempt++) {
    $mailListeners = @(Get-NetTCPConnection -LocalPort 1025,8025 -State Listen -ErrorAction SilentlyContinue)
    if (@($mailListeners | Select-Object -ExpandProperty LocalPort -Unique).Count -eq 2) { break }
    Start-Sleep -Milliseconds 250
}
if (@($mailListeners | Select-Object -ExpandProperty LocalPort -Unique).Count -ne 2 -or @($mailListeners | Where-Object LocalAddress -ne '127.0.0.1').Count) { throw 'Mailpit must expose both SMTP and inbox only on loopback.' }
foreach ($mailOwner in @($mailListeners | Select-Object -ExpandProperty OwningProcess -Unique)) {
    if ((Get-CimInstance Win32_Process -Filter "ProcessId=$mailOwner").ExecutablePath -ne $serviceMailpit) { throw 'Mailpit ports do not belong to the expected executable.' }
}
Write-Host 'NFCheck MySQL: 127.0.0.1:3307; local mail inbox: http://127.0.0.1:8025. Existing XAMPP services unchanged.'
