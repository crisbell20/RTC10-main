/**
 * CCMD Live Exam Monitoring (no incident management — Admin resolves incidents)
 */

const MONITOR_API = '../../api/ccmd/monitor.php';
const MONITOR_REFRESH_MS = 1000;

let currentExamId = null;
let currentPage = 1;
let refreshTimer = null;
let searchTimer = null;
let liveExams = [];
let initialExamApplied = false;

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('examSelect').addEventListener('change', (e) => {
        currentExamId = e.target.value ? parseInt(e.target.value, 10) : null;
        currentPage = 1;
        if (currentExamId) {
            refreshAll();
            startAutoRefresh();
        } else {
            stopAutoRefresh();
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

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            refreshAll();
            startAutoRefresh();
        } else {
            stopAutoRefresh();
        }
    });

    refreshLiveExamsDropdown({ useInitialExamId: true }).then((hasExams) => {
        if (hasExams) {
            return refreshAll();
        }
    }).then(() => {
        startAutoRefresh();
    });
});

function stopAutoRefresh() {
    if (refreshTimer) {
        clearInterval(refreshTimer);
        refreshTimer = null;
    }
}

function startAutoRefresh() {
    stopAutoRefresh();
    if (!currentExamId && !liveExams.length) {
        return;
    }
    refreshTimer = setInterval(() => refreshAll(), MONITOR_REFRESH_MS);
}

/**
 * Reload published exam list; preserve selected exam when possible.
 */
async function refreshLiveExamsDropdown(options = {}) {
    const { preserveSelection = true, useInitialExamId = false } = options;
    const previousId = preserveSelection ? currentExamId : null;

    try {
        const res = await axios.get(`${MONITOR_API}?action=live_exams`);
        if (!res.data.success) {
            throw new Error(res.data.message || 'Failed to load exams');
        }

        liveExams = res.data.data || [];
        const select = document.getElementById('examSelect');

        if (!liveExams.length) {
            currentExamId = null;
            document.getElementById('monitorEmpty').classList.remove('d-none');
            document.getElementById('monitorBody').classList.add('d-none');
            select.innerHTML = '<option value="">No published exams</option>';
            stopAutoRefresh();
            return false;
        }

        select.innerHTML = liveExams.map(exam => `
            <option value="${exam.Exam_ID}">
                ${escapeHtml(exam.Exam_Name)} (${exam.Active_Participants || 0} active)
            </option>
        `).join('');

        document.getElementById('monitorEmpty').classList.add('d-none');
        document.getElementById('monitorBody').classList.remove('d-none');

        let nextId = null;
        if (
            useInitialExamId &&
            !initialExamApplied &&
            typeof INITIAL_EXAM_ID === 'number' &&
            liveExams.some(e => parseInt(e.Exam_ID, 10) === INITIAL_EXAM_ID)
        ) {
            nextId = INITIAL_EXAM_ID;
            initialExamApplied = true;
        } else if (previousId && liveExams.some(e => parseInt(e.Exam_ID, 10) === previousId)) {
            nextId = previousId;
        } else {
            nextId = parseInt(liveExams[0].Exam_ID, 10);
        }

        currentExamId = nextId;
        select.value = String(currentExamId);
        return true;
    } catch (err) {
        console.error(err);
        if (!preserveSelection) {
            Swal.fire('Error', 'Failed to load exams for monitoring', 'error');
        }
        return false;
    }
}

async function refreshAll() {
    const hasExams = await refreshLiveExamsDropdown({ preserveSelection: true });
    if (!hasExams || !currentExamId) {
        return;
    }

    await Promise.all([loadSummary(), loadSessions()]);
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
                    <div class="stat-label">Not Started</div>
                    <div class="stat-icon icon-red"><i class="bi bi-hourglass-split"></i></div>
                </div>
                <div class="stat-value">${d.not_started ?? 0}</div>
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
            '<tr><td colspan="5" class="text-center text-danger py-4">Failed to load sessions</td></tr>';
    }
}

function renderSessions(rows, pagination) {
    const tbody = document.getElementById('sessionsTableBody');

    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No trainees match this filter</td></tr>';
    } else {
        tbody.innerHTML = rows.map(row => {
            const statusClass = row.status === 'in_progress' ? 'in-progress'
                : row.status === 'completed' ? 'completed' : 'not-started';
            const statusLabel = row.status === 'in_progress' ? 'In progress'
                : row.status === 'completed' ? 'Completed' : 'Not started';
            const timeLeft = row.time_remaining
                ? (row.time_remaining.expired ? 'Expired' : row.time_remaining.display)
                : '—';
            const isCompleted = row.status === 'completed';
            const hasScore = isCompleted && row.score_display;
            const questionCount = row.question_count || 0;
            const answeredCount = row.answered_count || 0;
            const answeredProgress = questionCount > 0
                ? Math.min(100, Math.round((answeredCount / questionCount) * 1000) / 10)
                : (row.answered_progress ?? row.progress ?? 0);
            const answeredLabel = `${answeredCount}/${questionCount}`;
            const progressLabel = hasScore ? row.score_display : answeredLabel;
            const progressBarClass = answeredProgress >= 100 ? 'bg-success' : 'bg-primary';
            const scoreCaption = hasScore ? `Score: ${row.score_display}` : '';
            const progressCaption = hasScore
                ? `${scoreCaption} • ${answeredLabel} answered`
                : `${answeredLabel} answered`;

            return `
                <tr>
                    <td>
                        <strong>${escapeHtml(row.Fullname)}</strong>
                        <div class="small text-muted">${escapeHtml(row.Academic_Number || '')}</div>
                    </td>
                    <td>${escapeHtml(row.batch_names || '—')}</td>
                    <td><span class="status-pill ${statusClass}">${statusLabel}</span></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" title="${escapeHtml(progressCaption)}">
                                <div class="progress-bar ${progressBarClass}"
                                     style="width: ${Math.min(100, answeredProgress)}%"></div>
                            </div>
                            <small title="${hasScore ? 'Score' : 'Answered'}">${escapeHtml(progressLabel)}</small>
                        </div>
                    </td>
                    <td>${escapeHtml(timeLeft)}</td>
                </tr>
            `;
        }).join('');
    }

    document.getElementById('paginationInfo').textContent =
        `Page ${pagination.page || 1} of ${pagination.pages || 1} (${pagination.total || 0} trainees)`;
    document.getElementById('prevPageBtn').disabled = (pagination.page || 1) <= 1;
    document.getElementById('nextPageBtn').disabled = (pagination.page || 1) >= (pagination.pages || 1);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}
