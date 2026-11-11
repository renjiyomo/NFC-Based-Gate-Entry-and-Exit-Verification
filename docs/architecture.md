# Architecture

Laravel handles authentication, authorization, validation, transactions, database locks, reports, private photos and exports. React/TypeScript with Inertia renders the interface. Vite builds frontend assets. Native browser Web NFC supplies NDEF records on supported Android Chrome; credential verification and direction decisions remain on the backend.

One active gate account operates its own gate. Administrators manage people, credentials, gates and review workflows. Student/employee classification controls catalogue fields and independent credential numbering.

Credential issuance is pending → checked coin write → matching read-back → activation. Raw references and completion nonces exist transiently; only digests are stored. Ownership/history are immutable. A database-backed counter assigns display labels inside the issuance transaction.

A persistent reader sends one request at a time to the automatic-scan endpoint. Stable request IDs support recovery after ambiguous connection failures. Person locks serialize direction decisions across gates. Current effective history and a Manila operating-day cutoff determine the next direction; the duplicate check precedes toggling.

Single-gate activity checks run within the automatic scan transaction. A person lock serializes repeated-scan decisions and groups matching alerts within one minute. Flags and their evidence remain append-only. Manual historical checks also examine overdue Missing Exit records. Unattended Missing Exit detection uses bounded idempotent catch-up pages; a scheduler must actually invoke the command or protected hosted endpoint.

Optional Gemini summaries receive aggregate facts and anonymous gate aliases. The provider selects valid fact identifiers; backend validation renders the saved numerical statements. Claims and daily quotas commit before the external request, and uncertain responses are not automatically retried. Original and online workspaces require explicit aggregate approval. Summary requests never control scan authorization or Entry/Exit direction.

Local MySQL, isolated test/lab schemas and online PostgreSQL retain separate credentials, keys, cookies and sources. The cloud application uses verified TLS, a restricted database role, private object storage, database sessions and shared cache.

See [database structure](database.md), [NFC support](nfc-integration.md) and [deployment](deployment.md).
