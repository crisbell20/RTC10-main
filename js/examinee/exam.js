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

const LOG_CHEATING_API = '../../api/examinee/log-cheating.php';
const LOG_CONNECTION_API = '../../api/examinee/log-connection.php';
const EXAM_PING_API = '../../api/examinee/exam-questions.php?action=ping';
const EXAM_OCCUPANCY_RELEASE_API = '../../api/examinee/exam-questions.php?action=release_occupancy';
const CONNECTIVITY_HEARTBEAT_MS = 10000;
const CONNECTIVITY_PING_TIMEOUT_MS = 4000;
const CONNECTIVITY_RESTORE_DEBOUNCE_MS = 800;
const CONNECTION_RESTORED_BANNER_MS = 2500;

let isOffline = false;
let pendingAnswer = null;
let pendingAutoSubmit = false;
let pendingCheatingEvents = [];
let examOfflineSince = null;
let connectivityHeartbeatTimer = null;
let restoreDebounceTimer = null;
let connectivityCheckInFlight = false;
let missedPings = 0;
let restoredBannerTimer = null;

/** Short batch when tab hide / blur is already known. */
const FOCUS_COALESCE_FAST_MS = 60;
/** Wait for visibilitychange after fullscreen drop before calling it a fullscreen-only exit. */
const FOCUS_COALESCE_FULLSCREEN_MS = 320;
const FOCUS_COALESCE_DEFAULT_MS = 100;

function isLikelyMobileExamClient() {
    const ua = navigator.userAgent || '';
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(ua);
}

function isTouchExamNav() {
    return isLikelyMobileExamClient()
        || window.matchMedia('(max-width: 991.98px)').matches;
}

let proctorWarningShowing = false;
let proctorFullscreenGraceUntil = 0;

let focusViolationCoalesceTimer = null;
const pendingFocusViolation = {
    pageHidden: false,
    windowBlur: false,
    fullscreenExit: false,
};

function resetPendingFocusViolation() {
    pendingFocusViolation.pageHidden = false;
    pendingFocusViolation.windowBlur = false;
    pendingFocusViolation.fullscreenExit = false;
}

function clearFocusViolationCoalescing() {
    if (focusViolationCoalesceTimer !== null) {
        clearTimeout(focusViolationCoalesceTimer);
        focusViolationCoalesceTimer = null;
    }
    resetPendingFocusViolation();
}

function focusCoalesceDelayMs() {
    if (pendingFocusViolation.pageHidden || document.hidden) {
        return FOCUS_COALESCE_FAST_MS;
    }
    if (pendingFocusViolation.fullscreenExit && !pendingFocusViolation.windowBlur) {
        return FOCUS_COALESCE_FULLSCREEN_MS;
    }
    return FOCUS_COALESCE_DEFAULT_MS;
}

function queueFocusViolation(kind) {
    if (!proctoringActive || examSubmitted) {
        return;
    }
    if (kind === 'pageHidden') pendingFocusViolation.pageHidden = true;
    if (kind === 'windowBlur') pendingFocusViolation.windowBlur = true;
    if (kind === 'fullscreenExit') pendingFocusViolation.fullscreenExit = true;

    if (focusViolationCoalesceTimer !== null) {
        clearTimeout(focusViolationCoalesceTimer);
    }
    focusViolationCoalesceTimer = setTimeout(
        emitCoalescedFocusViolation,
        focusCoalesceDelayMs()
    );
}

function reportFocusPageHidden(snapshot) {
    const hidden = snapshot.pageHidden || document.hidden;
    if (!hidden) {
        return false;
    }

    if (isLikelyMobileExamClient()) {
        reportViolation(
            'APP_BACKGROUND',
            'App Moved to Background',
            'The exam was moved to the background.',
            null,
            { warn: true }
        );
    } else {
        reportViolation(
            'TAB_SWITCH',
            'Tab Switch Detected',
            'Leaving the exam tab is not allowed.',
            null,
            { warn: true }
        );
    }
    return true;
}

/**
 * Browsers often exit fullscreen when the tab is hidden. Coalesce related signals
 * so we log TAB_SWITCH (or APP_BACKGROUND), not a misleading FULLSCREEN_EXIT.
 */
function emitCoalescedFocusViolation() {
    focusViolationCoalesceTimer = null;
    const snapshot = { ...pendingFocusViolation };
    resetPendingFocusViolation();

    if (!proctoringActive || examSubmitted) {
        return;
    }

    if (reportFocusPageHidden(snapshot)) {
        return;
    }

    if (snapshot.windowBlur) {
        reportViolation(
            'VISIBILITY_HIDDEN',
            'Exam Page Hidden',
            'The exam page is no longer in focus.',
            null,
            { warn: true }
        );
        return;
    }

    if (snapshot.fullscreenExit && !isFullscreen()) {
        if (reportFocusPageHidden({ pageHidden: document.hidden })) {
            return;
        }
        if (isTouchExamNav() && Date.now() < proctorFullscreenGraceUntil) {
            return;
        }
        reportViolation(
            'FULLSCREEN_EXIT',
            'Exited Fullscreen Detected',
            'You exited fullscreen mode.'
        );
    }
}

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
        window.location.href = 'dashboard.php';
    });
}

let occupancyToken = '';
let occupancyReleased = false;

function occupancyStorageKey() {
    return `rtc10_exam_occ_${sessionId}`;
}

function getOccupancyToken() {
    if (occupancyToken) {
        return occupancyToken;
    }
    try {
        occupancyToken = sessionStorage.getItem(occupancyStorageKey()) || '';
    } catch (error) {
        occupancyToken = '';
    }
    if (!occupancyToken) {
        occupancyToken = (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function')
            ? crypto.randomUUID().replace(/-/g, '')
            : `${Date.now().toString(16)}${Math.random().toString(16).slice(2, 18)}`;
        occupancyToken = occupancyToken.replace(/[^A-Za-z0-9_-]/g, '').slice(0, 64);
        try {
            sessionStorage.setItem(occupancyStorageKey(), occupancyToken);
        } catch (error) {
            // Keep the in-memory token if storage is unavailable.
        }
    }
    return occupancyToken;
}

function occupancyReleaseUrl() {
    const token = getOccupancyToken();
    return `${EXAM_OCCUPANCY_RELEASE_API}&session_id=${encodeURIComponent(sessionId)}&occupancy_token=${encodeURIComponent(token)}`;
}

function occupancyPingUrl() {
    const token = getOccupancyToken();
    const qs = sessionId && token
        ? `&session_id=${encodeURIComponent(sessionId)}&occupancy_token=${encodeURIComponent(token)}`
        : '';
    return `${EXAM_PING_API}${qs}&t=${Date.now()}`;
}

function isOccupancyConflict(error) {
    return error?.response?.status === 409;
}

function showOccupancyBlockedAlert(error) {
    const message = error?.response?.data?.message
        || 'This exam is already open on another device.';
    return Swal.fire({
        icon: 'warning',
        title: 'Exam already open',
        text: message,
        confirmButtonText: 'OK',
        allowOutsideClick: false,
        allowEscapeKey: false,
    }).then(() => {
        goToAvailableExams();
    });
}

function releaseExamOccupancy() {
    if (!sessionId || examSubmitted || occupancyReleased) {
        return;
    }
    occupancyReleased = true;
    const url = occupancyReleaseUrl();
    const body = JSON.stringify({
        action: 'release_occupancy',
        session_id: sessionId,
        occupancy_token: getOccupancyToken(),
    });
    try {
        if (navigator.sendBeacon) {
            navigator.sendBeacon(url, new Blob([body], { type: 'text/plain;charset=UTF-8' }));
        }
    } catch (error) {
        // Keep going with fetch keepalive.
    }
    try {
        fetch(url, {
            method: 'POST',
            body,
            keepalive: true,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'text/plain;charset=UTF-8' },
        }).catch(() => {});
    } catch (error) {
        // Ignore unload-time fetch failures.
    }
    try {
        fetch(url, {
            method: 'GET',
            keepalive: true,
            credentials: 'same-origin',
            cache: 'no-store',
        }).catch(() => {});
    } catch (error) {
        // Ignore unload-time fetch failures.
    }
}

function isNetworkError(error) {
    if (!error) {
        return true;
    }
    return !error.response
        || error.code === 'ERR_NETWORK'
        || error.code === 'ECONNABORTED'
        || error.message === 'Network Error';
}

function browserThinksOnline() {
    return typeof navigator.onLine !== 'boolean' || navigator.onLine === true;
}

async function pingServer() {
    if (!browserThinksOnline()) {
        return false;
    }

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), CONNECTIVITY_PING_TIMEOUT_MS);
    try {
        const res = await fetch(occupancyPingUrl(), {
            method: 'GET',
            cache: 'no-store',
            credentials: 'same-origin',
            signal: controller.signal,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        return !!res;
    } catch (err) {
        return false;
    } finally {
        clearTimeout(timeoutId);
    }
}

function showConnectionBanner(state) {
    const banner = document.getElementById('offlineBanner');
    const text = document.getElementById('offlineBannerText');
    if (!banner || !text) {
        return;
    }

    if (restoredBannerTimer) {
        clearTimeout(restoredBannerTimer);
        restoredBannerTimer = null;
    }

    banner.hidden = false;
    banner.classList.remove('is-offline', 'is-restored');

    if (state === 'restored') {
        banner.classList.add('is-restored');
        text.textContent = 'Your connection has been restored';
        restoredBannerTimer = setTimeout(() => {
            banner.hidden = true;
            banner.classList.remove('is-restored');
            restoredBannerTimer = null;
        }, CONNECTION_RESTORED_BANNER_MS);
        return;
    }

    banner.classList.add('is-offline');
    text.textContent = 'You are currently offline';
}

function hideConnectionBanner() {
    const banner = document.getElementById('offlineBanner');
    if (restoredBannerTimer) {
        clearTimeout(restoredBannerTimer);
        restoredBannerTimer = null;
    }
    if (banner) {
        banner.hidden = true;
        banner.classList.remove('is-offline', 'is-restored');
    }
}

function syncNavLock() {
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');
    const answeredCount = Object.keys(answers).length;

    if (prevBtn) {
        prevBtn.disabled = isOffline || currentQuestionIndex === 0;
    }
    if (nextBtn) {
        nextBtn.disabled = isOffline;
    }
    if (submitBtn) {
        if (isOffline) {
            submitBtn.disabled = true;
        } else if (answeredCount === questions.length) {
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

function updateFullscreenButton() {
    const btn = document.getElementById('examFullscreenBtn');
    if (!btn) {
        return;
    }

    const on = isFullscreen();
    const label = btn.querySelector('.exam-fullscreen-btn-label');
    const icon = btn.querySelector('i');

    btn.classList.toggle('is-active', on);
    btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    btn.disabled = examSubmitted || on;
    btn.hidden = on;

    if (icon) {
        icon.className = 'bi bi-fullscreen';
    }
    if (label) {
        label.textContent = 'Enter fullscreen';
    }
}

async function enterExamFullscreenFromButton() {
    if (examSubmitted || isFullscreen()) {
        return;
    }

    proctorFullscreenGraceUntil = Date.now() + 2500;
    const entered = await requestExamFullscreen();
    fullscreenEnforced = entered;
    updateFullscreenButton();

    if (!entered) {
        await promptReturnToFullscreen();
    }
}

async function promptReturnToFullscreen() {
    if (examSubmitted || !proctoringActive || isFullscreen()) {
        return;
    }

    const result = await Swal.fire({
        icon: 'warning',
        title: 'Fullscreen Required',
        html: `
            <p>This exam must be taken in <strong>fullscreen mode</strong>.</p>
        `,
        confirmButtonText: 'Enter Fullscreen',
        allowOutsideClick: false,
        allowEscapeKey: false,
        heightAuto: true,
    });

    if (result.isConfirmed) {
        const retry = await requestExamFullscreen();
        fullscreenEnforced = retry;
        updateFullscreenButton();
    }
}

async function retryPendingAnswerSave() {
    if (!pendingAnswer || !sessionId) {
        return;
    }

    const { questionId, choiceId } = pendingAnswer;
    try {
        await axios.post('../../api/examinee/submit-answer.php', {
            session_id: sessionId,
            question_id: questionId,
            choice_id: choiceId,
            occupancy_token: getOccupancyToken(),
        });
        answers[questionId] = choiceId;
        pendingAnswer = null;
        updateProgress();
    } catch (error) {
        console.error('Error retrying answer save:', error);
        if (isNetworkError(error)) {
            setExamOffline();
            return;
        }
        if (isOccupancyConflict(error)) {
            await showOccupancyBlockedAlert(error);
        }
    }
}

async function reportExamDisconnectIfNeeded() {
    if (!examOfflineSince || !sessionId) {
        return;
    }

    const disconnectedAt = examOfflineSince;
    const restoredAt = Date.now();

    try {
        await axios.post(LOG_CONNECTION_API, {
            session_id: sessionId,
            disconnected_at: disconnectedAt,
            restored_at: restoredAt,
        });
        examOfflineSince = null;
    } catch (err) {
        console.warn('Could not record exam disconnect in audit log', err);
    }
}

function setExamOffline() {
    if (examSubmitted) {
        return;
    }

    isOffline = true;
    missedPings = 2;
    if (examOfflineSince == null) {
        examOfflineSince = Date.now();
    }
    document.body.classList.add('exam-offline');
    showConnectionBanner('offline');
    syncNavLock();
}

async function setExamOnline() {
    if (!isOffline || examSubmitted) {
        return;
    }
    if (!browserThinksOnline()) {
        return;
    }

    isOffline = false;
    missedPings = 0;
    document.body.classList.remove('exam-offline');
    showConnectionBanner('restored');

    await reportExamDisconnectIfNeeded();
    await retryPendingAnswerSave();
    await flushPendingCheatingEvents();

    if (isOffline) {
        return;
    }

    if (pendingAutoSubmit) {
        pendingAutoSubmit = false;
        syncNavLock();
        await submitExam(true);
        return;
    }

    syncNavLock();
    await new Promise((resolve) => setTimeout(resolve, CONNECTION_RESTORED_BANNER_MS));
    if (!isOffline) {
        await promptReturnToFullscreen();
    }
}

async function verifyConnection() {
    if (connectivityCheckInFlight || examSubmitted) {
        return;
    }

    connectivityCheckInFlight = true;
    try {
        const ok = browserThinksOnline() && await pingServer();
        if (ok) {
            missedPings = 0;
            await setExamOnline();
            await reportExamDisconnectIfNeeded();
        } else {
            missedPings += 1;
            if (missedPings >= 2 || (typeof navigator.onLine === 'boolean' && !navigator.onLine)) {
                setExamOffline();
            }
        }
    } finally {
        connectivityCheckInFlight = false;
    }
}

function scheduleRestoreCheck() {
    if (restoreDebounceTimer) {
        clearTimeout(restoreDebounceTimer);
    }
    restoreDebounceTimer = setTimeout(() => {
        restoreDebounceTimer = null;
        verifyConnection();
    }, CONNECTIVITY_RESTORE_DEBOUNCE_MS);
}

function stopConnectivityHeartbeat() {
    if (connectivityHeartbeatTimer) {
        clearInterval(connectivityHeartbeatTimer);
        connectivityHeartbeatTimer = null;
    }
    if (restoreDebounceTimer) {
        clearTimeout(restoreDebounceTimer);
        restoreDebounceTimer = null;
    }
    if (restoredBannerTimer) {
        clearTimeout(restoredBannerTimer);
        restoredBannerTimer = null;
    }
}

function initConnectivityMonitor() {
    window.addEventListener('offline', () => {
        if (restoreDebounceTimer) {
            clearTimeout(restoreDebounceTimer);
            restoreDebounceTimer = null;
        }
        setExamOffline();
    });

    window.addEventListener('online', () => {
        scheduleRestoreCheck();
    });

    stopConnectivityHeartbeat();
    connectivityHeartbeatTimer = setInterval(() => {
        if (examSubmitted) {
            stopConnectivityHeartbeat();
            return;
        }
        verifyConnection();
    }, CONNECTIVITY_HEARTBEAT_MS);

    if (typeof navigator.onLine === 'boolean' && !navigator.onLine) {
        setExamOffline();
    }
}

// Load exam questions
async function loadExam() {
    try {
        const response = await axios.get('../../api/examinee/exam-questions.php', {
            params: {
                action: 'get_session_questions',
                session_id: sessionId,
                occupancy_token: getOccupancyToken(),
            },
        });
        
        if (response.data.success) {
            examData = response.data.data;
            questions = examData.questions;
            
            if (questions.length === 0) {
                throw new Error('No questions found for this exam');
            }

            hydrateSavedAnswers(examData.saved_answers || {});
            
            // Check if exam time has expired
            if (examData.time_expired) {
                document.getElementById('loadingOverlay').style.display = 'none';
                await submitExam(true);
                return;
            }
            
            initializeExam();
            renderQuestion(findResumeQuestionIndex());
            startTimer();

            document.getElementById('loadingOverlay').style.display = 'none';
            document.getElementById('examContainer').style.display = 'block';

            initProctoring();
            initExamSwipeNavigation();
            initConnectivityMonitor();
            await enterProctorMode();
        }

        } catch (error) {
    console.error('Error loading exam:', error);

    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) {
        loadingOverlay.style.display = 'none';
        loadingOverlay.classList.add('d-none');
        loadingOverlay.hidden = true;
    }

    if (isOccupancyConflict(error)) {
        await showOccupancyBlockedAlert(error);
        return;
    }

    const message = error.response?.data?.message || 'Could not load exam questions';
    const expired = /expired/i.test(message);
    const locked =
        error.response?.status === 403 ||
        /exam session is locked/i.test(message);

    if (locked) {
        await Swal.fire({
            icon: 'error',
            title: 'Exam Locked',
            text: 'Your exam has been locked. Please contact administrator to unlock.',
            confirmButtonText: 'OK',
            allowOutsideClick: false,
            allowEscapeKey: false,
        });

        goToAvailableExams();
        return;
    }

    await Swal.fire({
        icon: expired ? 'warning' : 'error',
        title: expired ? 'Time Expired' : 'Failed to Load Exam',
        text: expired
            ? 'The time limit has been reached. Your exam has been submitted.'
            : message,
        confirmButtonText: 'OK',
        allowOutsideClick: false,
        allowEscapeKey: false,
    });

    goToAvailableExams();
}
}

// Initialize exam UI
function initializeExam() {
    document.getElementById('totalQuestions').textContent = questions.length;
    document.getElementById('totalQuestionsNav').textContent = questions.length;
    
    // Display exam title and subject / course
    if (examData.exam_title) {
        document.getElementById('examTitle').textContent = examData.exam_title;
    }

    const examInfoEl = document.getElementById('examInfo');
    if (examInfoEl) {
        const subjectName = examData.subject_name || questions[0]?.Subject_Name || '';
        const courseName = examData.course_name || '';
        const infoParts = [subjectName, courseName].filter(Boolean);
        examInfoEl.textContent = infoParts.length ? infoParts.join(' • ') : '';
    }
    
    // Display deadline information
    if (examData.time_started && examData.duration) {
        const startTime = parseExamDateTime(examData.time_started);
        const deadlineText = document.getElementById('deadlineText');
        if (startTime && deadlineText) {
            const deadline = new Date(startTime.getTime() + (examData.duration * 60 * 1000));
            const deadlineStr = deadline.toLocaleString('en-US', {
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });
            deadlineText.textContent = `Must finish by ${deadlineStr}`;
        }
    }
    
    updateProgress();
    applyExamWatermark();
    buildProgressSegments();
}

function buildProgressSegments() {
    const container = document.getElementById('progressBarSegments');
    if (!container || !questions.length) {
        return;
    }

    container.innerHTML = questions.map((_, index) => `
        <button type="button"
                class="exam-progress-segment unanswered"
                data-question-index="${index}"
                aria-label="Question ${index + 1}, unanswered"></button>
    `).join('');

    if (!container.dataset.boundClick) {
        container.addEventListener('click', (event) => {
            const segment = event.target.closest('[data-question-index]');
            if (!segment) {
                return;
            }
            goToQuestion(parseInt(segment.dataset.questionIndex, 10));
        });
        container.dataset.boundClick = '1';
    }

    updateProgressSegments();
}

function updateProgressSegments() {
    const container = document.getElementById('progressBarSegments');
    if (!container || !questions.length) {
        return;
    }

    const answeredCount = Object.keys(answers).length;
    container.setAttribute('aria-valuemax', String(questions.length));
    container.setAttribute('aria-valuenow', String(answeredCount));

    container.querySelectorAll('.exam-progress-segment').forEach((segment, index) => {
        const question = questions[index];
        const answered = question && isQuestionAnswered(question.Question_ID);

        segment.classList.toggle('answered', answered);
        segment.classList.toggle('unanswered', !answered);
        segment.classList.toggle('current', index === currentQuestionIndex);
        segment.setAttribute(
            'aria-label',
            `Question ${index + 1}, ${answered ? 'answered' : 'unanswered'}`
        );
        segment.setAttribute('title', `Question ${index + 1}`);
    });
}

function isQuestionAnswered(questionId) {
    return answers[questionId] != null && answers[questionId] !== '';
}

function goToQuestion(index) {
    if (isOffline) {
        return;
    }
    if (index < 0 || index >= questions.length) {
        return;
    }
    renderQuestion(index);
}

function reviewFromStart() {
    goToQuestion(0);
    document.getElementById('questionsContainer')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function applyExamWatermark() {
    if (typeof paintExamineeWatermark === 'function') {
        paintExamineeWatermark(document.getElementById('examWatermarkLayer'));
    }
}

function hydrateSavedAnswers(savedAnswers) {
    answers = {};
    if (!savedAnswers || typeof savedAnswers !== 'object') {
        return;
    }

    Object.entries(savedAnswers).forEach(([questionId, choiceId]) => {
        if (choiceId != null && choiceId !== '') {
            answers[questionId] = choiceId;
        }
    });
}

function findResumeQuestionIndex() {
    for (let i = 0; i < questions.length; i++) {
        const qid = questions[i].Question_ID;
        if (answers[qid] == null) {
            return i;
        }
    }
    return 0;
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
                    <h5 class="mb-2 question-stem">${escapeHtml(question.Question_Text)}</h5>
                    <small class="text-muted">
                        <i class="bi bi-bookmark"></i> ${escapeHtml(question.Subject_Name)}
                    </small>
                </div>
            </div>
            
            <div class="choices">
                ${question.answers.map((answer, idx) => {
                    const letter = String.fromCharCode(65 + idx);
                    return `
                    <label class="choice-option ${answers[question.Question_ID] == answer.Choice_ID ? 'selected' : ''}"
                           onclick="selectAnswer(${question.Question_ID}, ${answer.Choice_ID}, this)">
                        <input type="radio"
                               name="question_${question.Question_ID}"
                               value="${answer.Choice_ID}"
                               ${answers[question.Question_ID] == answer.Choice_ID ? 'checked' : ''}>
                        <span class="choice-label-letter">${letter}.</span>
                        <span class="choice-label-text">${escapeHtml(answer.Choice_Text)}</span>
                    </label>
                `;
                }).join('')}
            </div>
        </div>
    `;
    
    // Update navigation
    document.getElementById('currentQuestionNum').textContent = index + 1;

    if (index === questions.length - 1) {
        document.getElementById('nextBtn').style.display = 'none';
        document.getElementById('submitBtn').style.display = 'block';
    } else {
        document.getElementById('nextBtn').style.display = 'block';
        document.getElementById('submitBtn').style.display = 'none';
    }

    updateProgressSegments();
    syncNavLock();
}

// Select an answer
async function selectAnswer(questionId, choiceId, element) {
    if (isOffline || examSubmitted) {
        return;
    }

    const choices = document.querySelectorAll('.choice-option');
    choices.forEach(choice => choice.classList.remove('selected'));
    if (element) {
        element.classList.add('selected');
        const radio = element.querySelector('input[type="radio"]');
        if (radio) {
            radio.checked = true;
        }
    }

    try {
        await axios.post('../../api/examinee/submit-answer.php', {
            session_id: sessionId,
            question_id: questionId,
            choice_id: choiceId,
            occupancy_token: getOccupancyToken(),
        });
        answers[questionId] = choiceId;
        pendingAnswer = null;
        updateProgress();
    } catch (error) {
        console.error('Error saving answer:', error);
        if (isNetworkError(error)) {
            pendingAnswer = { questionId, choiceId };
            setExamOffline();
            return;
        }

        if (isOccupancyConflict(error)) {
            await showOccupancyBlockedAlert(error);
            return;
        }

        if (element) {
            element.classList.remove('selected');
            const radio = element.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = false;
            }
        }

        const savedId = answers[questionId];
        if (savedId != null) {
            document.querySelectorAll('.choice-option').forEach((choice) => {
                const input = choice.querySelector('input');
                if (input && String(input.value) === String(savedId)) {
                    choice.classList.add('selected');
                    input.checked = true;
                }
            });
        }
    }
}

// Update progress
function updateProgress() {
    const answeredCount = Object.keys(answers).length;

    document.getElementById('answeredCount').textContent = answeredCount;
    updateProgressSegments();
    
    syncNavLock();
}

// Navigation
function nextQuestion() {
    if (isOffline) {
        return;
    }
    if (currentQuestionIndex < questions.length - 1) {
        renderQuestion(currentQuestionIndex + 1);
    }
}

function previousQuestion() {
    if (isOffline) {
        return;
    }
    if (currentQuestionIndex > 0) {
        renderQuestion(currentQuestionIndex - 1);
    }
}

function initExamSwipeNavigation() {
    if (!isTouchExamNav()) {
        return;
    }

    const area = document.getElementById('questionsArea');
    if (!area || area.dataset.swipeBound === '1') {
        return;
    }
    area.dataset.swipeBound = '1';

    let startX = 0;
    let startY = 0;
    let tracking = false;

    const SWIPE_THRESHOLD = 50;
    const MAX_VERTICAL_DRIFT = 80;

    area.addEventListener('touchstart', (e) => {
        if (e.touches.length !== 1) {
            return;
        }
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
        tracking = true;
    }, { passive: true });

    area.addEventListener('touchend', (e) => {
        if (!tracking) {
            return;
        }
        tracking = false;

        const touch = e.changedTouches[0];
        if (!touch) {
            return;
        }

        const dx = touch.clientX - startX;
        const dy = touch.clientY - startY;

        if (Math.abs(dx) < SWIPE_THRESHOLD) {
            return;
        }
        if (Math.abs(dy) > MAX_VERTICAL_DRIFT && Math.abs(dy) > Math.abs(dx)) {
            return;
        }

        if (dx < 0) {
            if (!isOffline) {
                nextQuestion();
            }
        } else if (!isOffline) {
            previousQuestion();
        }
    }, { passive: true });
}

// Timer
function goToAvailableExams() {
    window.location.href = 'dashboard.php';
}

function showTimeExpiredSubmittedAlert() {
    return Swal.fire({
        icon: 'warning',
        title: 'Time Expired',
        text: 'The time limit has been reached. Your exam has been submitted.',
        confirmButtonText: 'OK',
        allowOutsideClick: false,
        allowEscapeKey: false,
    }).then(() => {
        goToAvailableExams();
    });
}

function startTimer() {
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }

    const sessionStartDate = parseExamDateTime(examData.time_started);
    const sessionStart = sessionStartDate ? sessionStartDate.getTime() : NaN;
    const duration = Number(examData.duration) * 60 * 1000;
    const timerEl = document.getElementById('timer');

    if (!Number.isFinite(sessionStart) || !Number.isFinite(duration)) {
        if (timerEl) {
            timerEl.textContent = '00:00:00';
        }
        return;
    }

    const tick = () => {
        const remaining = duration - (Date.now() - sessionStart);

        if (!Number.isFinite(remaining) || remaining <= 0) {
            clearInterval(timerInterval);
            timerInterval = null;
            if (timerEl) {
                timerEl.textContent = '00:00:00';
            }
            if (isOffline) {
                pendingAutoSubmit = true;
                Swal.fire({
                    icon: 'warning',
                    title: 'Time Expired',
                    text: 'The time limit has been reached. Your exam will submit when your connection is restored.',
                    allowOutsideClick: false,
                    showConfirmButton: false
                });
                return;
            }
            Swal.fire({
                icon: 'warning',
                title: 'Time Expired',
                text: 'The time limit has been reached. Submitting your exam...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false
            });
            submitExam(true);
            return;
        }

        const hours = Math.floor(remaining / 3600000);
        const minutes = Math.floor((remaining % 3600000) / 60000);
        const seconds = Math.floor((remaining % 60000) / 1000);

        if (timerEl) {
            timerEl.textContent = `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
            if (remaining < 600000) {
                timerEl.classList.add('warning');
            }
        }

        if (remaining < 300000 && remaining > 299000) {
            Swal.fire({
                icon: 'warning',
                title: '5 Minutes Remaining',
                text: 'You have 5 minutes left to complete the exam.',
                timer: 3000,
                showConfirmButton: false
            });
        }
    };

    tick();
    timerInterval = setInterval(tick, 1000);
}

function pad(num) {
    const n = Number(num);
    if (!Number.isFinite(n) || n < 0) {
        return '00';
    }
    return Math.floor(n).toString().padStart(2, '0');
}

// Submit exam
function examineeGradePercent(score, totalQuestions) {
    const totalItems = Number(totalQuestions) || 0;
    if (totalItems <= 0) {
        return 50;
    }

    let ratio = Number(score) / totalItems;
    if (!Number.isFinite(ratio) || ratio < 0) {
        ratio = 0;
    } else if (ratio > 1) {
        ratio = 1;
    }

    return Math.round((ratio * 50 + 50) * 100) / 100;
}

async function submitExam(autoSubmit = false) {
    if (examSubmitted) {
        if (autoSubmit) {
            showTimeExpiredSubmittedAlert();
        }
        return;
    }

    if (isOffline && !autoSubmit) {
        setExamOffline();
        return;
    }

    const answeredCount = Object.keys(answers).length;
    const unanswered = questions.length - answeredCount;
    
    // If not auto-submit, check if all questions are answered
    if (!autoSubmit) {
        if (unanswered > 0) {
            const result = await Swal.fire({
                title: 'Incomplete Exam',
                html: `
                    <p>You must answer all questions before submitting.</p>
                    <p class="text-danger"><strong>${unanswered} question(s) remaining</strong></p>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Continue Answering',
                cancelButtonText: 'Review Answers',
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#6c757d'
            });

            if (!result.isConfirmed) {
                reviewFromStart();
            }
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
        
        if (!result.isConfirmed) {
            reviewFromStart();
            return;
        }
    }
    
    try {
        clearInterval(timerInterval);
        await reportExamDisconnectIfNeeded();

        const response = await axios.post('../../api/examinee/submit-exam.php', {
            session_id: sessionId,
            occupancy_token: getOccupancyToken(),
        });
        
        if (response.data.success) {
            examSubmitted = true;
            await reportExamDisconnectIfNeeded();
            updateFullscreenButton();
            proctoringActive = false;
            stopConnectivityHeartbeat();
            hideConnectionBanner();
            clearFocusViolationCoalescing();
            const data = response.data.data;

            if (autoSubmit) {
                await showTimeExpiredSubmittedAlert();
                return;
            }

            const canReview = data.can_review_responses === true;
            const resultId = data.result_id;
            const average = examineeGradePercent(data.score, data.total_questions);

            Swal.fire({
                title: data.remarks === 'Passed' ? 'Congratulations!' : 'Exam Completed',
                html: `
                    <div class="text-center">
                        <h3 class="mb-3">${data.remarks}</h3>
                        <p class="mb-2">Score: <strong>${data.score} / ${data.total_questions}</strong></p>
                        <p class="mb-2">Average: <strong>${average}%</strong></p>
                        ${!canReview ? '<p class="text-muted small mt-2"><i class="bi bi-lock"></i> Response review is disabled for this exam.</p>' : ''}
                    </div>
                `,
                icon: data.remarks === 'Passed' ? 'success' : 'info',
                confirmButtonText: canReview && resultId ? 'View Responses' : 'OK',
                showCancelButton: canReview && resultId,
                cancelButtonText: 'OK',
                allowOutsideClick: false
            }).then((swalResult) => {
                if (canReview && resultId && swalResult.isConfirmed) {
                    window.location.href = `result.php?result_id=${resultId}`;
                } else {
                    goToAvailableExams();
                }
            });
        }
    } catch (error) {
        console.error('Error submitting exam:', error);
        if (isNetworkError(error)) {
            if (autoSubmit) {
                pendingAutoSubmit = true;
            }
            setExamOffline();
            if (!autoSubmit) {
                startTimer();
            }
            return;
        }
        if (isOccupancyConflict(error)) {
            if (!autoSubmit) {
                startTimer();
            }
            await showOccupancyBlockedAlert(error);
            return;
        }
        const message = error.response?.data?.message || 'Failed to submit exam. Please try again.';
        const expired = /expired/i.test(message) || /already been submitted/i.test(message) || /already ended/i.test(message);

        if (autoSubmit || expired) {
            examSubmitted = true;
            proctoringActive = false;
            stopConnectivityHeartbeat();
            hideConnectionBanner();
            clearFocusViolationCoalescing();
            await showTimeExpiredSubmittedAlert();
            return;
        }

        Swal.fire({
            icon: 'error',
            title: 'Submission Failed',
            text: message
        });
        
        startTimer();
    }
}

// Prevent accidental page close
window.addEventListener('beforeunload', (e) => {
    if (sessionId && timerInterval && !examSubmitted) {
        e.preventDefault();
        e.returnValue = '';
    }
});

window.addEventListener('pagehide', () => {
    releaseExamOccupancy();
});

window.addEventListener('unload', () => {
    releaseExamOccupancy();
});

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState !== 'hidden' || !sessionId || examSubmitted) {
        return;
    }
    pingServer();
});

document.addEventListener('freeze', () => {
    if (!sessionId || examSubmitted) {
        return;
    }
    pingServer();
});

// --- Proctoring: fullscreen, tab, and clipboard detection ---

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

function reportViolation(type, title, message, e = null, options = {}) {
    if (!proctoringActive || examSubmitted) return false;

    if (e) {
        e.preventDefault();
    }

    logCheatingEvent(type);

    const showWarning = options.warn !== false;
    if (showWarning) {
        showProctorWarning(
            title,
            `<p>${escapeHtml(message)}</p><p>This incident has been <strong>recorded</strong>.</p>`
        );
    }

    return true;
}

async function handleCheatingLock() {
    if (examSubmitted) {
        return;
    }

    proctoringActive = false;

    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }

    stopConnectivityHeartbeat();
    clearFocusViolationCoalescing();

    // Release occupancy before marking the exam as submitted/locked.
    releaseExamOccupancy();

    examSubmitted = true;

    if (isFullscreen()) {
        try {
            if (document.exitFullscreen) {
                await document.exitFullscreen();
            }
        } catch (error) {
            console.warn('Could not exit fullscreen:', error);
        }
    }

    await Swal.fire({
        icon: 'error',
        title: 'Exam Locked',
        text: 'Your exam has been locked. Please contact administrator to unlock.',
        confirmButtonText: 'OK',
        allowOutsideClick: false,
        allowEscapeKey: false,
    });

    goToAvailableExams();
}

async function logCheatingEvent(type) {
    if (!sessionId || examSubmitted || !type) return;

    if (isOffline || !browserThinksOnline()) {
        queuePendingCheatingEvent(type);
        return;
    }

    try {
        const response = await axios.post(LOG_CHEATING_API, {
            session_id: sessionId,
            type,
        });

        const data = response.data?.data;

        if (data?.cheating_locked === true) {
            await handleCheatingLock();
        }
    } catch (err) {
        console.error('Failed to log proctoring event:', err);

        if (err.response?.status === 403) {
            const message = err.response?.data?.message || '';

            if (/exam session is locked/i.test(message)) {
                await handleCheatingLock();
                return;
            }
        }

        if (isNetworkError(err)) {
            queuePendingCheatingEvent(type);
        }
    }
}

function queuePendingCheatingEvent(type) {
    if (pendingCheatingEvents.length >= 50) {
        return;
    }
    pendingCheatingEvents.push(type);
}

async function flushPendingCheatingEvents() {
    if (!pendingCheatingEvents.length || examSubmitted) {
        return;
    }

    const queued = pendingCheatingEvents.splice(0, pendingCheatingEvents.length);
    for (const type of queued) {
        await logCheatingEvent(type);
        if (examSubmitted) {
            return;
        }
    }
}

async function enterProctorMode() {
    proctoringActive = true;

    if (isTouchExamNav()) {
        proctorFullscreenGraceUntil = Date.now() + 3000;
        document.body.classList.add('exam-mobile-fs-hint');
        window.setTimeout(() => {
            document.body.classList.remove('exam-mobile-fs-hint');
        }, 8000);
    }

    const entered = await requestExamFullscreen();
    fullscreenEnforced = entered;
    updateFullscreenButton();
    if (!entered) {
        await promptReturnToFullscreen();
    }
}

function showProctorWarning(title, message) {
    const needsFullscreen = !isFullscreen();

    if (isTouchExamNav() && !needsFullscreen) {
        const config = {
            icon: 'warning',
            title,
            html: message,
            toast: true,
            position: 'top',
            timer: 4000,
            showConfirmButton: false,
            timerProgressBar: true,
        };

        if (proctorWarningShowing && Swal.isVisible()) {
            Swal.update(config);
            return;
        }

        proctorWarningShowing = true;
        Swal.fire(config).finally(() => {
            proctorWarningShowing = false;
        });
        return;
    }

    const confirmButtonText = needsFullscreen ? 'Return to Fullscreen' : 'Continue Exam';
    const config = {
        icon: 'warning',
        title,
        html: message,
        confirmButtonText,
        allowOutsideClick: false,
        heightAuto: true,
    };

    if (proctorWarningShowing && Swal.isVisible()) {
        Swal.update(config);
        return;
    }

    proctorWarningShowing = true;
    Swal.fire(config).then(async (result) => {
        if (result.isConfirmed && !isFullscreen() && !examSubmitted) {
            await requestExamFullscreen();
            updateFullscreenButton();
        }
    }).finally(() => {
        proctorWarningShowing = false;
    });
}

function handleFullscreenChange() {
    updateFullscreenButton();

    if (!proctoringActive || examSubmitted) return;

    if (!isFullscreen()) {
        if (isTouchExamNav() && Date.now() < proctorFullscreenGraceUntil) {
            return;
        }
        queueFocusViolation('fullscreenExit');
    }
}

function handleVisibilityChange() {
    if (!proctoringActive || examSubmitted) return;
    if (document.hidden) {
        queueFocusViolation('pageHidden');
    }
}

function handleWindowBlur() {
    if (!proctoringActive || examSubmitted || document.hidden) return;
    queueFocusViolation('windowBlur');
}

function handleCopyAttempt(e) {
    reportViolation(
        'COPY_ATTEMPT',
        'Copy Attempt Detected',
        'Copying exam content is not allowed.',
        e
    );
}

function handlePasteAttempt(e) {
    reportViolation(
        'PASTE_ATTEMPT',
        'Paste Attempt Detected',
        'Pasting text into the exam is not allowed.',
        e
    );
}

function handleContextMenuBlock(e) {
    if (!proctoringActive || examSubmitted) return;

    const examRoot = document.getElementById('examContainer');
    if (!examRoot || !examRoot.contains(e.target)) return;

    const isMobile = /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);

    if (isMobile) {
        reportViolation(
            'COPY_ATTEMPT',
            'Copy Attempt Detected',
            'Copying exam content is not allowed.',
            e
        );
    } else {
        reportViolation(
            'CONTEXT_MENU',
            'Right-Click Attempt Detected',
            'Right-click is not allowed during a proctored exam.',
            e
        );
    }
}

const CLIPBOARD_SHORTCUTS = {
    c: {
        type: 'COPY_ATTEMPT',
        title: 'Copy Attempt Detected',
        message: 'Copying exam content is not allowed.'
    },
    v: {
        type: 'PASTE_ATTEMPT',
        title: 'Paste Attempt Detected',
        message: 'Pasting text into the exam is not allowed.'
    }
};

function handleProctorKeydown(e) {
    if (!proctoringActive || examSubmitted) return;

    const isMac = navigator.platform.toUpperCase().includes('MAC');
    const mod = isMac ? e.metaKey : e.ctrlKey;
    if (!mod) return;

    const shortcut = CLIPBOARD_SHORTCUTS[e.key.toLowerCase()];
    if (!shortcut) return;

    reportViolation(shortcut.type, shortcut.title, shortcut.message, e);
}

function initProctoring() {
    document.addEventListener('fullscreenchange', handleFullscreenChange);
    document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
    document.addEventListener('MSFullscreenChange', handleFullscreenChange);
    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('blur', handleWindowBlur);

    document.addEventListener('copy', handleCopyAttempt, true);
    document.addEventListener('paste', handlePasteAttempt, true);
    document.addEventListener('contextmenu', handleContextMenuBlock, true);
    document.addEventListener('keydown', handleProctorKeydown, true);

    const fullscreenBtn = document.getElementById('examFullscreenBtn');
    if (fullscreenBtn && !fullscreenBtn.dataset.bound) {
        fullscreenBtn.dataset.bound = '1';
        fullscreenBtn.addEventListener('click', enterExamFullscreenFromButton);
    }
    updateFullscreenButton();
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

// Initialize
window.goToQuestion = goToQuestion;
window.reviewFromStart = reviewFromStart;
window.nextQuestion = nextQuestion;
window.previousQuestion = previousQuestion;
window.submitExam = submitExam;
window.selectAnswer = selectAnswer;

loadExam();
