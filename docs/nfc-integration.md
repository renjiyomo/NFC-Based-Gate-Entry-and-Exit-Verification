# NFC and browser support

The scanner uses the browser NDEFReader API. Supported physical testing uses NFC-enabled Android Chrome, NTAG215 NDEF tags and a secure context. Use trusted HTTPS for an online site or authorized USB reverse forwarding for phone access to laptop localhost. Ordinary LAN HTTP is not a secure deployment.

Safari/iOS does not implement the Web NFC API. Installing the site as a PWA does not add that API. Non-NFC pages are designed to work responsively on modern browsers, but actual iOS device acceptance remains untested. Native iOS NFC would require a separate native integration, not a CSS or JavaScript compatibility flag.

Sources: [Chrome Web NFC](https://developer.chrome.com/docs/capabilities/nfc), [MDN NDEFReader compatibility](https://github.com/mdn/browser-compat-data/blob/main/api/NDEFReader.json).

## Gate reader

Start Scanner grants/starts one session. Valid coin reads record the server-detected action without per-person confirmation. Ready resumes after acknowledgement. The UI suppresses repeated read events; the backend independently enforces the ten-second duplicate window.

Keep the page visible and phone unlocked. Backgrounding clears the previous identity and stops scanning without automatic restart. After connection uncertainty, recover the existing request; retry its ID only when offered. Never submit a fresh operation to replace an uncertain saved scan.

Web NFC has no removal event. Remove each coin after the result. A quiet-gap heuristic does not prove physical removal, identity or movement. NFC references can be cloned and are not strong holder authentication.

## Coin handling

Check a coin before issuance. Active/pending registrations are protected from overwrite. Unknown references from another workspace are not automatically transferable. Matching read-back is separate from writing. Do not permanently lock, erase or password-guess existing coins during normal administration.

Existing labels remain unchanged when automatic numbering is introduced. The new label is inventory metadata; changing it does not require writing an existing coin. Each new issuance still has a separate random NFC reference.

Original phone access uses localhost:8000 when a verified USB reverse mapping is configured. The separate hardware workspace uses port 8003 and different records. File Transfer alone is not USB debugging authorization. Keep the intended workspace and registered coins consistent.
