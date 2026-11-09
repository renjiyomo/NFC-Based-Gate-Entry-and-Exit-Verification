# Phase 9 — AI-assisted aggregate summaries

Completed November 9, 2026 for the approved **synthetic lab / Gemini Free Tier only** scope. Phase 10 has not started. No payment setup, paid provider fallback or real-campus-data AI integration was authorized or introduced. Remaining physical/operational acceptance limits in [Phase 6](phase-6-report.md) still apply.

## Implemented behavior

Administrators have **AI summaries** under Insights. Daily and Monday–Sunday weekly selections show validated Laravel aggregates without needing an AI service. Requests retain the exact aggregate snapshot, reporting period/source, requester, snapshot time, canonical hash and contract version. History separates queued/processing/ready/failed/unavailable/rejected generation from approved/rejected/unreviewed human review.

The model selects and orders 5–12 fact IDs from a bounded backend catalog. Required facts cover Entries, Exits, attempts, denials and saved review flags. Laravel renders every sentence and adds all required measurement limits. This is AI-assisted selection of observations with controlled factual wording, rather than unrestricted model-written prose. Provider JSON containing unknown facts, extra prose, missing required facts, duplicates or omitted limitations is rejected; raw provider text is never stored or displayed. This design prevents the accepted output from supplying new figures, identities, crossing times or incident allegations.

The catalog includes effective passages, distinct people, attempts, denials, suppressed duplicates, voided originals, qualified recorded presence, saved flag/review-state counts and anonymous gate comparisons. Gates are aliased within each snapshot; at most ten gates with effective activity are represented, with an explicit coverage message. The full authorized reports remain the place to identify actual stations and inspect every gate. No cross-period identity for an anonymous gate alias is claimed.

Details show provenance, provider/model, reported token usage, selected statements and append-only administrator reviews. They warn if current aggregates differ from the saved snapshot because of new activity, corrections or anomaly reviews. Approval applies to the saved draft, not later data or any misconduct claim. A stale review revision cannot overwrite another decision. Review reasons reject NFC reference formats and are excluded from flashed input.

## Provider, privacy and no-payment policy

The user selected **Gemini Free Tier only, with no payment or billing forever**, and authorized only synthetic aggregate counts and anonymous gate labels. The user created the Google project/key personally, reported **Free Tier** in AI Studio and saved the key/project ID privately. No Google account permissions or billing settings were changed by the agent. The key was never pasted into chat, source, URLs or client props. The ignored `.tools/secrets/ai-provider.json` inherits access limited to the Windows owner and SYSTEM.

The adapter is pinned to `gemini-3.1-flash-lite` on the fixed HTTPS GenerateContent endpoint. It accepts only the configured `gemini_free` provider/model and an explicit Free Tier confirmation; unsupported provider/model choices are unavailable rather than substituted. The main workspace and non-local environments are excluded. Only the isolated simulator, hardware lab and guarded testing schema are supported. No personal names, campus numbers, operator/gate names, NFC references/digests, photographs, record IDs or freeform notes enter the provider input. Provider input contains the period, numeric facts, fixed factual sentences, anonymous aliases and measurement limits.

**Google account billing cannot be controlled or independently verified by this application.** There is no per-request free-only switch asserted by NFCheck. The project must remain unlinked from billing; the local confirmation is an owner attestation, not a Google billing-status API check. If the free quota is unavailable or exhausted, generation fails without a paid fallback or automatic retry. The core facts and gate workflow remain available. Do not enable billing to resolve quota/verification errors. Google documents free-tier product-improvement use of prompts/responses, which is why this approval covers synthetic data only. [Google pricing](https://ai.google.dev/gemini-api/docs/pricing), [data-use terms](https://ai.google.dev/gemini-api/terms), [billing tiers](https://ai.google.dev/gemini-api/docs/billing).

Authentication uses `x-goog-api-key`, never a query parameter. TLS verification stays enabled; redirects are disabled. Each claim has one request, a 12-second timeout, 600 output-token limit, minimal thinking and no tools, grounding, files, caching or conversation history. Request/response sizes are bounded. Refusal, quota, connection, incomplete and invalid-output cases produce controlled error codes; exception messages/raw responses are discarded. No SDK dependency was required; the adapter uses Laravel's HTTP client. [Key authentication](https://ai.google.dev/gemini-api/docs/api-key), [GenerateContent structured outputs](https://ai.google.dev/gemini-api/docs/generate-content/structured-output), [thinking controls](https://ai.google.dev/gemini-api/docs/generate-content/thinking).

## Jobs, limits and recovery

Provider requests execute in a separate PHP worker, outside gate HTTP serving and outside a database transaction. Requests have an actor-bound idempotency key; changed periods cannot reuse it. A task can be claimed once. The worker rechecks current administrator status/auth revision, configuration and approved workspace before reserving a claim. A locked singleton counter enforces at most **3 generation claims per workspace per UTC day**, including uncertain/failed claimed work. Additional HTTP request throttles bound queue/history creation. This is a request quota, not a monetary billing guarantee.

Claimed work is never automatically retried after a lost response or worker interruption. After two minutes, an administrator can close an interrupted request without another provider call. A late worker result cannot overwrite that terminal outcome. Operators inspect existing generation history before explicitly creating another request after an uncertain HTTP response.

Two new project-owned hidden workers were started and reuse was verified: simulator and hardware. They add no network listeners or Windows services; existing XAMPP/app/ADB processes were preserved. They poll queued tasks every two seconds and make no provider call when there is no eligible task. They are not OS-startup services. For future sessions:

```powershell
# Start/reuse the corresponding lab worker; never opens a public listener.
.\scripts\start-summary-worker.ps1 -Workspace simulator
.\scripts\start-summary-worker.ps1 -Workspace hardware

# Optional one-shot processing for local diagnosis.
.\scripts\dev.ps1 summary-worker simulator
```

The launcher verifies its captured PID/command before reuse and refuses an unrelated PID. It never kills another process. Disabling the private integration prevents further provider work; queued requests then receive unavailable outcomes. Keep the key out of logs and diagnostics.

## Schema and files

Migration 18 is applied to all four fixed schemas. `summary_requests`, `summary_results` and `summary_reviews` have SELECT/INSERT-only runtime privileges. `summary_tasks` additionally permits UPDATE only on `claimed_at`; its model/service enforce a single claim. `summary_controls` contains one quota row and permits UPDATE only on `quota_day` and `claims`. Runtime DELETE is denied. Originals, outcome history and review reasons are retained. Task/actor/control locks serialize claims and decisions; unique actor/request-key and one-result-per-request constraints prevent duplicate outcomes.

Implementation lives in `app/Services/Summaries`, `app/Http/Controllers/Summaries`, four `Summary*` models, the `WorkSummaries` command, typed React summary pages and the worker scripts. `/admin/summaries` supports GET/POST, GET `/{uuid}`, POST `/{uuid}/reviews` and POST `/{uuid}/recover`. Administrator authorization, active sessions, required password changes, CSRF and throttling apply. Source is derived server-side; guards and cross-source lookups are denied. API keys, models, provider overrides and freeform prompts are not accepted by the generation form.

## Verification and live evidence

The final full check passed **182 PHP tests / 1,975 assertions**, **25 NFC adapter tests** and all **four existing multi-process MySQL gate races**. Composer validation/audit, npm audit, PHP/frontend formatting, TypeScript and Vite build passed; audits reported zero vulnerabilities. New checks cover daily/weekly Manila boundaries, safe snapshots and canonical hashes, single claims, command limits, idempotency, quotas, redirects/refusals/outages, invalid model output, review history/stale revisions, corrections, account revocation, source/role restrictions, immutable SQL and recovery without another call. Test transport prevents stray HTTP requests and never loads the workstation's private Gemini key. Fixtures and quota changes roll back in the guarded MySQL testing schema. No separate multi-process summary-worker race benchmark was performed.

One actual Gemini request succeeded using the existing simulator's November 8 synthetic records. It selected **12** validated facts. Key reconciliation: **4 Entries, 1 Exit, 5 passages, 11 attempts, 1 denial, 1 suppressed duplicate, 3 saved review flags (2 open, 1 false alarm)**. Reported usage was **528 input tokens and 125 output tokens including thinking**. A synthetic administrator review approved the saved draft after checking the counts. The request/result/task/review remain in the simulator as traceable evidence. No live call was made from main or hardware. [Sanitized live evidence](evidence/phase-9-live-evidence.json).

Browser QA verified disabled-provider facts/empty history, live request → queued → ready, provenance/tokens, approval history, weekly selection/history and desktop/393×851 layouts. Mobile content stayed within the document width; facts tables scroll within their regions. Sessions were signed out afterward. The Google Free Tier status is user-reported; local key validity, successful API authentication, output consistency and private-file access were independently checked.

Main remains 4 users / 2 people / 1 gate / zero credentials, attempts, passages, flags or summary requests; all three requested administrators are active. Simulator retains its previous 7 closed credentials / 11 attempts / 5 passages / 3 passage corrections / 3 flags / 6 evidence links / 2 anomaly reviews, plus one summary request/task/result/review. Hardware retains 9 credentials / 26 attempts / 6 passages and unchanged active student/employee coins, with zero summary requests. There are no pending gate reviews. Testing business fixtures are zero; each schema retains its single quota-control row. Runtime accounts reject 23 tested immutable/protected SQL operations. Secret-value scans include the actual API key and found no tracked/reviewable-file or application-log exposure.

## Limits and next phase

Live evidence covers one synthetic daily generation. Weekly boundaries/output handling are automated; weekly live-provider quality and broader quota/outage behavior are not claimed as physically/live tested. Output is deliberately constrained factual observation selection, not unconstrained narrative analysis. Provider availability, free quotas and terms can change; no automatic paid upgrade or model switch is implemented. Main/real data use remains excluded. Production privacy/retention authorization, deeper worker concurrency/load testing, backup/restore and the remaining Phase 6 hardware cases are outstanding. Earlier phase limitations remain applicable.

Phase 9 is complete for this restricted local scope. Stop here. Phase 10 security/testing/optimization requires explicit approval; no public deployment has occurred.
