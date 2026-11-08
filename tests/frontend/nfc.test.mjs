import test from 'node:test';
import assert from 'node:assert/strict';
import { canonicalReference, referenceFromMessage, NdefAdapter, writeVirtualTag, readVirtualTag, clearVirtualTag, unknownReference } from '../../resources/js/lib/nfc.ts';

const payload = 'nfcheck:v1:' + 'A'.repeat(43);
const textRecord = (text, encoding = 'utf-8') => ({ recordType: 'text', encoding, data: new DataView(new TextEncoder().encode(text).buffer) });

test('only exact canonical 256-bit references are accepted', () => {
    assert.equal(canonicalReference(payload), true);
    for (const value of [payload + '=', payload + '\n', payload.replace(/A$/, 'B'), payload.replace(':v1:', ':v2:'), 'https://example.invalid']) assert.equal(canonicalReference(value), false);
    assert.equal(canonicalReference(unknownReference()), true);
});
test('NDEF parsing rejects URLs, extra records, invalid encoding and oversized data', () => {
    assert.equal(referenceFromMessage([textRecord(payload)]), payload);
    for (const records of [[], [textRecord(payload), textRecord(payload)], [{ recordType: 'url', data: textRecord(payload).data }], [textRecord('https://example.invalid')], [textRecord(payload, 'latin1')], [textRecord('A'.repeat(129))], [{ recordType: 'text', data: new DataView(Uint8Array.from([0xff]).buffer) }]]) assert.throws(() => referenceFromMessage(records));
});
test('NDEF write uses one text record and the caller abort signal', async () => {
    let captured;
    const signal = new AbortController().signal;
    const adapter = new NdefAdapter(() => ({ write: async (...args) => { captured = args; } }));
    await adapter.write(payload, signal);
    assert.deepEqual(captured[0], { records: [{ recordType: 'text', data: payload, encoding: 'utf-8', lang: 'en' }] });
    assert.equal(captured[1].signal, signal);
    await assert.rejects(adapter.write('invalid', signal));
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
    reader.scan = async () => { throw new Error('permission'); };
    await assert.rejects(adapter.read(new AbortController().signal), /permission or hardware/);
});
test('virtual tag is memory-only and explicitly clearable', () => {
    clearVirtualTag(); assert.equal(readVirtualTag(), null);
    writeVirtualTag(payload, 'TEST-FIXTURE'); assert.equal(readVirtualTag(), payload);
    clearVirtualTag(); assert.equal(readVirtualTag(), null);
});
