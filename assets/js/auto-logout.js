/**
 * Auto Logout on Inactivity
 * Logs out user after 5 minutes of inactivity
 */

(function() {
    // Configuration
    const INACTIVITY_TIMEOUT = 5 * 60 * 1000; // 5 minutes in milliseconds
    const WARNING_TIME = 30 * 1000; // Show warning 30 seconds before logout
    
    let inactivityTimer;
    let warningTimer;
    let warningShown = false;

    // Events that reset the inactivity timer
    const activityEvents = [
        'mousedown',
        'mousemove',
        'keypress',
        'scroll',
        'touchstart',
        'click'
    ];

    // Reset the inactivity timer
    function resetTimer() {
        // Clear existing timers
        clearTimeout(inactivityTimer);
        clearTimeout(warningTimer);
        warningShown = false;

        // Set warning timer (30 seconds before logout)
        warningTimer = setTimeout(showWarning, INACTIVITY_TIMEOUT - WARNING_TIME);

        // Set logout timer
        inactivityTimer = setTimeout(logout, INACTIVITY_TIMEOUT);
    }

    // Show warning before logout
    function showWarning() {
        if (warningShown) return;
        warningShown = true;

        // Check if SweetAlert2 is available
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Session Timeout Warning',
                text: 'You will be logged out in 30 seconds due to inactivity. Move your mouse or press any key to stay logged in.',
                icon: 'warning',
                timer: 30000,
                timerProgressBar: true,
                showConfirmButton: true,
                confirmButtonText: 'Stay Logged In',
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed || result.dismiss === Swal.DismissReason.timer) {
                    // If user clicked or timer expired, check if they're still inactive
                    if (result.dismiss === Swal.DismissReason.timer) {
                        // Timer expired, logout
                        logout();
                    } else {
                        // User clicked, reset timer
                        resetTimer();
                    }
                }
            });
        } else {
            // Fallback to native alert if SweetAlert2 is not available
            const stayLoggedIn = confirm('You will be logged out in 30 seconds due to inactivity. Click OK to stay logged in.');
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

    // Logout function
    function logout() {
        // Show logout message
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

    // Initialize auto-logout
    function init() {
        // Set up event listeners for user activity
        activityEvents.forEach(event => {
            document.addEventListener(event, resetTimer, true);
        });

        // Start the timer
        resetTimer();

        console.log('Auto-logout initialized: 5 minutes inactivity timeout');
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
