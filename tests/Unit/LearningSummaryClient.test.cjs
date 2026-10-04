const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync(require('node:path').resolve(__dirname, '../../public/assets/js/iuoamc-learning-1.1.0.js'), 'utf8');
function harness(restored = false, confirmed = true) {
    const listeners = {};
    let confirmations = 0;
    const fields = [{value: 'draft text'}];
    const summary = {dataset: {dirty: String(restored), confirm: 'Submit?'}, querySelectorAll: () => fields,
        addEventListener: (name, fn) => listeners[name] = fn};
    const counter = {};
    vm.runInNewContext(source, {
        document: {querySelector: selector => ({'[data-learning-summary]': summary, '[data-word-count]': counter}[selector] || null)},
        window: {addEventListener: (name, fn) => listeners[name] = fn, confirm: () => {confirmations++; return confirmed;}}
    });
    const isDirty = () => {let prevented = false; listeners.beforeunload({preventDefault: () => prevented = true}); return prevented;};
    return {listeners, counter, isDirty, confirmations: () => confirmations};
}
test('incomplete summary drafts save without final confirmation and clear the exit warning', () => {
    const h = harness(); assert.equal(h.counter.textContent, '2'); assert.equal(h.isDirty(), false);
    h.listeners.input(); assert.equal(h.isDirty(), true);
    h.listeners.submit({submitter: {value: 'save'}});
    assert.equal(h.confirmations(), 0); assert.equal(h.isDirty(), false);
});
test('cancelled submission and restored validation drafts retain the exit warning', () => {
    const h = harness(true, false); assert.equal(h.isDirty(), true);
    let cancelled = false;
    h.listeners.submit({submitter: {value: 'submit'}, preventDefault: () => cancelled = true});
    assert.equal(cancelled, true); assert.equal(h.confirmations(), 1); assert.equal(h.isDirty(), true);
});
