# Local development — Phase 1

The application provides local UI previews. Operational authentication, database workflows and NFC actions begin only in their approved later phases. Follow [AGENTS.md](../AGENTS.md) and the phase roadmap.

## Current workstation

Isolated tools, dependencies, a generated local application key and built assets are prepared. A temporary background preview is running at [127.0.0.1:8000](http://127.0.0.1:8000/preview/dashboard). Its process record/logs are under ignored `.tools`. Use that existing preview without starting a duplicate server. Future foreground sessions started below are stopped with Ctrl+C.

If no preview is running:

```powershell
.\scripts\dev.ps1 serve
```

The server binds only to **127.0.0.1:8000**, without port fallback. Phone-accessible trusted HTTPS belongs to later NFC integration. No public hosting was configured.

## Fresh checkout

Use Windows x64 and PowerShell 7.4+. Run commands from the project root. Before creating secrets, verify root `.htaccess` remains in place and the old Apache project URL is denied. Never serve the project root.

```powershell
# Verify pinned downloads and prepare isolated PHP/Node/Git/patched Composer.
pwsh -File .\scripts\bootstrap-tools.ps1

# Install committed dependency locks. No migration scripts are configured.
.\scripts\dev.ps1 composer install --no-interaction --prefer-dist
.\scripts\dev.ps1 npm ci

# Only for a new local environment; preserve any existing .env.
if (-not (Test-Path -LiteralPath .env)) { Copy-Item -LiteralPath .env.example -Destination .env }
.\.tools\php\php.exe artisan key:generate
```

Edit local `.env` to set `NFCHECK_UI_PREVIEW=true` with `APP_ENV=local`. The example defaults previews off. Keep file sessions/cache and synchronous queues for Phase 1. Database settings are reserved Phase 2 placeholders, with no operational username/password. Do not run migrate/seed/reset commands.

```powershell
.\scripts\dev.ps1 build
.\scripts\dev.ps1 check
.\scripts\dev.ps1 serve
```

Key generation writes the ignored environment file. Never paste/commit its key. Do not regenerate an existing application's key as a routine startup action: that can invalidate encrypted data and sessions.

## Hot reload

Start Laravel in one terminal and Vite in a second:

```powershell
.\scripts\dev.ps1 serve
# Second terminal, same project:
.\scripts\dev.ps1 vite
```

Vite is loopback-only on 5173 with the intended 8000 origin. Stop both with Ctrl+C. If a generated `public/hot` remains after Vite stops, remove that stale file and rebuild for the static preview. It is ignored by Git. Reload after a production build; static assets do not hot reload.

## Individual checks

| Command | Purpose |
| --- | --- |
| `pwsh -File .\scripts\bootstrap-tools.ps1 -VerifyOnly` | Verify existing tools/config/build provenance and audit without rebuilding |
| `.\scripts\dev.ps1 composer validate --strict` | Validate Composer manifest/lock |
| `.\scripts\dev.ps1 composer audit` | Audit app PHP dependencies |
| `.\scripts\dev.ps1 npm audit` | Audit frontend dependencies |
| `.\scripts\dev.ps1 typecheck` | Strict TS7 app/library type checking |
| `.\scripts\dev.ps1 build` | Typecheck and production build |
| `.\scripts\dev.ps1 format --test` | PHP formatting check; omit `--test` to format |
| `.\scripts\dev.ps1 npm run format:check` | Frontend formatting check |
| `.\scripts\dev.ps1 npm run format` | Format frontend code |
| `.\scripts\dev.ps1 test` | Database-independent feature tests |
| `.\scripts\dev.ps1 check` | Full validation/audits/format/build/test pipeline |
| `.\scripts\dev.ps1 git status` | Use patched local Git |

The launcher prepends local paths and uses an isolated Composer home for its process, then restores the environment. No system PATH/global runtime is changed. Provenance and security-bootstrap limitations are in [toolchain.md](toolchain.md).

## Routes and structure

`/login` and `/preview/{page}` are local-only previews; `/` redirects to the login shell. There are no login POST or operational mutation handlers. Page allowlist: dashboard, students, employees, credentials, guards, gates, scanner, history, reports, analytics and settings. Unknown pages return 404. `/up` is a lightweight health route.

Laravel owns the controller, preview gate, headers and Inertia props. React pages/components are under `resources/js`, tokens in `resources/css/app.css`, and Blade in `resources/views/app.blade.php`. Inertia SSR and DevTools recording are disabled; no extra Node server is required.

## Troubleshooting and limits

- **Old XAMPP URL returns 403:** expected; use port 8000. Keep root `.htaccess`.
- **Preview returns 404:** check environment, explicit preview flag and loopback access. Production rejection is intentional. Restart after environment changes; clear a locally generated configuration cache if necessary.
- **Bare `php` reports cURL failure:** that is shared XAMPP PHP; use the launcher or full isolated PHP path.
- **Port 8000 occupied:** use the existing preview, or stop its foreground terminal with Ctrl+C if you started it there. The launcher intentionally refuses a port fallback. Never kill all PHP processes.
- **Disabled login/scanning or no data:** expected Phase 1 shells; no operational access is granted.
- **Checksum/audit fails:** review/update pinned inputs from official sources. Do not bypass checks or TLS.

Shared Apache/PHP/MariaDB/phpMyAdmin remain unchanged with the Phase 0 findings. Full browser zoom, assistive technology certification, physical phones/tags, trusted phone HTTPS and production operations require later validation. Emulated responsive widths are not hardware tests.
