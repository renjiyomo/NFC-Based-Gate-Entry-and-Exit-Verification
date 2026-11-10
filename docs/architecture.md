# Architecture

Laravel handles authentication, authorization, validation, transactions, database locks, reports, private photos and exports. React/TypeScript with Inertia renders the interface. Vite builds frontend assets. Native browser Web NFC supplies NDEF records on supported Android Chrome; credential verification and direction decisions remain on the backend.

One active gate account operates its own gate. Administrators manage people, credentials, gates and review workflows. Student/employee classification controls catalogue fields and independent credential numbering.

Credential issuance is pending → checked coin write → matching read-back → activation. Raw references and completion nonces exist transiently; only digests are stored. Ownership/history are immutable. A database-backed counter assigns display labels inside the issuance transaction.

A persistent reader sends one request at a time to the automatic-scan endpoint. Stable request IDs support recovery after ambiguous connection failures. Person locks serialize direction decisions across gates. Current effective history and a Manila operating-day cutoff determine the next direction; the duplicate check precedes toggling.

Missing Exit detection uses bounded idempotent catch-up pages with append-only anomaly/evidence rows. A scheduler must actually invoke the command or protected hosted endpoint. Defining a Laravel schedule alone does not activate a hosted scheduler.

Local MySQL, isolated test/lab schemas and online PostgreSQL retain separate credentials, keys, cookies and sources. The cloud application uses verified TLS, a restricted database role, private object storage, database sessions and shared cache.

See [database structure](database.md), [NFC support](nfc-integration.md) and [deployment](deployment.md).
