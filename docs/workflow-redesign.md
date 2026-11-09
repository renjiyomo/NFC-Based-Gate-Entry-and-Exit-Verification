# Gate-account workflow — November 9, 2026

This is the current workflow requested after Phase 11. Earlier phase reports describe the previous individual-guard/manual-confirmation system. Their historical evidence remains intact; their grant/review/schedule instructions do not govern new scans.

The follow-up [directory, scan edge-case and lost-coin review](scan-edge-cases.md) adds list dropdowns, visible day-aware history warnings and a clearer loss/replacement route. Its latest full checks passed 210 PHP tests/2,313 assertions, 28 frontend tests and 11 real races. The original redesign report remains its earlier checkpoint.

## Roles and sequence

Two operator roles remain: **Administrator** and **Gate account** (the retained database role value is `guard`). Students/employees are registered persons, not login roles. Each gate has one unique username/password account. All guards at that gate use its session; the university manages guard dates/shifts outside NFCheck. New records identify the gate account, not an individual guard on duty. Ordinary session security/idle expiry still applies; there is no forced sign-out at a shift change.

1. Administrator creates the gate name/code and username/password in one form. Both save atomically. No email activation, separate guard name or assignment dates are required. Gate status controls its login and scanning; password may be reset on the same edit form. One account per gate is enforced by the unique database gate reference.
2. Administrator registers an active student or employee using the controlled profile fields. There is no separate campus-access grant in this workflow.
3. Administrator checks a coin, selects the person, prepares/writes that same checked coin, reads it back separately and activates it. The checked-destination/live-coin overwrite protections remain essential. Issuance is separate from gate scanning; it does not record an Entry/Exit.
4. The gate account signs in with username/password. Its own active station is selected automatically; it cannot operate another gate. Choose Entry or Exit once for the lane/session. Direction stays selected and never alternates by guessing.
5. A successful NFC read sends a unique request to the backend. An active person plus a selected active, unexpired credential is eligible. The backend saves the selected Entry/Exit atomically without manual confirmation, with `gate-auto-v2` policy and `nfc_scan` recording method. The result identifies the person and may show a private photo for monitoring.
6. Unknown/malformed/revoked/cancelled/expired/inactive credentials do not create a passage. Same-request retries return the original result. Same-person/gate/direction repeats within the existing ten-second window produce a duplicate result rather than another passage. Entry followed by Exit remains two different directions.
7. If a connection ends, recover the original request status. Retry the same request when offered. A missing request whose payload was cleared can reread a coin under the same request/direction; a changed payload cannot replace an already saved request. Do not fabricate a second operation to replace an uncertain first one.

These are **credential-scan records**. They do not independently prove holder identity or a physical crossing. Existing `photo`/`campus_document` observations remain labeled as their original method; they were not rewritten as automatic scans. Presence remains a last-recorded-direction estimate, not attendance or verified occupancy.

## Academic catalogue

The database contains the user's 17 programs with their supplied codes and six departments. New input must match the reference tables, including backend validation; free typing a program/department or sending arrays is rejected. Student academic term is replaced by separate required academic year and semester fields. Academic year uses consecutive `YYYY-YYYY`; semesters are 1st Semester, 2nd Semester and Midyear. Standard year-level dropdowns are 1–4, with the maximum stored per program. Year level is curriculum standing, not the number of years a student has remained enrolled. Academic metadata does not silently expire a person's credential.

The [official BU program list](https://bicol-u.edu.ph/programs/) matches the supplied Polangui list. BU also publishes the [CHED Computer Engineering curriculum standard](https://bicol-u.edu.ph/wp-content/uploads/2025/09/CMO-87-s.-2017-BS-Computer-Engineering.pdf), which describes a four-year curriculum. This supports the current undergraduate default; it is not a claim that every BU programme or historical curriculum has a four-year maximum. Before institutional rollout, the registrar should confirm each programme's catalogue maximum, legacy/irregular cases and department naming. Programme names follow the user's supplied spelling/abbreviations. Old profile text/year/academic-term values remain retained; editing an old profile requires selecting valid current fields instead of silently guessing a replacement.

## Accounts and recovery

Current active login inventory is in the private `.tools/secrets/operator-accounts.json` file. Main administrators: `alexi`, `pauleen`, `zaidy`; main gates: `gate001`, `gate002`. Simulator: administrator `simulator`, gate `sim-gate-01`. Hardware: administrator `hardware.admin`, gate `hw-gate-01`. The workspaces remain separate. The handoff contains newly generated gate passwords and only initial administrator passwords that still match their stored hash. Personally changed passwords stay unchanged and cannot be recovered from hashes. Passwords are not printed in chat or committed. A later administrator password change does not create a new plaintext password inventory.

Former individual lab guard accounts are retained as inactive historical actors. Their old password files are historical, not the current gate login handoff. Gate passwords are set/reset directly by an administrator, without sending mail. Private CLI provisioning is now for administrators; gate accounts are created with their gate.

## Verification and deployment boundary

Migration 19 adds username/gate-account binding, 17 program and six department reference rows, academic year/semester, and the automatic-record contract while preserving earlier rows. Existing access grants/dated assignments are retained for legacy foreign-key/history integrity; their mutation endpoints return 410 and their forms have been removed from current screens. Legacy manual-service regression tests still exercise retained historical contracts, while new-flow HTTP tests and two additional real concurrency races cover automatic recording. Reference catalogues have SELECT-only runtime privileges.

The final report records exact test counts, screenshots and retained records. The redesigned automatic flow still needs a new physical phone Entry/Exit acceptance pass; the earlier manual-flow RF results do not prove this new interaction. No USB debugging device was detected during this revision's connection check.

No GitHub publication or Vercel deployment occurred. [Vercel lists PHP as a community runtime](https://vercel.com/docs/functions/runtimes); a straight upload of this local Laravel/MySQL/private-file/worker setup is not a deployment solution. Database hosting, durable private storage/session/cache, migration identity and any worker arrangement need a concrete reviewed hosting design. Complete the flow/device checks before publishing or changing hosting.
