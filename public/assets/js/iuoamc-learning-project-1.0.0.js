(() => {
    const form = document.querySelector('[data-learning-project]');
    if (!form) return;
    const snapshot = () => JSON.stringify(Array.from(form.querySelectorAll('textarea,input[type=checkbox]'), input => [input.name, input.type === 'checkbox' ? input.checked : input.value]));
    const initial = snapshot();
    let saving = false;
    form.addEventListener('submit', event => {
        if (event.submitter?.value === 'submit' && !window.confirm(form.dataset.confirm)) { event.preventDefault(); return; }
        saving = true;
    });
    window.addEventListener('beforeunload', event => {
        if (!saving && (snapshot() !== initial || form.dataset.dirty === 'true')) { event.preventDefault(); event.returnValue = ''; }
    });
})();
