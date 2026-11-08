# Local development — Phase 3

Authentication, student/employee management, private photos, gates, guards, access grants and gate assignments are implemented. Credential issuance, scanning and passage recording require later phase approvals. Follow [AGENTS.md](../AGENTS.md) and [the current report](phase-3-report.md).

## Current workstation

Use [sign-in](http://127.0.0.1:8000/login) and the [local recovery inbox](http://127.0.0.1:8025). The application and isolated services are running. Do not start a duplicate application server.

```powershell
.\scripts\dev.ps1 services
.\scripts\dev.ps1 serve
```

The service command reuses matching processes and refuses ports owned by another program. It does not stop processes. The application binds only to 127.0.0.1:8000 and refuses port fallback. Future foreground sessions stop with Ctrl+C.

## Fresh checkout

Use Windows x64 and PowerShell 7.4+, from the project root. Verify root `.htaccess` remains in place and the old Apache project URL is denied. Never serve the project root.

```powershell
pwsh -File .\scripts\bootstrap-tools.ps1
.\scripts\dev.ps1 composer install --no-interaction --prefer-dist
.\scripts\dev.ps1 npm ci

# Preserve existing environments and keys.
if (-not (Test-Path -LiteralPath .env)) {
    Copy-Item -LiteralPath .env.example -Destination .env
    .\scripts\dev.ps1 artisan key:generate
}

# Pinned service packages, new private instance, fixed schemas.
pwsh -File .\scripts\bootstrap-services.ps1
.\scripts\dev.ps1 migrate

# Provision each account ONCE; initial passwords go to a private handoff file.
.\scripts\dev.ps1 artisan nfcheck:provision-user alexi@nfcheck.test --name=Alexi --role=admin --generate
.\scripts\dev.ps1 artisan nfcheck:provision-user pauleen@nfcheck.test --name=Pauleen --role=admin --generate
.\scripts\dev.ps1 artisan nfcheck:provision-user zaidy@nfcheck.test --name=Zaidy --role=admin --generate

.\scripts\dev.ps1 build
.\scripts\dev.ps1 check
.\scripts\dev.ps1 serve
```

Existing emails are rejected rather than replaced. Do not regenerate keys, delete data directories or run `migrate:fresh`/`db:wipe` during routine startup. The migration helper verifies the private instance, uses a separate migration account and applies runtime grants afterward. See [database setup](database-setup.md).

## Accounts

Open `.tools/secrets/initial-accounts.json` privately to copy an initial password. All three requested accounts are active administrators. First sign-in requires a personal password of 15–128 characters; passphrases are accepted. This handoff stores initial credentials only and does not track passwords after a user changes them.

Recovery mail stays in Mailpit on this machine. Reset links expire after 30 minutes and work once. A reset ends other sessions and returns to sign-in.

Administrator provisioning/access maintenance remains the reviewed CLI workflow. `nfcheck:account-access {email} --role=guard --status=active` calls the account service, invalidates old sessions on their next request, records an audit event and protects the last active administrator. Relevant CLI changes also invalidate stale guard-edit forms. There is no public registration. Do not bypass services with bulk SQL changes to roles/passwords/status.

Create guard accounts through **Security personnel → Add security guard**. The guard receives a password setup link; no administrator role or gate assignment is granted. Delivery failures leave the account available for retry and remove the undelivered token. Recovery/setup mail stays in local Mailpit on this workstation.

## Management workflows

- Students/employees have separate identity/profile forms. Record status and campus-access grants are distinct. Save a record, then use its details page to grant or revoke a time-bounded authorization.
- Search/filter/pagination query MySQL. Current, archived and all-record views are available. Campus numbers are unique within classification, including archives; gate codes and account emails remain unique across archives.
- Upload an upright JPEG/PNG/WebP image, at most 2 MB and 4096 × 4096 pixels. Photos are re-encoded to private JPEGs with metadata removed and at most an 800px longest edge. Current normalization does not correct EXIF orientation.
- Active guards and gates can receive schedules. Choose times in Asia/Manila; blank end means no scheduled expiry. Same-guard/same-gate overlaps are rejected. Replacing a schedule retains the old revoked record.
- Archive operations retain details/history and revoke grants or assignments. Restore returns a record as inactive. Ordinary deactivation blocks current access without erasing grant/schedule history; reactivation may make an otherwise valid retained period apply again.
- Stale edit versions are rejected instead of overwriting newer work. Reload the latest record and review changes before saving again.

Photos are never served through public storage. Their controller requires administrator authorization and private/no-store headers. Service startup protects the local photo directory with Windows user/SYSTEM ACLs. Do not run `storage:link` to expose private files.

## Hot reload and checks

Run `scripts/dev.ps1 serve` and `scripts/dev.ps1 vite` in separate terminals. Vite is loopback-only on 5173 with the intended 8000 origin. If an ignored `public/hot` remains after Vite stops, remove that stale file and rebuild. Reload after a production build.

| Command | Purpose |
| --- | --- |
| `pwsh -File .\scripts\bootstrap-tools.ps1 -VerifyOnly` | Verify runtime/Composer provenance |
| `pwsh -File .\scripts\bootstrap-services.ps1 -VerifyOnly` | Verify service archives and executables |
| `.\scripts\dev.ps1 services` | Start/reuse loopback services |
| `.\scripts\dev.ps1 migrate` | Pending migrations in both fixed schemas and scoped runtime grants |
| `.\scripts\dev.ps1 artisan route:list` | Inspect routes |
| `.\scripts\dev.ps1 composer audit` / `npm audit` | Dependency advisories |
| `.\scripts\dev.ps1 build` | Strict TypeScript and Vite build |
| `.\scripts\dev.ps1 format --test` | PHP style; omit --test to format |
| `.\scripts\dev.ps1 npm run format:check` | Frontend style; use format to format |
| `.\scripts\dev.ps1 test` | Unit and isolated MySQL feature tests |
| `.\scripts\dev.ps1 check` | Full validation/audits/style/build/test pipeline |

Database tests require services, migrations and ignored `.env.testing`. Before transactions, they verify testing environment, driver, host/port, test username/schema and server version. Fixtures roll back; the main application receives no synthetic people/gates. Hashing costs are reduced only in testing.

The launcher restores PATH/Composer environment changes after use. MySQL/Mailpit run as hidden project processes, not installed Windows services. Database files survive process restarts.

## Routes

- Guest: GET/POST `/login`, `/forgot-password`; GET `/reset-password/{token}`, POST `/reset-password`.
- Signed-in: POST `/logout`, GET/PUT `/account/password`.
- Administrators: connected `/admin/students`, `/admin/employees`, `/admin/gates`, `/admin/guards` resources and their create/detail/edit/archive/restore actions. Person photo/grant and gate/guard assignment endpoints are scoped to those records. `/admin/choices/{gates|guards}` supplies bounded searchable choices. Dashboard and later modules retain their honest unavailable states.
- Guards: `/guard/scanner`, `/guard/activity`; `/guard/gates/{gate}/scanner` additionally requires the exact effective assignment and active gate. NFC actions remain unavailable.
- Optional previews: `/preview/login`, `/preview/{page}`, disabled by default. Production always rejects previews.
- `/up`: lightweight application health, not database readiness.

## Troubleshooting and limits

- **403 on old localhost project/public/index.php:** intentional; use 127.0.0.1:8000.
- **Port occupied:** reuse the existing service or Ctrl+C in a foreground session you started. Never kill all PHP/MySQL processes.
- **Database unavailable:** start project services; do not repoint at XAMPP MariaDB.
- **Password change required:** expected for initial credentials.
- **Recovery mail absent:** check Mailpit and the local inbox; no external SMTP service is configured.
- **Checksum/audit fails:** investigate pinned inputs from official sources; do not bypass checks or TLS.

This is a local development installation. Production needs a separately reviewed deployment, trusted HTTPS/Secure cookies, real SMTP, backups/retention and operator procedures. Existing XAMPP audit findings remain unchanged. No physical phone/tag, full browser zoom, assistive-technology certification or production readiness is claimed.

The Phase 3 pipeline passed 87 tests / 946 assertions. Browser QA used a separate loopback 8001 listener with disposable accounts in `nfcheck_testing`; those rows/photos and test credentials were removed afterward. The separate QA listener may still display a login screen without an operator account. Use **port 8000** for the normal application. Evidence and cleanup results are linked from the Phase 3 report.
