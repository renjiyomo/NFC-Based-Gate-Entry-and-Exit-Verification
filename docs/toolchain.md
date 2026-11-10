# Toolchain

The Windows x64 bootstrap scripts install verified project-private tools under ignored .tools. Pinned tool/service sources and hashes are in scripts/toolchain/artifacts.json and scripts/service-artifacts.json. These binaries and generated receipts are not application source.

Initial bootstrap uses PowerShell 7.4+. Routine npm run dev locates built-in Windows PowerShell by its full system path. scripts/dev.ps1 selects the private PHP, Node, Composer and Git paths so shared XAMPP/global installations are not silently substituted.

The verified local runtime baseline is PHP 8.5.12, Node 24.22.2, Composer 2.11.1 and Oracle MySQL 8.4.13. The cloud container checks its patched PHP floor and installs the required extensions/dependencies. Recheck upstream advisories and dependency audits before a release; version checks are not a complete security review.

The shared XAMPP PHP/Apache installation is not the selected application server. Preserve its existing services/configuration when working on this project. PostgreSQL/S3 are optional cloud services, not prerequisites for local MySQL use.

See [setup](development.md) and [database connections](database-setup.md).
