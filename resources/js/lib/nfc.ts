// NDEF only: UID, card payment protocols and physical identity are never trusted.
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
export type NfcErrorCode =
    | 'insecure'
    | 'embedded'
    | 'unsupported'
    | 'hidden'
    | 'busy'
    | 'permission'
    | 'unavailable'
    | 'incompatible'
    | 'read_failed'
    | 'write_failed'
    | 'invalid_record'
    | 'timeout'
    | 'aborted';
const messages: Record<NfcErrorCode, string> = {
    insecure:
        'Open NFCheck through trusted HTTPS or the local USB connection. Ordinary Wi-Fi HTTP cannot use Web NFC.',
    embedded: 'Open NFCheck directly in a Chrome tab. NFC is unavailable inside an embedded page.',
    unsupported:
        'Web NFC is unavailable in this browser. Use an updated Chrome browser on your NFC-enabled Android phone.',
    hidden: 'Keep Chrome visible and the phone unlocked, then tap the NFC button again.',
    busy: 'Another NFC operation is active. Finish or cancel it before starting another.',
    permission:
        'NFC permission was not granted. Allow NFC in this site’s Chrome permissions, then tap again.',
    unavailable:
        'The NFC radio is unavailable. Turn on NFC, unlock the phone and keep Chrome visible, then tap again.',
    incompatible:
        'This device or tag cannot complete the NDEF operation. Use a compatible, writable NTAG215 tag.',
    read_failed:
        'The tag could not be read. Remove it, check its position near the phone’s NFC antenna, then retry.',
    write_failed:
        'The write did not finish. The tag may be locked, incompatible or removed too soon. Keep issuance pending and retry the intended tag.',
    invalid_record:
        'Use a tag containing exactly one correctly encoded NFCheck text record. Unrelated, malformed or oversized records are rejected.',
    timeout:
        'No NFC operation completed within 30 seconds. Remove the tag and tap the button to retry.',
    aborted: 'NFC operation cancelled. Tap the button again when you are ready.',
};
export class NfcError extends Error {
    readonly code: NfcErrorCode;
    constructor(code: NfcErrorCode) {
        super(messages[code]);
        this.name = 'NfcError';
        this.code = code;
    }
}
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
        throw new NfcError('invalid_record');
    const record = records[0];
    const encoding = (record.encoding ?? 'utf-8').toLowerCase();
    if (
        !['utf-8', 'utf-16', 'utf-16le', 'utf-16be'].includes(encoding) ||
        !record.data ||
        record.data.byteLength > 128
    )
        throw new NfcError('invalid_record');
    let text: string;
    try {
        text = new TextDecoder(encoding, { fatal: true }).decode(record.data);
    } catch {
        throw new NfcError('invalid_record');
    }
    if (!canonicalReference(text)) throw new NfcError('invalid_record');
    return text;
}
export function nfcCapabilities() {
    return {
        secure: typeof window !== 'undefined' && window.isSecureContext,
        topLevel: typeof window !== 'undefined' && window.top === window.self,
        api:
            typeof window !== 'undefined' &&
            typeof (window as unknown as { NDEFReader?: unknown }).NDEFReader === 'function',
        visible: typeof document !== 'undefined' && document.visibilityState === 'visible',
    };
}
export function nfcAvailable(): boolean {
    const c = nfcCapabilities();
    return c.secure && c.topLevel && c.api;
}
function nativeError(error: unknown, mode: string): NfcError {
    if (error instanceof NfcError) return error;
    const name = error && typeof error === 'object' && 'name' in error ? String(error.name) : '';
    return new NfcError(
        name === 'NotAllowedError' || name === 'SecurityError'
            ? 'permission'
            : name === 'NotReadableError'
              ? 'unavailable'
              : name === 'NotSupportedError'
                ? 'incompatible'
                : name === 'InvalidStateError'
                  ? 'busy'
                  : name === 'AbortError'
                    ? 'aborted'
                    : name === 'TimeoutError'
                      ? 'timeout'
                      : mode === 'write'
                        ? 'write_failed'
                        : 'read_failed',
    );
}
let radioOwner: AbortController | null = null;
export class NdefAdapter {
    private create: () => NfcReader;
    private timeoutMs: number;
    constructor(create?: () => NfcReader, options: { timeoutMs?: number } = {}) {
        this.timeoutMs = options.timeoutMs ?? 30000;
        if (!Number.isInteger(this.timeoutMs) || this.timeoutMs < 1 || this.timeoutMs > 120000)
            throw new Error('Invalid NFC timeout.');
        this.create =
            create ??
            (() => {
                const c = nfcCapabilities();
                if (!c.secure) throw new NfcError('insecure');
                if (!c.topLevel) throw new NfcError('embedded');
                if (!c.api) throw new NfcError('unsupported');
                if (!c.visible) throw new NfcError('hidden');
                const Reader = (window as unknown as { NDEFReader: ReaderConstructor }).NDEFReader;
                return new Reader();
            });
    }
    async write(payload: string, signal: AbortSignal): Promise<void> {
        if (!canonicalReference(payload)) throw new NfcError('invalid_record');
        return this.perform('write', signal, payload);
    }
    read(signal: AbortSignal): Promise<string> {
        return this.perform('read', signal);
    }
    probe(signal: AbortSignal): Promise<void> {
        return this.perform('probe', signal);
    }
    private perform(mode: 'read', external: AbortSignal, payload?: string): Promise<string>;
    private perform(
        mode: 'write' | 'probe',
        external: AbortSignal,
        payload?: string,
    ): Promise<void>;
    private perform(
        mode: 'read' | 'write' | 'probe',
        external: AbortSignal,
        payload?: string,
    ): Promise<string | void> {
        return new Promise((resolve, reject) => {
            if (radioOwner) {
                reject(new NfcError('busy'));
                return;
            }
            if (external.aborted) {
                reject(
                    new NfcError(external.reason?.name === 'TimeoutError' ? 'timeout' : 'aborted'),
                );
                return;
            }
            const internal = new AbortController();
            radioOwner = internal;
            const disposers: (() => void)[] = [];
            let settled = false;
            let timer: ReturnType<typeof setTimeout> | undefined;
            const finish = (error?: NfcError, value?: string) => {
                if (settled) return;
                settled = true;
                if (timer !== undefined) clearTimeout(timer);
                external.removeEventListener('abort', cancelled);
                for (const dispose of disposers) dispose();
                internal.abort();
                if (radioOwner === internal) radioOwner = null;
                if (error) reject(error);
                else resolve(value);
            };
            const cancelled = () =>
                finish(
                    new NfcError(external.reason?.name === 'TimeoutError' ? 'timeout' : 'aborted'),
                );
            external.addEventListener('abort', cancelled, { once: true });
            timer = setTimeout(() => finish(new NfcError('timeout')), this.timeoutMs);
            try {
                const reader = this.create();
                if (mode === 'read') {
                    const reading = (event: Event) => {
                        try {
                            finish(
                                undefined,
                                referenceFromMessage((event as NfcReading).message.records),
                            );
                        } catch (error) {
                            finish(nativeError(error, mode));
                        }
                    };
                    const failed = () => finish(new NfcError('read_failed'));
                    reader.addEventListener('reading', reading);
                    reader.addEventListener('readingerror', failed);
                    disposers.push(
                        () => reader.removeEventListener('reading', reading),
                        () => reader.removeEventListener('readingerror', failed),
                    );
                }
                // Start within the button gesture; no network or permission-query await comes first.
                const native =
                    mode === 'write'
                        ? reader.write(
                              {
                                  records: [
                                      {
                                          recordType: 'text',
                                          data: payload!,
                                          encoding: 'utf-8',
                                          lang: 'en',
                                      },
                                  ],
                              },
                              { signal: internal.signal, overwrite: true },
                          )
                        : reader.scan({ signal: internal.signal });
                Promise.resolve(native).then(
                    () => {
                        if (mode !== 'read') finish();
                    },
                    (error: unknown) => finish(nativeError(error, mode)),
                );
            } catch (error) {
                finish(nativeError(error, mode));
            }
        });
    }
}
// A virtual tag is held in module memory, just like the contents of a tag in the lab.
// Explicit lab handoff retains a simulated tag in memory for at most two minutes.
// Ordinary sign-out and page hide clear it; it never enters browser storage.
let virtualTag: { payload: string; label: string } | null = null;
let handoffExpires: number | null = null;
let handoffTimer: ReturnType<typeof setTimeout> | null = null;
export function writeVirtualTag(payload: string, label: string): void {
    if (!canonicalReference(payload)) throw new Error('Invalid virtual tag.');
    clearVirtualTag();
    virtualTag = { payload, label };
    handoffExpires = null;
}
export function readVirtualTag(): string | null {
    if (handoffExpires !== null && Date.now() >= handoffExpires) clearVirtualTag();
    return virtualTag?.payload ?? null;
}
export function virtualTagLabel(): string | null {
    if (handoffExpires !== null && Date.now() >= handoffExpires) clearVirtualTag();
    return virtualTag?.label ?? null;
}
export function clearVirtualTag(): void {
    if (handoffTimer !== null) clearTimeout(handoffTimer);
    handoffTimer = null;
    virtualTag = null;
    handoffExpires = null;
}
export function holdVirtualTagForHandoff(): void {
    if (!readVirtualTag()) throw new Error('The virtual tag is unavailable.');
    handoffExpires = Date.now() + 120000;
    if (handoffTimer !== null) clearTimeout(handoffTimer);
    handoffTimer = setTimeout(clearVirtualTag, 120000);
}
export function acceptVirtualTagHandoff(): void {
    if (!readVirtualTag()) return;
    if (handoffTimer !== null) clearTimeout(handoffTimer);
    handoffTimer = null;
    handoffExpires = null;
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
