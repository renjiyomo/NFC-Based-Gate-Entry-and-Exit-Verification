// NDEF adapter only. Hardware UID and physical passage are never used as evidence.
export interface NfcRecord {
    recordType: string;
    encoding?: string;
    data?: DataView;
}
interface NfcReading extends Event {
    message: { records: NfcRecord[] };
}
export interface NfcReader extends EventTarget {
    scan(options: { signal: AbortSignal }): Promise<void>;
    write(
        message: {
            records: { recordType: string; data: string; encoding: string; lang: string }[];
        },
        options: { signal: AbortSignal; overwrite: boolean },
    ): Promise<void>;
}
type ReaderConstructor = new () => NfcReader;
export function canonicalReference(value: string): boolean {
    if (!/^nfcheck:v1:[A-Za-z0-9_-]{43}$/.test(value)) return false;
    const encoded = value.slice(11);
    try {
        const bytes = atob(encoded.replace(/-/g, '+').replace(/_/g, '/') + '=');
        return (
            bytes.length === 32 &&
            btoa(bytes).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '') === encoded
        );
    } catch {
        return false;
    }
}
export function referenceFromMessage(records: NfcRecord[]): string {
    if (records.length !== 1 || records[0].recordType !== 'text')
        throw new Error('Use a tag containing exactly one NFCheck text record.');
    const record = records[0];
    const encoding = (record.encoding ?? 'utf-8').toLowerCase();
    if (
        !['utf-8', 'utf-16', 'utf-16le', 'utf-16be'].includes(encoding) ||
        !record.data ||
        record.data.byteLength > 128
    )
        throw new Error('The tag text encoding or length is unsupported.');
    let text: string;
    try {
        text = new TextDecoder(encoding, { fatal: true }).decode(record.data);
    } catch {
        throw new Error('The tag text could not be decoded.');
    }
    if (!canonicalReference(text))
        throw new Error('The tag does not contain a valid NFCheck reference.');
    return text;
}
export function nfcAvailable(): boolean {
    return (
        typeof window !== 'undefined' &&
        window.isSecureContext &&
        window.top === window.self &&
        'NDEFReader' in window
    );
}
export class NdefAdapter {
    private create: () => NfcReader;
    constructor(create?: () => NfcReader) {
        this.create =
            create ??
            (() => {
                if (!nfcAvailable())
                    throw new Error(
                        'Web NFC is unavailable. Use a supported secure, top-level browser with NFC hardware.',
                    );
                const Reader = (window as unknown as { NDEFReader: ReaderConstructor }).NDEFReader;
                return new Reader();
            });
    }
    async write(payload: string, signal: AbortSignal): Promise<void> {
        if (!canonicalReference(payload))
            throw new Error('The issuance reference is unavailable. Start a new issuance.');
        await this.create().write(
            { records: [{ recordType: 'text', data: payload, encoding: 'utf-8', lang: 'en' }] },
            { signal, overwrite: true },
        );
    }
    read(signal: AbortSignal): Promise<string> {
        const reader = this.create();
        return new Promise((resolve, reject) => {
            const dispose = () => {
                reader.removeEventListener('reading', reading);
                reader.removeEventListener('readingerror', failed);
                signal.removeEventListener('abort', aborted);
            };
            const failed = () => {
                dispose();
                reject(new Error('The tag could not be read. Remove it, then try again.'));
            };
            const aborted = () => {
                dispose();
                reject(new Error('NFC operation ended. Tap the button to try again.'));
            };
            const reading = (event: Event) => {
                try {
                    const value = referenceFromMessage((event as NfcReading).message.records);
                    dispose();
                    resolve(value);
                } catch (error) {
                    dispose();
                    reject(error);
                }
            };
            if (signal.aborted) {
                aborted();
                return;
            }
            reader.addEventListener('reading', reading);
            reader.addEventListener('readingerror', failed);
            signal.addEventListener('abort', aborted, { once: true });
            reader.scan({ signal }).catch(() => {
                dispose();
                reject(
                    new Error(
                        'NFC permission or hardware is unavailable. Check the device and tap again.',
                    ),
                );
            });
        });
    }
}
// A virtual tag is held in module memory, just like the contents of a tag in the lab.
// It survives Inertia navigation only; page hide, reload and logout clear it.
let virtualTag: { payload: string; label: string } | null = null;
export function writeVirtualTag(payload: string, label: string): void {
    if (!canonicalReference(payload)) throw new Error('Invalid virtual tag.');
    virtualTag = { payload, label };
}
export function readVirtualTag(): string | null {
    return virtualTag?.payload ?? null;
}
export function virtualTagLabel(): string | null {
    return virtualTag?.label ?? null;
}
export function clearVirtualTag(): void {
    virtualTag = null;
}
if (typeof window !== 'undefined') window.addEventListener('pagehide', clearVirtualTag);
export function unknownReference(): string {
    const bytes = crypto.getRandomValues(new Uint8Array(32));
    return (
        'nfcheck:v1:' +
        btoa(String.fromCharCode(...bytes))
            .replace(/\+/g, '-')
            .replace(/\//g, '_')
            .replace(/=+$/, '')
    );
}
