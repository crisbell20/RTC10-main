/**
 * Staff portal sidebar toggle (Google Classroom style).
 * Desktop: collapse to icon rail with per-item tooltips (no hover expansion).
 * Mobile: off-canvas drawer.
 */
(function () {
    'use strict';

    const STORAGE_KEY = 'rtc10_staff_sidebar_collapsed';
    const MOBILE_MQ = window.matchMedia('(max-width: 768px)');

    function isStaffPortal() {
        return document.body
            && !document.body.classList.contains('examinee-portal')
            && document.querySelector('.dashboard-container .sidebar');
    }

    function isMobile() {
        return MOBILE_MQ.matches;
    }

    function isCollapsed() {
        return document.body.classList.contains('staff-sidebar-collapsed');
    }

    function applyStoredCollapseState() {
        if (!document.body || !isStaffPortal() || isMobile()) {
            return;
        }
        if (localStorage.getItem(STORAGE_KEY) === '1') {
            document.body.classList.add('staff-sidebar-collapsed');
        }
    }

    applyStoredCollapseState();

    function setCollapsed(collapsed) {
        document.body.classList.toggle('staff-sidebar-collapsed', collapsed);
        localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
        if (typeof tooltipController !== 'undefined') {
            tooltipController.sync();
        }
    }

    function setDrawerOpen(open) {
        document.body.classList.toggle('staff-sidebar-open', open);
    }

    function closeDrawer() {
        setDrawerOpen(false);
    }

    function getLinkLabel(link) {
        return link.textContent.replace(/\s+/g, ' ').trim();
    }

    function createTooltipController(sidebar) {
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
            return !isMobile() && isCollapsed();
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

        sidebar.querySelectorAll('.sidebar-menu a').forEach((link) => {
            link.addEventListener('mouseenter', () => showTooltip(link));
            link.addEventListener('mouseleave', hideTooltip);
            link.addEventListener('focus', () => showTooltip(link));
            link.addEventListener('blur', hideTooltip);
        });

        sidebar.addEventListener('scroll', () => {
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

    let tooltipController;

    function init() {
        if (typeof window.RTC10PromoteSidebarState === 'function') {
            window.RTC10PromoteSidebarState();
        }
        if (!isStaffPortal()) {
            return;
        }

        const topNav = document.querySelector('.top-nav');
        const sidebar = document.querySelector('.sidebar');
        if (!topNav || !sidebar) {
            return;
        }

        tooltipController = createTooltipController(sidebar);

        if (typeof window.RTC10BindSidebarNavTransition === 'function') {
            window.RTC10BindSidebarNavTransition(sidebar, isMobile, {
                onLeave: () => tooltipController.hide()
            });
        }

        let toggle = topNav.querySelector('.sidebar-toggle-btn');
        if (!toggle) {
            toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'sidebar-toggle-btn';
            toggle.setAttribute('aria-label', 'Toggle navigation menu');
            toggle.setAttribute('aria-expanded', 'true');
            toggle.innerHTML = '<i class="bi bi-list"></i>';
            topNav.insertBefore(toggle, topNav.firstChild);
        }

        let backdrop = document.querySelector('.staff-sidebar-backdrop');
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.className = 'staff-sidebar-backdrop';
            backdrop.setAttribute('aria-hidden', 'true');
            document.body.appendChild(backdrop);
        }

        function syncToggleState() {
            const mobile = isMobile();
            const expanded = mobile
                ? document.body.classList.contains('staff-sidebar-open')
                : !isCollapsed();

            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            toggle.setAttribute(
                'aria-label',
                mobile
                    ? (expanded ? 'Close navigation menu' : 'Open navigation menu')
                    : (expanded ? 'Collapse navigation menu' : 'Expand navigation menu')
            );
            tooltipController.sync();
        }

        function onToggleClick() {
            if (isMobile()) {
                setDrawerOpen(!document.body.classList.contains('staff-sidebar-open'));
            } else {
                setCollapsed(!isCollapsed());
            }
            syncToggleState();
        }

        toggle.addEventListener('click', onToggleClick);

        backdrop.addEventListener('click', () => {
            if (isMobile()) {
                closeDrawer();
                syncToggleState();
            }
        });

        sidebar.addEventListener('click', (event) => {
            if (isMobile() && event.target.closest('a')) {
                closeDrawer();
                syncToggleState();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }
            if (document.body.classList.contains('staff-sidebar-open')) {
                closeDrawer();
                syncToggleState();
            }
        });

        function onViewportChange() {
            if (isMobile()) {
                document.body.classList.remove('staff-sidebar-collapsed');
            } else {
                closeDrawer();
                if (localStorage.getItem(STORAGE_KEY) === '1') {
                    document.body.classList.add('staff-sidebar-collapsed');
                } else {
                    document.body.classList.remove('staff-sidebar-collapsed');
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
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
