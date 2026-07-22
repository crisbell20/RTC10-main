const API_BASE = '../../api/ccmd/dashboard.php';

function loadStats() {
    axios.get(`${API_BASE}?action=stats`)
        .then(res => {
            if (res.data.success) {
                const data = res.data.data;

                document.querySelector('.stat-card.blue .stat-value').textContent = data.active_exams;
                document.querySelector('.stat-card.green .stat-value').textContent = data.active_participants;

                const completedEl = document.getElementById('completedToday');
                if (completedEl) completedEl.textContent = data.completed_today;

                const publishedEl = document.getElementById('publishedExams');
                if (publishedEl) publishedEl.textContent = data.active_exams;
            }
        })
        .catch(err => {
            console.error('Error loading stats:', err);
        });
}

function loadLiveExams() {
    axios.get(`${API_BASE}?action=live_exams`)
        .then(res => {
            if (res.data.success) {
                renderExamTable(res.data.data);
            }
        })
        .catch(err => {
            console.error('Error loading live exams:', err);
        });
}

function renderExamTable(exams) {
    const tbody = document.getElementById('examTableBody');

    if (!exams || exams.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No active exams</td></tr>';
        return;
    }

    tbody.innerHTML = exams.map(exam => `
        <tr>
            <td>${escapeHtml(exam.Exam_Name)}</td>
            <td>${escapeHtml(exam.Course_Name)}</td>
            <td>${exam.Total_Participants || 0}</td>
            <td>
                <span class="badge ${exam.Active_Participants > 0 ? 'bg-success' : 'bg-secondary'}">
                    ${exam.Active_Participants || 0}
                </span>
            </td>
            <td>
                <span class="badge ${getStatusBadge(exam.Status)}">
                    ${escapeHtml(exam.Status)}
                </span>
            </td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="viewExamDetails(${exam.Exam_ID})">
                    <i class="bi bi-eye"></i> Monitor
                </button>
            </td>
        </tr>
    `).join('');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

function getStatusBadge(status) {
    switch (status) {
        case 'Published': return 'bg-success';
        case 'Draft': return 'bg-warning';
        case 'Closed': return 'bg-secondary';
        default: return 'bg-info';
    }
}

function viewExamDetails(examId) {
    window.location.href = `monitor-examination.php?exam_id=${examId}`;
}

function startAutoRefresh() {
    setInterval(() => {
        loadStats();
        loadLiveExams();
    }, 30000);
}

document.addEventListener('DOMContentLoaded', function () {
    loadStats();
    loadLiveExams();
    startAutoRefresh();
});
