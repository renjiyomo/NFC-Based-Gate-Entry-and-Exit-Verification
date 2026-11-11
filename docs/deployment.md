# Deployment

Deploy the full Laravel application, not just public/build. The repository contains a PHP/FrankenPHP Docker build, frontend build stage, public-only document root and production startup checks. Private toolchains, environments, photos, backups and development notes are excluded from the image.

The prepared free path uses Vercel Hobby Container Runtime with Supabase Free PostgreSQL and a private S3-compatible photo bucket. Keep a no-card/no-charge account policy; stop at any paid requirement. Free quotas, cold starts and inactivity pausing still apply. See [the Vercel setup guide](vercel-online-setup.md).

Local MySQL and online PostgreSQL are independent. New migrations and scoped grants must be applied to the explicitly approved target before deploying code that uses them. Latest local code needs migration 22, including credential_counters; a previously initialized online schema at migration 20 is not updated merely by pushing GitHub. Never run local bootstrap/reset scripts against the cloud owner account.

Use the restricted runtime role in the website; keep maintenance credentials separate. Retain the application key across redeployments. Configure private S3 portraits, database sessions/cache and trusted proxy headers. Migrations/account initialization do not run on every web startup.

New scans run the single-gate activity checks inside their existing transaction. Administrators can use Activity checks → Check earlier records to recheck historical attempts and overdue Missing Exit records. Unattended Missing Exit processing requires an actual scheduler runner. For local evaluation an operator may run `scripts/dev.ps1 artisan schedule:work`. The hosted endpoint /internal/missing-exits requires a private CRON_SECRET bearer token; scheduler provisioning remains an explicit hosting step.

Optional Gemini summaries use anonymous aggregates with explicit workspace approval. Set AI_SUMMARIES_ENABLED, AI_SUMMARIES_ALLOW_AGGREGATES, AI_SUMMARIES_FREE_TIER_CONFIRMED and AI_SUMMARIES_INLINE to true, and supply AI_SUMMARIES_API_KEY and AI_SUMMARIES_PROJECT_ID privately. Keep the Google project on Free Tier without billing. Generation completes from the administrator request, with no background worker required online. Missing or refused provider access leaves gate scanning available. See [activity checks and summaries](activity-and-summaries.md).

Validate container startup, stable HTTPS origin, login/password change, private portraits, catalogue fields, gate permissions, NFC issuance/read-back, automatic scans, duplicates and request recovery. A successful build or /up response does not establish full application acceptance. No iOS NFC support is implied by hosting the site.
