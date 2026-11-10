# Backup and recovery

Keep local and online data, private photos and the application encryption key together in a controlled backup plan. The key is needed to read encrypted sessions and other key-dependent data. Never commit backups, environment files or passwords.

Local tooling uses encrypted archives under .tools/backups with the private backup key in .tools/secrets/backup-key.txt. PostgreSQL maintenance tooling uses native pg_dump/psql over verified TLS and can verify an owned disposable restore by table fingerprints. Temporary plaintext is removed after a successful drill.

Restore only into a newly created owned drill schema. Never overwrite main, testing, simulator, hardware or online data to demonstrate recovery. Compare all table fingerprints and confirm source state is unchanged before removing the exact owned drill target.

Structure-only snapshots in database/schema are documentation, not backups. They do not include records, catalogue rows, operator credentials, portraits or the application key. A fresh installation uses migrations and explicit account provisioning.

Retain encrypted archives and the decryption key separately in operator-controlled storage. Restore work and online migrations require their target identities and privileges to be reviewed. Provider dashboards alone do not establish a tested recovery path.
