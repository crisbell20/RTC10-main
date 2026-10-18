// Cheating incidents

const INCIDENTS_API = '../../api/admin/cheating-incidents.php';
const INCIDENTS_REFRESH_MS = 1000;

let cheatingLogModal = null;
let incidentsRefreshTimer = null;
let incidentsRefreshPaused = false;
let lastIncidentsSnapshot = '';
let lastExamSummarySnapshot = '';
let selectedExam = null;
let selectedCourseId = '';

document.addEventListener('DOMContentLoaded', () => {
    const modalEl = document.getElementById('cheatingLogModal');
    if (modalEl) {
        cheatingLogModal = new bootstrap.Modal(modalEl);
    }

    loadCourses();

    document.getElementById('filterCourse')?.addEventListener('change', () => {
        selectedCourseId = document.getElementById('filterCourse').value || '';
        selectedExam = null;
        lastIncidentsSnapshot = '';
        lastExamSummarySnapshot = '';
        updateIncidentsViewMode();
        refreshIncidents();
    });
    document.getElementById('backToExamsBtn')?.addEventListener('click', () => {
        selectedExam = null;
        lastIncidentsSnapshot = '';
        updateIncidentsViewMode();
        loadExamSummary();
    });

    document.getElementById('examSummaryCards')?.addEventListener('click', handleExamSummaryClick);
    document.getElementById('examSummaryCards')?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') return;
        const card = event.target.closest('.exam-summary-card');
        if (!card) return;
        event.preventDefault();
        handleExamSummaryClick({ target: card });
    });
    document.getElementById('incidentsTableBody')?.addEventListener('click', handleIncidentsTableClick);

    document.addEventListener('show.bs.dropdown', (event) => {
        if (event.target.closest('.incidents-table')) {
            incidentsRefreshPaused = true;
        }
    });

    document.addEventListener('hidden.bs.dropdown', (event) => {
        if (event.target.closest('.incidents-table')) {
            incidentsRefreshPaused = false;
            refreshIncidents();
        }
    });

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

function getIncidentsFilterParams(extra = {}) {
    const params = { ...extra };
    if (selectedCourseId) {
        params.course_id = selectedCourseId;
    }
    return params;
}

async function loadCourses() {
    const select = document.getElementById('filterCourse');
    if (!select) {
        return;
    }

    try {
        const res = await axios.get('../../api/masterfiles/courses.php?action=list');
        const courses = res.data.data || [];
        select.innerHTML = '<option value="">All courses</option>';
        courses.forEach(course => {
            const opt = document.createElement('option');
            opt.value = course.Course_ID;
            opt.textContent = course.Course_Name;
            select.appendChild(opt);
        });
    } catch (err) {
        console.error('Failed to load courses:', err);
    }
}

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
    if (selectedExam) {
        await loadIncidents();
    } else {
        await loadExamSummary();
    }
}

function updateIncidentsViewMode() {
    const summaryPanel = document.getElementById('examSummaryPanel');
    const incidentsPanel = document.getElementById('examIncidentsPanel');
    const titleEl = document.getElementById('incidentsSectionTitle');
    const subEl = document.getElementById('incidentsSectionSub');

    if (selectedExam) {
        summaryPanel.style.display = 'none';
        incidentsPanel.style.display = 'block';
        titleEl.textContent = selectedExam.Exam_Name || 'Exam incidents';
        subEl.textContent = [
            selectedExam.Subject_Name,
            selectedExam.Course_Name,
        ].filter(Boolean).join(' • ') || 'Trainee incidents for this exam';
    } else {
        summaryPanel.style.display = 'block';
        incidentsPanel.style.display = 'none';
        titleEl.textContent = 'Incidents by Exam';
        subEl.textContent = 'Select an exam to review trainee incidents';
    }
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

async function loadExamSummary() {
    if (incidentsRefreshPaused || selectedExam) {
        return;
    }

    try {
        const res = await axios.get(INCIDENTS_API, {
            params: getIncidentsFilterParams({ action: 'exam_summary' }),
        });
        if (!res.data.success) {
            renderExamSummary([], 'Failed to load exams');
            return;
        }

        renderExamSummary(res.data.data?.exams || []);
    } catch (err) {
        console.error('Failed to load exam summary:', err);
        renderExamSummary([], err.response?.data?.message || 'Failed to load exams');
    }
}

function examSummarySnapshot(exams) {
    return JSON.stringify(
        (exams || []).map(exam => [
            exam.Exam_ID,
            exam.incident_count,
            exam.latest_incident,
            (exam.Assignments || []).map(a => a.label || `${a.batch_name || ''} • ${a.section_name || ''}`),
        ])
    );
}

function renderExamSummary(exams, message = '') {
    const container = document.getElementById('examSummaryCards');
    if (!container) return;

    if (message) {
        lastExamSummarySnapshot = '';
        container.innerHTML = `<div class="incident-exam-empty text-danger">${escapeHtml(message)}</div>`;
        return;
    }

    if (!exams.length) {
        lastExamSummarySnapshot = '';
        container.innerHTML = '<div class="incident-exam-empty">No incidents recorded yet</div>';
        return;
    }

    const snapshot = examSummarySnapshot(exams);
    if (snapshot === lastExamSummarySnapshot) {
        return;
    }
    lastExamSummarySnapshot = snapshot;

    container.innerHTML = exams.map(exam => {
        const count = exam.incident_count ?? 0;
        const path = [exam.Course_Name, exam.Subject_Name].filter(Boolean).join(', ');
        const assignmentPills = (exam.Assignments || []).map(a => {
            const label = a.label || (
                a.batch_name && a.section_name
                    ? `${a.batch_name} • ${a.section_name}`
                    : (a.batch_name || a.section_name || '')
            );
            return label ? `<span class="badge bg-light text-dark border">${escapeHtml(label)}</span>` : '';
        }).join('');
        return `<div class="incident-exam-card exam-summary-card" tabindex="0" data-exam-id="${exam.Exam_ID}">
            <div class="d-flex justify-content-between align-items-start">
                <div class="flex-grow-1 pe-2">
                    <h5>${escapeHtml(exam.Exam_Name)}</h5>
                    ${path ? `<div class="text-muted small mb-2">${escapeHtml(path)}</div>` : ''}
                    <div class="incident-exam-assignment-row">
                        <div class="d-flex flex-wrap gap-1">${assignmentPills}</div>
                        <span class="incident-exam-count"><strong>${count}</strong> incident${count === 1 ? '' : 's'}</span>
                    </div>
                </div>
                <i class="bi bi-chevron-right text-muted"></i>
            </div>
        </div>`;
    }).join('');
}

function handleExamSummaryClick(event) {
    const card = event.target.closest('.exam-summary-card');
    const examId = parseInt(card?.dataset.examId || '0', 10);
    if (!examId) return;

    openExamIncidents(examId);
}

async function openExamIncidents(examId) {
    try {
        const res = await axios.get(INCIDENTS_API, {
            params: getIncidentsFilterParams({ action: 'exam_summary' }),
        });
        const exams = res.data.data?.exams || [];
        const exam = exams.find(item => parseInt(item.Exam_ID, 10) === examId);
        if (!exam) {
            showAlert('Exam not found', 'error');
            return;
        }

        selectedExam = exam;
        lastIncidentsSnapshot = '';
        updateIncidentsViewMode();
        await loadIncidents();
    } catch (err) {
        showAlert(err.response?.data?.message || 'Failed to open exam incidents', 'error');
    }
}

async function loadIncidents() {
    if (incidentsRefreshPaused || !selectedExam) {
        return;
    }

    const params = new URLSearchParams({
        action: 'list',
        limit: '100',
        exam_id: String(selectedExam.Exam_ID),
        ...(selectedCourseId ? { course_id: selectedCourseId } : {}),
    });

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

function incidentsSnapshot(incidents) {
    return JSON.stringify(
        (incidents || []).map(inc => [
            inc.Incident_ID,
            inc.violation_count,
            inc.Timestamp,
            inc.latest_violation_label,
        ])
    );
}

function renderIncidents(incidents, message = '') {
    const tbody = document.getElementById('incidentsTableBody');

    if (message) {
        lastIncidentsSnapshot = '';
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">${escapeHtml(message)}</td></tr>`;
        return;
    }

    if (!incidents.length) {
        lastIncidentsSnapshot = '';
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No incidents for this exam</td></tr>';
        return;
    }

    const snapshot = incidentsSnapshot(incidents);
    if (snapshot === lastIncidentsSnapshot) {
        return;
    }
    lastIncidentsSnapshot = snapshot;

    tbody.innerHTML = incidents.map(inc => `
        <tr class="incident-summary-row" data-session-id="${inc.Session_ID}">
            <td>
                <strong>${escapeHtml(inc.Trainee_Name)}</strong>
                <div class="small text-muted">${escapeHtml(inc.Badge_Number || '')}</div>
            </td>
            <td>${escapeHtml(inc.Batch_Name || '—')}</td>
            <td>${escapeHtml(inc.Section_Name || '—')}</td>
            <td><span class="badge bg-danger">${escapeHtml(inc.proctor_summary || inc.latest_violation_label || truncate(inc.Reason, 90))}</span></td>
            <td>${formatDateTime(inc.Timestamp)}</td>
            <td>${inc.violation_count ?? 0}</td>
            <td>${Number(inc.Cheating_Locked) === 1 ? '<span class="badge bg-danger">Locked</span>' : '<span class="badge bg-secondary">Recorded</span>'}</td>
        </tr>
    `).join('');
}

function handleIncidentsTableClick(event) {
    const row = event.target.closest('.incident-summary-row');
    if (!row) return;

    viewCheatingLogs(parseInt(row.dataset.sessionId, 10));
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

    const badge = data.trainee?.Badge_Number || '—';
    const traineeName = data.trainee?.Fullname || 'Trainee';
    const examName = data.exam?.Exam_Name || '—';

    const session = data.session || {};
    const sessionId = data.session_id || session.Session_ID || null;
    const isLocked = Number(session.Cheating_Locked) === 1;
    const lockedAt = session.Cheating_Locked_At
        ? new Date(session.Cheating_Locked_At).getTime()
        : null;

    titleEl.textContent = `Proctoring Log — ${traineeName}`;

    const modalFooter = document.querySelector('#cheatingLogModal .modal-footer');

    if (modalFooter) {
        let unlockBtn = document.getElementById('unlockSessionBtn');

        if (isLocked) {
            if (!unlockBtn) {
                unlockBtn = document.createElement('button');
                unlockBtn.type = 'button';
                unlockBtn.id = 'unlockSessionBtn';
                unlockBtn.className = 'btn btn-warning';
                modalFooter.insertBefore(unlockBtn, modalFooter.firstChild);
            }

            unlockBtn.innerHTML = '<i class="bi bi-unlock"></i> Unlock Session';
            unlockBtn.style.display = 'inline-block';
            unlockBtn.onclick = () => unlockExamSession(sessionId);
        } else if (unlockBtn) {
            unlockBtn.remove();
        }
    }

    if (!logs.length) {
        bodyEl.innerHTML = `
            <div class="mb-3 small text-muted">
                <div><strong>Exam:</strong> ${escapeHtml(examName)}</div>
            </div>
            <p class="text-muted mb-0">No proctoring events were recorded for this session.</p>
        `;
        return;
    }

    bodyEl.innerHTML = `
        <div class="mb-3 small text-muted">
            <div><strong>Exam:</strong> ${escapeHtml(examName)}</div>
            <div><strong>Total events:</strong> ${logs.length}</div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th style="width: 170px;">Time</th>
                        <th>Incident</th>
                        <th style="width: 110px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    ${logs.map(log => {
                        const logTime = new Date(log.Timestamp).getTime();
                        const isLockEvent =
                            isLocked &&
                            lockedAt &&
                            !Number.isNaN(logTime) &&
                            logTime <= lockedAt &&
                            Math.abs(logTime - lockedAt) < 5000;

                        return `
                            <tr>
                                <td>
                                    <small>${formatDateTime(log.Timestamp)}</small>
                                </td>
                                <td>
                                    ${escapeHtml(
                                        log.proctor_line ||
                                        formatProctorLogLine(
                                            badge,
                                            traineeName,
                                            log.event_label || log.Type
                                        )
                                    )}
                                </td>
                                <td>
                                    ${
                                        isLockEvent
                                            ? '<span class="badge bg-danger">Locked</span>'
                                            : '<span class="badge bg-secondary">Recorded</span>'
                                    }
                                </td>
                            </tr>
                        `;
                    }).join('')}
                </tbody>
            </table>
        </div>
    `;
}

async function unlockExamSession(sessionId) {
    if (!sessionId) {showAlert('Invalid exam session.', 'error');
        return;
    }

    const result = await Swal.fire({
        icon: 'warning',
        title: 'Unlock Exam Session?',
        text: 'This will allow the trainee to continue the locked exam session.',
        showCancelButton: true,
        confirmButtonText: 'Yes, Unlock',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#f0ad4e',
    });

    if (!result.isConfirmed) {
        return;
    }

    try {
        const res = await axios.post(INCIDENTS_API, {
            action: 'unlock_session', session_id: sessionId,
        });

        if (!res.data.success) {
            throw new Error(res.data.message || 'Failed to unlock exam session.');
        }

        await Swal.fire({
            icon: 'success',
            title: 'Session Unlocked',
            text: 'The exam session has been unlocked successfully.',
            confirmButtonText: 'OK',
        });

        if (cheatingLogModal) {
            cheatingLogModal.hide();
        }

        refreshIncidents();
    } catch (err) {
        console.error('Failed to unlock exam session:', err);

        await Swal.fire({
            icon: 'error',
            title: 'Unlock Failed',
            text: err.response?.data?.message || err.message || 'Failed to unlock exam session.',
            confirmButtonText: 'OK',
        });
    }
}

function truncate(text, max) {
    if (!text) return '';
    return text.length > max ? `${text.substring(0, max)}…` : text;
}

function formatProctorLogLine(badgeNumber, fullname, eventLabel) {
    const badge = badgeNumber && badgeNumber !== '—' ? badgeNumber : '—';
    const name = fullname || 'Trainee';
    const label = eventLabel || 'Suspicious activity';
    return `${badge}, ${name} — ${label}`;
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
window.unlockExamSession = unlockExamSession;