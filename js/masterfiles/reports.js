const REPORTS_API = '../../api/masterfiles/reports.php';

function reportsLink(path) {
    const base = window.REPORTS_LINK_BASE || '';
    return `${base}${path}`;
}

let performanceChart, passFailChart, subjectsChart, weakSubjectsChart;
let currentTab = 'overview';
let studentSummaryData = [];
let subjectSummaryData = [];
let atRiskData = [];
let overviewMetrics = {};
let performanceTrendData = [];
let topSubjectsData = [];
let weakSubjectsData = [];
let studentDetailModal, subjectDetailModal;
let filterTimer = null;
let reportsLoading = false;

document.addEventListener('DOMContentLoaded', () => {
    studentDetailModal = new bootstrap.Modal(document.getElementById('studentDetailModal'));
    subjectDetailModal = new bootstrap.Modal(document.getElementById('subjectDetailModal'));

    initializeCharts();
    loadCourses();
    loadBatches();
    bindEvents();
    updateFilterChips();
    updateExportButton();
    loadReports();
});

function bindEvents() {
    document.getElementById('exportReportBtn')?.addEventListener('click', exportReport);
    document.getElementById('clearFiltersBtn')?.addEventListener('click', clearFilters);
    document.getElementById('viewAllAtRiskBtn')?.addEventListener('click', viewAllAtRisk);

    document.getElementById('filterCourse')?.addEventListener('change', async function() {
        document.getElementById('filterBatch').value = '';
        await loadBatches(this.value);
        resetSectionFilter();
        scheduleReportsReload();
    });

    document.getElementById('filterBatch')?.addEventListener('change', async function() {
        await loadSections(this.value);
        scheduleReportsReload();
    });

    document.getElementById('filterSection')?.addEventListener('change', () => {
        scheduleReportsReload();
    });

    document.getElementById('studentStatusFilter')?.addEventListener('change', loadStudentSummary);

    document.querySelectorAll('#reportTabs .nav-link').forEach(btn => {
        btn.addEventListener('click', () => switchTab(btn.dataset.tab));
    });
}

function getFilterParams(extra = {}) {
    const params = new URLSearchParams({
        ...(document.getElementById('filterCourse').value && { course_id: document.getElementById('filterCourse').value }),
        ...(document.getElementById('filterBatch').value && { batch_id: document.getElementById('filterBatch').value }),
        ...(document.getElementById('filterSection').value && { section_id: document.getElementById('filterSection').value }),
        ...extra
    });
    return params;
}

function scheduleReportsReload() {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
        updateFilterChips();
        loadReports();
    }, 200);
}

function resetSectionFilter() {
    const select = document.getElementById('filterSection');
    if (!select) {
        return;
    }
    select.innerHTML = '<option value="">All sections</option>';
    select.value = '';
    select.disabled = true;
}

function selectedOptionText(selectId) {
    const select = document.getElementById(selectId);
    if (!select || !select.value) {
        return '';
    }
    return select.options[select.selectedIndex]?.textContent?.trim() || '';
}

function updateFilterChips() {
    const chipsEl = document.getElementById('filterChips');
    const clearBtn = document.getElementById('clearFiltersBtn');
    if (!chipsEl) {
        return;
    }

    const chips = [];
    const course = selectedOptionText('filterCourse');
    const batch = selectedOptionText('filterBatch');
    const section = selectedOptionText('filterSection');

    if (course) chips.push({ label: 'Course', value: course });
    if (batch) chips.push({ label: 'Batch', value: batch });
    if (section) chips.push({ label: 'Section', value: section });

    chipsEl.innerHTML = chips.length
        ? chips.map(c => `<span class="reports-filter-chip"><strong>${escapeHtml(c.label)}:</strong>&nbsp;${escapeHtml(c.value)}</span>`).join('')
        : '<span class="text-muted small">Showing all courses and batches</span>';

    clearBtn?.classList.toggle('d-none', chips.length === 0);
}

async function clearFilters() {
    document.getElementById('filterCourse').value = '';
    document.getElementById('filterBatch').value = '';
    resetSectionFilter();
    await loadBatches();
    updateFilterChips();
    loadReports();
}

function switchTab(tab) {
    currentTab = tab;
    document.querySelectorAll('#reportTabs .nav-link').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tab);
    });
    document.getElementById('tabOverview').style.display = tab === 'overview' ? 'block' : 'none';
    document.getElementById('tabStudents').style.display = tab === 'students' ? 'block' : 'none';
    document.getElementById('tabSubjects').style.display = tab === 'subjects' ? 'block' : 'none';

    updateExportButton();

    if (tab === 'students') loadStudentSummary();
    if (tab === 'subjects') loadSubjectSummary();
}

function updateExportButton() {
    const btn = document.getElementById('exportReportBtn');
    if (!btn) return;

    if (currentTab === 'overview') {
        btn.innerHTML = '<i class="bi bi-download me-2"></i>Export Overview CSV';
    } else if (currentTab === 'subjects') {
        btn.innerHTML = '<i class="bi bi-download me-2"></i>Export Subjects CSV';
    } else {
        btn.innerHTML = '<i class="bi bi-download me-2"></i>Export Students CSV';
    }
}

function setReportsLoading(loading) {
    reportsLoading = loading;
    document.getElementById('reportsLoadingBar')?.classList.toggle('d-none', !loading);
    document.getElementById('metricsRow')?.classList.toggle('is-loading', loading);
}

async function loadReports() {
    const params = getFilterParams();
    setReportsLoading(true);

    try {
        const tasks = [
            loadMetrics(params),
            loadAtRisk(params),
            loadPerformanceTrend(params),
            loadPassFail(params),
            loadTopSubjects(params),
            loadWeakSubjects(params)
        ];

        if (currentTab === 'students') {
            tasks.push(loadStudentSummary());
        } else if (currentTab === 'subjects') {
            tasks.push(loadSubjectSummary());
        }

        await Promise.all(tasks);
    } catch (error) {
        Swal.fire('Error', 'Failed to load reports data', 'error');
    } finally {
        setReportsLoading(false);
    }
}

function atRiskSummaryText() {
    return 'Overdue untaken exams count as failed once the deadline passes. Upcoming exams are excluded. General rules: pass rate below 60%, 2+ effective failures, or average below 60%.';
}

function passFailPercentLabel(value, total) {
    if (!total) return '0.0%';
    return `${((value / total) * 100).toFixed(1)}%`;
}

function passFailChartPluginOptions() {
    return {
        legend: {
            position: 'bottom',
            labels: {
                generateLabels(chart) {
                    const dataset = chart.data.datasets[0];
                    const data = dataset.data || [];
                    const total = data.reduce((sum, v) => sum + (Number(v) || 0), 0);
                    const colors = dataset.backgroundColor || [];
                    return (chart.data.labels || []).map((label, i) => {
                        const value = Number(data[i]) || 0;
                        const pct = passFailPercentLabel(value, total);
                        return {
                            text: `${label}: ${value} (${pct})`,
                            fillStyle: colors[i],
                            strokeStyle: colors[i],
                            lineWidth: 0,
                            hidden: false,
                            index: i
                        };
                    });
                }
            }
        },
        tooltip: {
            callbacks: {
                label(context) {
                    const data = context.dataset.data || [];
                    const total = data.reduce((sum, v) => sum + (Number(v) || 0), 0);
                    const value = Number(context.parsed) || 0;
                    const pct = passFailPercentLabel(value, total);
                    return `${context.label}: ${value} (${pct})`;
                }
            }
        }
    };
}

function initializeCharts() {
    const chartDefaults = { responsive: true, maintainAspectRatio: false };

    performanceChart = new Chart(document.getElementById('performanceChart'), {
        type: 'line',
        data: { labels: [], datasets: [{ label: 'Average Score (%)', data: [], borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,0.1)', tension: 0.4, fill: true }] },
        options: {
            ...chartDefaults,
            scales: {
                x: { ticks: { maxRotation: 45, minRotation: 0 } },
                y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        title: items => {
                            const idx = items[0]?.dataIndex;
                            const row = performanceTrendData[idx];
                            return row?.exam_title || items[0]?.label || '';
                        },
                        label: context => `Avg score: ${context.parsed.y}%`,
                        afterLabel: context => {
                            const row = performanceTrendData[context.dataIndex];
                            if (!row) return '';
                            const lines = [];
                            if (row.date) lines.push(`Date: ${formatDate(row.date)}`);
                            if (row.submission_count != null) {
                                lines.push(`Attempts: ${row.submission_count}`);
                            }
                            return lines;
                        }
                    }
                }
            }
        }
    });

    passFailChart = new Chart(document.getElementById('passFailChart'), {
        type: 'pie',
        data: { labels: ['Passed', 'Failed'], datasets: [{ data: [0, 0], backgroundColor: ['#10b981', '#ef4444'] }] },
        options: { ...chartDefaults, plugins: passFailChartPluginOptions() }
    });

    const subjectBarOptions = (dataRef) => ({
        ...chartDefaults,
        indexAxis: 'y',
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: context => {
                        const row = dataRef[context.dataIndex];
                        const attempts = row?.attempt_count;
                        return attempts != null
                            ? `Avg: ${context.parsed.x}% (${attempts} attempt${attempts === 1 ? '' : 's'})`
                            : `Avg: ${context.parsed.x}%`;
                    }
                }
            }
        },
        scales: { x: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } } }
    });

    subjectsChart = new Chart(document.getElementById('subjectsChart'), {
        type: 'bar',
        data: { labels: [], datasets: [{ label: 'Avg %', data: [], backgroundColor: '#a855f7' }] },
        options: subjectBarOptions(topSubjectsData)
    });

    weakSubjectsChart = new Chart(document.getElementById('weakSubjectsChart'), {
        type: 'bar',
        data: { labels: [], datasets: [{ label: 'Avg %', data: [], backgroundColor: '#ef4444' }] },
        options: subjectBarOptions(weakSubjectsData)
    });
}

function setChartEmpty(canvasId, emptyId, isEmpty) {
    const canvas = document.getElementById(canvasId);
    const empty = document.getElementById(emptyId);
    if (canvas) canvas.style.visibility = isEmpty ? 'hidden' : 'visible';
    empty?.classList.toggle('d-none', !isEmpty);
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

async function loadSections(batchId = '') {
    const select = document.getElementById('filterSection');
    if (!select) {
        return;
    }

    const batch = batchId || document.getElementById('filterBatch').value;
    if (!batch) {
        resetSectionFilter();
        return;
    }

    const res = await axios.get(`${REPORTS_API}?action=sections&batch_id=${batch}`);
    select.innerHTML = '<option value="">All sections</option>';
    (res.data.data || []).forEach(section => {
        const opt = document.createElement('option');
        opt.value = section.Section_ID;
        opt.textContent = section.Section_Name;
        select.appendChild(opt);
    });
    select.disabled = false;
}

async function loadMetrics(params) {
    const res = await axios.get(`${REPORTS_API}?action=metrics&${params}`);
    const d = res.data.data;
    overviewMetrics = d;

    document.getElementById('activeExaminees').textContent = d.active_examinees;
    document.getElementById('examsAdministered').textContent = d.exams_administered;
    document.getElementById('passRate').textContent = d.pass_rate + '%';
    document.getElementById('avgScore').textContent = d.avg_score + '%';
}

async function loadAtRisk(params) {
    const res = await axios.get(`${REPORTS_API}?action=at_risk&${params}`);
    const payload = res.data.data || {};
    const rows = payload.students || (Array.isArray(payload) ? payload : []);
    const summary = payload.summary || null;
    atRiskData = rows;
    const tbody = document.getElementById('atRiskBody');
    const summaryEl = document.getElementById('atRiskSummary');
    const viewAllBtn = document.getElementById('viewAllAtRiskBtn');

    if (summaryEl) {
        if (summary) {
            const total = summary.total_at_risk ?? summary.count ?? rows.length;
            const displayed = summary.displayed ?? rows.length;
            let text = `${total} at-risk (${summary.percent}% of ${summary.total_examinees} examinees with assigned exams)`;
            if (total > displayed) {
                text += ` — showing ${displayed} of ${total}`;
            }
            summaryEl.textContent = text;
        } else {
            summaryEl.textContent = atRiskSummaryText();
        }
    }

    const totalAtRisk = summary?.total_at_risk ?? rows.length;
    viewAllBtn?.classList.toggle('d-none', totalAtRisk <= 0);

    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">No at-risk students for the selected filters.</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map(r => `
        <tr>
            <td><strong>${escapeHtml(r.Fullname)}</strong><br><small class="text-muted">${escapeHtml(r.Academic_Number || '')}</small></td>
            <td>${r.exams_assigned ?? '—'}</td>
            <td>${r.exams_taken ?? 0}</td>
            <td>${r.missing_overdue > 0 ? `<span class="text-danger fw-bold">${r.missing_overdue}</span>` : '0'}</td>
            <td>${r.avg_percentage ?? 0}%</td>
            <td>${r.pass_rate ?? 0}%</td>
            <td>${statusBadge(r.status)}</td>
            <td><button class="btn btn-sm btn-outline-primary" onclick="viewStudentDetail(${r.User_ID})">View</button></td>
        </tr>
    `).join('');
}

function viewAllAtRisk() {
    const statusFilter = document.getElementById('studentStatusFilter');
    if (statusFilter) statusFilter.value = 'at_risk';
    switchTab('students');
}

async function loadPerformanceTrend(params) {
    const res = await axios.get(`${REPORTS_API}?action=performance_trend&${params}`);
    performanceTrendData = res.data.data || [];
    const isEmpty = !performanceTrendData.length;

    performanceChart.data.labels = performanceTrendData.map(i => formatDate(i.date) || truncateLabel(i.exam_title, 18));
    performanceChart.data.datasets[0].data = isEmpty ? [] : performanceTrendData.map(i => parseFloat(i.avg_score));
    performanceChart.update();
    setChartEmpty('performanceChart', 'performanceChartEmpty', isEmpty);
}

async function loadPassFail(params) {
    const res = await axios.get(`${REPORTS_API}?action=pass_fail&${params}`);
    const d = res.data.data || {};
    const passed = parseInt(d.passed, 10) || 0;
    const failed = parseInt(d.failed, 10) || 0;
    const total = parseInt(d.total, 10) || passed + failed;
    const isEmpty = total === 0;

    passFailChart.data.datasets[0].data = isEmpty ? [0, 0] : [passed, failed];
    passFailChart.update();
    setChartEmpty('passFailChart', 'passFailChartEmpty', isEmpty);

    const subtitle = document.getElementById('passFailSubtitle');
    if (subtitle) {
        subtitle.textContent = isEmpty
            ? 'Due assigned exams in scope'
            : `${total} due assigned exam${total === 1 ? '' : 's'} (matches Overall Pass Rate)`;
    }
}

async function loadTopSubjects(params) {
    const res = await axios.get(`${REPORTS_API}?action=top_subjects&${params}`);
    topSubjectsData = res.data.data || [];
    const isEmpty = !topSubjectsData.length;

    subjectsChart.data.labels = topSubjectsData.map(i => truncateLabel(i.Subject_Name, 28));
    subjectsChart.data.datasets[0].data = isEmpty ? [] : topSubjectsData.map(i => parseFloat(i.avg_score));
    subjectsChart.update();
    setChartEmpty('subjectsChart', 'subjectsChartEmpty', isEmpty);
}

async function loadWeakSubjects(params) {
    const res = await axios.get(`${REPORTS_API}?action=weak_subjects&${params}`);
    weakSubjectsData = res.data.data || [];
    const isEmpty = !weakSubjectsData.length;

    weakSubjectsChart.data.labels = weakSubjectsData.map(i => truncateLabel(i.Subject_Name, 28));
    weakSubjectsChart.data.datasets[0].data = isEmpty ? [] : weakSubjectsData.map(i => parseFloat(i.avg_score));
    weakSubjectsChart.update();
    setChartEmpty('weakSubjectsChart', 'weakSubjectsChartEmpty', isEmpty);
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
            <td><small>${escapeHtml(s.section_names || '—')}</small></td>
            <td>${s.exams_assigned ?? 0}</td>
            <td>${s.exams_taken ?? 0}</td>
            <td>${s.missing_overdue > 0 ? `<span class="text-danger fw-bold">${s.missing_overdue}</span>` : '0'}</td>
            <td><strong>${s.avg_percentage ?? 0}%</strong></td>
            <td>${s.pass_rate ?? 0}%</td>
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
            <td><strong>${escapeHtml(s.Subject_Name)}</strong></td>
            <td>${escapeHtml(s.Course_Name)}</td>
            <td>${s.exams}</td>
            <td>${s.students}</td>
            <td>${s.avg_percentage}%</td>
            <td>${s.pass_rate}%</td>
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
        document.getElementById('studentDetailSub').innerHTML =
            `${data.summary.exams_assigned ?? 0} assigned • ${data.summary.exams_taken} taken • ${data.summary.missing_overdue ?? 0} overdue missing • ${data.summary.avg_percentage}% avg • ${data.summary.pass_rate}% pass rate • ${statusBadge(data.summary.status)}`;

        const attemptsHtml = (data.attempts || []).map(a => `
            <tr>
                <td>${escapeHtml(a.exam_title)}</td>
                <td>${escapeHtml(a.Subject_Name)}</td>
                <td>${formatDate(a.Submission_Date)}</td>
                <td>${a.Percentage}% <small class="text-muted">/ ${a.Passing_Score}%</small></td>
                <td><span class="${a.status === 'Passed' ? 'text-success' : 'text-danger'}">${a.status}</span></td>
                <td>
                    <a href="${reportsLink(`exam-responses.php?exam_id=${a.Exam_ID}`)}" class="btn btn-sm btn-link p-0">Responses</a>
                </td>
            </tr>
        `).join('');

        body.innerHTML = `
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead class="table-light">
                        <tr><th>Exam</th><th>Subject</th><th>Date</th><th>Score</th><th>Status</th><th></th></tr>
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
                <td>${e.pass_rate}% <small class="text-muted">/ ${e.Passing_Score ?? '—'}%</small></td>
                <td><a href="${reportsLink(`exam-responses.php?exam_id=${e.Exam_ID}`)}" class="btn btn-sm btn-add-user">View responses</a></td>
            </tr>
        `).join('');

        body.innerHTML = `
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead class="table-light">
                        <tr><th>Exam</th><th>Students</th><th>Due assigned</th><th>Avg %</th><th>Pass Rate</th><th></th></tr>
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
    let headers, rows, filename, reportType;

    if (currentTab === 'students') {
        headers = ['Name', 'Academic Number', 'Batch', 'Section', 'Assigned', 'Taken', 'Missing Overdue', 'Avg %', 'Pass Rate', 'Best Subject', 'Weakest Subject', 'Status'];
        rows = studentSummaryData.map(s => [
            s.Fullname, s.Academic_Number, s.batch_names, s.section_names,
            s.exams_assigned, s.exams_taken, s.missing_overdue,
            s.avg_percentage, s.pass_rate, s.best_subject, s.weakest_subject, s.status
        ]);
        filename = 'student-performance.csv';
        reportType = 'student-performance';
    } else if (currentTab === 'subjects') {
        headers = ['Subject', 'Course', 'Exams', 'Students', 'Avg %', 'Pass Rate', 'Lowest', 'Highest'];
        rows = subjectSummaryData.map(s => [
            s.Subject_Name, s.Course_Name, s.exams, s.students,
            s.avg_percentage, s.pass_rate, s.lowest, s.highest
        ]);
        filename = 'subject-performance.csv';
        reportType = 'subject-performance';
    } else {
        headers = ['Metric', 'Value'];
        rows = [
            ['Students Assigned', overviewMetrics.active_examinees ?? ''],
            ['Exams (non-draft)', overviewMetrics.exams_administered ?? ''],
            ['Students with Finished Attempts', overviewMetrics.students_with_results ?? ''],
            ['Exams with Finished Attempts', overviewMetrics.exams_with_results ?? ''],
            ['Finished Attempts', overviewMetrics.finished_submissions ?? ''],
            ['Overall Pass Rate (%)', overviewMetrics.pass_rate ?? ''],
            ['Average Score (%)', overviewMetrics.avg_score ?? ''],
            ['At-Risk Count', overviewMetrics.at_risk_count ?? ''],
            ['At-Risk (%)', overviewMetrics.at_risk_percent ?? '']
        ];
        filename = 'reports-overview.csv';
        reportType = 'overview';

        if (atRiskData.length) {
            rows.push(['', '']);
            rows.push(['--- At-Risk Students (preview) ---', '']);
            rows.push(['Name', 'Academic Number', 'Assigned', 'Taken', 'Missing Overdue', 'Avg %', 'Pass Rate']);
            atRiskData.forEach(r => {
                rows.push([
                    r.Fullname, r.Academic_Number,
                    r.exams_assigned, r.exams_taken, r.missing_overdue,
                    r.avg_percentage, r.pass_rate
                ]);
            });
        }
    }

    if (!rows.length) {
        Swal.fire('Info', 'No data to export for current filters.', 'info');
        return;
    }

    downloadCsv(headers, rows, filename);
    axios.post(`${REPORTS_API}?action=log_export`, { report_type: reportType, row_count: rows.length }).catch(() => {});
}

function downloadCsv(headers, rows, filename) {
    const filterNote = buildFilterNoteRow();
    const lines = [];
    if (filterNote) lines.push(filterNote);
    lines.push(headers.map(h => csvCell(h)).join(','));
    rows.forEach(row => lines.push(row.map(v => csvCell(v)).join(',')));
    const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
}

function buildFilterNoteRow() {
    const parts = [];
    const course = selectedOptionText('filterCourse');
    const batch = selectedOptionText('filterBatch');
    const section = selectedOptionText('filterSection');
    if (course) parts.push(`Course: ${course}`);
    if (batch) parts.push(`Batch: ${batch}`);
    if (section) parts.push(`Section: ${section}`);
    return parts.length ? `"Filters: ${parts.join(' | ').replace(/"/g, '""')}"` : '';
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

function truncateLabel(text, maxLen = 24) {
    const str = String(text ?? '');
    return str.length > maxLen ? `${str.slice(0, maxLen - 1)}…` : str;
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

window.viewStudentDetail = viewStudentDetail;
window.viewSubjectDetail = viewSubjectDetail;
