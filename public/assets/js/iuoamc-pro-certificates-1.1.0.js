/* IUOAMC certificate batches: one authenticated mutation at a time. */
(() => {
    'use strict';
    document.querySelectorAll('[data-pc-run]').forEach((panel) => {
        const resume = panel.querySelector('[data-pc-run-resume]');
        const pause = panel.querySelector('[data-pc-run-pause]');
        const progress = panel.querySelector('[data-pc-run-progress]');
        const count = panel.querySelector('[data-pc-run-count]');
        const message = panel.querySelector('[data-pc-run-message]');
        const csrf = panel.querySelector('[data-pc-run-csrf]');
        if (!resume || !pause || !progress || !count || !message || !csrf) return;
        const endpoint = new URL(panel.dataset.endpoint, window.location.href);
        if (endpoint.origin !== window.location.origin) return;
        let active = false, inFlight = false, finished = false;
        const say = (key) => { message.textContent = panel.dataset[key] || panel.dataset.messageFailure; };
        const controls = () => {
            resume.hidden = active || finished;
            resume.disabled = inFlight;
            pause.hidden = !active;
            pause.disabled = !active;
            panel.setAttribute('aria-busy', inFlight ? 'true' : 'false');
        };
        const stop = (key, terminal = false) => { active = false; finished = terminal; say(key); controls(); };
        async function step() {
            if (!active || inFlight || finished) return;
            inFlight = true; controls();
            const abort = new AbortController();
            const timer = window.setTimeout(() => abort.abort(), 120000);
            try {
                const response = await fetch(endpoint.href, {
                    method: 'POST', credentials: 'same-origin', redirect: 'error', cache: 'no-store',
                    headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf.value, 'X-Requested-With': 'XMLHttpRequest'},
                    signal: abort.signal
                });
                if ([401, 403, 419].includes(response.status)) { stop('messageAuth'); return; }
                if ([409, 422, 423].includes(response.status)) { stop('messageConflict'); return; }
                if (!response.ok || !(response.headers.get('content-type') || '').includes('application/json')) { stop('messageFailure'); return; }
                const result = await response.json();
                if (!Number.isInteger(result.done) || !Number.isInteger(result.total) || result.done < 0 || result.total < 1 || result.total > 100 || result.done > result.total || result.run_id !== Number(panel.dataset.runId) || typeof result.completed !== 'boolean' || typeof result.stopped !== 'boolean') {
                    stop('messageFailure'); return;
                }
                progress.max = result.total; progress.value = result.done;
                count.textContent = `${result.done} / ${result.total}`;
                if (result.stopped) { stop('messageStopped', true); return; }
                if (result.completed) { stop('messageComplete', true); return; }
                if (active) say('messageRunning'); else say('messagePaused');
            } catch (error) {
                // A disconnected request may have committed; resume asks the same persisted run.
                stop('messageNetwork');
            } finally {
                window.clearTimeout(timer); inFlight = false; controls();
                if (active && !finished) window.setTimeout(step, 250);
            }
        }
        resume.addEventListener('click', () => { if (active || inFlight || finished) return; active = true; say('messageRunning'); controls(); step(); });
        pause.addEventListener('click', () => { active = false; say('messagePaused'); controls(); });
        controls();
        if (panel.dataset.autoStart === '1') resume.click();
    });
})();
