# Phase completion report

**Phase:** 2 — Database and Authentication

**Status:** Completed for the approved local foundation scope

**Completed:** 8 November 2026, Asia/Manila

**Checkpoint:** Stop here; Phase 3 requires explicit user approval.

## Result

NFCheck now has real account authentication and recovery backed by isolated Oracle MySQL. The requested **alexi@nfcheck.test**, **pauleen@nfcheck.test** and **zaidy@nfcheck.test** accounts are active administrators. Each has a different generated initial password and must set a personal password before entering the workspace.

Open [sign-in](http://127.0.0.1:8000/login). Initial credentials are available privately in ignored `.tools/secrets/initial-accounts.json`; no password is printed in this report or committed. Recovery messages stay in the [local Mailpit inbox](http://127.0.0.1:8025). Shared XAMPP services/databases and original assets were preserved.

## Implemented features

- Project-local MySQL 8.4.13 LTS on loopback 3307, separate main/testing schemas, distinct root/migrator/runtime/test credentials and restricted runtime grants.
- Ten guarded migrations, core models/relationships, enum roles/statuses, unique and foreign-key constraints, interval CHECK constraints and transaction-backed account services.
- Validated sign-in/sign-out with normalized email, Argon2id password hashing, generic failures, account/IP throttling, first-use password change and no public registration.
- Encrypted database sessions, 30-minute idle expiry, session/CSRF rotation, HttpOnly/SameSite cookies, account security revisions and revocation of older sessions.
- Working recovery and change forms, hashed expiring single-use reset tokens, current-password verification, canonical-origin recovery links and generic recovery responses.
- Administrator/guard workspace gates and model policies. Exact effective guard assignment plus active guard/gate is required for the protected station context. Administrator status alone does not grant scanner operation.
- Audit foundations for authentication, access maintenance, recovery and denied authorization. Allowlisted metadata excludes credential plaintext; runtime SQL cannot update/delete audit rows. Account/audit mutations commit or roll back together.
- CLI-only private operator provisioning/access maintenance with last-active-administrator protection.
- Responsive connected account forms, actual account identity/role/sign-out controls, role-filtered navigation, accessible validation and password visibility controls. Optional previews remain separately gated and disabled by default.
- No-store/no-referrer protections, debug disabled, exception argument suppression, reset-token request-log redaction and encrypted Inertia history cleared on sign-out/session revocation.

The browser check found and fixed a redirect problem caused by AJAX navigation plus missing Referer headers. Forms now use explicit return routes. A regression test covers it. Normal validation errors focus the relevant field instead of showing a misleading navigation failure.

## Database changes

Both `nfcheck` and `nfcheck_testing` contain:

| Table | Responsibility |
| --- | --- |
| persons | Identity classification and active/inactive state |
| users | Operators, password hashes, role/status and security revision |
| student_profiles / employee_profiles | Separate profile details tied to persons |
| gates | Gate identity and state |
| access_authorizations | Person authorization intervals/revocation |
| guard_gate_assignments | Guard/station intervals/revocation |
| audit_logs | Append-only application/runtime account audit |
| sessions | Encrypted session payloads |
| password_reset_tokens | Expiring reset-token hashes |
| migrations | Framework migration ledger |

There are three main-schema operator accounts, with **no synthetic people, profiles, gates, assignments or authorizations**. Tests create transaction-scoped fixtures only in the testing schema and roll them back. NFC credential, verification, passage, anomaly, reporting and AI tables remain deferred. [Database design](database.md) distinguishes this implemented subset from the future ERD.

## Main files

- `database/migrations/*`, `app/Models/*`, `app/Enums/*`, `app/Support/IsolatedDatabase.php`.
- `app/Services/AccountService.php`, `AuditLogger.php`, `app/Policies/*`.
- `app/Http/Controllers/Auth/*`, `app/Http/Requests/*`, account-session/password middleware, provider and authenticated routes.
- `resources/js/pages/Login.tsx`, `pages/auth/*`, `components/AuthPanel.tsx`, `lib/workspace.ts` and connected layout/navigation.
- `scripts/bootstrap-services.ps1`, `start-services.ps1`, scoped provisioning/migration helpers, pinned service manifest and redacting `server.php`.
- MySQL-aware tests, updated example environment, [development instructions](development.md), [database setup/provenance](database-setup.md).

## Verification

**63 tests passed with 526 assertions.** The full verification pipeline passed; check receipts accompany the source.

- Unit and MySQL feature tests cover authentication success/failure, normalization, validation, CSRF, throttles, role/object restrictions, assignment boundaries, inactive overrides, account revisions, first-use change, recovery expiry/replay, canonical links, session encryption/expiry, database constraints, least privilege, append-only audit permissions and transaction rollback.
- Composer validation/audit, npm audit, Prettier, strict TypeScript/Vite build and Pint pass. Dependency audits reported no known advisories at this check.
- Service archive/executable checks pass; scoped migrations report no pending migrations in either schema; routine service startup reuses the intended processes.
- Live HTTP checks confirm sign-in/recovery 200, unauthenticated workspaces redirect to sign-in, disabled previews and source paths return 404, and the original Apache path remains 403.
- Actual browser checks cover a generic login error/focus, administrator login, required first password change, sign-out, generic recovery confirmation and real SMTP capture. New password submission/reset correctness is verified by automated feature tests; browser QA leaves initial administrator passwords unchanged.
- Responsive account form checks and screenshots are in the evidence directory. No full browser zoom, assistive-technology certification, phone/tag hardware test or production deployment is claimed.

Evidence: [runtime/accounts/permissions](evidence/phase-2-runtime-checks.json), [HTTP/mail/loopback checks](evidence/phase-2-http-checks.json), [service provenance](evidence/phase-2-service-verification.json), [browser checks](evidence/phase-2-browser-checks.json), [sign-in](evidence/phase-2-login.png), [first sign-in](evidence/phase-2-first-sign-in.png), [mobile recovery](evidence/phase-2-recovery-mobile.png).

## Security and UI review

The web app never uses root or migrator credentials. Main/testing accounts cannot access each other's schema; audit grants are SELECT/INSERT only. Private credential/environment/data/mail files are ignored and restricted to the current Windows account and SYSTEM. Local disk handoff files are plaintext and are not a password manager.

Forms use labels, appropriate autocomplete, bounded inputs, confirmation/current-password checks, inline errors and processing states. Passwords are not retained after submissions. Browser QA enters existing generated credentials only; users set their own replacement password at first use. No operational data or invented statistics are displayed by later module shells.

## Issues and remaining scope

No blocking Phase 2 defect remains after verification. This is a local development installation: real outbound SMTP, trusted production HTTPS/Secure cookies, backup/retention policy, broader operational review and hardware testing remain future deployment work. The obvious-password blocklist is deliberately small, not a comprehensive breached-password corpus. Database administrators can still alter audit data.

Shared XAMPP security findings from Phase 0 remain unchanged; use the isolated application URL and runtimes. The old Apache 403 is intentional.

**Phase 3 is not implemented.** Next approved scope would be student, employee, gate and guard-assignment management with validated CRUD, search/filtering, status changes and pagination. Wait for the user's instruction before starting it.
