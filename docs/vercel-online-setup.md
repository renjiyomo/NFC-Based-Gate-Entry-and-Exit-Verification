# NFCheck online setup: next steps

Prepared November 10, 2026. Supabase PostgreSQL and private photo storage are configured. The application code still needs to reach GitHub and pass its first Vercel container build. No public website has been deployed by this preparation.

## What is already done

- Online database: Supabase PostgreSQL 17.11, database `postgres`, private schema `nfcheck_online`.
- 28 tables, 20 migrations, 17 programs and six departments.
- Administrator usernames: `alexi`, `pauleen`, `zaidy`. All three must change their temporary password after first sign-in.
- Gate account: `gate1`, linked to Gate 1. Other gate accounts can be created through Gates & accounts.
- The database application account has restricted permissions; the website does not use the Supabase database owner account.
- Private photo bucket: `nfcheck-photos`. The Data API is not needed by this PHP application.
- The online workspace starts with zero students/employees, NFC credentials and passages. Local records and existing coins were retained separately.

## Your private files

Open these locally in your editor when needed. They are Git-ignored and excluded from the container build.

| File, relative to the project folder | Purpose |
| --- | --- |
| `.tools/secrets/cloud-production.env` | The server environment settings to import into Vercel |
| `.tools/secrets/cloud-operator-accounts.json` | Initial administrator/gate usernames and temporary passwords |
| `.tools/secrets/cloud-runtime.json` | Saved online application key and restricted database account; retain across redeployments |
| `.tools/secrets/cloud-services.json` | Private provider settings and maintenance connection |
| `.tools/secrets/backup-key.txt` | Key for private encrypted backup archives |

Do not upload these files to GitHub or share their contents/screenshots. Vercel's private Environment Variables settings are the destination for the production settings. An S3 key, database password and website account password are three different credentials.

## 1. Put the prepared source version on GitHub

Use the prepared local source commit. From PowerShell in the project folder:

```powershell
Set-Location -LiteralPath 'C:\xampp\htdocs\NFC-Based Gate Entry and Exit Verification'
.\scripts\dev.ps1 git push origin main
```

If Git asks you to sign in, use your own GitHub account. Once the push finishes, open the repository on GitHub and confirm the root contains `Dockerfile`, `vercel.json` and the `deploy` folder. A previous unrelated phone-connection script edit may remain uncommitted locally; it is not required for this hosted setup. Never force-push to resolve a rejected push; review the reported conflict first.

## 2. Create a new Vercel project

1. Open your personal Vercel dashboard and stay on the **Hobby** plan.
2. Click **Add New → Project**.
3. Import the NFCheck GitHub repository, with the updated `main` branch.
4. Choose a new project name, for example `nfcheck`.
5. Select **Container** as the framework preset. The root `Dockerfile` builds the full Laravel application.
6. Keep Root Directory at the repository root. Keep the Container preset's command/output defaults. Do not select `public/build` as a static Vite output directory.

Vercel's Container runtime is Beta. If Container is absent from the preset choices, stop at that screen and report the available options. Do not buy an upgrade or deploy only the React frontend. [Vercel PHP container instructions](https://vercel.com/kb/guide/deploy-php-on-vercel-with-docker).

## 3. Add the private production settings

1. Expand **Environment Variables** before deploying.
2. Use **Import .env** if offered, selecting the local `.tools/secrets/cloud-production.env` file. Otherwise add its key/value pairs in that private panel.
3. Apply these settings to **Production** only. Preview deployments must not reuse this database/account/key combination.
4. Enable access to **System Environment Variables** in the project settings. Depending on the dashboard layout, this is available under Settings → Environment Variables.
5. Keep all supplied security/storage/database values. The generated file already contains the restricted database account, application encryption key, database CA certificate and S3 settings. Do not replace its DB password with the Supabase owner password.

`APP_URL` is initially empty. On a production Vercel deployment, startup derives `https://` plus the project's production `*.vercel.app` domain from `VERCEL_PROJECT_PRODUCTION_URL`. This avoids guessing the hostname. For a custom domain or another hosting provider, set APP_URL explicitly to the stable HTTPS origin. [Vercel system variables](https://vercel.com/docs/environment-variables/system-environment-variables).

The repository sets one Function region, Sydney (`syd1`), close to the selected Supabase database. Keep this setting when using this database. [Vercel Function regions](https://vercel.com/docs/functions/configuring-functions/region).

## 4. Deploy and inspect the result

1. Click **Deploy**.
2. Wait for the Docker build. The frontend and PHP dependencies are built together.
3. Inspect build/runtime logs if deployment fails. Share the error text with credentials removed; never send the private environment file.
4. Once ready, open the project's stable **production domain**, rather than a temporary preview/commit URL.
5. Open `/up` on that domain. A successful response shows framework startup; it does not certify database, photo or NFC behavior.
6. Open `/login` and sign in using an administrator entry in the private account file. Complete the mandatory password change and keep the new password privately.

The workstation has no Docker engine, so this is the first Linux container build/host check. Treat it as part of acceptance, not a formality. Migrations and account initialization do not run on every website start or redeployment.

## 5. Check the hosted application before friend testing

1. Confirm the program and department dropdowns appear.
2. Add a clearly labelled synthetic student and employee. Upload a test portrait and confirm it can be viewed after signing out/in.
3. Check Gates & accounts. Gate 1 and `gate1` should already exist.
4. Sign in as `gate1` and verify it can use the scanner but cannot open administrator pages.
5. From Supabase's Table Editor, select schema `nfcheck_online` to inspect application tables if needed. The database is named `postgres`; `nfcheck_online` is its isolated application schema. Leave the Data API disabled.

## 6. Test a coin online

1. On a compatible Android NFC phone, open the stable HTTPS production URL in Chrome. Turn NFC on and keep Chrome visible and the phone unlocked.
2. Sign in as an administrator. In NFC setup, run the access check and allow NFC for this new website origin.
3. Use an unused blank test coin. Existing active localhost/hardware coins must stay separate. Their references are not registered in this fresh online database; issuance refuses unknown NFCheck references rather than overwriting them.
4. Issue a credential to the synthetic person: check the blank coin, prepare, write that same coin, read back and activate after a match.
5. Sign in as `gate1`. Select Entry, start scanning and present that coin. A valid scan records automatically. Repeat with Exit.
6. Verify matching history rows and dashboard totals. Check duplicate suppression, a cancelled/revoked test credential and connection-error recovery before inviting your friend.
7. Test using mobile data and then with the laptop turned off. A hosted setup must not depend on localhost or USB forwarding.

Your friend needs a compatible NFC Android phone and physical access to a coin to test scanning. USB/ADB is unnecessary for the hosted HTTPS site. Free hosting can have cold starts and quotas; online timing must be measured rather than assumed identical to localhost. Preserve the saved application key and restricted DB credentials across redeploys.
