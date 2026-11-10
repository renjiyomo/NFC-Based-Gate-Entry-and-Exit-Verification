# Testing and research

Use synthetic fixtures in the guarded testing database. Tests require MySQL 8.4.13 at 127.0.0.1:3307/nfcheck_testing with the restricted nfcheck_test user. They roll back fixtures. The concurrency harness captures its own records and restores testing counters/quota; do not overlap it with other MySQL tests.

Run PHP tests, concurrency scenarios, frontend tests, TypeScript/build and format checks through scripts/dev.ps1. Actual PostgreSQL checks use a uniquely named owned probe schema, a temporary restricted role, expiring manifest, real overlapping transactions and encrypted restore comparison. They must clean their owned resources and never migrate the permanent online schema as a side effect.

Credential numbering tests cover independent student/employee sequences, cancellation, rollback, server rejection of manual labels, padding beyond 999 and simultaneous issuance by different administrators. Existing labels and raw references must remain intact.

Reader checks cover activation, multiple readings, malformed tags, permission failure, cancellation, listener cleanup and repeat-event handling. Emulation or unit tests cannot establish real RF behavior or iPhone acceptance.

Controlled phone testing has covered the core automatic flow on a vivo Android device: registered identity, automatic records, rapid repeats, blank rejection followed by a valid scan, and Home/background stop with explicit restart. Broader devices, post-commit response loss, overnight/scheduler operation, queues and institutional deployment still need acceptance.

Presence is a last-recorded-direction estimate, not verified occupancy or classroom attendance. Test scans in the original workspace are not proof of actual arrivals/departures. Missing Exit indicates an incomplete recorded sequence; do not infer misconduct.

Keep identifiable records, portraits and private artifacts out of the repository. Retain consent, approved operating hours/cutoff, threshold tuning and usability/research evidence through the university's normal process.
