/**
 * Shared logout button handler for all authenticated pages.
 */
(function () {
    const LOGIN_URL = '../../login.php';
    const LOGOUT_API = '../../api/auth/logout.php';

    function confirmLogout() {
        if (typeof Swal === 'undefined') {
            return Promise.resolve({ isConfirmed: confirm('Are you sure you want to logout?') });
        }

        return Swal.fire({
            title: 'Logout',
            text: 'Are you sure you want to logout?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, logout',
            cancelButtonText: 'No, stay',
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#6b7280'
        });
    }

    function performLogout() {
        return fetch(LOGOUT_API, { method: 'POST' })
            .finally(() => {
                window.location.href = LOGIN_URL;
            });
    }

    function attachLogoutButton(buttonId) {
        const btn = document.getElementById(buttonId || 'logoutBtn');
        if (!btn || btn.dataset.logoutBound === '1') {
            return;
        }

        btn.dataset.logoutBound = '1';
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            confirmLogout().then((result) => {
                if (result.isConfirmed) {
                    performLogout();
                }
            });
        });
    }

    window.LogoutHandler = {
        attach: attachLogoutButton,
        perform: performLogout
    };

    function applyStaffSidebarStateEarly() {
        if (typeof window.RTC10PromoteSidebarState === 'function') {
            window.RTC10PromoteSidebarState();
        }
        if (document.body.classList.contains('examinee-portal')) {
            return;
        }
        if (!document.querySelector('.dashboard-container .sidebar')) {
            return;
        }
        if (window.matchMedia('(max-width: 768px)').matches) {
            return;
        }
        try {
            if (localStorage.getItem('rtc10_staff_sidebar_collapsed') === '1') {
                document.body.classList.add('staff-sidebar-collapsed');
            }
        } catch (error) {
            // Ignore storage errors in restricted browser modes.
        }
    }

    function applyExamineeSidebarStateEarly() {
        if (typeof window.RTC10PromoteSidebarState === 'function') {
            window.RTC10PromoteSidebarState();
        }
        if (!document.body.classList.contains('examinee-portal')) {
            return;
        }
        if (!document.querySelector('.dashboard-container .sidebar')) {
            return;
        }
        if (window.matchMedia('(max-width: 991.98px)').matches) {
            return;
        }
        try {
            if (localStorage.getItem('rtc10_examinee_sidebar_collapsed') === '1') {
                document.body.classList.add('examinee-sidebar-collapsed');
            }
        } catch (error) {
            // Ignore storage errors in restricted browser modes.
        }
    }

    applyStaffSidebarStateEarly();
    applyExamineeSidebarStateEarly();

    function init() {
        applyStaffSidebarStateEarly();
        applyExamineeSidebarStateEarly();
        attachLogoutButton('logoutBtn');
        loadStaffSidebarToggle();
        loadStaffUi();
    }

    function loadStaffUi() {
        if (document.body.classList.contains('examinee-portal')) {
            return;
        }
        if (!document.querySelector('.dashboard-container .sidebar')) {
            return;
        }
        if (document.querySelector('script[src*="staff-ui.js"]')) {
            return;
        }

        const loader = document.querySelector('script[src*="logout-handler.js"]');
        let src = '../../assets/js/staff-ui.js?v=1';
        if (loader && loader.src) {
            src = loader.src.replace(/logout-handler\.js(\?v=\d+)?/, 'staff-ui.js?v=1');
        }

        const script = document.createElement('script');
        script.src = src;
        script.defer = true;
        document.head.appendChild(script);
    }

    function loadStaffSidebarToggle() {
        if (document.body.classList.contains('examinee-portal')) {
            return;
        }
        if (!document.querySelector('.dashboard-container .sidebar')) {
            return;
        }

        const loader = document.querySelector('script[src*="logout-handler.js"]');
        let src = '../../assets/js/sidebar-toggle.js?v=6';
        if (loader && loader.src) {
            src = loader.src.replace(/logout-handler\.js(\?v=\d+)?/, 'sidebar-toggle.js?v=6');
        }

        const script = document.createElement('script');
        script.src = src;
        script.defer = true;
        document.head.appendChild(script);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
