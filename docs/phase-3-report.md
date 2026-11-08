# Phase completion report

**Phase:** 3 — Student, Employee, and Gate Management

**Status:** Completed and verified for the approved local management scope

**Completed:** 8 November 2026, Asia/Manila

**Checkpoint:** Stop here. Phase 4 requires explicit approval.

## Result

Student, employee, gate and security-guard management now use MySQL through validated Laravel endpoints and connected React forms. Search, filters, pagination, details, edits, private photographs, access grants, gate assignments, archiving and restoration work through the backend.

Open [NFCheck](http://127.0.0.1:8000/login). The three requested administrators remain active: alexi@nfcheck.test, pauleen@nfcheck.test and zaidy@nfcheck.test. Their credentials were not changed by development or browser QA. Existing initial-password handoff and first-use password requirements remain in effect.

## Implemented features

- Separate student and employee directories and profiles, with classification-scoped campus-number uniqueness, required affiliation fields, optional year/term, validated record status and person details.
- Server search by name/number, program/department filtering, active/inactive and current/archived filters, current-access filtering, stable ordering and pagination at 15/30/50 rows. Search treats wildcard characters literally and binds values.
- Optional private JPEG/PNG/WebP uploads, bounded to 2 MB and 4096 × 4096 source pixels. Images are decoded, resized to at most 800 pixels on their longest edge, re-encoded as JPEG and stripped of embedded metadata. No original filename or public storage URL is exposed.
- Gate create/edit/details/search/status management with unique normalized codes and assignment history.
- Guard-only account management, including private password setup links, status changes, search and gate filters. Administrators cannot be edited or archived through guard routes. A supplied role/password/email-change field cannot widen guard privileges.
- Guard setup uses a random undisclosed initial password and a reset link so the guard chooses a password. Failed delivery preserves the account, removes its undelivered token and allows retry. Inactive/archived guards lose sessions and reset tokens.
- Time-bounded access grants separate from person record status. Inactive/archived people cannot receive new grants. Overlapping grants are rejected; revocations preserve history.
- Time-bounded guard/gate assignments, searchable paginated choices, overlap rejection for the same guard/gate, schedule replacement retaining the old revoked schedule, and immediate revocation. Only active guards/gates can receive assignments.
- Guard station selection now lists only that guard's current assignments at active, unarchived gates. Station context still cannot perform NFC scanning.
- Backend DELETE endpoints archive records instead of erasing history. Archiving deactivates records and revokes relevant grants/assignments. Restoration returns records as inactive and never restores revoked permissions automatically.
- Edit versions reject stale saves. Services lock actors/targets, recheck authorization/state and commit mutations with their audit records. Guard edits also detect relevant CLI account changes.
- Connected forms, inline errors and focus, processing states, confirmation dialogs, real directory counts and responsive tables. Mobile header wrapping was corrected during visual QA.

## Files and database changes

Key additions are management controllers, ManagementRequest, PersonManagement/GateManagement/GuardManagement/AccessManagement services, private photo handling, DirectoryData serializers, reusable management controls, nine connected page components, and management feature tests.

Existing models, policies, routes, scanner context, shared props, service startup and styles were updated. Private test-storage files are ignored by Git.

Two scoped migrations were applied to both isolated schemas:

| Migration | Change |
| --- | --- |
| 000011 | archived_at and record_version on persons, gates and users; archival/status indexes |
| 000012 | DATETIME(6) storage for access and assignment start/end fields |

Both schemas now have **12 applied migrations**, with the existing ten application tables plus the framework migration ledger. Schedule storage uses UTC; form inputs and display explicitly use Asia/Manila. DATETIME avoids TIMESTAMP's narrower future range, documented in the [MySQL manual](https://dev.mysql.com/doc/refman/8.4/en/datetime.html). A regression test verifies schedules beyond 2038.

The main schema remains free of development people/gates/assignments. Shared XAMPP services and unrelated databases were preserved. The original logos and manuscript still match their earlier hashes.

## Tests and evidence

**87 tests passed with 946 assertions.** The complete check pipeline passed: Composer validation/audit, npm audit, Prettier, strict TypeScript/Vite build, Pint and PHP tests. Dependency audits reported no known advisories at this check.

New tests cover both identity classifications, persisted profile edits, uniqueness including archived records, mismatched object/type URLs, stale edits, archiving/restoration, private photo access/re-encoding/payload removal, unsafe uploads, file cleanup on audit rollback, gate and guard management, fixed guard roles, setup delivery failure, session/reset-token revocation, Manila conversion, grant/assignment intervals, overlap/adjacency, parent scoping, schedule replacement and audit rollback. Paginated lookup tests cover eligibility, searching, selected records and denied guard access.

Actual browser QA verified student creation/photo upload, an edit surviving reload, combined filters, archive/restore, dialog Escape/focus, searchable guard choices, schedule review/cancel, guard/employee screens and focused duplicate-code errors. Nine management views were checked at 360px; representative forms/details/directories passed at 360/768/1280px with no page-wide horizontal overflow. Account controls remain within their header. Browser console checks were clean.

Browser QA used disposable accounts and records in **nfcheck_testing** on loopback port 8001, with a visible test-workspace notice. Screenshots show QA data, not institutional data. Owned QA rows/photos were removed afterward; feature-test fixtures were rolled back. The normal application remains on port 8000. The QA listener is separate and has no remaining operator account.

Evidence:

- [Runtime, schemas, accounts and permissions](evidence/phase-3-runtime-checks.json)
- [Live routes and loopback listeners](evidence/phase-3-http-checks.json)
- [Browser workflow and responsive checks](evidence/phase-3-browser-checks.json)
- [QA cleanup receipt](evidence/phase-3-qa-cleanup.json)
- [Student directory](evidence/phase-3-directory.png)
- [Saved/restored person](evidence/phase-3-person.png)
- [Gate details](evidence/phase-3-gate.png)
- [Mobile employee form](evidence/phase-3-form-mobile.png)
- [Mobile validation](evidence/phase-3-validation-mobile.png)

## Security and UI review

All management routes require an authenticated, current account, completed initial password setup and administrator authorization. Policies and services enforce access; frontend visibility is not the permission boundary. Mutations use CSRF-protected web routes and bounded validation. Current runtime database accounts retain their narrow grants, including SELECT/INSERT-only audit access.

Photos are delivered through an authorized controller with private/no-store headers. Private storage's automatic serving is disabled; photo paths are generated UUIDs, and server paths/original filenames are excluded from page data. Startup restricts the local photo directory to the Windows user and SYSTEM. Uploaded images cannot become executable/public originals. Files created by failed mutations are cleaned up; previous photos survive failed edits.

Archive confirmations name the target and explain retention/revocation. Restore explicitly leaves records inactive. Grant/assignment times and open-ended periods are labeled. Database-derived state is distinct from NFC verification and physical passage. No gate activity, attendance, statistics or identity matches are fabricated.

## Known limits and remaining work

No blocking Phase 3 defect remains. This is a local development installation. Production SMTP/HTTPS, backups/retention, broader load testing, full assistive-technology validation and physical phone/tag testing remain deployment/later-phase work. Photo normalization assumes an upright source image and does not automatically correct EXIF orientation.

Administrator account maintenance remains the existing reviewed CLI workflow; the new UI manages security guards. Archival retains identity/photos/history until a separately approved retention/purge policy is implemented. Privileged database administrators can still alter audit data.

**Phase 4 is not implemented.** NFC generation, issuance, write/read-back, activation, revocation/replacement and its development simulator require the next approval. Scanning, passage recording, operational statistics and later modules remain unavailable.
