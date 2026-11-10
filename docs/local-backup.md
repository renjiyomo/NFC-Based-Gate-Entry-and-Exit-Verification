# Run NFCheck on a groupmate's Windows laptop

This guide creates a separate local copy for browser testing and offline use after setup. Each laptop gets its own database, administrator password and uploaded photos. The online records are not synchronized to these laptops.

No phone, NFC coin, USB connection, Supabase account, Gemini key, payment card or hosting account is needed for this setup. NFC reading and writing still require a supported NFC device; desktop browser testing covers the rest of the application.

## 1. Check the laptop

Use Windows 10/11, 64-bit, with an Intel or AMD processor. Check Settings → System → About → System type if unsure. Windows on ARM, macOS and Linux are outside this automatic installer's supported path.

Have an internet connection for the initial downloads and several GB of free disk space. Leave extra space for the database and future photos. A current Chrome, Edge or Firefox browser is sufficient for normal desktop pages.

Install these prerequisites once:

| Prerequisite | What to do |
| --- | --- |
| PowerShell 7.4 or later | Follow Microsoft's [PowerShell installation instructions](https://learn.microsoft.com/en-us/powershell/scripting/install/installing-powershell-on-windows). Select a stable Windows x64 release. After installation, close and reopen your terminal. |
| Microsoft Visual C++ x64 Redistributable | Install the current supported x64 runtime from [Microsoft](https://learn.microsoft.com/en-us/cpp/windows/latest-supported-vc-redist). This supplies DLLs needed by PHP and MySQL. If it is already installed, its installer may offer Repair. |
| Editor, optional | Visual Studio Code makes it easier to read files. It is not required to run the website. |

You do not need to replace an existing PHP, Node, MySQL or XAMPP installation. NFCheck's scripts select their own tools inside the project folder:

| Tool | Project version |
| --- | --- |
| PHP | 8.5.12, x64 NTS, required extensions included |
| Node.js | 24.22.2, with npm 11 |
| Composer | 2.11.1 with the pinned local packaging/dependency patch |
| Oracle MySQL | 8.4.13, local port 3307 |
| Mailpit | 1.33.1, local development inbox |

The setup verifies the downloaded tools against committed hashes, installs PHP packages from composer.lock and frontend packages from package-lock.json, then checks the build. Do not substitute an old global PHP/npm binary, run composer update/npm update, or bypass a failed verification to finish setup.

## 2. Download the source

1. Sign in to GitHub with your own account. This repository is private; the owner must give your account access and you must accept the invitation before you can download it. The owner can also share the source ZIP privately.
2. Open the [NFCheck repository](https://github.com/renjiyomo/NFC-Based-Gate-Entry-and-Exit-Verification).
3. Select the main branch, then Code → Download ZIP.
4. Extract the ZIP into a short, writable local folder, for example `C:\NFCheck`. Avoid OneDrive/network folders and do not run files from inside the ZIP viewer.
5. Open the extracted folder. You should see README.md, artisan, package.json, composer.lock and scripts directly inside it. If they are inside another nested folder, open that folder instead.

The ZIP intentionally has no .env, .tools, vendor, node_modules or database rows. Setup creates/downloads these locally. An empty database folder or no existing phpMyAdmin database is normal at this point.

Windows may mark downloaded ZIP contents as coming from the internet. Verify the ZIP came from the intended repository. If its Properties dialog offers Unblock, you can unblock that trusted ZIP before extracting it. Do not bypass an organization-managed execution policy or disable security software to run setup.

## 3. Open the correct terminal and folder

Open **PowerShell 7** from the Start menu. Windows PowerShell 5.1 is a different application and cannot run the initial bootstrap scripts.

Check the version:

```powershell
$PSVersionTable.PSVersion
```

The major version must be 7 and the version must be at least 7.4.

Go to your extracted project folder. Change the example path if you chose another location:

```powershell
Set-Location -LiteralPath 'C:\NFCheck'
Test-Path -LiteralPath .\scripts\setup-local.ps1
```

The second command should print True. If it prints False, you are in the wrong folder.

## 4. Run setup once

```powershell
pwsh -NoProfile -File .\scripts\setup-local.ps1
```

Keep this terminal open and allow each step to finish. Initial downloads can take several minutes, depending on the connection. The installer prints nine numbered steps:

1. Download and verify the project's private PHP, Node, Git and Composer tools.
2. Install PHP packages from the lockfile.
3. Install frontend packages from the lockfile.
4. Create the local .env file.
5. Generate that installation's application key.
6. Download/check MySQL and Mailpit, then create the private local databases and accounts.
7. Create the tables, catalogues and restricted application permissions through migrations.
8. Create one administrator and save its generated password privately.
9. Build the frontend.

Success ends with **Setup complete**. The installer does not open the website automatically. No manual SQL import, email activation, cloud credentials or MySQL root password entry is required.

If setup is interrupted, leave the folder and its files in place. Resolve the reported problem, then run:

```powershell
pwsh -NoProfile -File .\scripts\setup-local.ps1 -Resume
```

This resumes from the saved checkpoint and retries the failed step. Completed steps are retained, including the application key, database credentials and administrator handoff. The checkpoint belongs to this exact folder; keep the folder at the same location after installation.

If setup refuses an **existing installation**, use the normal startup below. An older installation without a setup checkpoint uses the individual maintenance commands in [development](development.md); do not delete its .env or database to force fresh setup.

## 5. Start the website

From PowerShell in the same project folder:

```powershell
.\scripts\dev.ps1 npm run dev
```

This uses the project's downloaded Node/npm and PHP. A globally installed npm is not required. It starts or reuses the verified local database/mail processes and serves the website.

Open [http://127.0.0.1:8000/login](http://127.0.0.1:8000/login).

Keep the serving terminal open. Closing it or pressing Ctrl+C stops the foreground website. Database/mail processes may remain running so that the next startup can reuse them. The launcher checks ownership and will not stop another program occupying its ports.

## 6. Sign in

1. Open `.tools\secrets\initial-accounts.json` inside **your own** project folder using an editor.
2. Find the admin@nfcheck.test entry and read its generated password privately.
3. On the login page, enter username **admin** and that password.
4. Complete the required password change. Save your new password privately; the handoff file contains the initial password and does not become a password manager.

Your password and database are different from those on the original laptop and online deployment. Do not use the online account file or share password files through GitHub/chat.

## 7. Test the web application without a coin

Use clearly labelled synthetic records, for example Test Student and Test Employee.

- Add a student; check the program, year level, academic year and semester selections. Edit the record and test a sample photo.
- Add an employee; check the department dropdown, details and sample photo.
- Add a gate with its username/password, then verify that account can sign in and see its assigned gate.
- Check search, filters, record views, light/dark mode and the administrator navigation.
- Open monitoring/history/reports. An empty installation has no passages, so empty tables and zero passage totals are expected.
- Open the credential registry. Issuing, writing or scanning a physical NFC coin is outside this laptop-only test. An unsupported-NFC message on a desktop browser is expected.

Use the live HTTPS site for the separate phone/coin test. The fresh local database does not contain credentials issued online or on someone else's laptop.

Core local pages do not need an AI provider key. Keep optional external summaries disabled unless separately configured for their approved data scope.

## 8. Start again tomorrow or work offline

After setup and the build have finished, ordinary local use does not need internet. After restarting Windows:

1. Open PowerShell in the same project folder.
2. Run `.\scripts\dev.ps1 npm run dev`.
3. Open `http://127.0.0.1:8000/login` and use the password you set.

You do not rerun fresh setup, import SQL or turn on XAMPP Apache/MySQL each time. Optional frontend hot reload is for code editing; this backup installation uses the built frontend.

Keep the installed folder at its original location. It holds .env, private keys/credentials, MySQL data and uploaded local photos. For an actual records backup, follow [backup and recovery](backup-and-recovery.md); a source ZIP alone is not a data backup. Do not copy a running MySQL data directory or commit private files to Git.

## Troubleshooting

| Message or symptom | What to do |
| --- | --- |
| pwsh is not recognized | Install PowerShell 7 x64, then close/reopen the terminal. Start PowerShell 7 from the Start menu if PATH has not refreshed. |
| Requires PowerShell 7.4 | The terminal is probably Windows PowerShell 5.1. Open PowerShell 7 and check its version. |
| Scripts are disabled or a downloaded script is blocked | Review the downloaded source and Windows/organization policy. On a personally managed PC, RemoteSigned is the normal policy for local unsigned scripts; use Microsoft's [execution-policy guide](https://learn.microsoft.com/en-us/powershell/module/microsoft.powershell.core/about/about_execution_policies). An organization-managed restriction needs its administrator. Do not use Bypass/Unrestricted to suppress the error. |
| VCRUNTIME140.dll, VCRUNTIME140_1.dll or MSVCP140.dll is missing | Install/repair Microsoft's supported Visual C++ x64 Redistributable, then retry/resume. Do not download individual DLLs from third-party sites. |
| npm/php/composer is not recognized | Use the commands in this guide. After setup, `.\scripts\dev.ps1` selects the private tools; the global commands may refer to unrelated installations. |
| Port 3307, 8000, 1025 or 8025 belongs to another process | Stop that program through its own normal controls if it is yours, or ask for help. NFCheck will not kill it or silently change databases. XAMPP's usual port 3306 is separate. |
| Download, certificate or SHA-256 verification failed | Check the connection, available disk space and the exact error. Keep verification enabled. Resolve the failed download before resuming; do not substitute an arbitrary package version. |
| Setup stopped partway | Keep the folder and run setup-local.ps1 with -Resume after fixing the error. |
| Existing installation found | Use normal startup. Use -Resume only if this installer created its checkpoint; preserve the existing .env and data. |
| Browser says connection refused | Check that the serving terminal is still running and use port 8000. The MySQL/mail terminal output alone does not mean the website has started. |
| Table or migration error | Keep the data and error message. Use the guarded migration command from [development](development.md); never use migrate:fresh/db:wipe to repair an existing workspace. |
| Cannot sign in after first password change | Use your new password. The initial handoff password no longer applies; each laptop has its own accounts. |

The installer downloads verified versions, but a new laptop's OS policies, antivirus rules, network and disk permissions can still affect installation. Share the exact non-secret error when asking for help. Never send .env, database passwords, API keys, raw NFC references or private records.
