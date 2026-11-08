import test from 'node:test';
import assert from 'node:assert/strict';
import { canonicalReference, referenceFromMessage, tagSnapshot, NdefAdapter, NfcError, ReadBackConfirmation, nfcCapabilities, writeVirtualTag, readVirtualTag, clearVirtualTag, unknownReference, holdVirtualTagForHandoff, acceptVirtualTagHandoff } from '../../resources/js/lib/nfc.ts';
import { getEventListeners } from 'node:events';

const payload = 'nfcheck:v1:' + 'A'.repeat(43);
const textRecord = (text, encoding = 'utf-8') => ({ recordType: 'text', encoding, data: new DataView(new TextEncoder().encode(text).buffer) });
const emptyDestination = { kind: 'empty', reference: null, serial: 'fixture-coin' };
function reading(reader, records = [], serial = 'fixture-coin') {
    const event = new Event('reading'); event.message = { records }; event.serialNumber = serial; reader.dispatchEvent(event);
}
function writerReader(write) {
    const reader = new EventTarget(); reader.scan = async () => reading(reader); reader.write = write; return reader;
}

test('only exact canonical 256-bit references are accepted', () => {
    assert.equal(canonicalReference(payload), true);
    for (const value of [payload + '=', payload + '\n', payload.replace(/A$/, 'B'), payload.replace(':v1:', ':v2:'), 'https://example.invalid']) assert.equal(canonicalReference(value), false);
    assert.equal(canonicalReference(unknownReference()), true);
});
test('NDEF parsing rejects URLs, extra records, invalid encoding and oversized data', () => {
    assert.equal(referenceFromMessage([textRecord(payload)]), payload);
    for (const records of [[], [textRecord(payload), textRecord(payload)], [{ recordType: 'url', data: textRecord(payload).data }], [textRecord('https://example.invalid')], [textRecord(payload, 'latin1')], [textRecord('A'.repeat(129))], [{ recordType: 'text', data: new DataView(Uint8Array.from([0xff]).buffer) }]]) assert.throws(() => referenceFromMessage(records));
});
test('NDEF write uses one text record and releases its owned radio signal', async () => {
    let captured;
    const signal = new AbortController().signal;
    const adapter = new NdefAdapter(() => writerReader(async (...args) => { captured = args; }));
    await adapter.write(payload, signal, emptyDestination);
    assert.deepEqual(captured[0], { records: [{ recordType: 'text', data: payload, encoding: 'utf-8', lang: 'en' }] });
    assert.equal(captured[1].overwrite, true);
    assert.equal(captured[1].signal.aborted, true);
    assert.equal(signal.aborted, false);
    await assert.rejects(adapter.write('invalid', signal, emptyDestination));
});
test('separate read-back resolves one matching text record', async () => {
    const reader = new EventTarget();
    reader.scan = async () => { const event = new Event('reading'); event.message = { records: [textRecord(payload)] }; reader.dispatchEvent(event); };
    const adapter = new NdefAdapter(() => reader);
    assert.equal(await adapter.read(new AbortController().signal), payload);
});
test('aborted reads and permission failures reject instead of hanging', async () => {
    const reader = new EventTarget(); reader.scan = async () => {};
    const adapter = new NdefAdapter(() => reader);
    const controller = new AbortController();
    const read = adapter.read(controller.signal); controller.abort(); await assert.rejects(read);
    await assert.rejects(adapter.read(controller.signal));
    reader.scan = async () => { throw new DOMException('Native diagnostic must not be shown', 'NotAllowedError'); };
    await assert.rejects(adapter.read(new AbortController().signal), { code: 'permission' });
});

test('first read stops radio immediately and removes all listeners', async () => {
    const reader = new EventTarget(); let nativeSignal;
    reader.scan = ({ signal }) => { nativeSignal = signal; return Promise.resolve(); };
    const external = new AbortController();
    const read = new NdefAdapter(() => reader).read(external.signal);
    const event = new Event('reading'); event.message = { records: [textRecord(payload)] };
    reader.dispatchEvent(event);
    assert.equal(nativeSignal.aborted, true);
    assert.equal(getEventListeners(reader, 'reading').length, 0);
    assert.equal(getEventListeners(reader, 'readingerror').length, 0);
    assert.equal(getEventListeners(external.signal, 'abort').length, 0);
    reader.dispatchEvent(new Event('readingerror'));
    assert.equal(await read, payload);
});

test('timeout ends a native read even when the native promise ignores abort', async () => {
    const reader = new EventTarget(); let nativeSignal;
    reader.scan = ({ signal }) => { nativeSignal = signal; return new Promise(() => {}); };
    await assert.rejects(new NdefAdapter(() => reader, { timeoutMs: 5 }).read(new AbortController().signal), { code: 'timeout' });
    assert.equal(nativeSignal.aborted, true);
    assert.equal(getEventListeners(reader, 'reading').length, 0);
});

test('external cancellation stops a pending write and pre-aborted calls never start native NFC', async () => {
    let calls = 0; let nativeSignal;
    const adapter = new NdefAdapter(() => writerReader(({}, { signal }) => { calls++; nativeSignal = signal; return new Promise(() => {}); }));
    const controller = new AbortController();
    const write = adapter.write(payload, controller.signal, emptyDestination);
    assert.equal(calls, 1); // Starts inside the original gesture, before any await.
    controller.abort();
    await assert.rejects(write, { code: 'aborted' });
    assert.equal(nativeSignal.aborted, true);
    await assert.rejects(adapter.write(payload, controller.signal, emptyDestination), { code: 'aborted' });
    assert.equal(calls, 1);
});

test('radio lease rejects concurrent adapters and is released after cancellation', async () => {
    const reader = new EventTarget(); reader.scan = async () => {};
    const controller = new AbortController();
    const read = new NdefAdapter(() => reader).read(controller.signal);
    let otherStarted = false;
    const other = new NdefAdapter(() => { otherStarted = true; return reader; });
    await assert.rejects(other.probe(new AbortController().signal), { code: 'busy' });
    assert.equal(otherStarted, false);
    controller.abort(); await assert.rejects(read, { code: 'aborted' });
    await other.probe(new AbortController().signal);
    assert.equal(otherStarted, true);
});

test('access probe stops scanning and never consumes tag contents', async () => {
    const reader = new EventTarget(); let nativeSignal;
    reader.scan = ({ signal }) => { nativeSignal = signal; return Promise.resolve(); };
    await new NdefAdapter(() => reader).probe(new AbortController().signal);
    assert.equal(nativeSignal.aborted, true);
    assert.equal(getEventListeners(reader, 'reading').length, 0);
});

test('native error details are redacted and failure categories remain distinct', async () => {
    for (const [name, code] of [['NotAllowedError', 'permission'], ['NotReadableError', 'unavailable'], ['NotSupportedError', 'incompatible'], ['InvalidStateError', 'busy'], ['NetworkError', 'write_failed']]) {
        const adapter = new NdefAdapter(() => writerReader(async () => { throw new DOMException(payload, name); }));
        await assert.rejects(adapter.write(payload, new AbortController().signal, emptyDestination), (error) => error instanceof NfcError && error.code === code && !error.message.includes(payload));
    }
});

test('decoder respects DataView offsets and valid UTF-16 while rejecting malformed UTF-8', () => {
    const bytes = new Uint8Array(60); bytes.set(new TextEncoder().encode(payload), 3);
    assert.equal(referenceFromMessage([{ recordType: 'text', data: new DataView(bytes.buffer, 3, payload.length) }]), payload);
    const utf16 = new Uint8Array(payload.length * 2); for (let i = 0; i < payload.length; i++) utf16[i * 2] = payload.charCodeAt(i);
    assert.equal(referenceFromMessage([{ recordType: 'text', encoding: 'utf-16le', data: new DataView(utf16.buffer) }]), payload);
    assert.throws(() => referenceFromMessage([{ recordType: 'text', data: new DataView(Uint8Array.from([0xc3, 0x28]).buffer) }]), { code: 'invalid_record' });
});

test('capability detection distinguishes insecure, embedded and unsupported contexts', async () => {
    const previousWindow = globalThis.window; const previousDocument = globalThis.document;
    try {
        for (const [secure, topLevel, api, visible, code] of [[false, true, true, true, 'insecure'], [true, false, true, true, 'embedded'], [true, true, false, true, 'unsupported'], [true, true, true, false, 'hidden']]) {
            const context = { isSecureContext: secure, NDEFReader: api ? function() {} : undefined }; context.self = context; context.top = topLevel ? context : {};
            globalThis.window = context; globalThis.document = { visibilityState: visible ? 'visible' : 'hidden' };
            assert.deepEqual(nfcCapabilities(), { secure, topLevel, api, visible });
            await assert.rejects(new NdefAdapter().probe(new AbortController().signal), { code });
        }
    } finally {
        if (previousWindow === undefined) delete globalThis.window; else globalThis.window = previousWindow;
        if (previousDocument === undefined) delete globalThis.document; else globalThis.document = previousDocument;
    }
});

test('read-back passes the actual native reference to the server and requires its acknowledgement', async () => {
    const reader = new EventTarget();
    reader.scan = async () => { const event = new Event('reading'); event.message = { records: [textRecord(payload)] }; reader.dispatchEvent(event); };
    const confirmation = new ReadBackConfirmation(); let saved = false;
    await confirmation.confirm(payload, () => new NdefAdapter(() => reader).read(new AbortController().signal), async (scanned) => {
        saved = scanned === payload;
        assert.equal(confirmation.verified, false);
        return { read_back_at: '2026-11-09T00:00:00Z' };
    }, new AbortController().signal);
    assert.equal(saved, true);
    assert.equal(confirmation.verified, true);
});

test('coin inspection distinguishes empty, NFCheck and unrelated contents without writing', async () => {
    assert.equal(tagSnapshot([{recordType:'empty'}]).kind, 'empty');
    assert.equal(tagSnapshot([textRecord(payload)]).kind, 'reference');
    assert.equal(tagSnapshot([textRecord('unrelated fixture')]).kind, 'other');
    const reader = new EventTarget(); reader.scan = async () => reading(reader, [textRecord(payload)]);
    reader.write = () => { throw new Error('Inspection must not write'); };
    const snapshot = await new NdefAdapter(() => reader).inspect(new AbortController().signal);
    assert.equal(snapshot.reference === payload, true);
    assert.equal(snapshot.serial, 'fixture-coin');
});

test('guarded write refuses a registered reference when an empty coin was checked', async () => {
    const reader = new EventTarget(); let writes = 0;
    reader.scan = async () => reading(reader, [textRecord('nfcheck:v1:' + Buffer.alloc(32, 1).toString('base64url'))]);
    reader.write = async () => { writes++; };
    await assert.rejects(new NdefAdapter(() => reader).write(payload, new AbortController().signal, emptyDestination), {code:'wrong_destination'});
    assert.equal(writes, 0);
    assert.equal(getEventListeners(reader, 'reading').length, 0);
});

test('guarded write checks the actual destination again and writes once from its reading event', async () => {
    const retired = 'nfcheck:v1:' + Buffer.alloc(32, 2).toString('base64url');
    const checked = {kind:'reference',reference:retired,serial:'fixture-coin'};
    const reader = new EventTarget(); let writes = 0;
    reader.scan = async () => { reading(reader, [textRecord(retired)]); reading(reader, [textRecord(retired)]); };
    reader.write = async (message) => { writes++; assert.equal(message.records[0].data === payload, true); };
    await new NdefAdapter(() => reader).write(payload, new AbortController().signal, checked);
    assert.equal(writes, 1);
    reader.scan = async () => reading(reader, [textRecord(retired)], 'different-coin');
    await assert.rejects(new NdefAdapter(() => reader).write(payload, new AbortController().signal, checked), {code:'wrong_destination'});
    assert.equal(writes, 1);
});

test('an interrupted write can retry its own pending reference only on the checked coin', async () => {
    const reader = new EventTarget(); let writes = 0;
    reader.scan = async () => reading(reader, [textRecord(payload)]);
    reader.write = async () => { writes++; };
    await new NdefAdapter(() => reader).write(payload, new AbortController().signal, emptyDestination);
    assert.equal(writes, 1);
    reader.scan = async () => reading(reader, [textRecord(payload)], 'different-coin');
    await assert.rejects(new NdefAdapter(() => reader).write(payload, new AbortController().signal, emptyDestination), {code:'wrong_destination'});
    assert.equal(writes, 1);
});

test('wrong valid native tag blocks saving and clears a previous successful read-back', async () => {
    const confirmation = new ReadBackConfirmation(); const controller = new AbortController();
    await confirmation.confirm(payload, () => payload, async () => ({ read_back_at: '2026-11-09T00:00:00Z' }), controller.signal);
    assert.equal(confirmation.verified, true);
    const other = 'nfcheck:v1:' + Buffer.alloc(32, 1).toString('base64url');
    const reader = new EventTarget();
    reader.scan = async () => { const event = new Event('reading'); event.message = { records: [textRecord(other)] }; reader.dispatchEvent(event); };
    let saved = false;
    await assert.rejects(confirmation.confirm(payload, () => new NdefAdapter(() => reader).read(controller.signal), async () => { saved = true; return { read_back_at: 'old' }; }, controller.signal), { code: 'mismatch' });
    assert.equal(saved, false);
    assert.equal(confirmation.verified, false);
});

test('read errors, missing server acknowledgement and server rejection never retain old confirmation', async () => {
    const confirmation = new ReadBackConfirmation(); const signal = new AbortController().signal;
    for (const failure of ['read', 'server', 'missing']) {
        await confirmation.confirm(payload, () => payload, async () => ({ read_back_at: 'old' }), signal);
        await assert.rejects(confirmation.confirm(payload,
            () => { if (failure === 'read') throw new NfcError('read_failed'); return payload; },
            async () => { if (failure === 'server') throw new Error('Server rejected fixture'); return { read_back_at: null }; }, signal));
        assert.equal(confirmation.verified, false);
    }
});

test('a new write or a hidden page invalidates confirmation and late server success cannot restore it', async () => {
    const confirmation = new ReadBackConfirmation(); let finish; let started;
    const saving = new Promise((resolve) => { started = resolve; });
    const read = confirmation.confirm(payload, () => payload, () => { started(); return new Promise((resolve) => { finish = resolve; }); }, new AbortController().signal);
    await saving;
    confirmation.invalidate();
    finish({ read_back_at: '2026-11-09T00:00:00Z' });
    await assert.rejects(read, { code: 'aborted' });
    assert.equal(confirmation.verified, false);
});

test('cancellation between native reading and saving prevents server submission', async () => {
    const controller = new AbortController(); const confirmation = new ReadBackConfirmation(); let saved = false;
    await assert.rejects(confirmation.confirm(payload, () => { controller.abort(); return payload; }, async () => { saved = true; return { read_back_at: 'old' }; }, controller.signal), { code: 'aborted' });
    assert.equal(saved, false);
    assert.equal(confirmation.verified, false);
});
test('virtual tag is memory-only and explicitly clearable', () => {
    clearVirtualTag(); assert.equal(readVirtualTag(), null);
    writeVirtualTag(payload, 'TEST-FIXTURE'); assert.equal(readVirtualTag(), payload);
    clearVirtualTag(); assert.equal(readVirtualTag(), null);
});
test('explicit lab handoff expires after two minutes without persistent storage', () => {
    const original = Date.now;
    try {
        Date.now = () => 1000;
        writeVirtualTag(payload, 'SYNTHETIC-HANDOFF'); holdVirtualTagForHandoff();
        Date.now = () => 120999; assert.equal(readVirtualTag(), payload);
        Date.now = () => 121000; assert.equal(readVirtualTag(), null);
    } finally { Date.now = original; clearVirtualTag(); }
});
test('received lab tag stays in memory until ordinary clearing after handoff acceptance', () => {
    const original = Date.now;
    try {
        Date.now = () => 1000; writeVirtualTag(payload, 'SYNTHETIC-RECEIVED'); holdVirtualTagForHandoff();
        Date.now = () => 2000; acceptVirtualTagHandoff();
        Date.now = () => 300000; assert.equal(readVirtualTag(), payload);
        clearVirtualTag(); assert.equal(readVirtualTag(), null);
    } finally { Date.now = original; clearVirtualTag(); }
});
