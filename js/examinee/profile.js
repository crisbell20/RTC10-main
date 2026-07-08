/**
 * Examinee Profile Page
 */

const PROFILE_API = '../../api/examinee/profile.php';
const CHANGE_PASSWORD_API = '../../api/auth/change-password.php';

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('logoutBtn').addEventListener('click', handleLogout);
    document.getElementById('changePasswordBtn').addEventListener('click', openPasswordChangeModal);
    loadProfile();

    if (typeof MUST_CHANGE_PASSWORD !== 'undefined' && MUST_CHANGE_PASSWORD) {
        document.getElementById('passwordAlertBanner').classList.remove('d-none');
        setTimeout(openPasswordChangeModal, 600);
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
    const summary = data.exam_summary || {};

    document.getElementById('loadingMessage').style.display = 'none';
    document.getElementById('profileContent').style.display = 'block';

    document.getElementById('profileAvatar').textContent = getInitials(profile.Fullname);
    document.getElementById('profileFullname').textContent = profile.Fullname || '—';

    const metaParts = [];
    if (profile.Personnel_Rank) metaParts.push(`Rank: ${profile.Personnel_Rank}`);
    if (profile.Academic_Number) metaParts.push(`Academic No.: ${profile.Academic_Number}`);
    document.getElementById('profileMeta').textContent = metaParts.length
        ? metaParts.join('  •  ')
        : 'Trainee account';

    document.getElementById('profileBadges').innerHTML = [
        statusBadge(profile.Status),
        profile.Role_Name ? `<span class="badge bg-light text-dark">${escapeHtml(profile.Role_Name)}</span>` : '',
        profile.Must_Change_Password ? '<span class="badge bg-warning text-dark">Password reset required</span>' : ''
    ].filter(Boolean).join('');

    document.getElementById('accountInfoGrid').innerHTML = [
        infoItem('Full Name', profile.Fullname),
        infoItem('Email', profile.Email),
        infoItem('Username', profile.Username),
        infoItem('Academic Number', profile.Academic_Number || '—'),
        infoItem('Personnel Rank', profile.Personnel_Rank || '—'),
        infoItem('Account Status', profile.Status),
        infoItem('Member Since', formatDateTime(profile.Date_Created))
    ].join('');

    document.getElementById('securityInfoGrid').innerHTML = [
        infoItem('Username', profile.Username),
        infoItem('Last Login', data.last_login ? formatDateTime(data.last_login) : '—'),
        infoItem('Password Status', profile.Must_Change_Password ? 'Reset required' : 'Up to date')
    ].join('');

    renderEnrollments(enrollments);
    renderExamSummary(summary);

    if (profile.Must_Change_Password) {
        document.getElementById('passwordAlertBanner').classList.remove('d-none');
    }
}

function renderEnrollments(enrollments) {
    const container = document.getElementById('enrollmentSection');

    if (!enrollments.length) {
        container.innerHTML = '<p class="text-muted mb-0">No enrollment records found. Contact your administrator if this is incorrect.</p>';
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
                Enrolled: ${formatDate(row.Date_Enrolled)}
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

function renderExamSummary(summary) {
    document.getElementById('examSummaryGrid').innerHTML = [
        statItem(summary.exams_completed ?? 0, 'Completed'),
        statItem(summary.exams_available ?? 0, 'Available'),
        statItem(summary.average_score > 0 ? `${summary.average_score}%` : '—', 'Average Score'),
        statItem(`${summary.passed_count ?? 0} / ${summary.failed_count ?? 0}`, 'Passed / Failed')
    ].join('');

    const lastExamBlock = document.getElementById('lastExamBlock');
    const lastExam = summary.last_exam;

    if (lastExam && lastExam.exam_title) {
        lastExamBlock.innerHTML = `
            <strong><i class="bi bi-clock-history me-1"></i>Last Exam Taken</strong>
            ${escapeHtml(lastExam.exam_title)} — ${parseFloat(lastExam.Percentage || 0).toFixed(1)}%
            (${escapeHtml(lastExam.Remarks || 'N/A')}) on ${formatDateTime(lastExam.Submission_Date)}
        `;
    } else {
        lastExamBlock.innerHTML = `
            <strong><i class="bi bi-clock-history me-1"></i>Last Exam Taken</strong>
            No completed exams yet.
        `;
    }
}

function infoItem(label, value) {
    return `
        <div class="profile-info-item">
            <label>${escapeHtml(label)}</label>
            <span>${escapeHtml(String(value ?? '—'))}</span>
        </div>
    `;
}

function statItem(value, label) {
    return `
        <div class="profile-stat-item">
            <div class="stat-value">${escapeHtml(String(value))}</div>
            <div class="stat-label">${escapeHtml(label)}</div>
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

function openPasswordChangeModal() {
    Swal.fire({
        title: 'Change Password',
        html: `
            <div class="text-start">
                <label class="form-label">Current Password*</label>
                <input type="password" id="curPwd" class="form-control mb-2">
                <label class="form-label">New Password*</label>
                <input type="password" id="newPwd" class="form-control mb-2">
                <ul class="small text-muted mb-2">
                    <li>8 characters minimum</li>
                    <li>One uppercase letter</li>
                    <li>One lowercase letter</li>
                    <li>One number</li>
                    <li>One special character</li>
                </ul>
                <label class="form-label">Confirm New Password*</label>
                <input type="password" id="confPwd" class="form-control mb-2">
                <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" id="togglePwdVisibility">
                    <label class="form-check-label" for="togglePwdVisibility">Show passwords</label>
                </div>
            </div>
        `,
        didOpen: () => {
            const cur = document.getElementById('curPwd');
            const next = document.getElementById('newPwd');
            const conf = document.getElementById('confPwd');
            const toggle = document.getElementById('togglePwdVisibility');
            if (toggle && cur && next && conf) {
                toggle.addEventListener('change', (e) => {
                    const type = e.target.checked ? 'text' : 'password';
                    cur.type = type;
                    next.type = type;
                    conf.type = type;
                });
            }
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
                document.getElementById('passwordAlertBanner').classList.add('d-none');
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

function handleLogout(e) {
    e.preventDefault();
    Swal.fire({
        title: 'Logout',
        text: 'Are you sure you want to logout?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, logout',
        cancelButtonText: 'No, stay',
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#6b7280'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../../api/auth/logout.php', { method: 'POST' })
                .then(() => { window.location.href = '../../login.php'; })
                .catch(() => { window.location.href = '../../login.php'; });
        }
    });
}

function showFatalError(message) {
    document.getElementById('loadingMessage').style.display = 'none';
    Swal.fire({
        title: 'Error',
        text: message,
        icon: 'error',
        confirmButtonText: 'Go to Dashboard'
    }).then(() => {
        window.location.href = '../examinee/dashboard.php';
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    const date = new Date(dateStr);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
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
