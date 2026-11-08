# NFCheck development rules

- Work only in the user-approved phase. Report results and stop for explicit approval before the next phase.
- Current approved scope is Phase 5: assigned-guard scanning, fresh authorization, identity review, explicit Entry/Exit confirmation, immutable attempts/passages, retry recovery, duplicate handling and refreshed activity. Phase 4 is complete; see docs/phase-4-report.md.
- Stop after Phase 5 verification/reporting. Physical hardware validation remains Phase 6; dashboards, exports, analytics and anomaly/AI modules remain later phases.
- User selected development defaults: 30-second review validity and a 10-second duplicate-passage window. Both are configurable and require campus acceptance before operational use.
- Simulator routes require local/testing environment, loopback, an explicit flag and a dedicated simulation/testing schema. Simulated credentials cannot pass normal verification. Do not create synthetic people or credentials in the main schema.
- Raw NFC references and completion nonces must never enter Inertia props/history, URLs, sessions, logs, browser storage or Git. Deliver once via protected no-store JSON and retain only transient browser memory; store digests only.
- Use project-local runtimes through `scripts/dev.ps1`; shared XAMPP PHP is outdated and has mismatched cURL. Preserve all existing databases and unrelated projects.
- Keep the root `.htaccess` denial. Serve `public` through the loopback development server; never expose application source or `.tools` through shared Apache.
- Preview routes must require a local/testing environment, loopback client, and explicit preview flag. Production must reject previews even if the flag is set.
- Use Laravel policies and server-side decisions when backend phases begin. Never claim identity/physical passage from an NFC read alone.
- No invented operational statistics, fake authenticated users, real institutional sample data, secrets, plaintext NFC tokens, or private photos in Git.
- Keep controllers focused and React components reusable. Typecheck/build, run meaningful tests and dependency audits for relevant changes.
- Migrations may run only against the new isolated NFCheck schemas. Tests must verify the expected testing connection before any destructive database operation; preserve existing XAMPP databases.
