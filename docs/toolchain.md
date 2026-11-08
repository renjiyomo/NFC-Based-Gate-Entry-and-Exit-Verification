# Isolated development tools

Phase 1 uses project-local tools under the ignored `.tools` directory. The existing XAMPP PHP/Apache/MariaDB installation and global Node, Git and Composer installations are unchanged. This setup is for local development; it does not patch the shared XAMPP services or establish production readiness.

Run the script with **PowerShell 7.4 or later on Windows x64** (the audited host has 7.6.9), from the project directory:

```powershell
pwsh -File '.\scripts\bootstrap-tools.ps1'
pwsh -File '.\scripts\bootstrap-tools.ps1' -VerifyOnly
```

The first command provisions and verifies the pinned tools. `-VerifyOnly` performs no tool download, extraction, configuration generation, clone, dependency installation or rebuild. It checks existing files/configuration and runs the tool's network diagnostics/audit, which may update its private cache. Successful verification writes `.tools/toolchain-receipt.json`. Neither command installs the application's dependencies, creates a database, changes global PATH/settings, restarts services or modifies global Composer configuration.

Normal provisioning regenerates the managed `.tools/php/php.ini`; keep intentional configuration changes in the reviewed script rather than editing that generated file. Environment variables and the working directory are restored on completion or failure. All package-manager home/cache files used here are isolated under `.tools`.

## Pinned inputs

The complete machine-readable download URLs and SHA-256 values are in [`scripts/toolchain/artifacts.json`](../scripts/toolchain/artifacts.json), observed on 7 November 2026:

| Artifact | Purpose | SHA-256 |
| --- | --- | --- |
| PHP 8.5.12-1 Windows x64 NTS ZIP | Matching executable, extensions and dependent DLLs | `2a36a2edcb7a5b9252f638fd5e4cf84d7f6e066092654220dc0b63e5cf4e9723` |
| Node 24.22.2 Windows x64 ZIP | Node with bundled npm 11.19.1 | `ca1bff5d8ead13c9f2ea404a176db46bd430704d5b5113734049fa365e7799e2` |
| MinGit 2.56.0.windows.4 x64 ZIP | Git CLI without a system installer | `e3bf78351fe777fba47ddee5950082f8a98bb0f02604fdc7ba3ee2cb68ffa877` |
| Official Composer 2.11.1 PHAR | Restricted bootstrap only | `6f1b891e1d4927d62740276c540fb490e47689e19e102c49e8da849794882f8c` |
| Mozilla CA bundle dated 5 November 2026 | Explicit TLS certificate verification | `427c064216761a1e72a8b30f0d954d08cb6e4435a82091fb865afd20c8497285` |

Publisher references: [PHP Windows packages/checksums](https://www.php.net/downloads.php?os=windows), [Node signed checksum manifest](https://nodejs.org/dist/v24.22.2/SHASUMS256.txt.asc), [Git release/checksums](https://github.com/git-for-windows/git/releases/tag/v2.56.0.windows.4), [Composer checksum](https://getcomposer.org/download/2.11.1/composer.phar.sha256sum), [curl CA bundle history](https://curl.se/docs/caextract.html). The script compares pinned hashes obtained over verified HTTPS; it does not claim independent GPG-signature validation.

Downloads must match their pinned hashes before extraction/use. Existing mismatched archives or extracted package files cause failure rather than silent replacement. Extracted files are compared with the verified original ZIP entries. No recursive cleanup/deletion occurs. Inspect or quarantine a failed partial download manually; do not bypass its hash check. Updating this dated recipe requires fresh official-source review, new checksums, a reviewed build lockfile and successful audits.

## PHP configuration and service boundary

The complete NTS distribution is appropriate for CLI and the loopback development server. It is not loaded into XAMPP's Apache module. Enable matching cURL, Fileinfo, GD, Intl, Mbstring, OpenSSL, PDO MySQL/SQLite and ZIP extensions. `PHPRC` points to this isolated configuration and additional INI scanning is disabled for the script process.

The managed configuration sets UTC, strict sessions, HttpOnly/SameSite cookies, explicit CA paths, `expose_php=Off`, and `session.upload_progress.enabled=0`. Development errors/assertions remain visible. These settings are development defaults, not a production configuration. The upload-progress setting follows the remaining conditional vector in the [PHP October advisory](https://www.php.net/archive/2026.php).

VerifyOnly checks PHP 8.5.12 NTS, required extensions, upload-progress mitigation, package integrity, Node/Git versions, CA trust material, and Composer build provenance. Before introducing secrets into this workspace, the existing Apache `htdocs` project route must remain blocked; a loopback PHP server alone does not close that original route. See the environment audit and local development instructions for the web boundary.

## Why Composer is rebuilt locally

Official stable Composer 2.11.1's bundled `symfony/filesystem` predates the high-severity [GHSA-6prc-h956-hxjq fix](https://github.com/symfony/symfony/security/advisories/GHSA-6prc-h956-hxjq). A clean application audit does not repair the tool's bundled dependency. The original PHAR is retained solely to install the explicitly locked official build dependencies with plugins and scripts disabled.

The resulting local runtime is **`.tools/composer-patched.phar`**. It is not the publisher's original PHAR; its SHA-256 must not be compared with the publisher's PHAR checksum. Its source and build inputs are recorded separately:

- Official repository: `https://github.com/composer/composer.git`, tag `2.11.1`, commit `472b418967419c6124edd91dc6310a3bec091dae`.
- Reviewed build manifest and exact dependency lock: [`scripts/toolchain/composer.json`](../scripts/toolchain/composer.json) and [`composer.lock`](../scripts/toolchain/composer.lock).
- `symfony/filesystem` is locked to patched **7.4.23**. Symfony Console resolves to **6.4.49** in this build, within Composer's supported constraint. The lockfile is authoritative for every transitive version.
- The build targets the real PHP 8.5.12 runtime, removes unnecessary development-only dependencies/autoload configuration, disables plugins, and retains advisory blocking/auditing. The narrowly constrained filesystem cooldown exception applies an already reviewed security fix immediately; it does not ignore that advisory. [Supported Composer cooldown configuration](https://getcomposer.org/doc/06-config.md#policy)
- [`compiler-completions.patch`](../scripts/toolchain/compiler-completions.patch) adds only the exact existing `completion.fish` and `completion.zsh` templates to the compiler's resource allowlist. Missing/unexpected-file checks remain enabled. These templates are packaged without execution. Compiler source itself is excluded from the runtime PHAR; no runtime vendor code or version constants are patched.

The script refuses an unknown repository/commit, unexpected source modifications/untracked files or a compiler diff differing from that exact patch. It installs the locked build dependencies with `--no-dev --no-plugins --no-scripts --prefer-dist`, then uses Composer's official [`bin/compile`](https://raw.githubusercontent.com/composer/composer/2.11.1/bin/compile). The official compiler establishes the autoloader layout, uses the actual Git tag for version information, and packages its own installed metadata. Building a PHAR also avoids the unbundled package layout and Windows file-URI problems encountered during this phase.

The local PHAR's embedded SHA-512 integrity signature and patched package metadata are checked; that internal signature is not publisher authentication. Its tool lockfile audit and diagnostic must succeed. A receipt stores the local PHAR SHA-256, source commit and build manifest/lock/patch hashes. Future VerifyOnly runs also compare the existing receipt's PHAR hash. This verifies the recorded local artifact; it is not a claim that the workstation is immune to compromise or that arbitrary modified dependencies are trustworthy.

## Composer home and public keys

Only official public verification keys are copied into `.tools/composer-home`. No user-global `auth.json`, credentials, plugin configuration or cache is copied. Keys were checked against [Composer's official public-key page](https://composer.github.io/pubkeys.html):

| Key | Whitespace-normalized SHA-256 fingerprint |
| --- | --- |
| Tags/releases | `57815BA27E54DC317ECC7CC5573090D087719BA68F3BB7234E5D42D084A14642` |
| Dev/snapshots | `4AC45767E5EC22652F0C1167CBBB8A2B0C708369153E328CAD90147DAFE50952` |

These public keys do not authorize development snapshots for NFCheck. The tool recipe uses a stable tagged source checkout and pinned inputs.

## Failure handling and limits

Do not pass insecure dependency flags, disable TLS, suppress advisories or silently replace unknown build trees. Preserve the error and review the failing artifact/configuration. A newly published advisory or new Composer release may make this pinned recipe fail its audit/diagnostic; update the reviewed recipe rather than hiding the finding. An interrupted fresh installation can be rerun when its existing verified files are intact; corrupted partial files require inspection.

The original bootstrap PHAR has a known bundled issue during the bounded build bootstrap. Its use is restricted to official, explicitly locked tool dependencies over verified TLS, within the isolated directory, with no plugins/scripts/custom repositories. This exposure is documented, not called repaired. All subsequent application package work must use the rebuilt tool after its checks pass.

No MySQL server, app authentication, external AI service, public deployment or physical NFC capability is installed by these scripts. Shared Apache, phpMyAdmin and MariaDB still need their separately planned lifecycle work before operational use.
