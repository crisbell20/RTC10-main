/**
 * Examinee Performance Page
 */

const PERFORMANCE_API = '../../api/examinee/performance.php';

let trendChart = null;
let subjectTrendChart = null;
let subjectDetailModal = null;
let currentDateRange = 'all';
let overviewData = null;

document.addEventListener('DOMContentLoaded', () => {
    subjectDetailModal = new bootstrap.Modal(document.getElementById('subjectDetailModal'));
    document.getElementById('dateRangeFilter').addEventListener('change', (e) => {
        currentDateRange = e.target.value;
        loadPerformance();
    });
    loadPerformance();
});

async function loadPerformance() {
    document.getElementById('loadingMessage').style.display = 'block';
    document.getElementById('performanceContent').style.display = 'none';

    try {
        const response = await axios.get(`${PERFORMANCE_API}?action=overview&date_range=${currentDateRange}`);
        if (response.data.success) {
            overviewData = response.data.data;
            renderOverview(overviewData);
        } else {
            showFatalError(response.data.message || 'Failed to load performance data');
        }
    } catch (error) {
        console.error('Performance load error:', error);
        const msg = error.response?.data?.message || 'Failed to load performance data. Please try again.';
        showFatalError(msg);
    }
}

function renderOverview(data) {
    document.getElementById('loadingMessage').style.display = 'none';
    document.getElementById('performanceContent').style.display = 'block';

    const summary = data.summary || {};
    const hasData = (summary.exams_taken || 0) > 0;

    document.getElementById('emptyState').classList.toggle('d-none', hasData);
    document.getElementById('performanceBody').classList.toggle('d-none', !hasData);

    if (!hasData) {
        return;
    }

    renderStatusBanner(summary, data.weak_subjects || []);
    renderSubjectHighlights('strongSubjectsList', data.strong_subjects || [], 'No strong subjects identified yet.');
    renderSubjectHighlights('weakSubjectsList', data.weak_subjects || [], 'No weak subjects — great job!');
    renderTrendChart(data.trend || []);
    renderRecommendations(data.recommendations || []);
}

function renderStatusBanner(summary, weakSubjects) {
    const status = summary.status || 'No data';
    const examsTaken = summary.exams_taken || 0;
    const classMap = {
        'On track': 'on-track',
        'Needs review': 'needs-review',
        'At risk': 'at-risk',
        'No data': 'no-data'
    };
    const iconMap = {
        'On track': 'bi-check-circle-fill',
        'Needs review': 'bi-exclamation-circle-fill',
        'At risk': 'bi-x-octagon-fill',
        'No data': 'bi-info-circle-fill'
    };

    let message = '';
    if (status === 'On track') {
        message = `You are on track overall across ${examsTaken} completed exam${examsTaken !== 1 ? 's' : ''}. Keep maintaining your study habits.`;
    } else if (status === 'Needs review') {
        const count = weakSubjects.length;
        message = count > 0
            ? `${examsTaken} exam${examsTaken !== 1 ? 's' : ''} completed. ${count} subject${count > 1 ? 's' : ''} need${count === 1 ? 's' : ''} attention.`
            : `${examsTaken} exam${examsTaken !== 1 ? 's' : ''} completed. Focus on improving your pass rate.`;
    } else if (status === 'At risk') {
        message = `${examsTaken} exam${examsTaken !== 1 ? 's' : ''} completed. Prioritize study in your weakest subjects immediately.`;
    } else {
        message = 'Complete exams to receive a performance status.';
    }

    document.getElementById('statusBanner').className = `perf-status-banner ${classMap[status] || 'no-data'}`;
    document.getElementById('statusBanner').innerHTML = `
        <i class="bi ${iconMap[status] || 'bi-info-circle-fill'}"></i>
        <div>
            <strong>${escapeHtml(status)}</strong>
            <div>${escapeHtml(message)}</div>
        </div>
    `;
}

function renderTrendChart(trend) {
    const canvas = document.getElementById('trendChart');
    const emptyMsg = document.getElementById('trendEmpty');

    if (!trend.length) {
        canvas.classList.add('d-none');
        emptyMsg.classList.remove('d-none');
        if (trendChart) {
            trendChart.destroy();
            trendChart = null;
        }
        return;
    }

    canvas.classList.remove('d-none');
    emptyMsg.classList.add('d-none');

    const labels = trend.map((t, index) => `Exam ${index + 1}`);
    const scores = trend.map(t => parseFloat(t.score));

    if (trendChart) {
        trendChart.destroy();
    }

    trendChart = new Chart(canvas, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Score (%)',
                data: scores,
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                fill: true,
                tension: 0,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: { callback: (v) => v + '%' }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: (items) => {
                            const point = trend[items[0].dataIndex];
                            return point?.exam_title || items[0].label;
                        },
                        label: (ctx) => {
                            const point = trend[ctx.dataIndex];
                            const scoreLabel = `${ctx.parsed.y}%`;
                            if (point?.subject_name) {
                                return `${scoreLabel} · ${point.subject_name}`;
                            }
                            return scoreLabel;
                        },
                        afterLabel: (ctx) => {
                            const point = trend[ctx.dataIndex];
                            return point?.date ? formatDateTime(point.date) : '';
                        }
                    }
                }
            }
        }
    });
}

function renderRecommendations(recommendations) {
    const container = document.getElementById('recommendationsList');

    if (!recommendations.length) {
        container.innerHTML = '<p class="text-muted mb-0">No specific recommendations yet. Keep taking exams to get personalized study guidance.</p>';
        return;
    }

    container.innerHTML = recommendations.map(rec => {
        const priorityClass = rec.priority >= 80 ? 'priority-high' : rec.priority >= 40 ? 'priority-medium' : 'priority-low';
        const actionBtn = rec.action === 'take_exam'
            ? `<a href="../examinee/dashboard.php" class="btn btn-link btn-sm p-0 rec-action">View exams →</a>`
            : `<button type="button" class="btn btn-link btn-sm p-0 rec-action" onclick="openSubjectDetail(${rec.subject_id})">View subject →</button>`;

        return `
            <div class="recommendation-item ${priorityClass}">
                <div>${escapeHtml(rec.message)}</div>
                ${rec.subject_id ? actionBtn : ''}
            </div>
        `;
    }).join('');
}

function renderSubjectHighlights(containerId, subjects, emptyText) {
    const container = document.getElementById(containerId);

    if (!subjects.length) {
        container.innerHTML = `<p class="text-muted mb-0">${escapeHtml(emptyText)}</p>`;
        return;
    }

    container.innerHTML = subjects.map(s => `
        <div class="subject-highlight-item" onclick="openSubjectDetail(${s.Subject_ID})">
            <div>
                <strong>${escapeHtml(s.Subject_Name)}</strong>
                <div class="small text-muted">${escapeHtml(s.Course_Name || '')}</div>
            </div>
            <div class="text-end">
                <div class="fw-bold">${s.avg_percentage}%</div>
                ${statusBadge(s.status)}
            </div>
        </div>
    `).join('');
}

async function openSubjectDetail(subjectId) {
    const modalBody = document.getElementById('subjectDetailBody');
    modalBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
    subjectDetailModal.show();

    try {
        const response = await axios.get(`${PERFORMANCE_API}?action=subject_detail&subject_id=${subjectId}&date_range=${currentDateRange}`);
        if (response.data.success) {
            renderSubjectDetail(response.data.data);
        } else {
            modalBody.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(response.data.message || 'Failed to load subject details')}</div>`;
        }
    } catch (error) {
        const msg = error.response?.data?.message || 'Failed to load subject details';
        modalBody.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(msg)}</div>`;
    }
}

function renderSubjectDetail(data) {
    const subject = data.subject || {};
    const summary = data.summary;
    const attempts = data.attempts || [];
    const suggestions = data.suggestions || [];

    document.getElementById('subjectDetailModalLabel').textContent = subject.Subject_Name || 'Subject Performance';

    let summaryHtml = '';
    if (summary) {
        summaryHtml = `
            <div class="d-flex align-items-center gap-2 mb-3">
                ${statusBadge(summary.status)}
                <span class="text-muted small">${escapeHtml(subject.Course_Name || '')}</span>
            </div>
            <div class="subject-detail-summary">
                <div class="subject-detail-stat"><div class="value">${summary.avg_percentage}%</div><div class="label">Avg Score</div></div>
                <div class="subject-detail-stat"><div class="value">${summary.pass_rate}%</div><div class="label">Pass Rate</div></div>
                <div class="subject-detail-stat"><div class="value">${summary.exams_taken}</div><div class="label">Exams</div></div>
                <div class="subject-detail-stat"><div class="value">${summary.highest}%</div><div class="label">Highest</div></div>
                <div class="subject-detail-stat"><div class="value">${summary.lowest}%</div><div class="label">Lowest</div></div>
                <div class="subject-detail-stat"><div class="value">${trendLabel(summary.trend)}</div><div class="label">Trend</div></div>
            </div>
        `;
    } else {
        summaryHtml = '<div class="alert alert-info">No completed exams in this subject for the selected period.</div>';
    }

    let chartHtml = '';
    if ((data.trend || []).length >= 2) {
        chartHtml = `
            <h6 class="mb-2">Score Progression</h6>
            <div class="subject-chart-container"><canvas id="subjectTrendChart"></canvas></div>
        `;
    }

    let attemptsHtml = '';
    if (attempts.length) {
        attemptsHtml = `
            <h6 class="mb-2">Exam History</h6>
            ${attempts.map(a => `
                <div class="exam-attempt-row">
                    <div>
                        <strong>${escapeHtml(a.exam_title)}</strong>
                        <div class="small text-muted">${formatDateTime(a.Submission_Date)} · ${escapeHtml(a.time_taken)}</div>
                    </div>
                    <div class="text-end">
                        <span class="badge ${a.status === 'Passed' ? 'bg-success' : 'bg-danger'} me-2">${escapeHtml(a.status)}</span>
                        <strong>${parseFloat(a.Percentage).toFixed(1)}%</strong>
                        <div class="small text-muted">${a.Score}/${a.total_questions}</div>
                        <a href="result.php?result_id=${a.Result_ID}" class="btn btn-link btn-sm p-0" onclick="event.stopPropagation()">View details</a>
                    </div>
                </div>
            `).join('')}
        `;
    } else {
        attemptsHtml = '<p class="text-muted">No exam attempts recorded for this subject.</p>';
    }

    let suggestionsHtml = '';
    if (suggestions.length) {
        suggestionsHtml = `
            <h6 class="mt-3 mb-2"><i class="bi bi-lightbulb me-1"></i>Study Suggestions</h6>
            <ul class="suggestion-list">
                ${suggestions.map(s => `<li>${escapeHtml(s)}</li>`).join('')}
            </ul>
        `;
    }

    document.getElementById('subjectDetailBody').innerHTML = summaryHtml + chartHtml + attemptsHtml + suggestionsHtml;

    if ((data.trend || []).length >= 2) {
        renderSubjectTrendChart(data.trend);
    }
}

function renderSubjectTrendChart(trend) {
    const canvas = document.getElementById('subjectTrendChart');
    if (!canvas) return;

    if (subjectTrendChart) {
        subjectTrendChart.destroy();
    }

    subjectTrendChart = new Chart(canvas, {
        type: 'line',
        data: {
            labels: trend.map((t, index) => `Exam ${index + 1}`),
            datasets: [{
                label: 'Score (%)',
                data: trend.map(t => parseFloat(t.score)),
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                fill: true,
                tension: 0,
                pointRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, max: 100, ticks: { callback: (v) => v + '%' } }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: (items) => {
                            const point = trend[items[0].dataIndex];
                            return point?.exam_title || items[0].label;
                        },
                        label: (ctx) => `${ctx.parsed.y}%`,
                        afterLabel: (ctx) => {
                            const point = trend[ctx.dataIndex];
                            return point?.date ? formatDateTime(point.date) : '';
                        }
                    }
                }
            }
        }
    });
}

function statusBadge(status) {
    const map = {
        'Strong': 'strong',
        'Improving': 'improving',
        'On track': 'on-track',
        'Needs focus': 'needs-focus',
        'At risk': 'at-risk',
        'No data': 'no-data'
    };
    const cls = map[status] || 'no-data';
    return `<span class="perf-badge ${cls}">${escapeHtml(status || 'Unknown')}</span>`;
}

function trendLabel(trend) {
    if (trend === 'improving') return '↑ Up';
    if (trend === 'declining') return '↓ Down';
    return '— Stable';
}

function showFatalError(message) {
    document.getElementById('loadingMessage').style.display = 'none';
    Swal.fire({
        title: 'Error',
        text: message,
        icon: 'error',
        confirmButtonText: 'Go to Exams'
    }).then(() => {
        window.location.href = '../examinee/dashboard.php';
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    const date = new Date(dateStr);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatDateTime(dateStr) {
    if (!dateStr) return '—';
    const date = new Date(dateStr);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
}

window.openSubjectDetail = openSubjectDetail;
