# Phase-by-phase implementation strategy

Status: **Phases 0–5 completed for their approved scopes. Core Phase 6 implementation and the controlled vivo/NTAG215 session are complete; full physical readiness remains partial with unperformed cases listed in the report.** Every phase ends with a report and an explicit stop. Successful tests do not authorize the next phase. See [Phase 6 status](phase-6-report.md). Phase 7 is complete for local development: see [the report](phase-7-report.md). Phase 8 is not started.

## Phase acceptance gates

| Phase | Work authorized only after its approval | Acceptance evidence before reporting completion |
| --- | --- | --- |
| **0 — Planning, research and audit** | Inspect workspace/runtime; official security/compatibility research; scope, architecture, ERD, roles, UI research, risk and roadmap documents | Dated evidence, existing assets preserved, design limitations explicit, no scaffolding or database changes. This checkpoint is complete. |
| **1 — Foundation and UI/UX** | Resolve required runtime/tooling prerequisites; initialize Laravel at current root; React/Inertia/TypeScript/Tailwind/ordinary Vite; Git/ignore rules; design tokens, layouts, reusable components and page shells | Clean runtime diagnostic, dependencies resolved without bypass flags, applicable audits reviewed, frontend typecheck/build, shell navigation and 360–1440px layouts/keyboard/empty states checked. No invented statistics or backend claims. |
| **2 — Database and authentication** | Provision isolated MySQL 8.4 LTS; core migrations/models/relationships, private account provisioning, password recovery, role policies, sessions, validation and audit foundation | Migrations on intended engine; positive/negative auth/policy tests; disabled-account and CSRF/throttle/recovery tests; unique/FK/transaction behavior; no root database account for app. |
| **3 — People and gate management** | Persisted student/employee/gate/account management, access grants and guard assignments, validated forms, photos, search/filter/pagination | Database-backed workflow and reload persistence; object policies; private upload delivery; invalid inputs and expired/unassigned gates tested. |
| **4 — Credential management** | Random pending credentials and ownership, activation/revocation/replacement, write/read-back transport preparation, visibly isolated simulator | Digest-only storage, revoked/unknown/duplicate/expired cases, interrupted issuance and concurrent replacement tests; one active assignment. Simulator uses real verification service but is rejected in production. Physical writing remains Phase 6 evidence. |
| **5 — Gate verification and passage** | Assigned scanner workflow, explicit direction, verification/review/confirmation, cancellation/rejection/expiry, duplicates, fresh status checks, recent activity, incidents and presence estimate | End-to-end positive/negative tests, same-key retries and changed-payload rejection, cancellation/confirmation race, revocation during review, concurrent confirmations, no passages from denied/unconfirmed scans. |
| **6 — Physical NFC integration** | Android Chrome Web NFC read/write adapter, capability/permission/error handling, server integration and hardware instructions | Software integration and parser tests; actual device/tag matrix where available. If no hardware, clearly report physical tests outstanding and physical readiness unverified. Never replace hardware results with simulation. |
| **7 — Monitoring, analytics and reports** | Real dashboard queries, history filters, trends/charts, CSV/XLSX/PDF export, query optimization | Reconcile aggregates/exports with known source fixtures and corrections; Manila date boundaries; private export access/formula-injection protection; explain query plans on representative data. |
| **8 — Explainable anomaly review** | Versioned rules, evidence, severity, alerts, reviewer/status/resolution workflows | Labeled true/false scenarios, precision/recall/false-positive report, duplicate flag prevention and reviewed false alarms; anomalies do not become wrongdoing claims. No unapproved ML. |
| **9 — Optional AI summaries** | Only after scope/provider/privacy/cost approval: minimized validated aggregates, generation jobs/history, provenance, consistency checks, human review | Numerical/period consistency tests, rejected/failed generation behavior, no identity/token leakage, permissions and graceful provider outage. Core verification works without AI. |
| **10 — Security, testing and optimization** | Cross-cutting auth/policy/injection/XSS/CSRF/upload/session/NFC review, audits, responsiveness/accessibility, race/load/query tests | Recorded results and outstanding risks; high/critical findings resolved or phase held incomplete; no unsupported claim of complete security. |
| **11 — Deployment preparation and documentation** | Production configuration plan, HTTPS, DB/migrations, tested backup/restore, logs, retention, administrator/guard guides, NFC setup and research evidence | Restore drill, configuration/secrets review, operational/manual outage procedures, device evidence, institution/data authorization and readiness assessment. Public deployment still needs explicit approval. |

## Environment prerequisites and sequencing

The environment audit is complete even though remediation is not. Before Phase 1 package installation: use complete matching patched PHP/extensions, current Composer and patched Git, plus selected Node LTS. An isolated setup is preferred to modifying shared XAMPP. Validate cURL and all required extensions, advisory mitigations, and no startup warning. Verify versions again at execution time.

Before creating `.env` or private source content, block the existing Apache `htdocs` route to this project; a new vhost or a loopback server alone leaves the original path reachable. Use a narrowly scoped, verified arrangement that preserves unrelated sites. Do not change shared services or configuration silently. Exact implementation should be reviewable before any consequential shared-environment change.

Phase 1 does not need an operational institutional database or fake users. Phase 2 provisions MySQL separately from the existing MariaDB instance, preferably on unused loopback port 3307; verify availability at that time. Preserve all existing databases. Use synthetic testing schemas distinct from development and later production.

Phase 2 introduces core identity/access/credential constraints as needed for the approved database design, while feature behavior remains in its later phase. Later modules add migrations only when their scope is approved. Do not create empty placeholder tables for imports, ML or AI before those modules exist.

Security work is continuous: later Phase 10 is a consolidated review, not permission to postpone authorization, credential safeguards or validation. UI shells likewise do not imply operational services. Keep each phase small enough to verify and checkpoint.

## Development standards and checkpoint protocol

Use official Laravel conventions, PSR-12, focused controllers/FormRequests/actions/policies, typed React server props and reusable components. Separate business logic from views. Use parameterized database access; no duplicate backend or authoritative frontend policy. Add justified dependencies only when their module needs them.

Run meaningful tests appropriate to the actual change; use the selected MySQL engine for constraint/concurrency acceptance rather than relying solely on SQLite. Commit lockfiles and phase work after successful checks when a Git repository exists. Phase 0 did not initialize Git or create a commit. Do not include old manuscript or identity files in a future commit without an intentional repository-content review.

Every completion report must identify phase/status, summary, implemented features, files, database changes, actual checks/results, security/UI review, known issues, next phase and approval status. If critical acceptance criteria fail, report partial/blocked scope honestly and fix within that phase; never advance automatically.

## Open policy inputs by decision point

| Needed before | Input / accountable party |
| --- | --- |
| Phase 2/3 data model finalization | Institution: campus-number rules, dual affiliations, required identity fields and access validity model |
| Phase 3/5 gate operation | Campus security: gate inventory, assignments, photo/manual review, emergency/outage procedure, recent-history access window |
| Phase 5 duplicate rules | Campus security/research lead: duplicate window and repeated-passage handling, tested with gate workflow |
| Phase 8 rules | Security/research lead: after-hours policy, rule thresholds, review responsibilities and labeled evaluation scenarios |
| Any real data/research participants | Institution/DPO/research authority: processing authorization, notice, lawful basis, retention and approved study design |
| Phase 9 | User/institution: AI scope, provider, aggregate privacy controls and budget |
| Operational readiness | Operations/security: trusted HTTPS, supported servers, backups/restore, update owner, hardware and peak-load evidence |

These inputs are not invented in Phase 0 and do not prevent safe synthetic UI development after Phase 1 approval.
