# Backup and recovery

The Phase 11 tool creates encrypted **local** backups and tests restoration into a new disposable schema on the verified private MySQL instance. It never replaces an existing schema. It is not an automated production disaster-recovery service or an off-device backup.

## Create and verify

From this workspace in PowerShell 7.4+:

```powershell
.\scripts\backup-workspace.ps1 -Workspace main -VerifyRestore
.\scripts\backup-workspace.ps1 -Workspace simulator -VerifyRestore
.\scripts\backup-workspace.ps1 -Workspace hardware -VerifyRestore
```

Omit `-VerifyRestore` to create/decrypt-check an archive without a restore drill. Only those three named workspaces and fixed 127.0.0.1:3307/MySQL 8.4.13/project data directory are accepted. Run during a quiet interval with no schema migration; no service is stopped automatically. All source tables must use InnoDB. The tool uses `mysqldump --single-transaction --quick --skip-lock-tables --no-tablespaces --set-gtid-purged=OFF --hex-blob`, without CREATE DATABASE/USE selection. The temporary client option file keeps its maintenance password out of process arguments and shell history. Root is used only by this guarded local maintenance CLI, never by the web application.

Outputs live in ignored `.tools/backups`: a uniquely named ZIP and sanitized evidence JSON. The wrapper restricts that directory to the current Windows account and SYSTEM. Every archive entry, including its hash manifest, uses ZIP AES-256 with a generated 32-byte random recovery key stored in private `.tools/secrets/backup-key.txt`. The password is never printed. This is standard ZIP encryption via [PHP ZipArchive](https://www.php.net/manual/en/ziparchive.setencryptionname.php); archive filenames/entry names remain visible metadata, so the archive also needs access control. This tool caps total uncompressed entries at 64 MiB; larger deployments need a reviewed streaming backup system.

The package includes the full selected schema, its matching environment (including APP_KEY/runtime credentials), and the normalized private photos referenced by that schema. It excludes database root/migrator secrets, the Gemini key, other workspace environments, logs, Mailpit mail and unrelated files. Treat the entire package as sensitive. Do not send it through chat/email or place it under public storage. Preserve the matching application release/lockfiles separately. Provider and maintenance credentials need separate approved secret recovery; they are not recovered from this archive.

On verification, the encrypted manifest and SHA-256 of every entry are checked before SQL is used. No arbitrary archive paths are extracted. A new `nfcheck_restore_<random>` schema is created; it has no runtime user grants or web listener. Every restored table's ordered rows are hashed and compared to source fingerprints before/after backup. Source changes or mismatches fail the drill. The original schema is read only. Only the exact newly created schema is dropped afterward, and only captured temporary plaintext filenames are removed. A crash may leave a private work directory or a drill schema: an operator must verify its ownership/name before any cleanup, never use wildcard DROP or recursive deletion on computed paths.

## Completed drills and limits

November 9, 2026: main, simulator and hardware each restored all **24 tables** with identical row fingerprints and unchanged source, verified the matching environment bytes, removed the owned drill schema and cleaned temporary plaintext. There were no private photo rows in these three workspaces, so real photo restoration was not exercised. Archive tests cover correct/wrong keys, overwrite refusal, invalid paths, unencrypted archives and changed-content manifest mismatch. Sanitized receipts are in [Phase 11 recovery evidence](evidence/phase-11-recovery.json).

The measured local drill duration is not a campus RTO. No schedule, off-device copy, retention purge, key escrow, host-loss exercise or production restore has been performed. Current archives and key are on the same laptop: loss/compromise of it can affect both. The institution must choose RPO/RTO, authorized backup operators, encrypted off-device locations, separate key escrow, rotation, retention/holds and periodic restore drills. Do not invent a retention period or automatically delete existing records/backups.

## Actual recovery runbook — requires a chosen target

1. Declare the incident, preserve evidence, stop application writes under the approved outage procedure and identify the exact recovery point/owner. Do not restore into the current live schema in place. Assess credential compromise and physical gate continuity separately.
2. Obtain the trusted encrypted archive, its separate recovery key, matching release and original APP_KEY through the approved private handoff. Verify the recorded archive SHA-256 and use `App\Support\RecoveryArchive::read` in an isolated operator-controlled environment; never print returned SQL/environment contents. Reject failed decryption, manifest mismatch or unexpected entries.
3. Provision a **new isolated recovery database and private file directory**, with target identity verified. Import verified database.sql using a maintenance account into that new schema, not the source schema. Restore the approved photo entries outside public storage; verify their hashes and database references. Retain the original APP_KEY to interpret existing encrypted data. Rotate runtime/maintenance credentials as appropriate without silently replacing that application key.
4. Verify migration ledger/schema, row fingerprints/counts, foreign-key relationships, selected credential ownership, event/correction totals and source separation. Reapply the narrow runtime grants; a logical dump does not back up MySQL users/grants. Before reopening, revoke old sessions and password-reset tokens through an approved narrowly scoped maintenance action. Keep verification unavailable until post-backup revocations, account disablements, access grants and assignment changes are reconciled: restoring an older snapshot must not make a lost/compromised reference eligible again. Keep AI disabled and reconcile its quota/claims; never automatically retry claimed tasks or pending NFC issuance. Close uncertainty safely and retain history.
5. Establish the loss window from the backup and approved RPO, reconcile later manual/immutable records with accountable staff, test authenticated/private/photo/report and synthetic staging flows, then approve traffic cutover. Missing events must not be invented or represented as observed scans. Keep the original database preserved until the incident owner approves disposition.

The current helper deliberately automates only creation and the disposable **local drill**, not live replacement or arbitrary target import. Host-specific recovery commands and recovery-key escrow must be finalized after production target selection. See [deployment](deployment.md) and [outage procedures](security-guard-guide.md#outages-and-uncertain-results).
