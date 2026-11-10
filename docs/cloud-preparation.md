# Cloud preparation status

Updated November 10, 2026. The user selected Supabase PostgreSQL for the free online thesis version. Database initialization and private photo storage acceptance are complete. No GitHub push or public website deployment was performed. Follow [the detailed setup guide](vercel-online-setup.md) next.

## Accepted database and storage

- Supabase PostgreSQL **17.11**, certificate-verified Session pooler on port 5432, database postgres, private schema nfcheck_online.
- **28 tables / 20 migrations**, 17 programs, six departments and five case-insensitive identifier indexes.
- Administrators **alexi, pauleen, zaidy**, plus **gate1** linked to Gate 1. Four generated passwords were verified against stored hashes. Administrators must change their initial password.
- Zero online people, NFC credentials and passages. Local records, passwords, photos and coin assignments were not copied.
- Restricted application role with SELECT/INSERT-only history and column-specific UPDATE permissions; maintenance credentials are excluded from website configuration. Anonymous Supabase API roles cannot access the schema.
- Supabase private photo bucket passed synthetic SDK and actual Laravel JPEG write/stream/delete checks. Anonymous public access was refused; temporary objects were removed.
- The read-only scripts/check-cloud-services.php now checks PostgreSQL and S3. Actual TLS, runtime identity, permitted review-column update, denied immutable-history/fact updates and bucket access checks passed.

Private handoffs: .tools/secrets/cloud-operator-accounts.json and cloud-production.env (47 server variables). Never print or publish them. Retain cloud-runtime.json and its application key across redeployments.

## Implementation

Laravel/React/Inertia stays intact; local MySQL remains supported. PostgreSQL adaptations cover migration/check syntax, binary digests, report date/hour expressions, aggregates, case-insensitive searches, schema identity and locking. Native bound parameters use unnamed statements with emulation disabled. Immutable history reads retain restrictive grants.

Portraits stream through authorized backend routes from local or S3 storage. Database sessions and cache persist outside ephemeral containers. Online AI and email recovery are disabled. Initializer target guards refuse existing initialization; migrations and account creation never run during website startup.

Original MySQL schemas remain at **19 migrations / 26 tables**. Migration 20 adds shared cache tables online; it was not applied locally. Existing MySQL target/test guards remain in place.

## Verification

- Whole check: **223 PHP tests / 2,397 assertions**, **36 frontend tests**, **11 actual MySQL races**, build/typecheck/format and both dependency audits passed; audits reported zero advisories.
- Actual Supabase PostgreSQL acceptance: **six tests / 40 assertions**, **four overlapping races** covering different/same request IDs and both scan/revocation orderings.
- Disposable encrypted PostgreSQL restore matched **all 28 table fingerprints**, with source unchanged; owned probes/role/manifest/plaintext removed.
- Resumed final checks: **16 tests / 149 assertions**, PDF/export **four / 40**, local database foundation **14 / 45**, all passed.
- Permanent schema audit confirms five identifier indexes, catalogue/account counts, password-change requirements and anonymous schema denial.
- Initial permanent-source encrypted restore also matched all 28 tables, with source unchanged and owned restore/plaintext removed. An idle connection stalled final comparison; comparison resumed on a fresh connection without repeating the completed dump/restore. Connection/keepalive defaults and reconnect-after-restore were added; five portability/preflight tests / 23 assertions passed. Keepalive behavior depends on OS support; see [libpq parameters](https://www.postgresql.org/docs/17/libpq-connect.html).
- Current publishable-worktree scan checked 447 files against saved database/S3/application/account secrets: no matches. All private cloud files are Git-ignored. This does not audit every historical Git revision.

These software results do not establish hosted Linux container, public HTTPS or physical online NFC acceptance.

## Container and hosting

Pinned PHP 8.5 FrankenPHP Docker configuration, locked dependencies, frontend build, public-only document root and non-root runtime are prepared. Code is root-owned; storage/cache directories are writable. The build allowlist excludes private files. Startup validates production settings, materializes the database CA and resolves blank APP_URL from Vercel's production domain.

**No Docker engine exists on this workstation**, so the image has not been built or run under Linux. First Vercel deployment must validate it. Container Runtime is Beta; use Hobby only and stop at any card/upgrade requirement. Sydney is configured near the selected database. Do not deploy only the Vite frontend.

## Rejected TiDB path

The actual Starter instance reported 8.0.11-TiDB-v8.5.3-serverless. TLS and SELECT/INSERT worked, but the runtime account could not update an explicitly granted column (8121). That target was rejected. Owned users/schemas were removed and the temporary global CHECK setting restored OFF. TiDB is not used by the prepared website.

References: [TiDB kernels](https://docs.pingcap.com/releases/tidb-cloud/), [column privileges](https://docs.pingcap.com/tidb/stable/column-privilege-management/).

## Preserved state and next acceptance

Observed local counts remain main 6 users / 5 people / 2 gates / 2 credentials / 10 attempts / 5 passages; simulator 4 / 2 / 1 / 7 / 11 / 5; hardware 4 / 2 / 1 / 9 / 26 / 6. Fixtures were rolled back/restored. Existing HW-TAG-08/HW-TAG-09, local passwords and workers were retained. Previously stopped private MySQL was started for tests; shared XAMPP was unchanged. Unrelated phone-script edits remain separate.

Next: publish the prepared source when instructed, configure Vercel Production variables, validate container/HTTPS/database/photo behavior, then issue a separate online test coin and test automatic Entry/Exit. Existing local coins are not registered online. New physical acceptance of the revised automatic workflow remains pending.

No paid resource, billing change, live Gemini call, local business-data migration, public deployment or push was performed. Free quotas and inactivity pausing still apply.

References: [Supabase connections](https://supabase.com/docs/guides/database/connecting-to-postgres), [private S3 credentials](https://supabase.com/docs/guides/storage/s3/authentication), [Supabase Free limits](https://supabase.com/pricing), [Vercel PHP Container setup](https://vercel.com/kb/guide/deploy-php-on-vercel-with-docker).
