const REPORTS_API = '../../api/masterfiles/reports.php';

let performanceChart, passFailChart, subjectsChart, participationChart;
let participationData = [];
let detailedResultsData = [];

document.addEventListener('DOMContentLoaded', () => {
    initializeCharts();
    loadCourses();
    bindEvents();
    loadReports();
});

function bindEvents() {
    document.getElementById('filterCourse')?.addEventListener('change', function () {
        loadSubjects(this.value);
    });
}

function getFilterParams(extra = {}) {
    const params = new URLSearchParams({
        date_range: document.getElementById('dateRange').value,
        ...(document.getElementById('filterCourse').value && { course_id: document.getElementById('filterCourse').value }),
        ...(document.getElementById('filterSubject').value && { subject_id: document.getElementById('filterSubject').value }),
        ...extra
    });
    return params;
}

function initializeCharts() {
    const chartDefaults = { responsive: true, maintainAspectRatio: false };

    performanceChart = new Chart(document.getElementById('performanceChart'), {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Average Score (%)',
                data: [],
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            ...chartDefaults,
            scales: { y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } } }
        }
    });

    passFailChart = new Chart(document.getElementById('passFailChart'), {
        type: 'pie',
        data: {
            labels: ['Passed', 'Failed'],
            datasets: [{ data: [0, 0], backgroundColor: ['#10b981', '#ef4444'] }]
        },
        options: { ...chartDefaults, plugins: { legend: { position: 'bottom' } } }
    });

    subjectsChart = new Chart(document.getElementById('subjectsChart'), {
        type: 'bar',
        data: {
            labels: [],
            datasets: [{ label: 'Avg %', data: [], backgroundColor: '#a855f7' }]
        },
        options: {
            ...chartDefaults,
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } } }
        }
    });

    participationChart = new Chart(document.getElementById('participationChart'), {
        type: 'bar',
        data: {
            labels: [],
            datasets: [{ label: 'Examinees', data: [], backgroundColor: '#f59e0b' }]
        },
        options: {
            ...chartDefaults,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
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
    await loadSubjects();
}

async function loadSubjects(courseId = '') {
    const course = courseId || document.getElementById('filterCourse').value;
    const url = course
        ? `../../api/masterfiles/subjects.php?action=list&course_id=${course}`
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

async function loadReports() {
    const params = getFilterParams();
    try {
        await Promise.all([
            loadMetrics(params),
            loadPerformanceTrend(params),
            loadPassFail(params),
            loadTopSubjects(params),
            loadParticipation(params),
            loadDetailedResults(params)
        ]);
    } catch (error) {
        console.error('Failed to load reports:', error);
        Swal.fire('Error', 'Failed to load reports data', 'error');
    }
}

async function loadMetrics(params) {
    const res = await axios.get(`${REPORTS_API}?action=metrics&${params}`);
    const d = res.data.data;
    const completionRate = d.active_examinees > 0 && d.exams_administered > 0
        ? Math.min(100, Math.round((d.finished_submissions / (d.active_examinees * d.exams_administered)) * 1000) / 10)
        : 0;

    document.getElementById('totalExaminees').textContent = d.active_examinees;
    document.getElementById('totalExams').textContent = d.exams_administered;
    document.getElementById('completionRate').textContent = completionRate + '%';
    document.getElementById('avgScore').textContent = d.avg_score + '%';
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

async function loadParticipation(params) {
    const res = await axios.get(`${REPORTS_API}?action=participation&${params}`);
    participationData = res.data.data || [];
    participationChart.data.labels = participationData.map(i => i.exam_title);
    participationChart.data.datasets[0].data = participationData.map(i => parseInt(i.participant_count));
    participationChart.update();
}

async function loadDetailedResults(params) {
    const query = params || getFilterParams({ page: 1 });
    const res = await axios.get(`${REPORTS_API}?action=detailed_results&${query}`);
    const data = res.data.data;
    const rows = data.rows || [];
    detailedResultsData = rows;

    const tbody = document.getElementById('resultsTableBody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No results found</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map(row => {
        const statusClass = row.status === 'Passed' ? 'text-success' : 'text-danger';
        return `
            <tr>
                <td>${escapeHtml(row.examinee_name)}</td>
                <td><a href="../masterfiles/exam-responses.php?exam_id=${row.Exam_ID}" class="text-decoration-none">${escapeHtml(row.exam_title)}</a></td>
                <td>${escapeHtml(row.Subject_Name)}</td>
                <td>${formatDate(row.Submission_Date)}</td>
                <td>${row.Score}</td>
                <td>${row.Percentage}%</td>
                <td><span class="${statusClass} fw-bold">${escapeHtml(row.status)}</span></td>
            </tr>
        `;
    }).join('');
}

function exportReport() {
    if (!detailedResultsData.length) {
        Swal.fire('No data', 'There is no report data to export for the current filters.', 'info');
        return;
    }

    const headers = ['Examinee', 'Exam', 'Subject', 'Date', 'Score', 'Percentage', 'Status'];
    const rows = detailedResultsData.map(row => [
        row.examinee_name,
        row.exam_title,
        row.Subject_Name,
        formatDate(row.Submission_Date),
        row.Score,
        row.Percentage + '%',
        row.status
    ]);

    const csv = [headers, ...rows]
        .map(row => row.map(cell => `"${String(cell ?? '').replace(/"/g, '""')}"`).join(','))
        .join('\n');

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'ccmd-exam-results.csv';
    link.click();
    URL.revokeObjectURL(link.href);
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

window.loadReports = loadReports;
window.exportReport = exportReport;
