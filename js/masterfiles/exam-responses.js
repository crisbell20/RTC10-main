const API = '../../api/masterfiles/exam-responses.php';
const EXAM_ID = window.EXAM_RESPONSES_EXAM_ID;
const CAN_GRANT_RETAKE = window.EXAM_RESPONSES_CAN_RETAKE === true;

let summaryData = null;
let rankingsData = [];
let notSubmittedData = [];
let itemAnalysisData = null;
let itemAnalysisLoaded = false;
let itemAnalysisFilterBound = false;
let detailModal = null;
let searchTimer = null;
let assignmentFiltersEnabled = false;
let assignmentFilterState = null;
let activeResponseTab = 'rankings';
let retakeInFlight = false;

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
}

async function loadPage() {
    try {
        const summaryRes = await axios.get(`${API}?action=summary&exam_id=${EXAM_ID}`);

        if (!summaryRes.data.success) {
            throw new Error(summaryRes.data.message || 'Failed to load summary');
        }

        summaryData = summaryRes.data.data;
        renderSummary(summaryData);
        setupAssignmentFilters(summaryData.exam?.Assignments || []);

        await loadAssignmentFilteredData();

        document.getElementById('loadingBlock').style.display = 'none';
        document.getElementById('contentBlock').style.display = 'block';
        updateExportButtonVisibility();
    } catch (err) {
        document.getElementById('loadingBlock').innerHTML = `
            <div class="alert alert-danger d-inline-block">
                ${escapeHtml(err.response?.data?.message || err.message || 'Failed to load exam responses')}
            </div>
        `;
    }
}

async function loadAssignmentFilteredData() {
    const filterQuery = getAssignmentFilterQuery();

    try {
        const [rankingsRes, notSubmittedRes] = await Promise.all([
            axios.get(`${API}?action=rankings&exam_id=${EXAM_ID}${buildRankingsQuery(filterQuery)}`),
            axios.get(`${API}?action=not_submitted&exam_id=${EXAM_ID}${filterQuery}`)
        ]);

        if (rankingsRes.data.success) {
            rankingsData = rankingsRes.data.data || [];
            renderRankings(rankingsData);
            updateExportButtonVisibility();
        }

        if (notSubmittedRes.data.success) {
            notSubmittedData = notSubmittedRes.data.data || [];
            renderNotSubmitted(notSubmittedData);
            document.getElementById('notSubmittedCount').textContent = notSubmittedData.length;
        }
    } catch (err) {
        Swal.fire('Error', err.response?.data?.message || 'Failed to load responses', 'error');
    }
}

function buildRankingsQuery(filterQuery) {
    const status = document.getElementById('statusFilter')?.value || 'all';
    const search = document.getElementById('searchInput')?.value.trim() || '';
    let query = filterQuery;

    query += `&status=${encodeURIComponent(status)}`;
    if (search) {
        query += `&search=${encodeURIComponent(search)}`;
    }

    return query;
}

function getAssignmentFilterQuery() {
    return '';
}

async function loadRankings() {
    const filterQuery = getAssignmentFilterQuery();

    try {
        const res = await axios.get(`${API}?action=rankings&exam_id=${EXAM_ID}${buildRankingsQuery(filterQuery)}`);
        if (res.data.success) {
            rankingsData = res.data.data || [];
            renderRankings(rankingsData);
            updateExportButtonVisibility();
        }
    } catch (err) {
        Swal.fire('Error', err.response?.data?.message || 'Failed to load rankings', 'error');
    }
}

function buildAssignmentFilterState(assignments) {
    const batches = new Map();
    const sectionsByBatch = new Map();
    const allSections = new Map();

    (assignments || []).forEach((assignment) => {
        const batchId = assignment.parent_batch_id || assignment.batch_id;
        const batchName = assignment.batch_name || 'Batch';
        if (batchId) {
            batches.set(String(batchId), batchName);
        }

        const sectionId = assignment.section_id;
        const sectionName = assignment.section_name;
        if (!batchId || !(sectionId > 0) || !sectionName) {
            return;
        }

        const batchKey = String(batchId);
        if (!sectionsByBatch.has(batchKey)) {
            sectionsByBatch.set(batchKey, new Map());
        }
        sectionsByBatch.get(batchKey).set(String(sectionId), sectionName);
        allSections.set(String(sectionId), sectionName);
    });

    const toSortedList = (map) => [...map.entries()]
        .map(([id, name]) => ({ id, name }))
        .sort((a, b) => a.name.localeCompare(b.name));

    return {
        batches: toSortedList(batches),
        sectionsByBatch,
        allSections: toSortedList(allSections),
        showBatch: batches.size > 1,
        showSection: allSections.size > 0
    };
}

function getSectionsForSelectedBatch(batchId) {
    if (!assignmentFilterState) {
        return [];
    }

    if (!batchId) {
        return assignmentFilterState.allSections;
    }

    const sections = assignmentFilterState.sectionsByBatch.get(String(batchId));
    if (!sections) {
        return [];
    }

    return [...sections.entries()]
        .map(([id, name]) => ({ id, name }))
        .sort((a, b) => a.name.localeCompare(b.name));
}

function populateSectionFilter(sections, preserveSelection) {
    const sectionFilter = document.getElementById('sectionFilter');
    if (!sectionFilter) {
        return;
    }

    const previousValue = preserveSelection ? sectionFilter.value : '';
    sectionFilter.innerHTML = '<option value="">All sections</option>' + sections
        .map(({ id, name }) => `<option value="${escapeHtml(id)}">${escapeHtml(name)}</option>`)
        .join('');

    const validIds = new Set(sections.map((section) => section.id));
    sectionFilter.value = previousValue && validIds.has(previousValue) ? previousValue : '';
    sectionFilter.style.display = sections.length > 0 ? '' : 'none';
}

function updateExportButtonVisibility() {
    const exportBtn = document.getElementById('exportCsvBtn');
    if (!exportBtn) {
        return;
    }

    const onRankings = activeResponseTab === 'rankings';
    exportBtn.style.display = onRankings ? '' : 'none';
    exportBtn.disabled = !onRankings || rankingsData.length === 0;
}

function updateResponseFiltersVisibility() {
    const wrapper = document.getElementById('responseFilters');
    if (!wrapper) {
        return;
    }

    const onRankings = activeResponseTab === 'rankings';
    wrapper.style.display = onRankings ? 'flex' : 'none';

    wrapper.querySelectorAll('.rankings-only-filter').forEach((element) => {
        element.style.display = onRankings ? '' : 'none';
    });

    updateAssignmentFilterDropdownVisibility();
}

function updateAssignmentFilterDropdownVisibility() {
    const batchFilter = document.getElementById('batchFilter');
    const sectionFilter = document.getElementById('sectionFilter');
    if (!batchFilter || !sectionFilter || !assignmentFilterState) {
        return;
    }

    batchFilter.style.display = assignmentFilterState.showBatch ? '' : 'none';

    if (!assignmentFilterState.showSection) {
        sectionFilter.style.display = 'none';
        return;
    }

    const batchId = batchFilter.style.display !== 'none' ? (batchFilter.value || '') : '';
    const sections = getSectionsForSelectedBatch(batchId);
    sectionFilter.style.display = sections.length > 0 ? '' : 'none';
}

function handleBatchFilterChange() {
    const batchFilter = document.getElementById('batchFilter');
    const batchId = batchFilter?.value || '';
    const sections = getSectionsForSelectedBatch(batchId);

    populateSectionFilter(sections, false);
    updateResponseFiltersVisibility();
    loadAssignmentFilteredData();
}

function setupAssignmentFilters(assignments) {
    assignmentFiltersEnabled = false;
    assignmentFilterState = null;
    updateResponseFiltersVisibility();
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
                <p>Overall Average</p>
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
    const exam = summaryData?.exam || {};
    const retakeOpen = exam.Status === 'Published' && Number(exam.Is_Archived || 0) !== 1;

    if (!rows.length) {
        tbody.innerHTML = '';
        empty.style.display = 'block';
        return;
    }

    empty.style.display = 'none';
    tbody.innerHTML = rows.map(row => {
        const attemptCount = Number(row.attempt_count || 1);
        const retakeGranted = Number(row.Retake_Granted || 0) === 1;
        const retakeUsed = attemptCount >= 2 || Number(row.Is_Retake || 0) === 1;
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
                    <small class="text-muted">${escapeHtml(row.Badge_Number || '')}</small>
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
                    <div class="d-grid gap-1">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewDetail(${row.Result_ID})">
                            View
                        </button>
                        ${CAN_GRANT_RETAKE ? `
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="grantRetake(${row.Result_ID})"
                            ${retakeOpen && !retakeGranted && !retakeUsed ? '' : 'disabled'}
                            title="${escapeHtml(!retakeOpen ? 'Retake is available only while the exam is open' : (retakeUsed ? 'This trainee has already used their one retake' : (retakeGranted ? 'A retake is already authorized' : 'Let this trainee take the exam again')))}">
                            Retake
                        </button>` : ''}
                    </div>
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
            <td>${escapeHtml(row.Personnel_Rank || '—')}</td>
            <td>
                <div class="fw-medium">${escapeHtml(row.Fullname)}</div>
                <small class="text-muted">${escapeHtml(row.Badge_Number || '')}</small>
            </td>
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

function formatAttemptScoreRatio(score, total) {
    const value = parseFloat(score);
    const count = parseInt(total, 10) || 0;
    if (!Number.isFinite(value)) {
        return '—';
    }
    const shown = Number.isInteger(value) ? String(value) : String(Math.round(value));
    return count > 0 ? `${shown}/${count}` : shown;
}

function formatHistoryAverage(score, total) {
    const value = parseFloat(score);
    const count = parseInt(total, 10) || 0;
    if (!Number.isFinite(value) || count <= 0) {
        return '—';
    }
    const ratio = Math.min(1, Math.max(0, value / count));
    const average = Math.round((ratio * 50 + 50) * 100) / 100;
    return Number.isInteger(average) ? String(average) : average.toFixed(1);
}

function switchTab(tab) {
    activeResponseTab = tab;

    document.querySelectorAll('#responseTabs .nav-link').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tab);
    });

    document.getElementById('rankingsPanel').style.display = tab === 'rankings' ? 'block' : 'none';
    document.getElementById('notSubmittedPanel').style.display = tab === 'not-submitted' ? 'block' : 'none';
    document.getElementById('itemAnalysisPanel').style.display = tab === 'item-analysis' ? 'block' : 'none';

    updateResponseFiltersVisibility();
    updateExportButtonVisibility();

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
        notice.textContent = `${data.not_submitted_count} trainee(s) have not submitted. Analysis is based on ${data.total_examinees} finished submission(s).`;
    } else if (data.all_completed) {
        notice.style.display = 'block';
        notice.className = 'alert alert-info py-2 small mb-3';
        notice.textContent = `All ${data.assigned_count} assigned trainee(s) have completed the exam.`;
    } else {
        notice.style.display = 'none';
    }

    renderItemAnalysisList(getFilteredItemAnalysisItems());
}

function getFilteredItemAnalysisItems() {
    const items = itemAnalysisData?.items || [];
    const query = (document.getElementById('itemAnalysisSearch')?.value || '').trim().toLowerCase();

    if (!query) {
        return items;
    }

    return items.filter(item => {
        const haystack = [
            item.question_text,
            item.correct_answer,
            item.difficulty_interpretation,
            item.discrimination_interpretation,
            `q${item.question_number}`,
        ].join(' ').toLowerCase();
        return haystack.includes(query);
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
    const traineeWord = count === 1 ? 'trainee' : 'trainees';
    return `Selected by ${count} ${traineeWord} | ${formatPercent(percentage)}`;
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
                <p class="item-analysis-question question-stem">${escapeHtml(item.question_text)}</p>
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
    document.getElementById('detailModalTitle').textContent = 'Trainee Response';
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
    const badge = data.Badge_Number || '—';
    const rank = data.Personnel_Rank || '—';
    document.getElementById('detailModalTitle').textContent = data.Fullname || 'Trainee Response';
    document.getElementById('detailModalSub').textContent = `${badge} | ${rank}`;

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
                    <strong class="question-stem">Q${index + 1}. ${escapeHtml(q.Question_Text)}</strong>
                    ${badge}
                </div>
                ${choicesHtml}
            </div>
        `;
    }).join('');

    const history = Array.isArray(data.attempt_history) ? data.attempt_history : [];
    const historyHtml = history.length > 1 ? `
        <div class="mb-3">
            <div class="small text-muted mb-1">Retake history</div>
            ${history.map(attempt => `
                <div class="d-flex justify-content-between small border-bottom py-1">
                    <span><strong>Attempt ${attempt.attempt_number}</strong> Score: ${escapeHtml(formatAttemptScoreRatio(attempt.Score, data.total_questions))} Average: ${formatHistoryAverage(attempt.Score, data.total_questions)}%</span>
                    <span>${formatDateTime(attempt.Submission_Date)}</span>
                </div>
            `).join('')}
        </div>
    ` : '';
    document.getElementById('detailModalBody').innerHTML = `
        <div class="mb-3 p-3 rounded bg-light">
            <div class="row g-2 small">
                <div class="col-md-4"><strong>Submitted:</strong> ${formatDateTime(data.Submission_Date)}</div>
                <div class="col-md-4"><strong>Overall Average:</strong> ${formatGrade(data.grade_percent)}%</div>
                <div class="col-md-4"><strong>Score:</strong> ${escapeHtml(data.score_display ?? data.Score ?? '—')}</div>
                <div class="col-md-4"><strong>Batch:</strong> ${escapeHtml(data.batch_names || '—')}</div>
                <div class="col-md-4"><strong>Section:</strong> ${escapeHtml(data.section_names || '—')}</div>
                <div class="col-md-4"><strong>Remarks:</strong> ${escapeHtml(data.remarks || data.status || '—')}</div>
            </div>
        </div>
        ${historyHtml}
        ${questionsHtml || '<p class="text-muted">No questions found.</p>'}
    `;
}

function exportCsv() {
    if (activeResponseTab !== 'rankings' || !rankingsData.length) return;

    const headers = [
        'Rank', 'Badge No.', 'Name', 'Batch', 'Section',
        'Score', 'Ave %', 'Standing', 'Remarks', 'Time', 'Submitted'
    ];
    const lines = [headers.map(h => csvCell(h)).join(',')];

    rankingsData.forEach(row => {
        lines.push([
            csvCell(row.Personnel_Rank),
            csvCell(row.Badge_Number),
            csvCell(row.Fullname),
            csvCell(row.batch_names),
            csvCell(row.section_names),
            csvCell(formatScoreForExport(row)),
            csvCell(formatGrade(row.grade_percent)),
            csvCell(row.standing ?? row.rank),
            csvCell(row.remarks || row.status),
            csvCell(row.time_taken || 'N/A'),
            csvCell(formatDateTime(row.Submission_Date))
        ].join(','));
    });

    const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    const title = summaryData?.exam?.Title || 'exam';
    link.href = url;
    link.download = `${sanitizeFilename(title)}-trainee-rankings-${formatLocalDate(new Date())}.csv`;
    link.click();
    URL.revokeObjectURL(url);

    axios.post(`${API}?action=log_export&exam_id=${EXAM_ID}`).catch(() => {});
}

function formatScoreForExport(row) {
    const display = row.score_display;
    if (display != null && String(display).includes('/')) {
        const parts = String(display).split('/');
        if (parts.length === 2 && parts[0] !== '' && parts[1] !== '') {
            return excelText(`${parts[0]}/${parts[1]}`);
        }
    }
    const score = row.Score;
    const total = row.total_items ?? summaryData?.exam?.question_count ?? 0;
    if (total > 0 && score != null && score !== '') {
        return excelText(`${score}/${total}`);
    }
    return score != null ? String(score) : '';
}

/** Keep values like 5/5 as text so Excel does not turn them into dates. */
function excelText(value) {
    return `="${value}"`;
}

function formatLocalDate(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
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

async function grantRetake(resultId) {
    if (!CAN_GRANT_RETAKE || retakeInFlight) return;

    const row = rankingsData.find(item => Number(item.Result_ID) === Number(resultId));
    if (!row) return;

    const result = await Swal.fire({
        title: 'Allow retake?',
        html: `Allow retake for <strong>${escapeHtml(row.Fullname)}</strong>? Trainees can take the exam again while it is still open.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Allow retake',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#d97706'
    });

    if (!result.isConfirmed) return;

    retakeInFlight = true;
    try {
        const res = await axios.post(`${API}?action=grant_retake&exam_id=${EXAM_ID}&result_id=${encodeURIComponent(resultId)}`);
        if (!res.data.success) {
            throw new Error(res.data.message || 'Failed to allow retake');
        }
    } catch (err) {
        Swal.fire(
            'Unable to allow retake',
            err.response?.data?.message || err.message || 'Please try again.',
            'error'
        );
        return;
    } finally {
        retakeInFlight = false;
    }

    await Swal.fire({
        icon: 'success',
        title: 'Retake allowed',
        text: `${row.Fullname} can take this exam again.`,
        timer: 2200,
        showConfirmButton: false
    });

    itemAnalysisLoaded = false;
    itemAnalysisData = null;
    try {
        await reloadResponses();
    } catch (err) {
        window.location.reload();
    }
}

async function reloadResponses() {
    const summaryRes = await axios.get(`${API}?action=summary&exam_id=${EXAM_ID}`);
    if (!summaryRes.data.success) {
        throw new Error(summaryRes.data.message || 'Failed to refresh exam responses');
    }

    summaryData = summaryRes.data.data;
    renderSummary(summaryData);
    await loadAssignmentFilteredData();
}

window.viewDetail = viewDetail;
window.grantRetake = grantRetake;
