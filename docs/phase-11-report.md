# Phase 11 — deployment preparation and final documentation

Completed **November 9, 2026**, for preparation and controlled local recovery testing. User authorization was to continue to the next phase; it did not authorize public deployment. The final readiness decision is **local demonstration/synthetic testing ready; production and institutional acceptance pending**. See [the detailed readiness review](final-readiness.md).

## Delivered

- Unapplied production environment and Nginx/PHP-FPM templates, HTTPS/phone/proxy requirements, staged release/migration/rollback strategy and environment reference. Actual hosting, DNS, TLS and mail configuration remain unchosen. Existing migration target guards are preserved.
- Read-only `nfcheck:check-deployment --json`: ten configuration findings without secret output, DB queries or network calls. It intentionally reports the current loopback/lab installation as unsuitable for production configuration. It is not a full readiness certificate.
- Guarded PowerShell/PHP local backup tool: private ACLs, single-transaction logical dump, AES-256 encrypted entries/manifest/environment, referenced private photos, digest verification and a new disposable-schema restore option. No existing schema is a restore target. The recovery key is private and never printed.
- Successful restore drills for all three operating workspaces, each checking all 24 table row fingerprints against unchanged source, then removing only the owned drill schema and captured plaintext work files. Main 70 rows / 2.362 s; simulator 235 rows / 2.312 s; hardware 240 rows / 2.279 s. These small local durations are not campus RTOs. No workspace had a private photo row, so real-photo recovery remains unexercised. [Sanitized receipts](evidence/phase-11-recovery.json).
- Administrator/guard instructions for accounts, independent person/access/NFC status, coin reuse/replacement, explicit identity/passage review, uncertain-response recovery, history/corrections/reports/anomalies/AI and operational escalation. Recovery documentation explicitly requires reconciling later revocations before reopening after an old snapshot.
- Logging, retention, incident, key escrow and backup-owner responsibilities; updated database/NFC/development/research references and a requirement-by-requirement final scope review. Phase indicator now displays 11.

## Actual verification

`scripts/dev.ps1 check` passed **196 PHP tests / 2,151 assertions**, **25 NFC adapter tests** and **eight real MySQL concurrency scenarios**. TypeScript/production build, formatting, Composer validation and Composer/npm audits passed with no reported known vulnerabilities. New tests cover archive encryption/round-trip/wrong key/overwrite refusal/path rejection/plaintext rejection/integrity mismatch and configuration preflight safety. A first test cleanup warning was fixed; the final full check has no test warnings/failures. After strengthening environment-snapshot consistency, seven targeted archive/preflight tests / 23 assertions and a repeated main restore drill passed.

All four runtime accounts still reject the 23 immutable-table privilege probes and lack DDL privileges. Final read-only schema verification confirms the main/lab records and all 18 migrations are preserved; testing fixtures and quota values are restored. Source/private-value and NFC-pattern log scans passed. The encrypted backup key is additionally checked against reviewable files before commit. No secret/archive/plaintext dump is committed.

No new UI flow was introduced beyond the server phase indicator; targeted browser evidence remains in Phase 10. Nginx is a reviewed illustration, **not installed or syntax-tested on this Windows host**. No public server, firewall/DNS change, paid service, production SMTP, scheduler, account reset, live AI request or summary-worker restart occurred. No new migration or persistent runtime grant was added. Only the newly owned restore schemas were created and removed.

## Files and retained limitations

Code: `RecoveryArchive`, `DeploymentChecks`, `CheckDeployment`, `scripts/backup-workspace.ps1/php`, associated tests and phase props. Templates: `deploy/production.env.example`, `deploy/nginx.conf.example`. Guides: deployment, backup/recovery, environment/operations, administrator, guard and final readiness; current index/status/database/NFC/research documents link them. Earlier phase reports remain unchanged.

Production blockers are explicit in [final readiness](final-readiness.md): actual host/HTTPS/migration-target/mail acceptance; off-device backup and separate key escrow with approved RPO/RTO/retention; real-photo/host-loss/production-scale restore; institutional privacy/research/access/outage policy; remaining Phase 6 physical cases and realistic load/usability evidence. AI remains Gemini Free Tier synthetic lab-only, with no payment/billing or paid fallback. No claim of full security, verified occupancy, anti-cloning or operational approval is made.

**Stop after Phase 11.** Further device/research/deployment work requires a concrete next instruction. The project is not automatically published.
