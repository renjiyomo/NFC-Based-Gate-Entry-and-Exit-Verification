# NFCheck

**NFC-Based Campus Gate Entry and Exit Verification and Monitoring System with Data Analytics, Anomaly Detection, and AI-Generated Summaries for Bicol University Polangui**

Current checkpoint: **Phase 1 — project foundation and UI/UX completed. Phase 2 awaits explicit approval.**

Laravel, React, TypeScript, Inertia, Tailwind and Vite are connected. Explore [the local preview](http://127.0.0.1:8000/preview/dashboard) or [sign-in screen](http://127.0.0.1:8000/login) while the development server is running.

This checkpoint contains **interface shells**. Authentication, database-backed records, credential issuance, scanning, passage recording, statistics, exports and AI are unavailable. The UI labels these states honestly; it contains no invented operational records or counts. No MySQL instance/schema, migration or institutional data was created.

## Run locally

Use PowerShell 7.4+ in this project directory. The current workstation already has isolated tools, dependencies, a local environment and built assets prepared.

```powershell
# Start the loopback server if it is not already running. Ctrl+C stops it.
.\scripts\dev.ps1 serve

# Run the verification pipeline.
.\scripts\dev.ps1 check
```

The Phase 1 preview was left running in the background on port 8000. Open the link above without starting a second server. Future foreground sessions started with the launcher are stopped with Ctrl+C.

For a fresh checkout, individual tool commands and development with hot reload, see [development instructions](docs/development.md). Use the launcher: shared XAMPP PHP still has its old extension mismatch. Project tools live in ignored `.tools`; no global runtime or shared service was upgraded.

## Security boundary

Root `.htaccess` denies access through the original shared XAMPP `htdocs` route, including its `public` subdirectory. This is intentional. The patched loopback server serves only `public` at port 8000. Do not remove the denial to make the old URL work.

Preview routes require a local/testing environment, a loopback client and explicit `NFCHECK_UI_PREVIEW=true`; the example defaults it to false. Production rejects previews even if that flag is accidentally true. Preview controls do not replace Phase 2 authentication/authorization. No public deployment has occurred.

## Documents

| Document | Contents |
| --- | --- |
| [Phase 1 report](docs/phase-1-report.md) | Implemented foundation, actual checks, UI evidence, limitations and approval checkpoint |
| [Local development](docs/development.md) | Setup/startup, isolated commands, boundaries and troubleshooting |
| [Isolated toolchain](docs/toolchain.md) | Verified downloads, patched Composer build and reproducible provisioning |
| [Phase 0 report](docs/phase-0-report.md) | Original planning checkpoint and audit outcomes |
| [Environment audit](docs/environment-audit.md) | Historical installed environment and dated security research |
| [Requirements and permissions](docs/requirements.md) | Revised research scope and module/role boundaries |
| [Architecture](docs/architecture.md) | Responsibilities, verification versus passage, retries and concurrency |
| [Database proposal and ERD](docs/database.md) | Proposed entities/constraints; not implemented schema |
| [UI/UX research](docs/ui-ux.md) | Inspected reference, extracted design values and layout plan |
| [Security plan](docs/security.md) | Threat model, privacy and operational safeguards |
| [NFC plan](docs/nfc-integration.md) | Credential lifecycle, hardware limits and future test plan |
| [Roadmap](docs/roadmap.md) | Phase boundaries and acceptance gates |
| [Test/research plan](docs/testing-and-research.md) | Planned feature, hardware and research evaluation |

## Project rules

- Implement only the explicitly approved phase, then report and stop.
- Keep security decisions and business logic in Laravel; React handles presentation and interaction.
- Use synthetic development data until institutional processing is authorized.
- Never commit environment secrets, private photographs, database dumps or credential plaintext.
- A credential reference does not prove identity or physical passage. Later guard confirmation remains explicit; campus presence remains an estimate.
- Original logos and the old-title manuscript are preserved. The old manuscript is excluded from Git; the revised gate scope is authoritative.
