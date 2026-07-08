const API_BASE = '../../api/masterfiles/';
const COURSES_API = API_BASE + 'courses.php';
const SUBJECTS_API = API_BASE + 'subjects.php';
const EXAMS_API = API_BASE + 'exams.php';
const EXAM_QUESTIONS_API = API_BASE + 'exam-questions.php';
const EXAM_BATCHES_API = API_BASE + 'exam-batches.php';
const QUESTION_BANK_API = API_BASE + 'exam.php';

let exams = [];
let courses = [];
let subjectsByCourse = {};
let allQuestions = [];
let allBatches = [];
let currentExamId = null;
let selectedQuestionIds = [];
let selectedBatchIds = [];

let currentCourseFilter = '';
let currentSubjectFilter = '';
let currentStatusFilter = '';

function showAlert(message, type = 'success') {
    const alertDiv = document.getElementById('messageAlert');
    alertDiv.innerHTML = `<div class="alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
}

function loadCoursesAndSubjects() {
    // Load courses first
    axios.get(`${COURSES_API}?action=list`)
        .then(res => {
            if (res.data.success) {
                courses = res.data.data || [];
                const courseSelect = document.getElementById('filterCourse');
                const examCourse = document.getElementById('examCourse');
                if (courseSelect) {
                    courseSelect.innerHTML = '<option value="">All courses</option>';
                }
                if (examCourse) {
                    examCourse.innerHTML = '<option value="">Select course</option>';
                }
                courses.forEach(c => {
                    if (courseSelect) {
                        const opt = document.createElement('option');
                        opt.value = c.Course_ID;
                        opt.textContent = c.Course_Name;
                        courseSelect.appendChild(opt);
                    }
                    if (examCourse) {
                        const opt2 = document.createElement('option');
                        opt2.value = c.Course_ID;
                        opt2.textContent = c.Course_Name;
                        examCourse.appendChild(opt2);
                    }
                });
                populateQuestionCourseFilter();
            }
        })
        .catch(err => console.error('Error loading courses:', err));

    // Preload all subjects and group by course
    axios.get(`${SUBJECTS_API}?action=list`)
        .then(res => {
            if (res.data.success) {
                const list = res.data.data || [];
                subjectsByCourse = {};
                list.forEach(s => {
                    if (!subjectsByCourse[s.Course_ID]) {
                        subjectsByCourse[s.Course_ID] = [];
                    }
                    subjectsByCourse[s.Course_ID].push(s);
                });
                populateSubjectFilters();
                console.log('Subjects loaded:', subjectsByCourse);
            }
        })
        .catch(err => console.error('Error loading subjects:', err));
}

function populateSubjectFilters() {
    const filterSubject = document.getElementById('filterSubject');
    if (!filterSubject) return;
    filterSubject.innerHTML = '<option value="">All subjects</option>';
    Object.values(subjectsByCourse).flat().forEach(s => {
        const opt = document.createElement('option');
        opt.value = s.Subject_ID;
        opt.textContent = `${s.Subject_Name} (${s.Subject_Code})`;
        filterSubject.appendChild(opt);
    });
}

function populateExamSubjectSelect(courseId, selectedSubjectId) {
    const examSubject = document.getElementById('examSubject');
    if (!examSubject) return;
    
    examSubject.innerHTML = '<option value="">Select subject</option>';
    
    if (!courseId) {
        console.log('No course selected');
        return;
    }
    
    if (!subjectsByCourse[courseId]) {
        console.log('No subjects found for course:', courseId);
        return;
    }
    
    console.log('Populating subjects for course:', courseId, subjectsByCourse[courseId]);
    
    subjectsByCourse[courseId].forEach(s => {
        const opt = document.createElement('option');
        opt.value = s.Subject_ID;
        opt.textContent = `${s.Subject_Name} (${s.Subject_Code})`;
        if (selectedSubjectId && String(selectedSubjectId) === String(s.Subject_ID)) {
            opt.selected = true;
        }
        examSubject.appendChild(opt);
    });
}

function loadExams() {
    const params = ['action=list'];
    if (currentCourseFilter) params.push(`course_id=${encodeURIComponent(currentCourseFilter)}`);
    if (currentSubjectFilter) params.push(`subject_id=${encodeURIComponent(currentSubjectFilter)}`);
    if (currentStatusFilter === 'Archived') params.push('archived_only=1');
    const qs = '?' + params.join('&');

    axios.get(`${EXAMS_API}${qs}`)
        .then(res => {
            if (res.data.success) {
                exams = res.data.data || [];
                renderExams();
            }
        })
        .catch(() => showAlert('Error loading exams', 'error'));
}

function filterExamsForDisplay() {
    if (currentStatusFilter === 'Archived') {
        return exams.filter(e => String(e.Is_Archived) === '1');
    }

    return exams.filter(e => {
        if (String(e.Is_Archived) === '1') {
            return false;
        }
        if (currentStatusFilter) {
            return e.Status === currentStatusFilter;
        }
        return true;
    });
}

function buildExamActionMenu(exam) {
    const isArchived = String(exam.Is_Archived) === '1';
    const items = [];

    if (!isArchived) {
        items.push(`<li><button class="dropdown-item" type="button" data-exam-action="edit" data-exam-id="${exam.Exam_ID}"><i class="bi bi-pencil me-2"></i>Edit exam</button></li>`);
        items.push(`<li><button class="dropdown-item" type="button" data-exam-action="copy-code" data-exam-id="${exam.Exam_ID}"><i class="bi bi-clipboard me-2"></i>Copy exam code</button></li>`);
        items.push(`<li><button class="dropdown-item" type="button" data-exam-action="reset-code" data-exam-id="${exam.Exam_ID}"><i class="bi bi-arrow-clockwise me-2"></i>Reset exam code</button></li>`);
        items.push('<li><hr class="dropdown-divider"></li>');

        const reviewOn = String(exam.Allow_Response_Review) === '1';
        items.push(`<li><button class="dropdown-item" type="button" data-exam-action="toggle-review" data-exam-id="${exam.Exam_ID}"><i class="bi ${reviewOn ? 'bi-check-circle-fill text-success' : 'bi-circle'} me-2"></i>Allow response review</button></li>`);

        const finished = parseInt(exam.Finished_Count, 10) || 0;
        items.push(`<li><button class="dropdown-item" type="button" data-exam-action="view-responses" data-exam-id="${exam.Exam_ID}"><i class="bi bi-bar-chart-line me-2"></i>View responses${finished ? ` (${finished})` : ''}</button></li>`);
        items.push('<li><hr class="dropdown-divider"></li>');

        if (exam.Status === 'Draft') {
            items.push(`<li><button class="dropdown-item" type="button" data-exam-action="publish" data-exam-id="${exam.Exam_ID}"><i class="bi bi-send me-2"></i>Publish exam</button></li>`);
        }
        if (exam.Status === 'Published') {
            items.push(`<li><button class="dropdown-item" type="button" data-exam-action="close" data-exam-id="${exam.Exam_ID}"><i class="bi bi-lock me-2"></i>Close exam</button></li>`);
        }
        if (exam.Status === 'Closed') {
            items.push(`<li><button class="dropdown-item" type="button" data-exam-action="reopen" data-exam-id="${exam.Exam_ID}"><i class="bi bi-unlock me-2"></i>Reopen exam</button></li>`);
        }

        items.push(`<li><button class="dropdown-item" type="button" data-exam-action="archive" data-exam-id="${exam.Exam_ID}"><i class="bi bi-archive me-2"></i>Archive</button></li>`);

        if ((exam.Session_Count || 0) === 0 && exam.Status !== 'Published') {
            items.push('<li><hr class="dropdown-divider"></li>');
            items.push(`<li><button class="dropdown-item text-danger" type="button" data-exam-action="delete" data-exam-id="${exam.Exam_ID}"><i class="bi bi-trash me-2"></i>Delete</button></li>`);
        }
    } else {
        items.push(`<li><button class="dropdown-item" type="button" data-exam-action="restore" data-exam-id="${exam.Exam_ID}"><i class="bi bi-arrow-counterclockwise me-2"></i>Restore</button></li>`);
        const finishedArchived = parseInt(exam.Finished_Count, 10) || 0;
        items.push(`<li><button class="dropdown-item" type="button" data-exam-action="view-responses" data-exam-id="${exam.Exam_ID}"><i class="bi bi-bar-chart-line me-2"></i>View responses${finishedArchived ? ` (${finishedArchived})` : ''}</button></li>`);
        if (exam.Exam_Code) {
            items.push(`<li><button class="dropdown-item" type="button" data-exam-action="copy-code" data-exam-id="${exam.Exam_ID}"><i class="bi bi-clipboard me-2"></i>Copy exam code</button></li>`);
        }
    }

    return items.join('');
}

function renderExams() {
    const container = document.getElementById('examsContainer');
    if (!container) return;

    const filtered = filterExamsForDisplay();

    if (!filtered.length) {
        container.innerHTML = '<div class="text-center text-muted py-4">No exams found</div>';
        return;
    }

    container.innerHTML = filtered.map(e => `
        <div class="course-card" data-exam-id="${e.Exam_ID}">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <h5 class="mb-1">${escapeHtml(e.Title)}</h5>
                    <small class="text-muted">${escapeHtml(e.Course_Name)} • ${escapeHtml(e.Subject_Name)}</small>
                </div>
                <div class="d-flex align-items-center gap-1">
                    <div class="dropdown">
                        <button type="button" class="btn btn-sm btn-link text-secondary p-0" data-bs-toggle="dropdown" aria-expanded="false" title="More actions">
                            <i class="bi bi-three-dots-vertical" style="font-size: 1.2rem;"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            ${buildExamActionMenu(e)}
                        </ul>
                    </div>
                </div>
            </div>

            ${e.Exam_Code ? `
            <div class="d-flex align-items-center flex-wrap gap-2 mb-3 p-2 rounded" style="background:#f8f9fa;">
                <span class="text-muted small">Exam code:</span>
                <code class="fs-5 fw-bold text-primary exam-code-value">${escapeHtml(e.Exam_Code)}</code>
                <button type="button" class="btn btn-sm btn-outline-primary" data-exam-action="copy-code" data-exam-id="${e.Exam_ID}">
                    <i class="bi bi-clipboard"></i> Copy
                </button>
                ${String(e.Is_Archived) !== '1' ? `
                <button type="button" class="btn btn-sm btn-outline-secondary" data-exam-action="reset-code" data-exam-id="${e.Exam_ID}">
                    <i class="bi bi-arrow-clockwise"></i> Reset
                </button>` : ''}
            </div>` : ''}

            <div class="mb-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-people" style="font-size: 1.5rem;"></i>
                    <span style="font-size: 1.5rem; font-weight: 600;">${e.Question_Count || 0}</span>
                    <span class="text-muted">questions</span>
                </div>
            </div>

            <div>
                <div class="text-muted small mb-2">DETAILS</div>
                <div class="d-flex gap-2 flex-wrap">
                    ${e.Schedule_Date ? `<span class="badge bg-light text-dark border">${new Date(e.Schedule_Date).toLocaleDateString()}</span>` : ''}
                    ${e.Deadline ? `<span class="badge bg-light text-dark border">Due: ${new Date(e.Deadline).toLocaleDateString()}</span>` : ''}
                    ${e.Duration ? `<span class="badge bg-light text-dark border">${e.Duration} min</span>` : ''}
                    ${e.Passing_Score ? `<span class="badge bg-light text-dark border">${e.Passing_Score}% passing</span>` : ''}
                    ${e.Allow_Response_Review !== undefined ? `<span class="badge ${String(e.Allow_Response_Review) === '1' ? 'bg-info text-white' : 'bg-light text-dark border'}">Review: ${String(e.Allow_Response_Review) === '1' ? 'ON' : 'OFF'}</span>` : ''}
                    <span class="badge ${badgeForStatus(e.Status, e.Is_Archived)}">${escapeHtml(String(e.Is_Archived) === '1' ? 'Archived' : e.Status)}</span>
                </div>
            </div>
        </div>
    `).join('');
}

function copyExamCode(examId) {
    const exam = exams.find(e => String(e.Exam_ID) === String(examId));
    if (!exam || !exam.Exam_Code) {
        showAlert('Exam code is not available', 'error');
        return;
    }

    const code = exam.Exam_Code;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(code)
            .then(() => showAlert(`Exam code copied: ${code}`, 'success'))
            .catch(() => fallbackCopyExamCode(code));
    } else {
        fallbackCopyExamCode(code);
    }
}

function fallbackCopyExamCode(code) {
    const temp = document.createElement('textarea');
    temp.value = code;
    document.body.appendChild(temp);
    temp.select();
    try {
        document.execCommand('copy');
        showAlert(`Exam code copied: ${code}`, 'success');
    } catch (err) {
        showAlert('Could not copy exam code', 'error');
    }
    document.body.removeChild(temp);
}

function showExamCodeModal(code, title = 'Exam Code') {
    Swal.fire({
        title,
        html: `
            <p class="text-muted mb-2">Share this code with examinees to start the exam.</p>
            <div class="p-3 rounded" style="background:#f8f9fa;">
                <code style="font-size:1.75rem; font-weight:700; letter-spacing:0.15em;">${escapeHtml(code)}</code>
            </div>
        `,
        icon: 'info',
        confirmButtonText: 'Copy code',
        showCancelButton: true,
        cancelButtonText: 'Close'
    }).then(result => {
        if (result.isConfirmed) {
            copyExamCodeByValue(code);
        }
    });
}

function copyExamCodeByValue(code) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(code)
            .then(() => showAlert(`Exam code copied: ${code}`, 'success'))
            .catch(() => fallbackCopyExamCode(code));
    } else {
        fallbackCopyExamCode(code);
    }
}

async function resetExamCode(examId) {
    const exam = exams.find(e => String(e.Exam_ID) === String(examId));
    if (!exam) return;

    const result = await Swal.fire({
        title: 'Reset exam code?',
        html: `Generate a new code for <strong>${escapeHtml(exam.Title)}</strong>?<br><small class="text-muted">Students using the old code will no longer be able to start the exam.</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Reset code',
        cancelButtonText: 'Cancel'
    });

    if (!result.isConfirmed) return;

    try {
        const res = await axios.post(EXAMS_API, { action: 'reset_code', exam_id: examId });
        if (res.data.success) {
            const idx = exams.findIndex(e => String(e.Exam_ID) === String(examId));
            if (idx !== -1) {
                exams[idx].Exam_Code = res.data.exam_code;
            }
            renderExams();
            showExamCodeModal(res.data.exam_code, 'New Exam Code');
        } else {
            showAlert(res.data.message || 'Failed to reset exam code', 'error');
        }
    } catch (err) {
        showAlert(err.response?.data?.message || 'Error resetting exam code', 'error');
    }
}

async function toggleResponseReview(examId) {
    const exam = exams.find(e => String(e.Exam_ID) === String(examId));
    if (!exam) return;

    const currentlyOn = String(exam.Allow_Response_Review) === '1';
    const enabling = !currentlyOn;

    const result = await Swal.fire({
        title: enabling ? 'Enable response review?' : 'Disable response review?',
        html: enabling
            ? `Examinees will be able to view their answers and correct solutions for <strong>${escapeHtml(exam.Title)}</strong> after submission.`
            : `Examinees will only see their score for <strong>${escapeHtml(exam.Title)}</strong>. Question breakdown and correct answers will be hidden.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: enabling ? 'Enable review' : 'Disable review',
        cancelButtonText: 'Cancel'
    });

    if (!result.isConfirmed) return;

    try {
        const res = await axios.post(EXAMS_API, { action: 'toggle_response_review', exam_id: examId });
        if (res.data.success) {
            const idx = exams.findIndex(e => String(e.Exam_ID) === String(examId));
            if (idx !== -1) {
                exams[idx].Allow_Response_Review = res.data.allow_response_review;
            }
            renderExams();
            showAlert(res.data.message, 'success');
        } else {
            showAlert(res.data.message || 'Failed to update response review setting', 'error');
        }
    } catch (err) {
        showAlert(err.response?.data?.message || 'Error updating response review setting', 'error');
    }
}

async function archiveExam(examId) {
    const exam = exams.find(e => String(e.Exam_ID) === String(examId));
    if (!exam) return;

    const result = await Swal.fire({
        title: 'Archive exam?',
        text: `"${exam.Title}" will be hidden from active exam management.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Archive',
        cancelButtonText: 'Cancel'
    });

    if (!result.isConfirmed) return;

    try {
        const res = await axios.post(EXAMS_API, { action: 'archive', exam_id: examId });
        if (res.data.success) {
            showAlert('Exam archived successfully', 'success');
            loadExams();
        } else {
            showAlert(res.data.message || 'Failed to archive exam', 'error');
        }
    } catch (err) {
        showAlert(err.response?.data?.message || 'Error archiving exam', 'error');
    }
}

async function restoreExam(examId) {
    try {
        const res = await axios.post(EXAMS_API, { action: 'restore', exam_id: examId });
        if (res.data.success) {
            showAlert('Exam restored successfully', 'success');
            currentStatusFilter = '';
            const statusFilter = document.getElementById('filterExamStatus');
            if (statusFilter) statusFilter.value = '';
            loadExams();
        } else {
            showAlert(res.data.message || 'Failed to restore exam', 'error');
        }
    } catch (err) {
        showAlert(err.response?.data?.message || 'Error restoring exam', 'error');
    }
}

async function closeExam(examId) {
    const result = await Swal.fire({
        title: 'Close exam?',
        text: 'Students will no longer be able to start this exam.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Close exam',
        cancelButtonText: 'Cancel'
    });

    if (!result.isConfirmed) return;

    try {
        const res = await axios.post(EXAMS_API, { action: 'close', exam_id: examId });
        if (res.data.success) {
            showAlert('Exam closed successfully', 'success');
            loadExams();
        } else {
            showAlert(res.data.message || 'Failed to close exam', 'error');
        }
    } catch (err) {
        showAlert(err.response?.data?.message || 'Error closing exam', 'error');
    }
}

async function publishExamQuick(examId) {
    const exam = exams.find(e => String(e.Exam_ID) === String(examId));
    if (!exam) return;

    if ((exam.Question_Count || 0) === 0 || (exam.Batch_Count || 0) === 0) {
        showAlert('Add questions and assign batches before publishing. Opening edit form...', 'error');
        editExam(examId);
        return;
    }

    const result = await Swal.fire({
        title: 'Publish exam?',
        text: `"${exam.Title}" will become available to assigned examinees.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Publish',
        cancelButtonText: 'Cancel'
    });

    if (!result.isConfirmed) return;

    try {
        const res = await axios.post(EXAMS_API, {
            action: 'update',
            exam_id: exam.Exam_ID,
            subject_id: exam.Subject_ID,
            title: exam.Title,
            description: exam.Description,
            schedule_date: exam.Schedule_Date,
            deadline: exam.Deadline,
            duration: exam.Duration,
            passing_score: exam.Passing_Score,
            status: 'Published',
            is_randomized: exam.Is_Randomized
        });
        if (res.data.success) {
            showAlert('Exam published successfully', 'success');
            loadExams();
        } else {
            showAlert(res.data.message || 'Failed to publish exam', 'error');
        }
    } catch (err) {
        showAlert(err.response?.data?.message || 'Error publishing exam', 'error');
    }
}

function bindExamCardActions() {
    const container = document.getElementById('examsContainer');
    if (!container || container.dataset.actionsBound === 'true') {
        return;
    }

    container.addEventListener('click', function(e) {
        const button = e.target.closest('[data-exam-action]');
        if (!button) return;

        const action = button.dataset.examAction;
        const examId = parseInt(button.dataset.examId, 10);
        if (!examId) return;

        e.preventDefault();

        switch (action) {
            case 'edit':
                editExam(examId);
                break;
            case 'copy-code':
                copyExamCode(examId);
                break;
            case 'reset-code':
                resetExamCode(examId);
                break;
            case 'toggle-review':
                toggleResponseReview(examId);
                break;
            case 'view-responses':
                window.location.href = `exam-responses.php?exam_id=${examId}`;
                break;
            case 'archive':
                archiveExam(examId);
                break;
            case 'restore':
                restoreExam(examId);
                break;
            case 'close':
                closeExam(examId);
                break;
            case 'publish':
                publishExamQuick(examId);
                break;
            case 'reopen':
                reopenExam(examId);
                break;
            case 'delete':
                deleteExam(examId);
                break;
            default:
                break;
        }
    });

    container.dataset.actionsBound = 'true';
}

function manageExamDetails(examId) {
    // This would open a modal or navigate to manage questions and batches
    editExam(examId);
}

function badgeForStatus(status, isArchived) {
    if (String(isArchived) === '1') return 'bg-dark text-white';
    if (status === 'Published') return 'bg-success text-white';
    if (status === 'Closed') return 'bg-secondary text-white';
    return 'bg-warning text-dark';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

// Reopen a closed exam
async function reopenExam(examId) {
    // Get exam details first
    const exam = exams.find(e => e.Exam_ID === examId);
    if (!exam) return;
    
    const result = await Swal.fire({
        title: 'Reopen Exam',
        html: `
            <p>Reopen "<strong>${escapeHtml(exam.Title)}</strong>" for students?</p>
            <div class="text-start mt-3">
                <label class="form-label">New Deadline (optional)</label>
                <input type="datetime-local" id="newDeadline" class="form-control" 
                       value="${exam.Deadline ? new Date(exam.Deadline).toISOString().slice(0, 16) : ''}">
                <small class="text-muted">Leave unchanged or set a new deadline</small>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Reopen',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#28a745',
        preConfirm: () => {
            return {
                deadline: document.getElementById('newDeadline').value
            };
        }
    });
    
    if (!result.isConfirmed) return;
    
    try {
        const updateData = {
            action: 'update',
            exam_id: examId,
            subject_id: exam.Subject_ID,
            title: exam.Title,
            description: exam.Description,
            schedule_date: exam.Schedule_Date,
            deadline: result.value.deadline || exam.Deadline,
            duration: exam.Duration,
            passing_score: exam.Passing_Score,
            status: 'Published',
            is_randomized: exam.Is_Randomized
        };
        
        const response = await axios.post(EXAMS_API, updateData);
        
        if (response.data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Exam Reopened',
                text: 'The exam is now available to students again',
                timer: 2000,
                showConfirmButton: false
            });
            loadExams();
        }
    } catch (error) {
        console.error('Error reopening exam:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.response?.data?.message || 'Failed to reopen exam'
        });
    }
}

// Load questions from question bank (optional server-side filters)
function loadQuestions(filters = {}) {
    let url = `${QUESTION_BANK_API}?action=list`;
    if (filters.course_id) url += `&course_id=${encodeURIComponent(filters.course_id)}`;
    if (filters.subject_id) url += `&subject_id=${encodeURIComponent(filters.subject_id)}`;
    if (filters.search) url += `&search=${encodeURIComponent(filters.search)}`;

    return axios.get(url)
        .then(res => {
            if (res.data.success) {
                allQuestions = res.data.questions || [];
                return allQuestions;
            }
            allQuestions = [];
            return [];
        })
        .catch(err => {
            console.error('Error loading questions:', err);
            allQuestions = [];
            return [];
        });
}

function getQuestionPickerFilters() {
    return {
        course_id: document.getElementById('questionCourseFilter')?.value || '',
        subject_id: document.getElementById('questionSubjectFilter')?.value || '',
        search: document.getElementById('questionSearchInput')?.value.trim() || ''
    };
}

function updateQuestionPickerSummary(count) {
    const summary = document.getElementById('questionPickerSummary');
    if (!summary) return;
    const filters = getQuestionPickerFilters();
    const parts = [`${count} question${count === 1 ? '' : 's'} available`];
    if (filters.course_id) {
        const course = courses.find(c => String(c.Course_ID) === String(filters.course_id));
        if (course) parts.push(course.Course_Name);
    }
    if (filters.subject_id) {
        const subject = Object.values(subjectsByCourse).flat()
            .find(s => String(s.Subject_ID) === String(filters.subject_id));
        if (subject) parts.push(subject.Subject_Name);
    }
    summary.textContent = parts.join(' • ');
}

function reloadQuestionPickerList() {
    return loadQuestions(getQuestionPickerFilters()).then(questions => {
        updateQuestionPickerSummary(questions.length);
        renderQuestionList(questions);
    });
}

// Load all batches
function loadBatches() {
    return axios.get(`${EXAM_BATCHES_API}?action=list`)
        .then(res => {
            if (res.data.success) {
                allBatches = res.data.data || [];
                return allBatches;
            }
            return [];
        })
        .catch(err => {
            console.error('Error loading batches:', err);
            return [];
        });
}

// Render question list in selection modal
function renderQuestionList(questions) {
    const container = document.getElementById('questionListContainer');
    if (!container) return;

    if (!questions || questions.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-4">No questions found</div>';
        return;
    }

    // Group questions by course + subject
    const grouped = {};
    questions.forEach(q => {
        const groupKey = `${q.Course_Name || 'Unknown Course'} → ${q.Subject_Name || 'Unknown Subject'}`;
        if (!grouped[groupKey]) {
            grouped[groupKey] = [];
        }
        grouped[groupKey].push(q);
    });

    let html = '';
    Object.keys(grouped).sort().forEach(groupKey => {
        html += `<div class="mb-3">
            <h6 class="text-primary mb-2">${escapeHtml(groupKey)}</h6>`;
        
        grouped[groupKey].forEach(q => {
            const isChecked = selectedQuestionIds.includes(q.Question_ID);
            const usageCount = parseInt(q.exam_usage_count, 10) || 0;
            html += `
                <div class="form-check mb-2">
                    <input class="form-check-input question-checkbox" type="checkbox" 
                           value="${q.Question_ID}" id="q_${q.Question_ID}"
                           ${isChecked ? 'checked' : ''}>
                    <label class="form-check-label" for="q_${q.Question_ID}">
                        ${escapeHtml(q.Question_Text)}
                        ${usageCount > 0 ? `<small class="text-muted"> (used in ${usageCount} exam${usageCount === 1 ? '' : 's'})</small>` : ''}
                    </label>
                </div>`;
        });
        
        html += '</div>';
    });

    container.innerHTML = html;

    // Add event listeners to checkboxes
    document.querySelectorAll('.question-checkbox').forEach(cb => {
        cb.addEventListener('change', function() {
            const id = parseInt(this.value, 10);
            if (this.checked) {
                if (!selectedQuestionIds.includes(id)) {
                    selectedQuestionIds.push(id);
                }
            } else {
                selectedQuestionIds = selectedQuestionIds.filter(qId => qId !== id);
            }
            updateQuestionSelectionCount();
        });
    });

    updateQuestionSelectionCount();
}

// Update question selection count
function updateQuestionSelectionCount() {
    const checkboxes = document.querySelectorAll('.question-checkbox:checked');
    const count = checkboxes.length;
    const countDisplay = document.getElementById('selectedQuestionCount');
    if (countDisplay) {
        countDisplay.textContent = `${count} selected`;
    }
}

// Filter questions in picker modal (server-side reload)
function filterQuestionsBySubject() {
    reloadQuestionPickerList();
}

function searchQuestions() {
    reloadQuestionPickerList();
}

function populateQuestionCourseFilter() {
    const filter = document.getElementById('questionCourseFilter');
    if (!filter) return;

    filter.innerHTML = '<option value="">All Courses</option>';
    courses.forEach(course => {
        const opt = document.createElement('option');
        opt.value = course.Course_ID;
        opt.textContent = course.Course_Name;
        filter.appendChild(opt);
    });
}

function populateQuestionSubjectFilter(courseId) {
    const filter = document.getElementById('questionSubjectFilter');
    if (!filter) return;

    filter.innerHTML = '<option value="">All Subjects</option>';
    const list = courseId
        ? (subjectsByCourse[courseId] || [])
        : Object.values(subjectsByCourse).flat();

    list.forEach(subject => {
        const opt = document.createElement('option');
        opt.value = subject.Subject_ID;
        opt.textContent = `${subject.Subject_Name} (${subject.Subject_Code})`;
        filter.appendChild(opt);
    });
}

// Render batch checkboxes
function renderBatchSelection() {
    const container = document.getElementById('batchSelectionContainer');
    if (!container) return;

    if (!allBatches || allBatches.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-2">No batches available</div>';
        return;
    }

    let html = '';
    allBatches.forEach(b => {
        const isChecked = selectedBatchIds.includes(b.Batch_ID);
        html += `
            <div class="form-check mb-2">
                <input class="form-check-input batch-checkbox" type="checkbox" 
                       value="${b.Batch_ID}" id="b_${b.Batch_ID}"
                       ${isChecked ? 'checked' : ''}>
                <label class="form-check-label" for="b_${b.Batch_ID}">
                    ${escapeHtml(b.Batch_Name)} - ${escapeHtml(b.Course_Name)}
                </label>
            </div>`;
    });

    container.innerHTML = html;

    // Add event listeners to checkboxes
    document.querySelectorAll('.batch-checkbox').forEach(cb => {
        cb.addEventListener('change', updateBatchSelectionCount);
    });

    updateBatchSelectionCount();
}

// Update batch selection count
function updateBatchSelectionCount() {
    const checkboxes = document.querySelectorAll('.batch-checkbox:checked');
    const count = checkboxes.length;
    const countDisplay = document.getElementById('batchCount');
    if (countDisplay) {
        countDisplay.textContent = count;
    }
}

// Load assigned questions for an exam
function loadExamQuestions(examId) {
    return axios.get(`${EXAM_QUESTIONS_API}?action=get&exam_id=${examId}`)
        .then(res => {
            if (res.data.success) {
                return res.data.data || [];
            }
            return [];
        })
        .catch(err => {
            console.error('Error loading exam questions:', err);
            return [];
        });
}

// Load assigned batches for an exam
function loadExamBatches(examId) {
    return axios.get(`${EXAM_BATCHES_API}?action=get&exam_id=${examId}`)
        .then(res => {
            if (res.data.success) {
                return res.data.data || [];
            }
            return [];
        })
        .catch(err => {
            console.error('Error loading exam batches:', err);
            return [];
        });
}

// Update question count display
function updateQuestionCountDisplay(count) {
    const countDisplay = document.getElementById('questionCount');
    if (countDisplay) {
        countDisplay.textContent = `${count} question${count !== 1 ? 's' : ''} assigned`;
        countDisplay.className = count > 0 ? 'badge bg-success text-white' : 'badge bg-warning text-dark';
    }
}

// Exam form submit
document.getElementById('examForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const examId = document.getElementById('examId').value;
    const payload = {
        subject_id: document.getElementById('examSubject').value,
        title: document.getElementById('examTitle').value,
        description: document.getElementById('examDescription').value,
        schedule_date: document.getElementById('examSchedule').value || null,
        deadline: document.getElementById('examDeadline').value || null,
        duration: document.getElementById('examDuration').value || null,
        passing_score: document.getElementById('examPassing').value || null,
        status: document.getElementById('examStatus').value,
        is_randomized: document.getElementById('examRandomized').checked ? 1 : 0
    };

    if (!payload.subject_id || !payload.title) {
        showAlert('Subject and title are required', 'error');
        return;
    }

    // Collect selected batch IDs
    const batchCheckboxes = document.querySelectorAll('.batch-checkbox:checked');
    const batchIds = Array.from(batchCheckboxes).map(cb => parseInt(cb.value));

    let action = 'add';
    if (examId) {
        action = 'update';
        payload.exam_id = examId;
    }
    payload.action = action;

    axios.post(EXAMS_API, payload)
        .then(res => {
            if (res.data.success) {
                const savedExamId = examId || res.data.exam_id;
                
                // Create promises for batch and question assignments
                const promises = [];
                
                // Assign batches if exam was saved successfully
                if (savedExamId && batchIds.length > 0) {
                    promises.push(
                        axios.post(EXAM_BATCHES_API, {
                            action: 'assign',
                            exam_id: savedExamId,
                            batch_ids: batchIds
                        })
                    );
                }
                
                // Assign questions if any were selected
                if (savedExamId && selectedQuestionIds.length > 0) {
                    promises.push(
                        axios.post(EXAM_QUESTIONS_API, {
                            action: 'assign',
                            exam_id: savedExamId,
                            question_ids: selectedQuestionIds
                        })
                    );
                }
                
                // Wait for all assignments to complete
                if (promises.length > 0) {
                    return Promise.all(promises).then(() => {
                        if (action === 'add' && res.data.exam_code) {
                            showExamCodeModal(res.data.exam_code, 'Exam Created');
                        } else {
                            showAlert(action === 'add' ? 'Exam added successfully' : 'Exam updated successfully', 'success');
                        }
                        bootstrap.Modal.getInstance(document.getElementById('examModal')).hide();
                        document.getElementById('examForm').reset();
                        document.getElementById('examId').value = '';
                        loadExams();
                    }).catch(err => {
                        console.error('Error assigning batches/questions:', err);
                        const errorMsg = err.response?.data?.message || 'Failed to assign batches or questions';
                        showAlert(errorMsg, 'error');
                    });
                } else {
                    if (action === 'add' && res.data.exam_code) {
                        showExamCodeModal(res.data.exam_code, 'Exam Created');
                    } else {
                        showAlert(action === 'add' ? 'Exam added successfully' : 'Exam updated successfully', 'success');
                    }
                    bootstrap.Modal.getInstance(document.getElementById('examModal')).hide();
                    document.getElementById('examForm').reset();
                    document.getElementById('examId').value = '';
                    loadExams();
                }
            } else {
                showAlert(res.data.message || 'Operation failed', 'error');
            }
        })
        .catch(err => {
            showAlert(err.response?.data?.message || 'Error saving exam', 'error');
        });
});

function editExam(examId) {
    const exam = exams.find(e => e.Exam_ID === examId);
    if (!exam) {
        showAlert('Exam not found in list', 'error');
        return;
    }
    
    // Set a flag to prevent reset on modal show
    const modal = document.getElementById('examModal');
    modal.dataset.editMode = 'true';
    
    currentExamId = examId;
    
    document.getElementById('examId').value = exam.Exam_ID;
    document.getElementById('examTitle').value = exam.Title || '';
    document.getElementById('examDescription').value = exam.Description || '';
    document.getElementById('examSchedule').value = exam.Schedule_Date ? exam.Schedule_Date.replace(' ', 'T') : '';
    document.getElementById('examDeadline').value = exam.Deadline ? exam.Deadline.replace(' ', 'T') : '';
    document.getElementById('examDuration').value = exam.Duration || '';
    document.getElementById('examPassing').value = exam.Passing_Score || '';
    document.getElementById('examStatus').value = exam.Status || 'Draft';
    document.getElementById('examRandomized').checked = !!exam.Is_Randomized;

    const examCourse = document.getElementById('examCourse');
    if (examCourse) {
        examCourse.value = exam.Course_ID;
        
        // Ensure subjects are loaded before populating
        if (subjectsByCourse[exam.Course_ID] && subjectsByCourse[exam.Course_ID].length > 0) {
            populateExamSubjectSelect(exam.Course_ID, exam.Subject_ID);
        } else {
            // If subjects not loaded yet, reload them
            axios.get(`${SUBJECTS_API}?action=list&course_id=${exam.Course_ID}`)
                .then(res => {
                    if (res.data.success) {
                        const list = res.data.data || [];
                        if (!subjectsByCourse[exam.Course_ID]) subjectsByCourse[exam.Course_ID] = [];
                        subjectsByCourse[exam.Course_ID] = list;
                        populateExamSubjectSelect(exam.Course_ID, exam.Subject_ID);
                    }
                })
                .catch(err => {
                    console.error('Error loading subjects:', err);
                });
        }
    }

    // Load assigned questions and update count
    loadExamQuestions(examId).then(questions => {
        selectedQuestionIds = questions.map(q => q.Question_ID);
        updateQuestionCountDisplay(questions.length);
    });

    // Load assigned batches and pre-select them
    loadExamBatches(examId).then(batches => {
        selectedBatchIds = batches.map(b => b.Batch_ID);
        renderBatchSelection();
    });

    document.getElementById('examModalLabel').textContent = 'Edit Exam';
    document.getElementById('examSubmitBtn').textContent = 'Save Changes';
    new bootstrap.Modal(modal).show();
}

function deleteExam(examId) {
    Swal.fire({
        title: 'Delete exam?',
        text: 'This action cannot be undone',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete',
        cancelButtonText: 'Cancel'
    }).then(result => {
        if (!result.isConfirmed) return;
        axios.post(EXAMS_API, { action: 'delete', exam_id: examId })
            .then(res => {
                if (res.data.success) {
                    showAlert('Exam deleted', 'success');
                    loadExams();
                } else {
                    showAlert(res.data.message || 'Failed to delete exam', 'error');
                }
            })
            .catch(err => showAlert(err.response?.data?.message || 'Error deleting exam', 'error'));
    });
}

// Filters
document.getElementById('filterCourse').addEventListener('change', function () {
    currentCourseFilter = this.value || '';
    // Update subject filter to only subjects under this course
    const filterSubject = document.getElementById('filterSubject');
    if (filterSubject) {
        filterSubject.innerHTML = '<option value="">All subjects</option>';
        const list = currentCourseFilter ? (subjectsByCourse[currentCourseFilter] || []) : Object.values(subjectsByCourse).flat();
        list.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.Subject_ID;
            opt.textContent = `${s.Subject_Name} (${s.Subject_Code})`;
            filterSubject.appendChild(opt);
        });
    }
    currentSubjectFilter = '';
    loadExams();
});

document.getElementById('filterSubject').addEventListener('change', function () {
    currentSubjectFilter = this.value || '';
    loadExams();
});

document.getElementById('filterExamStatus').addEventListener('change', function () {
    currentStatusFilter = this.value || '';
    if (currentStatusFilter === 'Archived') {
        loadExams();
    } else {
        renderExams();
    }
});

// When course in exam modal changes, update subject select
document.getElementById('examCourse').addEventListener('change', function () {
    const courseId = this.value;
    
    // If subjects for this course are already loaded, populate immediately
    if (subjectsByCourse[courseId] && subjectsByCourse[courseId].length > 0) {
        populateExamSubjectSelect(courseId);
    } else if (courseId) {
        // Otherwise, fetch subjects for this course
        axios.get(`${SUBJECTS_API}?action=list&course_id=${courseId}`)
            .then(res => {
                if (res.data.success) {
                    const list = res.data.data || [];
                    subjectsByCourse[courseId] = list;
                    populateExamSubjectSelect(courseId);
                }
            })
            .catch(err => {
                console.error('Error loading subjects:', err);
                showAlert('Failed to load subjects', 'error');
            });
    } else {
        populateExamSubjectSelect('');
    }
});

// Auto-calculate deadline when schedule date changes
document.getElementById('examSchedule').addEventListener('change', function() {
    const scheduleDate = this.value;
    const deadlineInput = document.getElementById('examDeadline');
    
    // Only auto-fill if deadline is empty
    if (scheduleDate && !deadlineInput.value) {
        const schedule = new Date(scheduleDate);
        // Default: 24 hours after schedule date
        schedule.setHours(schedule.getHours() + 24);
        
        // Format for datetime-local input
        const year = schedule.getFullYear();
        const month = String(schedule.getMonth() + 1).padStart(2, '0');
        const day = String(schedule.getDate()).padStart(2, '0');
        const hours = String(schedule.getHours()).padStart(2, '0');
        const minutes = String(schedule.getMinutes()).padStart(2, '0');
        
        deadlineInput.value = `${year}-${month}-${day}T${hours}:${minutes}`;
    }
});

// Validate deadline is after schedule date
document.getElementById('examDeadline').addEventListener('change', function() {
    const scheduleDate = document.getElementById('examSchedule').value;
    const deadline = this.value;
    
    if (scheduleDate && deadline) {
        const schedule = new Date(scheduleDate);
        const deadlineDate = new Date(deadline);
        
        if (deadlineDate <= schedule) {
            Swal.fire({
                icon: 'warning',
                title: 'Invalid Deadline',
                text: 'Deadline must be after the schedule date',
                confirmButtonColor: '#2563eb'
            });
            this.value = '';
        }
    }
});

// Modal show: reset to Add mode (only if not in edit mode)
document.getElementById('examModal').addEventListener('show.bs.modal', function (e) {
    const modal = e.target;
    
    // Skip reset if opening for edit
    if (modal.dataset.editMode === 'true') {
        delete modal.dataset.editMode;
        return;
    }
    
    // Reset for add mode
    document.getElementById('examForm').reset();
    document.getElementById('examId').value = '';
    document.getElementById('examModalLabel').textContent = 'Add New Exam';
    document.getElementById('examSubmitBtn').textContent = 'Add Exam';
    
    currentExamId = null;
    selectedQuestionIds = [];
    selectedBatchIds = [];
    
    updateQuestionCountDisplay(0);
    renderBatchSelection();
});

document.getElementById('manageQuestionsBtn').addEventListener('click', function() {
    const courseFilter = document.getElementById('questionCourseFilter');
    const subjectFilter = document.getElementById('questionSubjectFilter');
    const searchInput = document.getElementById('questionSearchInput');
    if (courseFilter) courseFilter.value = '';
    if (subjectFilter) subjectFilter.value = '';
    if (searchInput) searchInput.value = '';
    populateQuestionSubjectFilter('');

    reloadQuestionPickerList().then(() => {
        new bootstrap.Modal(document.getElementById('questionSelectionModal')).show();
    });
});

// Question course filter change handler
document.getElementById('questionCourseFilter')?.addEventListener('change', function() {
    populateQuestionSubjectFilter(this.value || '');
    const subjectFilter = document.getElementById('questionSubjectFilter');
    if (subjectFilter) subjectFilter.value = '';
    reloadQuestionPickerList();
});

// Question subject filter change handler
document.getElementById('questionSubjectFilter')?.addEventListener('change', function() {
    reloadQuestionPickerList();
});

// Question search input handler
document.getElementById('questionSearchInput')?.addEventListener('input', debounce(function() {
    reloadQuestionPickerList();
}, 400));

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func.apply(this, args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Save Question Selection button handler
document.getElementById('saveQuestionSelectionBtn').addEventListener('click', function() {
    const checkboxes = document.querySelectorAll('.question-checkbox:checked');
    const questionIds = Array.from(checkboxes).map(cb => parseInt(cb.value));
    
    if (!currentExamId) {
        // Store selection for new exam (will be saved when exam is created)
        selectedQuestionIds = questionIds;
        updateQuestionCountDisplay(questionIds.length);
        bootstrap.Modal.getInstance(document.getElementById('questionSelectionModal')).hide();
        return;
    }
    
    // Save question assignment for existing exam
    axios.post(EXAM_QUESTIONS_API, {
        action: 'assign',
        exam_id: currentExamId,
        question_ids: questionIds
    })
    .then(res => {
        if (res.data.success) {
            selectedQuestionIds = questionIds;
            updateQuestionCountDisplay(questionIds.length);
            bootstrap.Modal.getInstance(document.getElementById('questionSelectionModal')).hide();
            showAlert('Questions assigned successfully', 'success');
            loadExams(); // Refresh exam list to update counts
        } else {
            showAlert(res.data.message || 'Failed to assign questions', 'error');
        }
    })
    .catch(err => {
        showAlert(err.response?.data?.message || 'Error assigning questions', 'error');
    });
});

// Dropdown toggle handler for Masterfiles menu
document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
    toggle.addEventListener('click', function(e) {
        e.preventDefault();
        const submenu = this.nextElementSibling;
        const isActive = this.classList.toggle('active');
        if (isActive) {
            submenu.classList.add('show');
        } else {
            submenu.classList.remove('show');
        }
    });
});

// Handle submenu link clicks - prevent dropdown from closing
document.querySelectorAll('.submenu a[href]').forEach(link => {
    link.addEventListener('click', function(e) {
        e.stopPropagation();
        const href = this.getAttribute('href');
        window.location.href = href;
    });
});

// Logout handler
document.getElementById('logoutBtn').addEventListener('click', function(e) {
    e.preventDefault();
    Swal.fire({
        title: 'Logout',
        text: 'Are you sure you want to logout?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, logout',
        cancelButtonText: 'No, stay',
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#6b7280'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../../api/auth/logout.php', {
                method: 'POST'
            }).then(() => {
                window.location.href = '../../login.php';
            }).catch(() => {
                window.location.href = '../../login.php';
            });
        }
    });
});

// Init
loadCoursesAndSubjects();
bindExamCardActions();
loadExams();
loadBatches();

