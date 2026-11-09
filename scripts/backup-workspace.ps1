#Requires -Version 7.4
param(
    [ValidateSet('main', 'simulator', 'hardware')][string]$Workspace = 'main',
    [switch]$VerifyRestore
)
$ErrorActionPreference = 'Stop'
$backupRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
foreach ($backupFolder in @('.tools/backups', '.tools/secrets')) {
    $backupDirectory = Join-Path $backupRoot $backupFolder
    New-Item -ItemType Directory -Path $backupDirectory -Force | Out-Null
    $backupInfo = [IO.DirectoryInfo]::new($backupDirectory)
    $backupAcl = [IO.FileSystemAclExtensions]::GetAccessControl($backupInfo, [Security.AccessControl.AccessControlSections]::Access)
    $backupAcl.SetAccessRuleProtection($true, $false)
    foreach ($backupRule in $backupAcl.GetAccessRules($true, $true, [Security.Principal.SecurityIdentifier])) { $backupAcl.RemoveAccessRuleSpecific($backupRule) }
    foreach ($backupSid in @([Security.Principal.WindowsIdentity]::GetCurrent().User, [Security.Principal.SecurityIdentifier]::new('S-1-5-18'))) {
        $backupAcl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new($backupSid, 'FullControl', 'ContainerInherit,ObjectInherit', 'None', 'Allow'))
    }
    [IO.FileSystemAclExtensions]::SetAccessControl($backupInfo, $backupAcl)
}
$backupPreviousMarker = $env:NFCHECK_BACKUP_PREPARED
try {
    $env:NFCHECK_BACKUP_PREPARED = '1'
    $backupArguments = @((Join-Path $PSScriptRoot 'backup-workspace.php'), $Workspace)
    if ($VerifyRestore) { $backupArguments += '--verify-restore' }
    & (Join-Path $backupRoot '.tools/php/php.exe') @backupArguments
    if ($LASTEXITCODE -ne 0) { throw 'Backup/recovery verification failed; existing schemas were not replaced. Inspect private evidence.' }
} finally {
    $env:NFCHECK_BACKUP_PREPARED = $backupPreviousMarker
}
