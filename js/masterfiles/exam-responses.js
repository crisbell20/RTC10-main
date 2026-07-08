const API = '../../api/masterfiles/exam-responses.php';
const EXAM_ID = window.EXAM_RESPONSES_EXAM_ID;

let summaryData = null;
let rankingsData = [];
let notSubmittedData = [];
let detailModal = null;
let searchTimer = null;

document.addEventListener('DOMContentLoaded', () => {
    detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
    bindEvents();
    loadPage();
});

function bindEvents() {
    document.getElementById('logoutBtn')?.addEventListener('click', handleLogout);

    document.querySelectorAll('#responseTabs .nav-link').forEach(btn => {
        btn.addEventListener('click', () => switchTab(btn.dataset.tab));
    });

    document.getElementById('statusFilter')?.addEventListener('change', loadRankings);
    document.getElementById('searchInput')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadRankings, 300);
    });

    document.getElementById('exportCsvBtn')?.addEventListener('click', exportCsv);
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

function renderSummary(data) {
    const exam = data.exam || {};
    const passingScore = data.passing_score ?? exam.Passing_Score ?? 50;

    document.getElementById('examTitle').textContent = exam.Title || 'Exam Responses';
    document.getElementById('examMeta').textContent =
        `${exam.Course_Name || ''} • ${exam.Subject_Name || ''} • ${exam.question_count || 0} items • Passing: ${formatScore(passingScore)}%`;

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
        const isAnswered = q.user_answer_id != null;
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
            </div>
        </div>
        ${questionsHtml || '<p class="text-muted">No questions found.</p>'}
    `;
}

function exportCsv() {
    if (!rankingsData.length) return;

    const headers = [
        'Personnel Rank', 'Name', 'Academic Number', 'Score', 'Grade (%)',
        'Standing', 'Remarks', 'Time Taken', 'Submitted'
    ];
    const lines = [headers.join(',')];

    rankingsData.forEach(row => {
        lines.push([
            csvCell(row.Personnel_Rank),
            csvCell(row.Fullname),
            csvCell(row.Academic_Number),
            csvCell(row.score_display ?? row.Score),
            formatGrade(row.grade_percent),
            row.standing ?? row.rank,
            csvCell(row.remarks || row.status),
            csvCell(row.time_taken),
            csvCell(row.Submission_Date)
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

function handleLogout(e) {
    e.preventDefault();
    Swal.fire({
        title: 'Logout',
        text: 'Are you sure you want to logout?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, logout'
    }).then(result => {
        if (result.isConfirmed) {
            fetch('../../api/auth/logout.php', { method: 'POST' })
                .finally(() => { window.location.href = '../../html/auth/login.html'; });
        }
    });
}

window.viewDetail = viewDetail;
