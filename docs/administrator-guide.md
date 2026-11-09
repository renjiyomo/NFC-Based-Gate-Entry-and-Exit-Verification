# Administrator guide

Use synthetic workspaces until institutional approval for real records/live gate operation exists. Main is http://127.0.0.1:8000/login, simulator http://localhost:8002/login and hardware lab http://localhost:8003/login. Check the workspace banner before acting.

## Accounts

Main has the requested Alexi, Pauleen and Zaidy administrators. Initial passwords are in the private local handoff; never paste them into chat/screenshots. First sign-in requires a personal password. Forgot password uses the matching workspace; local recovery messages stay in Mailpit. Sign out afterward. Campus persons are separate from operator accounts.

There is no public registration. An authorized system operator provisions accounts with `php artisan nfcheck:provision-user <email> --name="Name" --role=admin` or `--role=guard`; the command prompts privately for an initial password. Local `--generate` is development-only. Never put passwords in command arguments. Existing emails are rejected rather than overwritten. Security personnel management and the audited account-access CLI support changes; last-active-administrator protection applies. Deactivation/password/role changes invalidate sessions. An administrator does not automatically gain guard scanning rights.

## People, gates and access

1. Search before adding a student/employee. Enter approved campus identity/profile fields and photographs. Valid raster photos are re-encoded and stored privately. Resolve stale-record warnings by reviewing newer values.
2. Person status and the separate time-bounded campus authorization must both be valid. Active person status does not mean an NFC credential exists or access is allowed.
3. Configure approved gates and time-bounded active guard assignments. Inactive/unassigned stations cannot scan/confirm. Obtain actual schedules from campus security.
4. Archiving retains history and closes access/credentials; restoration leaves records inactive for deliberate review. Never delete history to hide mistakes.

## NFC coin process

Keep one coin near the phone and use visible nonsensitive inventory labels. Confirm the intended person before preparation.

- Open Issue credential and **Check NFC coin first**. Active/pending references show the owner and block overwrite. Unknown/unrelated data is blocked; establish ownership and the reuse decision before erasing anything.
- First issuance uses a ready coin and correct person. Normal replacement keeps the current credential selected until successful activation. For loss/theft, revoke the old credential as lost immediately, then issue a replacement; replacement failure cannot undo loss revocation.
- Prepare pending issuance, write the **same checked coin**, remove it, perform a separate matching read-back, and activate within the displayed ten-minute deadline. A different destination is rejected before writing. Wrong/cancelled read-back disables activation even after an earlier match. Keep Chrome visible and the phone unlocked.
- If reload/navigation loses the receipt, cancel pending issuance and prepare again. Never copy/store the raw payload. After activation, check the coin's owner/label and follow the institution's handoff process.
- For a returned coin, explicitly retire the current reference, verify its closure, then check/reuse that coin. An external erase app is not the routine NFCheck process.

The registry defaults to current credentials; View all history retains closed records. Registry status describes a **database reference**, not proof of coin contents. External erasure/rewrite cannot notify NFCheck. After an external overwrite, inspect each coin, identify affected records, revoke the orphaned/lost reference and issue a deliberate replacement on a checked ready coin. Never reassign a credential to another person. See [NFC handling](nfc-integration.md).

## Monitoring and review

Dates use Manila periods over UTC storage. Only effective confirmed passages count; denied/unconfirmed/cancelled/expired/duplicate attempts stay separate. Presence is a latest-recorded-direction estimate, not a census or attendance. Check source/period before interpretation.

History detail supports reasoned note/void/reinstate corrections. Originals remain intact; another correction requires reload/revision review. Never paste raw references into notes. Private report exports allow 10,000 CSV/XLSX rows or 500 PDF rows. Narrow filters if exceeded; no silent truncation. Protect exports under approved retention/access rules.

Anomaly detection is manual, bounded to 31 days/1,000 attempts. Review the rule/version, evidence and current context. Flags are not misconduct. Record reasoned decisions; reopen terminal outcomes before changing them. Stale revisions are rejected. Lab thresholds and campus hours need approval before operational use.

AI summaries use only approved synthetic lab aggregates through Gemini Free Tier, with no billing/payment or paid fallback. After uncertainty, open the existing request, refresh status and inspect saved facts/provenance before approving/rejecting. The limit is three claims/workspace/UTC day; failures are not retried automatically. Main/production AI is excluded. Approval concerns the snapshot, not operational readiness or wrongdoing.

## Maintenance duties

Use [recovery](backup-and-recovery.md) with an authorized operator. Preserve schemas, APP_KEY and audits. Follow [guard recovery](security-guard-guide.md#outages-and-uncertain-results) for uncertain requests. Escalate lost devices, sharing, overwritten coins and unexpected decisions. Named owners, retention, production mail and emergency procedures are required before campus use.
