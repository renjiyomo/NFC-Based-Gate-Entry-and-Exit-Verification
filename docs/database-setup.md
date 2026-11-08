# Isolated database and recovery mail

Phase 2 uses **Oracle MySQL 8.4.13 LTS**, bound to **127.0.0.1:3307**, in `.tools/mysql/mysql-8.4.13-winx64`. Data resides in private `.tools/mysql-data`; config is `.tools/mysql/my.ini`. Existing XAMPP MariaDB on 3306 is unchanged. MySQL X, local infile, general/slow query logs and server-side file import/export are disabled. Storage uses UTC; campus display uses Asia/Manila.

**Mailpit 1.33.1** captures SMTP on 127.0.0.1:1025, with the [inbox on 127.0.0.1:8025](http://127.0.0.1:8025). It has no outbound relay and retains at most 100 messages. Its private data/logs in `.tools/mail` may contain reset links.

## Provenance

Pinned URLs/hashes are in `scripts/service-artifacts.json`. The MySQL ZIP came from Oracle's CDN, matched its published MD5 and had its detached signature verified with GnuPG against independently matched official fingerprint **BCA43417C3B485DD128EC6D4B7B3B788A8D3785C**. The SHA-256 pin records that verified archive. Mailpit came from its official release and matched the release checksum. Sources: [Oracle downloads](https://dev.mysql.com/downloads/mysql/8.4.html?os=3), [signature verification](https://dev.mysql.com/doc/refman/8.4/en/checking-gpg-signature.html), [Mailpit release](https://github.com/axllent/mailpit/releases/tag/v1.33.1).

`bootstrap-services.ps1` verifies pinned archive hashes, rejects escaping archive paths, compares installed server/client executable hashes against those archives and refuses to overwrite unknown nonempty installation directories. Downloaded archives, signatures, keys and binaries remain ignored under `.tools`.

## Schemas and privileges

| Account | Scope |
| --- | --- |
| Private root | New private instance; bootstrap only, never used by the web app |
| nfcheck_migrator | Schema/data privileges only in nfcheck, nfcheck_testing and the optional nfcheck_simulator |
| nfcheck_app | Per-table DML; audit/events SELECT/INSERT only; attempts SELECT/INSERT plus named review-column UPDATE; migrations SELECT |
| nfcheck_test | Equivalent restricted grants only in nfcheck_testing |
| nfcheck_simulator | Equivalent restricted grants only in nfcheck_simulator |

Runtime accounts cannot create/alter/drop tables, modify/delete audit rows, grant privileges or read the other schema. A privileged database administrator still controls audit data; no tamper-proof external audit store is claimed.

Each schema has the ten core tables plus credentials/assignments, `scan_attempts`, `access_events` and Laravel's migration ledger. The current ledger has **15 migrations**. Reporting/correction/anomaly tables remain deferred. See [database design](database.md).

Phase 4 brings the ledger to 13 applied migrations in all three project schemas. Persons/gates/users support retained archives and edit versions; access/assignment schedule columns use UTC DATETIME(6). Credential records store unique binary digests, permanent assignments and an owner-qualified selected-assignment pointer. Archive operations deactivate records and revoke related permissions; restoration leaves them inactive. Existing main records are preserved. Transaction fixtures roll back in `nfcheck_testing`; Phase 4 browser QA uses only `nfcheck_simulator`.

`scripts/dev.ps1 simulator` adds the fixed `nfcheck_simulator` schema/runtime user and a distinct random secret in the protected database handoff. Its environment/key/session cookie live in ignored `.env.simulator`. The migrator receives DDL only within this additional schema; simulator runtime grants remain per-table DML, audit SELECT/INSERT, migrations SELECT. MySQL version/data-directory checks run before provisioning and migration. The runtime cannot query the main/test schemas. A CLI-only guarded seed creates one disposable lab administrator and two explicitly simulated people/grants, idempotently; no credential references are seeded or stored in plaintext. Existing lab records are preserved.

## Setup

Phase 5 adds two synthetic lab guards and `SIM-GATE-01` only in the simulator schema; their private sign-in file is `.tools/secrets/simulator-guards.json`. Existing records and passwords are not reset. The concurrent test harness uses a private maintenance connection only to remove its captured testing-fixture IDs from immutable tables, restores pre-run row counts and never targets the main schema. Web runtimes never receive that credential.

```powershell
# First setup: verify packages and initialize this private instance.
pwsh -File .\scripts\bootstrap-services.ps1
.\scripts\dev.ps1 migrate

# Routine startup: reuse matching processes; preserve data.
.\scripts\dev.ps1 services

# Existing package integrity verification.
pwsh -File .\scripts\bootstrap-services.ps1 -VerifyOnly
```

Initialization refuses unknown nonempty data directories. MySQL secure initialization generates an expiring root password; provisioning replaces it with a random password using a private temporary client file, removes that file and never prints passwords. Root/migrator/runtime/test passwords are distinct random values in `.tools/secrets/database.json`. Environment files receive only their corresponding runtime credentials; a separate testing application key is generated if absent.

The migration helper verifies the exact project data directory, version and fixed schemas before using its migration credential. Each migration also checks its target before up/down. Routine startup does not automatically apply migrations. Never use shared databases or generic reset commands.

Private directories and environment files have Windows ACLs restricted to the current account and SYSTEM. They are ignored by Git and outside the served public directory. Private handoff/database credential files are unencrypted on disk; Windows administrators/the current account can access them. Do not share or include them in screenshots.

Private normalized photos live in `storage/app/private/person-photos`; service startup also restricts this directory to the Windows user and SYSTEM. Local disk automatic serving is disabled. The administrator-authorized photo endpoint is the only application delivery route; no public symlink is created.

## Authentication configuration

- Laravel session authentication; admin/guard roles, active/inactive accounts; no public registration or remember-me login.
- Argon2id: 64 MiB, three iterations, one thread. Tests reduce costs only in their process.
- New passwords: 15–128 characters, confirmation, passphrases allowed, small local obvious-password/repetition blocklist. This is not a comprehensive breached-password corpus or NIST-compliance claim. The length/passphrase choice follows current [NIST guidance](https://pages.nist.gov/800-63-4/sp800-63b.html).
- Encrypted database payloads; 30-minute idle expiry; login/logout session and CSRF rotation. Password changes/resets revoke other sessions. Account password/role/status revisions invalidate older sessions on the next request.
- HttpOnly/SameSite=Lax cookies. Secure=false is specific to local HTTP; production needs trusted HTTPS/Secure cookies.
- Generic failure/recovery responses and account/IP throttles. Reset tokens are stored as hashes, expire after 30 minutes and work once. Recovery URLs use configured APP_URL instead of request Host.
- APP_DEBUG=false; exception arguments omitted, password/token fields excluded from flashed input, no-referrer/no-store headers. The local server router redacts reset-token paths and omits query strings from request logs. Inertia history state is encrypted and its key is cleared on sign-out/session revocation.

Framework references: [authentication](https://laravel.com/framework/docs/13.x/authentication), [password recovery](https://laravel.com/framework/docs/passwords).

## Local administrators and audits

The three requested accounts are active administrators. Distinct initial passwords are in `.tools/secrets/initial-accounts.json`, with a required first-use change. Generated-file handoff is local-only; interactive CLI provisioning uses a hidden password prompt. This stores initial credentials only, not subsequent user passwords. Remove consumed initial entries privately when no longer needed.

Main-schema people, profiles, gates, authorizations and assignments have no sample rows. Tests roll fixtures back in nfcheck_testing. Audits cover account creation/access, sign-in/out, session revocation, recovery and denied authorization. Allowlisted metadata excludes passwords, reset tokens, session IDs, raw email and raw IP. Session rows themselves retain ordinary IP/user-agent metadata while they exist. Institutional retention/backup policy remains a later operational decision.
