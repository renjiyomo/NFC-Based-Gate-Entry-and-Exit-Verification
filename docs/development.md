# Local setup

## Existing installation

Open a terminal in the project folder and run `npm run dev`. The launcher uses Windows' built-in PowerShell and the project PHP runtime, starts/reuses database/mail services, and serves http://127.0.0.1:8000/login. The terminal remains occupied while serving. Ctrl+C stops the foreground website.

Use `npm run dev:frontend` in a second terminal for optional hot reload. If global npm/Node is unavailable, use `scripts/dev.ps1 npm run dev`. Preserve .env, .tools/secrets and .tools/mysql-data between runs.

## A groupmate's new Windows x64 computer

Downloading GitHub provides source code, not your database or passwords. The following creates a new independent local database. It does not connect to the original laptop's records.

1. Download/clone the updated repository and extract it into a writable local folder. Open a terminal in that folder.
2. Install PowerShell 7.4 or later for the initial bootstrap scripts. Routine npm startup also works with built-in Windows PowerShell.

Use Microsoft's [PowerShell installation guide](https://learn.microsoft.com/en-us/powershell/scripting/install/installing-powershell-on-windows). PHP's Windows build also requires the matching Visual C++ runtime; install it if PHP reports a missing runtime DLL, following [PHP's Windows requirements](https://www.php.net/manual/en/install.windows.manual.php). No hosting account, API key or payment card is needed for local setup.
3. Run these commands one at a time. The initial downloads need internet and can take several minutes.

The one-command option for a fresh clone is `pwsh -NoProfile -File .\scripts\setup-local.ps1`. It performs the steps below, creates username admin, then stops. Start afterward with `.\scripts\dev.ps1 npm run dev`; this uses the downloaded private Node/npm even if the computer has no global Node installation. It refuses an existing .env/database handoff; use the normal startup on an existing installation. If interrupted, resolve the failed step and continue the individual commands without replacing existing keys or databases.

```powershell
pwsh -NoProfile -File .\scripts\bootstrap-tools.ps1
.\scripts\dev.ps1 composer install --no-interaction --prefer-dist
.\scripts\dev.ps1 npm ci
if (-not (Test-Path -LiteralPath .env)) {
    Copy-Item -LiteralPath .env.example -Destination .env
    .\scripts\dev.ps1 artisan key:generate
}
pwsh -NoProfile -File .\scripts\bootstrap-services.ps1
.\scripts\dev.ps1 migrate
.\scripts\dev.ps1 artisan nfcheck:provision-user admin@nfcheck.test --name=Administrator --role=admin --generate
.\scripts\dev.ps1 build
.\scripts\dev.ps1 npm run dev
```

4. Open http://127.0.0.1:8000/login. The initial username is `admin`; retrieve the generated password privately from .tools/secrets/initial-accounts.json. Complete the first-login password change.
5. Create gates/accounts and synthetic test people through the application. Do not reuse another computer's private keys, passwords or database directory.

PowerShell must finish each setup command successfully before proceeding. If a download/hash check or command fails, retain the error and resolve it; do not disable version, schema, TLS or signature checks. Existing schemas must never be reset with migrate:fresh/db:wipe.

The supported private Windows toolchain is separate from shared XAMPP. The initial isolated service provisioner creates its own MySQL users/passwords and writes local environment settings. XAMPP Apache/MySQL need not run for the application. Verify no unrelated program already occupies 3307 or 8000.

## Database changes

Add a new forward migration under database/migrations, review it, then use `scripts/dev.ps1 migrate` on the recognized local workspaces. Existing data must remain intact. A SQL snapshot is reference material; importing it and then running all migrations would attempt to recreate the same tables.

## Checks

```powershell
.\scripts\dev.ps1 test
.\scripts\dev.ps1 concurrency
.\scripts\dev.ps1 npm run test:nfc
.\scripts\dev.ps1 build
.\scripts\dev.ps1 format --test
.\scripts\dev.ps1 npm run format:check
```

Run database tests and concurrency tests separately. Tests assert the dedicated testing database identity and restore scoped fixtures. See [database connections](database-setup.md) and [testing](testing-and-research.md).
