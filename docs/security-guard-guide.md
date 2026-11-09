# Gate monitoring guide

1. Sign in with this gate's username/password. Its active station appears automatically. All guards assigned by the university to that gate use its session; there is no shift/date form or individual guard sign-in. Normal idle/security session expiry still applies.
2. Choose **Entry** or **Exit** for the lane. Direction stays selected; it never alternates automatically.
3. Tap **Scan NFC coin** on the compatible Android NFC phone, then present one coin. A valid active credential for an active person is recorded automatically. No identity checkbox or Confirm button is required. The result shows the person and the saved direction/time, and may display their private photo for monitoring.
4. Read the result. Recorded means the backend saved it. Duplicate suppressed means no additional passage was added within the same-person/gate/direction ten-second window. Rejected means no passage was saved. Continue scanning the next person without clearing the previous result first.
5. Monitor the gate and follow the university's own escalation and emergency procedures. The record identifies the gate account, not the individual guard on duty. A coin scan alone does not prove who carried it or that they crossed the gate. Report a concern or ask an administrator for a reasoned correction when needed.

## Connection and phone issues

If a request loses connection, preserve the page and use **Recover request status** after reconnection. The original may already be saved. Use **Retry the same request** when offered. If the token was cleared, reread under that same request/direction; do not invent a replacement event for an uncertain scan. No offline success or trusted offline queue exists.

NFC must be on, the phone unlocked and Chrome visible. Permission denial, locking/backgrounding and timeout stop the reader; start again deliberately. NFC setup's reference-format read is diagnostic only and creates no passage. Desktop Chrome/IAB NFC support is not assumed.

Local physical testing uses http://localhost:8003 with the authorized USB reverse connection; the current gate username is `hw-gate-01`, with its password in the private operator account file. Old `hardware.guard1`/`guard2` logins are retired. Reconnect/unlock/authorize USB debugging and ask the operator to restore forwarding after a cable disconnect. A real deployment needs trusted HTTPS on the chosen phone-access origin.

For continuing outages use the institution-approved manual/emergency procedure; do not infer emergency egress from estimated presence. Manual ledgers remain separate: there is no backdated manual-passage import. Sign out when closing the station, secure the device and report unresolved issues. See [the current workflow](workflow-redesign.md) and [final readiness](final-readiness.md).
