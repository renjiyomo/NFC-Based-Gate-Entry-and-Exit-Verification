# Administrator guide

Sign in with a local or hosted administrator account for the intended workspace. Change any initial password when required. Local and online account passwords can differ.

1. Create an active gate and its username/password through Gates & accounts. One gate account is shared for that station; university guard scheduling stays outside the application.
2. Register the student or employee with the catalogue fields. Students use program, bounded year level, academic year and semester. Employees use the department catalogue. An active person and active selected credential are required for scanning.
3. Open NFC credentials and choose Issue credential. Check the destination coin before preparing. Select the person and issuance purpose. The server assigns TAG-STUDENT-001 or TAG-EMPLOYEE-001 onward; no manual label is needed.
4. Prepare the pending credential, write the checked coin, remove it, then read back the same coin. Activate only after a confirmed match. The page holds a one-time transient receipt; leaving it can require cancellation and a fresh issuance.
5. Use the registry to inspect current credentials and history. Existing active/pending coins are blocked from accidental overwrite. Do not erase coins with another app to try to change their registration.
6. For a lost coin, revoke it as lost first, then issue a new-reference replacement after verifying the person's identity. The lost reference stays revoked even if replacement fails.
7. Review Entry/Exit history, append reasoned corrections and review anomalies. Missing Exit keeps the original Entry and has no fabricated Exit time. Reports count effective passages, not denied/duplicate attempts.

Private portraits are served through authorized application routes. Avoid institutional personal data in test datasets or public exports. Do not share administrator passwords with gate users.

Overview shows **Estimated inside campus**: total people, students and employees. Each person counts once when their latest effective passage is Entry; a later Exit removes them. Student and employee counts add up to the total. Rejected, duplicate and unfinished scans do not add people. Voiding or reinstating a passage recalculates the latest effective direction.

These current counts cover all gates in the workspace and stay independent of the activity filters below. Live updates refresh them every 30 seconds while the page is visible, or use Refresh. Local and online records remain separate. Missed scans can affect the estimate: a Missing Exit flag, midnight, an inactive record or an archived record does not invent an Exit or automatically subtract someone.

Students and employees are records, not additional login roles. Optional AI summaries use restricted aggregates and a separately configured provider; core scanning does not need AI.
