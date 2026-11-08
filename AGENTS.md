# NFCheck development rules

- Work only in the user-approved phase. Report results and stop for explicit approval before the next phase.
- Current approved scope is Phase 3: student/employee and private photo management, gates, guard accounts/assignments, access/status changes, validated search/filtering and pagination backed by MySQL. Phase 2 is complete; see docs/phase-2-report.md.
- Stop after Phase 3 verification and reporting. Do not implement NFC issuance/scanning, passage, reporting or analytics workflows in this phase.
- Phase 3 is complete: 87 tests / 946 assertions, full checks and browser verification passed. Await explicit Phase 4 approval; see docs/phase-3-report.md.
- Use project-local runtimes through `scripts/dev.ps1`; shared XAMPP PHP is outdated and has mismatched cURL. Preserve all existing databases and unrelated projects.
- Keep the root `.htaccess` denial. Serve `public` through the loopback development server; never expose application source or `.tools` through shared Apache.
- Preview routes must require a local/testing environment, loopback client, and explicit preview flag. Production must reject previews even if the flag is set.
- Use Laravel policies and server-side decisions when backend phases begin. Never claim identity/physical passage from an NFC read alone.
- No invented operational statistics, fake authenticated users, real institutional sample data, secrets, plaintext NFC tokens, or private photos in Git.
- Keep controllers focused and React components reusable. Typecheck/build, run meaningful tests and dependency audits for relevant changes.
- Migrations may run only against the new isolated NFCheck schemas. Tests must verify the expected testing connection before any destructive database operation; preserve existing XAMPP databases.
