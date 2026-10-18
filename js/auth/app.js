const authRequestConfig = {
    headers: { 'Content-Type': 'application/json' },
    withCredentials: true,
};

function redirectAfterLogin(role) {
    if (role === 'Admin') {
        window.location.replace('html/admin/dashboard.php');
    } else if (role === 'CCMD') {
        window.location.replace('html/ccmd/dashboard.php');
    } else {
        window.location.replace('html/examinee/dashboard.php');
    }
}

function getAuthErrorMessage(error, fallback = 'An error occurred during login') {
    return error.response?.data?.message || error.message || fallback;
}

function clearLoginFields() {
    const loginId = document.getElementById('loginId');
    const password = document.getElementById('password');
    const togglePassword = document.getElementById('togglePassword');

    if (loginId) {
        loginId.value = '';
    }
    if (password) {
        password.value = '';
        password.type = 'password';
    }
    if (togglePassword) {
        const toggleIcon = togglePassword.querySelector('i');
        if (toggleIcon) {
            toggleIcon.classList.remove('bi-eye-fill');
            toggleIcon.classList.add('bi-eye-slash-fill');
        }
        togglePassword.setAttribute('aria-label', 'Show password');
    }
}

function showSecurityVerificationModal(captchaData) {
    const num1 = Number(captchaData.captcha_num1);
    const num2 = Number(captchaData.captcha_num2);

    if (!Number.isFinite(num1) || !Number.isFinite(num2)) {
        return Promise.reject(new Error('Security verification could not be started. Please try logging in again.'));
    }

    return Swal.fire({
        title: 'Security Verification',
        width: 'min(28rem, calc(100vw - 2rem))',
        customClass: {
            popup: 'login-captcha-popup',
            htmlContainer: 'login-captcha-body',
            actions: 'login-captcha-actions',
        },
        html: `
            <div class="text-center mb-3">
                <i class="bi bi-shield-check" style="font-size: 3rem; color: #2563eb;"></i>
            </div>
            <p class="mb-3">Please solve this simple math problem to continue:</p>
            <div class="alert alert-info">
                <h3 class="mb-0">${num1} + ${num2} = ?</h3>
            </div>
            <input type="text" inputmode="numeric" pattern="[0-9]*" id="captchaAnswer" class="form-control captcha-answer" placeholder="Enter your answer" autocomplete="off" autofocus>
        `,
        showCancelButton: true,
        confirmButtonText: 'Verify',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#2563eb',
        allowOutsideClick: false,
        preConfirm: () => {
            const raw = document.getElementById('captchaAnswer').value;
            if (raw === null || String(raw).trim() === '') {
                Swal.showValidationMessage('Please enter an answer');
                return false;
            }
            const parsed = parseInt(String(raw).trim(), 10);
            if (Number.isNaN(parsed)) {
                Swal.showValidationMessage('Please enter a valid number');
                return false;
            }
            return parsed;
        },
    });
}

document.getElementById('loginForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const submitButton = this.querySelector('button[type="submit"]');
    if (submitButton?.disabled) {
        return;
    }

    const identifier = document.getElementById('loginId').value.trim();
    const password = document.getElementById('password').value;

    if (!identifier || !password) {
        Swal.fire('Error', 'Please enter your badge number or email and password', 'error');
        return;
    }

    if (submitButton) {
        submitButton.disabled = true;
    }

    let awaitingCaptcha = false;

    try {
        const response = await axios.post('api/auth/auth.php', {
            identifier,
            password,
        }, authRequestConfig);

        if (response.data.success) {
            if (response.data.requires_captcha) {
                awaitingCaptcha = true;

                try {
                    const result = await showSecurityVerificationModal(response.data);
                    if (!result.isConfirmed) {
                        Swal.fire('Cancelled', 'Login cancelled', 'info');
                        return;
                    }

                    await axios.post('api/auth/verify-captcha.php', {
                        answer: result.value,
                    }, authRequestConfig);

                    Swal.fire({
                        icon: 'success',
                        title: 'Verified!',
                        text: 'Login successful',
                        timer: 1500,
                        showConfirmButton: false,
                    }).then(() => {
                        redirectAfterLogin(response.data.user.role);
                    });
                } catch (error) {
                    if (error.message && !error.response) {
                        Swal.fire('Error', error.message, 'error');
                        return;
                    }
                    clearLoginFields();
                    const msg = getAuthErrorMessage(error, 'Verification failed');
                    Swal.fire('Error', msg, error.response?.status === 429 ? 'warning' : 'error');
                } finally {
                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                }
                return;
            }

            Swal.fire('Success', 'Login successful', 'success').then(() => {
                redirectAfterLogin(response.data.user?.role || 'Examinee');
            });
        } else {
            clearLoginFields();
            Swal.fire('Error', response.data.message || 'Login failed', response.data.locked ? 'warning' : 'error');
        }
    } catch (error) {
        clearLoginFields();
        const msg = getAuthErrorMessage(error);
        const isLocked = error.response?.status === 429 || error.response?.data?.locked;
        Swal.fire('Error', msg, isLocked ? 'warning' : 'error');
    } finally {
        if (submitButton && !awaitingCaptcha) {
            submitButton.disabled = false;
        }
    }
});
