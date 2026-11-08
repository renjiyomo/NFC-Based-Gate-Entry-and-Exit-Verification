# NFCheck development rules

- Work only in the user-approved phase. Report results and stop for explicit approval before the next phase.
- Current approved scope is Phase 6: Android Chrome Web NFC integration, secure phone connectivity, capability/permission/error handling, real NTAG215 writing/read-back/scanning and physical acceptance evidence. Phase 5 is complete; see docs/phase-5-report.md.
- User has a Samsung A52 and ten NTAG215 tags and chose USB testing. USB authorization and port 8003 reverse forwarding are verified on SM-A525F / Android 14 / installed Chrome 147.0.7727.111. Real browser NFC access and physical tag acceptance are pending. Never equate phone ownership, API detection or mock tests with physical acceptance.
- Stop after Phase 6 verification/reporting. Dashboards, exports, analytics and anomaly/AI modules remain later phases. Physical tests require the user's phone/tag interaction and truthful recorded outcomes.
- Use a separate hardware-test schema/workspace for synthetic physical tests; preserve main records and simulator isolation. Keep servers loopback-only by default; do not expose a service or bypass browser certificate/security checks without the user's chosen connection setup.
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
