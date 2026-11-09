# Phase 8 — Explainable anomaly review

Completed November 9, 2026 for the authorized local development scope. Phase 9 AI has not started. Physical/operational acceptance remains limited as recorded in [Phase 6](phase-6-report.md).

## Delivered workflow

Administrators now have **Anomaly review** in the Monitoring navigation. Expand **Run checks and inspect rules**, select Manila dates and run detection on saved attempts. New flags appear in the review queue; repeating the same evaluation retains existing flags and their human decisions. The queue filters by dates, rule, priority and review status. Its open/in-review count covers all dates in the current workspace, independently of the queue filters.

Open a flag to inspect its explanation, priority, source, immutable rule configuration/version and linked supporting records. The detail view re-evaluates the original rule against current effective records and warns when the evidence set changes after corrections. Administrators can append open/in-review/resolved/false-alarm decisions with reasons. Closed flags must be reopened before a further decision. Reviews retain actor/time/reason; stale revisions are rejected. Resolve means the review was completed, not that misconduct was established. The latest reviewer is displayed; this is not an exclusive assignment mechanism.

Detection is **on demand**, not a continuously running alert service. A run is limited to 31 days and 1,000 attempts; oversized selections are rejected without truncation or partial creation. Supporting context can precede the selected period. Flags use the triggering attempt's received timestamp for date filtering; linked passage confirmation can occur later. Opening the queue does not run detection automatically. No scheduler, email/push delivery, Python, ML, AI or gate-blocking behavior was added.

## Rules and development policy

| Rule | Trigger / evidence | Review priority |
| --- | --- | --- |
| Repeated Entry | Effective Entry immediately follows another effective Entry for the same person/source, across all gates | Low |
| Exit without Entry | Effective Exit has no preceding effective Entry, or immediately follows Exit | Low |
| Denied burst | At least 5 denied attempts at one gate in a 60-second sliding window | Medium |
| Revoked credential burst | At least 3 attempts with the same known revoked credential at one gate in 60 seconds | Medium |
| Duplicate burst | At least 5 suppressed passage confirmations for the same person/gate in 60 seconds | Medium |
| Different gates, short interval | Consecutive effective passages for one person at different gates less than 30 seconds apart | Low |
| After hours | Effective passage falls outside configured daily Manila hours | Low; disabled pending campus hours |

The user approved development defaults of 5 denied attempts / 60 seconds, 3 revoked attempts / 60 seconds and different-gate passages / 30 seconds. Duplicate-burst testing uses 5 suppressed confirmations / 60 seconds. These are hypotheses for local testing, not campus-approved operational policy. `config/anomalies.php` holds the configuration. Threshold rules default on only in local environments and can be explicitly disabled with `ANOMALY_THRESHOLD_RULES=false`; production defaults off. After-hours open/close hours remain null. Tests cover opening/closing boundaries and an overnight schedule without enabling a real campus schedule.

Burst windows exclude the exact lower boundary and include the anchor; simultaneous timestamps are ordered by record ID. Only threshold witness records are linked, so evidence stays bounded. Each additional qualifying anchor can generate a separate flag. Reruns deduplicate the same source/rule/version/anchor; this does not merge a sustained burst into a single incident. Configuration changes produce a new canonical version hash and may generate separate flags under that new version. Original detections remain available.

Denied bursts describe a station pattern, including unrelated unknown references. They do not attribute those attempts to one person. Unknown input is not fingerprinted for tracking. Cross-gate intervals do not establish impossible travel because no gate-distance model exists. Missing scans, reader errors, staged tests, shift work and approved exceptions can explain indicators. Flags never change credentials, authorization, guard decisions, confirmed passages or classroom attendance.

## Implementation and security

Migration 17 adds `anomaly_flags`, `anomaly_evidence` and `anomaly_reviews` in all four recognized schemas. Flags retain source, rule/version/configuration, anchor, explanation, severity and timestamps. A unique detection identity prevents duplicate rows. Evidence links attempts; review records append status/actor/reason/time. All three tables have SELECT/INSERT-only runtime grants; SQL UPDATE/DELETE is rejected. Existing attempts provide a stable locking row for concurrent review serialization, preserving immutable flag privileges. Current locking reads and expected revisions prevent overwriting another review.

Focused services live in `app/Services/Anomalies`; the administrator controller exposes `/admin/anomalies`, POST `.../detect`, GET `.../{uuid}` and POST `.../{uuid}/reviews`. Existing authentication, account revision, password-change, CSRF and throttling boundaries apply. Source is derived from the authorized workspace. Guards cannot list/read/run/review global flags; cross-source details are denied. Responses use explicit safe DTOs, not raw attempt models. Notes reject NFC references and are not flashed into sessions. Audits retain allowlisted action/source/status/version metadata, not freeform notes or NFC secrets.

## Verification and evaluation

The full check passed **165 PHP tests / 1,813 assertions**, **25 NFC adapter tests**, all **four existing multi-process gate races**, strict TypeScript/Vite build, formatting, Composer validation and dependency audits with zero reported vulnerabilities. After final layout refinements, frontend formatting and the production build passed again. New checks cover effective predecessors, corrections to prior/anchor passages, reruns, policy provenance, threshold boundaries, source separation, status history/reopening/stale decisions, role authorization, immutable SQL grants, private DTOs and rejecting oversized detection batches. The database unique key is directly tested; no separate multi-process anomaly-detection race benchmark was performed.

The labeled synthetic evaluation contains **19 recording-pattern scenarios**: **TP 8, TN 11, FP 0, FN 0**. Pattern-label precision and recall are 1.0; false-positive rate is 0.0 on this small deterministic fixture set. Zero denominators are represented as null by the evaluator. These numbers measure conformity to the selected rules, not real-world security effectiveness or detection of misconduct. [Sanitized scenario evidence](evidence/phase-8-evaluation.json).

False-alarm analysis: an initial Exit or repeated Entry can be intentional lab traffic or an authorized exception while still satisfying its recording-pattern rule. The automated workflow test records a missing-Entry exception as false_alarm and exercises reopening. Browser QA reviewed one intentionally repeated simulator Entry as in_review, then false_alarm with an explicit lab reason. Campus evaluation still needs independently labeled observations, expected traffic distribution, approved thresholds, missing-scan rates and false-alarm analysis by rule.

Desktop and 393×851 browser QA verified the empty state, development-policy display, generation from retained simulator records, evidence links, reviewer/status history, false-alarm filter and mobile layout. Three sequence flags were created from the existing 11 simulator attempts; rerunning checked all 11 and created **zero** additional flags. One flag retains two review entries and a false_alarm decision; two remain open. Mobile evidence/queue tables scroll within their regions without document-wide overflow. Browser sessions were signed out afterward.

Main remains 4 users / 2 people / 1 gate / zero credentials, attempts, passages or anomaly flags. All three requested administrators remain active. Simulator retains 7 closed credentials / 11 attempts / 5 passages / 3 earlier passage corrections, now with 3 flags / 6 evidence links / 2 review entries. Hardware retains 9 credentials / 26 attempts / 6 passages / zero flags, no pending reviews and the same active student/employee coins. Testing fixtures rolled back to zero. All four schemas have 17 migrations and reject 13 tested immutable SQL operations. Private-value and NFC-log scans passed. Shared XAMPP, root Apache denial, loopback listeners and USB forwarding were preserved.

## Limits and next phase

Review indicators are provisional and can remain open after evidence changes; the detail screen warns rather than silently deleting or resolving history. Detection/query cost is bounded but has not been benchmarked at campus scale. There is no automated recurrence, external notification, exclusive reviewer assignment, institutional policy editor or anomaly export. Comprehensive assistive-technology and production load certification remain outstanding. These limits and Phase 6's unperformed physical cases must be addressed before operational deployment.

Stop after Phase 8. Phase 9 is optional AI summaries and requires separate scope, provider, privacy and budget approval. No external AI processing has been configured.
