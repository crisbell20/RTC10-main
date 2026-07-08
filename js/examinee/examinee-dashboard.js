/**
 * Examinee Dashboard JavaScript
 * Handles data fetching and UI updates for the examinee dashboard
 */

// API endpoints
const API_BASE = '../../api/examinee/';
const DASHBOARD_API = API_BASE + 'dashboard.php';
const START_EXAM_API = API_BASE + 'start-exam.php';

/**
 * Fetch dashboard statistics
 */
async function fetchDashboardStats() {
    try {
        const response = await axios.get(DASHBOARD_API + '?action=statistics');
        if (response.data.success) {
            updateStatistics(response.data.data);
        } else {
            console.error('Failed to fetch statistics:', response.data.message);
        }
    } catch (error) {
        handleError(error, 'Failed to load dashboard statistics');
    }
}

/**
 * Fetch available exams
 */
async function fetchAvailableExams() {
    try {
        const response = await axios.get(DASHBOARD_API + '?action=available-exams');
        if (response.data.success) {
            populateExamsTable(response.data.data);
        } else {
            console.error('Failed to fetch available exams:', response.data.message);
        }
    } catch (error) {
        handleError(error, 'Failed to load available exams');
    }
}

/**
 * Fetch recent results
 */
async function fetchRecentResults() {
    try {
        const response = await axios.get(DASHBOARD_API + '?action=recent-results');
        if (response.data.success) {
            populateResultsTable(response.data.data);
        } else {
            console.error('Failed to fetch recent results:', response.data.message);
        }
    } catch (error) {
        handleError(error, 'Failed to load recent results');
    }
}

/**
 * Update statistics cards
 */
function updateStatistics(data) {
    // Update exams available
    document.getElementById('examsAvailable').textContent = data.exams_available || 0;
    document.getElementById('availableStatus').textContent = 
        data.exams_available > 0 ? `${data.exams_available} exam${data.exams_available > 1 ? 's' : ''} ready` : 'No exams available';
    
    // Update exams completed
    document.getElementById('examsCompleted').textContent = data.exams_completed || 0;
    document.getElementById('completionRate').textContent = 
        `${data.completion_rate || 0}% completion rate`;
    
    // Update average score
    document.getElementById('averageScore').textContent = 
        data.average_score > 0 ? `${data.average_score}%` : '0%';
    document.getElementById('scoreChange').textContent = 
        data.average_score >= 75 ? 'Good performance' : 'Keep improving';
    
    // Update learning points
    document.getElementById('learningPoints').textContent = data.learning_points || 0;
    document.getElementById('pointsLabel').textContent = 
        `${data.learning_points || 0} total points earned`;
}

/**
 * Populate available exams table
 */
function populateExamsTable(exams) {
    const tbody = document.getElementById('examsTableBody');
    tbody.innerHTML = '';
    
    if (!exams || exams.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center text-muted py-4">
                    <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                    <p class="mt-2">No exams available at this time</p>
                </td>
            </tr>
        `;
        return;
    }
    
    exams.forEach(exam => {
        const row = document.createElement('tr');
        
        // Calculate status based on exam state
        const status = getExamStatus(exam);
        
        // Format schedule date
        const scheduleDate = exam.Schedule_Date ? 
            formatDate(exam.Schedule_Date) : 'Not scheduled';
        
        // Format deadline
        const deadline = exam.Deadline ? 
            formatDateTime(exam.Deadline) : 'No deadline';
        
        // Format description (truncate if too long)
        const description = exam.Description ? 
            (exam.Description.length > 50 ? escapeHtml(exam.Description.substring(0, 50)) + '...' : escapeHtml(exam.Description)) : 
            'No description';
        
        // Format duration
        const duration = exam.Duration ? `${exam.Duration} mins` : 'N/A';
        
        // Format question count
        const questionCount = exam.Question_Count || 0;
        
        // Determine button text
        const buttonText = exam.has_started ? 'Continue Exam' : 'Take Exam';
        const buttonIcon = exam.has_started ? 'arrow-right-circle' : 'play-circle';
        
        row.innerHTML = `
            <td>${escapeHtml(exam.Title)}</td>
            <td>${description}</td>
            <td>${escapeHtml(exam.Course_Name)}</td>
            <td>${escapeHtml(exam.Subject_Name)}</td>
            <td>${questionCount}</td>
            <td>${duration}</td>
            <td><span class="badge bg-${status.color}">${status.text}</span></td>
            <td>
                <small class="text-muted">Due: ${deadline}</small>
            </td>
            <td>
                <button class="btn btn-sm btn-primary take-exam-btn" 
                        data-exam-id="${exam.Exam_ID}"
                        data-session-id="${exam.Session_ID || ''}"
                        data-has-started="${exam.has_started}"
                        data-requires-code="${exam.requires_exam_code ? 'true' : 'false'}"
                        ${status.text === 'Overdue' && !exam.has_started ? 'disabled' : ''}>
                    <i class="bi bi-${buttonIcon}"></i> ${buttonText}
                </button>
            </td>
        `;
        
        tbody.appendChild(row);
    });
    
    // Attach event listeners to "Take Exam" buttons
    document.querySelectorAll('.take-exam-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            handleTakeExam(this.getAttribute('data-exam-id'), this);
        });
    });
}

/**
 * Populate recent results table
 */
function populateResultsTable(results) {
    const tbody = document.getElementById('resultsTableBody');
    tbody.innerHTML = '';
    
    if (!results || results.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center text-muted py-4">
                    <i class="bi bi-clipboard-data" style="font-size: 2rem;"></i>
                    <p class="mt-2">No exam results yet</p>
                </td>
            </tr>
        `;
        return;
    }
    
    results.forEach(result => {
        const row = document.createElement('tr');
        
        // Format submission date
        const submissionDate = formatDateTime(result.Submission_Date);
        
        // Determine pass/fail badge
        const statusBadge = result.status === 'Passed' ? 
            '<span class="badge bg-success">Passed</span>' : 
            '<span class="badge bg-danger">Failed</span>';

        const canViewDetails = result.can_view_details !== false;
        const detailsCell = canViewDetails
            ? `<button class="btn btn-sm btn-outline-primary view-details-btn" data-result-id="${result.Result_ID}">
                    <i class="bi bi-eye"></i> View Details
               </button>`
            : `<span class="text-muted small"><i class="bi bi-lock"></i> Review disabled</span>`;
        
        row.innerHTML = `
            <td>${escapeHtml(result.exam_title)}</td>
            <td>${submissionDate}</td>
            <td>${result.Score}</td>
            <td>${result.Percentage}%</td>
            <td>${statusBadge}</td>
            <td>${detailsCell}</td>
        `;
        
        tbody.appendChild(row);
    });
    
    // Attach event listeners to "View Details" buttons
    document.querySelectorAll('.view-details-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const resultId = this.getAttribute('data-result-id');
            handleViewDetails(resultId);
        });
    });
}

/**
 * Handle "Take Exam" button click
 */
async function handleTakeExam(examId, buttonEl) {
    const button = buttonEl || (typeof event !== 'undefined' ? event.currentTarget : null);
    if (!button) {
        Swal.fire('Error', 'Unable to start exam', 'error');
        return;
    }

    const hasStarted = button.getAttribute('data-has-started') === 'true';
    const sessionId = button.getAttribute('data-session-id');
    const requiresCode = button.getAttribute('data-requires-code') === 'true';
    
    // If exam already started, just continue (no code required on resume)
    if (hasStarted && sessionId) {
        window.location.href = `../student masterfiles/exam.php?session_id=${sessionId}`;
        return;
    }
    
    const examRow = button.closest('tr');
    const duration = examRow ? examRow.cells[5].textContent : 'N/A';
    const deadlineEl = examRow ? examRow.querySelector('small') : null;
    const deadline = deadlineEl ? deadlineEl.textContent : '';
    
    const swalConfig = {
        title: 'Start Exam',
        html: `
            <div class="text-start">
                <p>Are you ready to begin this exam?</p>
                ${requiresCode ? `
                <div class="mb-3">
                    <label for="examCodeInput" class="form-label">Exam code <span class="text-danger">*</span></label>
                    <input id="examCodeInput" class="form-control text-uppercase" placeholder="Enter exam code" maxlength="12" autocomplete="off">
                    <small class="text-muted">Get this code from your instructor or proctor.</small>
                </div>` : ''}
                <div class="alert alert-warning">
                    <strong><i class="bi bi-clock"></i> Important:</strong>
                    <ul class="mb-0 mt-2">
                        <li>The timer will start immediately</li>
                        <li>Duration: <strong>${duration}</strong></li>
                        <li>${deadline}</li>
                        <li><strong>Fullscreen mode</strong> will be required — tab switching is monitored</li>
                    </ul>
                </div>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, start exam',
        cancelButtonText: 'Not yet',
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#6b7280',
        width: '500px',
        didOpen: () => {
            if (requiresCode) {
                document.getElementById('examCodeInput')?.focus();
            }
        },
        preConfirm: () => {
            if (!requiresCode) {
                return { exam_code: null };
            }
            const codeInput = document.getElementById('examCodeInput');
            const code = codeInput ? codeInput.value.trim() : '';
            if (!code) {
                Swal.showValidationMessage('Exam code is required');
                return false;
            }
            return { exam_code: code };
        }
    };

    const result = await Swal.fire(swalConfig);
    
    if (!result.isConfirmed) {
        return;
    }
    
    Swal.fire({
        title: 'Starting exam...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    try {
        const payload = { exam_id: examId };
        if (requiresCode && result.value?.exam_code) {
            payload.exam_code = result.value.exam_code;
        }

        const response = await axios.post(START_EXAM_API, payload);
        
        if (response.data.success) {
            Swal.close();
            window.location.href = `../student masterfiles/exam.php?session_id=${response.data.data.session_id}`;
        } else {
            Swal.fire({
                title: 'Error',
                text: response.data.message,
                icon: 'error',
                confirmButtonColor: '#2563eb'
            });
        }
    } catch (error) {
        handleError(error, 'Failed to start exam');
    }
}

/**
 * Handle "View Details" button click
 */
function handleViewDetails(resultId) {
    // Redirect to result page in student masterfiles
    window.location.href = `../student masterfiles/result.php?result_id=${resultId}`;
}

/**
 * Get exam status based on deadline and start time
 */
function getExamStatus(exam) {
    // If exam has been started, check time remaining for the exam duration
    if (exam.has_started && exam.exam_deadline) {
        const now = new Date();
        const deadline = new Date(exam.exam_deadline);
        const timeRemaining = deadline - now;
        
        if (timeRemaining <= 0) {
            return { text: 'Time Expired', color: 'danger' };
        } else if (timeRemaining < 10 * 60 * 1000) { // Less than 10 minutes
            return { text: 'In Progress', color: 'warning' };
        } else {
            return { text: 'In Progress', color: 'info' };
        }
    }
    
    // If not started, check the deadline to START the exam
    if (!exam.Deadline) {
        return { text: 'Available', color: 'success' };
    }
    
    const now = new Date();
    const deadline = new Date(exam.Deadline);
    const timeUntilDeadline = deadline - now;
    
    // Check if deadline to start has passed
    if (timeUntilDeadline < 0) {
        return { text: 'Overdue', color: 'danger' };
    } else if (timeUntilDeadline < 24 * 60 * 60 * 1000) { // Less than 24 hours
        return { text: 'Due Soon', color: 'warning' };
    } else {
        return { text: 'Available', color: 'success' };
    }
}

/**
 * Format date as readable string
 */
function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

/**
 * Format date and time as readable string
 */
function formatDateTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    });
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Handle errors
 */
function handleError(error, defaultMessage) {
    console.error('Error:', error);
    
    let message = defaultMessage;
    
    if (error.response) {
        // HTTP error response
        const status = error.response.status;
        
        if (status === 401) {
            // Session expired
            Swal.fire({
                title: 'Session Expired',
                text: 'Your session has expired. Please log in again.',
                icon: 'warning',
                confirmButtonColor: '#2563eb',
                timer: 3000
            }).then(() => {
                window.location.href = '../../login.php';
            });
            return;
        } else if (status === 403) {
            message = 'Access denied. You do not have permission to access this resource.';
        } else if (status === 500) {
            message = error.response.data.message || 'Server error. Please try again later.';
        } else if (error.response.data && error.response.data.message) {
            message = error.response.data.message;
        }
    } else if (error.request) {
        // Network error
        message = 'Network error. Please check your connection and try again.';
    }
    
    Swal.fire({
        title: 'Error',
        text: message,
        icon: 'error',
        confirmButtonColor: '#2563eb'
    });
}

/**
 * Show error message
 */
function showError(message) {
    Swal.fire({
        title: 'Error',
        text: message,
        icon: 'error',
        confirmButtonColor: '#2563eb'
    });
}

/**
 * Initialize dashboard on page load
 */
document.addEventListener('DOMContentLoaded', function() {
    // Fetch all data in parallel
    Promise.all([
        fetchDashboardStats(),
        fetchAvailableExams(),
        fetchRecentResults()
    ]).catch(error => {
        console.error('Error loading dashboard:', error);
    });
});
