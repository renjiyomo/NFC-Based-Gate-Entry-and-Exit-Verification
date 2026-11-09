# NFCheck

The [current interface](docs/interface-redesign.md) uses the portfolio's charcoal/paper/amber palette, local Instrument Sans fonts, circular light/dark switching, cleaner account/navigation controls, responsive directories/forms and request-driven skeletons. Latest checks: 210 PHP tests / 2,313 assertions, 33 frontend tests and 11 concurrency scenarios.

**NFC-Based Campus Gate Entry and Exit Verification and Monitoring System with Data Analytics, Anomaly Detection, and AI-Generated Summaries for Bicol University Polangui**

Current workflow: **One username/password account per gate, automatic NFC Entry/Exit recording, database-backed BU Polangui academic dropdowns, and no separate campus-access grants or guard shift forms.** Two roles: Administrator and Gate account. See [the current workflow](docs/workflow-redesign.md). Earlier phase reports remain historical evidence.

Directory lists now include program/year and department dropdowns. Repeated-direction and overnight/missing-scan cases record with visible warnings; lost coins are revoked immediately before replacement. See [the decision table and lost-coin procedure](docs/scan-edge-cases.md).

Open [NFCheck sign-in](http://127.0.0.1:8000/login). Create the gate and login together; register an active person; check/write/read-back/activate a coin; then use the gate account to choose Entry or Exit and scan. Valid scans save automatically. Gate status controls its login; university guard schedules stay outside NFCheck. Existing histories and active coins are retained.

The [isolated NFC simulator](http://localhost:8002/login) uses its own schema, key, cookie, administrator, gate account, synthetic people and lab gate. Current username/password handoff for every workspace is in private `.tools/secrets/operator-accounts.json`. Older individual-guard handoffs are historical. Issue/write/activate a virtual tag, hand it to the lab gate account and sign in as `sim-gate-01` in the same tab. Use **localhost:8002** for the lab and **127.0.0.1:8000** for the main app to separate browser cookies. See [the Phase 5 report](docs/phase-5-report.md) and [NFC instructions](docs/nfc-integration.md).

For actual phone/tag tests, use the separate [hardware workspace](http://localhost:8003/login). Run `scripts/dev.ps1 hardware`, enable USB debugging and authorize this laptop on the phone, then run `scripts/connect-phone.ps1 Connect`. Open the same URL in **phone Chrome**, sign in with gate username hw-gate-01 from private `.tools/secrets/operator-accounts.json`, and open **NFC setup**. File Transfer alone does not authorize debugging. Setup preserves main/simulator records; mock/browser emulation checks do not count as physical NFC evidence. See [Phase 6 status and test procedure](docs/phase-6-report.md).

Issuance now follows **check coin → choose person → write checked coin → matching read-back → activate**. A coin carrying an active/pending reference must be explicitly retired before reuse. The writer checks the presented destination again before writing. The registry defaults to current references, with closed records under **View all history**; people lists show NFC status separately. Writing or erasing a coin outside NFCheck cannot update its database record automatically. See [coin handling and recovery](docs/nfc-integration.md#coin-handling-and-recovery).

The three requested local administrators are **alexi@nfcheck.test**, **pauleen@nfcheck.test**, and **zaidy@nfcheck.test**. Each has a different generated initial password in the ignored, Windows-access-restricted `.tools/secrets/initial-accounts.json`. A personal password is required on first sign-in. Passwords are never committed or printed in reports.

Recovery messages arrive only in the [local mail inbox](http://127.0.0.1:8025). There is no external email relay.

## Final checkpoint and guides

Start with [final readiness](docs/final-readiness.md), the [administrator guide](docs/administrator-guide.md) and [security guard guide](docs/security-guard-guide.md). Deployment/HTTPS templates, environment/logging reference and encrypted local backup/restore procedures are prepared. The latest whole check passed **210 PHP tests / 2,313 assertions, 33 frontend tests and 11 concurrency scenarios**. No public deployment or additional live AI request occurred. See [Phase 11](docs/phase-11-report.md) for the earlier deployment-preparation checkpoint.

## Run locally

Use PowerShell 7.4+ in this directory. The current workstation has tools, services, dependencies, schemas and built assets prepared; its application server is already running on port 8000.

```powershell
# Start/reuse the project's matching loopback services when needed.
.\scripts\dev.ps1 services

# Prepare/start or reuse the dedicated local NFC lab.
.\scripts\dev.ps1 simulator

# Start only when port 8000 is free. Ctrl+C stops a foreground session.
.\scripts\dev.ps1 serve

# Verify dependencies, style, TypeScript, build, and isolated tests.
.\scripts\dev.ps1 check
```

See [development instructions](docs/development.md) and [database setup](docs/database-setup.md) for a fresh checkout. Tools, MySQL data, mail and secrets live under ignored `.tools`. Shared XAMPP PHP, Apache, MariaDB and unrelated projects/databases remain unchanged.

## Security boundary

The original Apache `htdocs` URL, including `public/index.php`, intentionally returns **403**. Root `.htaccess` protects source and secrets. Use **127.0.0.1:8000**, which serves only `public`. Do not remove that denial to restore the old URL.

Normal workspace routes require authentication and server-side authorization. Optional `/preview/*` routes require a local/testing environment, loopback client and explicit preview flag. They are disabled on this workstation and always rejected in production. No public deployment occurred.

## Documents

| Document | Contents |
| --- | --- |
| [Current workflow](docs/workflow-redesign.md) | Gate accounts, automatic NFC scans, academic catalogues and current login handoff |
| [Scan edge cases](docs/scan-edge-cases.md) | Directory dropdowns, repeated/overnight scans, feedback and lost-coin recovery |
| [Workflow verification](docs/workflow-redesign-report.md) | Current tests, browser checks, preserved records and recovery receipts |
| [Final readiness](docs/final-readiness.md) | Scope reconciliation, evidence and campus-use acceptance inputs |
| [Phase 11 report](docs/phase-11-report.md) | Deployment preparation, encrypted recovery drills and operator guides |
| [Deployment](docs/deployment.md) | Unapplied HTTPS/release/migration templates and target requirements |
| [Backup and recovery](docs/backup-and-recovery.md) | Private encrypted backups, isolated restore checks and incident runbook |
| [Environment and operations](docs/environment-and-operations.md) | Variables, logs, retention, monitoring and ownership |
| [Administrator guide](docs/administrator-guide.md) | Accounts, people, coins, reporting and review |
| [Guard guide](docs/security-guard-guide.md) | NFC identity/passage workflow and outage recovery |
| [Phase 10 report](docs/phase-10-report.md) | Security findings/fixes, concurrency, performance, browser checks and remaining limits |
| [Phase 9 report](docs/phase-9-report.md) | Free Tier aggregate summaries, generation jobs, privacy and live evidence |
| [Phase 8 report](docs/phase-8-report.md) | Explainable rules, evidence, human review and synthetic evaluation |
| [Phase 7 report](docs/phase-7-report.md) | Monitoring, history, corrections, exports and validation |
| [Phase 6 report](docs/phase-6-report.md) | NFC integration, USB setup, verified software and pending physical acceptance |
| [Phase 5 report](docs/phase-5-report.md) | Guard review/confirmation, retry recovery, duplicate protection, concurrent tests and evidence |
| [Phase 4 report](docs/phase-4-report.md) | Credential lifecycle, isolated simulator, validation and UI evidence |
| [Phase 3 report](docs/phase-3-report.md) | Connected management workflows, schema changes, tests and UI evidence |
| [Phase 2 report](docs/phase-2-report.md) | Features, schema, checks, evidence and checkpoint |
| [Development](docs/development.md) | Setup/startup, commands, routes and troubleshooting |
| [Database setup](docs/database-setup.md) | Service provenance, privileges and private credentials |
| [Toolchain](docs/toolchain.md) | Verified runtimes and patched Composer build |
| [Database design](docs/database.md) | Implemented subset and future ERD proposal |
| [Phase 1 report](docs/phase-1-report.md) | Historical foundation/UI checkpoint |
| [Phase 0 report](docs/phase-0-report.md) | Original planning and environment audit |
| [Requirements](docs/requirements.md) | Scope and module/role boundaries |
| [Architecture](docs/architecture.md) | Verification versus passage and concurrency |
| [UI/UX research](docs/ui-ux.md) | Reference research and design system |
| [Security plan](docs/security.md) | Threat model and operational safeguards |
| [NFC plan](docs/nfc-integration.md) | Credential lifecycle and hardware limits |
| [Roadmap](docs/roadmap.md) | Phase boundaries and acceptance gates |
| [Test/research plan](docs/testing-and-research.md) | Planned hardware and research evaluation |

## Project rules

- Implement only the explicitly approved phase, then report and stop.
- Keep security decisions and business logic in Laravel; React handles presentation.
- Never commit secrets, private photographs, database dumps or credential plaintext.
- A credential reference does not prove identity or physical passage. Later guard confirmation remains explicit; campus presence remains an estimate.
- Preserve original branding and the old-title manuscript. The revised gate scope is authoritative.
