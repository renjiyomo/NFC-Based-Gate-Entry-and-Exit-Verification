# NFCheck development rules

- Work only in the user-approved phase. Report results and stop for explicit approval before the next phase.
- Current checkpoint is Phase 1: foundation and honest UI shells. Do not add database/authentication, operational records or NFC business logic until their phases are approved.
- Use project-local runtimes through `scripts/dev.ps1`; shared XAMPP PHP is outdated and has mismatched cURL. Preserve all existing databases and unrelated projects.
- Keep the root `.htaccess` denial. Serve `public` through the loopback development server; never expose application source or `.tools` through shared Apache.
- Preview routes must require a local/testing environment, loopback client, and explicit preview flag. Production must reject previews even if the flag is set.
- Use Laravel policies and server-side decisions when backend phases begin. Never claim identity/physical passage from an NFC read alone.
- No invented operational statistics, fake authenticated users, real institutional sample data, secrets, plaintext NFC tokens, or private photos in Git.
- Keep controllers focused and React components reusable. Typecheck/build, run meaningful tests and dependency audits for relevant changes.
- Do not run migrations, seeders or database-reset commands in Phase 1.
