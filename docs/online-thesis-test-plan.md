# Free online thesis test plan

Updated November 10, 2026. The user approved Supabase PostgreSQL after the actual TiDB Starter instance failed restricted column-update acceptance. The online database is initialized and software-tested. The website is not yet deployed.

Use [the beginner deployment guide](vercel-online-setup.md) for exact next steps and [the preparation report](cloud-preparation.md) for evidence and limits.

## Selected services

| Purpose | Service | Status |
| --- | --- | --- |
| Full Laravel/React application | Vercel Hobby Container Runtime | Docker configuration prepared; first build/host acceptance pending; Beta |
| Database, sessions, cache | Existing Supabase Free PostgreSQL | Version 17.11, private nfcheck_online schema, 28 tables and restricted runtime role initialized |
| Private portraits | Same Supabase Free project, private S3 Storage | Synthetic SDK and Laravel write/stream/delete checks passed |
| Possible hosting fallback | Render Free Docker service | Not deployed/accepted; evaluate if Vercel unavailable and no card required |

Stop at any payment card, billing or upgrade requirement. Free quotas/inactivity pausing mean this is a thesis evaluation, not an always-on institutional service. Supabase's Data API is unnecessary: PHP connects server-side to PostgreSQL.

## Completed acceptance

Local regression: 223 PHP tests / 2,397 assertions, 36 frontend tests, 11 actual MySQL races, build/typecheck/format and zero-advisory audits. Actual PostgreSQL: six tests / 40 assertions, four overlapping races and encrypted restore with 28 matched table fingerprints/source unchanged.

Initialization created only catalogues and four operators; no people/credentials/passages copied. Narrow runtime grants, verified TLS and anonymous schema denial were checked. Local MySQL data/accounts/coins remain separate.

TiDB Starter 8.5.3 was rejected: permitted column UPDATE failed. Do not use owner credentials or broad UPDATE as a workaround.

## Deployment order

1. Publish prepared source to the existing GitHub repository, excluding private files and unrelated phone-script edits.
2. Import into personal Vercel Hobby using Container preset and root Dockerfile.
3. Import private server variables into Production only; enable system variables. Isolate previews.
4. Deploy and inspect the first Linux build/runtime. No local Docker engine is available; container acceptance is pending.
5. Test stable HTTPS URL: startup, administrator password change, catalogue fields, private portrait uploads, gate permissions and account management.
6. On supported Android Chrome, allow NFC on the new HTTPS origin. Issue a separate writable online test coin, verify read-back and activate. Test automatic Entry/Exit, duplicate suppression, revocation and recovery.
7. Let a friend test authorized accounts. Confirm the URL works with this laptop switched off.
8. Resolve actual failures before marking hosted release accepted.

The revised automatic NFC flow has not received new physical acceptance. Earlier local manual-confirmation tests do not certify it. NFC credentials do not prove personal identity or an actual crossing. iOS Safari/Web NFC acceptance is not claimed.

## Configuration boundaries

- One full PHP application under HTTPS; Vite output alone cannot authenticate or record passages.
- Patched PHP, upload progress disabled, errors/debug off, non-root runtime and public-only root.
- Private external photos, encrypted database sessions and shared database cache.
- No migration/account initialization on web startup; separate guarded maintenance credentials.
- Online AI and mail recovery disabled. Existing approved synthetic Gemini labs remain separate.
- Encrypted private backups and separately retained key; provider dashboards alone are not recovery tests.

References: [Vercel PHP Container guide](https://vercel.com/kb/guide/deploy-php-on-vercel-with-docker), [Supabase connections](https://supabase.com/docs/guides/database/connecting-to-postgres), [Free limits](https://supabase.com/pricing).
