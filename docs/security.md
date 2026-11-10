# Security model

Server-side policies protect administrator, gate, credential, photo, report and correction actions. Gate accounts are restricted to their station. Authentication uses username/password, session version invalidation, CSRF protection, bounded rate limits and encrypted database sessions.

NFC references/nonces are not written into URLs, browser storage, application logs or Git. Private photos require authorized streaming routes. Uploads are validated/re-encoded. Reports are private/no-store; CSV formula prefixes and XLSX literals are handled deliberately, and PDF remote/active content is disabled.

The web database account is not a schema owner. Immutable facts use SELECT/INSERT-only grants with narrow terminal-review/task/counter updates. credential_counters allows only SELECT and UPDATE(last_number); the migration seeds the fixed classification rows.

Use the guarded local migration runner. Tests assert exact testing host, port, schema, account and engine version. Never replace these guards with broad credentials or disable server constraints to accommodate a host.

Hosted settings require HTTPS origin, debug/errors off, verified database TLS, shared cache, encrypted secure cookies and private object storage. Keep provider keys/backend credentials in server environment settings, not frontend variables.

Private local files, backup archives and environment settings are intentionally excluded from Git. Schema-only reference files do not grant database access. A database owner can still alter its data; application history is not an independent tamper-proof external ledger.

Optional Gemini use remains Free Tier only under the configured aggregate-data restrictions. Keep billing unlinked and do not add paid fallback models. Main workspace/provider separation must be preserved.
