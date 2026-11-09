# Campus totals and interface refinement

Completed locally on November 9, 2026. This revision follows the user's dashboard and person-record screenshots and supersedes the earlier amber action colour. It preserves the portfolio's charcoal/paper backgrounds, typography, saved theme preference and 620 ms circular transition.

## Dashboard: refresh, totals and periods

The overview checks for new saved records every 30 seconds while its tab is visible. This lets an administrator see scans recorded by other gate sessions. It is a partial data update, not a whole-page reload, and never creates a scan or passage. **Live updates** can now be paused; **Refresh** remains available. Pausing survives a manual data refresh in the current page session.

The first row shows **current campus records**: students, employees, active NFC coins and active gates. Student/employee totals include active and inactive current records and exclude archived records. Active coins count selected, unexpired credentials for the current workspace source. Active gates exclude inactive and archived gates. These are registry counts, not live attendance or proof of what remains written on a physical coin.

The activity row shows **Entries, Exits, rejected scans and estimated inside**. Date and other filters control activity counts, charts, history and report downloads. One day selects that Manila calendar date; one week selects Monday–Sunday containing the chosen date; one month selects its calendar month; custom dates include both end dates. The applied range and update time are visible below the controls.

Estimated inside follows each person's last effective passage across all gates up to the period cutoff. It does not use the period start or gate/account filter. Missing scans affect the estimate. The expandable number definitions explain this limit; it is not a campus headcount or classroom attendance. Analytics uses distinct people with passages as its fourth activity total instead.

## Interface changes

- Blue actions and blue navigation/focus accents take their direction from the existing BU Polangui seal. Orange remains an institutional accent; green, amber and red retain their outcome meanings. These are logo-inspired application colours, not a claim of an official brand specification.
- Student and employee records have a larger portrait, a clear name/number/status group, one edit/archive action area, aligned profile fields and a separate current-coin section. Lost-coin guidance appears with an active coin. Credential history remains intact.
- Add/edit person forms use a compact upload button, image preview, change/remove controls, filename and upload limits. The photo control appears before the identity fields. Blob previews are released when replaced or unmounted. Client checks reject unsupported, empty or oversized files; server image validation/re-encoding is unchanged. Selecting a file does not save a record.
- NFC credentials and Gates & accounts each have four filter fields in one row at desktop width; narrower screens use fewer columns. Existing person catalogue dropdowns are retained.
- History has visible **Recorded Entry & Exit / All scan attempts** tabs. Primary controls select the period; more filters hold gate, person, direction and result options. Table warning explanations expand in place. Individual records use readable reason/method labels and aligned details.
- Reports focuses on choosing summary, passage or scan-attempt content and downloading CSV, Excel or PDF. Analytics contains comparisons, readable rejection reasons and the busiest hours, with all hours available in expanded data. Server export limits and correction rules are unchanged.
- Activity checks puts filtering and the review queue before optional detection/rule details. Summaries puts period selection and draft availability before the expandable source numbers. Review decisions, revision checks, privacy/Free Tier restrictions and saved provenance remain available. Existing historical notes/results were retained.
- NFC setup contains browser/connection/permission status and deliberate access/coin-format checks. The phone preparation/USB-development panel was removed. Diagnostic reads still do not validate identity, write a coin or record a passage; cancellation, timeout and visibility handling remain in place.
- The gate scanner separates direction/scan controls from the result. Its result retains portrait, identity, direction, saved time, rejection/duplicate/warning distinctions and previous-record context. Recovery/retry still uses the original request. Recent gate activity is a compact list. Guard history no longer shows a redundant single-gate selector or phase-roadmap text.
- Password controls use eye/eye-off icons with accessible names and pressed state, including gate account creation, recovery/change forms and sign-in.

## Verification and limits

- Whole check passed: **211 PHP tests / 2,337 assertions, 36 frontend tests and 11 real MySQL concurrency scenarios**, plus audits, PHP style, TypeScript, formatting and production build. Later presentation adjustments passed a fresh build and all 36 frontend tests.
- The new database test covers date-independent current registry counts, workspace-specific active coins, inactive people and inactive/archived gate exclusion. Three photo tests cover permitted formats, empty/unsupported files and the 2 MB boundary. Existing NFC, automatic recording, duplicate, sequence-warning, revocation, recovery and concurrency checks still pass.
- The 34 selected contrast pairs passed: minimum checked small-text ratio 4.61:1 and control/focus ratio 3.41:1. Filled actions are checked against their separate blue action background. This is a bounded palette check, not a complete accessibility audit.
- Read-only browser checks used hardware/simulator administrator sessions and the hardware gate account. Verified both themes, desktop four-field row positions, photo preview/removal without saving, password reveal/conceal without saving, paused updates/manual refresh, custom Manila dates, unchanged registry totals and history tabs (11 existing attempts versus five existing passages in the simulator period).
- Checked profile, form, monitoring, reports, analytics, activity-check and summary layouts at configured 320/393 px phone widths; scan controls at both sizes; populated summary/flag/scan-detail views at phone width; additional 768 px and desktop views. Checked page bodies had no horizontal overflow; wide tables intentionally scroll. Checked visible phone fields had at least 16 px text and 44 px height. No browser warning/error log entries were observed. Viewport overrides were reset and the test sessions signed out.
- Existing business counts, catalogue privileges and selected student **HW-TAG-09** / employee **HW-TAG-08** were verified unchanged. No account/password change, schema migration, real coin write/revocation, passage/correction/review/detection submission, live AI call, service restart, GitHub push or deployment was performed by the browser review. Read/login auditing still records normal activity.

The phone was unavailable. These browser and software checks do not establish physical NFC/speaker acceptance, iOS Safari acceptance or campus deployment readiness. A fresh automatic Entry/Exit/duplicate/recovery check on the actual NFC phone is still pending.

[Sanitized evidence](evidence/interface-refinement.json) contains the passing counts, palette checks and retained source-state checks. Screenshots remain in ignored `.tools/artifacts`: `ui-overview-refined-light.png`, `ui-profile-refined-dark.png`, `ui-reports-refined-light.png` and `ui-scanner-refined-mobile-dark.png`.
