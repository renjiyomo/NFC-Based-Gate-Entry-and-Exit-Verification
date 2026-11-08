# NFCheck database and ERD proposal

Status: Phase 0 design only. No database, tables or migrations have been created. This proposal uses the workflows in [architecture.md](architecture.md); the database product/version decision is recorded in [environment-audit.md](environment-audit.md).

## Relational design

Use InnoDB-compatible transactions, foreign keys, `utf8mb4`, UTC timestamps with microsecond precision where supported, and explicit status/reason values. Internally use unsigned bigint primary keys unless a framework requirement justifies otherwise. Expose opaque operation identifiers rather than treating an obscure database ID as authorization. All identifiers still require object-level policies.

```mermaid
erDiagram
    USERS ||--o{ GUARD_GATE_ASSIGNMENTS : receives
    GATES ||--o{ GUARD_GATE_ASSIGNMENTS : hosts
    PERSONS ||--o| STUDENT_PROFILES : has
    PERSONS ||--o| EMPLOYEE_PROFILES : has
    PERSONS ||--o{ ACCESS_AUTHORIZATIONS : receives
    PERSONS ||--o{ CREDENTIAL_ASSIGNMENTS : owns
    NFC_CREDENTIALS ||--o| CREDENTIAL_ASSIGNMENTS : assigned_once
    PERSONS o|--o| CREDENTIAL_ASSIGNMENTS : active_pointer
    USERS ||--o{ SCAN_ATTEMPTS : submits
    GATES ||--o{ SCAN_ATTEMPTS : receives
    PERSONS o|--o{ SCAN_ATTEMPTS : identifies
    NFC_CREDENTIALS o|--o{ SCAN_ATTEMPTS : presented
    SCAN_ATTEMPTS ||--o| ACCESS_EVENTS : confirmed_as
    PERSONS ||--o{ ACCESS_EVENTS : passes
    GATES ||--o{ ACCESS_EVENTS : records
    USERS ||--o{ ACCESS_EVENTS : confirms
    ACCESS_EVENTS ||--o{ EVENT_CORRECTIONS : corrected_by
    PERSONS ||--o| PRESENCE_STATES : projects
    ACCESS_EVENTS o|--o| PRESENCE_STATES : last_effective_event
    USERS o|--o{ AUDIT_LOGS : acts
    SCAN_ATTEMPTS ||--o{ ANOMALY_EVIDENCE : supports
    ANOMALY_FLAGS ||--o{ ANOMALY_EVIDENCE : cites
    SCAN_ATTEMPTS o|--o{ INCIDENTS : concerns
    USERS ||--o{ INCIDENTS : reports
    USERS ||--o{ AI_SUMMARIES : requests
```

The diagram shows the planned core and later modules together. `presence_states`, anomaly tables, incidents, AI summaries and settings are conditional as detailed below. It omits framework infrastructure tables and some actor/authorization foreign keys for readability. A nullable relationship represents an unknown credential or absence of prior events, not permission to fabricate an identity.

## Identity and access tables

| Table | Proposed important columns | Constraints and indexes |
|---|---|---|
| `users` | `id`, normalized `email`, `name`, password hash, `role`, `status`, optional `person_id`, framework remember token, timestamps | Unique email; optional unique person link; role/status allowlists; no cascade deletion into history. |
| `persons` | `id`, `person_type`, `campus_number`, `full_name`, private `photo_path`, `status`, nullable `active_credential_assignment_id`, timestamps | Unique `(person_type, campus_number)`; unique active-assignment pointer; indexed type/status and searchable identity fields. |
| `student_profiles` | `person_id` primary key, `program`, optional `year_level`, optional `academic_term` | FK person; service validates student type and allowed values; no unnecessary personal attributes. |
| `employee_profiles` | `person_id` primary key, `department`, `position` | FK person; service validates employee type. |
| `access_authorizations` | `id`, `person_id`, `status`, `valid_from`, nullable `valid_until`, `reason`, `granted_by`, nullable revocation actor/time, timestamps | Person and user FKs; index `(person_id, status, valid_from, valid_until)`; valid end after start. |
| `gates` | `id`, unique `code`, `name`, `status`, timestamps | Unique code; indexed status. |
| `guard_gate_assignments` | `id`, `guard_id`, `gate_id`, `starts_at`, nullable `ends_at`, nullable revocation time/actor, timestamps | User/gate FKs; index `(guard_id, gate_id, starts_at, ends_at)`; end after start; role/overlap validity handled transactionally. |

One person initially has one student or employee profile according to `person_type`; dual affiliation is not assumed. Service-level transactions preserve cross-table profile/type consistency. Operator accounts are independent. Student/employee access status and operator account status have different meanings.

Effective authorization requires an active person and a currently valid non-revoked grant. Validity intervals are half-open: `start <= now < end`, with a null end meaning no scheduled end. Do not infer authorized access from record existence alone. The initial model uses campus-wide member grants; add gate-specific member scopes only when approved.

## Credential ownership and activation

| Table | Proposed important columns | Constraints and indexes |
|---|---|---|
| `nfc_credentials` | `id`, `token_digest BINARY(32)`, `status`, `created_by`, pending/read-back/activation/revocation/expiry timestamps as needed, nullable revocation reason, nullable `replaces_credential_id`, timestamps | Unique digest; self FK for replacement; indexed status/expiry; no raw token or secure-identity claim based on tag UID. |
| `credential_assignments` | `id`, `person_id`, `credential_id`, `assigned_by`, `assigned_at`, nullable `ended_at`, nullable end reason | Unique `credential_id`; unique `(person_id, id)` for owner-qualified pointer; person, credential and user FKs; index `(person_id, assigned_at)`. |

`persons.active_credential_assignment_id` is a nullable unique pointer. Add a composite foreign key `(persons.id, persons.active_credential_assignment_id)` referencing `credential_assignments(person_id, id)`. This guarantees that a non-null active pointer belongs to that person. The person row exists first, assignments are added next, and the pointer FK is added after both tables exist. A null pointer means no live credential. Use restricted deletion; a composite `SET NULL` would also affect the non-null person ID and is inappropriate.

The pointer enforces one selected active assignment per person; it does **not** independently enforce credential state or expiry. All issuance, activation, revocation and verification use the same transactional service. Lock the stable person row, verify ownership and state, activate the selected credential, revoke/end any replaced assignment, update the pointer, and audit atomically. Verification requires an active credential, matching permanent owner assignment, matching active pointer, valid expiry and valid member authorization.

Credentials move from pending to active only after the read-back workflow. Cancelled/failed/expired pending issuance is unusable. Lost-credential revocation clears the pointer and ends the assignment immediately, even if replacement subsequently fails. A revoked credential remains in history and cannot be reactivated or reassigned. Replacement always creates new random material. Assignment ownership is immutable; termination fields record its history.

## Attempts, confirmed passage and correction history

| Table | Proposed important columns | Constraints and indexes |
|---|---|---|
| `scan_attempts` | `id`, opaque operation ID, `guard_id`, `gate_id`, applicable assignment ID, `request_id`, canonical payload fingerprint, `direction`, nullable `person_id`, nullable `credential_id`, nullable `authorization_id`, immutable verification `outcome`/reason/policy version, `received_at`, `review_expires_at`, controlled `review_status`, nullable `reviewed_by`, `reviewed_at`, bounded review reason, `source` | Unique operation ID and `(guard_id, request_id)`; FKs; indexes `(received_at, id)`, `(gate_id, received_at)`, `(person_id, received_at)`, `(outcome, received_at)`, `(review_status, review_expires_at)`. |
| `access_events` | `id`, `scan_attempt_id`, `person_id`, `credential_assignment_id`, `gate_id`, `guard_id`, `direction`, decision/policy snapshot, `confirmed_at`, `source` | Unique attempt FK; all historical FKs restricted; indexes `(confirmed_at, id)`, `(person_id, confirmed_at)`, `(gate_id, confirmed_at)`, `(direction, confirmed_at)`. |
| `event_corrections` | `id`, `access_event_id`, `action` (`annotate`/`void`), reason, `actor_id`, `created_at` | Event/user FKs; append-only; index `(access_event_id, created_at)`. |

Unknown credentials produce attempts with nullable person/credential references and controlled reason codes. Do not store raw credential input, unrestricted device metadata or personal data in arbitrary JSON. Bound all request fields. If repeated unknown-token correlation is needed for a rule, use a protected fingerprint with a documented retention purpose, not plaintext input.

The review TTL starts at server receipt; the initial proposal is 30 seconds and must be tested at gates. Exact request retries return the same attempt without extending that TTL. Reusing a request key with a different canonical payload is rejected. Confirmation always performs fresh authorization checks, including when returning an already-recorded outcome to an otherwise permitted operator.

For different request IDs, confirmation also checks effective recent passage for the same person/gate/direction under the person lock. Within the configurable duplicate window, write no second event: terminate the new review as `cancelled`, reason `duplicate_passage`, and return an authorized reference to the existing event. Store optional `duplicate_of_event_id` on the attempt, with its FK added after `access_events` exists. Preserve the second verification facts and audit. Select the threshold in Phase 5; test concurrent guards, boundary conditions and intentional later passage. An index supporting person/gate/direction/time should be evaluated for this query.

Allowed verification is not passage. Only an explicit valid guard confirmation inserts an event. Unique attempt linkage prevents double insertion. Lock the person and any persisted presence row in a consistent order, enforce the fresh checks, and commit the event, essential audit data and projection together. Duplicate-tap policy and sequence warnings are separate from transport retry idempotency.

Keep verification facts immutable. Allowed scans start review `pending`; denied scans use `not_applicable`. Only an audited compare-and-set terminal transition from pending to `confirmed`, `identity_mismatch`, `cancelled`, or `expired` is permitted. Store reviewer/time/reason, with server time authoritative for expiry even before an expired state is materialized. Confirm and event insertion commit together; cancellation/rejection must win or lose atomically against confirmation and cannot be undone into success. Take locks in the common person/attempt order and test the race. Exact transport retries do not reopen review. Ordinary users cannot otherwise edit/delete history. A correction preserves original rows and uses an audited effective-event projection. Voiding an event requires recalculating affected presence, report aggregates and related anomaly review context. Query reports from effective confirmed events; count denied scans from attempts, never as passages.

## Conditional tables

| Table / phase | Purpose and proposed constraints |
|---|---|
| `presence_states` / 5 or 7, only if persisted projection is needed | `person_id` primary/FK, state `unknown`/`inside`/`outside`, nullable last effective event FK, update time/version. Lock with person. Start unknown; an Exit from unknown records outside and flags missing context without automatic denial. Derive/rebuild from effective events. |
| `anomaly_flags` / 8 | Rule identifier/version, unique deduplication key, type, severity, explanation, evaluation window, state, reviewer/time/resolution. Index state/severity/time. No guilt or disciplinary conclusion. |
| `anomaly_evidence` / 8 | Composite primary key `(anomaly_flag_id, scan_attempt_id)` with FKs. Evidence can relate to confirmed events through their attempt. |
| `incidents` / guard reporting scope | Reporter and gate FKs, optional attempt FK, bounded description, status, reviewer and resolution/time. Access-controlled text; preserve review history if multiple updates need traceability. |
| `ai_summaries` / 9, conditional approval | Reporting interval, validated aggregate snapshot/hash, prompt/provider/model versions, generation/review status, output text, requester/reviewer FKs, generated time and bounded error code. No raw identities, credentials or API keys. |
| `system_settings` / only where required | Unique allowlisted key, validated value/type, updater and time. Audit changes; exclude secrets and arbitrary executable configuration. |
| `import_batches` / deferred | Create only with an approved import module and its validation, rollback/error and provenance requirements. |

`audit_logs` is a core foundation, not conditional: actor FK may be null for system events; store action, target type/ID, outcome, server time, and redacted bounded changes. Index actor/time, action/time and target. Its polymorphic target is a controlled audit reference rather than a relational authorization shortcut. Keep essential mutation audits in the same transaction. Do not claim application append-only controls prevent database administrator tampering.

## Implementation and verification constraints

- Use migrations in dependency order: users/people/gates, grants and assignments, credentials and owner assignments, active-pointer FK, attempts/events, then extensions. Framework session/reset/job/cache tables are selected according to runtime configuration.
- Use database `CHECK` constraints where supported and tested; Laravel validation alone does not replace foreign keys or uniqueness. Cross-row state and validity require transactional services and concurrency tests.
- Prefer deactivation and restricted deletion. Retention/anonymization migrations require an approved institutional policy, legal hold handling and a backup-aware disposal plan.
- Convert Asia/Manila reporting periods into half-open UTC ranges. Test midnight and month/year boundaries. Keep scan receipt and confirmed passage timestamps distinct.
- Verify duplicate keys, credential-owner mismatch, interrupted replacement, immediate lost-token revocation, expiration during review, concurrent confirmation/replacement and projection rebuilds using the actual selected database engine.
- Test query plans with representative authorized fixtures before adding indexes beyond known filters. No operational rows or counts are hardcoded in React. Development/test fixtures remain explicitly separate from institutional data.
