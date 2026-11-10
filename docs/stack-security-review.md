# Stack and security version review

Checked November 10, 2026. The initial inventory/advisory review was read-only. Subsequent authorized cloud preparation added the S3 adapter and PostgreSQL support, and initialized the online schema. See [cloud preparation](cloud-preparation.md) for those operations and tests. This is not public deployment approval or a complete penetration test.

## Runtime distinction

`scripts/dev.ps1` explicitly selects the project's private `.tools/php/php.exe`, not `C:/xampp/php/php.exe`. The private executable reports **PHP 8.5.12**. Composer's `php: ^8.4` is a supported-version constraint, not evidence that NFCheck runs PHP 8.4. A running private PHP process was observed; this review does not establish availability of every workspace server.

The separate shared XAMPP executable reports **PHP 8.4.16** and fails to load cURL: its extension API is 20220829 while PHP expects 20240924. Apache's installed executable reports **2.4.58**; its XAMPP module configuration points to `C:/xampp/php/php8apache2_4.dll`. These shared components were left unchanged. Do not expose this shared installation for the online test; use an independently prepared patched server image.

## Security findings

- PHP's October 21 advisory fixes CVE-2026-109618 and CVE-2026-109619 in 8.5.12 / 8.4.27. Private PHP meets that floor; shared 8.4.16 does not. The October 23 follow-up recommends disabling session upload progress for CVE-2026-115560 pending the regular fix. Private `php.ini` already has `session.upload_progress.enabled=0`; its default serializer is `php`, with no application serializer-switch/session-start matches found. Preserve this setting in deployment. [PHP advisories](https://www.php.net/archive/2026.php).
- Node 24.22.2 is newer than the October security release's 24.22.1 fixed version. [Node security release](https://nodejs.org/en/blog/vulnerability/october-2026-security-releases).
- Laravel 13.40.0 meets the patched floor for the November 4 Task Output Injection advisory. [Laravel advisory](https://github.com/laravel/framework/security/advisories/GHSA-63hm-g96r-2m2q).
- MySQL's installed binary reports 8.4.13, matching the October 20 LTS release. This review did not query a live database server. [Oracle release notes](https://dev.mysql.com/doc/relnotes/mysql/8.4/en/news-8-4-13.html).
- Apache 2.4.58 precedes subsequent security fixes; exploitability of specific module-dependent issues was not tested. [Apache security history](https://httpd.apache.org/security/vulnerabilities_24.html).

Fresh Composer audit: **0 advisories, 0 abandoned packages**. Fresh npm audit: **0 vulnerabilities**, including high/critical. `composer check-platform-reqs --no-dev` passed for the installed private PHP runtime. These results cover reported dependency advisories, not all configuration or hosting risks. Full application tests were not rerun for this inventory-only task.

The private development PHP configuration has `display_errors=On`. A cloud runtime must explicitly set it off, keep Laravel `APP_DEBUG=false`, and recheck all advisories when building. The local PHP development server is not the proposed public server.

## Installed application stack

Versions below come from installed packages or executable output, rather than dependency ranges.

| Component | Installed version / implementation | Purpose |
| --- | --- | --- |
| PHP | 8.5.12, NTS x64; Zend/OPcache 8.5.12 | Backend runtime |
| Laravel | 13.40.0 | Routing, authorization, validation, database and application services |
| Oracle MySQL Community | 8.4.13 | Relational database; private target is loopback port 3307 |
| Supabase PostgreSQL | 17.11 | Selected online database; private schema nfcheck_online, restricted runtime role and verified TLS |
| React / React DOM | 19.3.1 / 19.3.1 | Browser interface |
| TypeScript | 7.0.2 | Frontend language/type checking |
| Inertia React / Laravel adapters | 3.11.0 / 3.7.0 | Connect Laravel routes and React pages |
| Tailwind CSS / Vite plugin | 4.3.5 / 4.3.5 | Styling alongside project CSS |
| Vite | 8.3.6 | Frontend development/build tooling |
| React Vite / Laravel Vite plugins | 6.2.1 / 3.2.1 | Build integration |
| Lucide React | 1.70.0 | Icons |
| Instrument Sans / JetBrains Mono | Locally hosted licensed font assets | Interface typography |
| Dompdf | 3.1.6 | PDF exports |
| OpenSpout | 5.13.0 | Spreadsheet exports |
| S3 storage adapter / AWS SDK | 3.35.3 / 3.403.2 | Added during subsequent November 10 cloud preparation; local portraits remain the default |
| Web NFC | Native browser `NDEFReader` API; NTAG215 NDEF tags | Credential reading/writing; supported Android Chrome testing |
| Gemini | `gemini-3.1-flash-lite`, optional server-side HTTP integration | Restricted synthetic-lab summaries; main workspace excluded; Free Tier only |
| Sessions / cache / portraits | Local: encrypted database sessions, file cache, private local photos. Online: encrypted database sessions, database cache, private Supabase S3 | Online services configured; hosted container acceptance pending |
| Authentication | Laravel username/password, Argon2id; administrator and gate account roles | Server-authorized operations |

## Installed development and support tools

| Tool | Installed version |
| --- | --- |
| Node.js | 24.22.2 |
| npm | 11.19.1 |
| Composer | 2.11.1, project-selected patched PHAR |
| Git for Windows | 2.56.0.windows.4 |
| Mailpit | 1.33.1; local mail inbox |
| PHPUnit | 12.5.43 |
| Mockery | 1.6.15 |
| Collision | 8.9.5 |
| Laravel Pint | 1.32.1 |
| Prettier | 3.9.10 |
| Frontend test runner | Built-in Node test runner |
| Node / React / React DOM type definitions | 24.19.2 / 19.3.0 / 19.3.0 |
| Private PHP linked OpenSSL / libcurl | 3.5.9 / 8.23.0 |
| PostgreSQL pg_dump / psql | 17.11, private maintenance clients only; no local PostgreSQL server |
| Local orchestration | PowerShell scripts; isolated PHP development server |

No public website has been deployed. Supabase PostgreSQL and private S3 storage are now initialized/tested; Vercel Hobby Container is the prepared hosting route, with its first build pending. TiDB Starter was rejected after privilege tests. No new physical NFC, speaker or iOS acceptance is established.

Sanitized command outputs are retained locally under ignored `.tools/artifacts/stack-review-{composer-audit,composer-direct,npm-audit,npm-installed}.json`. Existing unrelated phone-connection script edits were preserved.
