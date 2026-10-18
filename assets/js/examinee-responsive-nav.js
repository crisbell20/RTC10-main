/**
 * Examinee portal sidebar toggle.
 * Desktop: collapse to icon rail with per-item tooltips (no hover expansion).
 * Mobile/tablet: drawer + bottom navigation (sidebar hidden).
 */
(function () {
    'use strict';

    if (!document.body.classList.contains('examinee-portal')) {
        return;
    }

    const STORAGE_KEY = 'rtc10_examinee_sidebar_collapsed';
    const MOBILE_MQ = window.matchMedia('(max-width: 991.98px)');

    const topNav = document.querySelector('.top-nav');
    const sidebar = document.querySelector('.sidebar');
    if (!topNav || !sidebar) {
        return;
    }

    function isMobileNav() {
        return MOBILE_MQ.matches;
    }

    function isCollapsed() {
        return document.body.classList.contains('examinee-sidebar-collapsed');
    }

    function applyStoredCollapseState() {
        if (isMobileNav()) {
            return;
        }
        try {
            if (localStorage.getItem(STORAGE_KEY) === '1') {
                document.body.classList.add('examinee-sidebar-collapsed');
            }
        } catch (error) {
            // Ignore storage errors in restricted browser modes.
        }
    }

    applyStoredCollapseState();

    if (typeof window.RTC10PromoteSidebarState === 'function') {
        window.RTC10PromoteSidebarState();
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
        toggle.innerHTML = '<i class="bi bi-list"></i>';
        topNav.insertBefore(toggle, topNav.firstChild);
    }

    function getLinkLabel(link) {
        return link.textContent.replace(/\s+/g, ' ').trim();
    }

    function createTooltipController(sidebarEl) {
        let tooltipEl = document.getElementById('rtc10-sidebar-collapsed-tooltip');
        if (!tooltipEl) {
            tooltipEl = document.createElement('div');
            tooltipEl.id = 'rtc10-sidebar-collapsed-tooltip';
            tooltipEl.className = 'sidebar-collapsed-tooltip';
            tooltipEl.setAttribute('role', 'tooltip');
            tooltipEl.hidden = true;
            document.body.appendChild(tooltipEl);
        }

        let activeLink = null;

        function shouldShow() {
            return !isMobileNav() && isCollapsed();
        }

        function hideTooltip() {
            tooltipEl.hidden = true;
            activeLink = null;
        }

        function positionTooltip(link) {
            const rect = link.getBoundingClientRect();
            tooltipEl.style.top = `${rect.top + (rect.height / 2)}px`;
            tooltipEl.style.left = `${rect.right + 8}px`;
            tooltipEl.style.transform = 'translateY(-50%)';
        }

        function showTooltip(link) {
            if (!shouldShow()) {
                hideTooltip();
                return;
            }

            const label = getLinkLabel(link);
            if (!label) {
                hideTooltip();
                return;
            }

            activeLink = link;
            tooltipEl.textContent = label;
            tooltipEl.hidden = false;
            positionTooltip(link);
        }

        sidebarEl.querySelectorAll('.sidebar-menu a').forEach((link) => {
            link.addEventListener('mouseenter', () => showTooltip(link));
            link.addEventListener('mouseleave', hideTooltip);
            link.addEventListener('focus', () => showTooltip(link));
            link.addEventListener('blur', hideTooltip);
        });

        sidebarEl.addEventListener('scroll', () => {
            if (activeLink && !tooltipEl.hidden) {
                positionTooltip(activeLink);
            }
        }, true);

        return {
            sync() {
                if (!shouldShow()) {
                    hideTooltip();
                }
            },
            hide: hideTooltip
        };
    }

    const tooltipController = createTooltipController(sidebar);

    if (typeof window.RTC10BindSidebarNavTransition === 'function') {
        window.RTC10BindSidebarNavTransition(sidebar, isMobileNav, {
            onLeave: () => tooltipController.hide()
        });
    }

    function setCollapsed(collapsed) {
        document.body.classList.toggle('examinee-sidebar-collapsed', collapsed);
        try {
            localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
        } catch (error) {
            // Ignore storage errors in restricted browser modes.
        }
        tooltipController.sync();
    }

    function setDrawerOpen(open) {
        document.body.classList.toggle('sidebar-open', open);
    }

    function closeDrawer() {
        setDrawerOpen(false);
    }

    function syncToggleState() {
        const mobile = isMobileNav();
        const expanded = mobile
            ? document.body.classList.contains('sidebar-open')
            : !isCollapsed();

        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        toggle.setAttribute(
            'aria-label',
            mobile
                ? (expanded ? 'Close navigation menu' : 'Open navigation menu')
                : (expanded ? 'Collapse navigation menu' : 'Expand navigation menu')
        );
        toggle.innerHTML = mobile && expanded
            ? '<i class="bi bi-x-lg"></i>'
            : '<i class="bi bi-list"></i>';
        tooltipController.sync();
    }

    function toggleSidebar() {
        if (isMobileNav()) {
            setDrawerOpen(!document.body.classList.contains('sidebar-open'));
        } else {
            setCollapsed(!isCollapsed());
        }
        syncToggleState();
    }

    toggle.addEventListener('click', function (e) {
        e.preventDefault();
        toggleSidebar();
    });

    backdrop.addEventListener('click', function () {
        if (isMobileNav()) {
            closeDrawer();
            syncToggleState();
        }
    });

    sidebar.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            if (isMobileNav()) {
                closeDrawer();
                syncToggleState();
            }
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') {
            return;
        }
        if (document.body.classList.contains('sidebar-open')) {
            closeDrawer();
            syncToggleState();
        }
    });

    function onViewportChange() {
        if (isMobileNav()) {
            document.body.classList.remove('examinee-sidebar-collapsed');
            closeDrawer();
        } else {
            closeDrawer();
            if (localStorage.getItem(STORAGE_KEY) === '1') {
                document.body.classList.add('examinee-sidebar-collapsed');
            } else {
                document.body.classList.remove('examinee-sidebar-collapsed');
            }
        }
        syncToggleState();
    }

    if (typeof MOBILE_MQ.addEventListener === 'function') {
        MOBILE_MQ.addEventListener('change', onViewportChange);
    } else if (typeof MOBILE_MQ.addListener === 'function') {
        MOBILE_MQ.addListener(onViewportChange);
    }

    syncToggleState();
})();
