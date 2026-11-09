# Current readiness — gate-account revision

The [interface redesign](interface-redesign.md) is now verified locally: portfolio-based light/dark themes, responsive shell/forms/tables and loading states. Latest whole check: 210 PHP tests/2,313 assertions, 33 frontend tests and 11 MySQL races. Existing data/coin and physical/deployment limits below still apply.

**The redesigned software flow is verified locally. Physical acceptance of automatic recording and production deployment remain pending.** The user could not connect the NFC phone for this revision. Earlier manual-flow physical tests do not prove the new interaction. See [current workflow](workflow-redesign.md) and [verification report](workflow-redesign-report.md).

## Current flow

Two roles: Administrator and Gate account. Each gate has one username/password login, shared by guards whose schedules stay outside the app. The administrator registers a person and activates a checked NFC coin. The gate chooses Entry/Exit; a valid scan saves immediately. No separate access grant, dated guard assignment or manual confirmation is required. Students/employees are identity records, not login roles.

Catalogue dropdowns contain the supplied 17 BU Polangui programs and six departments. Year level is bounded, currently 1–4 per program; academic year and semester are separate. Initial/legacy values remain retained until deliberately updated. Registrar confirmation of program maxima/legacy curricula is still required for institutional rollout.

## Verified evidence

- Current whole check: 210 PHP tests / 2,313 assertions, 33 frontend tests (25 NFC, three feedback and five theme tests) and 11 actual overlapping MySQL concurrency scenarios passed. Build/typecheck/style and dependency audits passed. See [interface verification](interface-redesign.md) and [directory filters, sequence warnings and lost coins](scan-edge-cases.md).
- Direct real gate-account sign-in, automatic gate selection, combined gate/account form, catalogue form and mobile scanner were inspected in the browser. There is no manual identity checkbox/Confirm button or shift/date input. These browser checks created no passages and do not replace RF tests.
- Four isolated schemas retain 19 migrations/26 tables and reference catalogues; test business fixtures/quota values were restored. Existing immutable records and selected physical coins remain intact. Runtime immutable/catalogue mutation probes were denied. No private credential value or NFC plaintext pattern appeared in the checked repository/log scope.
- Post-revision encrypted restore drills matched all 26 tables in main, simulator and hardware, preserved each source, and removed their owned disposable schemas and temporary plaintext. See [recovery receipts](evidence/workflow-redesign-recovery.json). All three currently have zero private photos; production-sized/off-device/host-loss/photo acceptance is still pending.

## Data meaning

New records identify an authenticated gate account and a valid credential scan in the chosen direction. They do not independently establish who held the coin or whether a physical crossing occurred. Earlier manual observations retain their original methods; new scans use nfc_scan/gate-auto-v2. Presence is estimated from recorded directions, not a census or classroom attendance. Rule flags are not misconduct findings. AI stays Gemini Free Tier synthetic lab-only, with no billing/payment/paid fallback and no main/production generation.

## Before campus operation or publication

1. Complete the new phone Entry/Exit/duplicate/recovery check and the remaining relevant device/failure matrix. No rewrite of the current coins is needed.
2. Confirm campus identity/access, missing-photo, external guard schedule, outage/emergency and incident responsibilities. Approve registrar catalogue values and study/privacy/retention permissions.
3. Choose and validate a real host/domain, trusted phone HTTPS, supported database/migration target, durable private storage/session/cache, monitoring/patch/clock ownership and mail/recovery arrangements where applicable. Local-only migration guards remain intact.
4. Establish separate off-device backups/key escrow, approved RPO/RTO/retention, production-sized restore/load evidence, and reconciliation of post-backup revocations before reopening.
5. Review GitHub visibility/content and a specific hosting design only after the flow is accepted. No public repository or Vercel deployment was created. PHP community runtime support does not make the existing local MySQL/private-file/polling-worker arrangement a one-click serverless deployment.

Use the [administrator](administrator-guide.md), [gate monitoring](security-guard-guide.md), [deployment](deployment.md) and [backup/recovery](backup-and-recovery.md) guides. Earlier phase reports remain dated historical checkpoints; the current workflow supersedes their individual-guard/grant/manual-confirmation instructions.
