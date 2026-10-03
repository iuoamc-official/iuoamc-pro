const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync(require('node:path').resolve(__dirname, '../../public/assets/js/iuoamc-learning-experience-1.0.0.js'), 'utf8');
function harness(saved, unavailable = false, unsavedDraft = false) {
    const actions = {}, listeners = {}, attributes = {}, properties = {};
    let stored;
    const buttons = ['smaller', 'larger', 'night', 'reset'].map(action => ({
        dataset: {readerAction: action}, addEventListener: (_, fn) => actions[action] = fn,
        setAttribute: (key, value) => attributes[key] = value
    }));
    const reader = {dataset: {}, style: {setProperty: (key, value) => properties[key] = value},
        querySelector: () => buttons[2], querySelectorAll: () => buttons};
    const input = {value: 'Initial'};
    const notes = {dataset: {unsaved: 'Unsaved', dirty: String(unsavedDraft)}, querySelector: () => input,
        addEventListener: (name, fn) => listeners[name] = fn};
    vm.runInNewContext(source, {
        document: {querySelector: selector => ({'[data-learning-reader]': reader, '[data-learning-notes]': notes}[selector] || null)},
        window: {addEventListener: (name, fn) => listeners[name] = fn},
        localStorage: {getItem: () => {if (unavailable) throw Error('Denied'); return saved;}, setItem: (_, value) => {if (unavailable) throw Error('Denied'); stored = JSON.parse(value);}}
    });
    return {reader, input, stored: () => stored, actions, attributes, properties, listeners};
}
test('reading preferences persist, restore, reset and stay within readable limits', () => {
    const h = harness('{"size":22,"theme":"night"}');
    assert.equal(h.properties['--reading-font'], '22px'); assert.equal(h.attributes['aria-pressed'], 'true');
    for(let i=0; i<10; i++) h.actions.larger();
    assert.equal(h.stored().size, 25);
    for(let i=0; i<10; i++) h.actions.smaller();
    assert.equal(h.stored().size, 16);
    h.actions.reset(); assert.deepEqual(h.stored(), {size:19,theme:'day'});
});
test('invalid settings and unavailable browser storage do not block reading', () => {
    const invalid = harness('{"size":999,"theme":"invalid"}');
    assert.equal(invalid.properties['--reading-font'], '19px');
    const denied = harness(null, true); denied.actions.night();
    assert.equal(denied.reader.dataset.readingTheme, 'night');
});
test('notes warn on unsaved changes and preserved conflict drafts, but allow saving', () => {
    const h = harness(null); let prevented = false;
    h.input.value = 'Changed'; h.listeners.beforeunload({preventDefault: () => prevented = true});
    assert.equal(prevented, true); h.listeners.submit(); prevented = false;
    h.listeners.beforeunload({preventDefault: () => prevented = true}); assert.equal(prevented, false);
    const conflict = harness(null, false, true);
    conflict.listeners.beforeunload({preventDefault: () => prevented = true}); assert.equal(prevented, true);
});
