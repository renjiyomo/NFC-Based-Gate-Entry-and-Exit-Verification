# Local development and maintenance

Current update: **The user-approved [gate-account workflow](workflow-redesign.md) supersedes earlier individual-guard, scheduled-assignment, separate-grant and manual-confirmation instructions.** Migration 19 / 26 tables; 196 PHP tests / 2,062 assertions, 25 NFC tests and ten concurrency scenarios passed. Program/department catalogues and academic year/semester are implemented. New automatic-flow physical acceptance remains pending; no GitHub publication or Vercel deployment occurred.

Phases 1–11 are implemented/prepared for their approved local scopes. Credential issuance, controlled physical NFC, guard passage, monitoring/reports, anomaly review and synthetic Gemini Free Tier summaries are connected. Broader physical/institutional/production acceptance remains pending. Follow [AGENTS.md](../AGENTS.md), [final readiness](final-readiness.md), [operator guides](administrator-guide.md), [deployment preparation](deployment.md) and [backup/recovery](backup-and-recovery.md). Historical sections below describe earlier checkpoints.

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

Phase 9 adds administrator `/admin/summaries` (GET/POST), GET `/admin/summaries/{uuid}`, POST `.../reviews` and POST `.../recover`. Gemini Free Tier only is configured for synthetic lab schemas; the main workspace is excluded. Keep the Google project unlinked from billing. The private key/config is `.tools/secrets/ai-provider.json`, never a web form or committed environment value. Use `scripts/start-summary-worker.ps1 -Workspace simulator` or `hardware` to start/reuse a hidden dedicated worker; `scripts/dev.ps1 summary-worker simulator` runs once. These workers do not start automatically with Windows. [Phase 9 workflow, privacy, recovery and limits](phase-9-report.md).

Phase 8 adds administrator `/admin/anomalies`, POST `/admin/anomalies/detect`, GET `/admin/anomalies/{uuid}` and POST `/admin/anomalies/{uuid}/reviews`. Open **Run checks and inspect rules** to evaluate up to 31 days / 1,000 saved attempts. Detection is on demand; no scheduler or external notifications are installed. Local development thresholds are in `config/anomalies.php`; after-hours checks remain disabled pending campus hours. See [rules, review process and evaluation](phase-8-report.md).

Phase 7 connects administrator `/admin/dashboard`, `/admin/history`, `/admin/analytics` and `/admin/reports`. History details are `/admin/history/{passages|attempts}/{uuid}`. POST `/admin/history/passages/{uuid}/corrections` appends a reasoned note/void/reinstatement with a current revision. GET `/admin/reports/export` accepts `format=csv|xlsx|pdf` and `dataset=summary|passages|attempts` plus the same validated filters. Exports are private, audited and throttled; limits are 10,000 rows for CSV/Excel and 500 for PDF. See [definitions and checks](phase-7-report.md). These features are administrator-only. The PHP minimum is now 8.4; the prepared runtime remains 8.5.12.

- Guest: GET/POST `/login`, `/forgot-password`; GET `/reset-password/{token}`, POST `/reset-password`.
- Signed-in: POST `/logout`, GET/PUT `/account/password`.
- Administrators: connected `/admin/students`, `/admin/employees`, `/admin/gates`, `/admin/guards` resources and their create/detail/edit/archive/restore actions. Person photo/grant and gate/guard assignment endpoints are scoped to those records. `/admin/choices/{gates|guards}` supplies bounded searchable choices. Monitoring routes above are connected; anomaly/AI modules remain deferred.
- Guards: `/guard/scanner`, `/guard/activity`; `/guard/gates/{gate}/scanner` additionally requires the exact effective assignment and active gate. NFC verification, separate identity review, confirmation and recent activity are connected; real NFC hardware remains untested.
- Optional previews: `/preview/login`, `/preview/{page}`, disabled by default. Production always rejects previews.
- `/up`: lightweight application health, not database readiness.

## Credential management and simulator

The main administrator registry is `/admin/credentials`. Issue to an active person, choose an inventory label, and optionally set a validity end in Asia/Manila. Preparation returns an unrecoverable one-time JSON receipt. Write the NDEF text, perform a separate matching read-back, then activate within the 10-minute pending window. Hiding, reloading or leaving the issuance page clears the receipt; cancel the pending entry and issue again if it is lost. Never copy references into URLs, logs or files.

Normal replacement keeps the current tag until the new one activates. For loss, use **Revoke credential → Lost tag** first; revocation survives any subsequent replacement failure. Credential ownership and closed history are permanent. Person archives also close active/pending credentials. Expiry is enforced immediately during validation; `scripts/dev.ps1 artisan nfcheck:expire-credentials` additionally closes expired rows and clears selected pointers.

Run `scripts/dev.ps1 simulator` to prepare/reuse `nfcheck_simulator` and start the public-only listener on loopback port 8002. Open **http://localhost:8002/login** using the disposable account in ignored `.tools/secrets/simulator-account.json`. This account is unrelated to the three main administrators. Synthetic people/grants are created only in this schema; setup preserves existing lab records and never resets a schema. Two loopback hosts separate browser cookies: keep the main app on **127.0.0.1:8000**, lab on **localhost:8002**. Opening both on the same hostname can overwrite the common XSRF cookie; reload the affected workspace before a JSON write. Issuance receipts cannot survive such a reload.

Simulator routes require local/testing environment, loopback, the explicit flag and a simulation/testing schema. Production rejects them even with the flag. Routes fix the source server-side; normal verification rejects simulated references. The virtual tag lives only in tab memory across Inertia navigation and clears on reload, page hide, sign-out or explicit clearing. Only digests are stored in the database. Both transports call `CredentialVerifier`; neither Phase 4 inspection endpoint records a gate decision or passage.

`npm run test:nfc` checks the NDEF adapter using mock readers. These are software checks; a real NFC phone/tag and trusted HTTPS are still needed for physical acceptance. The full Phase 4 pipeline passed **109 tests / 1,193 assertions**, plus **6 adapter tests**. See [Phase 4 evidence and limits](phase-4-report.md).

## Troubleshooting and limits

- **403 on old localhost project/public/index.php:** intentional; use 127.0.0.1:8000.
- **Port occupied:** reuse the existing service or Ctrl+C in a foreground session you started. Never kill all PHP/MySQL processes.
- **Database unavailable:** start project services; do not repoint at XAMPP MariaDB.
- **Password change required:** expected for initial credentials.
- **Recovery mail absent:** check Mailpit and the local inbox; no external SMTP service is configured.
- **Checksum/audit fails:** investigate pinned inputs from official sources; do not bypass checks or TLS.

This is a local development installation. Production needs a separately reviewed deployment, trusted HTTPS/Secure cookies, real SMTP, backups/retention and operator procedures. Existing XAMPP audit findings remain unchanged. Physical NFC acceptance, full browser zoom, assistive-technology certification and production readiness are not established.

The Phase 3 pipeline passed 87 tests / 946 assertions. Browser QA used a separate loopback 8001 listener with disposable accounts in `nfcheck_testing`; those rows/photos and test credentials were removed afterward. The separate QA listener may still display a login screen without an operator account. Use **port 8000** for the normal application. Evidence and cleanup results are linked from the Phase 3 report.

## Phase 5 gate operations

The Phase 5 implementation is complete. Guards use `/guard/scanner`, select a current assigned gate, choose Entry/Exit and scan. A credential check creates an attempt only. Review the photograph or explicitly choose the trusted-campus-document fallback, check the identity/observed-passage assertion, then confirm. Identity mismatch/cancel/30-second expiry create no passage. Confirmation rechecks current state and revision. The user selected a 10-second duplicate window; a same-person/gate/direction/source confirmation within it is suppressed, even across guards and request IDs. At the boundary a new passage is permitted with any applicable sequence note.

Gate JSON endpoints are POST `/guard/gates/{gate}/scan`, GET `.../requests/{requestId}`, POST `.../attempts/{attempt}/review` and GET `.../attempts/{attempt}/photo`. The lab equivalents begin `/simulator/guard/gates`. Source is fixed by the route; simulated references cannot create physical events. Writes require JSON/CSRF and active guard/gate assignment; operation lookup additionally binds the same guard/session/auth revision. The URL may contain a non-secret request UUID for reload recovery, never a reference. Recover uncertain status before resending an exact request. Review retries never extend the deadline. No trusted offline decisions are queued.

`/guard/activity/feed` and station activity return at most 30 permitted attempts from the last 30 minutes; `/admin/history/feed` returns at most 50 latest attempts. The UI refreshes every 8 seconds while visible and after actions. Credential authorization, review state and passage are distinct. Full report filters/exports and dashboard aggregates remain later work.

Run `scripts/dev.ps1 simulator` for the isolated lab. The administrator file remains `.tools/secrets/simulator-account.json`; two separate guard accounts are in `.tools/secrets/simulator-guards.json`. Issue, simulate write/read-back, activate, then select **Hand virtual tag to a lab guard** and sign in as that guard in the same tab. Complete the first server check within two minutes to accept the handoff. The accepted tag stays in tab memory until reload/page hide/ordinary sign-out/clear. No role is impersonated and no plaintext is persisted. Refreshing discards the tag but a pending review can still recover by its request UUID.

Use `scripts/dev.ps1 concurrency` for the four real multi-process race scenarios; `check` includes it after the transaction-based PHP suite. The harness requires the exact isolated testing runtime, commits only owned synthetic fixtures for visibility to its workers, observes simultaneous blocked transactions and restores pre-run row counts by captured IDs through a private maintenance connection. Never run generic schema resets. The final Phase 5 suite passed 132 tests / 1,324 assertions, 8 adapter tests and all 4 concurrent scenarios.

`nfcheck:expire-reviews` materializes unattended review expiry; validity already applies on every request. No scheduler daemon was installed. See [Phase 5 report](phase-5-report.md) for evidence and hardware/policy limits. Earlier Phase 3/4 check totals above describe their historical checkpoints.

## Phase 6 phone and NFC workspace

The Phase 6 software checks passed 135 PHP tests / 1,368 assertions, 16 NFC adapter tests and all four concurrent MySQL scenarios. Real Samsung A52/NTAG215 acceptance is still pending; see [the current report](phase-6-report.md). Historical Phase 3–5 totals above remain their own checkpoints.

Use `scripts/dev.ps1 hardware` to prepare/reuse the separate `nfcheck_hardware` schema and public-only loopback listener on port 8003. It applies the existing 15 migrations and scoped runtime grants, creates only synthetic identities/gate/test operators, and preserves previous data/passwords. No credential or passage is generated by setup. `.env.hardware` has its own key, cookie and runtime password; private account entries `admin`, `guard1`, `guard2` are in ignored `.tools/secrets/hardware-accounts.json`. They are unrelated to the main administrators. Hardware routes require the explicit flag, local/testing environment and loopback. Simulator routes reject this schema.

The USB helper uses the existing Google-signed Android SDK ADB. The user must enable USB debugging and approve this laptop on the unlocked phone; File Transfer does not authorize debugging. Run `scripts/connect-phone.ps1 Connect`, then open **http://localhost:8003/login** in phone Chrome. Only the selected port is reversed to the loopback backend. No LAN listener, wireless debugging, public tunnel or certificate bypass is installed. `Status` reports authorization/owned mapping; `Disconnect` removes only this setup's mapping. Other processes/mappings remain untouched. The helper refuses conflicting listeners/mappings, multiple or non-USB devices and an unexpected ADB server.

The signed-in `/nfc/setup` route supports both roles. Permission-state queries never trigger the native prompt. Access and format-read checks require a button gesture; their reader stops on completion/cancel/timeout/hidden page and no backend event is created. The issuance and scanner screens use the same adapter with cancellation and redacted errors. Never claim RF success from API detection or desktop emulation.

The main app remains **127.0.0.1:8000**. The simulator and hardware lab both use **localhost** on different ports, so their common XSRF cookie may overwrite the other workspace in the same browser profile. Avoid simultaneous JSON workflows in these two labs; reload the affected page before a write if switching. Reloading/hiding issuance discards the one-time receipt. The phone's separate Chrome profile does not share the laptop's cookies.
