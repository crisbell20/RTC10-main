// Exam Taking JavaScript
let sessionId = null;
let examData = null;
let questions = [];
let currentQuestionIndex = 0;
let answers = {};
let timerInterval = null;
let startTime = null;
let examSubmitted = false;
let proctoringActive = false;
let fullscreenEnforced = false;
const lastViolationLog = {};

const LOG_CHEATING_API = '../../api/examinee/log-cheating.php';
const VIOLATION_COOLDOWN_MS = 3000;

// Get session ID from URL
const urlParams = new URLSearchParams(window.location.search);
sessionId = urlParams.get('session_id');

if (!sessionId) {
    Swal.fire({
        icon: 'error',
        title: 'Invalid Session',
        text: 'No exam session found. Redirecting to dashboard...',
        timer: 3000,
        showConfirmButton: false
    }).then(() => {
        window.location.href = '../examinee/dashboard.php';
    });
}

// Load exam questions
async function loadExam() {
    try {
        const response = await axios.get(`../../api/examinee/exam-questions.php?action=get_session_questions&session_id=${sessionId}`);
        
        if (response.data.success) {
            examData = response.data.data;
            questions = examData.questions;
            
            if (questions.length === 0) {
                throw new Error('No questions found for this exam');
            }
            
            // Check if exam time has expired
            if (examData.time_expired) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Time Expired',
                    text: 'The time limit for this exam has expired. Submitting your answers...',
                    allowOutsideClick: false,
                    showConfirmButton: false
                });
                
                // Auto-submit the exam
                setTimeout(() => {
                    submitExam(true);
                }, 2000);
                return;
            }
            
            initializeExam();
            renderQuestion(0);
            startTimer();

            document.getElementById('loadingOverlay').style.display = 'none';
            document.getElementById('examContainer').style.display = 'block';

            initProctoring();
            enterProctorMode();
        }
    } catch (error) {
        console.error('Error loading exam:', error);
        Swal.fire({
            icon: 'error',
            title: 'Failed to Load Exam',
            text: error.response?.data?.message || 'Could not load exam questions',
            confirmButtonText: 'Go to Dashboard'
        }).then(() => {
            window.location.href = '../examinee/dashboard.php';
        });
    }
}

// Initialize exam UI
function initializeExam() {
    document.getElementById('totalQuestions').textContent = questions.length;
    document.getElementById('totalQuestionsNav').textContent = questions.length;
    
    // Display exam title
    if (examData.exam_title) {
        document.getElementById('examTitle').textContent = examData.exam_title;
    }
    
    // Display deadline information
    if (examData.time_started && examData.duration) {
        const startTime = new Date(examData.time_started);
        const deadline = new Date(startTime.getTime() + (examData.duration * 60 * 1000));
        const deadlineStr = deadline.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
        document.getElementById('deadlineText').textContent = `Must finish by ${deadlineStr}`;
    }
    
    updateProgress();
}

// Render a question
function renderQuestion(index) {
    if (index < 0 || index >= questions.length) return;
    
    currentQuestionIndex = index;
    const question = questions[index];
    
    const container = document.getElementById('questionsContainer');
    container.innerHTML = `
        <div class="question-card">
            <div class="d-flex align-items-start mb-4">
                <span class="question-number">${index + 1}</span>
                <div class="flex-grow-1">
                    <h5 class="mb-2">${escapeHtml(question.Question_Text)}</h5>
                    <small class="text-muted">
                        <i class="bi bi-bookmark"></i> ${escapeHtml(question.Subject_Name)}
                    </small>
                </div>
            </div>
            
            <div class="choices">
                ${question.answers.map((answer, idx) => `
                    <label class="choice-option ${answers[question.Question_ID] == answer.Choice_ID ? 'selected' : ''}" 
                           onclick="selectAnswer(${question.Question_ID}, ${answer.Choice_ID})">
                        <input type="radio" 
                               name="question_${question.Question_ID}" 
                               value="${answer.Choice_ID}"
                               ${answers[question.Question_ID] == answer.Choice_ID ? 'checked' : ''}>
                        <span>${String.fromCharCode(65 + idx)}. ${escapeHtml(answer.Choice_Text)}</span>
                    </label>
                `).join('')}
            </div>
        </div>
    `;
    
    // Update navigation
    document.getElementById('currentQuestionNum').textContent = index + 1;
    document.getElementById('prevBtn').disabled = index === 0;
    
    if (index === questions.length - 1) {
        document.getElementById('nextBtn').style.display = 'none';
        document.getElementById('submitBtn').style.display = 'block';
    } else {
        document.getElementById('nextBtn').style.display = 'block';
        document.getElementById('submitBtn').style.display = 'none';
    }
}

// Select an answer
async function selectAnswer(questionId, choiceId) {
    answers[questionId] = choiceId;
    
    // Update UI
    const choices = document.querySelectorAll('.choice-option');
    choices.forEach(choice => choice.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
    
    // Save answer to server
    try {
        await axios.post('../../api/examinee/submit-answer.php', {
            session_id: sessionId,
            question_id: questionId,
            choice_id: choiceId
        });
        
        updateProgress();
    } catch (error) {
        console.error('Error saving answer:', error);
        // Don't show error to user, just log it
    }
}

// Update progress
function updateProgress() {
    const answeredCount = Object.keys(answers).length;
    const percentage = (answeredCount / questions.length) * 100;
    
    document.getElementById('answeredCount').textContent = answeredCount;
    document.getElementById('progressBar').style.width = percentage + '%';
    
    // Enable/disable submit button based on completion
    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn) {
        if (answeredCount === questions.length) {
            submitBtn.disabled = false;
            submitBtn.classList.remove('btn-secondary');
            submitBtn.classList.add('btn-success');
            submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Submit Exam';
        } else {
            submitBtn.disabled = true;
            submitBtn.classList.remove('btn-success');
            submitBtn.classList.add('btn-secondary');
            submitBtn.innerHTML = `<i class="bi bi-lock"></i> Answer All Questions (${answeredCount}/${questions.length})`;
        }
    }
}

// Navigation
function nextQuestion() {
    if (currentQuestionIndex < questions.length - 1) {
        renderQuestion(currentQuestionIndex + 1);
    }
}

function previousQuestion() {
    if (currentQuestionIndex > 0) {
        renderQuestion(currentQuestionIndex - 1);
    }
}

// Timer
function startTimer() {
    // Calculate elapsed time from session start
    const sessionStart = new Date(examData.time_started).getTime();
    const duration = examData.duration * 60 * 1000; // Duration in milliseconds
    
    timerInterval = setInterval(() => {
        const now = Date.now();
        const elapsed = now - sessionStart;
        const remaining = duration - elapsed;
        
        // If time expired, auto-submit
        if (remaining <= 0) {
            clearInterval(timerInterval);
            Swal.fire({
                icon: 'warning',
                title: 'Time Expired',
                text: 'The time limit has been reached. Submitting your exam...',
                allowOutsideClick: false,
                showConfirmButton: false
            });
            setTimeout(() => {
                submitExam(true);
            }, 2000);
            return;
        }
        
        // Display remaining time
        const hours = Math.floor(remaining / 3600000);
        const minutes = Math.floor((remaining % 3600000) / 60000);
        const seconds = Math.floor((remaining % 60000) / 1000);
        
        const timeString = `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
        document.getElementById('timer').textContent = timeString;
        
        // Warning when less than 10 minutes remaining
        if (remaining < 600000) { // 10 minutes
            document.getElementById('timer').classList.add('warning');
        }
        
        // Show warning at 5 minutes
        if (remaining < 300000 && remaining > 299000) {
            Swal.fire({
                icon: 'warning',
                title: '5 Minutes Remaining',
                text: 'You have 5 minutes left to complete the exam.',
                timer: 3000,
                showConfirmButton: false
            });
        }
    }, 1000);
}

function pad(num) {
    return num.toString().padStart(2, '0');
}

// Submit exam
async function submitExam(autoSubmit = false) {
    const answeredCount = Object.keys(answers).length;
    const unanswered = questions.length - answeredCount;
    
    // If not auto-submit, check if all questions are answered
    if (!autoSubmit) {
        if (unanswered > 0) {
            Swal.fire({
                title: 'Incomplete Exam',
                html: `
                    <p>You must answer all questions before submitting.</p>
                    <p class="text-danger"><strong>${unanswered} question(s) remaining</strong></p>
                `,
                icon: 'warning',
                confirmButtonText: 'Continue Answering',
                confirmButtonColor: '#2563eb'
            });
            return;
        }
        
        // All questions answered, ask for confirmation
        const result = await Swal.fire({
            title: 'Submit Exam?',
            text: 'Are you sure you want to submit your exam? You cannot change your answers after submission.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Submit',
            cancelButtonText: 'Review Answers',
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d'
        });
        
        if (!result.isConfirmed) return;
    }
    
    try {
        clearInterval(timerInterval);
        
        const response = await axios.post('../../api/examinee/submit-exam.php', {
            session_id: sessionId
        });
        
        if (response.data.success) {
            examSubmitted = true;
            proctoringActive = false;
            const data = response.data.data;
            const canReview = data.can_review_responses === true;
            const resultId = data.result_id;

            Swal.fire({
                title: data.remarks === 'Passed' ? 'Congratulations!' : 'Exam Completed',
                html: `
                    <div class="text-center">
                        <h3 class="mb-3">${data.remarks}</h3>
                        <p class="mb-2">Score: <strong>${data.score} / ${data.total_questions}</strong></p>
                        <p class="mb-2">Percentage: <strong>${data.percentage}%</strong></p>
                        <p class="text-muted">Passing Score: ${data.passing_score}%</p>
                        ${!canReview ? '<p class="text-muted small mt-2"><i class="bi bi-lock"></i> Response review is disabled for this exam.</p>' : ''}
                    </div>
                `,
                icon: data.remarks === 'Passed' ? 'success' : 'info',
                confirmButtonText: canReview && resultId ? 'View Responses' : 'Go to Dashboard',
                showCancelButton: canReview && resultId,
                cancelButtonText: 'Go to Dashboard',
                allowOutsideClick: false
            }).then((swalResult) => {
                if (canReview && resultId && swalResult.isConfirmed) {
                    window.location.href = `result.php?result_id=${resultId}`;
                } else {
                    window.location.href = '../examinee/dashboard.php';
                }
            });
        }
    } catch (error) {
        console.error('Error submitting exam:', error);
        Swal.fire({
            icon: 'error',
            title: 'Submission Failed',
            text: error.response?.data?.message || 'Failed to submit exam. Please try again.'
        });
        
        // Only restart timer if not auto-submit
        if (!autoSubmit) {
            startTimer();
        }
    }
}

// Prevent accidental page close
window.addEventListener('beforeunload', (e) => {
    if (sessionId && timerInterval && !examSubmitted) {
        e.preventDefault();
        e.returnValue = '';
    }
});

// --- Proctoring: fullscreen enforcement & tab detection ---

function isFullscreen() {
    return !!(document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement);
}

async function requestExamFullscreen() {
    const el = document.documentElement;
    try {
        if (el.requestFullscreen) {
            await el.requestFullscreen();
        } else if (el.webkitRequestFullscreen) {
            await el.webkitRequestFullscreen();
        } else if (el.msRequestFullscreen) {
            await el.msRequestFullscreen();
        } else {
            return false;
        }
        return isFullscreen();
    } catch (err) {
        console.warn('Fullscreen request failed:', err);
        return false;
    }
}

function canLogViolation(type) {
    const now = Date.now();
    const last = lastViolationLog[type] || 0;
    if (now - last < VIOLATION_COOLDOWN_MS) {
        return false;
    }
    lastViolationLog[type] = now;
    return true;
}

async function logCheatingEvent(type, detail = '') {
    if (!sessionId || examSubmitted) return;

    try {
        await axios.post(LOG_CHEATING_API, {
            session_id: sessionId,
            type,
            detail
        });
    } catch (err) {
        console.error('Failed to log proctoring event:', err);
    }
}

async function enterProctorMode() {
    const entered = await requestExamFullscreen();
    fullscreenEnforced = entered;
    proctoringActive = true;

    if (!entered) {
        if (canLogViolation('FULLSCREEN_DENIED')) {
            logCheatingEvent('FULLSCREEN_DENIED', 'Initial fullscreen request failed');
        }

        const result = await Swal.fire({
            icon: 'warning',
            title: 'Fullscreen Required',
            html: `
                <p>This exam must be taken in <strong>fullscreen mode</strong>.</p>
                <p class="text-muted small">Exiting fullscreen or switching tabs during the exam will be recorded as a violation.</p>
            `,
            confirmButtonText: 'Enter Fullscreen',
            allowOutsideClick: false,
            allowEscapeKey: false
        });

        if (result.isConfirmed) {
            const retry = await requestExamFullscreen();
            fullscreenEnforced = retry;
            if (!retry && canLogViolation('FULLSCREEN_DENIED')) {
                logCheatingEvent('FULLSCREEN_DENIED', 'User could not enter fullscreen after prompt');
            }
        }
    }
}

function showProctorWarning(title, message) {
    Swal.fire({
        icon: 'warning',
        title,
        html: message,
        confirmButtonText: isFullscreen() ? 'Continue Exam' : 'Return to Fullscreen',
        allowOutsideClick: false
    }).then(async (result) => {
        if (result.isConfirmed && !isFullscreen() && !examSubmitted) {
            await requestExamFullscreen();
        }
    });
}

function handleFullscreenChange() {
    if (!proctoringActive || examSubmitted) return;

    if (!isFullscreen()) {
        if (canLogViolation('FULLSCREEN_EXIT')) {
            logCheatingEvent('FULLSCREEN_EXIT', 'Trainee exited fullscreen during exam');
        }
        showProctorWarning(
            'Fullscreen Violation',
            '<p>You exited fullscreen mode. This has been <strong>recorded</strong>.</p><p>Return to fullscreen to continue your exam.</p>'
        );
    }
}

function handleVisibilityChange() {
    if (!proctoringActive || examSubmitted) return;

    if (document.hidden) {
        if (canLogViolation('TAB_SWITCH')) {
            logCheatingEvent('TAB_SWITCH', 'Exam page hidden — possible tab switch');
        }
        showProctorWarning(
            'Tab Switch Detected',
            '<p>Leaving the exam tab is not allowed. This incident has been <strong>recorded</strong>.</p><p>Return to the exam immediately.</p>'
        );
    }
}

function handleWindowBlur() {
    if (!proctoringActive || examSubmitted || document.hidden) return;

    if (canLogViolation('WINDOW_BLUR')) {
        logCheatingEvent('WINDOW_BLUR', 'Exam window lost focus');
    }
}

function initProctoring() {
    document.addEventListener('fullscreenchange', handleFullscreenChange);
    document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
    document.addEventListener('MSFullscreenChange', handleFullscreenChange);
    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('blur', handleWindowBlur);
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

// Initialize
loadExam();
