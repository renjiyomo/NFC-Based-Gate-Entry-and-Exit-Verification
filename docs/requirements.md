# Requirements and scope proposal

Status: **Requirements baseline created in Phase 0.** Authentication, Phase 3 management and Phase 4 credential lifecycles are implemented as recorded in [the Phase 4 report](phase-4-report.md); later modules remain planned. Physical NFC behavior remains untested. Source: the supplied NFCheck master development prompt, especially Parts 1, 6–8, 10–12, and 16. The revised gate monitoring direction is authoritative over the old-title manuscript.

## Research objective

Verify registered student and employee credential references at assigned campus gates, display the minimum information needed for a guard's identity review, and record guard-confirmed Entry/Exit activity. Provide traceable monitoring, accurate analytics, reports, and explainable anomaly review. AI summaries are a separately approved extension that consumes validated aggregates.

A credential match establishes that the presented reference is registered and currently authorized. It does not establish the holder's identity, prevent cloning, or prove a physical crossing. Guard observation remains part of the system. Gate activity does not establish classroom attendance or continued physical presence on campus.

## Module boundaries and acceptance requirements

| ID | Module | Planned behavior / acceptance |
| --- | --- | --- |
| R01 | Authentication and authorization | Private account provisioning; login/logout, password recovery, hashing, session rotation/invalidation, disabled-account enforcement, CSRF, throttling and server policies. No public registration. |
| R02 | Students | Validated create/edit/details, search/filter/pagination, program/year/term only as necessary, private photo, access activation/deactivation, credential/history links. |
| R03 | Employees | Validated create/edit/details, number/department/position, private photo, search/filter/pagination, access status and history. |
| R04 | Accounts, gates and assignments | Admin manages active operators and gates; time-bounded guard assignments restrict both verification and confirmation. Protect last active administrator from accidental removal. |
| R05 | Credentials | Random reference generation, pending issuance, writing/read-back, activation, revocation, loss replacement and assignment history. One active credential per person initially. Never move an issued token to another person. |
| R06 | NFC scanner | Compatible Android Chrome NDEF integration, deliberate permission request, explicit Entry/Exit, unsupported/error/offline states, read/write interruption handling. Simulator is development-only and visibly labeled. |
| R07 | Verification and passage | Validate at scan and confirmation, show identity for guard comparison, confirm/reject/cancel, expiry, idempotency, authoritative duplicate protection and transaction-safe concurrency. No alternating directions. |
| R08 | History and corrections | Paginated attempt/passage details with person/date/gate/direction/outcome filters. Immutable originals and reasoned append-only administrative corrections. |
| R09 | Monitoring | Backend-derived students/employees/active credentials, confirmed entries/exits, denied attempts, trends and recent activities. Explicit presence estimate and coverage limitations. Honest empty data. |
| R10 | Reports | Daily/weekly/monthly Manila periods, student/employee and gate comparisons, invalid/denied attempts, CSV/XLSX/PDF exports, authorized and audited; reconcile every aggregate to source events. |
| R11 | Anomaly review | Versioned deterministic rules, explanation, severity, evidence, reviewer/status/resolution. Indicators require human review; no guilt or disciplinary inference. |
| R12 | AI summaries — conditional | Separately approved Phase 9. Server-only provider credentials, aggregate-only input, provenance/history, numerical consistency checks and human review. Provider failure cannot stop core gate operations. |
| R13 | Audit and operational settings | Redacted authentication/admin/access-operation audit, narrow validated settings, privacy/retention procedures. Audit records are not claimed tamper-proof. |

## Proposed permissions

Two initial roles: **Administrator** and **Security Guard**. Campus persons are separate from operator accounts; a student or employee is not automatically an application user.

| Capability | Administrator | Security guard |
| --- | --- | --- |
| Manage students, employees, accounts, gates and assignments | Yes, validated and audited | No |
| Issue, replace, revoke credentials | Yes | No |
| Scan and confirm passage | No automatic grant; a later explicit operator capability would be required | Active assigned gates only, while account/assignment remains valid |
| View identity/photo | Authorized record management | Minimum identity during permitted verification; scoped recent activity only |
| View global history/dashboard/analytics | Yes | No |
| View recent gate activity | Yes | Assigned gate and bounded time window |
| Report a concern | Yes | Yes, for permitted station activity |
| Review/resolve anomalies and incidents | Yes, with reason | Report/view own permitted concern only; no unrestricted review |
| Export reports / request AI summary | Yes, logged | No |
| Correct history | Append-only correction with reason | No |
| Settings/audit log review | Yes, minimum necessary | No |

Authorization must cover every route, service action, photograph response, export, and object lookup. Hiding navigation is not an access control. A role change, revoked assignment, or disabled account invalidates future operations, including a pending confirmation.

## Proposed initial policies

- Campus-wide, time-bounded access authorization is sufficient initially; gate-specific person grants require a demonstrated policy need.
- Person status, credential status, authorization interval, and guard assignment all must be valid at server time. A disabled person overrides an otherwise active grant.
- Guard explicitly confirms each passage after review. Initial review expiry proposal: **30 seconds**, configurable and evaluated for gate usability. Unconfirmed/expired/denied attempts do not become passages.
- Rapid repeated confirmations for the same person/gate/direction are suppressed under a person lock even with different request IDs or guards. Retain the attempt with an audited duplicate reason; choose/test the duplicate window in Phase 5. Repeated Entry and Exit without a previous recorded Entry outside this window generate review indicators. They do not switch direction automatically. Blocking based on presence alone is not proposed; emergency exit cannot depend on a database estimate.
- Core application is online. During an outage, show unavailable status and use the institution's separate manual procedure; no browser-generated authorization success or trusted offline queue.
- Initial identity classification is student or employee. Concurrent dual affiliations need policy/schema review before implementation if required.

## Optional or deferred work

No initial supervisor role, public/student portal, classroom attendance, enrollment processing, automatic gate/barrier control, facial recognition, tracking/GPS, RFID UID authentication, mobile native app, separate Node service, Python/ML model, mass import, or external student-information-system integration. `import_batches` is deferred until an import feature is approved. AI provider choice, external processing, cost and summary scope require approval in Phase 9.

## Decisions that need institutional evidence later

These do not block planning or synthetic Phase 1 shells: actual gate/station inventory, assignment schedules, permitted identity fields, approved campus-number format and dual affiliations, access validity policy, photo availability, missing-photo/manual exception procedure, after-hours schedule, duplicate window, retention/legal basis/privacy notice, export access, research participant authorization, expected traffic/load, and AI provider/data policy. No real records or real operational policy is assumed from the old manuscript.

## Nonfunctional requirements

- Server timestamps in UTC, with Asia/Manila report boundaries; configured clock synchronization must be verified before operations.
- Foreign keys, uniqueness and transactions enforce invariants; pagination and relevant compound indexes support history/report queries.
- WCAG 2.2 AA target: semantic labels, keyboard access, visible focus, text/icon status, accessible announcements, readable contrast and large scanner controls.
- Responsive admin and guard flows; guard station optimized for portrait Android use with gate/direction always visible.
- Secure sessions, TLS, least privilege, input validation, safe photographs/exports, redacted logs, dependency audits, backups and traceable corrections.
- Measure actual p50/p95 response time and throughput on declared hardware/network. Capacity targets need gate-volume evidence; none are invented now.

All implementation phases require their own passing acceptance checks and explicit next-phase approval. See [roadmap](roadmap.md) and [test/evaluation plan](testing-and-research.md).
