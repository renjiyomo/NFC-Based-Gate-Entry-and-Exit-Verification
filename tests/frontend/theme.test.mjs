import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
import {changeTheme, currentTheme, themeOrigin} from '../../resources/js/lib/theme.ts';

function classes() {
    const set = new Set();
    return {contains: v=>set.has(v), add:(...v)=>v.forEach(x=>set.add(x)), remove:(...v)=>v.forEach(x=>set.delete(x)), toggle(v,on) { if(on) set.add(v); else set.delete(v); }};
}
function environment(t, reduced=false) {
    const saved = ['window','document','localStorage'].map(k=>[k,Object.getOwnPropertyDescriptor(globalThis,k)]);
    t.after(()=>saved.forEach(([k,d])=>d?Object.defineProperty(globalThis,k,d):delete globalThis[k]));
    const root = {classList:classes(),clientWidth:1000,style:{setProperty(){}}};
    const meta = new Map(), waves = new Set();
    globalThis.document = {documentElement:root,querySelector:()=>({setAttribute:(k,v)=>meta.set(k,v)}),body:{append:w=>waves.add(w)},createElement:()=>({className:'',setAttribute(){},remove(){waves.delete(this);}})};
    globalThis.window = Object.assign(new EventTarget(), {innerHeight:700,matchMedia:()=>({matches:reduced}),setTimeout:fn=>setImmediate(fn)});
    const store = new Map();
    globalThis.localStorage = {getItem:k=>store.get(k)??null,setItem:(k,v)=>store.set(k,v)};
    return {root,meta,waves,store};
}
const button = {getBoundingClientRect:()=>({left:940,top:20,width:38,height:38})};
test('initial paint uses saved light choice and defaults to dark when storage is unavailable', () => {
    const source=readFileSync(new URL('../../public/theme-init.js',import.meta.url),'utf8');
    for (const value of ['light','dark',null,'invalid','throws']) {
        const classList=classes();
        vm.runInNewContext(source,{document:{documentElement:{classList},querySelector:()=>null},localStorage:{getItem(){if(value==='throws') throw Error('Blocked');return value;}}});
        assert.equal(classList.contains('light'), value==='light');
    }
});
test('reduced motion applies immediately and storage failure does not break the switch', async t => {
    const {root,meta}=environment(t,true);
    globalThis.localStorage.setItem=()=>{throw Error('Private browsing');};
    await changeTheme('light',button);
    assert.equal(currentTheme(),'light');
    assert.equal(meta.get('content'),'#F4F2EC');
    assert.equal(root.classList.contains('sweep-in'),false);
    await changeTheme('dark',button);
    assert.equal(currentTheme(),'dark');
});
test('unsupported view transitions use a temporary wave and retain the requested theme', async t => {
    const {waves,store,root}=environment(t);
    const changing=changeTheme('light',button);
    assert.equal(waves.size,1);
    await changing;
    assert.equal(waves.size,0);
    assert.equal(currentTheme(),'light');
    assert.equal(store.get('nfcheck.theme'),'light');
    assert.equal(root.classList.contains('theming'),false);
});
test('a synchronous transition error does not lock subsequent theme switches', async t => {
    environment(t);
    document.startViewTransition=()=>{throw Error('Transition unavailable');};
    await changeTheme('light',button);
    assert.equal(currentTheme(),'light');
    await changeTheme('dark',button);
    assert.equal(currentTheme(),'dark');
});
test('theme wave covers the farthest viewport corner and clamps a stale toggle position', () => {
    const origin=themeOrigin(1000,700,1000,0);
    assert.ok(parseFloat(origin.radius)>140);
    const clamped=themeOrigin(0,0,-10,20);
    assert.equal(clamped.x,'0%');
    assert.equal(clamped.y,'100%');
    assert.ok(Number.isFinite(parseFloat(clamped.radius)));
});
