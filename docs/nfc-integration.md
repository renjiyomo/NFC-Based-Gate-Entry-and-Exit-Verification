# NFC integration and credential lifecycle proposal

Status: **design and official capability research only**. No NFC code or physical tag test has been performed.

## Supported target and limits

Target NFC Forum Type 2 NTAG215 coins carrying NDEF, read/written using `NDEFReader` on compatible Android Chrome devices over trusted HTTPS. NXP identifies NTAG215 as a Type 2 tag with 504 bytes of user memory; the proposed small reference payload fits without personal information. Tag quality, formatting, locked memory and antenna placement still need physical verification. [NXP NTAG21x product specifications](https://www.nxp.com/products/NTAG213_215_216)

Chrome's Web NFC implementation is NDEF-only: no low-level ISO-DEP, peer-to-peer mode or host card emulation. It needs a top-level secure context, user gesture/permission and a compatible enabled NFC device. Scanning is affected by page visibility and device lock state. Detecting `NDEFReader` establishes API presence, not working NFC hardware. Desktop/iOS/other-browser support is not assumed. [Chrome Web NFC documentation](https://developer.chrome.com/docs/capabilities/nfc)

Do not purchase a reader or phone merely because it advertises NFC. First establish model, Android/Chrome versions, NFC read/write support, NDEF tag compatibility and trusted HTTPS access to the application. A generic USB NFC reader is not automatically usable by Web NFC. This integration does not implement a native smart-card challenge-response protocol.

## Proposed versioned NDEF payload

One NDEF **text** record containing `nfcheck:v1:<base64url-secret>`. Generate exactly 32 cryptographically random bytes in PHP; canonical unpadded base64url yields 43 characters. The text is 54 ASCII bytes before NDEF/text overhead. The placeholder here is a format description, not a live credential.

Both layers validate record type, prefix/version, encoding, exact decoded secret length, canonical encoding and bounded record/message sizes. Reject unrelated, ambiguous multiple credential records, malformed and oversized data. Never navigate a URL read from a tag or trust arbitrary text as instructions. Backend validation remains authoritative even if the client parsed successfully.

Only the random reference is written. No name, campus number, photo, authorization status, API secret or password goes on the tag. Use a unique binary SHA-256 digest of the secret for lookup; never use hardware UID as secure authentication. Tag read-only/password features do not turn a copied NDEF token into proof of identity. Do not claim anti-cloning guarantees.

## Issuance, activation and replacement

| Step | Planned operation | Failure behavior |
| --- | --- | --- |
| 1 | Authorized administrator selects a valid person; server generates pending credential and permanent assignment | Validation/authorization failure produces no usable token |
| 2 | Plaintext is returned once into the issuance page's transient memory with no-store responses | Lost response/reload requires cancellation and new issuance; no plaintext recovery endpoint |
| 3 | Deliberate write action requests NFC permission and writes the NDEF text | Permission denied, abort, incompatible/locked tag or network loss leaves credential unusable |
| 4 | Separate read-back compares the exact expected secret in the same issuance session | Wrong tag/mismatch/interruption prevents activation |
| 5 | Server validates pending issuance, ownership, expiry and authorized completion under person lock; activate new token and update active assignment atomically | Activation retry is idempotent; failed activation cannot leave two active assignments |
| 6 | Clear secret from UI memory; retain redacted issuance/activation audit | No localStorage, URL, console, analytics or long-lived plaintext storage |

The browser reports read-back completion; this is software workflow evidence, not cryptographic attestation of an authentic or uncloned physical tag. Server authorization alone cannot observe the RF write. Pending issuance expires; exact issuance expiry will be chosen and tested in Phase 4. Do not automatically make a tag permanently read-only during development.

Lost credentials are revoked immediately, the active pointer is cleared and assignment ended, independently of replacement success. Normal replacement may keep the old credential valid until the fresh tag is ready, then atomically revoke old/activate new. A revoked token never becomes active again. Existing credential ownership never moves to another person. See [database invariants](database.md).

## Scan and passage flow

Explicit Entry/Exit and assigned gate are fixed into the attempt. Browser reading submits to the authenticated Laravel verification service. Display authorized credential + necessary identity details, then require guard comparison and observed-passage confirmation. A failed/missing backend response is not a successful verification. Guard rejection/cancellation/expiry are audited terminal review states and create no passage.

Stop/cancel the active NFC reader when leaving the scanner or switching to write mode; avoid concurrent reader instances and stale person panels. Debounce repeated browser notifications for usability while relying on backend idempotency and transaction rules for correctness. After a timeout, recover the existing operation status before a new action. On reconnection do not silently submit an old tag or authorize passage from cached identity data.

UI error states need actionable distinctions: unsupported API/device, NFC disabled or unavailable, permission denied, tag read error, malformed/unrelated credential, unknown/revoked/inactive reference, gate not assigned, expired review, network unavailable, ambiguous timeout, and server error. Keep a visible connection indicator; connectivity hints do not replace successful server validation.

## Controlled simulator

Phase 4 introduces a development-only transport that invokes the **same backend verification action** as NFC. Server restrictions must require local/test environment plus explicit simulator enablement, and production must reject simulator routes/source flags. Label the page and stored source as simulated; exclude simulated data from operational reports by default. A client cannot choose to bypass authorization by changing its source flag.

Unit/feature/browser tests can validate parsing, permissions UI, token lifecycle, business rules and race conditions. They cannot establish RF range, actual phone permissions behavior, physical writing or read-back reliability. Keep separate simulated and physical evidence.

## Physical acceptance checklist — not performed

Record model, Android build, Chrome version, tag type/supplier, NDEF state, app build and HTTPS origin. Using only authorized synthetic identities:

1. Verify certificate trust, login, gate assignment, NFC permission and scan start on the real phone.
2. Write a fresh pending tag, read it back, activate it, verify identity, and confirm Entry then Exit.
3. Verify unknown, malformed, revoked, replaced, locked and incompatible tags; repeated taps and two guards using the same reference.
4. Interrupt write, remove tag early, deny permission, disable NFC, lock phone, background the tab, switch apps and resume safely.
5. Interrupt network before verification and during confirmation; prove the same operation produces at most one passage after recovery.
6. Compare recorded server outcomes with guard observations; record failures and latency without claiming perfect identity assurance.

Hardware availability is unknown. Phase 6 may complete software integration with physical evidence explicitly outstanding, but physical readiness cannot be marked successful until these device/tag tests pass.
