import test from 'node:test';
import assert from 'node:assert/strict';
import {getEventListeners} from 'node:events';
import {NdefAdapter} from '../../resources/js/lib/nfc.ts';
import {ScanLatch} from '../../resources/js/lib/scanLatch.ts';
const ref = 'nfcheck:v1:' + 'A'.repeat(43);
const next = 'nfcheck:v1:' + 'B'.repeat(42) + 'A';
function reader() { const r = new EventTarget(); r.scan = async () => {}; return r; }
function emit(r, value=ref) { const e = new Event('reading'); e.message = {records:[{recordType:'text',encoding:'utf-8',data:new DataView(new TextEncoder().encode(value).buffer)}]};r.dispatchEvent(e); }

test('one scan activation delivers several people and keeps exclusive radio ownership until stop', async () => {
 const r=reader();let starts=0;let signal;r.scan=async o=>{starts++;signal=o.signal;};
 const c=new AbortController(), seen=[];
 await new NdefAdapter(()=>r).watch(c.signal,v=>seen.push(v),()=>{});
 emit(r);emit(r,next);assert.deepEqual(seen,[ref,next]);assert.equal(starts,1);
 await assert.rejects(new NdefAdapter(()=>reader()).read(new AbortController().signal),e=>e.code==='busy');
 c.abort();assert.equal(signal.aborted,true);emit(r);assert.equal(seen.length,2);
 assert.equal(getEventListeners(r,'reading').length,0);assert.equal(getEventListeners(c.signal,'abort').length,0);
});

test('malformed tag is rejected without taking down the persistent reader', async () => {
 const r=reader(),c=new AbortController(),errors=[],seen=[];
 await new NdefAdapter(()=>r).watch(c.signal,v=>seen.push(v),(e,fatal)=>errors.push([e.code,fatal]));
 emit(r,'invalid');emit(r);assert.deepEqual(errors,[['invalid_record',false]]);assert.deepEqual(seen,[ref]);c.abort();
});

test('permission denial releases the radio and a fresh explicit start can succeed', async () => {
 const r=reader();r.scan=async()=>{throw new DOMException('Denied','NotAllowedError');};
 await assert.rejects(new NdefAdapter(()=>r).watch(new AbortController().signal,()=>{},()=>{}),e=>e.code==='permission');
 assert.equal(getEventListeners(r,'reading').length,0);
 const c=new AbortController();await new NdefAdapter(()=>reader()).watch(c.signal,()=>{},()=>{});c.abort();
});

test('radio read failure stops listeners and requires an explicit new start', async () => {
 const r=reader(),c=new AbortController(),errors=[];
 await new NdefAdapter(()=>r).watch(c.signal,()=>{},(e,f)=>errors.push([e.code,f]));
 r.dispatchEvent(new Event('readingerror'));assert.deepEqual(errors,[['read_failed',true]]);
 assert.equal(getEventListeners(r,'reading').length,0);c.abort();
 const next=new AbortController();await new NdefAdapter(()=>reader()).watch(next.signal,()=>{},()=>{});next.abort();
});

test('abort during a never-resolving permission operation settles startup and removes listeners', async () => {
 const r=reader();r.scan=()=>new Promise(()=>{});const c=new AbortController();
 const p=new NdefAdapter(()=>r).watch(c.signal,()=>{},()=>{});c.abort();
 await assert.rejects(p,e=>e.code==='aborted');assert.equal(getEventListeners(r,'reading').length,0);
});

test('abort before start never touches hardware', async () => {
 const c=new AbortController();c.abort();
 await assert.rejects(new NdefAdapter(()=>{throw Error('must not create');}).watch(c.signal,()=>{},()=>{}),e=>e.code==='aborted');
});

test('an indefinitely active session is not a succession of timed one-shot reads', async () => {
 const r=reader(),c=new AbortController();let count=0;
 await new NdefAdapter(()=>r,{timeoutMs:1}).watch(c.signal,()=>count++,()=>{});
 await new Promise(resolve=>setTimeout(resolve,15));emit(r);assert.equal(count,1);c.abort();
});

test('continuous repeat events extend the quiet interval; different people do not wait', () => {
 const latch=new ScanLatch(10000);
 assert.equal(latch.accept(ref,0),true);
 assert.equal(latch.accept(ref,9000),false);
 assert.equal(latch.accept(ref,18000),false);
 assert.equal(latch.accept(ref,28000),true);
 assert.equal(latch.accept(next,28001),true);
 latch.clear();assert.equal(latch.accept(next,28002),true);
});
