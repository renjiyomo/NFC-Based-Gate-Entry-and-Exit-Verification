# Phase 7 — Monitoring, analytics and reports

Completed November 9, 2026 for the authorized local development scope. Phase 8 has not started. The remaining physical/operational acceptance limits in [Phase 6](phase-6-report.md) still apply.

## Delivered behavior

- Administrator overview, history, analytics and reports now query saved records. Current directory counts, confirmed Entries/Exits, denied verifications, suppressed duplicates, recent attempts, daily trends, gate/type/hour comparisons and denial reasons have honest empty states.
- Daily, Monday–Sunday weekly, monthly and custom Manila periods share one validated query/filter service. UTC microseconds are preserved. Bounds are inclusive start, exclusive next-day end, capped at the request snapshot; future dates are not drawn as observed zeroes. Custom ranges are limited to 366 days.
- History supports person/name/number/type, gate, guard, direction, verification outcome, effective review state, duplicate status and passage validity filters. Pages contain 25/50/100 records; applying filters starts on page 1 and stale page links recover to a valid page. Details retain archived identities and traceable source facts.
- Notes, voids and reinstatements append an administrator, reason and timestamp. Original passages and attempts remain immutable. A stale correction revision is rejected. The latest void/reinstate decision determines effective reporting; a later note does not change that decision. Voided events also stop influencing duplicate suppression and sequence checks; guard recovery marks a voided passage explicitly.
- Private audited CSV, Excel and PDF downloads use the same filters and definitions across all matching pages. Summary, passage and attempt datasets are available. CSV/Excel allow 10,000 rows and PDF 500; oversized exports request narrower filters rather than silently truncating. Files include workspace/source, period, filters and generation snapshot. Recent export audit records show prepared files, not proof the recipient opened them.

## Definitions and boundaries

Only effective, guard-confirmed passages count as Entries/Exits. Allowed verification alone, denied scans, cancelled/expired reviews and suppressed duplicates do not add passages. Attempt periods use received time; passage periods use confirmation time. A review completed later can therefore fall in a different passage period from its attempt.

Presence is each retained person's last effective direction through the reporting cutoff across all gates. It intentionally ignores gate, guard, direction and lower date bounds; person/type filters still apply. Inside/outside/unknown are estimates, including archived people with retained history. Missing crossings/scans limit them. Gate activity does not establish classroom attendance. Directory counts describe current records, independently of the selected historic period. Names, gate descriptions and correction state are evaluated at generation time; an old download is not a retained database snapshot.

Source is fixed by the authorized workspace: simulated on the simulator, physical on the normal/hardware workspace. Client source overrides are rejected. Lab reports are explicitly marked synthetic. No sample records were added to the main schema. Administrators alone can access global monitoring, corrections and exports; guard station history remains scoped as before.

CSV formula-like cells receive a leading apostrophe; Excel uses literal string cells, never inferred formulas. PDF rendering disables remote resources, PHP and JavaScript. Exports have private/no-store headers and bounded server generation. Temporary Excel files are private and removed after generation. NFC references/nonces/digests never enter report DTOs; searches and correction notes reject NFC reference formats and are excluded from flashed input.

## Schema and dependencies

Migration 16 adds append-only `passage_corrections` and source/time, source/person/time and source/gate/time passage indexes plus a source/time attempt index. All four recognized schemas have 16 applied migrations. Runtime correction privileges are SELECT/INSERT only. Stable person locks serialize corrections with guard confirmation; revision checks prevent stale administrative decisions.

Excel uses OpenSpout 5.13.0 and PDF uses Dompdf 3.1.6, resolved in Composer's lockfile. The project PHP minimum is now 8.4 for OpenSpout 5; the isolated PHP 8.5.12 runtime is unchanged. No shared XAMPP service or configuration was replaced.

## Verification

`scripts/dev.ps1 check` passed: **159 PHP tests / 1,705 assertions**, **25 NFC adapter tests**, all four multi-process MySQL races, strict TypeScript/Vite build, formatting, Composer validation and dependency audits (zero reported vulnerabilities). The race harness restored its owned fixture counts. New checks cover Manila/microsecond boundaries, current/future periods, shared totals, pagination/filter changes, archived history, presence, corrections/immutability/stale revisions, source/role isolation, secret handling, exports/formulas and export limits.

On MySQL 8.4.13, 10,001 isolated attempts and passages exercised indexed period queries. The selected day matched 1,440 records. Passage aggregation used `event_source_clock` with indexed correction lookups; attempt aggregation used `attempt_source_clock`; both had range access. The recorded local query times were 4.440 ms and 1.711 ms respectively. These are individual synthetic query measurements, not campus throughput or end-to-end latency benchmarks. Fixtures rolled back. [Sanitized plan evidence](evidence/phase-7-query-plans.json).

Browser QA covered desktop and 393×851 phone widths, actual hardware daily/monthly counts, source labels, history pagination/denial filters, CSV/Excel/PDF downloads and export audit display. The November hardware month reconciled to **4 Entries + 2 Exits = 6 passages** and **26 attempts**; November 9 alone had 3 Entries, 0 Exits, 19 attempts, 7 denials and 2 duplicates. Both active credential assignments remain unchanged. The simulator correction QA appended a note, void and reinstatement to one existing synthetic passage; the original five passages remain retained and effective after reinstatement. Main and hardware received no corrections. PDF QA inspected every page of synthetic summary/detail files and the four-page browser-downloaded attempt report; headings repeat, footers are numbered and data is legible without clipping. The summary spacing was adjusted to avoid a nearly empty trailing page.

Main remains 4 users / 2 people / 1 gate / zero credentials, attempts and passages; all three requested administrators are active. Simulator retains 7 closed credentials / 11 attempts / 5 passages, plus the three QA correction entries. Hardware retains 9 credentials / 26 attempts / 6 passages, with no pending reviews. Testing fixtures are gone. Runtime SQL rejects UPDATE/DELETE on corrections and existing immutable tables. Secret/log scans passed. Root Apache denial and loopback service isolation remain in place.

## Limits and next phase

Reports are bounded synchronous downloads. Very large institutional datasets may need background exports, retention policy and further measured query tuning, especially all-history presence ranking. Charts include textual/table alternatives; comprehensive assistive-technology certification and production load testing remain outstanding. Downloaded reports contain identity data and need institution-approved handling/retention before real deployment. Existing Phase 6 unperformed hardware cases remain unperformed.

Phase 7 is complete for local development. Stop here. Phase 8 requires explicit approval and concerns explainable anomaly review; no anomaly rules, AI provider or public deployment were introduced.
