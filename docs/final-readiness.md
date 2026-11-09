# Final project readiness — November 9, 2026

**Ready for continued local demonstration and controlled synthetic testing. Not yet approved for production deployment, real campus operation or participant research.** Phase 11 preparation is complete; public deployment has not occurred. This assessment preserves the narrower physical acceptance status from Phase 6.

## Scope reconciliation

| Requirement | Implemented evidence | Remaining boundary |
| --- | --- | --- |
| R01 Authentication | Private provisioning, password/reset/session controls, throttles and server roles | Production SMTP, HTTPS/host acceptance and institutional account ownership |
| R02–R03 Students/employees | Validated profiles, retained archives, private raster photos, search/pagination | Approved real fields/data, dual-affiliation policy if needed, missing-photo procedure |
| R04 Accounts/gates/assignments | Active operators, gates, bounded assignments and fresh confirmation checks | Real inventory, assignment schedules and operating responsibilities |
| R05 Credentials | Digest-only references, permanent ownership, check/write/read-back/activate, revocation/replacement and retained history | NTAG215 remains clonable; external erasure needs reconciliation |
| R06 NFC | Android Chrome NDEF adapter, permission/lifecycle/error handling, controlled vivo/NTAG215 session | Broader phone/tag matrix, two-guard and remaining interrupted/post-commit cases in Phase 6 |
| R07 Passage | Explicit direction, guard identity/observed-passage confirmation, expiry, retries and duplicates | Human accuracy, supervised guard acceptance and approved outage/egress procedure |
| R08–R10 History/monitoring/reports | Immutable events and append-only corrections, reconciled Manila periods/charts, private CSV/XLSX/PDF | Presence is estimated; peak-volume load and retention/research permissions |
| R11 Anomaly review | Versioned deterministic rules, evidence, human decisions, stale-review/concurrent detection protection | Local thresholds are development hypotheses; after-hours disabled; no ML or guilt inference |
| R12 AI summaries | Gemini Free Tier synthetic aggregates, fact-ID selection/backend wording, provenance, bounded jobs and review | Main/real-data/production AI excluded; no paid fallback or billing ever; not unrestricted generated incident prose |
| R13 Audit/settings | Allowlisted audits, narrow runtime grants, validated operational settings, recovery and logging runbooks | No tamper-proof external audit, automatic retention purge or institutional incident authorization |

No student portal, classroom attendance, facial recognition, GPS tracking, automatic barrier control, UID authentication, native phone app, separate Node backend, mass import or ML model is implied by this implementation. The revised gate-verification title/scope governs the older manuscript. Gate records do not prove attendance, continuous presence or wrongdoing.

## Verification and preserved state

Final full check: **196 PHP tests / 2,151 assertions, 25 NFC adapter tests and eight real overlapping MySQL race scenarios** passed, with build/typecheck/style and dependency audits. Phase 10 includes targeted desktop/mobile/keyboard/CSP and 10,001-row query evidence; those timings are not production throughput or full WCAG certification. Phase 11 adds encrypted-archive/configuration-preflight tests and successful full 24-table restore drills for main/simulator/hardware. See [recovery receipts](evidence/phase-11-recovery.json).

The main database retains four users, two people, one gate and zero credentials/attempts/passages/flags/summaries. All three requested administrators remain active. Simulator retains 11 attempts, five passages, three corrections, three flags and one approved summary. Hardware retains 26 attempts, six staged physical-source passages and nine credential records, with separate active student HW-TAG-09 and employee HW-TAG-08. No pending hardware review exists. All four schemas retain 18 migrations; isolated testing fixtures are cleaned up. No password/coin/history was reset and no new live AI call occurred. Shared XAMPP, source-path 403 and existing app/summary processes remain intact.

## Acceptance still required

| Accountable party | Evidence needed before live use |
| --- | --- |
| Institution/security operator | Named owners; real gates/assignments/access rules; identity/missing-photo and emergency/manual-outage procedures; approval of review/duplicate/anomaly timing |
| Host/network operator | Chosen supported host/domain; trusted phone HTTPS/certificate renewal; approved migration-target identity and grants; proxy/perimeter review; SMTP delivery; patching, clock and monitoring ownership |
| Recovery owner | Separate encrypted off-device backups/key escrow; approved RPO/RTO/retention; production-sized and host-loss restore drill, including photos; reconciliation of post-backup revocations and manual records |
| DPO/research authority | Lawful purpose/basis, notice/minimum data, retention/holds, authorized users/processors, incident handling and study/participant permission |
| Research/security lead | Independent labeled evaluation, supervised usability/accessibility, peak-volume load, broader physical NFC/two-guard/response-loss cases; truthful reporting of failures and unknowns |

Local recovery archives and their key are currently on the same laptop; the production environment/Nginx files remain templates. The preflight correctly fails on this development installation and never substitutes for those acceptance decisions. Passing automated tests does not make the system completely secure or operationally approved.

## Practical next steps

Review the [administrator](administrator-guide.md), [guard](security-guard-guide.md), [NFC](nfc-integration.md), [deployment](deployment.md), [operations](environment-and-operations.md) and [recovery](backup-and-recovery.md) guides. Choose whether the next work is completing physical acceptance, preparing a supervised research/demo session, or planning a specific deployment target. Each needs an explicit next instruction and its applicable institutional inputs. There is no automatic Phase 12 or public cutover.
