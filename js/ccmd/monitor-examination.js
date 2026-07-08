/**
 * CCMD Live Exam Monitoring
 */

const MONITOR_API = '../../api/ccmd/monitor.php';

let currentExamId = null;
let currentPage = 1;
let refreshTimer = null;
let searchTimer = null;
let liveExams = [];

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('refreshBtn').addEventListener('click', () => refreshAll());
    document.getElementById('examSelect').addEventListener('change', (e) => {
        currentExamId = e.target.value ? parseInt(e.target.value, 10) : null;
        currentPage = 1;
        if (currentExamId) {
            refreshAll();
            startAutoRefresh();
        }
    });
    document.getElementById('statusFilter').addEventListener('change', () => {
        currentPage = 1;
        loadSessions();
    });
    document.getElementById('searchInput').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            currentPage = 1;
            loadSessions();
        }, 350);
    });
    document.getElementById('prevPageBtn').addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            loadSessions();
        }
    });
    document.getElementById('nextPageBtn').addEventListener('click', () => {
        currentPage++;
        loadSessions();
    });

    loadLiveExams();
});

async function loadLiveExams() {
    try {
        const res = await axios.get(`${MONITOR_API}?action=live_exams`);
        if (!res.data.success) {
            throw new Error(res.data.message || 'Failed to load exams');
        }

        liveExams = res.data.data || [];
        const select = document.getElementById('examSelect');

        if (!liveExams.length) {
            document.getElementById('monitorEmpty').classList.remove('d-none');
            document.getElementById('monitorBody').classList.add('d-none');
            select.innerHTML = '<option value="">No published exams</option>';
            return;
        }

        select.innerHTML = liveExams.map(exam => `
            <option value="${exam.Exam_ID}">
                ${escapeHtml(exam.Exam_Name)} (${exam.Active_Participants || 0} active)
            </option>
        `).join('');

        document.getElementById('monitorEmpty').classList.add('d-none');
        document.getElementById('monitorBody').classList.remove('d-none');

        if (typeof INITIAL_EXAM_ID === 'number' && liveExams.some(e => parseInt(e.Exam_ID, 10) === INITIAL_EXAM_ID)) {
            currentExamId = INITIAL_EXAM_ID;
            select.value = String(INITIAL_EXAM_ID);
        } else {
            currentExamId = parseInt(liveExams[0].Exam_ID, 10);
            select.value = String(currentExamId);
        }

        refreshAll();
        startAutoRefresh();
    } catch (err) {
        console.error(err);
        Swal.fire('Error', 'Failed to load exams for monitoring', 'error');
    }
}

function startAutoRefresh() {
    if (refreshTimer) {
        clearInterval(refreshTimer);
    }
    refreshTimer = setInterval(refreshAll, 12000);
}

async function refreshAll() {
    if (!currentExamId) return;
    await Promise.all([
        loadSummary(),
        loadSessions(),
        loadIncidents()
    ]);
    document.getElementById('lastUpdated').textContent =
        'Last updated: ' + new Date().toLocaleTimeString();
}

async function loadSummary() {
    try {
        const res = await axios.get(`${MONITOR_API}?action=exam_summary&exam_id=${currentExamId}`);
        if (!res.data.success) return;

        const d = res.data.data;
        const exam = d.exam || {};

        document.getElementById('examTitle').textContent = exam.Title || 'Trainee Progress';
        document.getElementById('examMeta').textContent =
            `${exam.Subject_Name || ''} • ${exam.Course_Name || ''} • ${exam.question_count || 0} questions`;

        document.getElementById('summaryStats').innerHTML = `
            <div class="stat-card blue">
                <div class="stat-header">
                    <div class="stat-label">Assigned</div>
                    <div class="stat-icon icon-blue"><i class="bi bi-people"></i></div>
                </div>
                <div class="stat-value">${d.assigned ?? 0}</div>
            </div>
            <div class="stat-card green">
                <div class="stat-header">
                    <div class="stat-label">In Progress</div>
                    <div class="stat-icon icon-green"><i class="bi bi-play-circle"></i></div>
                </div>
                <div class="stat-value">${d.in_progress ?? 0}</div>
            </div>
            <div class="stat-card purple">
                <div class="stat-header">
                    <div class="stat-label">Completed</div>
                    <div class="stat-icon icon-purple"><i class="bi bi-check-circle"></i></div>
                </div>
                <div class="stat-value">${d.completed ?? 0}</div>
            </div>
            <div class="stat-card red">
                <div class="stat-header">
                    <div class="stat-label">Open Incidents</div>
                    <div class="stat-icon icon-red"><i class="bi bi-exclamation-triangle"></i></div>
                </div>
                <div class="stat-value">${d.open_incidents ?? 0}</div>
                <div class="stat-footer"><span>${d.not_started ?? 0} not started</span></div>
            </div>
        `;
    } catch (err) {
        console.error('Summary load error:', err);
    }
}

async function loadSessions() {
    if (!currentExamId) return;

    const status = document.getElementById('statusFilter').value;
    const search = document.getElementById('searchInput').value.trim();
    const params = new URLSearchParams({
        action: 'live_sessions',
        exam_id: currentExamId,
        page: currentPage,
        limit: 50,
        status,
    });
    if (search) params.set('search', search);

    try {
        const res = await axios.get(`${MONITOR_API}?${params.toString()}`);
        if (!res.data.success) {
            throw new Error(res.data.message);
        }

        const { rows, pagination } = res.data.data;
        renderSessions(rows || [], pagination || {});
    } catch (err) {
        console.error('Sessions load error:', err);
        document.getElementById('sessionsTableBody').innerHTML =
            '<tr><td colspan="6" class="text-center text-danger py-4">Failed to load sessions</td></tr>';
    }
}

function renderSessions(rows, pagination) {
    const tbody = document.getElementById('sessionsTableBody');

    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No trainees match this filter</td></tr>';
    } else {
        tbody.innerHTML = rows.map(row => {
            const statusClass = row.status === 'in_progress' ? 'in-progress'
                : row.status === 'completed' ? 'completed' : 'not-started';
            const statusLabel = row.status === 'in_progress' ? 'In progress'
                : row.status === 'completed' ? 'Completed' : 'Not started';
            const timeLeft = row.time_remaining
                ? (row.time_remaining.expired ? 'Expired' : row.time_remaining.display)
                : '—';
            const flagged = row.violation_count > 0 || row.has_open_incident;

            return `
                <tr class="${flagged ? 'row-flagged' : ''}">
                    <td>
                        <strong>${escapeHtml(row.Fullname)}</strong>
                        <div class="small text-muted">${escapeHtml(row.Academic_Number || row.Username || '')}</div>
                    </td>
                    <td>${escapeHtml(row.batch_names || '—')}</td>
                    <td><span class="status-pill ${statusClass}">${statusLabel}</span></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1">
                                <div class="progress-bar ${row.progress >= 100 ? 'bg-success' : 'bg-primary'}"
                                     style="width: ${row.progress}%"></div>
                            </div>
                            <small>${row.answered_count}/${row.question_count}</small>
                        </div>
                    </td>
                    <td>${escapeHtml(timeLeft)}</td>
                    <td>
                        <span class="violation-badge ${row.violation_count > 0 ? 'has-violations' : 'clean'}">
                            ${row.violation_count}${row.has_open_incident ? ' <i class="bi bi-exclamation-circle-fill"></i>' : ''}
                        </span>
                    </td>
                </tr>
            `;
        }).join('');
    }

    document.getElementById('paginationInfo').textContent =
        `Page ${pagination.page || 1} of ${pagination.pages || 1} (${pagination.total || 0} trainees)`;
    document.getElementById('prevPageBtn').disabled = (pagination.page || 1) <= 1;
    document.getElementById('nextPageBtn').disabled = (pagination.page || 1) >= (pagination.pages || 1);
}

async function loadIncidents() {
    if (!currentExamId) return;

    try {
        const res = await axios.get(`${MONITOR_API}?action=incidents&exam_id=${currentExamId}&limit=25`);
        if (!res.data.success) return;

        const incidents = res.data.data || [];
        const tbody = document.getElementById('incidentsTableBody');

        if (!incidents.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No incidents recorded for this exam</td></tr>';
            return;
        }

        tbody.innerHTML = incidents.map(inc => `
            <tr>
                <td>
                    <strong>${escapeHtml(inc.Trainee_Name)}</strong>
                    <div class="small text-muted">${escapeHtml(inc.Academic_Number || '')}</div>
                </td>
                <td>${escapeHtml(inc.Exam_Name)}</td>
                <td><span class="badge bg-danger">${escapeHtml(truncate(inc.Reason, 80))}</span></td>
                <td>${formatDateTime(inc.Timestamp)}</td>
                <td>${inc.violation_count ?? 0}</td>
                <td><span class="badge ${incidentStatusBadge(inc.Status)}">${escapeHtml(inc.Status)}</span></td>
            </tr>
        `).join('');
    } catch (err) {
        console.error('Incidents load error:', err);
    }
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
    return text.length > max ? text.substring(0, max) + '…' : text;
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
