const ARCHIVE_API = '../../api/masterfiles/archive.php';
const EXAMS_API = '../../api/masterfiles/exams.php';
const COURSES_API = '../../api/masterfiles/courses.php';
const MODULES_API = '../../api/masterfiles/modules.php';
const BATCHES_API = '../../api/masterfiles/batches.php';
const SECTIONS_API = '../../api/masterfiles/sections.php';
const SUBJECTS_API = '../../api/masterfiles/subjects.php';
const USERS_API = '../../api/masterfiles/users.php';
const QUESTIONS_API = '../../api/masterfiles/exam.php';

const RESTORE_ENDPOINTS = {
    exams: { url: EXAMS_API, action: 'restore', idKey: 'exam_id' },
    courses: { url: COURSES_API, action: 'restore', idKey: 'course_id' },
    modules: { url: MODULES_API, action: 'restore', idKey: 'module_id' },
    subjects: { url: SUBJECTS_API, action: 'restore', idKey: 'subject_id' },
    batches: { url: BATCHES_API, action: 'restore', idKey: 'batch_id' },
    sections: { url: SECTIONS_API, action: 'restore', idKey: 'section_id' },
    questions: { url: QUESTIONS_API, action: 'restore_question', idKey: 'question_id', query: true },
    users: { url: USERS_API, action: 'restore', idKey: 'user_id' },
};

const TABLE_CONFIG = {
    exams: {
        columns: ['Exam', 'Course / Subject', 'Status', 'Archived', 'Retention', 'Actions'],
        colspan: 6,
    },
    courses: {
        columns: ['Course', 'Details', 'Archived', 'Retention', 'Actions'],
        colspan: 5,
    },
    modules: {
        columns: ['Module', 'Course', 'Details', 'Archived', 'Retention', 'Actions'],
        colspan: 6,
    },
    subjects: {
        columns: ['Subject', 'Module / Course', 'Usage', 'Archived', 'Retention', 'Actions'],
        colspan: 6,
    },
    batches: {
        columns: ['Batch', 'Course', 'Period', 'Archived', 'Retention', 'Actions'],
        colspan: 6,
    },
    sections: {
        columns: ['Section', 'Batch / Course', 'Capacity', 'Archived', 'Retention', 'Actions'],
        colspan: 6,
    },
    questions: {
        columns: ['Question', 'Course / Subject', 'Usage', 'Archived', 'Retention', 'Actions'],
        colspan: 6,
    },
    users: {
        columns: ['User', 'Role', 'Archived', 'Retention', 'Actions'],
        colspan: 5,
    },
};

let currentType = 'exams';
let archivedItems = [];
let subjectsByCourse = {};
let archiveEnabled = true;
let retentionDays = 730;
let searchTimer = null;

document.addEventListener('DOMContentLoaded', () => {
    bindEvents();
    loadCoursesAndSubjects();
    loadSummary();
    switchArchiveType('exams');
});

function bindEvents() {
    document.querySelectorAll('[data-archive-type]').forEach(btn => {
        btn.addEventListener('click', () => switchArchiveType(btn.dataset.archiveType));
    });
    document.getElementById('filterCourse')?.addEventListener('change', onCourseFilterChange);
    document.getElementById('filterSubject')?.addEventListener('change', loadArchivedItems);
    document.getElementById('filterDateRange')?.addEventListener('change', loadArchivedItems);
    document.getElementById('searchArchive')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadArchivedItems, 350);
    });
    document.getElementById('clearFiltersBtn')?.addEventListener('click', clearFilters);
    document.getElementById('exportArchiveBtn')?.addEventListener('click', exportCsv);
    document.getElementById('archiveTableBody')?.addEventListener('click', onTableAction);
}

function switchArchiveType(type) {
    currentType = type;
    document.querySelectorAll('[data-archive-type]').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.archiveType === type);
    });
    updateFilterVisibility();
    renderTableHead();
    loadArchivedItems();
}

function updateFilterVisibility() {
    const showCourse = ['exams', 'modules', 'subjects', 'batches', 'sections', 'questions'].includes(currentType);
    document.getElementById('filterCourseCol').style.display = showCourse ? '' : 'none';
    document.getElementById('filterSubjectCol').style.display = currentType === 'exams' ? '' : 'none';
}

function renderTableHead() {
    const head = document.getElementById('archiveTableHead');
    const config = TABLE_CONFIG[currentType];
    if (!head || !config) return;
    head.innerHTML = `<tr>${config.columns.map(col => {
        const cls = col === 'Actions' ? ' class="text-end"' : '';
        const center = ['Sessions', 'Usage'].includes(col) ? ' class="text-center"' : cls;
        return `<th${center}>${col}</th>`;
    }).join('')}</tr>`;
}

function showAlert(message, type = 'success') {
    const alertDiv = document.getElementById('messageAlert');
    if (!alertDiv) return;
    alertDiv.innerHTML = `<div class="alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
}

async function loadSummary() {
    try {
        const res = await axios.get(`${ARCHIVE_API}?action=summary`);
        if (!res.data.success) return;

        archiveEnabled = res.data.archive_enabled !== false;
        retentionDays = res.data.retention_days || 730;

        const retentionLabel = document.getElementById('retentionDaysLabel');
        if (retentionLabel) retentionLabel.textContent = retentionDays;

        const warning = document.getElementById('migrationWarning');
        if (!archiveEnabled && warning) warning.classList.remove('d-none');
    } catch (err) {
        console.error('Error loading archive summary:', err);
    }
}

function loadCoursesAndSubjects() {
    axios.get(`${COURSES_API}?action=list`)
        .then(res => {
            if (!res.data.success) return;
            const courseSelect = document.getElementById('filterCourse');
            if (!courseSelect) return;
            courseSelect.innerHTML = '<option value="">All courses</option>';
            (res.data.data || []).forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.Course_ID;
                opt.textContent = c.Course_Name;
                courseSelect.appendChild(opt);
            });
        })
        .catch(err => console.error('Error loading courses:', err));

    axios.get(`${SUBJECTS_API}?action=list`)
        .then(res => {
            if (!res.data.success) return;
            subjectsByCourse = {};
            (res.data.data || []).forEach(s => {
                if (!subjectsByCourse[s.Course_ID]) subjectsByCourse[s.Course_ID] = [];
                subjectsByCourse[s.Course_ID].push(s);
            });
            populateSubjectFilter();
        })
        .catch(err => console.error('Error loading subjects:', err));
}

function populateSubjectFilter(courseId) {
    const subjectSelect = document.getElementById('filterSubject');
    if (!subjectSelect) return;

    const selectedCourse = courseId ?? document.getElementById('filterCourse')?.value ?? '';
    subjectSelect.innerHTML = '<option value="">All subjects</option>';

    const subjects = selectedCourse ? (subjectsByCourse[selectedCourse] || []) : Object.values(subjectsByCourse).flat();
    const seen = new Set();
    subjects.forEach(s => {
        if (seen.has(s.Subject_ID)) return;
        seen.add(s.Subject_ID);
        const opt = document.createElement('option');
        opt.value = s.Subject_ID;
        opt.textContent = s.Subject_Name;
        subjectSelect.appendChild(opt);
    });
}

function onCourseFilterChange() {
    populateSubjectFilter(document.getElementById('filterCourse')?.value || '');
    document.getElementById('filterSubject').value = '';
    loadArchivedItems();
}

function formatLocalDate(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function getDateRangeBounds() {
    const range = document.getElementById('filterDateRange')?.value || 'all';
    if (range === 'all') return { date_from: '', date_to: '' };
    const days = parseInt(range, 10) || 30;
    const to = new Date();
    const from = new Date();
    from.setDate(from.getDate() - days);
    return { date_from: formatLocalDate(from), date_to: formatLocalDate(to) };
}

function getFilters() {
    const dates = getDateRangeBounds();
    return {
        course_id: document.getElementById('filterCourse')?.value || '',
        subject_id: document.getElementById('filterSubject')?.value || '',
        search: document.getElementById('searchArchive')?.value.trim() || '',
        date_from: dates.date_from,
        date_to: dates.date_to,
    };
}

function hasActiveFilters() {
    const f = getFilters();
    const dateRange = document.getElementById('filterDateRange')?.value || 'all';
    return !!(f.course_id || f.subject_id || f.search || dateRange !== 'all');
}

function updateClearFiltersBtn() {
    const btn = document.getElementById('clearFiltersBtn');
    if (btn) btn.style.display = hasActiveFilters() ? '' : 'none';
}

function clearFilters() {
    document.getElementById('filterCourse').value = '';
    document.getElementById('filterSubject').value = '';
    document.getElementById('filterDateRange').value = 'all';
    document.getElementById('searchArchive').value = '';
    populateSubjectFilter('');
    loadArchivedItems();
}

function listActionForType(type) {
    return `list_${type}`;
}

async function loadArchivedItems() {
    const tbody = document.getElementById('archiveTableBody');
    const summary = document.getElementById('archiveSummary');
    const exportBtn = document.getElementById('exportArchiveBtn');
    const colspan = TABLE_CONFIG[currentType]?.colspan || 6;

    updateClearFiltersBtn();
    if (summary) summary.textContent = `Loading archived ${currentType}...`;

    const f = getFilters();
    const params = new URLSearchParams({ action: listActionForType(currentType) });
    if (f.course_id) params.append('course_id', f.course_id);
    if (f.subject_id && currentType === 'exams') params.append('subject_id', f.subject_id);
    if (f.search) params.append('search', f.search);
    if (f.date_from) params.append('date_from', f.date_from);
    if (f.date_to) params.append('date_to', f.date_to);

    try {
        const res = await axios.get(`${ARCHIVE_API}?${params.toString()}`);
        if (!res.data.success) {
            tbody.innerHTML = `<tr><td colspan="${colspan}" class="text-center text-danger py-4">${escapeHtml(res.data.message || 'Failed to load')}</td></tr>`;
            showAlert(res.data.message || 'Failed to load archived records', 'error');
            return;
        }

        archivedItems = res.data.data || [];
        if (res.data.retention_days) retentionDays = res.data.retention_days;
        renderArchiveTable();

        if (summary) {
            const label = archivedItems.length === 1 ? currentType.slice(0, -1) : currentType;
            summary.textContent = `Showing ${archivedItems.length} archived ${label}`;
        }
        if (exportBtn) exportBtn.disabled = archivedItems.length === 0;
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="${colspan}" class="text-center text-danger py-4">Failed to load archived ${currentType}</td></tr>`;
        showAlert(err.response?.data?.message || `Error loading archived ${currentType}`, 'error');
    }
}

function renderArchiveTable() {
    const tbody = document.getElementById('archiveTableBody');
    const colspan = TABLE_CONFIG[currentType]?.colspan || 6;
    if (!tbody) return;

    if (archivedItems.length === 0) {
        const hasFilters = hasActiveFilters();
        tbody.innerHTML = `<tr><td colspan="${colspan}">
            <div class="archive-empty-state">
                <i class="bi bi-archive d-block"></i>
                <h6>${hasFilters ? 'No matches found' : `No archived ${currentType}`}</h6>
                <p>${hasFilters ? 'Try adjusting your filters.' : 'Archived records will appear here for review and restoration.'}</p>
            </div>
        </td></tr>`;
        return;
    }

    const renderers = {
        exams: renderExamRow,
        courses: renderCourseRow,
        modules: renderModuleRow,
        subjects: renderSubjectRow,
        batches: renderBatchRow,
        sections: renderSectionRow,
        questions: renderQuestionRow,
        users: renderUserRow,
    };

    tbody.innerHTML = archivedItems.map(renderers[currentType]).join('');
}

function renderRestoreButton(id, title) {
    return `<button type="button" class="btn btn-sm btn-outline-success" data-archive-action="restore" data-item-id="${id}" title="Restore ${escapeHtml(title)}">
        <i class="bi bi-arrow-counterclockwise"></i>
    </button>`;
}

function renderExamRow(exam) {
    const retentionHtml = renderRetentionCell(exam);
    const statusBadge = badgeForStatus(exam.Status);
    const extraActions = exam.Exam_Code
        ? `<button type="button" class="btn btn-sm btn-outline-secondary" data-archive-action="copy-code" data-exam-code="${escapeHtml(exam.Exam_Code)}" title="Copy code"><i class="bi bi-clipboard"></i></button>`
        : '';
    const responsesBtn = `<button type="button" class="btn btn-sm btn-outline-primary" data-archive-action="responses" data-item-id="${exam.Exam_ID}" title="View responses"><i class="bi bi-bar-chart-line"></i></button>`;

    return `<tr>
        <td><div class="archive-exam-title">${escapeHtml(exam.Title)}</div></td>
        <td><span class="d-block">${escapeHtml(exam.Course_Name || '')}</span><small class="text-muted">${escapeHtml(exam.Subject_Name || '')}</small></td>
        <td><span class="badge archive-status-badge ${statusBadge}">${escapeHtml(exam.Status)}</span></td>
        <td>${formatDateTime(exam.Archived_At)}</td>
        <td>${retentionHtml}</td>
        <td class="text-end"><div class="archive-actions d-flex justify-content-end gap-1">${renderRestoreButton(exam.Exam_ID, exam.Title)}${responsesBtn}${extraActions}</div></td>
    </tr>`;
}

function renderCourseRow(course) {
    return `<tr>
        <td><strong>${escapeHtml(course.Course_Name)}</strong></td>
        <td>${escapeHtml(course.Details || '—')}</td>
        <td>${formatDateTime(course.Archived_At)}</td>
        <td>${renderRetentionCell(course)}</td>
        <td class="text-end">${renderRestoreButton(course.Course_ID, course.Course_Name)}</td>
    </tr>`;
}

function renderModuleRow(module) {
    return `<tr>
        <td><strong>${escapeHtml(module.Module_Name)}</strong></td>
        <td>${escapeHtml(module.Course_Name || '—')}</td>
        <td>${escapeHtml(module.Description || '—')}</td>
        <td>${formatDateTime(module.Archived_At)}</td>
        <td>${renderRetentionCell(module)}</td>
        <td class="text-end">${renderRestoreButton(module.Module_ID, module.Module_Name)}</td>
    </tr>`;
}

function renderSubjectRow(subject) {
    const examCount = parseInt(subject.exam_count, 10) || 0;
    const activeQuestions = parseInt(subject.active_questions, 10) || 0;
    const usageParts = [];
    if (examCount > 0) usageParts.push(`${examCount} exam${examCount === 1 ? '' : 's'}`);
    if (activeQuestions > 0) usageParts.push(`${activeQuestions} question${activeQuestions === 1 ? '' : 's'}`);
    const usageLabel = usageParts.length ? usageParts.join(', ') : 'No linked data';
    const moduleLine = subject.Module_Name
        ? `<span class="d-block">${escapeHtml(subject.Module_Name)}</span><small class="text-muted">${escapeHtml(subject.Course_Name || '')}</small>`
        : `<span class="d-block">${escapeHtml(subject.Course_Name || '—')}</span>`;
    const codeNote = subject.Subject_Code ? `<small class="d-block text-muted">${escapeHtml(subject.Subject_Code)}</small>` : '';

    return `<tr>
        <td><strong>${escapeHtml(subject.Subject_Name)}</strong>${codeNote}</td>
        <td>${moduleLine}</td>
        <td class="text-center"><span class="badge ${usageParts.length ? 'bg-warning text-dark' : 'bg-light text-muted border'}">${escapeHtml(usageLabel)}</span></td>
        <td>${formatDateTime(subject.Archived_At)}</td>
        <td>${renderRetentionCell(subject)}</td>
        <td class="text-end">${renderRestoreButton(subject.Subject_ID, subject.Subject_Name)}</td>
    </tr>`;
}

function renderBatchRow(batch) {
    const period = [batch.Date_Started, batch.Date_Ended].filter(Boolean).map(d => formatDateOnly(d)).join(' – ') || '—';
    const sectionNote = batch.Section_Name ? `<small class="d-block text-muted">Section: ${escapeHtml(batch.Section_Name)}</small>` : '';

    return `<tr>
        <td><strong>${escapeHtml(batch.Batch_Name)}</strong>${sectionNote}</td>
        <td>${escapeHtml(batch.Course_Name || '—')}</td>
        <td>${escapeHtml(period)}</td>
        <td>${formatDateTime(batch.Archived_At)}</td>
        <td>${renderRetentionCell(batch)}</td>
        <td class="text-end">${renderRestoreButton(batch.Batch_ID, batch.Batch_Name)}</td>
    </tr>`;
}

function renderSectionRow(section) {
    return `<tr>
        <td><strong>${escapeHtml(section.Section_Name)}</strong></td>
        <td>
            <span class="d-block">${escapeHtml(section.Batch_Name || '—')}</span>
            <small class="text-muted">${escapeHtml(section.Course_Name || '')}</small>
        </td>
        <td class="text-center">${section.Capacity ?? '—'}</td>
        <td>${formatDateTime(section.Archived_At)}</td>
        <td>${renderRetentionCell(section)}</td>
        <td class="text-end">${renderRestoreButton(section.Section_ID, section.Section_Name)}</td>
    </tr>`;
}

function renderQuestionRow(question) {
    const usage = parseInt(question.exam_usage_count, 10) || 0;
    const usageLabel = usage > 0 ? `Used in ${usage} exam${usage === 1 ? '' : 's'}` : 'Not used';
    const preview = (question.Question_Text || '').substring(0, 120);

    return `<tr>
        <td><div class="archive-exam-title">${escapeHtml(preview)}${(question.Question_Text || '').length > 120 ? '…' : ''}</div></td>
        <td><span class="d-block">${escapeHtml(question.Course_Name || '')}</span><small class="text-muted">${escapeHtml(question.Subject_Name || '')}</small></td>
        <td class="text-center"><span class="badge ${usage > 0 ? 'bg-warning text-dark' : 'bg-light text-muted border'}">${usageLabel}</span></td>
        <td>${formatDateTime(question.Archived_At)}</td>
        <td>${renderRetentionCell(question)}</td>
        <td class="text-end">${renderRestoreButton(question.Question_ID, 'question')}</td>
    </tr>`;
}

function renderUserRow(user) {
    return `<tr>
        <td>
            <strong>${escapeHtml(user.Fullname)}</strong>
            <small class="d-block text-muted">${escapeHtml(user.Email || user.Academic_Number || '')}</small>
        </td>
        <td><span class="badge bg-secondary">${escapeHtml(user.Role_Name || '—')}</span></td>
        <td>${formatDateTime(user.Archived_At)}</td>
        <td>${renderRetentionCell(user)}</td>
        <td class="text-end">${renderRestoreButton(user.User_ID, user.Fullname)}</td>
    </tr>`;
}

function renderRetentionCell(item) {
    const status = item.Retention_Status;

    if (status === 'protected') {
        return `<span class="badge archive-retention-badge archive-retention-badge--protected">
            <i class="bi bi-shield-check me-1"></i>Protected
        </span><small class="d-block text-muted mt-1">Has linked data</small>`;
    }
    if (status === 'unknown') return '<span class="text-muted">—</span>';
    if (status === 'eligible') {
        const overdue = parseInt(item.Days_Overdue, 10) || 0;
        const label = overdue > 0 ? `${overdue} day${overdue === 1 ? '' : 's'} overdue` : 'Eligible now';
        return `<span class="badge archive-retention-badge archive-retention-badge--eligible">
            <i class="bi bi-exclamation-triangle me-1"></i>${escapeHtml(label)}
        </span><small class="d-block text-muted mt-1">Pending auto-purge</small>`;
    }

    const daysLeft = parseInt(item.Days_Until_Purge, 10) || 0;
    const purgeDate = item.Purge_Eligible_At ? formatDateOnly(item.Purge_Eligible_At) : '';
    const urgencyClass = daysLeft <= 30 ? 'archive-retention-badge--soon' : 'archive-retention-badge--pending';

    return `<span class="badge archive-retention-badge ${urgencyClass}">
        <i class="bi bi-hourglass-split me-1"></i>${daysLeft} day${daysLeft === 1 ? '' : 's'} left
    </span>${purgeDate ? `<small class="d-block text-muted mt-1">Deletes ${escapeHtml(purgeDate)}</small>` : ''}`;
}

function onTableAction(e) {
    const btn = e.target.closest('[data-archive-action]');
    if (!btn) return;

    const action = btn.dataset.archiveAction;
    const itemId = btn.dataset.itemId;

    switch (action) {
        case 'restore':
            restoreItem(itemId);
            break;
        case 'responses':
            if (currentType === 'exams') window.location.href = `exam-responses.php?exam_id=${itemId}`;
            break;
        case 'copy-code':
            copyExamCode(btn.dataset.examCode);
            break;
    }
}

async function restoreItem(itemId) {
    const item = archivedItems.find(r => String(getItemId(r)) === String(itemId));
    const label = item ? getItemLabel(item) : 'this record';
    const endpoint = RESTORE_ENDPOINTS[currentType];

    let restoreHint = '';
    if (currentType === 'subjects') {
        restoreHint = '<br><br><small class="text-muted">The parent module and course will also be restored if archived. Other subjects in the same module stay archived.</small>';
    } else if (currentType === 'sections') {
        restoreHint = '<br><br><small class="text-muted">The parent batch and course will also be restored if archived. Other sections in the same batch stay archived.</small>';
    } else if (currentType === 'modules') {
        restoreHint = '<br><br><small class="text-muted">The parent course will also be restored if archived. Subjects in this module stay archived until restored individually.</small>';
    } else if (currentType === 'batches') {
        restoreHint = '<br><br><small class="text-muted">The parent course will also be restored if archived. Sections in this batch stay archived until restored individually.</small>';
    }

    const result = await Swal.fire({
        title: 'Restore record?',
        html: `<strong>${escapeHtml(label)}</strong> will return to active management.${restoreHint}`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Restore',
        confirmButtonColor: '#198754',
    });

    if (!result.isConfirmed) return;

    try {
        const payload = { action: endpoint.action, [endpoint.idKey]: itemId };
        const url = endpoint.query ? `${endpoint.url}?action=${endpoint.action}` : endpoint.url;
        const res = await axios.post(url, payload);
        if (res.data.success) {
            showAlert(res.data.message || 'Restored successfully', 'success');
            loadSummary();
            loadArchivedItems();
        } else {
            showAlert(res.data.message || 'Failed to restore', 'error');
        }
    } catch (err) {
        showAlert(err.response?.data?.message || 'Error restoring record', 'error');
    }
}

function getItemId(item) {
    const keysByType = {
        exams: 'Exam_ID',
        courses: 'Course_ID',
        modules: 'Module_ID',
        subjects: 'Subject_ID',
        batches: 'Batch_ID',
        sections: 'Section_ID',
        questions: 'Question_ID',
        users: 'User_ID',
    };
    const key = keysByType[currentType];
    return key ? item[key] : undefined;
}

function getItemLabel(item) {
    if (!item) return 'this record';
    return item.Title || item.Course_Name || item.Module_Name || item.Subject_Name || item.Batch_Name || item.Section_Name || item.Fullname || item.Question_Text?.substring(0, 60) || 'this record';
}

function copyExamCode(code) {
    if (!code) return;
    navigator.clipboard.writeText(code)
        .then(() => showAlert(`Exam code <strong>${escapeHtml(code)}</strong> copied`, 'success'))
        .catch(() => showAlert('Could not copy to clipboard', 'error'));
}

function exportCsv() {
    if (!archivedItems.length) return;

    const headers = ['Type', 'Name', 'Archived At', 'Retention', 'Purge Eligible At'];
    const rows = archivedItems.map(item => [
        currentType,
        getItemLabel(item),
        item.Archived_At || '',
        retentionLabelForExport(item),
        item.Purge_Eligible_At || '',
    ]);

    const csv = [headers, ...rows]
        .map(row => row.map(cell => `"${String(cell ?? '').replace(/"/g, '""')}"`).join(','))
        .join('\n');

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `archive-${currentType}-${formatLocalDate(new Date())}.csv`;
    link.click();
    URL.revokeObjectURL(url);
}

function retentionLabelForExport(item) {
    if (item.Retention_Status === 'protected') return 'Protected';
    if (item.Retention_Status === 'unknown') return 'Unknown';
    if (item.Retention_Status === 'eligible') return 'Eligible for purge';
    return `${item.Days_Until_Purge || 0} days left`;
}

function badgeForStatus(status) {
    switch (status) {
        case 'Published': return 'bg-success text-white';
        case 'Closed': return 'bg-secondary text-white';
        case 'Draft': return 'bg-light text-dark border';
        default: return 'bg-dark text-white';
    }
}

function formatDateTime(value) {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return escapeHtml(String(value));
    return d.toLocaleString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formatDateOnly(value) {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}
