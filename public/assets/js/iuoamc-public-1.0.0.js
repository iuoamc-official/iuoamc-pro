(() => {
    const button = document.querySelector('[data-nav-toggle]');
    const navigation = document.querySelector('[data-navigation]');
    button?.addEventListener('click', () => {
        const open = button.getAttribute('aria-expanded') === 'true';
        button.setAttribute('aria-expanded', String(!open));
        navigation?.classList.toggle('open', !open);
    });

    document.querySelector('[data-verify-form]')?.addEventListener('submit', (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const token = String(new FormData(form).get('token') || '').trim();
        if (token !== '') {
            window.location.assign(`${form.dataset.base}/${encodeURIComponent(token)}`);
        }
    });
})();
