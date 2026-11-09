# Administrator guide

The current sequence is **create gate/account → register person → issue checked coin → monitor automatic scans**. See [current workflow and evidence](workflow-redesign.md); earlier phase reports describe the previous workflow.

## Gate accounts

Open Gate stations → Add gate and account. Enter a gate name/code, unique username, password and matching confirmation. Passwords need at least 15 characters. Gate/account creation is one transaction. The account can immediately sign in; no email setup link or separate guard profile is needed.

Guards at a gate share its account/session across shifts. Their schedules are managed by the university outside NFCheck. Each account can operate only its own gate. Edit that gate to change its username or reset its password; leave password blank to retain it. Gate status controls scanning and the gate login. Archiving preserves history and disables the account; restore leaves it inactive until deliberately enabled. Password changes invalidate old sessions.

The Gates & accounts list shows the gate and its username together; no separate guard-login setup step is needed. Existing individual lab guards are inactive historical records. Administrator accounts remain separate and have no automatic gate-scanning permission.

## Register people

Students require name, student number, program, year level, academic year, semester and status. Select among the 17 BU Polangui programmes and supported year levels, currently 1–4. Academic year uses consecutive years such as 2026-2027. Employee records require name, employee number, department, position and status; choose one of the six departments. Photo upload is optional in the current form: JPEG/PNG/WebP up to 2 MB, privately normalized. Search before adding duplicates.

The directory lists also have catalogue dropdowns: Program and Year level for Students, Department for Employees. Other / older record values finds retained older profiles. Exact program filtering keeps BSIT separate from BSIT Animation. Combine filters with search/status/archive options; Clear filters restores the full list.

An active person plus an activated selected NFC credential can scan. No separate campus-access grant is needed. Deactivate a person or revoke their credential to stop eligibility. Academic year/semester describe the profile and do not automatically create an enrollment/attendance policy. Existing legacy profiles are preserved; choose current catalog values when editing them.

## Issue and replace coins

1. Check one coin first. Active/pending references show their owner and block ordinary overwrite. Establish ownership before repurposing unrelated/unknown contents.
2. Choose the person, a unique label and issuance purpose. Prepare pending issuance.
3. Write the same checked coin, remove it, read it back separately, and activate after a match within the ten-minute pending deadline. A different destination or wrong read-back is rejected. Keep Chrome visible and the phone unlocked.
4. Check the resulting owner/label before handoff. On a lost coin, revoke immediately before replacement. Normal replacement keeps the old selection until the new credential activates. A returned coin must be explicitly retired before reuse.

External erasure/rewrite cannot update the registry. Reconcile and revoke an orphaned reference, then deliberately issue a replacement. Never transfer an existing credential's ownership. Retain old labels/history. The previous student/employee active coins remain unchanged.

For loss, open the person → Manage coin / report lost → Report lost or revoke coin → Lost tag → confirm. Verify Revoked, then use Issue replacement. The missing coin is not required. Check the person's identity first, and never postpone lost revocation until replacement activation. A failed replacement leaves the lost coin revoked. If the old coin is found, do not reactivate its old reference. See [edge cases and lost-coin procedure](scan-edge-cases.md).

## Monitor and correct

Valid scans automatically record the selected Entry/Exit. Denials and duplicate suppression remain separate. Reports use Manila periods and effective, nonvoided records. A new NFC-scan record is not a manual identity attestation or independent proof of crossing. Presence is estimated, not attendance.

Repeated directions, missing Entry and earlier-day unclosed records are amber warnings on saved scans. They remain visible in history; they are not the same as a suppressed rapid duplicate. New history gaps never automatically create/backdate an opposite passage or reset at midnight. Review a suspected wrong direction and apply a reasoned correction when warranted.

Use reasoned append-only note/void/reinstate corrections for mistakes; do not edit originals. Anomalies are review indicators, not misconduct. Reports export privately with existing row bounds. AI remains Gemini Free Tier synthetic lab-only, without paid fallback/billing; main/production AI is excluded. Use [backup/recovery](backup-and-recovery.md), approved privacy/retention procedures and [guard recovery](security-guard-guide.md). Production and broader device/operational acceptance remain pending.

## Private login handoff

Use `.tools/secrets/operator-accounts.json` for the active username inventory and available initial passwords. Personal administrator passwords are unchanged; hashes cannot reveal them. Keep this file private. Main, simulator and hardware data/accounts are separate. The existing email-based initial-account files describe historical provisioning; username/password is the current sign-in screen.
