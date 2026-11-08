# Phase 0 environment and compatibility audit

Audit date: **7 November 2026, Asia/Manila**. All recommendations below are observations at this checkpoint and must be rechecked before installing packages. Audit operations were read-only except creation of Phase 0 documentation. No global updates, application scaffolding, database creation, or configuration edits were performed.

## Workspace and host

- Project root: `C:\xampp\htdocs\NFC-Based Gate Entry and Exit Verification`.
- The folder was not empty: `nfcheck-logo.png`, `bup logo no bg.png`, and `Manuscript old title-study/Chapter 1-3 Manuscript_Checked.docx` were present. They are preserved; no institutional dataset was imported or manuscript text processed.
- No source code, manifests, migrations, or Git repository existed. No applicable `AGENTS.md` was found in the root or checked ancestors.
- Windows 11 Home Single Language, version `10.0.26300`, x64. This identifies the host; the full update revision and Windows Update compliance were not established. Compiler runtime availability needs validation with the selected Windows PHP package.

## Installed software and recommendation

| Component | Installed / observed | Proposed target | Compatibility / security assessment |
| --- | --- | --- | --- |
| Terminal PHP | **8.4.16**, x64 ZTS, Visual C++ 2022; `C:\xampp\php\php.exe` | PHP **8.5.12-1** Windows rebuild, or newer patched stable 8.5 available at installation | PHP version fits Laravel 13's range, but installed patch predates critical fixes; cURL is broken. Do not initialize using this installation. |
| Apache PHP | **8.4.16**, Apache 2 Handler, thread safety enabled | Same patched PHP source version, matching TS Apache module and extension set if Apache is used | Independently verified over HTTP; not merely inferred from CLI. Shared XAMPP is unchanged. |
| cURL extension | DLL file version **8.2.12**, API `20220829`; runtime API `20240924` | Extension and dependent DLLs from the complete selected PHP distribution | Fails to initialize in CLI; no cURL support section in Apache PHP output. Mixing PHP packages is a confirmed blocker. |
| Composer | **2.8.11** | **2.11.1** or newer current stable Composer 2 | Diagnose exits 1; reports 11 advisories affecting 3 bundled packages, including high severity. Update before dependency installation. |
| Node.js | **22.18.0**, `C:\Program Files\nodejs\node.exe` | **24.22.2 LTS**, or latest patched 24 LTS | Existing version meets Vite/plugin's published Node floor but is old. Latest LTS observed is 26.13.0; mature 24 LTS is a deliberate choice. |
| npm | **10.9.3** | Bundled **11.19.1** with selected Node 24.22.2 | Latest standalone observed is 12.2.2; do not upgrade npm independently on the old Node without checking engines. |
| Database server | Running **10.4.32-MariaDB**, `mariadb.org binary distribution`, port **3306** | Separate **Oracle MySQL 8.4.13 LTS** or newer supported 8.4 patch | MariaDB is a separate product. 10.4 maintenance ended 18 June 2024. Do not label or use this server as the required MySQL baseline. |
| Database client | `C:\xampp\mysql\bin\mysql.exe`, Distrib 10.4.32-MariaDB | Client matching chosen MySQL server | `mysql` is absent from PATH; verified using full path. Client version alone was not used to identify the server. |
| Apache | **2.4.58**, Win64, VS17; `C:\xampp\apache\bin\httpd.exe` | Current stable **2.4.70**, released 6 November 2026, with compatible Windows vendor build | Syntax passes, but release is substantially outdated. Module/configuration determines individual CVE exposure; a syntax pass is not security clearance. |
| Git | **2.51.0.windows.2** | **2.56.0.windows.4** or newer patched Git for Windows | Current vendor release includes a Brotli security update; update before repository/dependency work. |
| phpMyAdmin | **5.2.1**, checked from installed version files | **5.2.3** current stable, if retained | Administration tool only, not the database server. Restrict access; do not use 6.0 snapshots. |
| Laravel / React stack | Not installed | Matrix below | Published compatibility only; no local resolve/build result yet. |

Version evidence: [PHP Windows downloads](https://www.php.net/downloads.php?os=windows), [Composer](https://getcomposer.org/download/), [Node 24 archive](https://nodejs.org/en/download/archive/v24), [Node downloads](https://nodejs.org/en/download), [npm 12.2.2](https://github.com/npm/cli/releases/tag/v12.2.2), [MySQL 8.4 Windows](https://dev.mysql.com/downloads/mysql/8.4.html), [MySQL release notes](https://dev.mysql.com/doc/relnotes/mysql/8.4/en/), [MariaDB maintenance](https://mariadb.org/about/), [Apache downloads](https://httpd.apache.org/download.cgi), [Apache security](https://httpd.apache.org/security/vulnerabilities_24.html), [Git for Windows release](https://github.com/git-for-windows/git/releases/tag/v2.56.0.windows.4), [phpMyAdmin downloads](https://www.phpmyadmin.net/downloads/).

Installed phpMyAdmin 5.2.1 also predates the 5.2.2 fixes for moderate XSS issues [CVE-2025-24529](https://www.phpmyadmin.net/security/PMASA-2025-2/) and [CVE-2025-24530](https://www.phpmyadmin.net/security/PMASA-2025-1/). Update the tool if retained; do not infer applicability of unrelated Linux/glibc advisories to this Windows host.

## PHP security findings

The brief's two critical issues are confirmed in official PHP advisories: [CVE-2026-109618](https://github.com/php/php-src/security/advisories/GHSA-w8hw-w9fx-j79w) and [CVE-2026-109619](https://github.com/php/php-src/security/advisories/GHSA-6wmf-4rv5-p3rp). Their patched source baselines are 8.2.35, 8.3.36, 8.4.27, and 8.5.12. Installed 8.4.16 is below its branch's baseline.

PHP's 26 October notice adds Windows `-1` package rebuilds containing Brotli 1.2.1 for CVE-2026-111497. `php -v` does not expose the package rebuild suffix; verify the downloaded artifact, checksum, and provenance. The 23 October notice also describes a conditional remaining session upload-progress vector (CVE-2026-115560) and recommends `session.upload_progress.enabled=0` pending a regular bugfix release. Existing CLI and Apache both have this feature enabled. The recommendation is documented, **not applied**. Recheck whether a subsequent stable release fixes it before implementing the runtime plan. [PHP 2026 security notices](https://www.php.net/archive/2026.php)

Use a stable supported PHP release, not PHP 8.6 RC. PHP 8.5 has active support through 2027 and security support through 2029. PHP 8.2 is approaching security EOL in December 2026. [PHP support table](https://www.php.net/supported-versions.php)

## Composer diagnostic findings

`composer diagnose` reached Packagist over HTTP/HTTPS, verified signing keys, and passed platform/Git/disk checks. It reported missing cURL and a streams fallback. Its own vulnerability check found **11 advisories across composer/composer, symfony/filesystem, and symfony/process**. These are tool dependencies, not a nonexistent NFCheck lockfile.

High findings included command execution through malicious Perforce metadata (CVE-2026-84361, CVE-2026-40261, CVE-2026-40176), arbitrary file write (CVE-2026-59948), and a GitHub Actions token disclosure advisory (CVE-2026-45793). Applicability depends on workflow; their presence still precludes accepting this Composer as the baseline. [Composer command execution advisory](https://github.com/composer/composer/security/advisories/GHSA-wg36-wvj6-r67p), [file-write advisory](https://github.com/advisories/GHSA-499r-g7pc-vmp9)

Run a fresh diagnostic after updating. Project-level `composer audit` and `npm audit` are **not applicable yet**: there is no dependency manifest or lockfile. They must run after Phase 1 installation; no clean project audit is claimed.

## Proposed coherent package matrix

| Package | Observed stable release / intended line | Published compatibility and Phase 1 gate |
| --- | --- | --- |
| Laravel framework | **13.40.0 / 13.x** | PHP 8.3–8.5. Official security support through 17 March 2028. |
| React / React DOM | **19.3.1 / 19.x**, matched | Satisfies current Inertia React peer declarations; verify selected React type packages. |
| Inertia | React client **3.11.0 / 3.x**, corresponding Laravel adapter **3.x** | Adapter declares Laravel 13 support. Keep compatible client/core/adapter releases. |
| TypeScript | **7.0.2** | Current stable; explicit typecheck and frontend tooling compatibility gate. Older starter declarations do not prove TS7 compatibility or incompatibility. |
| Tailwind CSS | **4.3.5 / 4.x** | Use its supported Vite integration; verify target browser minimums against actual Android devices. |
| Vite | **8.3.6 / 8.x** | Ordinary Vite; no separate Node backend. |
| laravel-vite-plugin | **3.2.1 / 3.x** | Declares Vite `^8.0.0`, Node `^20.19.0 || >=22.12.0`; selected Node 24 satisfies this. Inspect all peers during resolution. |

Primary evidence: [Laravel 13 release](https://github.com/laravel/framework/releases/tag/v13.40.0), [Laravel support](https://laravel.com/framework/docs/13.x/releases), [Laravel runtime requirements](https://laravel.com/framework/docs/13.x/deployment), [React release](https://github.com/react/react/releases/tag/v19.3.1), [TypeScript 7 announcement](https://devblogs.microsoft.com/typescript/announcing-typescript-7-0/), [Tailwind releases](https://github.com/tailwindlabs/tailwindcss/releases), [Tailwind browser requirements](https://tailwindcss.com/docs/compatibility), [Inertia releases](https://github.com/inertiajs/inertia/releases), [Inertia React manifest](https://raw.githubusercontent.com/inertiajs/inertia/v3.11.0/packages/react/package.json), [Laravel adapter manifest](https://raw.githubusercontent.com/inertiajs/inertia-laravel/3.x/composer.json), [Vite releases](https://github.com/vitejs/vite/releases), [Laravel Vite plugin manifest](https://raw.githubusercontent.com/laravel/vite-plugin/3.x/package.json).

Local **read-only** `npm view` checks also returned React 19.3.1, TypeScript 7.0.2, Inertia React 3.11.0, Vite 8.3.6, Tailwind 4.3.5, and laravel-vite-plugin 3.2.1. No packages were installed.

Current Laravel React starter tooling includes a Vite Plus alias. Prefer deliberate plain Vite configuration to avoid extra infrastructure. If TS7 fails a necessary integration check, evaluate and document a supported TS6 alternative using actual failures; do not suppress checks or use unsafe dependency flags. [Starter manifest](https://raw.githubusercontent.com/laravel/react-starter-kit/main/package.json)

## PHP extensions and Apache configuration

Loaded CLI extensions include bcmath, bz2, calendar, ctype, date, dom, exif, fileinfo, filter, ftp, gd, gettext, hash, iconv, intl, json, libxml, mbstring, mysqli, mysqlnd, openssl, pcre, PDO, pdo_mysql, pdo_sqlite, Phar, random, readline, Reflection, session, SimpleXML, SPL, standard, tokenizer, xml, xmlreader, xmlwriter, zip, and zlib. **cURL is missing**; Laravel lists it as required. GD is available for the proposed photo processing. CLI OPcache is not loaded; assess production configuration later.

Both CLI and Apache load `C:\xampp\php\php.ini`, with extension directory `C:\xampp\php\ext`. Apache loads `php8ts.dll` and `php8apache2_4.dll` from the same PHP directory; both DLL file versions are 8.4.16. This confirms the mismatched cURL DLL is 8.2.12 within an 8.4.16 installation.

`httpd -t` returned **Syntax OK**. `httpd -M` confirmed rewrite, headers, ssl, and php modules. `httpd -S` identifies the shared document root as `C:/xampp/htdocs` and an SSL vhost named `www.example.com`. A listening 443 port does not establish trusted HTTPS or phone compatibility; certificate trust was not tested.

Observed PHP defaults include `display_errors=On`, `expose_php=On`, `session.use_strict_mode=0`, empty `session.cookie_httponly`, and `date.timezone=Europe/Berlin`. Laravel session settings and production configuration must explicitly establish secure cookies, intended timezone behavior, and safe error handling. Database session timezone reported `SYSTEM`; neither timezone setting is appropriate evidence of Manila report boundaries.

Apache listens on wildcard addresses at 80/443; MariaDB listens at 3306 on `::`. Firewall policy and remote reachability were not audited. A local version query succeeded with root specified and no password supplied in the command; this does not establish whether client defaults provided credentials. NFCheck must use its own restricted database account.

## Safe remediation sequence — planned, not executed

1. After Phase 1 approval, select fresh official stable packages and verify signatures/checksums. Prefer isolated PHP, Composer, Node, and Git tooling rather than replacing DLLs inside shared XAMPP. Preserve existing projects and configuration.
2. Use the complete matching PHP package. For CLI-only local development, NTS is suitable; an Apache module requires matching x64 TS and a compatible Apache build/compiler runtime. Never copy only an extension DLL from another PHP version.
3. Apply the current PHP advisory's upload-progress setting to the isolated runtime if still required. Recheck `php -v`, `--ini`, `-m`, cURL HTTPS connectivity, and `composer diagnose`; no startup warnings or known high/critical findings may be ignored.
4. Update frontend tooling to selected Node LTS with its bundled npm. Recheck engines and package peers, install without bypass flags, commit lockfiles, then typecheck/build and run both dependency audits.
5. Before storing secrets under this workspace, prevent access through the original shared `htdocs` project route, and serve only Laravel `public` using a dedicated local web root or patched loopback development server. A new vhost or CLI server **alone does not block the original route**. The CLI-only alternative requires a scoped Apache access restriction, or stopping shared Apache with consideration for other projects. Plan and review the specific configuration change before applying it; do not disrupt unrelated sites. A CLI upgrade does not patch Apache's PHP.
6. In approved Phase 2, provision a **separate** MySQL 8.4 LTS instance, preferably loopback port 3307 to avoid existing MariaDB 3306. Create a new `nfcheck` schema and least-privilege account; do not reuse root or repoint/overwrite the XAMPP data directory. phpMyAdmin may later receive a separate connection profile. No existing database needs destruction.
7. Before phone trials, establish trusted HTTPS for the phone-accessible host with an appropriate certificate and controlled network access. A phone's `localhost` is the phone itself; HTTP to a PC's LAN IP is not the required secure deployment.

## Commands actually performed

```text
Get-ChildItem -Force; rg --files
Get-Command php,composer,node,npm,mysql,git,httpd -ErrorAction SilentlyContinue
where.exe php
php -v
php --ini
php -m
composer --version
composer diagnose
node -v
npm -v
mysql --version                             # command not on PATH
C:\xampp\mysql\bin\mysql.exe --version     # full-path check succeeded
mysql.exe ... --execute="SELECT VERSION(), @@version_comment, @@port, @@session.time_zone;"
git --version
git rev-parse --show-toplevel               # no repository
httpd.exe -v / -t / -M / -S                 # separate invocations
Invoke-WebRequest http://127.0.0.1/dashboard/phpinfo.php
Get-NetTCPConnection -State Listen          # filtered to relevant service ports
npm view <selected package> version
npm view laravel-vite-plugin version engines peerDependencies --json
```

The HTTP diagnostic used an existing XAMPP endpoint; no public diagnostic file was created and no complete phpinfo dump was saved. Only selected version/settings values were inspected. No automated application or hardware tests exist yet. Remaining uncertainties: resolved dependency graph, trusted phone HTTPS, device/tag compatibility, institutional access/retention policy, Windows patch posture, and production deployment environment.
