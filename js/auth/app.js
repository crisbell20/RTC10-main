const authRequestConfig = {
    headers: { 'Content-Type': 'application/json' },
    withCredentials: true,
};

document.getElementById('loginForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const submitButton = this.querySelector('button[type="submit"]');
    if (submitButton?.disabled) {
        return;
    }

    const identifier = document.getElementById('loginId').value.trim();
    const password = document.getElementById('password').value;

    if (!identifier || !password) {
        Swal.fire('Error', 'Please enter your academic number or email and password', 'error');
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
                const num1 = Number(response.data.captcha_num1);
                const num2 = Number(response.data.captcha_num2);

                if (!Number.isFinite(num1) || !Number.isFinite(num2)) {
                    Swal.fire('Error', 'Security verification could not be started. Please try logging in again.', 'error');
                    return;
                }

                awaitingCaptcha = true;

                Swal.fire({
                    title: 'Security Verification',
                    html: `
                        <div class="text-center mb-3">
                            <i class="bi bi-shield-check" style="font-size: 3rem; color: #2563eb;"></i>
                        </div>
                        <p class="mb-3">Please solve this simple math problem to continue:</p>
                        <div class="alert alert-info">
                            <h3 class="mb-0">${num1} + ${num2} = ?</h3>
                        </div>
                        <input type="number" id="captchaAnswer" class="swal2-input" placeholder="Enter your answer" autofocus>
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
                }).then(async (result) => {
                    if (!result.isConfirmed) {
                        Swal.fire('Cancelled', 'Login cancelled', 'info');
                        return;
                    }

                    try {
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
                            const role = response.data.user.role;
                            if (role === 'Admin') {
                                window.location.href = 'html/admin/dashboard.php';
                            } else if (role === 'CCMD') {
                                window.location.href = 'html/ccmd/dashboard.php';
                            } else {
                                window.location.href = 'html/examinee/dashboard.php';
                            }
                        });
                    } catch (error) {
                        const msg = error.response?.data?.message || 'Verification failed';
                        Swal.fire('Error', msg, 'error');
                    }
                }).finally(() => {
                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                });
            } else {
                Swal.fire('Success', 'Login successful', 'success').then(() => {
                    window.location.href = 'html/examinee/dashboard.php';
                });
            }
        } else {
            Swal.fire('Error', response.data.message || 'Login failed', 'error');
        }
    } catch (error) {
        const msg = error.response?.data?.message || error.message || 'An error occurred during login';
        Swal.fire('Error', msg, 'error');
    } finally {
        if (submitButton && !awaitingCaptcha) {
            submitButton.disabled = false;
        }
    }
});
