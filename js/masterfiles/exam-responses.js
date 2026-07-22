const API = '../../api/masterfiles/exam-responses.php';
const EXAM_ID = window.EXAM_RESPONSES_EXAM_ID;

let summaryData = null;
let rankingsData = [];
let notSubmittedData = [];
let itemAnalysisData = null;
let itemAnalysisLoaded = false;
let itemAnalysisFilterBound = false;
let detailModal = null;
let searchTimer = null;

document.addEventListener('DOMContentLoaded', () => {
    detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
    bindEvents();
    loadPage();
});

function bindEvents() {
    document.querySelectorAll('#responseTabs .nav-link').forEach(btn => {
        btn.addEventListener('click', () => switchTab(btn.dataset.tab));
    });

    document.getElementById('statusFilter')?.addEventListener('change', loadRankings);
    document.getElementById('searchInput')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadRankings, 300);
    });

    document.getElementById('exportCsvBtn')?.addEventListener('click', exportCsv);
    bindItemAnalysisFilters();
}

function bindItemAnalysisFilters() {
    if (itemAnalysisFilterBound) return;
    itemAnalysisFilterBound = true;

    document.getElementById('itemAnalysisSearch')?.addEventListener('input', () => {
        if (itemAnalysisData) {
            renderItemAnalysisList(getFilteredItemAnalysisItems());
        }
    });

    document.getElementById('itemAnalysisFilter')?.addEventListener('change', () => {
        if (itemAnalysisData) {
            renderItemAnalysisList(getFilteredItemAnalysisItems());
        }
    });
}

async function loadPage() {
    try {
        const [summaryRes, rankingsRes, notSubmittedRes] = await Promise.all([
            axios.get(`${API}?action=summary&exam_id=${EXAM_ID}`),
            axios.get(`${API}?action=rankings&exam_id=${EXAM_ID}`),
            axios.get(`${API}?action=not_submitted&exam_id=${EXAM_ID}`)
        ]);

        if (!summaryRes.data.success) {
            throw new Error(summaryRes.data.message || 'Failed to load summary');
        }

        summaryData = summaryRes.data.data;
        rankingsData = rankingsRes.data.success ? (rankingsRes.data.data || []) : [];
        notSubmittedData = notSubmittedRes.data.success ? (notSubmittedRes.data.data || []) : [];

        renderSummary(summaryData);
        renderRankings(rankingsData);
        renderNotSubmitted(notSubmittedData);

        document.getElementById('loadingBlock').style.display = 'none';
        document.getElementById('contentBlock').style.display = 'block';
        document.getElementById('exportCsvBtn').disabled = rankingsData.length === 0;
    } catch (err) {
        document.getElementById('loadingBlock').innerHTML = `
            <div class="alert alert-danger d-inline-block">
                ${escapeHtml(err.response?.data?.message || err.message || 'Failed to load exam responses')}
            </div>
        `;
    }
}

async function loadRankings() {
    const status = document.getElementById('statusFilter')?.value || 'all';
    const search = document.getElementById('searchInput')?.value.trim() || '';

    try {
        const res = await axios.get(`${API}?action=rankings&exam_id=${EXAM_ID}&status=${status}&search=${encodeURIComponent(search)}`);
        if (res.data.success) {
            rankingsData = res.data.data || [];
            renderRankings(rankingsData);
            document.getElementById('exportCsvBtn').disabled = rankingsData.length === 0;
        }
    } catch (err) {
        Swal.fire('Error', err.response?.data?.message || 'Failed to load rankings', 'error');
    }
}

function renderExamAssignmentBadges(assignments) {
    const items = assignments || [];
    const container = document.getElementById('examAssignments');
    if (!container) return;

    if (!items.length) {
        container.innerHTML = '<span class="text-muted small">No batches or sections assigned</span>';
        return;
    }

    container.innerHTML = items.map(a =>
        `<span class="badge bg-light text-dark border">${escapeHtml(a.label)}</span>`
    ).join('');
}

function renderSummary(data) {
    const exam = data.exam || {};
    const passingScore = data.passing_score ?? exam.Passing_Score ?? 50;

    document.getElementById('examTitle').textContent = exam.Title || 'Exam Responses';
    document.getElementById('examMeta').textContent =
        `${exam.Course_Name || ''} • ${exam.Subject_Name || ''} • ${exam.question_count || 0} items • Passing: ${formatScore(passingScore)}%`;

    renderExamAssignmentBadges(exam.Assignments || []);

    document.getElementById('rankingsCount').textContent = data.finished_count || 0;
    document.getElementById('notSubmittedCount').textContent = data.not_submitted_count || 0;

    document.getElementById('summaryStats').innerHTML = `
        <div class="col-6 col-md-3">
            <div class="stat-chip">
                <h4>${data.finished_count || 0} / ${data.assigned_count || 0}</h4>
                <p>Finished / Assigned</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-chip">
                <h4>${data.avg_grade || 0}%</h4>
                <p>Average Grade</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-chip">
                <h4>${data.pass_rate || 0}%</h4>
                <p>Pass Rate</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-chip">
                <h4>${data.highest_grade ?? '—'}${data.highest_grade != null ? '%' : ''}</h4>
                <p>Highest Grade</p>
            </div>
        </div>
    `;
}

function renderRankings(rows) {
    const tbody = document.getElementById('rankingsBody');
    const empty = document.getElementById('rankingsEmpty');

    if (!rows.length) {
        tbody.innerHTML = '';
        empty.style.display = 'block';
        return;
    }

    empty.style.display = 'none';
    tbody.innerHTML = rows.map(row => {
        const standing = row.standing ?? row.rank;
        const standingHtml = renderStandingBadge(standing);
        const remarksBadge = row.remarks === 'Passed' || row.status === 'Passed'
            ? '<span class="badge bg-success">Passed</span>'
            : '<span class="badge bg-danger">Failed</span>';

        return `
            <tr>
                <td>${escapeHtml(row.Personnel_Rank || '—')}</td>
                <td>
                    <div class="fw-medium">${escapeHtml(row.Fullname)}</div>
                    <small class="text-muted">${escapeHtml(row.Academic_Number || '')}</small>
                </td>
                <td><small>${escapeHtml(row.batch_names || '—')}</small></td>
                <td><small>${escapeHtml(row.section_names || '—')}</small></td>
                <td><strong>${escapeHtml(row.score_display ?? row.Score)}</strong></td>
                <td><strong>${formatGrade(row.grade_percent)}%</strong></td>
                <td>${standingHtml}</td>
                <td>${remarksBadge}</td>
                <td><small>${escapeHtml(row.time_taken || 'N/A')}</small></td>
                <td><small>${formatDateTime(row.Submission_Date)}</small></td>
                <td>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewDetail(${row.Result_ID})">
                        View
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function renderNotSubmitted(rows) {
    const tbody = document.getElementById('notSubmittedBody');
    const empty = document.getElementById('notSubmittedEmpty');

    if (!rows.length) {
        tbody.innerHTML = '';
        empty.style.display = 'block';
        return;
    }

    empty.style.display = 'none';
    tbody.innerHTML = rows.map(row => `
        <tr>
            <td>${escapeHtml(row.Fullname)}</td>
            <td>${escapeHtml(row.Academic_Number || '—')}</td>
            <td><small>${escapeHtml(row.batch_names || '—')}</small></td>
            <td><small>${escapeHtml(row.section_names || '—')}</small></td>
        </tr>
    `).join('');
}

function renderStandingBadge(standing) {
    if (standing === 1) return '<span class="rank-medal rank-1">1</span>';
    if (standing === 2) return '<span class="rank-medal rank-2">2</span>';
    if (standing === 3) return '<span class="rank-medal rank-3">3</span>';
    return `<span class="text-muted fw-semibold">${standing}</span>`;
}

function formatGrade(value) {
    const num = parseFloat(value);
    return Number.isFinite(num) ? num.toFixed(1) : '—';
}

function formatScore(value) {
    const num = parseFloat(value);
    return Number.isFinite(num) ? (Number.isInteger(num) ? String(num) : num.toFixed(1)) : '—';
}

function switchTab(tab) {
    document.querySelectorAll('#responseTabs .nav-link').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tab);
    });

    document.getElementById('rankingsPanel').style.display = tab === 'rankings' ? 'block' : 'none';
    document.getElementById('notSubmittedPanel').style.display = tab === 'not-submitted' ? 'block' : 'none';
    document.getElementById('itemAnalysisPanel').style.display = tab === 'item-analysis' ? 'block' : 'none';

    if (tab === 'item-analysis') {
        loadItemAnalysis();
    }
}

async function loadItemAnalysis() {
    const loading = document.getElementById('itemAnalysisLoading');
    const content = document.getElementById('itemAnalysisContent');
    const empty = document.getElementById('itemAnalysisEmpty');
    const notice = document.getElementById('itemAnalysisNotice');

    if (itemAnalysisLoaded && itemAnalysisData) {
        renderItemAnalysis(itemAnalysisData);
        return;
    }

    loading.style.display = 'block';
    content.style.display = 'none';
    empty.style.display = 'none';
    notice.style.display = 'none';

    try {
        const res = await axios.get(`${API}?action=item_analysis&exam_id=${EXAM_ID}`);
        if (!res.data.success) {
            throw new Error(res.data.message || 'Failed to load item analysis');
        }

        itemAnalysisData = res.data.data;
        itemAnalysisLoaded = true;
        renderItemAnalysis(itemAnalysisData);
    } catch (err) {
        loading.style.display = 'none';
        empty.style.display = 'block';
        empty.innerHTML = `
            <div class="alert alert-danger d-inline-block mb-0">
                ${escapeHtml(err.response?.data?.message || err.message || 'Failed to load item analysis')}
            </div>
        `;
    }
}

function renderItemAnalysis(data) {
    const loading = document.getElementById('itemAnalysisLoading');
    const content = document.getElementById('itemAnalysisContent');
    const empty = document.getElementById('itemAnalysisEmpty');
    const notice = document.getElementById('itemAnalysisNotice');
    const meta = document.getElementById('itemAnalysisMeta');

    loading.style.display = 'none';

    const items = data.items || [];
    if (!items.length || (data.total_examinees || 0) === 0) {
        content.style.display = 'none';
        notice.style.display = 'none';
        empty.style.display = 'block';
        empty.innerHTML = `
            <i class="bi bi-bar-chart-line" style="font-size:2rem;"></i>
            <p class="mt-2 mb-0">No finished submissions yet for item analysis.</p>
        `;
        return;
    }

    empty.style.display = 'none';
    content.style.display = 'block';

    if (data.not_submitted_count > 0) {
        notice.style.display = 'block';
        notice.className = 'alert alert-warning py-2 small mb-3';
        notice.textContent = `${data.not_submitted_count} examinee(s) have not submitted. Analysis is based on ${data.total_examinees} finished submission(s).`;
    } else if (data.all_completed) {
        notice.style.display = 'block';
        notice.className = 'alert alert-info py-2 small mb-3';
        notice.textContent = `All ${data.assigned_count} assigned examinee(s) have completed the exam.`;
    } else {
        notice.style.display = 'none';
    }

    meta.textContent = `${data.total_examinees} finished submission(s). Discrimination uses top and bottom 27% groups (${data.group_size} per group).`;

    renderItemAnalysisList(getFilteredItemAnalysisItems());
}

function getFilteredItemAnalysisItems() {
    const items = itemAnalysisData?.items || [];
    const query = (document.getElementById('itemAnalysisSearch')?.value || '').trim().toLowerCase();
    const filter = document.getElementById('itemAnalysisFilter')?.value || 'all';

    return items.filter(item => {
        if (query) {
            const haystack = [
                item.question_text,
                item.correct_answer,
                item.difficulty_interpretation,
                item.discrimination_interpretation,
                `q${item.question_number}`,
            ].join(' ').toLowerCase();
            if (!haystack.includes(query)) {
                return false;
            }
        }

        if (filter === 'review') {
            return parseFloat(item.discrimination_index) < 0;
        }
        if (filter === 'poor') {
            const d = parseFloat(item.discrimination_index);
            return Number.isFinite(d) && d >= 0 && d < 0.2;
        }
        if (filter === 'non-functioning') {
            return (item.distractors || []).some(d => !d.is_correct && (parseFloat(d.percentage) || 0) === 0);
        }

        return true;
    });
}

function renderItemAnalysisList(items) {
    const list = document.getElementById('itemAnalysisList');
    const countEl = document.getElementById('itemAnalysisCount');
    const total = itemAnalysisData?.items?.length || 0;

    if (countEl) {
        countEl.textContent = items.length === total
            ? `Showing ${total} question${total === 1 ? '' : 's'}`
            : `Showing ${items.length} of ${total} questions`;
    }

    if (!items.length) {
        list.innerHTML = `
            <div class="item-analysis-empty-filter">
                <i class="bi bi-search mb-2 d-block" style="font-size:1.5rem;"></i>
                No questions match your search or filter.
            </div>
        `;
        return;
    }

    list.innerHTML = items.map(item => renderItemAnalysisCard(item, itemAnalysisData.total_examinees)).join('');
}

function renderItemAnalysisChoiceBars(distractors) {
    return (distractors || []).map(choice => {
        const percentage = Math.max(0, Math.min(100, parseFloat(choice.percentage) || 0));
        const count = choice.count ?? 0;
        const isCorrect = !!choice.is_correct;
        const isEmpty = count === 0;
        const wideFill = percentage >= 18;

        const rowClass = isCorrect
            ? 'item-analysis-choice-row is-correct-answer'
            : 'item-analysis-choice-row';
        const trackClass = [
            'item-analysis-bar-track',
            isEmpty ? 'is-empty' : '',
            wideFill ? 'has-wide-fill' : '',
        ].filter(Boolean).join(' ');

        const fillClass = isCorrect
            ? 'item-analysis-bar-fill is-correct-answer'
            : 'item-analysis-bar-fill';
        const fillHtml = isEmpty
            ? ''
            : `<div class="${fillClass}" style="width:${percentage}%;"></div>`;

        const barLabel = formatBarSelectionLabel(count, percentage);

        const tags = [];
        if (isCorrect) {
            tags.push('<span class="item-analysis-tag is-correct-answer">Correct Answer</span>');
        } else {
            const distractorLabel = interpretDistractorLabel(percentage);
            if (distractorLabel) {
                tags.push(`<span class="item-analysis-tag">${escapeHtml(distractorLabel)}</span>`);
            }
        }

        return `
            <div class="${rowClass}">
                <div class="item-analysis-choice-label">
                    <span><span class="choice-letter">${escapeHtml(choice.choice_label)}.</span>${escapeHtml(choice.choice_text)}</span>
                    ${tags.join('')}
                </div>
                <div class="item-analysis-bar-row">
                    <div class="${trackClass}">
                        ${fillHtml}
                        <span class="item-analysis-bar-label">${escapeHtml(barLabel)}</span>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function formatBarSelectionLabel(count, percentage) {
    const examineeWord = count === 1 ? 'examinee' : 'examinees';
    return `Selected by ${count} ${examineeWord} | ${formatPercent(percentage)}`;
}

function interpretDistractorLabel(percentage) {
    if (percentage === 0) {
        return 'Non-functioning Distractor';
    }
    if (percentage < 5) {
        return 'Weak Distractor';
    }
    return 'Functional Distractor';
}

function renderItemAnalysisCard(item, totalExaminees) {
    return `
        <article class="item-analysis-card" id="itemAnalysisQ${item.question_id}">
            <div class="item-analysis-card-header">
                <span class="item-analysis-qnum-badge">Question ${item.question_number}</span>
                <p class="item-analysis-question">${escapeHtml(item.question_text)}</p>
            </div>
            <div class="item-analysis-metrics">
                <div class="item-analysis-metric">
                    <span class="item-analysis-metric-label">Responses correct</span>
                    <span class="item-analysis-metric-value">${item.number_correct} of ${totalExaminees}</span>
                    <span class="item-analysis-metric-note">${item.number_wrong} incorrect or unanswered</span>
                </div>
                <div class="item-analysis-metric">
                    <span class="item-analysis-metric-label">Difficulty index</span>
                    <span class="item-analysis-metric-value">${formatIndex(item.difficulty_index)}</span>
                    <span class="item-analysis-metric-note">${escapeHtml(item.difficulty_interpretation || '—')}</span>
                </div>
                <div class="item-analysis-metric">
                    <span class="item-analysis-metric-label">Discrimination (D)</span>
                    <span class="item-analysis-metric-value">${formatIndex(item.discrimination_index)}</span>
                    <span class="item-analysis-metric-note">${escapeHtml(item.discrimination_interpretation || '—')}</span>
                </div>
            </div>
            <div class="item-analysis-section-label">Answer choices</div>
            <div class="item-analysis-bars">
                ${renderItemAnalysisChoiceBars(item.distractors) || '<p class="text-muted small mb-0">No choices found.</p>'}
            </div>
        </article>
    `;
}

function formatIndex(value) {
    const num = parseFloat(value);
    return Number.isFinite(num) ? num.toFixed(2) : '—';
}

function formatPercent(value) {
    const num = parseFloat(value);
    return Number.isFinite(num) ? `${num.toFixed(1)}%` : '—';
}

function renderInterpretationBadge(label, type) {
    if (!label) return '';
    const normalized = String(label).toLowerCase();
    let cls = 'bg-secondary';

    if (type === 'difficulty') {
        if (normalized.includes('very easy')) cls = 'bg-success';
        else if (normalized.includes('easy')) cls = 'bg-info text-dark';
        else if (normalized.includes('moderate')) cls = 'bg-primary';
        else if (normalized.includes('very difficult')) cls = 'bg-danger';
        else if (normalized.includes('difficult')) cls = 'bg-warning text-dark';
    } else {
        if (normalized.includes('excellent')) cls = 'bg-success';
        else if (normalized.includes('good')) cls = 'bg-primary';
        else if (normalized.includes('fair')) cls = 'bg-warning text-dark';
        else if (normalized.includes('poor')) cls = 'bg-danger';
        else if (normalized.includes('review')) cls = 'bg-dark';
    }

    return `<span class="badge ${cls} interpretation-badge ms-1">${escapeHtml(label)}</span>`;
}

async function viewDetail(resultId) {
    const body = document.getElementById('detailModalBody');
    body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    document.getElementById('detailModalTitle').textContent = 'Student Response';
    document.getElementById('detailModalSub').textContent = '';
    detailModal.show();

    try {
        const res = await axios.get(`${API}?action=detail&exam_id=${EXAM_ID}&result_id=${resultId}`);
        if (!res.data.success) {
            throw new Error(res.data.message || 'Failed to load detail');
        }
        renderDetail(res.data.data);
    } catch (err) {
        body.innerHTML = `<div class="alert alert-danger">${escapeHtml(err.response?.data?.message || err.message)}</div>`;
    }
}

function renderDetail(data) {
    const rankLabel = data.Personnel_Rank ? `${data.Personnel_Rank} ` : '';
    document.getElementById('detailModalTitle').textContent = rankLabel + (data.Fullname || 'Student Response');
    document.getElementById('detailModalSub').textContent =
        `${data.remarks || data.status} • ${data.score_display} (${formatScore(data.raw_percent ?? data.Percentage)}% raw) • Grade ${formatGrade(data.grade_percent)}% • ${data.time_taken}`;

    const questionsHtml = (data.questions || []).map((q, index) => {
        const isCorrect = String(q.user_is_correct) === '1';
        const isAnswered = q.Answer_ID != null || q.user_answer_id != null || (q.user_answer_text && String(q.user_answer_text).trim() !== '');
        const cardClass = isAnswered ? (isCorrect ? 'correct' : 'incorrect') : '';

        const choicesHtml = (q.choices || []).map(choice => {
            const isUser = String(choice.Choice_ID) === String(q.user_answer_id);
            const isCorrectChoice = String(choice.Is_Correct) === '1';
            let cls = 'choice-item';
            if (isUser && isCorrectChoice) cls += ' user-answer correct-answer';
            else if (isUser) cls += ' user-answer incorrect';
            else if (isCorrectChoice) cls += ' correct-answer';

            return `<div class="${cls}">${escapeHtml(choice.Choice_Text)}</div>`;
        }).join('');

        const badge = isAnswered
            ? (isCorrect ? '<span class="badge bg-success">Correct</span>' : '<span class="badge bg-danger">Incorrect</span>')
            : '<span class="badge bg-secondary">Not answered</span>';

        return `
            <div class="question-card ${cardClass}">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <strong>Q${index + 1}. ${escapeHtml(q.Question_Text)}</strong>
                    ${badge}
                </div>
                ${choicesHtml}
            </div>
        `;
    }).join('');

    document.getElementById('detailModalBody').innerHTML = `
        <div class="mb-3 p-3 rounded bg-light">
            <div class="row g-2 small">
                <div class="col-md-4"><strong>Submitted:</strong> ${formatDateTime(data.Submission_Date)}</div>
                <div class="col-md-4"><strong>Passing score:</strong> ${formatScore(data.passing_score ?? data.Passing_Score)}%</div>
                <div class="col-md-4"><strong>Academic No.:</strong> ${escapeHtml(data.Academic_Number || '—')}</div>
                <div class="col-md-4"><strong>Batch:</strong> ${escapeHtml(data.batch_names || '—')}</div>
                <div class="col-md-4"><strong>Section:</strong> ${escapeHtml(data.section_names || '—')}</div>
            </div>
        </div>
        ${questionsHtml || '<p class="text-muted">No questions found.</p>'}
    `;
}

function exportCsv() {
    if (!rankingsData.length) return;

    const headers = [
        'Personnel Rank', 'Name', 'Batch', 'Section', 'Score', 'Grade (%)', 'Standing', 'Remarks'
    ];
    const lines = [headers.join(',')];

    rankingsData.forEach(row => {
        lines.push([
            csvCell(row.Personnel_Rank),
            csvCell(row.Fullname),
            csvCell(row.batch_names),
            csvCell(row.section_names),
            csvCell(row.score_display ?? row.Score),
            formatGrade(row.grade_percent),
            row.standing ?? row.rank,
            csvCell(row.remarks || row.status)
        ].join(','));
    });

    const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    const title = summaryData?.exam?.Title || 'exam';
    link.href = url;
    link.download = `${sanitizeFilename(title)}-rankings.csv`;
    link.click();
    URL.revokeObjectURL(url);

    axios.post(`${API}?action=log_export&exam_id=${EXAM_ID}`).catch(() => {});
}

function csvCell(value) {
    const str = String(value ?? '').replace(/"/g, '""');
    return `"${str}"`;
}

function sanitizeFilename(name) {
    return name.replace(/[^\w\-]+/g, '_').substring(0, 60);
}

function formatDateTime(dateStr) {
    if (!dateStr) return 'N/A';
    return new Date(dateStr).toLocaleString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

window.viewDetail = viewDetail;
