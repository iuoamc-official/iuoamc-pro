const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync(require('node:path').resolve(__dirname, '../../public/assets/js/iuoamc-objective-exam-1.0.0.js'), 'utf8');
function harness(responder) {
    let time = 0;
    const listeners = {}, intervals = [], timeouts = new Map(), requests = [];
    const radio = {name:'answers[1]', value:'1', disabled:false};
    let checked = false;
    const buttons = [{disabled:false}, {disabled:false}];
    const revision = {value:'0'};
    const timer = {dataset:{remainingMs:'1800000'}, textContent:''};
    const status = {dataset:{saving:'saving',saved:'saved',failed:'failed',expired:'expired',conflict:'conflict'}};
    const count = {textContent:''};
    const question = {dataset:{questionId:'1'},querySelector:() => checked ? radio : null};
    const link = {dataset:{}};
    const form = {
        dataset:{questionCount:'30',confirm:'Final?',resultUrl:'/result'},action:'/exam',
        elements:{namedItem: key => key === 'draft_revision' ? revision : {value:'csrf'}},
        addEventListener:(name,fn) => listeners[name] = fn,
        querySelectorAll: selector => ({'[data-question-id]':[question], 'input[type=radio]:checked':checked?[radio]:[], 'input[type=radio],button':[radio,...buttons], 'button':buttons}[selector] || [])
    };
    let destination;
    const document = {hidden:false,addEventListener:()=>{},querySelector: selector => selector.includes('data-question-link') ? link : ({'[data-objective-exam]':form,'[data-remaining-ms]':timer,'[data-autosave-status]':status,'[data-answered-count]':count}[selector] || null)};
    const context = {
        document,performance:{now:()=>time},Date:{now:()=>9000000000000},
        window:{location:{assign:url=>destination=url},confirm:()=>true,addEventListener:()=>{},setInterval:(fn,ms)=>intervals.push({fn,ms})},
        setTimeout:(fn)=>{const id=timeouts.size+1;timeouts.set(id,fn);return id;},clearTimeout:id=>timeouts.delete(id),
        fetch:async (url,options)=>{const payload=JSON.parse(options.body);requests.push(payload);return {ok:true,status:200,json:async()=>responder(payload)};}
    };
    vm.runInNewContext(source,context);
    return {timer,count,status,requests,radio,revision,buttons,
        setTime:value=>time=value,
        tick:()=>intervals.find(i=>i.ms===250).fn(),
        retry:()=>intervals.find(i=>i.ms===10000).fn(),
        select:value=>{checked=true;radio.value=String(value);listeners.change();},
        debounce:()=>{const callbacks=[...timeouts.values()];timeouts.clear();callbacks.forEach(fn=>fn());},
        destination:()=>destination,
        flush:()=>new Promise(resolve=>setImmediate(resolve))
    };
}
test('countdown uses monotonic elapsed time despite a changed wall clock',()=>{
    const h=harness(()=>({submitted:false,revision:1,remaining_ms:1800000}));
    assert.equal(h.timer.textContent,'30:00');h.setTime(60000);h.tick();assert.equal(h.timer.textContent,'29:00');
});
test('changes autosave and later saves use the acknowledged revision',async()=>{
    const h=harness(p=>({submitted:false,revision:p.draft_revision+1,remaining_ms:1800000}));
    h.select(1);h.debounce();await h.flush();assert.equal(h.requests[0].action,'save');assert.deepEqual(h.requests[0].answers,{'1':1});assert.equal(h.revision.value,'1');
    h.select(0);h.debounce();await h.flush();assert.equal(h.requests[1].draft_revision,1);assert.equal(h.revision.value,'2');
});
test('timeout locks inputs and requests server expiry without overwriting the draft',async()=>{
    const h=harness(()=>({submitted:false,revision:0,remaining_ms:1}));
    h.setTime(1800000);h.tick();await h.flush();assert.equal(h.radio.disabled,true);assert.equal(h.requests[0].action,'expire');assert.deepEqual(h.requests[0].answers,{});
    h.retry();await h.flush();assert.equal(h.requests[1].action,'expire');assert.equal(h.destination(),undefined);
});
test('closed server attempt navigates to its immutable result',async()=>{
    const h=harness(()=>({submitted:true,revision:0,remaining_ms:0,result_url:'/result'}));
    h.setTime(1800000);h.tick();await h.flush();assert.equal(h.destination(),'/result');
});
