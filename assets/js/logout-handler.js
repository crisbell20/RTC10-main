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

    function init() {
        attachLogoutButton('logoutBtn');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
