# Security and privacy plan

Status: **planned controls, not a completed security implementation or penetration test**. Runtime/tooling findings and remediation prerequisites are in [environment audit](environment-audit.md).

## Trust boundaries and threats

| Threat / boundary | Planned control | Residual limitation |
| --- | --- | --- |
| Cloned/stolen/shared NFC token | Random opaque token, private photo/identity review, revocation, short review window, rule indicators | A clonable NDEF token is not cryptographic proof of holder identity or physical passage. |
| Untrusted browser/client | Validate schema, sizes, role/account/gate/assignment/person/grant/credential server-side at scan and confirmation | An authorized operator may still misreport an observation; operational supervision is necessary. |
| Unauthorized object access | Policies for every identity, photograph, event, export, assignment and summary; scoped queries | Frontend role checks provide no security boundary. |
| Retry/replay/concurrent operations | Bound attempt, expiry, payload-bound idempotency keys, unique constraints, stable lock order and transactions | Deduplication cannot identify every deliberate physical replay across windows. |
| Session theft/CSRF/brute force | Session auth, regeneration on login, logout invalidation, CSRF, secure/HttpOnly/SameSite cookies, rate limits | Account sharing and compromised endpoints need institutional controls. |
| Database compromise / token leak | Store SHA-256 digest of a 32-byte random secret, never recoverable plaintext; restricted DB account, encrypted transport/backups | An adversary controlling the application can submit or fabricate operations; hashing does not secure a compromised server. |
| Injection/XSS/upload abuse | Laravel validation, parameterized queries, React escaping, no arbitrary HTML, sanitized exports, image re-encoding | Dependency/configuration mistakes remain possible; verify with threat-driven tests. |
| Unavailable network/database | No authorization success offline; no unconfirmed passage inserts; retry/status recovery, manual institutional fallback | Physical gate continuity and emergency egress remain operational responsibilities. |
| History tampering | Immutable originals, append-only corrections/audit, restricted DB privileges and backups | Application audit storage is not tamper-proof against a DB/server administrator. |
| AI leakage/hallucination | Aggregate-only approved payloads, server keys, provenance, consistency checks and human review | A provider can fail or produce misleading prose; AI never grants access or determines wrongdoing. |

## Authentication and authorization

Use Laravel's supported session/password-reset facilities, not a custom password protocol. Private account provisioning; password hashing using framework-supported secure algorithms; throttle login/reset attempts and avoid account enumeration. Rotate sessions on login, invalidate on logout, enforce disabled accounts on every protected request, and invalidate existing sessions on relevant account/credential changes. Protect last-administrator availability.

Recover passwords using expiring single-use tokens and configured mail delivery; development may use a local mail sink, but production recovery is not complete until tested with the approved provider. Keep reset links and session IDs out of logs. Production uses HTTPS, Secure/HttpOnly cookies and an intentional SameSite policy; no wildcard credentialed CORS. Consider administrator MFA during security review if operational requirements justify it.

Keep role rules small and explicit: two roles plus object/time-scoped gate assignment checks. An admin does not automatically scan. Every privileged mutation needs validation, policy enforcement and an audit entry. Resetting a password, revoking a credential, and changing an assignment must not be possible through a guard session.

## Credential and gate processing

Use PHP's cryptographic random generator for 32 random bytes; encode a versioned NDEF text reference, store its unique binary SHA-256 digest and a nonsensitive display label. High token entropy makes a fast one-way digest appropriate; it is not a password hash. Never log tokens or put them in URLs, analytics, localStorage, crash reports or export files. Issuance reveals plaintext only transiently to the authorized writer; lost issuance responses require a fresh pending credential.

Credential replacement revokes a lost token immediately. Under a stable per-person lock, permit only one active credential assignment and revalidate at confirmation. No browser-only authorization, alternating directions, identity assertion from hardware UID, or assertion that an ordinary NTAG215 prevents copying. See [NFC lifecycle](nfc-integration.md) and [transaction plan](architecture.md).

## Private data and files

Store photographs outside public web storage and serve only through authenticated authorized responses. Validate actual media type, size and dimensions; re-encode raster images, remove metadata, generate safe filenames, reject active formats such as SVG for identity uploads, and disallow path traversal. Do not send private photos through public URLs or external AI.

Reports require scoped authorization and audit. Neutralize spreadsheet formula injection in user-originated CSV/XLSX text; treat generated filenames and PDF/HTML content as untrusted. Heavy exports may run as queued jobs with private expiring downloads. Avoid arbitrary remote resource fetching by PDF tools. Select export libraries only in Phase 7 after compatibility/security review.

Do not commit `.env`, reset links, provider secrets, database dumps, credential plaintext, private images or real sample records. Future `.gitignore` and repository review must cover these before the first checkpoint. Keep runtime errors detailed locally only; production uses `APP_DEBUG=false`, safe error pages and redacted structured logs. Secrets belong in environment/secret management, not editable application settings.

## Privacy and research use

Plan for purpose limitation, transparency, proportionate collection, justified retention and appropriate safeguards consistent with [NPC Data Privacy Act implementing rules](https://privacy.gov.ph/implementing-rules-regulations-data-privacy-act-2012/). This is a design basis, not an institutional compliance determination.

Before any real records or study participants: institution/DPO review establishes lawful basis, authorized fields and users, privacy notice, retention/deletion periods, correction/access request handling, incident response, research approval and external processor terms. Do not invent a statutory retention period or assume consent from a campus credential. Use synthetic fixtures until authorization exists. Restrict access to identifiers, photographs and movement histories; pseudonymize research exports where possible.

Retention must cover operational records, denials, photos, audits, reports, AI snapshots and backups. Historical foreign-key restrictions prevent arbitrary deletion; a later approved retention procedure must coordinate anonymization/purge, legal holds and backup expiry without silently rewriting research evidence.

## Operational hardening and verification

Use supported patched runtimes, exact dependency lockfiles, integrity-verified tooling and dependency audits. Serve Laravel `public` only and block the original shared `htdocs` route to source files. A new vhost or loopback Laravel server alone does not remove that route. The current workspace is unsuitable for secrets without this isolation. Restrict admin tools/database interfaces and private dev hosts. No firewall or service settings were changed in Phase 0.

Use least-privilege DB credentials, TLS in transit, secure backup storage and tested restore procedures. Restrict ordinary application writes to audits/history; central protected logging may later strengthen tamper detection. Monitor failed authentication, forbidden object access, credential abuse and failed jobs with redaction. Define update ownership and incident response before operations.

Security testing will use [OWASP ASVS](https://owasp.org/www-project-application-security-verification-standard/) as a control checklist, scaled to approved scope. No ASVS certification or complete security guarantee is claimed. See [test plan](testing-and-research.md) for negative, concurrency and recovery cases.

## Open risks

Current vulnerable/mismatched tools require remediation; Windows update posture and external reachability are unverified. Human review accuracy, peak traffic, missing-photo policy, manual outage procedure, retention, authoritative access schedules and institution-approved deployment/AI processing remain unresolved. These are explicit acceptance inputs for later phases, not reasons to invent operational defaults now.
