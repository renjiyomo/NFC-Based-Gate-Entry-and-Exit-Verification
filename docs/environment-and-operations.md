# Environment and operations reference

The [production template](../deploy/production.env.example) is unapplied. `.env.example` remains the local-development template. Actual secrets are excluded from Git and must be handed off privately.

| Configuration | Purpose and handling |
| --- | --- |
| APP_ENV / APP_DEBUG / APP_URL | Production uses production / false / the exact approved HTTPS origin. Reset links use APP_URL. Local uses loopback. |
| APP_KEY | Private 32-byte application key. Retain the matching key across updates/restores. Separate keys per workspace; replacement is a planned security/data migration. |
| DB_CONNECTION / HOST / PORT / DATABASE / USERNAME / PASSWORD | mysql and the verified instance/schema/restricted runtime user. Local is 127.0.0.1:3307 with nfcheck, nfcheck_testing, nfcheck_simulator or nfcheck_hardware. Maintenance credentials never belong in the web environment. Review any DB_URL override. |
| SESSION_DRIVER / LIFETIME / ENCRYPT / SECURE_COOKIE / HTTP_ONLY / SAME_SITE | database / 30 minutes / true / true on HTTPS / true / lax or approved strict policy. Host-only cookies; separate workspace keys/cookies. Loopback HTTP intentionally uses Secure=false. |
| NFCHECK_UI_PREVIEW / NFCHECK_NFC_SIMULATOR / NFCHECK_HARDWARE_LAB | All false in production. Lab routes also enforce environment/source/loopback restrictions. |
| NFCHECK_REVIEW_SECONDS / NFCHECK_DUPLICATE_SECONDS | Lab values 30 / 10. Obtain campus workflow approval before operations. |
| ANOMALY_THRESHOLD_RULES | Development hypotheses; false in production pending approved thresholds. Campus hours are absent; after-hours checks are disabled. |
| FILESYSTEM_DISK | local; protected storage/app/private. No public photo symlink or automatic local serving. Persist approved private files across releases. |
| LOG_CHANNEL / LOG_LEVEL / LOG_DAILY_DAYS | Candidate daily / warning / 14 diagnostic days, subject to retention approval. Business-audit retention is separate. Never log credentials, NFC references/nonces, session/reset tokens, API keys or request bodies. |
| MAIL_MAILER / SCHEME / HOST / PORT / USERNAME / PASSWORD / FROM_ADDRESS | Local Mailpit captures messages only. Production needs approved authenticated TLS SMTP/sender and tested reset delivery. Template smtps:465 is a placeholder. |
| AI_SUMMARIES_ENABLED | False in production/main. Exclude the private lab provider file from deployment. Gemini Free Tier only; no billing/payment ever or paid fallback. |
| CACHE_STORE / QUEUE_CONNECTION / BROADCAST_CONNECTION | Current file / sync / log. The separate bounded lab summary worker is not a general production queue setup. |
| APP_MAINTENANCE_DRIVER / VITE_APP_NAME | File maintenance marker; display name only. Never expose server secrets through VITE_* values. Build assets; omit public/hot. |

## Routine local operation

Use `scripts/dev.ps1 services` to verify/reuse private MySQL/Mailpit, `simulator` and `hardware` for the separate lab servers, and `serve` only when main port 8000 is free. The scripts reject unexpected process/port identities. Preserve shared XAMPP, unrelated processes and root .htaccess. Summary-worker PID metadata is in `.tools/runtime/summary-*.json`; never stop processes by executable name. A relevant code update needs a reviewed restart of only its owned worker; this phase needed no restart.

`scripts/dev.ps1 check` runs audits, formatting, TypeScript/build, NFC software tests, MySQL tests and eight process races. `scripts/backup-workspace.ps1` creates local recovery evidence. `scripts/dev.ps1 artisan nfcheck:check-deployment --json` is read-only and deliberately returns failure for development configuration; do not change the live lab merely to make it green.

Expiry is enforced per operation. Optional housekeeping commands are `nfcheck:expire-reviews` and `nfcheck:expire-credentials`, under the correct environment; neither creates passage. No scheduler was installed. Do not mix workspace keys/schemas or reset existing schemas.

## Logs, monitoring and incidents

Audit metadata is allowlisted in audit_logs with retained actor/target references. Settings is not an editable audit viewer. Authorized DB/operations review needs minimum necessary access. Runtime grants prevent ordinary audit UPDATE/DELETE, not privileged administrator tampering. Diagnostic files in storage/logs and server artifacts remain private. The loopback router omits query strings and redacts path details. The Nginx template logs only fixed categories/method/status/timing; protected error logs can still include request details and need narrow access/redaction before external collection.

Monitor framework `/up` separately from DB connectivity, authorization failures, credential abuse, expiry/duplicates, migration state, backup age/success, certificate expiry, disk space, clock drift and interrupted jobs. Named owners, escalation and approved alert thresholds are required; none are fabricated here. No external monitoring service was subscribed or given private logs.

For suspected compromise, notify the accountable operator, preserve evidence, use audited credential revocation or account/session invalidation, and follow the institution's incident/privacy procedure. Do not erase audits, expose raw references in tickets or silently retry uncertain passage/AI work. Verify clock synchronization/UTC storage and Manila display before operations. Host patching, perimeter, escrow and incident duties remain acceptance inputs.

## Retention decisions

Approve separate lifetimes for movement/denial/correction/anomaly/summary/audit records, sessions/reset links, photos, diagnostic logs, backups and research exports. No automatic business-record purge exists. Foreign keys and append-only controls deliberately block ad hoc deletion. DPO-approved anonymization/purge, holds and backup expiry need a reviewed procedure; diagnostic rotation is not that procedure. Name the patch owner and repeat relevant audits/restore/device checks after changes.
