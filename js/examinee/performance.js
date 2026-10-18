// Examinee performance

const PERFORMANCE_API = '../../api/examinee/performance.php';
const SUBJECT_HIGHLIGHTS_INITIAL = 5;

let trendChart = null;
let subjectDetailModal = null;
let overviewData = null;

document.addEventListener('DOMContentLoaded', () => {
    subjectDetailModal = new bootstrap.Modal(document.getElementById('subjectDetailModal'));
    loadPerformance();
});

async function loadPerformance() {
    document.getElementById('loadingMessage').style.display = 'block';
    document.getElementById('performanceContent').style.display = 'none';

    try {
        const response = await axios.get(`${PERFORMANCE_API}?action=overview&date_range=all`);
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

    renderSubjectHighlights('strongSubjectsList', data.strong_subjects || [], 'No on track subjects yet.');
    renderSubjectHighlights('weakSubjectsList', data.weak_subjects || [], 'No at risk subjects — great job!');
    renderTrendChart(data.trend || []);
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

    const labels = trend.map(t => formatDate(t.date));
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
                tension: 0.4,
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

function renderSubjectHighlights(containerId, subjects, emptyText) {
    const container = document.getElementById(containerId);

    if (!subjects.length) {
        container.innerHTML = `<p class="text-muted mb-0">${escapeHtml(emptyText)}</p>`;
        return;
    }

    const hiddenCount = Math.max(0, subjects.length - SUBJECT_HIGHLIGHTS_INITIAL);
    const itemsHtml = subjects.map((s, index) => `
        <div class="subject-highlight-item${index >= SUBJECT_HIGHLIGHTS_INITIAL ? ' subject-highlight-hidden d-none' : ''}" onclick="openSubjectDetail(${s.Subject_ID})">
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

    const showMoreBtn = hiddenCount > 0
        ? `<button type="button" class="btn btn-link btn-sm p-0 subject-show-more-btn">Show more (${hiddenCount})</button>`
        : '';

    container.innerHTML = `<div class="subject-highlights-list">${itemsHtml}</div>${showMoreBtn}`;

    const btn = container.querySelector('.subject-show-more-btn');
    if (btn) {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const hidden = container.querySelectorAll('.subject-highlight-hidden');
            const expanded = btn.dataset.expanded === 'true';
            if (expanded) {
                hidden.forEach(el => el.classList.add('d-none'));
                btn.textContent = `Show more (${hidden.length})`;
                btn.dataset.expanded = 'false';
            } else {
                hidden.forEach(el => el.classList.remove('d-none'));
                btn.textContent = 'Show less';
                btn.dataset.expanded = 'true';
            }
        });
    }
}

async function openSubjectDetail(subjectId) {
    const modalBody = document.getElementById('subjectDetailBody');
    modalBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
    subjectDetailModal.show();

    try {
        const response = await axios.get(`${PERFORMANCE_API}?action=subject_detail&subject_id=${subjectId}&date_range=all`);
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

    document.getElementById('subjectDetailModalLabel').textContent = subject.Subject_Name || 'Subject Performance';

    let summaryHtml = '';
    if (summary) {
        summaryHtml = `
            <div class="d-flex align-items-center gap-2 mb-3">
                ${statusBadge(summary.status)}
                <span class="text-muted small">${escapeHtml(subject.Course_Name || '')}</span>
            </div>
            <div class="subject-detail-summary">
                <div class="subject-detail-stat"><div class="value">${summary.avg_percentage}%</div><div class="label">Average</div></div>
                <div class="subject-detail-stat"><div class="value">${summary.pass_rate}%</div><div class="label">Pass Rate</div></div>
                <div class="subject-detail-stat"><div class="value">${summary.exams_taken}</div><div class="label">Exams</div></div>
            </div>
        `;
    } else {
        summaryHtml = '<div class="alert alert-info">No completed exams in this subject for the selected period.</div>';
    }

    let attemptsHtml = '';
    if (attempts.length) {
        attemptsHtml = `
            <h6 class="mb-2">Exam History</h6>
            ${attempts.map(a => `
                <div class="exam-attempt-row">
                    <div>
                        <strong>${escapeHtml(a.exam_title)}</strong>
                        <div class="small text-muted">${a.attempt_number ? `Attempt ${a.attempt_number} · ` : ''}${formatDateTime(a.Submission_Date)} · ${escapeHtml(a.time_taken)}</div>
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

    document.getElementById('subjectDetailBody').innerHTML = summaryHtml + attemptsHtml;
}

function statusBadge(status) {
    const map = {
        'On track': 'on-track',
        'At risk': 'at-risk',
        'No data': 'no-data'
    };
    const cls = map[status] || 'no-data';
    return `<span class="perf-badge ${cls}">${escapeHtml(status || 'Unknown')}</span>`;
}

function showFatalError(message) {
    document.getElementById('loadingMessage').style.display = 'none';
    Swal.fire({
        title: 'Error',
        text: message,
        icon: 'error',
        confirmButtonText: 'Go to Exams'
    }).then(() => {
        window.location.href = 'dashboard.php';
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

function formatDate(dateStr) {
    const date = parseExamDateTime(dateStr);
    if (!date) return '—';
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatDateTime(dateStr) {
    const date = parseExamDateTime(dateStr);
    if (!date) return '—';
    return date.toLocaleString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
}

window.openSubjectDetail = openSubjectDetail;
