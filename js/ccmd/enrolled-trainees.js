const API = '../../api/ccmd/enrolled-trainees.php';
const REPORTS_API = '../../api/masterfiles/reports.php';

let currentPage = 1;
let totalPages = 1;
let searchTimer = null;
let assignedExamsModal = null;

document.addEventListener('DOMContentLoaded', () => {
    assignedExamsModal = new bootstrap.Modal(document.getElementById('assignedExamsModal'));
    bindEvents();
    loadCourses().then(() => loadBatches()).then(() => loadTrainees());
});

function bindEvents() {
    document.getElementById('filterCourse')?.addEventListener('change', async function () {
        document.getElementById('filterBatch').value = '';
        resetSectionFilter();
        await loadBatches(this.value);
        currentPage = 1;
        loadTrainees();
    });
    document.getElementById('filterBatch')?.addEventListener('change', async function () {
        await loadSections(this.value);
        currentPage = 1;
        loadTrainees();
    });
    ['filterSection', 'filterStatus'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => {
            currentPage = 1;
            loadTrainees();
        });
    });
    document.getElementById('searchTrainee')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            currentPage = 1;
            loadTrainees();
        }, 350);
    });
    document.getElementById('clearFiltersBtn')?.addEventListener('click', clearFilters);
    document.getElementById('prevPageBtn')?.addEventListener('click', () => changePage(-1));
    document.getElementById('nextPageBtn')?.addEventListener('click', () => changePage(1));
    document.getElementById('exportTraineesBtn')?.addEventListener('click', exportCsv);

    document.getElementById('traineesTableBody')?.addEventListener('click', handleTableAction);
}

function getFilters() {
    return {
        course_id: document.getElementById('filterCourse')?.value || '',
        batch_id: document.getElementById('filterBatch')?.value || '',
        section_id: document.getElementById('filterSection')?.value || '',
        enrollment_status: document.getElementById('filterStatus')?.value || '',
        search: document.getElementById('searchTrainee')?.value.trim() || '',
    };
}

function hasActiveFilters() {
    const f = getFilters();
    return !!(f.course_id || f.batch_id || f.section_id || f.search || f.enrollment_status !== 'Active');
}

function resetSectionFilter() {
    const select = document.getElementById('filterSection');
    if (!select) return;
    select.innerHTML = '<option value="">All sections</option>';
    select.value = '';
    select.disabled = true;
}

async function loadCourses() {
    const res = await axios.get('../../api/masterfiles/courses.php?action=list');
    const select = document.getElementById('filterCourse');
    select.innerHTML = '<option value="">All courses</option>';
    (res.data.data || []).forEach(c => {
        select.add(new Option(c.Course_Name, c.Course_ID));
    });
}

async function loadBatches(courseId = '') {
    const course = courseId || document.getElementById('filterCourse')?.value || '';
    const url = course
        ? `${REPORTS_API}?action=batches&course_id=${course}`
        : `${REPORTS_API}?action=batches`;
    const res = await axios.get(url);
    const select = document.getElementById('filterBatch');
    select.innerHTML = '<option value="">All batches</option>';
    (res.data.data || []).forEach(b => {
        select.add(new Option(b.Batch_Name, b.Batch_ID));
    });
}

async function loadSections(batchId = '') {
    const select = document.getElementById('filterSection');
    const batch = batchId || document.getElementById('filterBatch')?.value || '';
    if (!batch) {
        resetSectionFilter();
        return;
    }
    const res = await axios.get(`${REPORTS_API}?action=sections&batch_id=${batch}`);
    select.innerHTML = '<option value="">All sections</option>';
    (res.data.data || []).forEach(s => {
        select.add(new Option(s.Section_Name, s.Section_ID));
    });
    select.disabled = false;
}

async function loadTrainees() {
    const tbody = document.getElementById('traineesTableBody');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-hourglass-split"></i> Loading...</td></tr>';

    const params = new URLSearchParams({
        action: 'list',
        page: currentPage,
        per_page: 25,
        ...getFilters(),
    });

    try {
        const res = await axios.get(`${API}?${params.toString()}`);
        if (!res.data.success) throw new Error(res.data.message || 'Failed to load trainees');

        const rows = res.data.data || [];
        const pagination = res.data.pagination || {};
        totalPages = pagination.total_pages || 1;
        currentPage = pagination.page || 1;

        updateSummary(pagination.total || 0);
        updatePagination(pagination);
        renderTable(rows);
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${escapeHtml(err.response?.data?.message || err.message)}</td></tr>`;
    }
}

function updateSummary(total) {
    document.getElementById('traineesSummary').textContent = `Showing ${total} trainee${total === 1 ? '' : 's'}`;
    document.getElementById('clearFiltersBtn').classList.toggle('d-none', !hasActiveFilters());
}

function updatePagination(pagination) {
    const total = pagination.total || 0;
    const page = pagination.page || 1;
    const perPage = pagination.per_page || 25;
    const from = total === 0 ? 0 : ((page - 1) * perPage) + 1;
    const to = Math.min(page * perPage, total);

    document.getElementById('paginationInfo').textContent = total
        ? `Page ${page} of ${pagination.total_pages || 1} • ${from}–${to} of ${total}`
        : 'No entries';
    document.getElementById('prevPageBtn').disabled = page <= 1;
    document.getElementById('nextPageBtn').disabled = page >= (pagination.total_pages || 1);
}

function attrEscape(value) {
    return String(value ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
}

function renderTable(rows) {
    const tbody = document.getElementById('traineesTableBody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No trainees found for the selected filters.</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map(row => {
        const examCount = row.assigned_exam_count || 0;
        const examLabel = `${examCount} exam${examCount === 1 ? '' : 's'}`;

        return `
        <tr>
            <td>${escapeHtml(row.personnel_rank || '—')}</td>
            <td class="fw-medium">${escapeHtml(row.fullname)}</td>
            <td>${escapeHtml(row.badge_number || '—')}</td>
            <td>${escapeHtml(row.course_name || '—')}</td>
            <td>${escapeHtml(row.batch_name || '—')}</td>
            <td>${escapeHtml(row.section_name || '—')}</td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2"
                    data-trainee-action="view-exams"
                    data-user-id="${row.user_id}"
                    data-section-id="${row.section_id}"
                    data-fullname="${attrEscape(row.fullname)}"
                    ${examCount === 0 ? 'disabled' : ''}>
                    ${escapeHtml(examLabel)}
                </button>
            </td>
        </tr>`;
    }).join('');
}

function handleTableAction(event) {
    const btn = event.target.closest('[data-trainee-action]');
    if (!btn || btn.disabled) return;

    const action = btn.dataset.traineeAction;
    const userId = btn.dataset.userId;
    const sectionId = btn.dataset.sectionId;
    const fullname = btn.dataset.fullname || '';

    if (action === 'view-exams') {
        openAssignedExamsModal(userId, sectionId, fullname);
    }
}

async function openAssignedExamsModal(userId, sectionId, fullname) {
    document.getElementById('assignedExamsModalTitle').textContent = 'Assigned exams';
    document.getElementById('assignedExamsModalSubtitle').textContent = fullname;
    document.getElementById('assignedExamsModalBody').innerHTML =
        '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    assignedExamsModal.show();

    try {
        const res = await axios.get(`${API}?action=assigned_exams&user_id=${userId}&section_id=${sectionId}`);
        if (!res.data.success) throw new Error(res.data.message);

        const exams = res.data.data?.exams || [];
        if (!exams.length) {
            document.getElementById('assignedExamsModalBody').innerHTML =
                '<p class="text-muted text-center py-3 mb-0">No assigned exams for this trainee.</p>';
            return;
        }

        document.getElementById('assignedExamsModalBody').innerHTML = `
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Exam</th>
                            <th>Status</th>
                            <th>Session</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${exams.map(exam => `
                            <tr>
                                <td>${escapeHtml(exam.exam_title)}</td>
                                <td>${renderExamStatusBadge(exam.exam_status)}</td>
                                <td>${renderSessionStatusPill(exam.session_status)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>`;
    } catch (err) {
        document.getElementById('assignedExamsModalBody').innerHTML =
            `<div class="alert alert-danger mb-0">${escapeHtml(err.response?.data?.message || err.message)}</div>`;
    }
}

function renderExamStatusBadge(status) {
    const s = (status || '').toLowerCase();
    if (s === 'published') return '<span class="badge bg-success">Published</span>';
    if (s === 'closed') return '<span class="badge bg-secondary">Closed</span>';
    return `<span class="badge bg-light text-dark border">${escapeHtml(status || '—')}</span>`;
}

function renderSessionStatusPill(status) {
    const s = (status || 'not_started').toLowerCase();
    if (s === 'completed') return '<span class="status-pill completed">Completed</span>';
    if (s === 'in_progress') return '<span class="status-pill in-progress">In progress</span>';
    return '<span class="status-pill not-started">Not started</span>';
}

async function exportCsv() {
    const params = new URLSearchParams({ action: 'export', limit: 5000, ...getFilters() });
    try {
        const res = await axios.get(`${API}?${params.toString()}`);
        if (!res.data.success) throw new Error(res.data.message);

        const rows = res.data.data || [];
        const headers = ['Rank', 'Trainee', 'Badge No.', 'Course', 'Batch', 'Section', 'Assigned exams'];
        const lines = [headers.join(',')];
        rows.forEach(r => {
            lines.push([
                csvCell(r.personnel_rank),
                csvCell(r.fullname),
                csvCell(r.badge_number),
                csvCell(r.course_name),
                csvCell(r.batch_name),
                csvCell(r.section_name),
                csvCell(r.assigned_exam_count),
            ].join(','));
        });

        const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `assigned-trainees-${formatLocalDate(new Date())}.csv`;
        link.click();
        URL.revokeObjectURL(url);
    } catch (err) {
        Swal.fire('Export failed', err.response?.data?.message || err.message, 'error');
    }
}

function clearFilters() {
    document.getElementById('filterCourse').value = '';
    document.getElementById('filterBatch').value = '';
    document.getElementById('filterStatus').value = 'Active';
    document.getElementById('searchTrainee').value = '';
    resetSectionFilter();
    loadBatches();
    currentPage = 1;
    loadTrainees();
}

function changePage(delta) {
    currentPage = Math.max(1, Math.min(totalPages, currentPage + delta));
    loadTrainees();
}

function formatLocalDate(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function csvCell(value) {
    return `"${String(value ?? '').replace(/"/g, '""')}"`;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}
