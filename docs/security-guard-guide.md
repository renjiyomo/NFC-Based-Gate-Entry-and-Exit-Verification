# Gate monitoring guide

1. Sign in with this gate's username/password. Its active station appears automatically. All guards assigned by the university to that gate use its session; there is no shift/date form or individual guard sign-in. Normal idle/security session expiry still applies.
2. Tap **Start Scanner** once on a compatible Android NFC phone, allow NFC access and wait for **Ready to Scan**. Keep Chrome visible and the phone unlocked.
3. Present one coin, then remove it after the result. The server detects Entry or Exit from the latest effective scan in the Manila operating day. No direction selection or per-person confirmation is required. The result shows the person, optional private photo and recorded time. The reader stays ready for the next person; old results clear automatically.
4. Green means recorded. Amber means a recorded history warning requiring review. Blue **Already Scanned** means no additional passage; the ten-second backend window applies across gates. Red/rejected means no passage. Optional Sound supplements visible results. A prior-day unclosed Entry remains in history and is flagged Missing Exit after its recorded cutoff when the catch-up job runs; today's first valid scan can still be Entry. No Exit timestamp is invented.
5. Monitor the gate and follow the university's own escalation and emergency procedures. The record identifies the gate account, not the individual guard on duty. A coin scan alone does not prove who carried it or that they crossed the gate. Report a concern or ask an administrator for a reasoned correction when needed.

## Connection and phone issues

An unusual recorded sequence can show an amber warning. The previous effective record may be shown. Missing scans and overnight visits can explain gaps; do not accuse or automatically block someone based on sequence alone. No missing passage is invented. Report a mistaken direction for administrator correction. See [NFC behavior and coin handling](nfc-integration.md).

If someone reports a lost coin, ask an administrator to verify their identity and revoke the coin as lost immediately. A replacement uses a new reference and matching read-back/activation. The recovered old reference remains revoked. The guard account cannot issue/revoke credentials. Follow the university's approved procedure for a person without a usable coin; the app does not fabricate a successful NFC event.

If a request loses connection, preserve the page and use **Recover request status** after reconnection. The original may already be saved. Use **Retry the same request** when offered. If its transient reference was cleared and the server reports the request missing, use **Read original coin for same request**. Do not submit a new request for an uncertain scan. No offline success or trusted offline queue exists. Resume scanning explicitly after recovery.

NFC must be on, the phone unlocked and Chrome visible. Permission denial, locking/backgrounding and reader failures stop scanning; start again deliberately. The continuous gate listener can remain idle; separate issuance/setup reads retain their timeout. Web NFC cannot prove coin removal: repeated events extend a quiet interval, but guards should ensure each person presents and removes one coin. NFC setup's reference-format read is diagnostic only and creates no passage. Desktop Chrome/IAB NFC support is not assumed.

Local physical testing uses http://localhost:8003 with the authorized USB reverse connection; the current gate username is `hw-gate-01`, with its password in the private operator account file. Old `hardware.guard1`/`guard2` logins are retired. Reconnect/unlock/authorize USB debugging and ask the operator to restore forwarding after a cable disconnect. A real deployment needs trusted HTTPS on the chosen phone-access origin.

For continuing outages use the institution-approved manual/emergency procedure; do not infer emergency egress from estimated presence. Manual ledgers remain separate: there is no backdated manual-passage import. Sign out when closing the station, secure the device and report unresolved issues. See [architecture](architecture.md) and [deployment](deployment.md).
