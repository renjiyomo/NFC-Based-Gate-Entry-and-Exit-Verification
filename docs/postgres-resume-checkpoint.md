# PostgreSQL preparation pause checkpoint

**Resumed November10:** the user said "continue". The saved connection/validation matched, and the online database/account initialization subsequently completed. This file preserves the historical pause snapshot; see [current cloud preparation](cloud-preparation.md) and [the next Vercel steps](vercel-online-setup.md) for the current status.

Paused at the user's request on November 10, 2026 for travel without internet. Work must wait for a resume instruction.

## Completed

- Supabase PostgreSQL 17.11 session connection and server certificate verified; supplied database password works. Private connection details remain in ignored `.tools/secrets/cloud-services.json`; CA path was corrected to the existing `.tools/secrets/supabase-ca.crt`.
- PostgreSQL-compatible migrations, digest byte handling, report/search expressions, workspace identity and immutable-history reads prepared. Local MySQL remains in place.
- Latest disposable PostgreSQL validation passed: 28 tables / 20 migrations / 17 programs / six departments; **six workflow tests / 40 assertions**, **four actual overlapping concurrency scenarios**, and encrypted backup/restore with **28 matching table fingerprints** and unchanged source.
- Owned validation and restore schemas, temporary database role, test manifest and temporary plaintext files were removed. No validation process remains running. Encrypted archives remain private under `.tools/backups`; the existing backup key stays private.
- Full MySQL checks passed **223 PHP tests / 2,397 assertions**, **36 frontend tests**, **11 MySQL races**, audits/style/build/type checks. A later focused portability/preflight run passed five tests / 21 assertions.
- All saved source changes and test results are on disk. Private PostgreSQL client tools were retained; downloaded installation archive removed. No PostgreSQL server was installed.

## Not yet done

- `scripts/initialize-postgres.php` has been written but **has not been run**. No permanent `nfcheck_online` schema, permanent runtime role or online operator handoff has been initialized.
- `scripts/export-cloud-environment.php` has been written but has not generated the production environment file because initialization is pending.
- Requested three administrator identities were added to private configuration; passwords have not yet been generated for the online installation.
- Docker image cannot be built on this workstation because no Docker engine is available. Container files remain unverified under Linux/Vercel.
- Final documentation, hosted HTTPS/phone acceptance and publication/deployment are pending. No GitHub push or public deployment occurred.

## Resume here

1. Read this checkpoint, `AGENTS.md` and `.tools/artifacts/postgres-validation.json`. Review the existing uncommitted diff; preserve the unrelated `scripts/connect-phone.ps1` change.
2. Confirm saved configuration/CA still matches the completed validation receipt and the connection is available. A brief read-only connection check is enough for unchanged configuration; do not automatically repeat the lengthy completed tests.
3. Review and run the guarded initializer only for a fresh absent `nfcheck_online` schema. It requires the successful receipt, refuses existing schema/handoff files, initializes in one transaction and retains handoffs if a commit outcome becomes uncertain. Include the new case-insensitive identifier indexes; their behavior was tested separately in a cleaned minimal probe.
4. Verify the generated administrator/gate credentials privately, generate the private Vercel environment file, and finish relevant checks/documentation. Never print existing or generated passwords/keys.
5. Guide the user through GitHub/Vercel publication when concretely requested. Keep free/no-card/no-billing constraints. Online RF acceptance remains pending; preserve existing local hardware coins.

Network loss alone does not invalidate a completed passing test. Source/configuration changes or a specific new concern may justify targeted reruns. No tests should be started while the user has requested this pause.
