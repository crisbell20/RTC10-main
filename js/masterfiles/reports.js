const REPORTS_API = '../../api/masterfiles/reports.php';

function reportsLink(path) {
    const base = window.REPORTS_LINK_BASE || '';
    return `${base}${path}`;
}

function reportsResponsesLink(examId) {
    return reportsLink(`exam-responses.php?exam_id=${examId}&return=reports&tab=${currentTab}`);
}

let performanceChart, passFailChart, subjectsChart, weakSubjectsChart;
let currentTab = 'overview';
let traineeSummaryData = [];
let traineeSummaryMeta = { mode: 'all' };
let subjectSummaryData = [];
let overviewMetrics = {};
let performanceTrendData = [];
let topSubjectsData = [];
let weakSubjectsData = [];
let traineeDetailModal, subjectDetailModal;
let filterTimer = null;
let reportsLoading = false;

document.addEventListener('DOMContentLoaded', () => {
    traineeDetailModal = new bootstrap.Modal(document.getElementById('traineeDetailModal'));
    subjectDetailModal = new bootstrap.Modal(document.getElementById('subjectDetailModal'));

    initializeCharts();
    loadCourses().then(() => loadSubjects());
    loadBatches();
    bindEvents();
    updateFilterChips();
    updateExportButton();
    const urlTab = new URLSearchParams(window.location.search).get('tab');
    loadReports().then(() => {
        if (urlTab && ['overview', 'trainees', 'students', 'subjects'].includes(urlTab)) {
            switchTab(urlTab === 'students' ? 'trainees' : urlTab);
        }
    });
});

function bindEvents() {
    document.getElementById('exportReportBtn')?.addEventListener('click', exportReport);
    document.getElementById('clearFiltersBtn')?.addEventListener('click', clearFilters);
    document.getElementById('traineeSummaryBody')?.addEventListener('click', handleReportTraineeRowClick);
    document.getElementById('subjectSummaryBody')?.addEventListener('click', handleReportSubjectRowClick);

    document.getElementById('filterCourse')?.addEventListener('change', async function() {
        document.getElementById('filterBatch').value = '';
        document.getElementById('filterSubject').value = '';
        await loadBatches(this.value);
        await loadSubjects(this.value);
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

    document.getElementById('filterSubject')?.addEventListener('change', () => {
        scheduleReportsReload();
    });

    document.getElementById('traineeStatusFilter')?.addEventListener('change', loadTraineeSummary);
    document.getElementById('subjectSortFilter')?.addEventListener('change', renderSubjectSummaryTable);

    document.querySelectorAll('#reportTabs .nav-link').forEach(btn => {
        btn.addEventListener('click', () => switchTab(btn.dataset.tab));
    });
}

function getFilterParams(extra = {}) {
    const params = new URLSearchParams({
        ...(document.getElementById('filterCourse').value && { course_id: document.getElementById('filterCourse').value }),
        ...(document.getElementById('filterBatch').value && { batch_id: document.getElementById('filterBatch').value }),
        ...(document.getElementById('filterSection').value && { section_id: document.getElementById('filterSection').value }),
        ...(document.getElementById('filterSubject')?.value && { subject_id: document.getElementById('filterSubject').value }),
        ...extra
    });
    return params;
}

function isSubjectFilterActive() {
    return Boolean(document.getElementById('filterSubject')?.value);
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
    const subject = selectedOptionText('filterSubject');

    if (course) chips.push({ label: 'Course', value: course });
    if (batch) chips.push({ label: 'Batch', value: batch });
    if (section) chips.push({ label: 'Section', value: section });
    if (subject) chips.push({ label: 'Subject', value: subject });

    chipsEl.innerHTML = chips.length
        ? chips.map(c => `<span class="reports-filter-chip"><strong>${escapeHtml(c.label)}:</strong>&nbsp;${escapeHtml(c.value)}</span>`).join('')
        : '<span class="text-muted small">Showing all courses, batches, and subjects</span>';

    clearBtn?.classList.toggle('d-none', chips.length === 0);
}

async function clearFilters() {
    document.getElementById('filterCourse').value = '';
    document.getElementById('filterBatch').value = '';
    document.getElementById('filterSubject').value = '';
    resetSectionFilter();
    await loadBatches();
    await loadSubjects();
    updateFilterChips();
    loadReports();
}

function switchTab(tab) {
    currentTab = tab;
    document.querySelectorAll('#reportTabs .nav-link').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tab);
    });
    document.getElementById('tabOverview').style.display = tab === 'overview' ? 'block' : 'none';
    document.getElementById('tabTrainees').style.display = tab === 'trainees' ? 'block' : 'none';
    document.getElementById('tabSubjects').style.display = tab === 'subjects' ? 'block' : 'none';

    updateExportButton();

    if (tab === 'trainees') loadTraineeSummary();
    if (tab === 'subjects') loadSubjectSummary();
}

function updateExportButton() {
    const btn = document.getElementById('exportReportBtn');
    if (!btn) return;
    const showExport = currentTab === 'trainees' || currentTab === 'subjects';
    btn.classList.toggle('d-none', !showExport);
    btn.hidden = !showExport;
    btn.innerHTML = '<i class="bi bi-download me-2"></i>Export';
}

function setReportsLoading(loading) {
    reportsLoading = loading;
    document.getElementById('reportsLoadingBar')?.classList.toggle('d-none', !loading);
    document.getElementById('metricsRow')?.classList.toggle('is-loading', loading);
    document.getElementById('traineeMetricsRow')?.classList.toggle('is-loading', loading);
}

async function loadReports() {
    const params = getFilterParams();
    setReportsLoading(true);

    try {
        const tasks = [
            loadMetrics(params),
            loadPerformanceTrend(params),
            loadPassFail(params),
            loadTopSubjects(params),
            loadWeakSubjects(params)
        ];

        if (currentTab === 'trainees') {
            tasks.push(loadTraineeSummary());
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

const subjectBarLabelPlugin = {
    id: 'subjectBarLabelPlugin',
    afterDatasetsDraw(chart) {
        const rows = chart.options.plugins?.subjectBarLabelPlugin?.rows;
        if (!rows?.length) {
            return;
        }

        const { ctx } = chart;
        const meta = chart.getDatasetMeta(0);

        meta.data.forEach((bar, index) => {
            const row = rows[index];
            if (!row) {
                return;
            }

            const avg = formatGrade(row.avg_grade ?? row.avg_score ?? row.avg_raw_score ?? 0);
            const pass = formatGrade(row.pass_rate ?? 0);
            const label = `${avg}% Average | ${pass}% Passing Rate`;

            const barLeft = Math.min(bar.base, bar.x);
            const barWidth = Math.abs(bar.x - bar.base);
            if (barWidth < 48) {
                return;
            }

            ctx.save();
            ctx.beginPath();
            ctx.rect(barLeft, bar.y - (bar.height / 2), barWidth, bar.height);
            ctx.clip();
            ctx.fillStyle = '#ffffff';
            ctx.font = '600 11px system-ui, -apple-system, "Segoe UI", sans-serif';
            ctx.textAlign = 'left';
            ctx.textBaseline = 'middle';
            ctx.fillText(label, barLeft + 8, bar.y);
            ctx.restore();
        });
    }
};

if (typeof Chart !== 'undefined' && Chart.register) {
    Chart.register(subjectBarLabelPlugin);
}

function subjectBarChartOptions() {
    return {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        plugins: {
            legend: { display: false },
            subjectBarLabelPlugin: { rows: [] },
            tooltip: {
                callbacks: {
                    label: context => {
                        const rows = context.chart.options.plugins?.subjectBarLabelPlugin?.rows || [];
                        const row = rows[context.dataIndex];
                        const avg = formatGrade(row?.avg_grade ?? row?.avg_score ?? context.parsed.x ?? 0);
                        const pass = formatGrade(row?.pass_rate ?? 0);
                        return `${avg}% Average | ${pass}% Passing Rate`;
                    }
                }
            }
        },
        scales: { x: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } } }
    };
}

function updateSubjectBarChart(chart, rows) {
    const isEmpty = !rows.length;
    chart.data.labels = rows.map(i => truncateLabel(i.Subject_Name, 28));
    chart.data.datasets[0].data = isEmpty ? [] : rows.map(i => parseFloat(i.avg_grade ?? i.avg_score ?? 0));
    chart.options.plugins.subjectBarLabelPlugin = { rows };
    chart.update();
}

function initializeCharts() {
    const chartDefaults = { responsive: true, maintainAspectRatio: false };

    performanceChart = new Chart(document.getElementById('performanceChart'), {
        type: 'line',
        data: { labels: [], datasets: [{ label: 'Average (%)', data: [], borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,0.1)', tension: 0.4, fill: true }] },
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
                        label: context => {
                            const grade = context.parsed.y;
                            return `Average: ${grade}%`;
                        },
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

    subjectsChart = new Chart(document.getElementById('subjectsChart'), {
        type: 'bar',
        data: { labels: [], datasets: [{ label: 'Avg %', data: [], backgroundColor: '#a855f7' }] },
        options: subjectBarChartOptions()
    });

    weakSubjectsChart = new Chart(document.getElementById('weakSubjectsChart'), {
        type: 'bar',
        data: { labels: [], datasets: [{ label: 'Avg %', data: [], backgroundColor: '#ef4444' }] },
        options: subjectBarChartOptions()
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

async function loadSubjects(courseId = '') {
    const select = document.getElementById('filterSubject');
    if (!select) {
        return;
    }

    const previousValue = select.value;
    const course = courseId || document.getElementById('filterCourse').value;
    const url = course
        ? `../../api/masterfiles/subjects.php?action=list&course_id=${encodeURIComponent(course)}`
        : '../../api/masterfiles/subjects.php?action=list';
    const res = await axios.get(url);

    select.innerHTML = '<option value="">All subjects</option>';
    (res.data.data || []).forEach(subject => {
        const opt = document.createElement('option');
        opt.value = subject.Subject_ID;
        opt.textContent = subject.Subject_Name;
        select.appendChild(opt);
    });

    if (previousValue && select.querySelector(`option[value="${previousValue}"]`)) {
        select.value = previousValue;
    }
}

async function loadMetrics(params) {
    const res = await axios.get(`${REPORTS_API}?action=metrics&${params}`);
    const d = res.data.data;
    overviewMetrics = d;

    document.getElementById('activeTrainees').textContent = d.active_examinees;
    document.getElementById('examsAdministered').textContent = d.exams_administered;
    document.getElementById('passRate').textContent = d.pass_rate + '%';
    document.getElementById('avgScore').textContent = `${formatGrade(d.avg_grade ?? d.avg_score)}%`;
}

function handleReportTraineeRowClick(event) {
    const row = event.target.closest('tr[data-user-id]');
    if (!row) return;
    viewTraineeDetail(parseInt(row.dataset.userId, 10));
}

function handleReportSubjectRowClick(event) {
    const row = event.target.closest('tr[data-subject-id]');
    if (!row) return;
    viewSubjectDetail(parseInt(row.dataset.subjectId, 10));
}

async function loadPerformanceTrend(params) {
    const res = await axios.get(`${REPORTS_API}?action=performance_trend&${params}`);
    performanceTrendData = res.data.data || [];
    const isEmpty = !performanceTrendData.length;

    performanceChart.data.labels = performanceTrendData.map(i => formatDate(i.date) || truncateLabel(i.exam_title, 18));
    performanceChart.data.datasets[0].data = isEmpty ? [] : performanceTrendData.map(i => parseFloat(i.avg_grade ?? i.avg_score));
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
            ? 'Exams in scope'
            : `${total} exam${total === 1 ? '' : 's'} in scope`;
    }
}

async function loadTopSubjects(params) {
    const res = await axios.get(`${REPORTS_API}?action=top_subjects&${params}`);
    topSubjectsData = res.data.data || [];
    updateSubjectBarChart(subjectsChart, topSubjectsData);
    setChartEmpty('subjectsChart', 'subjectsChartEmpty', !topSubjectsData.length);
}

async function loadWeakSubjects(params) {
    const res = await axios.get(`${REPORTS_API}?action=weak_subjects&${params}`);
    weakSubjectsData = res.data.data || [];
    updateSubjectBarChart(weakSubjectsChart, weakSubjectsData);
    setChartEmpty('weakSubjectsChart', 'weakSubjectsChartEmpty', !weakSubjectsData.length);
}

function renderTraineeRankingsRow(s) {
    const standing = s.standing ?? '—';
    const standingClass = standing === 1 ? 'reports-standing-badge is-top' : 'reports-standing-badge';
    const rank = s.Personnel_Rank || '—';
    return `<tr class="reports-clickable-row" data-user-id="${s.User_ID}">
        <td><span class="${standingClass}">${standing}</span></td>
        <td>${escapeHtml(String(rank))}</td>
        <td>
            <div class="fw-medium">${escapeHtml(s.Fullname)}</div>
            <small class="text-muted">${escapeHtml(s.Badge_Number || '—')}</small>
        </td>
        <td>${s.exams_assigned ?? 0}</td>
        <td>${s.exams_taken ?? 0}</td>
        <td>${s.missing_overdue > 0 ? `<span class="text-danger fw-bold">${s.missing_overdue}</span>` : '0'}</td>
        <td>${formatAvgScoreCell(s)}</td>
        <td>${s.pass_rate ?? 0}%</td>
        <td>${statusBadge(s.status)}</td>
    </tr>`;
}

function traineeRankingsTableHead() {
    return `<tr>
        <th>Standing</th>
        <th>Rank</th>
        <th>Trainee</th>
        <th>Assigned</th>
        <th>Taken</th>
        <th>Missing</th>
        <th>Average</th>
        <th>Pass Rate</th>
        <th>Status</th>
    </tr>`;
}

async function loadTraineeSummary(silent = false) {
    const params = getFilterParams({
        student_status: document.getElementById('traineeStatusFilter')?.value || 'all'
    });
    const res = await axios.get(`${REPORTS_API}?action=student_summary&${params}`);
    const payload = res.data.data || {};
    traineeSummaryData = payload.students ?? (Array.isArray(payload) ? payload : []);
    traineeSummaryMeta = payload.meta || { mode: isSubjectFilterActive() ? 'by_subject' : 'all' };

    if (silent && currentTab !== 'trainees') {
        return;
    }

    updateTraineeSummaryHeader(traineeSummaryMeta);
    renderTraineeSummaryCards(traineeSummaryMeta.summary || {});
    renderTraineeSummaryTable(traineeSummaryData, traineeSummaryMeta);
}

function renderTraineeSummaryCards(summary = {}) {
    const totalEl = document.getElementById('traineeTotalCount');
    const onTrackEl = document.getElementById('traineeOnTrackCount');
    const atRiskEl = document.getElementById('traineeAtRiskCount');

    if (!totalEl || !onTrackEl || !atRiskEl) {
        return;
    }

    totalEl.textContent = summary.total_examinees ?? 0;
    onTrackEl.textContent = summary.on_track ?? 0;
    atRiskEl.textContent = summary.at_risk ?? 0;
}

function updateTraineeSummaryHeader(meta = {}) {
    const titleEl = document.getElementById('traineeSummaryTitle');
    const subEl = document.getElementById('traineeSummarySub');
    const isBySubject = meta.mode === 'by_subject';
    const subjectName = meta.subject_name || selectedOptionText('filterSubject') || 'selected subject';

    if (titleEl) {
        titleEl.textContent = 'Trainee Rankings';
    }
    if (subEl) {
        subEl.textContent = isBySubject
            ? `Ranked by average grade in ${subjectName}`
            : 'Ranked by average grade';
    }
}

function renderTraineeSummaryTable(rows, meta = {}) {
    const tbody = document.getElementById('traineeSummaryBody');
    const empty = document.getElementById('traineeSummaryEmpty');
    const head = document.getElementById('traineeSummaryHead');
    const isBySubject = meta.mode === 'by_subject';

    if (head) {
        head.innerHTML = traineeRankingsTableHead();
    }

    if (!rows.length) {
        tbody.innerHTML = '';
        empty.style.display = 'block';
        empty.textContent = isBySubject
            ? 'No trainees with assigned exams for this subject in the selected scope.'
            : 'No trainee data for selected filters.';
        return;
    }

    empty.style.display = 'none';
    tbody.innerHTML = rows.map(renderTraineeRankingsRow).join('');
}

async function loadSubjectSummary(silent = false) {
    const params = getFilterParams();
    const res = await axios.get(`${REPORTS_API}?action=subject_summary&${params}`);
    subjectSummaryData = res.data.data || [];

    if (silent && currentTab !== 'subjects') {
        return;
    }

    renderSubjectSummaryTable();
}

function getSubjectAverageScore(row) {
    const value = parseFloat(row.avg_grade ?? row.avg_percentage ?? 0);
    return Number.isNaN(value) ? 0 : value;
}

function sortSubjectSummaryData(data, sortOrder = 'top_to_lowest') {
    const sorted = [...data];
    if (sortOrder === 'lowest_to_top') {
        sorted.sort((a, b) => getSubjectAverageScore(a) - getSubjectAverageScore(b));
    } else {
        sorted.sort((a, b) => getSubjectAverageScore(b) - getSubjectAverageScore(a));
    }
    return sorted;
}

function renderSubjectSummaryTable() {
    const tbody = document.getElementById('subjectSummaryBody');
    const empty = document.getElementById('subjectSummaryEmpty');
    const sortOrder = document.getElementById('subjectSortFilter')?.value || 'top_to_lowest';
    const rows = sortSubjectSummaryData(subjectSummaryData, sortOrder);

    if (!rows.length) {
        tbody.innerHTML = '';
        empty.style.display = 'block';
        return;
    }
    empty.style.display = 'none';
    tbody.innerHTML = rows.map(s => `
        <tr class="reports-clickable-row" data-subject-id="${s.Subject_ID}">
            <td><strong>${escapeHtml(s.Subject_Name)}</strong></td>
            <td>${escapeHtml(s.Course_Name)}</td>
            <td>${s.exams}</td>
            <td>${formatAvgScoreCell(s)}</td>
            <td>${s.pass_rate}%</td>
        </tr>
    `).join('');
}

async function viewTraineeDetail(userId) {
    const body = document.getElementById('traineeDetailBody');
    body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    traineeDetailModal.show();

    try {
        const res = await axios.get(`${REPORTS_API}?action=student_detail&user_id=${userId}&${getFilterParams()}`);
        const data = res.data.data;
        document.getElementById('traineeDetailTitle').textContent = data.trainee?.Fullname ?? data.student?.Fullname ?? 'Trainee Detail';

        const attemptsHtml = (data.attempts || []).map(a => {
            const inProgress = !!a.is_in_progress || a.status === 'In progress';
            const attemptLabel = inProgress
                ? '<div class="small text-muted">In progress</div>'
                : (a.attempt_number ? `<div class="small text-muted">Attempt ${a.attempt_number} Score: ${escapeHtml(String(a.score_display ?? a.Score ?? '—'))} Average: ${formatScore(a.grade_percent)}%</div>` : '');
            return `
            <tr>
                <td>${escapeHtml(a.exam_title)}${attemptLabel}</td>
                <td>${escapeHtml(a.Subject_Name)}</td>
                <td>${formatDate(a.Submission_Date)}</td>
                <td>${inProgress ? '<span class="text-muted">—</span>' : formatAttemptScoreCell(a)}</td>
                <td>${inProgress ? '<span class="text-muted">In progress</span>' : `<span class="${a.status === 'Passed' ? 'text-success' : 'text-danger'}">${escapeHtml(a.status)}</span>`}</td>
            </tr>
        `;
        }).join('');

        const atRiskSubjects = data.at_risk_subjects || [];
        const atRiskSubjectsHtml = atRiskSubjects.length
            ? `
                <div class="table-responsive mb-4">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Subject</th>
                                <th>Average</th>
                                <th>Pass Rate</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${atRiskSubjects.map(subject => `
                                <tr>
                                    <td><strong>${escapeHtml(subject.Subject_Name)}</strong></td>
                                    <td><strong>${formatGrade(subject.avg_percentage)}%</strong></td>
                                    <td>${formatGrade(subject.pass_rate ?? 0)}%</td>
                                    <td>${statusBadge(subject.status)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `
            : '<p class="text-muted mb-4">No at-risk subjects — all completed subjects are on track.</p>';

        body.innerHTML = `
            <h6 class="mb-2">At Risk Subjects</h6>
            ${atRiskSubjectsHtml}
            <h6 class="mb-2">Exam History</h6>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead class="table-light">
                        <tr><th>Exam</th><th>Subject</th><th>Date</th><th>Score</th><th>Status</th></tr>
                    </thead>
                    <tbody>${attemptsHtml || '<tr><td colspan="5" class="text-muted">No attempts</td></tr>'}</tbody>
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
                <td>${escapeHtml(e.batch_names || '—')}</td>
                <td>${escapeHtml(e.section_names || '—')}</td>
                <td>${formatAvgScoreCell(e)}</td>
                <td>${e.pass_rate}%</td>
                <td><a href="${reportsResponsesLink(e.Exam_ID)}" class="btn btn-sm btn-add-user">View responses</a></td>
            </tr>
        `).join('');

        body.innerHTML = `
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Exam</th>
                            <th>Batch</th>
                            <th>Section</th>
                            <th>Avg score</th>
                            <th>Pass Rate</th>
                            <th></th>
                        </tr>
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
    if (currentTab === 'overview') {
        return;
    }

    let headers, rows, filename, reportType;

    if (currentTab === 'trainees') {
        headers = ['Standing', 'Rank', 'Name', 'Badge Number', 'Assigned', 'Taken', 'Missing Overdue', 'Average Grade %', 'Pass Rate', 'Status'];
        rows = traineeSummaryData.map(s => [
            s.standing,
            s.Personnel_Rank || '',
            s.Fullname,
            s.Badge_Number,
            s.exams_assigned,
            s.exams_taken,
            s.missing_overdue,
            s.avg_grade ?? s.avg_percentage,
            s.pass_rate,
            s.status
        ]);
        filename = buildExportFilename('trainee-rankings');
        reportType = 'trainee-rankings';
    } else if (currentTab === 'subjects') {
        headers = ['Subject', 'Course', 'Exams', 'Avg Grade %', 'Pass Rate'];
        const sortOrder = document.getElementById('subjectSortFilter')?.value || 'top_to_lowest';
        rows = sortSubjectSummaryData(subjectSummaryData, sortOrder).map(s => [
            s.Subject_Name, s.Course_Name, s.exams,
            s.avg_grade ?? s.avg_percentage, s.pass_rate
        ]);
        filename = buildExportFilename('subject-performance');
        reportType = 'subject-performance';
    } else {
        headers = ['Metric', 'Value'];
        rows = [
            ['Trainees Assigned', overviewMetrics.active_examinees ?? ''],
            ['Exams (non-draft)', overviewMetrics.exams_administered ?? ''],
            ['Trainees with Finished Attempts', overviewMetrics.students_with_results ?? ''],
            ['Exams with Finished Attempts', overviewMetrics.exams_with_results ?? ''],
            ['Finished Attempts', overviewMetrics.finished_submissions ?? ''],
            ['Overall Pass Rate (%)', overviewMetrics.pass_rate ?? ''],
            ['Average Grade (%)', overviewMetrics.avg_grade ?? overviewMetrics.avg_score ?? ''],
            ['At-Risk Count', overviewMetrics.at_risk_count ?? ''],
            ['At-Risk (%)', overviewMetrics.at_risk_percent ?? '']
        ];
        filename = buildExportFilename('reports-overview');
        reportType = 'overview';
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

function formatLocalDate(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function buildExportFilename(base) {
    return `${base}-${formatLocalDate(new Date())}.csv`;
}

function buildFilterNoteRow() {
    const parts = [];
    const course = selectedOptionText('filterCourse');
    const batch = selectedOptionText('filterBatch');
    const section = selectedOptionText('filterSection');
    const subject = selectedOptionText('filterSubject');
    if (course) parts.push(`Course: ${course}`);
    if (batch) parts.push(`Batch: ${batch}`);
    if (section) parts.push(`Section: ${section}`);
    if (subject) parts.push(`Subject: ${subject}`);
    return parts.length ? `"Filters: ${parts.join(' | ').replace(/"/g, '""')}"` : '';
}

function csvCell(v) {
    return `"${String(v ?? '').replace(/"/g, '""')}"`;
}

function statusBadge(status) {
    const map = {
        'On track': 'bg-success',
        'At risk': 'bg-danger',
        'No data': 'bg-secondary'
    };
    const label = status === 'On track' ? 'On Track' : (status === 'At risk' ? 'At Risk' : status);
    const cls = map[status] || 'bg-secondary';
    return `<span class="badge ${cls}">${escapeHtml(label)}</span>`;
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

function formatScore(value) {
    const num = parseFloat(value);
    if (Number.isNaN(num)) return '—';
    return Number.isInteger(num) ? String(num) : num.toFixed(1);
}

function formatGrade(value) {
    return formatScore(value);
}

function formatAvgScoreCell(row) {
    const grade = row.avg_grade ?? row.grade_percent ?? row.avg_percentage ?? row.raw_percent ?? row.Percentage;
    return `<strong>${formatGrade(grade ?? 0)}%</strong>`;
}

function formatAttemptScoreCell(row) {
    const grade = row.grade_percent;
    const scoreDisplay = row.score_display ?? row.Score;
    return `<strong>${escapeHtml(String(scoreDisplay))}</strong><br><small class="text-muted">${formatGrade(grade)}% grade</small>`;
}

window.viewTraineeDetail = viewTraineeDetail;
window.viewSubjectDetail = viewSubjectDetail;
