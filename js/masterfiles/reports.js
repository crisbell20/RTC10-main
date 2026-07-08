const REPORTS_API = '../../api/masterfiles/reports.php';

let performanceChart, passFailChart, subjectsChart, weakSubjectsChart, participationChart;
let currentTab = 'overview';
let studentSummaryData = [];
let subjectSummaryData = [];
let participationData = [];
let attemptsPage = 1;
let studentDetailModal, subjectDetailModal;
let searchTimer = null;

document.addEventListener('DOMContentLoaded', () => {
    studentDetailModal = new bootstrap.Modal(document.getElementById('studentDetailModal'));
    subjectDetailModal = new bootstrap.Modal(document.getElementById('subjectDetailModal'));

    initializeCharts();
    loadCourses();
    loadBatches();
    bindEvents();
    loadReports();
});

function bindEvents() {
    document.getElementById('logoutBtn')?.addEventListener('click', handleLogout);
    document.getElementById('applyFiltersBtn')?.addEventListener('click', () => {
        attemptsPage = 1;
        loadReports();
    });
    document.getElementById('exportReportBtn')?.addEventListener('click', exportReport);

    document.getElementById('filterCourse')?.addEventListener('change', function() {
        loadSubjects(this.value);
        loadBatches(this.value);
    });

    document.getElementById('studentSearch')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            if (currentTab === 'students') loadStudentSummary();
        }, 350);
    });

    document.getElementById('studentStatusFilter')?.addEventListener('change', loadStudentSummary);

    document.querySelectorAll('#reportTabs .nav-link').forEach(btn => {
        btn.addEventListener('click', () => switchTab(btn.dataset.tab));
    });

    document.getElementById('attemptsPrevBtn')?.addEventListener('click', () => {
        if (attemptsPage > 1) {
            attemptsPage--;
            loadDetailedResults();
        }
    });
    document.getElementById('attemptsNextBtn')?.addEventListener('click', () => {
        attemptsPage++;
        loadDetailedResults();
    });
}

function getFilterParams(extra = {}) {
    const params = new URLSearchParams({
        date_range: document.getElementById('dateRange').value,
        ...(document.getElementById('filterCourse').value && { course_id: document.getElementById('filterCourse').value }),
        ...(document.getElementById('filterSubject').value && { subject_id: document.getElementById('filterSubject').value }),
        ...(document.getElementById('filterBatch').value && { batch_id: document.getElementById('filterBatch').value }),
        ...(document.getElementById('studentSearch').value.trim() && { search: document.getElementById('studentSearch').value.trim() }),
        ...extra
    });
    return params;
}

function switchTab(tab) {
    currentTab = tab;
    document.querySelectorAll('#reportTabs .nav-link').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tab);
    });
    document.getElementById('tabOverview').style.display = tab === 'overview' ? 'block' : 'none';
    document.getElementById('tabStudents').style.display = tab === 'students' ? 'block' : 'none';
    document.getElementById('tabSubjects').style.display = tab === 'subjects' ? 'block' : 'none';

    if (tab === 'students') loadStudentSummary();
    if (tab === 'subjects') loadSubjectSummary();
}

async function loadReports() {
    const params = getFilterParams();
    try {
        await Promise.all([
            loadMetrics(params),
            loadAtRisk(params),
            loadPerformanceTrend(params),
            loadPassFail(params),
            loadTopSubjects(params),
            loadWeakSubjects(params),
            loadParticipation(params),
            loadDetailedResults(),
            loadStudentSummary(true),
            loadSubjectSummary(true)
        ]);
    } catch (error) {
        Swal.fire('Error', 'Failed to load reports data', 'error');
    }
}

function initializeCharts() {
    const chartDefaults = { responsive: true, maintainAspectRatio: false };

    performanceChart = new Chart(document.getElementById('performanceChart'), {
        type: 'line',
        data: { labels: [], datasets: [{ label: 'Average Score (%)', data: [], borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,0.1)', tension: 0.4, fill: true }] },
        options: { ...chartDefaults, scales: { y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } } } }
    });

    passFailChart = new Chart(document.getElementById('passFailChart'), {
        type: 'pie',
        data: { labels: ['Passed', 'Failed'], datasets: [{ data: [0, 0], backgroundColor: ['#10b981', '#ef4444'] }] },
        options: { ...chartDefaults, plugins: { legend: { position: 'bottom' } } }
    });

    subjectsChart = new Chart(document.getElementById('subjectsChart'), {
        type: 'bar',
        data: { labels: [], datasets: [{ label: 'Avg %', data: [], backgroundColor: '#a855f7' }] },
        options: { ...chartDefaults, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } } } }
    });

    weakSubjectsChart = new Chart(document.getElementById('weakSubjectsChart'), {
        type: 'bar',
        data: { labels: [], datasets: [{ label: 'Pass Rate %', data: [], backgroundColor: '#ef4444' }] },
        options: { ...chartDefaults, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } } } }
    });

    participationChart = new Chart(document.getElementById('participationChart'), {
        type: 'bar',
        data: { labels: [], datasets: [{ label: 'Examinees', data: [], backgroundColor: '#f59e0b' }] },
        options: {
            ...chartDefaults,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
            onClick: (evt, elements) => {
                if (elements.length && participationData[elements[0].index]) {
                    const examId = participationData[elements[0].index].Exam_ID;
                    window.location.href = `exam-responses.php?exam_id=${examId}`;
                }
            }
        }
    });
}

async function loadCourses() {
    const res = await axios.get('../../api/masterfiles/courses.php?action=list');
    const select = document.getElementById('filterCourse');
    select.innerHTML = '<option value="">All courses</option>';
    (res.data.data || []).forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.Course_ID;
        opt.textContent = c.Course_Name;
        select.appendChild(opt);
    });
}

async function loadSubjects(courseId) {
    const url = courseId
        ? `../../api/masterfiles/subjects.php?action=list&course_id=${courseId}`
        : '../../api/masterfiles/subjects.php?action=list';
    const res = await axios.get(url);
    const select = document.getElementById('filterSubject');
    select.innerHTML = '<option value="">All subjects</option>';
    (res.data.data || []).forEach(s => {
        const opt = document.createElement('option');
        opt.value = s.Subject_ID;
        opt.textContent = s.Subject_Name;
        select.appendChild(opt);
    });
}

async function loadBatches(courseId = '') {
    const course = courseId || document.getElementById('filterCourse').value;
    const url = course
        ? `${REPORTS_API}?action=batches&course_id=${course}`
        : `${REPORTS_API}?action=batches`;
    const res = await axios.get(url);
    const select = document.getElementById('filterBatch');
    select.innerHTML = '<option value="">All batches</option>';
    (res.data.data || []).forEach(b => {
        const opt = document.createElement('option');
        opt.value = b.Batch_ID;
        opt.textContent = b.Batch_Name;
        select.appendChild(opt);
    });
}

async function loadMetrics(params) {
    const res = await axios.get(`${REPORTS_API}?action=metrics&${params}`);
    const d = res.data.data;
    document.getElementById('activeExaminees').textContent = d.active_examinees;
    document.getElementById('examsAdministered').textContent = d.exams_administered;
    document.getElementById('passRate').textContent = d.pass_rate + '%';
    document.getElementById('avgScore').textContent = d.avg_score + '%';
}

async function loadAtRisk(params) {
    const res = await axios.get(`${REPORTS_API}?action=at_risk&${params}`);
    const rows = res.data.data || [];
    const tbody = document.getElementById('atRiskBody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No at-risk students in this period.</td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => `
        <tr>
            <td><strong>${escapeHtml(r.Fullname)}</strong><br><small class="text-muted">${escapeHtml(r.Academic_Number || '')}</small></td>
            <td>${r.exams_taken}</td>
            <td>${r.avg_percentage}%</td>
            <td>${r.pass_rate}%</td>
            <td>${statusBadge(r.status)}</td>
            <td><button class="btn btn-sm btn-outline-primary" onclick="viewStudentDetail(${r.User_ID})">View</button></td>
        </tr>
    `).join('');
}

async function loadPerformanceTrend(params) {
    const res = await axios.get(`${REPORTS_API}?action=performance_trend&${params}`);
    const data = res.data.data || [];
    performanceChart.data.labels = data.map(i => i.date);
    performanceChart.data.datasets[0].data = data.map(i => parseFloat(i.avg_score));
    performanceChart.update();
}

async function loadPassFail(params) {
    const res = await axios.get(`${REPORTS_API}?action=pass_fail&${params}`);
    const d = res.data.data;
    passFailChart.data.datasets[0].data = [parseInt(d.passed) || 0, parseInt(d.failed) || 0];
    passFailChart.update();
}

async function loadTopSubjects(params) {
    const res = await axios.get(`${REPORTS_API}?action=top_subjects&${params}`);
    const data = res.data.data || [];
    subjectsChart.data.labels = data.map(i => i.Subject_Name);
    subjectsChart.data.datasets[0].data = data.map(i => parseFloat(i.avg_score));
    subjectsChart.update();
}

async function loadWeakSubjects(params) {
    const res = await axios.get(`${REPORTS_API}?action=weak_subjects&${params}`);
    const data = res.data.data || [];
    weakSubjectsChart.data.labels = data.map(i => i.Subject_Name);
    weakSubjectsChart.data.datasets[0].data = data.map(i => parseFloat(i.pass_rate));
    weakSubjectsChart.update();
}

async function loadParticipation(params) {
    const res = await axios.get(`${REPORTS_API}?action=participation&${params}`);
    participationData = res.data.data || [];
    participationChart.data.labels = participationData.map(i => i.exam_title);
    participationChart.data.datasets[0].data = participationData.map(i => parseInt(i.participant_count));
    participationChart.update();
}

async function loadDetailedResults() {
    const params = getFilterParams({ page: attemptsPage });
    const res = await axios.get(`${REPORTS_API}?action=detailed_results&${params}`);
    const data = res.data.data;
    const rows = data.rows || [];
    const pagination = data.pagination || {};

    const tbody = document.getElementById('resultsTableBody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No results found</td></tr>';
    } else {
        tbody.innerHTML = rows.map(row => {
            const statusClass = row.status === 'Passed' ? 'text-success' : 'text-danger';
            return `
                <tr>
                    <td>${escapeHtml(row.examinee_name)}</td>
                    <td><a href="exam-responses.php?exam_id=${row.Exam_ID}" class="text-decoration-none">${escapeHtml(row.exam_title)}</a></td>
                    <td>${escapeHtml(row.Subject_Name)}</td>
                    <td>${formatDate(row.Submission_Date)}</td>
                    <td>${row.Score}</td>
                    <td>${row.Percentage}%</td>
                    <td><span class="${statusClass} fw-bold">${row.status}</span></td>
                </tr>
            `;
        }).join('');
    }

    document.getElementById('attemptsPaginationInfo').textContent =
        `Page ${pagination.page || 1} of ${pagination.pages || 1} (${pagination.total || 0} attempts)`;
    document.getElementById('attemptsPrevBtn').disabled = (pagination.page || 1) <= 1;
    document.getElementById('attemptsNextBtn').disabled = (pagination.page || 1) >= (pagination.pages || 1);
}

async function loadStudentSummary(silent = false) {
    const params = getFilterParams({
        student_status: document.getElementById('studentStatusFilter')?.value || 'all'
    });
    const res = await axios.get(`${REPORTS_API}?action=student_summary&${params}`);
    studentSummaryData = res.data.data || [];

    if (silent && currentTab !== 'students') {
        return;
    }

    const tbody = document.getElementById('studentSummaryBody');
    const empty = document.getElementById('studentSummaryEmpty');

    if (!studentSummaryData.length) {
        tbody.innerHTML = '';
        empty.style.display = 'block';
        return;
    }
    empty.style.display = 'none';
    tbody.innerHTML = studentSummaryData.map(s => `
        <tr>
            <td>
                <div class="fw-medium">${escapeHtml(s.Fullname)}</div>
                <small class="text-muted">${escapeHtml(s.Academic_Number || '')}</small>
            </td>
            <td><small>${escapeHtml(s.batch_names || '—')}</small></td>
            <td>${s.exams_taken}</td>
            <td><strong>${s.avg_percentage}%</strong></td>
            <td>${s.pass_rate}%</td>
            <td><small>${escapeHtml(s.best_subject || '—')}</small></td>
            <td><small>${escapeHtml(s.weakest_subject || '—')}</small></td>
            <td>${statusBadge(s.status)}</td>
            <td><button class="btn btn-sm btn-outline-primary" onclick="viewStudentDetail(${s.User_ID})">View</button></td>
        </tr>
    `).join('');
}

async function loadSubjectSummary(silent = false) {
    const params = getFilterParams();
    const res = await axios.get(`${REPORTS_API}?action=subject_summary&${params}`);
    subjectSummaryData = res.data.data || [];

    if (silent && currentTab !== 'subjects') {
        return;
    }

    const tbody = document.getElementById('subjectSummaryBody');
    const empty = document.getElementById('subjectSummaryEmpty');

    if (!subjectSummaryData.length) {
        tbody.innerHTML = '';
        empty.style.display = 'block';
        return;
    }
    empty.style.display = 'none';
    tbody.innerHTML = subjectSummaryData.map(s => `
        <tr>
            <td><strong>${escapeHtml(s.Subject_Name)}</strong><br><small class="text-muted">${escapeHtml(s.Subject_Code || '')}</small></td>
            <td>${escapeHtml(s.Course_Name)}</td>
            <td>${s.exams}</td>
            <td>${s.students}</td>
            <td>${s.attempts}</td>
            <td>${s.avg_percentage}%</td>
            <td><span class="${parseFloat(s.pass_rate) < 70 ? 'text-danger fw-bold' : ''}">${s.pass_rate}%</span></td>
            <td><small>${s.lowest}% – ${s.highest}%</small></td>
            <td><button class="btn btn-sm btn-outline-primary" onclick="viewSubjectDetail(${s.Subject_ID})">View</button></td>
        </tr>
    `).join('');
}

async function viewStudentDetail(userId) {
    const body = document.getElementById('studentDetailBody');
    body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    studentDetailModal.show();

    try {
        const res = await axios.get(`${REPORTS_API}?action=student_detail&user_id=${userId}&${getFilterParams()}`);
        const data = res.data.data;
        document.getElementById('studentDetailTitle').textContent = data.student.Fullname;
        document.getElementById('studentDetailSub').textContent =
            `${data.summary.exams_taken} exams • ${data.summary.avg_percentage}% avg • ${data.summary.pass_rate}% pass rate • ${data.summary.status}`;

        const attemptsHtml = (data.attempts || []).map(a => `
            <tr>
                <td>${escapeHtml(a.exam_title)}</td>
                <td>${escapeHtml(a.Subject_Name)}</td>
                <td>${formatDate(a.Submission_Date)}</td>
                <td>${a.Percentage}%</td>
                <td><span class="${a.status === 'Passed' ? 'text-success' : 'text-danger'}">${a.status}</span></td>
                <td>
                    <a href="exam-responses.php?exam_id=${a.Exam_ID}" class="btn btn-sm btn-link p-0">Exam responses</a>
                </td>
            </tr>
        `).join('');

        body.innerHTML = `
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead class="table-light">
                        <tr><th>Exam</th><th>Subject</th><th>Date</th><th>%</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>${attemptsHtml || '<tr><td colspan="6" class="text-muted">No attempts</td></tr>'}</tbody>
                </table>
            </div>
        `;
    } catch (err) {
        body.innerHTML = `<div class="alert alert-danger">${escapeHtml(err.response?.data?.message || 'Failed to load')}</div>`;
    }
}

async function viewSubjectDetail(subjectId) {
    const body = document.getElementById('subjectDetailBody');
    body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    subjectDetailModal.show();

    try {
        const res = await axios.get(`${REPORTS_API}?action=subject_detail&subject_id=${subjectId}&${getFilterParams()}`);
        const data = res.data.data;
        document.getElementById('subjectDetailTitle').textContent = data.subject.Subject_Name;
        document.getElementById('subjectDetailSub').textContent = data.subject.Course_Name;

        const examsHtml = (data.exams || []).map(e => `
            <tr>
                <td>${escapeHtml(e.exam_title)}</td>
                <td>${e.students}</td>
                <td>${e.attempts}</td>
                <td>${e.avg_percentage}%</td>
                <td>${e.pass_rate}%</td>
                <td><a href="exam-responses.php?exam_id=${e.Exam_ID}" class="btn btn-sm btn-outline-primary">View responses</a></td>
            </tr>
        `).join('');

        body.innerHTML = `
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead class="table-light">
                        <tr><th>Exam</th><th>Students</th><th>Attempts</th><th>Avg %</th><th>Pass Rate</th><th></th></tr>
                    </thead>
                    <tbody>${examsHtml || '<tr><td colspan="6" class="text-muted">No exams</td></tr>'}</tbody>
                </table>
            </div>
        `;
    } catch (err) {
        body.innerHTML = `<div class="alert alert-danger">${escapeHtml(err.response?.data?.message || 'Failed to load')}</div>`;
    }
}

function exportReport() {
    let headers, rows, filename;

    if (currentTab === 'students') {
        headers = ['Name', 'Academic Number', 'Batch', 'Exams', 'Avg %', 'Pass Rate', 'Best Subject', 'Weakest Subject', 'Status'];
        rows = studentSummaryData.map(s => [
            s.Fullname, s.Academic_Number, s.batch_names, s.exams_taken,
            s.avg_percentage, s.pass_rate, s.best_subject, s.weakest_subject, s.status
        ]);
        filename = 'student-performance.csv';
    } else if (currentTab === 'subjects') {
        headers = ['Subject', 'Course', 'Exams', 'Students', 'Attempts', 'Avg %', 'Pass Rate', 'Lowest', 'Highest'];
        rows = subjectSummaryData.map(s => [
            s.Subject_Name, s.Course_Name, s.exams, s.students, s.attempts,
            s.avg_percentage, s.pass_rate, s.lowest, s.highest
        ]);
        filename = 'subject-performance.csv';
    } else {
        headers = ['Name', 'Academic Number', 'Batch', 'Exams', 'Avg %', 'Pass Rate', 'Best Subject', 'Weakest Subject', 'Status'];
        rows = studentSummaryData.map(s => [
            s.Fullname, s.Academic_Number, s.batch_names, s.exams_taken,
            s.avg_percentage, s.pass_rate, s.best_subject, s.weakest_subject, s.status
        ]);
        filename = 'student-performance.csv';
    }

    if (!rows.length) {
        Swal.fire('Info', 'No data to export for current filters.', 'info');
        return;
    }

    downloadCsv(headers, rows, filename);

    const reportType = currentTab === 'subjects' ? 'subject-performance' : 'student-performance';
    axios.post(`${REPORTS_API}?action=log_export`, { report_type: reportType, row_count: rows.length }).catch(() => {});
}

function downloadCsv(headers, rows, filename) {
    const lines = [headers.map(h => csvCell(h)).join(',')];
    rows.forEach(row => lines.push(row.map(v => csvCell(v)).join(',')));
    const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
}

function csvCell(v) {
    return `"${String(v ?? '').replace(/"/g, '""')}"`;
}

function statusBadge(status) {
    const map = {
        'On track': 'bg-success',
        'Needs review': 'bg-warning text-dark',
        'At risk': 'bg-danger',
        'No data': 'bg-secondary'
    };
    const cls = map[status] || 'bg-secondary';
    return `<span class="badge ${cls}">${escapeHtml(status)}</span>`;
}

function formatDate(dateStr) {
    if (!dateStr) return 'N/A';
    return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

function handleLogout(e) {
    e.preventDefault();
    Swal.fire({
        title: 'Logout',
        text: 'Are you sure?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, logout'
    }).then(r => {
        if (r.isConfirmed) {
            fetch('../../api/auth/logout.php', { method: 'POST' })
                .finally(() => { window.location.href = '../../html/auth/login.html'; });
        }
    });
}

window.viewStudentDetail = viewStudentDetail;
window.viewSubjectDetail = viewSubjectDetail;
