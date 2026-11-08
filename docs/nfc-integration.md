# NFC integration and credential lifecycle

Status: **Phase 6 software and USB connection verified; vivo NFC access passed as reported by the user; tag acceptance remains pending**. Credential lifecycle, NDEF adapter, shared verifier and assigned-guard review/passage service are connected. The user switched from Samsung SM-A525F to **vivo V2318 / Android 16 / Chrome 156.0.8078.79**. USB debugging authorization, port 8003 forwarding and Android-advertised NFC hardware features are verified on the new phone. The user separately reported a successful Chrome NFC access check on the vivo after its own setup. Both phones' access results are user-reported probes, not tag write/read-back or guard test evidence. Actual tag acceptance remains pending. See [Phase 6 status](phase-6-report.md).

## Supported target and limits

Target NFC Forum Type 2 NTAG215 coins carrying NDEF, read/written using `NDEFReader` on compatible Android Chrome devices in a secure context: trusted HTTPS or phone loopback HTTP over an authorized USB reverse connection. NXP identifies NTAG215 as a Type 2 tag with 504 bytes of user memory; the small reference payload fits without personal information. Tag quality, formatting, locked memory and antenna placement still need physical verification. [NXP NTAG21x product specifications](https://www.nxp.com/products/NTAG213_215_216)

Chrome's Web NFC implementation is NDEF-only: no low-level ISO-DEP, peer-to-peer mode or host card emulation. It needs a top-level secure context, user gesture/permission and a compatible enabled NFC device. Scanning is affected by page visibility and device lock state. Detecting `NDEFReader` establishes API presence, not working NFC hardware. Desktop/iOS/other-browser support is not assumed. [Chrome Web NFC documentation](https://developer.chrome.com/docs/capabilities/nfc)

Do not purchase a reader or phone merely because it advertises NFC. First establish model, Android/Chrome versions, NFC read/write support, NDEF tag compatibility and trusted HTTPS access to the application. A generic USB NFC reader is not automatically usable by Web NFC. This integration does not implement a native smart-card challenge-response protocol.

## Implemented versioned NDEF payload

One NDEF **text** record containing `nfcheck:v1:<base64url-secret>`. Generate exactly 32 cryptographically random bytes in PHP; canonical unpadded base64url yields 43 characters. The text is 54 ASCII bytes before NDEF/text overhead. The placeholder here is a format description, not a live credential.

Both layers validate record type, prefix/version, encoding, exact decoded secret length, canonical encoding and bounded record/message sizes. Reject unrelated, ambiguous multiple credential records, malformed and oversized data. Never navigate a URL read from a tag or trust arbitrary text as instructions. Backend validation remains authoritative even if the client parsed successfully.

Only the random reference is written. No name, campus number, photo, authorization status, API secret or password goes on the tag. Use a unique binary SHA-256 digest of the secret for lookup; never use hardware UID as secure authentication. Tag read-only/password features do not turn a copied NDEF token into proof of identity. Do not claim anti-cloning guarantees.

## Issuance, activation and replacement

| Step | Implemented operation | Failure behavior |
| --- | --- | --- |
| 1 | Authorized administrator selects a valid person; server generates pending credential and permanent assignment | Validation/authorization failure produces no usable token |
| 2 | Plaintext is returned once into the issuance page's transient memory with no-store responses | Lost response/reload requires cancellation and new issuance; no plaintext recovery endpoint |
| 3 | Deliberate write action requests NFC permission and writes the NDEF text | Permission denied, abort, incompatible/locked tag or network loss leaves credential unusable |
| 4 | Separate read-back compares the exact expected secret in the same issuance session | Wrong tag/mismatch/interruption prevents activation |
| 5 | Server validates pending issuance, ownership, expiry and authorized completion under person lock; activate new token and update active assignment atomically | Activation retry is idempotent; failed activation cannot leave two active assignments |
| 6 | Clear secret from UI memory; retain redacted issuance/activation audit | No localStorage, URL, console, analytics or long-lived plaintext storage |

The browser reports read-back completion; this is software workflow evidence, not cryptographic attestation of an authentic or uncloned physical tag. Server authorization alone cannot observe the RF write. Pending issuance expires after **10 minutes**, including at the exact boundary. Completion requires the issuing actor, auth revision, session digest, completion nonce, matching reference and unchanged person revision. Hiding/leaving issuance clears the receipt. The expiry command closes rows/pointers; validation already checks time without relying on that command. Do not automatically make a tag permanently read-only during development.

Lost credentials are revoked immediately, the active pointer is cleared and assignment ended, independently of replacement success. Normal replacement may keep the old credential valid until the fresh tag is ready, then atomically revoke old/activate new. A revoked token never becomes active again. Existing credential ownership never moves to another person. See [database invariants](database.md).

## Implemented scan and passage flow — Phase 5

Explicit Entry/Exit and assigned gate are fixed into the attempt. Browser reading submits to the authenticated Laravel verification service. Display authorized credential + necessary identity details, then require guard comparison and observed-passage confirmation. A failed/missing backend response is not a successful verification. Guard rejection/cancellation/expiry are audited terminal review states and create no passage.

Stop/cancel the active NFC reader when leaving the scanner or switching to write mode; avoid concurrent reader instances and stale person panels. Debounce repeated browser notifications for usability while relying on backend idempotency and transaction rules for correctness. After a timeout, recover the existing operation status before a new action. On reconnection do not silently submit an old tag or authorize passage from cached identity data.

UI error states need actionable distinctions: unsupported API/device, NFC disabled or unavailable, permission denied, tag read error, malformed/unrelated credential, unknown/revoked/inactive reference, gate not assigned, expired review, network unavailable, ambiguous timeout, and server error. Keep a visible connection indicator; connectivity hints do not replace successful server validation.

## Controlled simulator

The implemented simulator invokes **the same `CredentialVerifier`** as normal credential inspection. Middleware requires local/testing environment, loopback, explicit flag and `nfcheck_simulator`/`nfcheck_testing`; production rejects it. Routes fix the source server-side and normal verification rejects simulated references. The lab uses its own runtime account, key, cookie and synthetic people. Credential inspection records no passage; the separate Phase 5 guard workflow records labeled simulated attempts/passages only in the lab schema.

1. Run `scripts/dev.ps1 simulator`; open **http://localhost:8002/login**. Read the disposable operator credentials privately from `.tools/secrets/simulator-account.json`. Keep the main app at **127.0.0.1:8000** so the browsers' XSRF cookies use separate hosts.
2. Open NFC credentials → Issue credential. Choose a synthetic person, a unique inventory label and purpose. Prepare the pending credential.
3. Select **Simulate write**, then **Simulate matching read-back**, then **Activate simulated credential**. The raw reference is never rendered or copied.
4. Select **Check simulated tag** → **Check virtual tag**. Repeat to show checks do not create passage. Use unknown/malformed probes to exercise rejections.
5. From the eligible result open the credential, revoke as lost, return to the simulator through in-app navigation and check again: it must reject as revoked. Issue replacement uses a fresh reference and retained history.
6. Clear the virtual tag or sign out. A reload/page hide also clears the memory-only tag; returning to issuance cannot recover a lost receipt. Cancel any pending issuance before starting a fresh one.

The physical issuance adapter writes exactly one UTF-8 NDEF text record. Separate read-back accepts only one bounded text record using supported UTF-8/UTF-16 decoding and canonical reference validation. Listeners attach before `scan()`, and navigation/hiding/timeout abort operations. API presence, permission errors, malformed data and interruption are tested with mock readers, without asserting RF behavior. Text record data/encoding follow the browser NDEF record interface. [MDN NDEFRecord](https://developer.mozilla.org/en-US/docs/Web/API/NDEFRecord), [Chrome Web NFC](https://developer.chrome.com/docs/capabilities/nfc).

Unit/feature/browser tests can validate parsing, permissions UI, token lifecycle, business rules and race conditions. They cannot establish RF range, actual phone permissions behavior, physical writing or read-back reliability. Keep separate simulated and physical evidence.

## Physical acceptance checklist — not performed

Record model, Android build, Chrome version, tag type/supplier, NDEF state, app build, secure origin and connection method. Using only authorized synthetic identities:

1. Verify secure-context access (USB loopback or trusted HTTPS), login, gate assignment, NFC permission and scan start on the real phone.
2. Write a fresh pending tag, read it back, activate it, verify identity, and confirm Entry then Exit.
3. Verify unknown, malformed, revoked, replaced, locked and incompatible tags; repeated taps and two guards using the same reference.
4. Interrupt write, remove tag early, deny permission, disable NFC, lock phone, background the tab, switch apps and resume safely.
5. Interrupt network before verification and during confirmation; prove the same operation produces at most one passage after recovery.
6. Compare recorded server outcomes with guard observations; record failures and latency without claiming perfect identity assurance.

Hardware availability is unknown. Phase 6 may complete software integration with physical evidence explicitly outstanding, but physical readiness cannot be marked successful until these device/tag tests pass.

### Guard simulator handoff — Phase 5

After activation, the administrator can select **Hand virtual tag to a lab guard**. This signs out and preserves only the synthetic virtual tag in module memory for up to two minutes while the separate lab guard signs in. The first server-authorized lab check accepts the handoff; afterward the tag remains memory-only until ordinary sign-out, clear, reload or page hide. Use `.tools/secrets/simulator-guards.json` privately for guard sign-in. Select `SIM-GATE-01`, explicitly choose Entry/Exit and scan. Check the identity-review assertion before confirming. Repeated confirmations within the chosen 10-second window must not create another passage. Cancel/mismatch/expiry create none. All lab events remain labeled simulated and isolated.

A reload clears the virtual tag. It does not extend or bypass an existing review: the non-secret request UUID can locate the same guard/session-bound operation until its original deadline. Photos remain private and review-scoped. Unknown/revoked/inactive/expired credentials are denied; no identity is disclosed for denied guard attempts. This software workflow does not establish physical RF reliability.
