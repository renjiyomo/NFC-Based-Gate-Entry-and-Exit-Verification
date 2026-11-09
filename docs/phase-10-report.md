# Phase 10 — security, testing and optimization

Completed **November 9, 2026**, for the isolated local development scope. Phase 11 has not started. This is a focused source, dependency, database, automated-test and browser review; it is not an independent penetration test, ASVS certification or production-readiness guarantee.

## Outcome and fixes

| Finding | Review severity / effect | Resolution and evidence |
| --- | --- | --- |
| Concurrent anomaly detection could throw a duplicate-key exception | Medium: overlapping runs by different administrators could fail despite the unique detection key | Reproduced with two separate PHP processes blocked at the same insertion barrier. After a collision, use a shared locking read to see the committed winner under MySQL REPEATABLE READ. One flag/evidence set is retained; no UPDATE privilege was added. The real-process regression now passes. |
| Browser policy did not restrict script execution or connection destinations | Medium, defense in depth: an injection defect would have had fewer browser restrictions | Default-deny CSP, fresh script nonces, no inline event handlers/eval, same-origin connections/forms, no frames/objects/workers/media. Built React pages, lazy-loaded charts and saved summaries render without observed CSP errors. This policy still allows same-origin scripts and inline styles required by the existing chart/layout implementation; it is not a fully nonce-only strict CSP. |
| Development server could log arbitrary unmatched paths and static query strings | Medium, confidentiality: accidental sensitive URL contents could enter access logs | Log fixed route categories and redact deeper path segments; omit all query strings. Serve allowed public static assets within the custom router instead of delegating to PHP's raw access logger. Control-character paths return 400; dot/traversal/private-path probes return 404. Owned loopback-server regression verifies redaction, HEAD and method restrictions. |

No unresolved high/critical finding was identified within this reviewed local scope. The deployment and institutional limitations below remain acceptance prerequisites. No packages, migrations, runtime grants, passwords or active credentials were changed. The phase indicator now displays 10.

## Security review and evidence

The review used [OWASP ASVS 5.0.0](https://asvs.owasp.org/) as a checklist basis, with [OWASP CSP](https://cheatsheetseries.owasp.org/cheatsheets/Content_Security_Policy_Cheat_Sheet.html) and [file-upload guidance](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html), consulted November 9, 2026. Coverage is selective; no ASVS level is claimed.

| Area | Implemented control / checked evidence | Limit |
| --- | --- | --- |
| Authentication | Framework password verification, generic failure/reset responses, per-subject/IP and IP-wide throttles, private provisioning, login session rotation, logout invalidation, account-version enforcement and last-admin protection; Authentication, PasswordRecovery and AccountSecurity tests | No MFA or institutional identity provider; account sharing/compromised endpoints remain risks. |
| Sessions / CSRF | Encrypted database session payload, 30-minute idle expiry, JSON serialization, HttpOnly, SameSite=Lax, separate workspace keys/cookies. Actual missing/wrong-token rejection exercised with Laravel's test exemption disabled, including guard, correction, summary and anomaly mutations | Secure cookies are intentionally false on loopback HTTP. HTTPS/Secure/proxy policy and production mail are Phase 11 acceptance work. Laravel's supported HTTPS origin check/token fallback is retained; see [framework CSRF documentation](https://laravel.com/framework/docs/13.x/csrf). |
| Authorization | Server role policies and source/object/gate/assignment scopes; fresh account/person/grant/credential checks at confirmation; forged IDs, disabled accounts, stale versions and source overrides covered by the suite | Human identity/observed-passage accuracy needs approved operational procedures. |
| SQL / XSS | Bound queries, escaped LIKE search, whitelisted filters, no arbitrary React HTML; SQL/HTML payload regression confirms input remains data; CSP adds browser restrictions | This is targeted payload and source review, not exhaustive fuzzing. |
| Uploads / exports | Private photos, actual raster validation, 2 MB/dimension bounds, JPEG re-encoding and metadata removal, generated paths, authorized delivery; PHP/SVG-disguise/corrupt-image/traversal rejection. CSV formula neutralization, literal XLSX text and PDF remote/PHP/JS disabled | Private storage and backups still depend on host protection; no production malware-scanning service was installed. |
| NFC / recovery | Digest-only random reference storage, permanent owner, checked destination before writing, matching read-back and activation, replacement/revocation, explicit direction/review, idempotency, expiry and duplicate suppression; 25 adapter tests plus lifecycle/gate feature tests | Ordinary NTAG215 contents can be copied or overwritten outside NFCheck. Physical acceptance remains partial under Phase 6. |
| Database / integrity | Four isolated MySQL schemas, runtime least privilege, immutable facts/corrections/audits, transaction rollback. 23 forbidden SQL operations rejected for EACH runtime account; no runtime DDL privilege | A database/OS administrator can still tamper; backups/restore and retention procedures are not validated yet. |
| AI / secrets | Test-only provider and HTTP fakes; single-claim and concurrent quota regressions; main/real-data AI excluded; no extra live Gemini requests. Private-value repository scan and NFC-pattern log scan passed | Google account billing state cannot be enforced by app code. User's Free Tier only/no-payment/no-billing policy remains mandatory. |

## Verification results

`scripts/dev.ps1 check` passed:

- **189 PHP tests / 2,128 assertions**, including 7 new router/security/log tests (110 assertions in the targeted run).
- **25 NFC adapter tests**; TypeScript and production build; PHP and frontend formatting checks.
- **8 real multi-process MySQL race scenarios**, each with observed overlapping blocked transactions: different-guard duplicate confirmation; same-request retry; confirm/cancel; confirm/revoke; different-admin detection; same-revision anomaly review; same-summary single claim; different-summary one-claim quota. Testing counts and the original quota singleton values were restored after scoped cleanup.
- Composer validation and audit: no known vulnerability advisories; npm audit: zero vulnerabilities at execution time. Audits do not rule out undisclosed vulnerabilities.
- Read-only runtime verification: all four schemas retain **18 migrations**. Each runtime account rejected all 23 immutable/DDL-related privilege probes. Private key/password values were absent from tracked/reviewable files; no NFC plaintext pattern was found in checked application/lab logs.

Artifacts are ignored local evidence under `.tools/artifacts/`: `phase-10-check.log`, `phase-10-security-tests.log`, `phase-10-concurrency.log`, `phase-10-performance.json`, `phase-10-runtime.json`, and desktop/mobile/scanner screenshots. Re-running the performance test replaces timing samples. Artifacts contain synthetic evidence and are not public exports.

## Query performance

Measured on this Windows laptop, Intel Core i5-12450H, approximately 16 GB RAM, isolated PHP 8.5.12 and MySQL 8.4.13 over loopback. The guarded test transaction inserts **10,001 synthetic attempts and 10,001 passages**; the selected Manila day has 1,440 matching rows. All fixture rows roll back. Measurements are 20 warm sequential invocations per workload, using `hrtime`, with query logging enabled. p50 is the middle-pair average; p95 is the nearest-rank 19th ordered sample. Values from the final full check:

| Database/application workload | p50 ms | p95 ms | Queries per invocation |
| --- | ---: | ---: | ---: |
| Full report summary including presence | 85.341 | 97.563 | 9 |
| Campus-wide last-direction presence | 28.745 | 39.837 | 1 |
| History page, 25 rows | 17.051 | 18.460 | 8 |
| History page, 100 rows | 28.119 | 31.568 | 8 |

EXPLAIN confirms source/date range access through `event_source_clock` and `attempt_source_clock`. History query count remains constant as page size grows, providing evidence against page-row N+1 behavior. Presence still ranks eligible historical events; its cost will grow with retained history. Existing bounds remain: paginated history, CSV/XLSX exports at 10,000 rows and PDF at 500 rows, detection at 31 days/1,000 attempts, bounded summary jobs. No speculative index/cache/materialized projection was added because these measurements did not identify a justified bottleneck. These are local query timings, not HTTP throughput, RF latency, peak-campus load capacity or a service-level guarantee. Multi-person/skewed histories and production-like concurrent traffic still need a deployment-specific load exercise.

## Responsive and accessibility checks

In the in-app browser, checked the built application at the normal desktop viewport (1280 CSS px) and a temporary 393 × 851 phone viewport (reported effective inner width 394). Login, populated dashboard/charts, credential registry, existing approved summary and assigned hardware-guard scanner loaded. Desktop and mobile document widths fit their viewports; wide record tables retain their labeled horizontal scrolling regions. Chart text and expanded daily data table show the same 4 Entries / 1 Exit as the saved simulator ledger.

The reviewed dashboard has one main landmark and h1, no unnamed buttons, no unlabeled form controls or images missing alt text, and a route status announcement. Mobile navigation opens, Escape closes it and returns focus to its trigger. Keyboard date editing submitted the selected reporting day. Guard direction buttons measure about 56 px high and the scan button 48 px. Desktop correctly reports Web NFC unavailable and offers no scan or passage success. No new RF action was taken. No console errors/warnings were observed on these checked screens. The viewport was reset and the hardware guard signed out after QA.

This was a targeted semantic/keyboard/visual check, not a screen-reader user study, automated axe scan or full WCAG 2.2 AA certification. Contrast, zoom, assistive technology and the broader browser/device matrix remain institutional usability acceptance work. The optional Vite hot-server CSP allowance is restricted to explicit loopback port 5173; hot-mode browser behavior was not exercised in this built-asset review.

## Preserved state and next gate

Main remains **4 users / 2 people / 1 gate / zero credentials, attempts, passages, anomalies or summaries**, including all three requested active administrators. Simulator retains **11 attempts / 5 passages / 3 correction entries / 3 flags / one approved summary**. Hardware retains **26 attempts / 6 passages / 9 credential records**, with no pending reviews, anomalies or summaries. Active student HW-TAG-09 and employee HW-TAG-08 remain unchanged on their separate coins. Testing business fixtures are empty after cleanup; the original quota singleton is retained. Shared XAMPP, the Apache source-path 403, existing loopback services and polling workers were preserved.

Outstanding acceptance inputs: trusted production HTTPS and host configuration; backup/restore drill; approved retention/privacy/research and real-data permissions; administrator/guard/outage procedures; clock synchronization and patch ownership; full physical NFC/device/two-guard/post-commit response-loss cases from [Phase 6](phase-6-report.md); campus-volume performance and supervised usability evidence. No public deployment or real campus operation was authorized. **Stop here; Phase 11 requires the user's next instruction.**
