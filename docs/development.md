# Local development — Phase 2

Authentication and the database foundation are implemented. People/gate CRUD, credential issuance, scanning and passage recording require later phase approvals. Follow [AGENTS.md](../AGENTS.md).

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

Provisioning and access maintenance are CLI-only in Phase 2. For reviewed maintenance, `nfcheck:account-access {email} --role=guard --status=active` calls the account service, invalidates old sessions on their next request, records an audit event and protects the last active administrator. There is no public registration. Do not bypass the service with bulk SQL changes to roles/passwords/status.

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
- Administrators: `/admin/dashboard` and allowlisted `/admin/{page}` shells.
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
