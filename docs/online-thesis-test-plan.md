# Free online thesis test plan

Reviewed November 10, 2026. This is a researched plan, not a completed deployment or a validated cloud database target. The user requires no payment card and no charges. The original localhost workspaces, accounts, histories and coins must be preserved.

## Proposed services

| Purpose | First candidate | Limit or acceptance condition |
| --- | --- | --- |
| Full Laravel/Inertia application | Vercel Hobby Container Runtime | Beta; use a Docker PHP HTTP server, not a static Vite deployment. Keep within free limits. |
| Application database | TiDB Cloud Starter, MySQL-compatible Free | Candidate only: verify this actual cloud instance supports NFCheck's SQL, constraints, column grants and transaction behavior before migrating application data. No paid consumption or card. |
| Private portraits | Dedicated Supabase Free project, private Storage bucket | Store files here, not in the container. Supabase's Postgres database is not proposed as NFCheck's database. |
| Host fallback | Render Free Docker web service | Same external database/storage requirements. Free service sleeps after inactivity; no persistent disk or free one-off jobs/shell. Do not proceed if signup requires a payment card. |

Use the supplied free HTTPS hostname. A paid custom domain is unnecessary. This is a small personal thesis evaluation, not an institutional always-on service. Free limits can pause services. Keep no payment methods attached; stop at any paid plan, trial-to-paid or card-verification requirement.

## Findings that affect this repository

- Vercel now documents a Container Runtime in Beta on all plans and a PHP/FrankenPHP Docker deployment route. Old advice that PHP cannot run on Vercel is incomplete. The archived `vercel-php` community runtime is not the preferred new deployment route.
- The application is Laravel 13 / PHP 8.4 with React and Inertia. Deploy it as one application under one HTTPS origin. Its frontend build alone cannot authenticate users or write passages.
- All existing migrations call `IsolatedDatabase::assertMigrationTarget()`, which verifies the private local MySQL 8.4.13 instance and loopback port 3307. The existing migration and account-initialization scripts are intentionally local-only. They are not cloud bootstrap commands.
- Person photo storage, existence checks and file responses explicitly select the local disk. Merely setting `FILESYSTEM_DISK=s3` does not fix this. The S3 adapter is not in the application's current Composer requirements. Photo reads must continue through authenticated Laravel routes, using streams from private storage instead of local filesystem paths.
- File cache is inappropriate for shared rate limits across ephemeral application instances. Use shared database cache with the required additive cache/cache-lock tables and grants, or an explicitly evaluated free shared cache service. Keep encrypted database sessions.
- Review trusted proxy handling and secure URL generation for the host's HTTPS termination. Runtime temporary files can be ephemeral; private photos, application records, session state, rate limits and backups cannot rely on the container filesystem.
- The deployment preflight currently requires private local files. Update it to validate the chosen private cloud-photo configuration, retaining local checks for local deployments.
- AI generation is excluded from the original online/production workspace under the existing policy. NFC does not require Gemini. Email recovery also needs a real verified mail delivery configuration; localhost SMTP and private workstation inboxes will not be available. Do not claim either feature works online merely because its page renders, or log password-reset links as a production mail substitute.

## Database acceptance comes first

TiDB is MySQL-compatible, not Oracle MySQL 8.4. The current tests establish correctness on the local Oracle MySQL target only. Before accepting a remote Starter instance, use a separate disposable cloud test database to verify:

1. TLS certificate verification and correct host/database/user identity.
2. Actual engine version and supported SQL: ENUM changes, binary digests, timestamp precision, reporting aggregates/window functions, all migration DDL and foreign keys.
3. Enforced CHECK constraints. TiDB's documentation says this feature is disabled by default; inspect and enable it appropriately before creating constraints, then prove invalid writes fail. Never delete a constraint to make deployment appear successful.
4. Separate maintenance and runtime users. Preserve append-only table privileges, restricted column updates and SELECT-only catalogues. Column-level privileges are documented from TiDB 8.5.6, but availability on the selected Starter instance must be proven.
5. Row locking, transaction retries, same-request idempotency, duplicate suppression and the scan-versus-revocation race using actual concurrent cloud connections.
6. Backup and restore into an isolated disposable target, with original sources untouched.

If a required capability is missing, stop this provider path. Do not replace database enforcement with an owner account in the web application or claim local test results certify the cloud target. A different provider/engine requires an explicit implementation and verification plan.

Aiven is not a new free MySQL option: its migration notice says new free provisioning stopped November 6, 2026. Clever Cloud's free DEV plan has different engine/version and user-management restrictions; it is not a verified drop-in for NFCheck's narrow runtime grants.

## Ordered deployment process

1. Keep localhost intact and create a deployment branch and private personal GitHub repository. Verify exclusions for `.env*` secrets, `.tools`, keys, passwords, SQL dumps, logs and private photos. Keep reviewed example configuration and lockfiles. No GitHub push or public resource creation has happened as part of this plan.
2. Create a TiDB Cloud **Starter / MySQL-compatible / Free** instance. Avoid PostgreSQL preview, temporary Zero instances and paid Essential/Premium plans. Leave consumption-based billing disabled. Save host, port, exact username, CA information and credentials privately. Create disposable validation and online-test databases with separate scopes; apply the acceptance checks above before copying real application records.
3. Create a Supabase **Free** project dedicated to test photos. Create a standard private bucket such as `nfcheck-photos`, with public access off. Generate S3 server credentials and record its endpoint/region privately. S3 keys bypass RLS and belong only in backend secrets. No Supabase Auth replacement or browser service key is needed.
4. Prepare and test the cloud release: a PHP 8.4 Docker image with required extensions, locked Composer dependencies, Node/Vite build, public-only HTTP document root, private temporary directories, stderr logging, private photo adapter, TLS database connection, shared cache/session configuration, approved cloud migrations/grants and a controlled account bootstrap. Build without embedding secrets. Do not run migrations or destructive seeding on every request or container startup.
5. In Vercel, create a new project from the personal private repository, select the Container preset/root Dockerfile, and stay on Hobby. Set secrets in the project environment settings. Use one stable test hostname and isolate preview deployment data from the shared test database. Configure production Laravel settings, secure cookies, no debug/preview/simulator/hardware flags and AI disabled. The Docker HTTP listener uses Vercel's expected port; only `public/` is served.
6. Bootstrap the approved cloud database from a controlled maintenance runner. Do not use a public migration URL. The current local scripts must first be extended with narrow cloud-target validation. Initialize the 17 programs/six departments and intentional test administrators/gate account. Use the restricted runtime database identity in the hosted app afterward. Preserve a fresh installation's generated APP_KEY across redeploys; a deliberately restored database must use the key appropriate to its encrypted contents.
7. Run hosted acceptance tests before inviting the friend: accounts/roles, records/catalogues, photo upload/read/delete permissions, credential issuance/read-back/activation, automatic Entry/Exit, duplicates, sequence warnings, lost/revoked/cancelled denial, recovery after a connection interruption, private downloads and data persistence after redeploy/cold start. Back up the remote test database and photo bucket and test restoration. `/up` alone does not verify these capabilities.
8. If Vercel's Beta runtime is unsuitable, create a Render **Web Service / Docker / Free** from the same prepared release. Bind to `0.0.0.0:$PORT`, keep the same approved external database and photo service, and set the new HTTPS APP_URL. Free Render filesystem writes are disposable; its free Postgres expires after 30 days and is not this MySQL application's drop-in database. Free Render has no interactive shell/one-off jobs, so keep migration/bootstrap outside the web service. Recheck secure cookies, proxy handling, callback URLs, cold starts and host-specific limits.

## Phone and friend testing

The phone should open the actual HTTPS test URL directly in Chrome, with NFC enabled and the phone unlocked. USB, ADB and the laptop are unnecessary once all dependencies are genuinely hosted. NFC permission is specific to the online origin and must be allowed there. A friend can view ordinary pages on other browsers, but coin scanning needs a compatible Android NFC phone and physical access to a coin.

The tags contain opaque NFCheck references, not localhost URLs. A fresh online database does not know hardware-workspace HW-TAG-08/HW-TAG-09. Preserve those coins. Use an unused blank test coin for the first online issuance, or deliberately migrate the complete matching credential/ownership/current-state/source data before claiming an existing coin will work. Do not copy only people or rewrite an active coin as a shortcut.

Use clearly labeled synthetic people initially. The administrator manages setup; the friend can use a test gate account for Entry/Exit testing. Keep passwords out of chat and source control. Test from mobile data as well as Wi-Fi, then turn off the laptop to prove there is no remaining localhost dependency. Compare recorded results and persistence, allowing for free-host cold starts and network latency. Do not promise identical performance or uninterrupted availability.

## Primary references

- [Vercel Container Runtime](https://vercel.com/docs/functions/runtimes/container), [PHP with Docker](https://vercel.com/kb/guide/deploy-php-on-vercel-with-docker), [Hobby plan](https://vercel.com/docs/plans/hobby).
- [TiDB plan/free quotas](https://docs.pingcap.com/tidbcloud/select-cluster-tier/), [cloud limitations](https://docs.pingcap.com/tidbcloud/serverless-limitations/), [CHECK constraints](https://docs.pingcap.com/tidb/stable/constraints/), [column privileges](https://docs.pingcap.com/tidb/stable/column-privilege-management/).
- [Supabase pricing](https://supabase.com/pricing), [S3 credentials](https://supabase.com/docs/guides/storage/s3/authentication).
- [Render free hosting](https://render.com/docs/free), [Aiven retirement](https://aiven.io/docs/products/mysql/howto/mysql-free-tier-migration-options), [Clever Cloud MySQL](https://www.clever.cloud/developers/doc/deploy/databases/mysql/).
- [Chrome Web NFC requirements](https://developer.chrome.com/docs/capabilities/nfc).
