const API_BASE = '../../api/ccmd/dashboard.php';

// Load dashboard statistics
function loadStats() {
    axios.get(`${API_BASE}?action=stats`)
        .then(res => {
            if (res.data.success) {
                const data = res.data.data;
                
                // Update stat cards
                document.querySelector('.stat-card.blue .stat-value').textContent = data.active_exams;
                document.querySelector('.stat-card.green .stat-value').textContent = data.active_participants;
                document.querySelector('.stat-card.red .stat-value').textContent = data.cheating_incidents;
                document.querySelector('.stat-card.purple .stat-value').textContent = data.completed_today;
                
                // Update footer labels
                document.querySelector('.stat-card.purple .stat-footer span').textContent = 'Today';
            }
        })
        .catch(err => {
            console.error('Error loading stats:', err);
        });
}

// Load live exam monitoring
function loadLiveExams() {
    axios.get(`${API_BASE}?action=live_exams`)
        .then(res => {
            if (res.data.success) {
                const exams = res.data.data;
                renderExamTable(exams);
            }
        })
        .catch(err => {
            console.error('Error loading live exams:', err);
        });
}

// Render exam monitoring table
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

// Load cheating incidents
function loadIncidents() {
    axios.get(`${API_BASE}?action=incidents`)
        .then(res => {
            if (res.data.success) {
                const incidents = res.data.data;
                renderIncidentTable(incidents);
            }
        })
        .catch(err => {
            console.error('Error loading incidents:', err);
        });
}

// Render incident table
function renderIncidentTable(incidents) {
    const tbody = document.getElementById('incidentTableBody');
    
    if (!incidents || incidents.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No incidents reported</td></tr>';
        return;
    }

    tbody.innerHTML = incidents.map(incident => `
        <tr>
            <td>${escapeHtml(incident.Trainee_Name)}</td>
            <td>${escapeHtml(incident.Exam_Name)}</td>
            <td>
                <span class="badge bg-danger">
                    ${escapeHtml(incident.Violation_Type)}
                </span>
            </td>
            <td>${formatDateTime(incident.Detected_Time)}</td>
            <td>
                <span class="badge ${getActionStatusBadge(incident.Action_Status)}">
                    ${escapeHtml(incident.Action_Status)}
                </span>
            </td>
        </tr>
    `).join('');
}

// Helper functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

function getStatusBadge(status) {
    switch(status) {
        case 'Published': return 'bg-success';
        case 'Draft': return 'bg-warning';
        case 'Closed': return 'bg-secondary';
        default: return 'bg-info';
    }
}

function getActionStatusBadge(status) {
    switch(status) {
        case 'Open': return 'bg-warning text-dark';
        case 'Pending': return 'bg-warning text-dark';
        case 'Reviewed': return 'bg-info';
        case 'Dismissed': return 'bg-secondary';
        case 'Escalated': return 'bg-danger';
        case 'Resolved': return 'bg-success';
        default: return 'bg-secondary';
    }
}

function formatDateTime(dateTime) {
    if (!dateTime) return '';
    const date = new Date(dateTime);
    return date.toLocaleString();
}

function viewExamDetails(examId) {
    window.location.href = `monitor-examination.php?exam_id=${examId}`;
}

// Auto-refresh every 30 seconds
function startAutoRefresh() {
    setInterval(() => {
        loadStats();
        loadLiveExams();
        loadIncidents();
    }, 30000); // 30 seconds
}

// Initialize dashboard
document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadLiveExams();
    loadIncidents();
    startAutoRefresh();
});
