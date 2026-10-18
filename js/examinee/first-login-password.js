// First-login password reset prompt

const FIRST_LOGIN_CHANGE_PASSWORD_API = '../../api/auth/change-password.php';

function collectPasswordResetFormValues() {
    const current = document.getElementById('curPwd').value;
    const next = document.getElementById('newPwd').value;
    const confirm = document.getElementById('confPwd').value;

    if (!current || !next || !confirm) {
        Swal.showValidationMessage('All fields are required');
        return false;
    }
    if (next !== confirm) {
        Swal.showValidationMessage('New password and confirmation do not match');
        return false;
    }
    if (next === current) {
        Swal.showValidationMessage('New password must be different from your current password');
        return false;
    }

    const passwordError = validatePasswordPolicy(next);
    if (passwordError) {
        Swal.showValidationMessage(passwordError);
        return false;
    }

    return { current, next, confirm };
}

function dismissFirstLoginPasswordPrompt() {
    axios.post(FIRST_LOGIN_CHANGE_PASSWORD_API, { action: 'dismiss_must_change' })
        .then((res) => {
            if (res.data.success) {
                Swal.fire('Notice', res.data.message || 'You can change your password later from Profile.', 'info');
            }
        })
        .catch(() => {});
}

function submitFirstLoginPasswordReset(values) {
    axios.post(FIRST_LOGIN_CHANGE_PASSWORD_API, {
        current_password: values.current,
        new_password: values.next,
        confirm_password: values.confirm
    }).then((res) => {
        if (res.data.success) {
            Swal.fire('Success', 'Password updated successfully', 'success');
        } else {
            Swal.fire('Error', res.data.message || 'Failed to update password', 'error')
                .then(() => openPasswordResetModal());
        }
    }).catch((err) => {
        const msg = err.response?.data?.message || 'Failed to update password';
        Swal.fire('Error', msg, 'error').then(() => openPasswordResetModal());
    });
}

function openPasswordResetModal() {
    Swal.fire({
        title: 'Reset Password',
        html: getPasswordChangeFormHtml(),
        width: window.matchMedia('(max-width: 575.98px)').matches ? '92%' : '32rem',
        didOpen: () => {
            bindPasswordChangeFormValidation();
        },
        focusConfirm: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        confirmButtonText: 'Reset Password',
        showCancelButton: true,
        cancelButtonText: 'Cancel',
        preConfirm: collectPasswordResetFormValues
    }).then((result) => {
        if (result.dismiss === Swal.DismissReason.cancel) {
            dismissFirstLoginPasswordPrompt();
            return;
        }
        if (!result.value) return;
        submitFirstLoginPasswordReset(result.value);
    });
}

if (typeof MUST_CHANGE_PASSWORD !== 'undefined' && MUST_CHANGE_PASSWORD) {
    setTimeout(openPasswordResetModal, 500);
}
