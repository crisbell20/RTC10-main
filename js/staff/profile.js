// Staff profile (ADMIN/CCMD)

const PROFILE_API = '../../api/auth/profile.php';
const CHANGE_PASSWORD_API = '../../api/auth/change-password.php';

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('changePasswordBtn')?.addEventListener('click', () => openPasswordChangeModal());
    document.getElementById('saveEmailBtn')?.addEventListener('click', saveProfileEmail);
    loadProfile();
});

async function loadProfile() {
    try {
        const response = await axios.get(PROFILE_API);
        if (response.data.success) {
            renderProfile(response.data.data);
        } else {
            showFatalError(response.data.message || 'Failed to load profile');
        }
    } catch (error) {
        const msg = error.response?.data?.message || 'Failed to load profile. Please try again.';
        showFatalError(msg);
    }
}

function renderProfile(data) {
    const profile = data.profile || {};

    document.getElementById('loadingMessage').style.display = 'none';
    document.getElementById('profileContent').style.display = 'block';

    document.getElementById('profileAvatar').textContent = getInitials(profile.Fullname);
    document.getElementById('profileFullname').textContent = profile.Fullname || '—';
    document.getElementById('profileMeta').textContent = profile.Role_Name
        ? `${profile.Role_Name} account`
        : 'Staff account';

    document.getElementById('profileBadges').innerHTML = [
        statusBadge(profile.Status),
        profile.Role_Name ? `<span class="badge bg-light text-dark">${escapeHtml(profile.Role_Name)}</span>` : ''
    ].filter(Boolean).join('');

    document.getElementById('accountInfoGrid').innerHTML = [
        infoItem('Last Name', profile.Lastname || '—'),
        infoItem('First Name', profile.Firstname || '—'),
        infoItem('Middle Name', profile.Middlename || '—'),
        infoItem('Role', profile.Role_Name || '—'),
        infoItem('Account Status', profile.Status),
        infoItem('Created', formatDateTime(profile.Date_Created))
    ].join('');

    const emailInput = document.getElementById('profileEmailInput');
    if (emailInput) {
        emailInput.value = profile.Email || '';
    }

    document.getElementById('securityInfoGrid').innerHTML = [
        infoItem('Last Login', data.last_login ? formatDateTime(data.last_login) : '—')
    ].join('');
}

function infoItem(label, value) {
    return `
        <div class="profile-info-item">
            <label>${escapeHtml(label)}</label>
            <span>${escapeHtml(String(value ?? '—'))}</span>
        </div>
    `;
}

function statusBadge(status) {
    const map = {
        Active: 'status-badge-active',
        Inactive: 'status-badge-inactive',
        Suspended: 'status-badge-suspended'
    };
    const cls = map[status] || 'bg-secondary';
    return `<span class="badge ${cls}">${escapeHtml(status || 'Unknown')}</span>`;
}

function getInitials(name) {
    if (!name) return '?';
    const parts = name.trim().split(/\s+/).filter(Boolean);
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
}

function openPasswordChangeModal() {
    Swal.fire({
        title: 'Change Password',
        html: getPasswordChangeFormHtml(),
        didOpen: () => {
            bindPasswordChangeFormValidation();
        },
        focusConfirm: false,
        confirmButtonText: 'Update Password',
        showCancelButton: true,
        cancelButtonText: 'Cancel',
        preConfirm: () => {
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
    }).then(result => {
        if (!result.value) return;

        axios.post(CHANGE_PASSWORD_API, {
            current_password: result.value.current,
            new_password: result.value.next,
            confirm_password: result.value.confirm
        }).then(res => {
            if (res.data.success) {
                Swal.fire('Success', 'Password updated successfully', 'success');
                loadProfile();
            } else {
                Swal.fire('Error', res.data.message || 'Failed to update password', 'error')
                    .then(() => openPasswordChangeModal());
            }
        }).catch(err => {
            const msg = err.response?.data?.message || 'Failed to update password';
            Swal.fire('Error', msg, 'error').then(() => openPasswordChangeModal());
        });
    });
}

async function saveProfileEmail() {
    const emailInput = document.getElementById('profileEmailInput');
    const saveBtn = document.getElementById('saveEmailBtn');
    if (!emailInput || !saveBtn) return;

    const email = emailInput.value.trim();
    if (!email) {
        Swal.fire('Validation', 'Email is required', 'warning');
        return;
    }

    saveBtn.disabled = true;
    try {
        const res = await axios.post(PROFILE_API, { email });
        if (res.data.success) {
            Swal.fire('Saved', res.data.data?.message || 'Email updated successfully', 'success');
            loadProfile();
        } else {
            Swal.fire('Error', res.data.message || 'Failed to update email', 'error');
        }
    } catch (err) {
        Swal.fire('Error', err.response?.data?.message || 'Failed to update email', 'error');
    } finally {
        saveBtn.disabled = false;
    }
}

function showFatalError(message) {
    document.getElementById('loadingMessage').style.display = 'none';
    const fallback = window.STAFF_PROFILE_DASHBOARD_URL || 'dashboard.php';
    Swal.fire({
        title: 'Error',
        text: message,
        icon: 'error',
        confirmButtonText: 'Go to Dashboard'
    }).then(() => {
        window.location.href = fallback;
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDateTime(dateStr) {
    if (!dateStr) return '—';
    const date = new Date(dateStr);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}
