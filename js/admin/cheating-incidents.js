/**
 * Admin — cheating incident management UI
 */

const INCIDENTS_API = '../../api/admin/cheating-incidents.php';
const INCIDENTS_REFRESH_MS = 1000;

let cheatingLogModal = null;
let incidentsRefreshTimer = null;

document.addEventListener('DOMContentLoaded', () => {
    const modalEl = document.getElementById('cheatingLogModal');
    if (modalEl) {
        cheatingLogModal = new bootstrap.Modal(modalEl);
    }

    document.getElementById('statusFilter').addEventListener('change', () => loadIncidents());

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            refreshIncidents();
            startIncidentsAutoRefresh();
        } else {
            stopIncidentsAutoRefresh();
        }
    });

    refreshIncidents();
    startIncidentsAutoRefresh();
});

function stopIncidentsAutoRefresh() {
    if (incidentsRefreshTimer) {
        clearInterval(incidentsRefreshTimer);
        incidentsRefreshTimer = null;
    }
}

function startIncidentsAutoRefresh() {
    stopIncidentsAutoRefresh();
    incidentsRefreshTimer = setInterval(() => refreshIncidents(), INCIDENTS_REFRESH_MS);
}

async function refreshIncidents() {
    await Promise.all([loadStats(), loadIncidents()]);
}

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

async function loadStats() {
    try {
        const res = await axios.get(`${INCIDENTS_API}?action=stats`);
        if (!res.data.success) return;

        const data = res.data.data || {};
        document.getElementById('openIncidentsCount').textContent = data.open_incidents ?? 0;
        document.getElementById('totalIncidentsCount').textContent = data.total_incidents ?? 0;
    } catch (err) {
        console.error('Failed to load incident stats:', err);
    }
}

async function loadIncidents() {
    const status = document.getElementById('statusFilter').value;
    const params = new URLSearchParams({ action: 'list', limit: '100' });
    if (status) params.set('status', status);

    try {
        const res = await axios.get(`${INCIDENTS_API}?${params.toString()}`);
        if (!res.data.success) {
            renderIncidents([], 'Failed to load incidents');
            return;
        }

        renderIncidents(res.data.data?.incidents || []);
    } catch (err) {
        console.error('Failed to load incidents:', err);
        renderIncidents([], err.response?.data?.message || 'Failed to load incidents');
    }
}

function renderIncidents(incidents, message = '') {
    const tbody = document.getElementById('incidentsTableBody');

    if (message) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">${escapeHtml(message)}</td></tr>`;
        return;
    }

    if (!incidents.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No incidents match this filter</td></tr>';
        return;
    }

    tbody.innerHTML = incidents.map(inc => `
        <tr>
            <td>
                <strong>${escapeHtml(inc.Trainee_Name)}</strong>
                <div class="small text-muted">${escapeHtml(inc.Academic_Number || '')}</div>
            </td>
            <td>
                <div>${escapeHtml(inc.Exam_Name)}</div>
                <div class="small text-muted">${escapeHtml(inc.Course_Name || '')}</div>
            </td>
            <td><span class="badge bg-danger">${escapeHtml(inc.proctor_summary || inc.latest_violation_label || truncate(inc.Reason, 90))}</span></td>
            <td>${formatDateTime(inc.Timestamp)}</td>
            <td>${inc.violation_count ?? 0}</td>
            <td><span class="badge ${incidentStatusBadge(inc.Status)}">${escapeHtml(inc.Status)}</span></td>
            <td class="incidents-actions-col text-center">
                ${renderIncidentActionsDropdown(inc)}
            </td>
        </tr>
    `).join('');
}

function renderIncidentActionsDropdown(inc) {
    const id = inc.Incident_ID;
    const status = inc.Status;
    const sessionId = inc.Session_ID;
    const items = [
        `<li><button type="button" class="dropdown-item" onclick="viewCheatingLogs(${sessionId})"><i class="bi bi-journal-text me-2"></i>View log</button></li>`,
        '<li><hr class="dropdown-divider"></li>',
    ];

    if (status !== 'Reviewed') {
        items.push(`<li><button type="button" class="dropdown-item" onclick="updateIncidentStatus(${id}, 'Reviewed')"><i class="bi bi-check2-circle me-2"></i>Mark reviewed</button></li>`);
    }
    if (status !== 'Dismissed') {
        items.push(`<li><button type="button" class="dropdown-item" onclick="updateIncidentStatus(${id}, 'Dismissed')"><i class="bi bi-x-circle me-2"></i>Dismiss</button></li>`);
    }
    if (status !== 'Escalated') {
        items.push(`<li><button type="button" class="dropdown-item text-danger" onclick="updateIncidentStatus(${id}, 'Escalated')"><i class="bi bi-exclamation-octagon me-2"></i>Escalate</button></li>`);
    }
    if (status !== 'Open') {
        items.push(`<li><button type="button" class="dropdown-item" onclick="updateIncidentStatus(${id}, 'Open')"><i class="bi bi-arrow-counterclockwise me-2"></i>Reopen</button></li>`);
    }

    return `
        <div class="dropdown">
            <button
                type="button"
                class="btn btn-sm btn-link text-secondary p-0 incidents-actions-trigger"
                data-bs-toggle="dropdown"
                data-bs-popper-config='{"strategy":"fixed"}'
                aria-expanded="false"
                title="More actions"
            >
                <i class="bi bi-three-dots-vertical" style="font-size: 1.25rem;"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">${items.join('')}</ul>
        </div>
    `;
}

async function updateIncidentStatus(incidentId, status) {
    const labels = {
        Reviewed: 'mark this incident as reviewed',
        Dismissed: 'dismiss this incident',
        Escalated: 'escalate this incident',
        Open: 'reopen this incident',
    };

    const result = await Swal.fire({
        title: 'Update incident status?',
        text: `Are you sure you want to ${labels[status] || 'update this incident'}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, update',
        cancelButtonText: 'Cancel',
    });

    if (!result.isConfirmed) return;

    try {
        const res = await axios.post(INCIDENTS_API, {
            action: 'update_status',
            incident_id: incidentId,
            status,
        });

        if (res.data.success) {
            showAlert(res.data.data?.message || 'Incident updated');
            loadStats();
            loadIncidents();
        }
    } catch (err) {
        Swal.fire('Error', err.response?.data?.message || 'Failed to update incident', 'error');
    }
}

async function viewCheatingLogs(sessionId) {
    if (!sessionId || !cheatingLogModal) return;

    const titleEl = document.getElementById('cheatingLogModalLabel');
    const bodyEl = document.getElementById('cheatingLogModalBody');
    titleEl.textContent = 'Proctoring Log';
    bodyEl.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    cheatingLogModal.show();

    try {
        const res = await axios.get(`${INCIDENTS_API}?action=logs&session_id=${sessionId}`);
        if (!res.data.success) {
            throw new Error(res.data.message || 'Failed to load log');
        }
        renderCheatingLogModal(res.data.data || {});
    } catch (err) {
        bodyEl.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(err.response?.data?.message || err.message || 'Failed to load proctoring log')}</div>`;
    }
}

function renderCheatingLogModal(data) {
    const bodyEl = document.getElementById('cheatingLogModalBody');
    const titleEl = document.getElementById('cheatingLogModalLabel');
    const logs = data.logs || [];
    const academic = data.trainee?.Academic_Number || '—';
    const traineeName = data.trainee?.Fullname || 'Trainee';
    titleEl.textContent = `Proctoring Log — ${traineeName}`;

    const examName = data.exam?.Exam_Name || '—';
    const started = formatDateTime(data.session?.Time_Started);
    const ended = data.session?.Time_Ended ? formatDateTime(data.session.Time_Ended) : 'In progress / not submitted';

    if (!logs.length) {
        bodyEl.innerHTML = `
            <div class="mb-3 small text-muted">
                <div><strong>Exam:</strong> ${escapeHtml(examName)}</div>
                <div><strong>Academic No.:</strong> ${escapeHtml(academic)}</div>
                <div><strong>Session:</strong> ${started} → ${escapeHtml(ended)}</div>
            </div>
            <p class="text-muted mb-0">No proctoring events were recorded for this session.</p>
        `;
        return;
    }

    bodyEl.innerHTML = `
        <div class="mb-3 small text-muted">
            <div><strong>Exam:</strong> ${escapeHtml(examName)}</div>
            <div><strong>Academic No.:</strong> ${escapeHtml(academic)}</div>
            <div><strong>Session:</strong> ${started} → ${escapeHtml(ended)}</div>
            <div><strong>Total events:</strong> ${logs.length}</div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th style="width: 170px;">Time</th>
                        <th>Incident</th>
                    </tr>
                </thead>
                <tbody>
                    ${logs.map(log => `
                        <tr>
                            <td><small>${formatDateTime(log.Timestamp)}</small></td>
                            <td>${escapeHtml(log.proctor_line || formatProctorLogLine(academic, traineeName, log.event_label || log.Type))}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

function incidentStatusBadge(status) {
    switch (status) {
        case 'Open': return 'bg-warning text-dark';
        case 'Reviewed': return 'bg-info';
        case 'Dismissed': return 'bg-secondary';
        case 'Escalated': return 'bg-danger';
        default: return 'bg-secondary';
    }
}

function truncate(text, max) {
    if (!text) return '';
    return text.length > max ? `${text.substring(0, max)}…` : text;
}

function formatProctorLogLine(academicNumber, fullname, eventLabel) {
    const academic = academicNumber && academicNumber !== '—' ? academicNumber : '—';
    const name = fullname || 'Trainee';
    const label = eventLabel || 'Suspicious activity';
    return `${academic}, ${name} — ${label}`;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

function formatDateTime(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (Number.isNaN(d.getTime())) return '—';
    return d.toLocaleString();
}

window.viewCheatingLogs = viewCheatingLogs;
window.updateIncidentStatus = updateIncidentStatus;
