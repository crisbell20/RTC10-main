/**
 * Examinee portal — mobile sidebar (hamburger) toggle.
 * UI only; no API or session changes.
 */
(function () {
    if (!document.body.classList.contains('examinee-portal')) {
        return;
    }

    const topNav = document.querySelector('.top-nav');
    const sidebar = document.querySelector('.sidebar');
    if (!topNav || !sidebar) {
        return;
    }

    let backdrop = document.querySelector('.examinee-sidebar-backdrop');
    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.className = 'examinee-sidebar-backdrop';
        backdrop.setAttribute('aria-hidden', 'true');
        document.body.appendChild(backdrop);
    }

    let toggle = topNav.querySelector('.examinee-nav-toggle');
    if (!toggle) {
        toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'examinee-nav-toggle';
        toggle.setAttribute('aria-label', 'Open navigation menu');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.innerHTML = '<i class="bi bi-list"></i>';
        topNav.insertBefore(toggle, topNav.firstChild);
    }

    function isMobileNav() {
        return window.matchMedia('(max-width: 991.98px)').matches;
    }

    function setOpen(open) {
        document.body.classList.toggle('sidebar-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
        toggle.innerHTML = open
            ? '<i class="bi bi-x-lg"></i>'
            : '<i class="bi bi-list"></i>';
    }

    function closeSidebar() {
        setOpen(false);
    }

    function toggleSidebar() {
        if (!isMobileNav()) {
            closeSidebar();
            return;
        }
        setOpen(!document.body.classList.contains('sidebar-open'));
    }

    toggle.addEventListener('click', function (e) {
        e.preventDefault();
        toggleSidebar();
    });

    backdrop.addEventListener('click', closeSidebar);

    sidebar.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            if (isMobileNav()) {
                closeSidebar();
            }
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeSidebar();
        }
    });

    window.addEventListener('resize', function () {
        if (!isMobileNav()) {
            closeSidebar();
        }
    });
})();
