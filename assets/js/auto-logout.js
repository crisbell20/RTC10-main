/**
 * Auto Logout on Inactivity
 * Timeout comes from System Settings (Security Policy).
 */

(function() {
    const DEFAULT_TIMEOUT_MS = 5 * 60 * 1000;
    const WARNING_TIME = 30 * 1000;
    const SESSION_POLICY_URL = '../../api/auth/session-policy.php';

    let inactivityTimeoutMs = DEFAULT_TIMEOUT_MS;
    let inactivityTimer;
    let warningTimer;
    let warningShown = false;

    const activityEvents = [
        'mousedown',
        'mousemove',
        'keypress',
        'scroll',
        'touchstart',
        'click'
    ];

    function resetTimer() {
        clearTimeout(inactivityTimer);
        clearTimeout(warningTimer);
        warningShown = false;

        const warningDelay = Math.max(0, inactivityTimeoutMs - WARNING_TIME);
        warningTimer = setTimeout(showWarning, warningDelay);
        inactivityTimer = setTimeout(logout, inactivityTimeoutMs);
    }

    function showWarning() {
        if (warningShown) return;
        warningShown = true;

        const warningSeconds = Math.max(1, Math.round(Math.min(WARNING_TIME, inactivityTimeoutMs) / 1000));

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Session Timeout Warning',
                text: `You will be logged out in ${warningSeconds} seconds due to inactivity. Move your mouse or press any key to stay logged in.`,
                icon: 'warning',
                timer: warningSeconds * 1000,
                timerProgressBar: true,
                showConfirmButton: true,
                confirmButtonText: 'Stay Logged In',
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed || result.dismiss === Swal.DismissReason.timer) {
                    if (result.dismiss === Swal.DismissReason.timer) {
                        logout();
                    } else {
                        resetTimer();
                    }
                }
            });
        } else {
            const stayLoggedIn = confirm(`You will be logged out in ${warningSeconds} seconds due to inactivity. Click OK to stay logged in.`);
            if (stayLoggedIn) {
                resetTimer();
            }
        }
    }

    function redirectAfterLogout() {
        if (typeof LogoutHandler !== 'undefined') {
            LogoutHandler.perform();
            return;
        }

        fetch('../../api/auth/logout.php', { method: 'POST' })
            .finally(() => {
                window.location.href = '../../login.php';
            });
    }

    function logout() {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Session Expired',
                text: 'You have been logged out due to inactivity.',
                icon: 'info',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true,
                allowOutsideClick: false
            }).then(() => {
                redirectAfterLogout();
            });
        } else {
            alert('You have been logged out due to inactivity.');
            redirectAfterLogout();
        }
    }

    async function loadIdleTimeoutMs() {
        try {
            const res = await fetch(SESSION_POLICY_URL, { credentials: 'same-origin' });
            const data = await res.json();
            const minutes = parseInt(data?.data?.idle_timeout_minutes, 10);
            if (res.ok && data.success && minutes > 0) {
                return minutes * 60 * 1000;
            }
        } catch (error) {
            // Keep the default timeout if the policy cannot be loaded.
        }
        return DEFAULT_TIMEOUT_MS;
    }

    async function init() {
        inactivityTimeoutMs = await loadIdleTimeoutMs();

        activityEvents.forEach(event => {
            document.addEventListener(event, resetTimer, true);
        });

        resetTimer();

        window.addEventListener('pageshow', (event) => {
            if (event.persisted) {
                resetTimer();
            }
        });

        window.addEventListener('pagehide', (event) => {
            if (event.persisted) {
                clearTimeout(inactivityTimer);
                clearTimeout(warningTimer);
                warningShown = false;
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
