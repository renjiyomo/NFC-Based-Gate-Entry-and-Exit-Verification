# Security and privacy controls

Status: **Implemented local controls reviewed in Phase 10, November 9, 2026.** See [the Phase 10 report](phase-10-report.md) for findings, fixes, exact tests and limits. This is not an independent penetration test, ASVS certification or assurance of complete security. Earlier [environment audit](environment-audit.md) findings are historical; the project now uses its isolated patched toolchain and MySQL instance.

## Trust boundaries

| Threat / boundary | Implemented control | Residual limitation |
| --- | --- | --- |
| Copied/stolen/shared NFC reference | Random opaque reference stored as digest; private identity review; checked coin before writing; matching read-back; replacement/revocation; short confirmation window | Ordinary NTAG215 NDEF is clonable and may be rewritten externally. UID is not identity authentication; a coin cannot notify the database that it was erased. |
| Untrusted browser/operator | Validated schemas, server roles/object/source scopes and fresh account/gate/assignment/person/grant/credential checks at scan and confirmation | A compromised endpoint or dishonest authorized operator can misreport observations. |
| Retry/concurrent operations | Payload-bound request IDs, unique constraints, stable locks, terminal review state and transactions; eight real multi-process regression scenarios | Deduplication does not identify every deliberate physical replay outside its window. |
| Session theft/CSRF/brute force | Supported Laravel password/session/reset flow, login rotation, logout invalidation, account versions, throttles, encrypted database sessions, 30-minute idle expiry, HttpOnly/SameSite=Lax and CSRF | Loopback HTTP uses Secure=false. Trusted HTTPS/Secure cookies and production proxy configuration remain deployment work. No MFA is implemented. |
| Injection/XSS/uploads | Bound queries and escaped search, whitelisted filters, React escaping, constrained CSP, private raster validation/re-encoding, safe report formats | Targeted tests/source review are not exhaustive fuzzing. CSP allows same-origin scripts and inline styles; dependencies and host configuration need continued review. |
| File/URL leakage | Apache root-path denial; public-only loopback app router; path containment and dot-path rejection; route-category logs without queries/reset tokens/object IDs; private media authorization; no-store dynamic responses | An OS administrator or incorrectly configured future web server can expose files. The local router is not a production server. |
| History tampering | Immutable originals, append-only corrections/reviews/audits, narrowly scoped runtime grants, transaction rollback | Audit storage is not tamper-proof against database/OS administrators. |
| Outage | No client-only authorization or trusted offline queue; same-request recovery/retry and expiry; no unconfirmed passage writes | Emergency egress and continuity need an approved manual procedure. |
| AI leakage/hallucination | Synthetic aggregate-only lab scope, server key, validated fact-ID selection, backend wording, saved provenance and human review, bounded single-claim worker/quota | Free-tier processing terms apply; the application cannot enforce Google's billing-account state. AI never grants access or establishes wrongdoing. |

## Authentication, data and credentials

Accounts are privately provisioned; there is no public registration. Password verification/reset uses Laravel facilities. Generic responses and throttles reduce account enumeration/brute force. Security changes invalidate existing sessions; disabled/archived accounts and stale authorization versions are checked again. Administrators do not automatically gain guard scanning rights. Guard activity and private identities/photos are restricted to assigned operations.

Credential references contain 32 cryptographically random bytes with a versioned format. Only a SHA-256 digest is retained; high-entropy random tokens are not passwords. Issuance plaintext and completion nonces are transient, one-time, no-store responses. They must never enter URLs, browser storage, Inertia props, logs or exports. Permanent ownership and a locked active-assignment pointer prevent moving a credential between people. Outside-system erasure/rewrite still requires an administrator to reconcile/retire the old reference.

Photos stay outside public storage, use generated paths, enforce actual raster type/size/dimensions and are re-encoded to strip metadata. Uploaded SVG and executable formats are rejected. CSV neutralizes formulas; XLSX stores user text literally; PDF remote, PHP and JavaScript features are disabled. Reports require authorization and audited private downloads. See [NFC integration](nfc-integration.md) and [architecture](architecture.md).

## Runtime and secret handling

The application uses isolated loopback services and four separate MySQL schemas/keys/cookies. Each runtime account lacks DDL rights and cannot update/delete immutable originals and review history. Migration and scoped testing maintenance credentials are separate from runtime credentials. Never reset a schema or seed synthetic evidence into main.

`.env`, `.tools/secrets`, private photos, database data/dumps and generated evidence remain ignored. Never commit passwords, reset links, provider keys or NFC plaintext. Root `.htaccess` intentionally returns 403 for the shared Apache project URL; only the application `public` directory is served by loopback ports. Keep detailed errors private and use `APP_DEBUG=false` outside controlled tests. Dependency lockfiles and Composer/npm audits passed in Phase 10; they require ongoing patch ownership.

## Privacy and operational acceptance

Before real records or research participants, institution/DPO/research authority must approve purpose, lawful basis, minimum fields/users, notice, retention/deletion, access/correction requests, incident response and processor terms. Historical foreign keys/immutability mean a retention procedure must coordinate authorized anonymization/purge, legal holds and backup expiry. No statutory retention period or institutional approval has been invented. The [NPC implementing rules](https://privacy.gov.ph/implementing-rules-regulations-data-privacy-act-2012/) provide design context, not a compliance determination.

Phase 11 prepared HTTPS/host/proxy and logging templates, tested encrypted local restore, and delivered administrator/guard/outage/recovery instructions plus the [final readiness review](final-readiness.md). Actual production TLS/mail/host acceptance, off-device backup/key escrow and institution-approved procedures remain pending. Broader device/tag, two-guard and post-commit response-loss cases remain in [Phase 6](phase-6-report.md); campus-volume load and supervised usability evidence are pending. Host patch posture, institutional schedules, missing-photo policy and external reachability are not certified. No public deployment or live campus use is authorized.

The focused review follows [OWASP ASVS](https://asvs.owasp.org/), [CSP guidance](https://cheatsheetseries.owasp.org/cheatsheets/Content_Security_Policy_Cheat_Sheet.html) and [upload guidance](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html). See [testing and research](testing-and-research.md) for evidence boundaries.
