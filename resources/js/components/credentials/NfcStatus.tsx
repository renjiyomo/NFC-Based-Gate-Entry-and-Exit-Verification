import { Link } from '@inertiajs/react';
import { nfcCapabilities } from '../../lib/nfc';

export default function NfcStatus() {
    const c = nfcCapabilities();
    const message = !c.secure
        ? 'A secure connection is required: use USB localhost or trusted HTTPS.'
        : !c.topLevel
          ? 'Open NFCheck directly in its own Chrome tab.'
          : !c.api
            ? 'Web NFC is unavailable here. Open this workspace in Chrome on the Android NFC phone.'
            : 'Web NFC API detected. Allow NFC when prompted; keep the phone unlocked with Chrome visible.';
    return (
        <p className="credential-notice">
            {message} <Link href="/nfc/setup">NFC setup and diagnostics</Link>
        </p>
    );
}
