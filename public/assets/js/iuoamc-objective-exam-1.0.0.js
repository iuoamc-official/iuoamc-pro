(() => {
    const form = document.querySelector('[data-objective-exam]');
    if (!form) return;
    const timer = document.querySelector('[data-remaining-ms]');
    const status = document.querySelector('[data-autosave-status]');
    const answeredCount = document.querySelector('[data-answered-count]');
    const revisionField = form.elements.namedItem('draft_revision');
    const csrf = form.elements.namedItem('_token').value;
    let revision = Number(revisionField.value);
    let remainingUntil = performance.now() + Number(timer.dataset.remainingMs);
    let dirty = false;
    let expired = false;
    let completed = false;
    let blocked = false;
    let changed = 0;
    let pending = Promise.resolve();
    let debounce;
    const message = key => { status.textContent = status.dataset[key]; };
    const refreshAnswers = () => {
        let count = 0;
        form.querySelectorAll('[data-question-id]').forEach(question => {
            const answered = !!question.querySelector('input[type=radio]:checked');
            const link = document.querySelector(`[data-question-link="${question.dataset.questionId}"]`);
            if (link) link.dataset.answered = String(answered);
            if (answered) count++;
        });
        answeredCount.textContent = `${count} / ${form.dataset.questionCount}`;
    };
    const collect = () => {
        const answers = {};
        form.querySelectorAll('input[type=radio]:checked').forEach(input => {
            const id = input.name.match(/^answers\[(\d+)\]$/)?.[1];
            if (id) answers[id] = Number(input.value);
        });
        return answers;
    };
    const disable = () => {
        form.querySelectorAll('input[type=radio],button').forEach(input => { input.disabled = true; });
    };
    const redirectToResult = url => {
        completed = true; dirty = false;
        window.location.assign(url || form.dataset.resultUrl);
    };
    const send = async action => {
        if (completed || blocked) return;
        if (expired && action === 'save') return;
        const captured = changed;
        const requestStart = performance.now();
        message(expired ? 'expired' : 'saving');
        try {
            const response = await fetch(form.action, {
                method: 'POST', credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf},
                body: JSON.stringify({action, answers: expired ? {} : collect(), draft_revision: revision})
            });
            if (!response.ok) {
                if (response.status === 409) {
                    if (expired || action === 'submit') redirectToResult();
                    else { blocked = true; disable(); message('conflict'); }
                    return;
                }
                throw new Error('Save rejected');
            }
            const result = await response.json();
            if (result.submitted) { redirectToResult(result.result_url); return; }
            revision = result.revision;
            revisionField.value = String(revision);
            remainingUntil = Math.min(remainingUntil, requestStart + result.remaining_ms);
            if (captured === changed) dirty = false;
            message(expired ? 'expired' : 'saved');
        } catch {
            message('failed');
            if (action === 'submit' && !expired && !blocked) {
                form.querySelectorAll('button').forEach(button => { button.disabled = false; });
            }
        }
    };
    const queue = action => {
        pending = pending.then(() => send(action));
        return pending;
    };
    const closeAtDeadline = () => {
        if (expired || completed) return;
        expired = true;
        clearTimeout(debounce);
        disable(); message('expired');
        queue('expire');
    };
    const tick = () => {
        const seconds = Math.max(0, Math.ceil((remainingUntil - performance.now()) / 1000));
        timer.textContent = `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
        if (!seconds) closeAtDeadline();
    };
    form.addEventListener('change', () => {
        if (expired || blocked) return;
        dirty = true; changed++;
        refreshAnswers();
        clearTimeout(debounce);
        debounce = setTimeout(() => queue('save'), 600);
    });
    form.addEventListener('submit', event => {
        event.preventDefault();
        const action = event.submitter?.value || 'save';
        if (expired || blocked) return;
        if (action === 'submit' && !window.confirm(form.dataset.confirm)) return;
        clearTimeout(debounce);
        if (action === 'submit') form.querySelectorAll('button').forEach(button => { button.disabled = true; });
        queue(action);
    });
    window.addEventListener('beforeunload', event => {
        if (dirty && !completed && !expired) { event.preventDefault(); event.returnValue = ''; }
    });
    document.addEventListener('visibilitychange', () => { if (!document.hidden) tick(); });
    window.setInterval(() => {
        if (completed || blocked) return;
        if (expired) queue('expire');
        else if (dirty) queue('save');
    }, 10000);
    const questions = Array.from(form.querySelectorAll('[data-question-id]'));
    const pagination = form.querySelector('[data-exam-pagination]');
    if (pagination) {
        const size = Number(form.dataset.pageSize) || 5;
        const total = Math.ceil(questions.length / size);
        const previous = pagination.querySelector('[data-exam-previous]');
        const next = pagination.querySelector('[data-exam-next]');
        const pageStatus = pagination.querySelector('[data-exam-page-status]');
        const finalSubmit = form.querySelector('[data-exam-final-submit]');
        let page = 0;
        const showPage = (requested, focus = true) => {
            if (expired || blocked || completed) return;
            page = Math.max(0, Math.min(total - 1, requested));
            questions.forEach((question, index) => { question.hidden = Math.floor(index / size) !== page; });
            previous.disabled = page === 0;
            next.disabled = page === total - 1;
            finalSubmit.hidden = page !== total - 1;
            pageStatus.textContent = pageStatus.dataset.label
                .replace(':page', page + 1).replace(':total', total)
                .replace(':first', page * size + 1).replace(':last', Math.min((page + 1) * size, questions.length));
            document.querySelectorAll('[data-question-link]').forEach((link, index) => {
                if (Math.floor(index / size) === page) link.setAttribute('aria-current', 'page');
                else link.removeAttribute('aria-current');
            });
            if (dirty) { clearTimeout(debounce); queue('save'); }
            if (focus) questions[page * size].focus();
        };
        previous.addEventListener('click', () => showPage(page - 1));
        next.addEventListener('click', () => showPage(page + 1));
        document.querySelectorAll('[data-question-link]').forEach((link, index) => {
            link.addEventListener('click', event => {
                event.preventDefault();
                showPage(Math.floor(index / size), false);
                if (!expired && !blocked && !completed) questions[index].focus();
            });
        });
        pagination.hidden = false;
        showPage(0, false);
    }
    refreshAnswers(); tick();
    window.setInterval(tick, 250);
})();
