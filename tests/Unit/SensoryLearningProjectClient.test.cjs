const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync(require('node:path').resolve(__dirname, '../../public/assets/js/iuoamc-learning-project-1.0.0.js'), 'utf8');
function harness(confirm = true, dirty = false) {
    const listeners = {}, inputs = [{name:'fields[question]', value:'Initial', type:'textarea'}, {name:'fields[design]', value:'Design',type:'textarea'}, {name:'originality', checked:false,type:'checkbox'}];
    let confirmations = 0;
    const form = {dataset:{dirty:String(dirty), confirm:'Submit?'},querySelectorAll:()=>inputs,addEventListener:(name,fn)=>listeners[name]=fn};
    vm.runInNewContext(source,{document:{querySelector:()=>form},window:{confirm:()=>{confirmations++;return confirm;},addEventListener:(name,fn)=>listeners[name]=fn}});
    return {inputs,listeners,confirmations:()=>confirmations};
}
test('changes in any project section and declarations warn before navigation',()=>{
    const h=harness();let prevented=false;
    h.inputs[1].value='Changed second section';h.listeners.beforeunload({preventDefault:()=>prevented=true});assert.equal(prevented,true);
    h.inputs[1].value='Design';h.inputs[2].checked=true;prevented=false;
    h.listeners.beforeunload({preventDefault:()=>prevented=true});assert.equal(prevented,true);
});
test('saving a draft allows navigation without final submission confirmation',()=>{
    const h=harness();h.inputs[0].value='New draft';h.listeners.submit({submitter:{value:'save'}});
    let prevented=false;h.listeners.beforeunload({preventDefault:()=>prevented=true});
    assert.equal(prevented,false);assert.equal(h.confirmations(),0);
});
test('cancelled submission and restored conflict input retain unsaved protection',()=>{
    const h=harness(false,true);let prevented=false;
    h.listeners.submit({submitter:{value:'submit'},preventDefault:()=>prevented=true});assert.equal(prevented,true);
    prevented=false;h.listeners.beforeunload({preventDefault:()=>prevented=true});assert.equal(prevented,true);assert.equal(h.confirmations(),1);
});
