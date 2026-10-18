/**
 * Lightweight fade when navigating via sidebar links (desktop portal pages).
 */
(function () {
    'use strict';

    const NAV_KEY = 'rtc10_nav_transition';
    const LEAVE_MS = 200;

    function applyNavEnterTransition() {
        try {
            if (sessionStorage.getItem(NAV_KEY) === '1') {
                sessionStorage.removeItem(NAV_KEY);
                document.documentElement.classList.add('rtc10-nav-enter');
            }
        } catch (error) {
            // Ignore storage errors in restricted browser modes.
        }
    }

    function shouldSkipNavTransition(link, event) {
        if (event.defaultPrevented) {
            return true;
        }
        if (event.button !== 0) {
            return true;
        }
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return true;
        }
        if (link.target === '_blank') {
            return true;
        }

        const href = link.getAttribute('href');
        if (!href || href.charAt(0) === '#') {
            return true;
        }

        try {
            const dest = new URL(link.href, window.location.href);
            if (dest.origin !== window.location.origin) {
                return true;
            }
            if (dest.pathname === window.location.pathname) {
                return true;
            }
        } catch (error) {
            return true;
        }

        return false;
    }

    function bindSidebarNavTransition(sidebar, isMobileFn, options) {
        if (!sidebar || typeof isMobileFn !== 'function') {
            return;
        }

        const onLeave = options && typeof options.onLeave === 'function'
            ? options.onLeave
            : null;

        sidebar.querySelectorAll('.sidebar-menu a[href]').forEach((link) => {
            link.addEventListener('click', (event) => {
                if (isMobileFn()) {
                    return;
                }
                if (shouldSkipNavTransition(link, event)) {
                    return;
                }

                event.preventDefault();
                document.documentElement.classList.add('rtc10-nav-leave');

                if (onLeave) {
                    onLeave();
                }

                try {
                    sessionStorage.setItem(NAV_KEY, '1');
                } catch (error) {
                    // Ignore storage errors in restricted browser modes.
                }

                const destination = link.href;
                window.setTimeout(() => {
                    window.location.href = destination;
                }, LEAVE_MS);
            });
        });
    }

    window.RTC10ApplyNavEnterTransition = applyNavEnterTransition;
    window.RTC10BindSidebarNavTransition = bindSidebarNavTransition;

    applyNavEnterTransition();
})();
