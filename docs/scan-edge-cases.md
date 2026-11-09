# Directory filters, unusual scans and lost coins

Current operating policy, November 9, 2026. This extends the [gate-account workflow](workflow-redesign.md). There are no new schema changes or changes to the existing student/employee coins.

## Directory dropdowns

The Students list has a program dropdown backed by the same 17-program database catalogue as Add/Edit, plus a year-level dropdown. The Employees list has the same six-department dropdown as Add/Edit. All selections use exact backend filters: BSIT does not also select BSIT Animation. Search, status, current/archived selection and pagination still combine with these filters. Applying new filters starts at page one.

All programs/departments includes retained older profile values. Other / older record values finds values outside the current catalogue without silently changing the original record. Other / unspecified year finds a missing or out-of-range older year level. These are directory filters; they do not permit arbitrary catalogue values when creating or editing people. Current program/year maxima remain registrar-confirmation inputs for older curricula.

## Gate decision policy

A valid credential for an active person is still recorded automatically. Unusual chronology is a warning for the guard to check, rather than a hard rejection based on potentially incomplete history. New scans never invent a missing Entry/Exit, backdate a replacement record, alternate direction, reset records at midnight or alter an original passage.

| Situation | Saved result | Gate feedback |
| --- | --- | --- |
| First Entry, or Entry following Exit | Entry | Green: recorded |
| Exit following Entry on the same campus day | Exit | Green: recorded |
| Same person/gate/direction within ten seconds | No additional passage | Blue: duplicate suppressed; original time shown |
| Another Entry after an Entry, outside the duplicate window | Entry | Amber: a preceding Exit may be missing |
| Another Exit after an Exit, outside the duplicate window | Exit | Amber: a preceding Entry may be missing |
| Entry today after an unclosed Entry on an earlier day | Entry | Amber: earlier-day Entry has no recorded Exit |
| Exit today after an Exit on an earlier day | Exit | Amber: no Entry on the day of this Exit |
| Exit after an Entry on an earlier day | Exit | Amber: possible overnight visit or missed scan |
| First-ever Exit | Exit | Amber: no earlier Entry recorded |
| Revoked/lost, inactive-person, expired, pending, cancelled or invalid reference | No passage | Red: not recorded, with reason |
| Connection ends before a definite result | No claimed success | Recover the original request status |

Campus-day comparisons use Asia/Manila, including the midnight boundary that occurs within one UTC date. The duplicate window uses elapsed time and continues across midnight. Credential validation happens before duplicate suppression, so a revoked coin is denied even if it recently worked. Sequence checks consider the person's last effective passage across gates in the same authorized source; voided passages and denied attempts do not provide missing history. A warning is attached to the saved passage and appears on the scanner, recent activity and administrator history. Recent authorized warning results can show the previous effective record's time/direction/gate. That context and identity are withheld after the existing recent-result window.

The guard checks the person, displayed details and selected direction when there is a warning. A mistaken saved direction can be referred to an administrator for a reasoned note/void/reinstate correction; original scans remain intact. Repeated scans after the ten-second window are recorded with a warning and can affect scan totals. Totals represent effective recorded scans, not independently verified visits. Presence remains an estimate. The system cannot determine whether a person actually forgot to scan, stayed overnight, borrowed a coin or crossed physically.

Sound feedback is optional and starts off. After Sound on, a normal record has a high tone, a warning two medium tones, a rejection two low tones and a duplicate one lower tone. Text and colour remain the authoritative interface when sound is muted or unavailable. Audio failures cannot block a scan or cause a second write. Recovering a saved request does not automatically replay its tone. Physical NFC/speaker behaviour for this revision remains unverified because the phone was unavailable.

## Lost-coin procedure

1. The student or employee reports the loss to a guard/administrator. An administrator verifies the person's identity under the university's process; reporting a loss does not require the missing coin or an email account.
2. Open Students/Employees, find the person, then Manage coin / report lost. The selected active coin stays visible even after many cancelled replacement attempts. On its credential page choose Report lost or revoke coin, keep Lost tag as the reason and confirm revocation.
3. Confirm that the credential now shows Revoked with reason Lost. The person remains registered; the old selection closes and its ownership/history remain retained. After revocation commits, later scans of that reference are denied without identity or a passage. If the connection ends, reload the credential to check its actual status before proceeding.
4. Use Issue replacement on the revoked-lost credential. Check a different suitable coin, prepare, write that checked coin, perform a separate matching read-back and activate. A new unique reference is generated for the same person. Pending credentials cannot record passage. A failed/cancelled/timed-out replacement never restores the old coin.
5. If the old coin is found, its revoked reference remains invalid. Do not reactivate the old credential or assign its record to another person. An administrator may deliberately repurpose a suitable retired coin through the checked-coin issuance flow, which writes a new reference and preserves old history. The physical coin's old contents never update the database by themselves.

Normal replacement of a still-held working coin keeps it usable until the new credential activates. A lost coin must instead be revoked immediately before starting replacement. Until loss is reported and revocation commits, someone holding the still-active coin may use its reference. NFCheck does not verify a face or cryptographically authenticate a coin holder. [Chrome describes NDEF tags as readable data](https://developer.chrome.com/docs/capabilities/nfc); a static reference can be copied, so this design must not be described as clone-proof. Guard monitoring and prompt revocation remain necessary. For a person with no usable coin or a system outage, follow the university's approved identity/manual/emergency procedure; do not fabricate NFC success or silently backfill scans.

If another replacement was already pending when the loss was reported, cancel that pending issuance before starting the lost-coin replacement. A previously prepared normal-replacement receipt cannot activate after its old selection has been revoked; the backend rejects that stale state. Reload the person's record after another administrator changes its credential.

## Verification

The final whole check passed **210 PHP tests / 2,313 assertions, 28 frontend tests (25 NFC adapter and three feedback tests), and 11 real MySQL concurrency scenarios**. Composer/npm audits, formatting, TypeScript and production build passed. [Sanitized evidence](evidence/scan-edge-review.json) records the exact counts, races and retained workspace state. Migration count stays 19 with 26 tables in each schema; no new migration or privilege change was required.

Automated checks cover all tabled sequence cases; same-UTC-date Manila midnight; duplicate suppression across midnight; voided history; stable request recovery; exact catalogue/legacy/year filters; immediate lost revocation; failed replacement; pending denial; fresh replacement reference; continued rejection of the found old reference; and visibility of the selected credential behind more than ten later cancelled issuances. Real concurrent processes test an automatic scan racing lost revocation and a later denied scan. Isolated fixtures are cleaned up and original workspace business records remain unchanged.

Browser checks exercised both dropdown lists, exact/older-value filtering, mobile layout, the corrected person-to-issuance link, automatic replacement selection, and the lost-reason dialog. The active coin's dialog was cancelled without revoking or writing it. The scanner's direction/sound controls were checked, viewport reset and sessions signed out. A physical scan and audible-tone test still needs the phone; earlier manual-flow phone evidence does not establish this revision's RF behaviour.
