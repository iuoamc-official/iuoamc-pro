(() => {
    const reader = document.querySelector('[data-learning-reader]');
    if (reader) {
        const key = 'iuoamc.learning.reading.v1';
        let settings = {size: 19, theme: 'day'};
        try {
            const saved = JSON.parse(localStorage.getItem(key) || 'null');
            if (saved && [16, 19, 22, 25].includes(saved.size) && ['day', 'night'].includes(saved.theme)) settings = saved;
        } catch {}
        const apply = () => {
            reader.style.setProperty('--reading-font', `${settings.size}px`);
            reader.dataset.readingTheme = settings.theme;
            reader.querySelector('[data-reader-action="night"]').setAttribute('aria-pressed', String(settings.theme === 'night'));
            try { localStorage.setItem(key, JSON.stringify(settings)); } catch {}
        };
        reader.querySelectorAll('[data-reader-action]').forEach(button => button.addEventListener('click', () => {
            const action = button.dataset.readerAction;
            if (action === 'smaller') settings.size = Math.max(16, settings.size - 3);
            if (action === 'larger') settings.size = Math.min(25, settings.size + 3);
            if (action === 'night') settings.theme = settings.theme === 'night' ? 'day' : 'night';
            if (action === 'reset') settings = {size: 19, theme: 'day'};
            apply();
        }));
        apply();
    }
    const notes = document.querySelector('[data-learning-notes]');
    if (notes) {
        const input = notes.querySelector('textarea');
        const initial = input.value;
        let leavingToSave = false;
        notes.addEventListener('submit', () => { leavingToSave = true; });
        window.addEventListener('beforeunload', event => {
            if (!leavingToSave && (input.value !== initial || notes.dataset.dirty === 'true')) { event.preventDefault(); event.returnValue = notes.dataset.unsaved; }
        });
    }
    document.querySelector('[data-learning-print]')?.addEventListener('click', () => window.print());
})();
