# NFCheck

**NFC-Based Campus Gate Entry and Exit Verification and Monitoring System with Data Analytics, Anomaly Detection, and AI-Generated Summaries for Bicol University Polangui**

Current checkpoint: **Phase 5 — assigned-guard verification and confirmed Entry/Exit recording completed. Awaiting approval for Phase 6.**

Open [NFCheck sign-in](http://127.0.0.1:8000/login). Account security, people/gates/guards, access grants, credential lifecycles and assigned-guard verification are connected to isolated Oracle MySQL. Guards choose Entry or Exit, verify a reference, review identity and explicitly confirm observed passage. Retries recover the same operation, duplicate passages are suppressed and recent permitted activity refreshes. Physical NFC hardware remains untested; monitoring analytics, exports and AI remain later phases.

The [isolated NFC simulator](http://localhost:8002/login) uses its own schema, key, cookie, administrator, two guard accounts, synthetic people and lab gate. Administrator sign-in details are in `.tools/secrets/simulator-account.json`; separate guard details are in `.tools/secrets/simulator-guards.json`. Issue/write/activate a virtual tag, then use **Hand virtual tag to a lab guard** and sign in as the guard in the same tab. Use **localhost:8002** for the lab and **127.0.0.1:8000** for the main app to separate browser cookies. See [the Phase 5 report](docs/phase-5-report.md) and [NFC instructions](docs/nfc-integration.md).

The three requested local administrators are **alexi@nfcheck.test**, **pauleen@nfcheck.test**, and **zaidy@nfcheck.test**. Each has a different generated initial password in the ignored, Windows-access-restricted `.tools/secrets/initial-accounts.json`. A personal password is required on first sign-in. Passwords are never committed or printed in reports.

Recovery messages arrive only in the [local mail inbox](http://127.0.0.1:8025). There is no external email relay.

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
