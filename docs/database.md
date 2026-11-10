# Database design

Migrations under database/migrations are authoritative. Reference exports:

- [MySQL structure](../database/schema/mysql-schema.sql)
- [PostgreSQL structure](../database/schema/postgresql-schema.sql)

These snapshots have no rows, passwords, private portraits or usable NFC references. They are not installation scripts: use the migration setup in [development](development.md). A live MySQL database consists of managed engine files, not a required project-root SQL file.

Core relationships include users/gates, persons/student_profiles/employee_profiles, nfc_credentials/credential_assignments, scan_attempts/access_events, passage_corrections, anomaly_flags/evidence/reviews and summary requests/tasks/results/reviews. Program and department catalogues are database-backed.

credential_counters contains one row per person classification. Issuance locks the relevant row and assigns TAG-STUDENT-nnn or TAG-EMPLOYEE-nnn in the same transaction as the pending credential. Each sequence is independent. Cancelled, revoked and expired credentials retain their labels; committed numbers are not reused. An issuance transaction that rolls back does not consume its number. Padding is a minimum of three digits: 999 is followed by 1000. Existing labels are not renamed. The label is an inventory identifier, not the NFC secret or hardware UID.

Only effective confirmed passages count as Entry/Exit/presence estimates. Corrections append history rather than rewriting events. State-based scan decisions lock the person across gates, use a configurable Manila operating day, and suppress rapid repeats before toggling direction. Missing Exit is an append-only anomaly; no false Exit timestamp is created.

Timestamps are stored UTC and displayed/filtered in Asia/Manila. Gate state and original history are not evidence of an actual physical crossing. Database constraints, runtime column grants and backend authorization provide separate enforcement layers.

Local schemas nfcheck_testing, nfcheck_simulator and nfcheck_hardware are isolated from nfcheck. The online nfcheck_online PostgreSQL schema is another independent workspace. A coin issued in one workspace is not automatically registered in another.

To refresh the local reference export, use `scripts/dev.ps1 artisan` for normal application commands and `.tools/php/php.exe scripts/export-schema.php mysql` for the guarded schema-only export. PostgreSQL reference export requires an active owned disposable validation target; it does not dump the permanent online application's rows.
