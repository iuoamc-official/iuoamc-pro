(() => {
    'use strict';

    const shell = document.querySelector('[data-control-shell]');
    const sidebar = document.querySelector('#iuoamc-sidebar');
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const overlay = document.querySelector('[data-sidebar-overlay]');

    if (!shell || !sidebar || !toggle) {
        return;
    }

    const desktopQuery = window.matchMedia('(min-width: 821px)');
    const storageKey = 'iuoamc.control.sidebar.collapsed';
    const hideLabel = toggle.dataset.hideLabel || 'Hide sidebar';
    const showLabel = toggle.dataset.showLabel || 'Show sidebar';

    const readStoredState = () => {
        try {
            return window.localStorage.getItem(storageKey) === '1';
        } catch (_) {
            return false;
        }
    };

    const writeStoredState = (collapsed) => {
        try {
            window.localStorage.setItem(storageKey, collapsed ? '1' : '0');
        } catch (_) {
            // The interface remains functional when storage is unavailable.
        }
    };

    const updateButton = (expanded) => {
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        toggle.setAttribute('aria-label', expanded ? hideLabel : showLabel);
        toggle.setAttribute('title', expanded ? hideLabel : showLabel);
    };

    const updateSidebarAccessibility = (expanded) => {
        sidebar.toggleAttribute('inert', !expanded);
        sidebar.setAttribute('aria-hidden', expanded ? 'false' : 'true');
    };

    const closeMobileSidebar = () => {
        shell.classList.remove('is-sidebar-open');
        document.body.classList.remove('sidebar-lock');
        updateButton(false);
        updateSidebarAccessibility(false);
    };

    const applyDesktopState = () => {
        const collapsed = readStoredState();
        shell.classList.remove('is-sidebar-open');
        document.body.classList.remove('sidebar-lock');
        shell.classList.toggle('is-sidebar-collapsed', collapsed);
        updateButton(!collapsed);
        updateSidebarAccessibility(!collapsed);
    };

    const applyResponsiveState = () => {
        if (desktopQuery.matches) {
            applyDesktopState();
        } else {
            shell.classList.remove('is-sidebar-collapsed');
            closeMobileSidebar();
        }
    };

    toggle.addEventListener('click', () => {
        if (desktopQuery.matches) {
            const collapsed = !shell.classList.contains('is-sidebar-collapsed');
            shell.classList.toggle('is-sidebar-collapsed', collapsed);
            writeStoredState(collapsed);
            updateButton(!collapsed);
            updateSidebarAccessibility(!collapsed);
            return;
        }

        const open = !shell.classList.contains('is-sidebar-open');
        shell.classList.toggle('is-sidebar-open', open);
        document.body.classList.toggle('sidebar-lock', open);
        updateButton(open);
        updateSidebarAccessibility(open);
    });

    overlay?.addEventListener('click', closeMobileSidebar);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && shell.classList.contains('is-sidebar-open')) {
            closeMobileSidebar();
            toggle.focus();
        }
    });

    desktopQuery.addEventListener?.('change', applyResponsiveState);
    applyResponsiveState();
})();
