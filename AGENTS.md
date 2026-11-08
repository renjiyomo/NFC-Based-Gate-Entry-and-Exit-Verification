# NFCheck development rules

- Work only in the user-approved phase. Report results and stop for explicit approval before the next phase.
- Current approved scope is Phase 4: credential generation/assignment/activation/revocation/replacement, NDEF preparation, administrator management and an isolated development simulator using the shared verifier. Phase 3 is complete; see docs/phase-3-report.md.
- Stop after Phase 4 verification/reporting. Do not implement guard passage events, operational scanning, reporting or analytics in this phase. Physical hardware validation remains Phase 6.
- Simulator routes require local/testing environment, loopback, an explicit flag and a dedicated simulation/testing schema. Simulated credentials cannot pass normal verification. Do not create synthetic people or credentials in the main schema.
- Raw NFC references and completion nonces must never enter Inertia props/history, URLs, sessions, logs, browser storage or Git. Deliver once via protected no-store JSON and retain only transient browser memory; store digests only.
- Use project-local runtimes through `scripts/dev.ps1`; shared XAMPP PHP is outdated and has mismatched cURL. Preserve all existing databases and unrelated projects.
- Keep the root `.htaccess` denial. Serve `public` through the loopback development server; never expose application source or `.tools` through shared Apache.
- Preview routes must require a local/testing environment, loopback client, and explicit preview flag. Production must reject previews even if the flag is set.
- Use Laravel policies and server-side decisions when backend phases begin. Never claim identity/physical passage from an NFC read alone.
- No invented operational statistics, fake authenticated users, real institutional sample data, secrets, plaintext NFC tokens, or private photos in Git.
- Keep controllers focused and React components reusable. Typecheck/build, run meaningful tests and dependency audits for relevant changes.
- Migrations may run only against the new isolated NFCheck schemas. Tests must verify the expected testing connection before any destructive database operation; preserve existing XAMPP databases.
