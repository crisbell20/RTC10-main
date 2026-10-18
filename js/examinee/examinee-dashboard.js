// Examinee dashboard

const API_BASE = '../../api/examinee/';
const DASHBOARD_API = API_BASE + 'dashboard.php';
const START_EXAM_API = API_BASE + 'start-exam.php';

let cachedAvailableExams = [];
let scheduleUnlockTimer = null;

function getSwalWidth(defaultWidth) {
    return window.matchMedia('(max-width: 575.98px)').matches ? '92%' : (defaultWidth || '500px');
}

async function fetchDashboardStats() {
    try {
        const response = await axios.get(DASHBOARD_API + '?action=statistics');
        if (response.data.success) {
            updateStatistics(response.data.data);
        } else {
            console.error('Failed to fetch statistics:', response.data.message);
        }
    } catch (error) {
        handleError(error, 'Failed to load dashboard statistics');
    }
}

async function fetchAvailableExams() {
    try {
        const response = await axios.get(DASHBOARD_API + '?action=available-exams');
        if (response.data.success) {
            cachedAvailableExams = response.data.data || [];
            populateExamsTable(cachedAvailableExams);
        } else {
            console.error('Failed to fetch available exams:', response.data.message);
        }
    } catch (error) {
        handleError(error, 'Failed to load available exams');
    }
}

async function fetchRecentResults() {
    try {
        const response = await axios.get(DASHBOARD_API + '?action=recent-results');
        if (response.data.success) {
            populateResultsTable(response.data.data);
        } else {
            console.error('Failed to fetch recent results:', response.data.message);
        }
    } catch (error) {
        handleError(error, 'Failed to load recent results');
    }
}

function updateStatistics(data) {
    document.getElementById('examsAvailable').textContent = data.exams_available || 0;
    document.getElementById('availableStatus').textContent =
        data.exams_available > 0 ? `${data.exams_available} exam${data.exams_available > 1 ? 's' : ''} ready` : 'No exams available';

    document.getElementById('examsCompleted').textContent = data.exams_completed || 0;

    const passed = data.passed_count || 0;
    const failed = data.failed_count || 0;
    document.getElementById('passedFailedCount').textContent = `${passed} / ${failed}`;
    document.getElementById('passedFailedLabel').textContent = 'Passed / failed exams';

    document.getElementById('averageScore').textContent =
        data.average_score > 0 ? `${data.average_score}%` : '0%';
    document.getElementById('scoreChange').textContent =
        data.average_score >= 87.5 ? 'Good performance' : 'Keep improving';
}

function formatExamDescription(exam) {
    if (!exam.Description) {
        return 'No description';
    }
    const text = String(exam.Description);
    return text.length > 120 ? escapeHtml(text.substring(0, 120)) + '...' : escapeHtml(text);
}

function getExamActionMeta(exam, status) {
    const buttonText = exam.has_started ? 'Continue Exam' : 'Take Exam';
    const buttonIcon = exam.has_started ? 'arrow-right-circle' : 'play-circle';
    const duration = exam.Duration ? `${exam.Duration} mins` : 'N/A';
    const deadline = exam.Deadline ? formatDateTime(exam.Deadline) : 'No deadline';
    const scheduleLocked = isExamScheduleLocked(exam);
    const startsAt = exam.Schedule_Date ? formatDateTime(exam.Schedule_Date) : '';
    const disabled =
        (!exam.has_started && scheduleLocked)
        || (status.text === 'Overdue' && !exam.has_started);

    return { buttonText, buttonIcon, duration, deadline, startsAt, scheduleLocked, disabled };
}

function buildTakeExamButtonHtml(exam, meta, status, extraClass) {
    const btnClass = extraClass || 'btn btn-sm btn-primary take-exam-btn';
    const disabledAttr = meta.disabled ? 'disabled' : '';

    return `<button class="${btnClass}"
            data-exam-id="${exam.Exam_ID}"
            data-session-id="${exam.Session_ID || ''}"
            data-has-started="${exam.has_started ? 'true' : 'false'}"
            data-requires-code="${exam.requires_exam_code ? 'true' : 'false'}"
            data-duration="${escapeHtml(meta.duration)}"
            data-deadline="${escapeHtml(meta.deadline)}"
            data-schedule-date="${escapeHtml(exam.Schedule_Date || '')}"
            data-starts-at="${escapeHtml(meta.startsAt)}"
            ${disabledAttr}>
        <i class="bi bi-${meta.buttonIcon}"></i> ${meta.buttonText}
    </button>`;
}

function attachTakeExamListeners() {
    document.querySelectorAll('.take-exam-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            handleTakeExam(this.getAttribute('data-exam-id'), this);
        });
    });
}

function populateExamsTable(exams) {
    const tbody = document.getElementById('examsTableBody');
    const cards = document.getElementById('examsCardsList');
    tbody.innerHTML = '';
    if (cards) {
        cards.innerHTML = '';
    }

    if (!exams || exams.length === 0) {
        clearScheduleUnlockTimer();
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center text-muted py-4">
                    <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                    <p class="mt-2">No exams available at this time</p>
                </td>
            </tr>
        `;
        if (cards) {
            cards.innerHTML = `
                <div class="examinee-empty-state">
                    <i class="bi bi-inbox"></i>
                    <p>No exams available at this time</p>
                </div>
            `;
        }
        return;
    }

    exams.forEach(exam => {
        const status = getExamStatus(exam);
        const meta = getExamActionMeta(exam, status);
        const description = formatExamDescription(exam);
        const questionCount = exam.Question_Count || 0;

        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${escapeHtml(exam.Title)}</td>
            <td>${description}</td>
            <td>${escapeHtml(exam.Subject_Name)}</td>
            <td>${questionCount}</td>
            <td>${meta.duration}</td>
            <td><span class="badge bg-${status.color}">${status.text}</span></td>
            <td><small class="text-muted">${meta.scheduleLocked && meta.startsAt ? `Starts: ${escapeHtml(meta.startsAt)}<br>` : ''}Due: ${meta.deadline}</small></td>
            <td>${buildTakeExamButtonHtml(exam, meta, status)}</td>
        `;
        tbody.appendChild(row);

        if (cards) {
            const card = document.createElement('article');
            card.className = 'examinee-data-card';
            card.innerHTML = `
                <div class="examinee-data-card-header">
                    <h6 class="examinee-data-card-title">${escapeHtml(exam.Title)}</h6>
                    <span class="badge bg-${status.color}">${status.text}</span>
                </div>
                <p class="examinee-data-card-desc">${description}</p>
                <dl class="examinee-data-card-meta">
                    <div><dt>Subject</dt><dd>${escapeHtml(exam.Subject_Name || '—')}</dd></div>
                    <div><dt>Questions</dt><dd>${questionCount}</dd></div>
                    <div><dt>Duration</dt><dd>${meta.duration}</dd></div>
                    ${meta.scheduleLocked && meta.startsAt ? `<div class="examinee-data-card-meta-wide"><dt>Starts</dt><dd>${escapeHtml(meta.startsAt)}</dd></div>` : ''}
                    <div class="examinee-data-card-meta-wide"><dt>Deadline</dt><dd>${meta.deadline}</dd></div>
                </dl>
                ${buildTakeExamButtonHtml(exam, meta, status, 'btn btn-primary take-exam-btn w-100 mt-2')}
            `;
            cards.appendChild(card);
        }
    });

    attachTakeExamListeners();
    scheduleExamsTableRefresh(exams);
}

function clearScheduleUnlockTimer() {
    if (scheduleUnlockTimer !== null) {
        clearTimeout(scheduleUnlockTimer);
        scheduleUnlockTimer = null;
    }
}

function scheduleExamsTableRefresh(exams) {
    clearScheduleUnlockTimer();
    if (!exams || exams.length === 0) {
        return;
    }

    const now = Date.now();
    let nextUnlockAt = null;

    exams.forEach((exam) => {
        if (exam.has_started) {
            return;
        }
        const schedule = parseExamDateTime(exam.Schedule_Date);
        if (!schedule) {
            return;
        }
        const startMs = schedule.getTime();
        if (startMs > now && (nextUnlockAt === null || startMs < nextUnlockAt)) {
            nextUnlockAt = startMs;
        }
    });

    if (nextUnlockAt === null) {
        return;
    }

    const delay = Math.min(Math.max(0, nextUnlockAt - now) + 500, 2147483647);
    scheduleUnlockTimer = setTimeout(() => {
        populateExamsTable(cachedAvailableExams);
    }, delay);
}

function sortResultsLatestFirst(results) {
    return [...results].sort((a, b) => {
        const dateA = examDateTimeMs(a.Time_Ended || a.Submission_Date);
        const dateB = examDateTimeMs(b.Time_Ended || b.Submission_Date);
        if (dateB !== dateA) return dateB - dateA;
        return (parseInt(b.Result_ID, 10) || 0) - (parseInt(a.Result_ID, 10) || 0);
    });
}

function populateResultsTable(results) {
    const tbody = document.getElementById('resultsTableBody');
    const cards = document.getElementById('resultsCardsList');
    tbody.innerHTML = '';
    if (cards) {
        cards.innerHTML = '';
    }

    if (!results || results.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center text-muted py-4">
                    <i class="bi bi-clipboard-data" style="font-size: 2rem;"></i>
                    <p class="mt-2">No exam results yet</p>
                </td>
            </tr>
        `;
        if (cards) {
            cards.innerHTML = `
                <div class="examinee-empty-state">
                    <i class="bi bi-clipboard-data"></i>
                    <p>No exam results yet</p>
                </div>
            `;
        }
        return;
    }

    sortResultsLatestFirst(results).forEach(result => {
        const inProgress = Number(result.is_in_progress) === 1 || result.status === 'In progress';
        const submissionDate = formatDateTime(result.Submission_Date);
        const statusBadge = inProgress
            ? '<span class="badge bg-secondary">In progress</span>'
            : (result.status === 'Passed'
                ? '<span class="badge bg-success">Passed</span>'
                : '<span class="badge bg-danger">Failed</span>');
        const attemptLabel = inProgress
            ? '<div class="small text-muted">In progress</div>'
            : (result.attempt_number ? `<div class="small text-muted">Attempt ${result.attempt_number}</div>` : '');
        const reviewDisabled = result.can_view_details === false;

        const row = document.createElement('tr');
        row.className = 'examinee-clickable-row';
        row.setAttribute('role', 'button');
        row.setAttribute('tabindex', '0');
        row.addEventListener('click', () => handleViewDetails(result.Result_ID));
        row.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                handleViewDetails(result.Result_ID);
            }
        });
        row.innerHTML = `
            <td>${escapeHtml(result.exam_title)}${attemptLabel}</td>
            <td>${submissionDate}</td>
            <td>${inProgress ? '—' : result.Score}</td>
            <td>${inProgress ? '—' : `${result.Percentage}%`}</td>
            <td>${statusBadge}${reviewDisabled && !inProgress ? ' <span class="text-muted small ms-1"><i class="bi bi-lock"></i></span>' : ''}</td>
        `;
        tbody.appendChild(row);

        if (cards) {
            const card = document.createElement('article');
            card.className = 'examinee-data-card examinee-clickable-card';
            card.setAttribute('role', 'button');
            card.setAttribute('tabindex', '0');
            card.addEventListener('click', () => handleViewDetails(result.Result_ID));
            card.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    handleViewDetails(result.Result_ID);
                }
            });
            card.innerHTML = `
                <div class="examinee-data-card-header">
                    <h6 class="examinee-data-card-title">${escapeHtml(result.exam_title)}</h6>
                    ${attemptLabel}
                    ${statusBadge}
                </div>
                <dl class="examinee-data-card-meta">
                    <div><dt>Date taken</dt><dd>${submissionDate}</dd></div>
                    <div><dt>Score</dt><dd>${inProgress ? '—' : result.Score}</dd></div>
                    <div><dt>Average</dt><dd>${inProgress ? '—' : `${result.Percentage}%`}</dd></div>
                </dl>
                ${reviewDisabled ? '<p class="text-muted small mb-0"><i class="bi bi-lock"></i> Score summary only</p>' : ''}
            `;
            cards.appendChild(card);
        }
    });
}

async function handleTakeExam(examId, buttonEl) {
    const button = buttonEl || (typeof event !== 'undefined' ? event.currentTarget : null);
    if (!button) {
        Swal.fire({ title: 'Error', text: 'Unable to start exam', icon: 'error', width: getSwalWidth() });
        return;
    }

    if (button.disabled) {
        return;
    }

    const hasStarted = button.getAttribute('data-has-started') === 'true';
    if (!hasStarted) {
        const schedule = parseExamDateTime(button.getAttribute('data-schedule-date'));
        if (schedule && Date.now() < schedule.getTime()) {
            const startsAt = button.getAttribute('data-starts-at') || formatDateTime(button.getAttribute('data-schedule-date'));
            Swal.fire({
                title: 'Not yet available',
                text: startsAt
                    ? `This exam opens at ${startsAt}. Please wait until the scheduled start time.`
                    : 'This exam is not yet available. Please wait until the scheduled start time.',
                icon: 'info',
                width: getSwalWidth(),
            });
            return;
        }
    }

    const requiresCode = button.getAttribute('data-requires-code') === 'true';

    if (hasStarted) {
        const sessionId = button.getAttribute('data-session-id');
        if (sessionId) {
            window.location.href = `exam.php?session_id=${encodeURIComponent(sessionId)}`;
            return;
        }

        Swal.fire({
            title: 'Continuing exam...',
            allowOutsideClick: false,
            width: getSwalWidth('320px'),
            didOpen: () => Swal.showLoading()
        });

        try {
            const response = await axios.post(START_EXAM_API, { exam_id: examId });
            if (response.data.success) {
                Swal.close();
                window.location.href = `exam.php?session_id=${response.data.data.session_id}`;
            } else {
                Swal.fire({ title: 'Error', text: response.data.message, icon: 'error', width: getSwalWidth() });
            }
        } catch (error) {
            handleError(error, 'Failed to continue exam');
        }
        return;
    }

    const duration = button.getAttribute('data-duration') || 'N/A';
    const deadline = button.getAttribute('data-deadline') || 'No deadline';

    const swalConfig = {
        title: 'Start exam?',
        html: `
            <div class="text-start small">
                <p class="mb-2">The timer starts immediately and fullscreen is required.</p>
                ${requiresCode ? `
                <div class="mb-2">
                    <label for="examCodeInput" class="form-label mb-1">Exam code <span class="text-danger">*</span></label>
                    <input id="examCodeInput" class="form-control form-control-sm text-uppercase" placeholder="Enter code" maxlength="12" autocomplete="off">
                </div>` : ''}
                <div class="border rounded p-2 bg-light">
                    <div><strong>Duration:</strong> ${escapeHtml(duration)}</div>
                    <div><strong>Due:</strong> ${escapeHtml(deadline)}</div>
                </div>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Start',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#6b7280',
        width: getSwalWidth('420px'),
        didOpen: () => {
            if (requiresCode) {
                document.getElementById('examCodeInput')?.focus();
            }
        },
        preConfirm: () => {
            if (!requiresCode) {
                return { exam_code: null };
            }
            const codeInput = document.getElementById('examCodeInput');
            const code = codeInput ? codeInput.value.trim() : '';
            if (!code) {
                Swal.showValidationMessage('Exam code is required');
                return false;
            }
            return { exam_code: code };
        }
    };

    const result = await Swal.fire(swalConfig);

    if (!result.isConfirmed) {
        return;
    }

    Swal.fire({
        title: 'Starting exam...',
        allowOutsideClick: false,
        width: getSwalWidth('320px'),
        didOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        const payload = { exam_id: examId };
        if (requiresCode && result.value?.exam_code) {
            payload.exam_code = result.value.exam_code;
        }

        const response = await axios.post(START_EXAM_API, payload);

        if (response.data.success) {
            Swal.close();
            window.location.href = `exam.php?session_id=${response.data.data.session_id}`;
        } else {
            Swal.fire({
                title: 'Error',
                text: response.data.message,
                icon: 'error',
                confirmButtonColor: '#2563eb',
                width: getSwalWidth()
            });
        }
    } catch (error) {
        handleError(error, 'Failed to start exam');
    }
}

function handleViewDetails(resultId) {
    window.location.href = `result.php?result_id=${resultId}`;
}

function isExamScheduleLocked(exam) {
    if (exam.has_started) {
        return false;
    }
    const schedule = parseExamDateTime(exam.Schedule_Date);
    if (!schedule) {
        return false;
    }
    return Date.now() < schedule.getTime();
}

function getExamStatus(exam) {
    if (exam.has_started && exam.exam_deadline) {
        const now = new Date();
        const deadline = parseExamDateTime(exam.exam_deadline);
        if (!deadline) {
            return { text: 'In Progress', color: 'info' };
        }
        const timeRemaining = deadline - now;

        if (timeRemaining <= 0) {
            return { text: 'Time Expired', color: 'danger' };
        }
        if (timeRemaining < 10 * 60 * 1000) {
            return { text: 'In Progress', color: 'warning' };
        }
        return { text: 'In Progress', color: 'info' };
    }

    if (isExamScheduleLocked(exam)) {
        return { text: 'Scheduled', color: 'secondary' };
    }

    if (!exam.Deadline) {
        return { text: 'Available', color: 'success' };
    }

    const now = new Date();
    const deadline = parseExamDateTime(exam.Deadline);
    if (!deadline) {
        return { text: 'Available', color: 'success' };
    }
    const timeUntilDeadline = deadline - now;

    if (timeUntilDeadline < 0) {
        return { text: 'Overdue', color: 'danger' };
    }
    if (timeUntilDeadline < 24 * 60 * 60 * 1000) {
        return { text: 'Due Soon', color: 'warning' };
    }
    return { text: 'Available', color: 'success' };
}

function formatDate(dateString) {
    const date = parseExamDateTime(dateString);
    if (!date) return '';
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function formatDateTime(dateString) {
    const date = parseExamDateTime(dateString);
    if (!date) return '';
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function handleError(error, defaultMessage) {
    console.error('Error:', error);

    let message = defaultMessage;

    if (error.response) {
        const status = error.response.status;

        if (status === 401) {
            Swal.fire({
                title: 'Session Expired',
                text: 'Your session has expired. Please log in again.',
                icon: 'warning',
                confirmButtonColor: '#2563eb',
                timer: 3000,
                width: getSwalWidth()
            }).then(() => {
                window.location.href = '../../login.php';
            });
            return;
        }
        if (error.response.data && error.response.data.message) {
            message = error.response.data.message;
        } else if (status === 403) {
            message = 'Access denied. You do not have permission to access this resource.';
        } else if (status === 500) {
            message = 'Server error. Please try again later.';
        }
    } else if (error.request) {
        message = 'Network error. Please check your connection and try again.';
    }

    Swal.fire({
        title: 'Error',
        text: message,
        icon: 'error',
        confirmButtonColor: '#2563eb',
        width: getSwalWidth()
    });
}

function showError(message) {
    Swal.fire({
        title: 'Error',
        text: message,
        icon: 'error',
        confirmButtonColor: '#2563eb',
        width: getSwalWidth()
    });
}

document.addEventListener('DOMContentLoaded', function() {
    Promise.all([
        fetchDashboardStats(),
        fetchAvailableExams(),
        fetchRecentResults()
    ]).catch(error => {
        console.error('Error loading dashboard:', error);
    });
});
