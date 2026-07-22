<?php
/**
 * Start Exam API
 * Creates a new exam session for an examinee
 */

header('Content-Type: application/json');
session_start();

require_once '../config/connection-pdo.php';
require_once '../includes/exam-code-utils.php';
require_once '../includes/exam-participant-utils.php';
require_once '../includes/exam-session-utils.php';
require_once '../includes/validation-utils.php';

/**
 * Send success response
 */
function sendSuccess($data) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
    exit;
}

/**
 * Send error response
 */
function sendError($message, $statusCode = 400) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'message' => $message
    ]);
    exit;
}

// Validate session
if (!isset($_SESSION['user_id'])) {
    sendError('Unauthorized. Please log in.', 401);
}

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Examinee') {
    sendError('Access denied. This resource is only available to examinees.', 403);
}

$userId = $_SESSION['user_id'];

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$examId = $input['exam_id'] ?? null;
$submittedCode = $input['exam_code'] ?? null;
$hasExamCode = examCodeColumnsExist($pdo);

// Validate exam_id parameter
if (empty($examId) || !is_numeric($examId)) {
    sendError('Invalid exam_id parameter', 400);
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Check if exam exists and is published
    $examColumns = $hasExamCode
        ? 'Exam_ID, Title, Status, Schedule_Date, Deadline, Duration, Exam_Code, Is_Archived'
        : 'Exam_ID, Title, Status, Schedule_Date, Deadline, Duration';

    $examQuery = "
        SELECT {$examColumns}
        FROM tbl_exam
        WHERE Exam_ID = ?
    ";
    $stmt = $pdo->prepare($examQuery);
    $stmt->execute([$examId]);
    $exam = $stmt->fetch();
    
    if (!$exam) {
        $pdo->rollBack();
        sendError('Exam not found', 404);
    }

    if ($hasExamCode && !empty($exam['Is_Archived'])) {
        $pdo->rollBack();
        sendError('This exam is no longer available', 403);
    }
    
    // Validate exam has questions assigned (Requirement 10.1, 10.2)
    $questionCountQuery = "
        SELECT COUNT(*) as question_count 
        FROM tbl_exam_question 
        WHERE Exam_ID = ?
    ";
    $stmt = $pdo->prepare($questionCountQuery);
    $stmt->execute([$examId]);
    $questionCount = $stmt->fetch();
    
    if ($questionCount['question_count'] == 0) {
        $pdo->rollBack();
        sendError('This exam has no questions assigned', 400);
    }
    
    // Validate exam is assigned to user's active batch (Requirement 10.3, 10.4)
    $batchAssignmentQuery = "
        SELECT COUNT(*) as batch_match 
        FROM tbl_exam_batch eb
        INNER JOIN tbl_user_batch ub ON eb.Batch_ID = ub.Batch_ID
        WHERE eb.Exam_ID = ? 
        AND ub.User_ID = ? 
        AND ub.Status = 'Active'
    ";
    $stmt = $pdo->prepare($batchAssignmentQuery);
    $stmt->execute([$examId, $userId]);
    $batchMatch = $stmt->fetch();
    
    if ($batchMatch['batch_match'] == 0) {
        $pdo->rollBack();
        sendError('You are not authorized to take this exam', 403);
    }

    if (!isExamParticipant($pdo, (int)$examId, (int)$userId)) {
        $pdo->rollBack();
        sendError('You are not selected to take this exam. Contact your instructor.', 403);
    }
    
    // Validate exam status is Published (Requirement 10.5, 10.6)
    if ($exam['Status'] !== 'Published') {
        $pdo->rollBack();
        sendError('This exam is not available', 400);
    }

    $scheduleError = validateExamScheduleStarted($exam);
    if ($scheduleError !== null) {
        $pdo->rollBack();
        sendError($scheduleError, 403);
    }
    
    // Check if deadline has passed (same moment as auto-close — no grace period)
    $deadlineError = validateExamDeadlineWithGrace($exam);
    if ($deadlineError !== null) {
        $pdo->rollBack();
        sendError($deadlineError, 403);
    }
    
    // Remove no-answer failed / abandoned attempts so reopen retake can proceed
    clearRetakeEligibleAttemptsForUser($pdo, (int)$userId, (int)$examId);

    if (userHasCompletedExamAttempt($pdo, (int)$userId, (int)$examId)) {
        $pdo->rollBack();
        sendError('You have already completed this exam', 403);
    }

    // Check for existing active session (not ended)
    $activeQuery = "
        SELECT Session_ID, Time_Started
        FROM tbl_exam_session
        WHERE User_ID = ?
        AND Exam_ID = ?
        AND Time_Ended IS NULL
    ";
    $stmt = $pdo->prepare($activeQuery);
    $stmt->execute([$userId, $examId]);
    $activeSession = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($activeSession) {
        $sessionId = (int)$activeSession['Session_ID'];
        $durationMinutes = (int)($exam['Duration'] ?? 0);

        reviveExpiredEmptySession(
            $pdo,
            $sessionId,
            (int)$examId,
            $activeSession['Time_Started'],
            $durationMinutes
        );

        $pdo->commit();
        sendSuccess([
            'session_id' => $sessionId,
            'exam_title' => $exam['Title'],
            'message' => 'Resuming existing exam session'
        ]);
    }

    // Validate exam access code for new sessions
    if ($hasExamCode && !empty($exam['Exam_Code'])) {
        $failedAttempts = getRecentFailedCodeAttempts($pdo, (int)$examId, (int)$userId);
        if ($failedAttempts >= 5) {
            $pdo->rollBack();
            sendError('Too many invalid code attempts. Please wait 15 minutes or contact your proctor.', 429);
        }

        $normalizedSubmitted = normalizeExamCode($submittedCode ?? '');
        $normalizedStored = normalizeExamCode($exam['Exam_Code']);

        if ($normalizedSubmitted === '' || $normalizedSubmitted !== $normalizedStored) {
            logExamCodeAttempt($pdo, (int)$examId, (int)$userId, false);
            $pdo->rollBack();
            sendError('Invalid or expired exam code', 403);
        }

        logExamCodeAttempt($pdo, (int)$examId, (int)$userId, true);
    }
    
    // Create new exam session
    $insertQuery = "
        INSERT INTO tbl_exam_session (Exam_ID, User_ID, Time_Started) 
        VALUES (?, ?, NOW())
    ";
    $stmt = $pdo->prepare($insertQuery);
    $stmt->execute([$examId, $userId]);
    
    $sessionId = $pdo->lastInsertId();
    
    // Commit transaction
    $pdo->commit();
    
    sendSuccess([
        'session_id' => $sessionId,
        'exam_title' => $exam['Title'],
        'message' => 'Exam session started successfully'
    ]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error in start-exam.php: " . $e->getMessage());
    sendError('Failed to start exam. Please try again.', 500);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in start-exam.php: " . $e->getMessage());
    sendError('An error occurred while starting the exam', 500);
}
