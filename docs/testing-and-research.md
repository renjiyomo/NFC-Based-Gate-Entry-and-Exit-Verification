# Testing and research evaluation

Status: **Phase 10 local verification completed November 9, 2026:** 189 PHP tests / 2,128 assertions, 25 NFC adapter tests, eight observed-overlap MySQL race scenarios, audits/style/typecheck/build and targeted desktop/mobile/keyboard checks passed. Query measurements use 10,001 attempts/passages in a rolled-back test transaction. See [Phase 10 evidence](phase-10-report.md). Core physical NFC has user-reported RF/UI and independent server evidence under [Phase 6](phase-6-report.md); broader physical, peak-load, institutional usability and deployment/restore acceptance remain outstanding. No participant results or production capacity claims are implied.

## Coverage and remaining acceptance cases

| Area / phase | Meaningful acceptance cases |
| --- | --- |
| Foundation / 1 | Typecheck and production build; required package peers; Composer/npm audits; page navigation and honest unavailable/empty states; mobile layouts, keyboard/focus/error announcements. |
| Authentication / 2 | Correct/wrong password, throttle, disabled accounts, session fixation prevention, logout invalidation, CSRF rejection, reset expiry/single use, enumeration resistance, role and cross-record policy denial. |
| Identity/gates / 3 | Validation, uniqueness, profile/type consistency, create/edit/status persistence, search/filter/pagination, restricted photos, forged IDs, unassigned/inactive gates and assignment interval boundaries. |
| Credential lifecycle / 4 | Secure random format and digest-only storage; permanent owner; pending/active/revoked/expired; wrong read-back, lost response, issuance timeout, replacement failure, immediate loss revocation and concurrent activation. |
| Gate workflow / 5 | Valid Entry/Exit, unknown/malformed/revoked/inactive credential, expired grant, wrong guard/gate/direction, permission changes during review, allowed scan without passage, identity mismatch/cancel/expiry, concern reporting. |
| Idempotency/concurrency / 4–5 | Identical request replay; same key with changed input rejected; two confirmations of one attempt create one event; different request IDs/guards for the same person/gate/direction within the duplicate window create one passage and an audited duplicate result; boundary and intentional later passage; confirm/cancel race; confirm/revoke race; DB rollback with no partial event/audit/presence. |
| Sequence/presence / 5–7 | Initial unknown; Entry then Exit; repeated Entry; Exit from unknown; no automatic alternating; correction void/rebuild; missing/out-of-order scans remain limitations of estimated presence. |
| Browser NFC / 6 | Unsupported API/hardware, denied permission, aborted or malformed NDEF read, multiple records, encoding/size rules, NFC disabled, locked/background phone, interrupted write and read-back. Software mocks are labeled separately. |
| Reporting/export / 7 | Counts reconcile to effective confirmed events and denied attempts; gate/person/type/outcome filters; Manila midnight/month/year boundaries; zero records; corrections; pagination; CSV/XLSX formulas; PDF and private download authorization. |
| Anomalies / 8 | Each rule's true and false scenarios, threshold/window boundary, deduplication, evidence, severity/review/resolution history and policy-version provenance. |
| AI / 9 | Approved aggregate payload only, missing/invalid aggregate, invented or changed counts rejected, period/gate consistency, provider timeout/error/rate-limit, history/provenance, human review and no dependency of gate flow on AI availability. |
| Cross-cutting / 10–11 | Injection/XSS/CSRF, private media traversal/format handling, log/secret leakage, secure cookies/HTTPS, production simulator rejection, actual DB engine query plans/concurrency, representative load, backup restore and manual outage exercise. |

The implemented suite uses PHPUnit/Laravel on the exact isolated MySQL engine, Node tests for the NFC adapter, and targeted in-app browser DOM/keyboard/visual checks. Mock browser NFC verifies software logic only. SecurityReviewTest disables Laravel's automatic test CSRF exemption to exercise the real rejection path. scripts/dev.ps1 check runs audits, style, TypeScript/build, adapter tests, PHP tests and the eight-process-race harness. There is no full automated browser/axe or screen-reader suite; the Phase 10 report identifies manual coverage.

Tests verify testing environment, 127.0.0.1:3307/nfcheck_testing/nfcheck_test, MySQL 8.4.13 and the project data directory before operations. Feature/performance fixtures roll back. The multi-process harness requires empty testing history, captures owned fixture IDs, uses only scoped maintenance cleanup, and restores the quota singleton and baseline counts. It does not truncate/reset schemas or modify lab/main history. SQLite is not substituted for MySQL acceptance.

## Phase 11 completion and research handoff

The final full suite passed **196 PHP tests / 2,151 assertions, 25 NFC adapter tests and eight observed-overlap MySQL race scenarios**. Three encrypted backups restored all 24 tables into newly owned disposable schemas with identical row fingerprints and unchanged source, then removed the owned schemas/plaintext work files. See [recovery receipts](evidence/phase-11-recovery.json), [Phase 11](phase-11-report.md) and [final readiness](final-readiness.md). Configuration preflight is read-only; it deliberately fails on the local development environment and is not deployment approval.

Research handoff must keep automated fixture results, simulator results, user-reported RF/UI plus independently checked backend evidence, and future institution-approved participant outcomes separate. No respondents, RF latency distribution, campus throughput, confusion matrix for actual incidents or usability scores have been invented. The Phase 8 synthetic rule evaluation and Phase 9 one-call aggregate evidence remain their own bounded datasets. Obtain approved design/truth labels, sampling, privacy/retention and participant permission before collecting research data; report denominators, failures, missing scans and unresolved cases. Broader NFC acceptance, peak-load/supervised usability, actual host/HTTPS/mail, off-device/key escrow and host-loss/photo recovery remain outstanding.

## Definitions for accurate analytics

- **Scan attempt:** a validated operation request and its immutable verification decision; an exact network retry does not create another attempt.
- **Denied attempt:** a persisted attempt whose verification decision denies access. Malformed/unauthenticated requests rejected before the workflow belong in bounded security logs, not fabricated person events.
- **Passage:** one successful guard confirmation of an allowed, unexpired, still-authorized attempt. Cancels/rejections/expiry do not count.
- **Effective passage:** confirmed event not voided by an authorized append-only correction. Counts/trends use effective events while preserving original history.
- **Estimated presence:** persons whose effective latest recorded passage indicates inside, with unknown/missing-scan coverage disclosed. It is not a physical census.
- **Reporting day:** half-open midnight-to-midnight Asia/Manila interval converted to UTC for queries; server receipt and confirmation times remain separate.

Transport retries, physically repeated taps, denied references and passage sequence anomalies need separate test assertions and metrics. Never count a denied scan as an exit or infer direction by toggling.

## Research questions and measures

1. **Verification correctness:** compare backend outcomes to a predefined synthetic authorization/credential truth set. Report false accept/deny outcomes separately from human identity-review errors. A matching reference is not evidence that its holder is the owner.
2. **Performance:** measure read-to-server-response, guard-review-to-confirmation, total interaction time, throughput and error/retry rates. Report p50/p95 with sample size, phone/server specifications, network, load, database size and procedure. Establish thresholds with actual gate-volume evidence; none have been invented.
3. **Data correctness:** reconcile events, occupancy projection, charts and exports against a known event ledger including retries, corrections, midnight boundaries and missing sequences.
4. **Anomaly effectiveness:** version rules and use independently labeled scenarios. Report confusion matrix, precision `TP/(TP+FP)`, recall `TP/(TP+FN)` and false-positive analysis with the evaluated scenario distribution. Handle zero denominators explicitly. Do not equate flags with misconduct or treat synthetic-scenario scores as campus-wide performance.
5. **Usability:** with institutional/research approval, observe representative guards/admins completing defined tasks. Measure completion/error/time and structured feedback; inspect accessibility and phone use at a realistic station. Do not fabricate respondents or results.
6. **AI factuality — if approved:** compare every factual/quantitative claim with its aggregate snapshot and period, assess readability through authorized review, record rejected summaries/provider failures. Generated text is a draft interpretation, never an authoritative incident finding.

## Evidence and honest reporting

Each later report records commands, build/commit identifier, environment, fixture data, test counts/failures, screenshots where useful, and known gaps. Keep synthetic, simulated-software, physical-device and institution-authorized evaluation evidence separate. Protect identity and movement data in screenshots/logs and research exports.

Do not label physical NFC, exact occupancy, identity assurance, anti-cloning effectiveness, legal compliance, production capacity or complete security as verified merely because automated tests pass. See [physical NFC checklist](nfc-integration.md) and [phase acceptance gates](roadmap.md).
