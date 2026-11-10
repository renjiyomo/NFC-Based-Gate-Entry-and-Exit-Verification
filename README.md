# NFCheck

NFC-Based Campus Gate Entry and Exit Verification and Monitoring System with Data Analytics, Anomaly Detection, and AI-Generated Summaries for Bicol University Polangui.

NFCheck registers students and employees, manages NFC credentials, detects Entry/Exit from scan history, and provides passage history, corrections, reports and anomaly review.

## Start here

- [Set up on another computer](docs/development.md)
- [Find and inspect the database](docs/database-setup.md)
- [Database structure and reference SQL](docs/database.md)
- [Administrator guide](docs/administrator-guide.md)
- [Gate scanner guide](docs/security-guard-guide.md)
- [Hosting and deployment](docs/deployment.md)
- [Browser and NFC requirements](docs/nfc-integration.md)

On an already configured Windows computer:

```powershell
npm run dev
```

Open http://127.0.0.1:8000/login. Keep the terminal open; Ctrl+C stops the foreground website. Frontend hot reload is optional: run `npm run dev:frontend` in another terminal.

## Workflow

Administrators create one username/password account per gate, register people and issue credentials. New labels are assigned by the server: TAG-STUDENT-001 and TAG-EMPLOYEE-001 onward, with independent database counters. Existing labels are retained.

At a gate, start the scanner once and present/remove one coin per person. The backend verifies registration and derives Entry or Exit from the latest effective passage in the operating day. Repeats within ten seconds are suppressed. The reader remains ready between successful scans. NFC references identify registered credentials; they do not independently prove holder identity or an actual crossing.

There are two login roles: Administrator and Gate account. Students and employees are registered people rather than login roles. Guard shifts are managed outside the application.

## Stack

Laravel/PHP, React, TypeScript, Inertia and Vite. Local Windows workspaces use a private MySQL 8.4 instance on port 3307. The cloud configuration supports Supabase PostgreSQL and private S3-compatible photo storage. The full application uses a PHP Docker container; the frontend build alone is insufficient.

## Device support

Non-NFC pages are designed for desktop and mobile browsers, including iOS layouts; physical iOS acceptance remains untested. Safari/iOS does not expose the Web NFC API required for reading/writing coins. Use NFC-enabled Android Chrome for scanning. Installing the website as a PWA does not add missing NFC APIs.

## Repository contents

Migrations are the database source of truth. Structure-only SQL snapshots in database/schema contain no account, person, credential or passage rows and are for reference, not a second installation method. Each groupmate creates a separate database during setup. Private environments, provider keys, passwords, local records/photos, runtime binaries and internal development notes are excluded from Git.

Optional aggregate summaries use a server-side Gemini integration with restricted data scope. Core gate operation does not require an AI key. Free-only provider settings must not be changed to paid fallbacks.
