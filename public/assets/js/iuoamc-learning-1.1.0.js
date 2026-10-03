(() => {
    const exam = document.querySelector('[data-learning-exam]');
    if (exam) {
        let dirty = false;
        const status = document.querySelector('[data-answered-count]');
        const updateAnswers = () => {
            let count = 0;
            exam.querySelectorAll('[data-question-id]').forEach(question => {
                const answered = Array.from(question.querySelectorAll('input[type=radio],textarea'))
                    .some(input => input.type === 'radio' ? input.checked : input.value.trim().length > 0);
                const link = document.querySelector(`[data-question-link="${question.dataset.questionId}"]`);
                if (link) link.dataset.answered = String(answered);
                if (answered) count++;
            });
            if (status) status.textContent = `${count} / 40`;
        };
        exam.addEventListener('input', () => { dirty = true; updateAnswers(); });
        exam.addEventListener('submit', event => {
            if (event.submitter?.value === 'submit' && !window.confirm(exam.dataset.confirm)) {
                event.preventDefault(); return;
            }
            dirty = false;
        });
        window.addEventListener('beforeunload', event => {
            if (dirty) { event.preventDefault(); event.returnValue = ''; }
        });
        updateAnswers();
    }
    const timer = document.querySelector('[data-deadline]');
    if (timer) {
        const deadline = Date.parse(timer.dataset.deadline);
        const updateTimer = () => {
            const seconds = Math.max(0, Math.floor((deadline - Date.now()) / 1000));
            const hours = String(Math.floor(seconds / 3600)).padStart(2, '0');
            const minutes = String(Math.floor(seconds % 3600 / 60)).padStart(2, '0');
            const remainder = String(seconds % 60).padStart(2, '0');
            timer.textContent = `${hours}:${minutes}:${remainder}`;
            if (!seconds) {
                const notice = document.querySelector('[data-time-expired]');
                if (notice) notice.hidden = false;
            }
        };
        if (Number.isFinite(deadline)) { updateTimer(); window.setInterval(updateTimer, 1000); }
    }
    const summary = document.querySelector('[data-learning-summary]');
    if (summary) {
        const output = document.querySelector('[data-word-count]');
        const meter = document.querySelector('.learning-summary-meter');
        const updateWords = () => {
            const text = Array.from(summary.querySelectorAll('textarea')).map(field => field.value.trim()).join(' ').trim();
            const count = text ? text.split(/\s+/u).length : 0;
            if (output) output.textContent = String(count);
            if (meter) meter.dataset.valid = String(count >= 180 && count <= 250);
        };
        summary.addEventListener('input', updateWords);
        summary.addEventListener('submit', event => {
            if (!window.confirm(summary.dataset.confirm)) event.preventDefault();
        });
        updateWords();
    }
})();
