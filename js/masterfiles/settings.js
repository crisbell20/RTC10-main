const SETTINGS_API = '../../api/masterfiles/settings.php';
const ARCHIVE_API = '../../api/masterfiles/archive.php';
const AUDIT_API = '../../api/masterfiles/audit-logs.php';

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('retentionSettingsForm')?.addEventListener('submit', saveSettings);
    document.getElementById('securitySettingsForm')?.addEventListener('submit', saveSecuritySettings);
    document.getElementById('purgeArchiveBtn')?.addEventListener('click', purgeArchivedRecords);
    document.getElementById('purgeAuditBtn')?.addEventListener('click', purgeAuditLogs);
    loadSettings();
});

function showAlert(message, type = 'success') {
    const alertDiv = document.getElementById('messageAlert');
    if (!alertDiv) return;

    alertDiv.innerHTML = `
        <div class="alert alert-${type === 'error' ? 'danger' : 'success'} alert-dismissible fade show" role="alert">
            ${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
}

function getArchiveRetentionDays() {
    return parseInt(document.getElementById('archiveRetentionDays')?.value, 10) || 730;
}

function getAuditRetentionDays() {
    return parseInt(document.getElementById('auditRetentionDays')?.value, 10) || 730;
}

function applyNumberField(input, value, min, max, fallback) {
    if (!input) return;
    if (min != null) input.min = min;
    if (max != null) input.max = max;
    input.value = value ?? fallback;
}

async function loadSettings() {
    const archiveInput = document.getElementById('archiveRetentionDays');
    const auditInput = document.getElementById('auditRetentionDays');
    const maxAttemptsInput = document.getElementById('loginMaxAttempts');
    const lockoutInput = document.getElementById('loginLockoutMinutes');
    const idleInput = document.getElementById('idleTimeoutMinutes');
    const cheatingAttemptLimitInput = document.getElementById('cheatingAttemptLimit');

    try {
        const res = await axios.get(`${SETTINGS_API}?action=get`);
        if (!res.data.success) {
            throw new Error(res.data.message || 'Failed to load settings');
        }

        const data = res.data.data || {};
        applyNumberField(archiveInput, data.archive_retention_days, data.archive_retention_min ?? 30, data.archive_retention_max ?? 3650, 730);
        applyNumberField(auditInput, data.audit_log_retention_days, data.audit_log_retention_min ?? 30, data.audit_log_retention_max ?? 3650, 730);
        applyNumberField(maxAttemptsInput, data.login_max_attempts, data.login_max_attempts_min ?? 3, data.login_max_attempts_max ?? 10, 5);
        applyNumberField(lockoutInput, data.login_lockout_minutes, data.login_lockout_minutes_min ?? 5, data.login_lockout_minutes_max ?? 60, 15);
        applyNumberField(idleInput, data.idle_timeout_minutes, data.idle_timeout_minutes_min ?? 5, data.idle_timeout_minutes_max ?? 60, 5);
        applyNumberField(cheatingAttemptLimitInput, data.cheating_attempt_limit, data.cheating_attempt_limit_min ?? 1, data.cheating_attempt_limit_max ?? 20, 5);
    } catch (err) {
        showAlert(err.response?.data?.message || err.message || 'Failed to load settings', 'error');
    }
}

async function saveSettings(event) {
    event.preventDefault();

    const archiveInput = document.getElementById('archiveRetentionDays');
    const auditInput = document.getElementById('auditRetentionDays');
    const saveBtn = document.getElementById('saveSettingsBtn');

    const payload = {
        action: 'update',
        archive_retention_days: parseInt(archiveInput.value, 10),
        audit_log_retention_days: parseInt(auditInput.value, 10),
    };

    saveBtn.disabled = true;

    try {
        const res = await axios.post(SETTINGS_API, payload);
        if (!res.data.success) {
            throw new Error(res.data.message || 'Failed to save settings');
        }

        const settings = res.data.data?.settings || {};
        archiveInput.value = settings.archive_retention_days ?? payload.archive_retention_days;
        auditInput.value = settings.audit_log_retention_days ?? payload.audit_log_retention_days;
        showAlert(res.data.data?.message || 'System settings saved');
    } catch (err) {
        showAlert(err.response?.data?.message || err.message || 'Failed to save settings', 'error');
    } finally {
        saveBtn.disabled = false;
    }
}

async function saveSecuritySettings(event) {
    event.preventDefault();

    const maxAttemptsInput = document.getElementById('loginMaxAttempts');
    const lockoutInput = document.getElementById('loginLockoutMinutes');
    const idleInput = document.getElementById('idleTimeoutMinutes');
    const cheatingAttemptLimitInput = document.getElementById('cheatingAttemptLimit');
    const saveBtn = document.getElementById('saveSecurityBtn');

    const payload = {
        action: 'update_security',
        login_max_attempts: parseInt(maxAttemptsInput.value, 10),
        login_lockout_minutes: parseInt(lockoutInput.value, 10),
        idle_timeout_minutes: parseInt(idleInput.value, 10),
        cheating_attempt_limit: parseInt(cheatingAttemptLimitInput.value, 10),
    };

    saveBtn.disabled = true;

    try {
        const res = await axios.post(SETTINGS_API, payload);
        if (!res.data.success) {
            throw new Error(res.data.message || 'Failed to save security policy');
        }

        const settings = res.data.data?.settings || {};
        maxAttemptsInput.value = settings.login_max_attempts ?? payload.login_max_attempts;
        lockoutInput.value = settings.login_lockout_minutes ?? payload.login_lockout_minutes;
        idleInput.value = settings.idle_timeout_minutes ?? payload.idle_timeout_minutes;
        cheatingAttemptLimitInput.value = settings.cheating_attempt_limit ?? payload.cheating_attempt_limit;
        showAlert(res.data.data?.message || 'Security policy saved');
    } catch (err) {
        showAlert(err.response?.data?.message || err.message || 'Failed to save security policy', 'error');
    } finally {
        saveBtn.disabled = false;
    }
}

function formatPurgeBreakdown(breakdown) {
    if (!breakdown || typeof breakdown !== 'object') return '';
    const labels = {
        exams: 'exams',
        courses: 'courses',
        batches: 'batches',
        sections: 'sections',
        questions: 'questions',
        users: 'users',
    };
    const parts = Object.entries(labels)
        .map(([key, label]) => {
            const count = breakdown[key] || 0;
            return count > 0 ? `${count} ${label}` : null;
        })
        .filter(Boolean);
    return parts.length ? ` (${parts.join(', ')})` : '';
}

async function purgeArchivedRecords() {
    const defaultDays = getArchiveRetentionDays();
    let eligibleCount = 0;
    let breakdown = null;

    try {
        const preview = await axios.get(`${ARCHIVE_API}?action=purge_preview&days=${defaultDays}`);
        if (preview.data.success) {
            eligibleCount = preview.data.eligible_count || 0;
            breakdown = preview.data.eligible_breakdown || null;
        }
    } catch (err) {
        console.error('Failed to load purge preview', err);
    }

    const breakdownText = formatPurgeBreakdown(breakdown);
    const eligibleNote = eligibleCount > 0
        ? `This will permanently delete <strong>${eligibleCount}</strong> archived record${eligibleCount === 1 ? '' : 's'} that exceed the configured retention period.${breakdownText}`
        : 'This will permanently delete archived records that exceed the configured retention period.';

    Swal.fire({
        title: 'Are you sure?',
        html: `${eligibleNote}<br><br><span class="text-danger">This action cannot be undone.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, purge archived records',
        confirmButtonColor: '#ef4444',
    }).then(async result => {
        if (!result.isConfirmed) return;
        try {
            const res = await axios.post(ARCHIVE_API, { action: 'purge', days: defaultDays });
            if (res.data.success) {
                Swal.fire('Done', res.data.message, 'success');
            }
        } catch (err) {
            Swal.fire('Error', err.response?.data?.message || 'Purge failed', 'error');
        }
    });
}

function purgeAuditLogs() {
    const defaultDays = getAuditRetentionDays();

    Swal.fire({
        title: 'Are you sure?',
        html: 'This will permanently delete audit log entries that exceed the configured retention period.<br><br><span class="text-danger">This action cannot be undone.</span>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, purge audit logs',
        confirmButtonColor: '#ef4444',
    }).then(async result => {
        if (!result.isConfirmed) return;
        try {
            const res = await axios.post(AUDIT_API, { action: 'purge', days: defaultDays });
            if (res.data.success) {
                Swal.fire('Done', res.data.message, 'success');
            }
        } catch (err) {
            Swal.fire('Error', err.response?.data?.message || 'Purge failed', 'error');
        }
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}
