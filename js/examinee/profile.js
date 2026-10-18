// Examinee profile

const PROFILE_API = '../../api/examinee/profile.php';
const CHANGE_PASSWORD_API = '../../api/auth/change-password.php';

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('changePasswordBtn').addEventListener('click', () => openPasswordChangeModal(false));
    document.getElementById('saveEmailBtn')?.addEventListener('click', saveProfileEmail);
    loadProfile();

    if (typeof MUST_CHANGE_PASSWORD !== 'undefined' && MUST_CHANGE_PASSWORD) {
        document.getElementById('passwordAlertBanner').classList.remove('d-none');
        setTimeout(() => openPasswordChangeModal(true), 600);
    }
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
        console.error('Profile load error:', error);
        const msg = error.response?.data?.message || 'Failed to load profile. Please try again.';
        showFatalError(msg);
    }
}

function renderProfile(data) {
    const profile = data.profile || {};
    const enrollments = data.enrollments || [];

    document.getElementById('loadingMessage').style.display = 'none';
    document.getElementById('profileContent').style.display = 'block';

    document.getElementById('profileAvatar').textContent = getInitials(profile.Fullname);
    document.getElementById('profileFullname').textContent = profile.Fullname || '—';

    const metaParts = [];
    if (profile.Personnel_Rank) metaParts.push(`Rank: ${profile.Personnel_Rank}`);
    if (profile.Badge_Number) metaParts.push(`Badge No.: ${profile.Badge_Number}`);
    document.getElementById('profileMeta').textContent = metaParts.length
        ? metaParts.join('  •  ')
        : 'Trainee account';

    document.getElementById('profileBadges').innerHTML = [
        statusBadge(profile.Status),
        profile.Role_Name ? `<span class="badge bg-light text-dark">${escapeHtml(profile.Role_Name)}</span>` : ''
    ].filter(Boolean).join('');

    document.getElementById('accountInfoGrid').innerHTML = [
        infoItem('Last Name', profile.Lastname || '—'),
        infoItem('First Name', profile.Firstname || '—'),
        infoItem('Middle Name', profile.Middlename || '—'),
        infoItem('Badge Number', profile.Badge_Number || '—'),
        infoItem('Personnel Rank', profile.Personnel_Rank || '—'),
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

    renderEnrollments(enrollments);

    if (profile.Must_Change_Password) {
        document.getElementById('passwordAlertBanner').classList.remove('d-none');
    }
}

function renderEnrollments(enrollments) {
    const container = document.getElementById('enrollmentSection');

    if (!enrollments.length) {
        container.innerHTML = '<p class="text-muted mb-0">No assignment records found. Contact your administrator if this is incorrect.</p>';
        return;
    }

    container.innerHTML = enrollments.map(row => `
        <div class="enrollment-card">
            <h6>${escapeHtml(row.Course_Name || 'Unknown Course')}</h6>
            <div class="enrollment-meta">
                ${escapeHtml(row.Batch_Name || '—')}
                ${row.Section_Name ? ' • ' + escapeHtml(row.Section_Name) : ''}
            </div>
            <div class="enrollment-meta">
                Assigned: ${formatDate(row.Date_Enrolled)}
                ${row.Date_Started ? ' • Started: ' + formatDate(row.Date_Started) : ''}
                ${row.Date_Ended ? ' • Ends: ' + formatDate(row.Date_Ended) : ''}
            </div>
            <div class="enrollment-badges">
                ${enrollmentStatusBadge(row.Enrollment_Status)}
                ${row.Batch_Status ? `<span class="badge bg-secondary">${escapeHtml(row.Batch_Status)} Batch</span>` : ''}
            </div>
        </div>
    `).join('');
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

function enrollmentStatusBadge(status) {
    const map = {
        Active: 'status-badge-active',
        Completed: 'status-badge-completed',
        Dropped: 'status-badge-dropped'
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

function openPasswordChangeModal(isFirstLoginPrompt = false) {
    Swal.fire({
        title: isFirstLoginPrompt ? 'Reset Password' : 'Change Password',
        html: getPasswordChangeFormHtml(),
        didOpen: () => {
            bindPasswordChangeFormValidation();
        },
        focusConfirm: false,
        confirmButtonText: isFirstLoginPrompt ? 'Reset Password' : 'Update Password',
        showCancelButton: true,
        cancelButtonText: 'Cancel',
        allowOutsideClick: !isFirstLoginPrompt,
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
        if (result.dismiss === Swal.DismissReason.cancel && isFirstLoginPrompt) {
            dismissMustChangePassword();
            return;
        }
        if (!result.value) return;

        axios.post(CHANGE_PASSWORD_API, {
            current_password: result.value.current,
            new_password: result.value.next,
            confirm_password: result.value.confirm
        }).then(res => {
            if (res.data.success) {
                Swal.fire('Success', 'Password updated successfully', 'success');
                document.getElementById('passwordAlertBanner').classList.add('d-none');
                loadProfile();
            } else {
                Swal.fire('Error', res.data.message || 'Failed to update password', 'error')
                    .then(() => openPasswordChangeModal(isFirstLoginPrompt));
            }
        }).catch(err => {
            const msg = err.response?.data?.message || 'Failed to update password';
            Swal.fire('Error', msg, 'error').then(() => openPasswordChangeModal(isFirstLoginPrompt));
        });
    });
}

async function dismissMustChangePassword() {
    try {
        const res = await axios.post(CHANGE_PASSWORD_API, { action: 'dismiss_must_change' });
        if (res.data.success) {
            document.getElementById('passwordAlertBanner')?.classList.add('d-none');
            Swal.fire('Notice', res.data.message || 'You can change your password later from Profile.', 'info');
            loadProfile();
        }
    } catch (err) {
        Swal.fire('Error', err.response?.data?.message || 'Could not dismiss password prompt', 'error');
    }
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
    Swal.fire({
        title: 'Error',
        text: message,
        icon: 'error',
        confirmButtonText: 'Go to Exams'
    }).then(() => {
        window.location.href = 'dashboard.php';
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateStr) {
    const date = parseExamDateTime(dateStr);
    if (!date) return '—';
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatDateTime(dateStr) {
    const date = parseExamDateTime(dateStr);
    if (!date) return '—';
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}
