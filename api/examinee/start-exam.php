<?php

// Examinee Start Exam API

header('Content-Type: application/json');
session_start();

require_once '../config/connection-pdo.php';
/** @var PDO $pdo */
require_once '../includes/exam-code-utils.php';
require_once '../includes/exam-participant-utils.php';
require_once '../includes/exam-session-utils.php';
require_once '../includes/validation-utils.php';
require_once '../includes/archive-utils.php';

function sendSuccess($data) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
    exit;
}

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
        sendError('Access denied. This resource is only available to trainees.', 403);
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

    // Block start when subject/course for this exam is archived
    if (archiveColumnExists($pdo, 'tbl_subject') && archiveColumnExists($pdo, 'tbl_course')) {
        $curriculumStmt = $pdo->prepare('
            SELECT s.Is_Archived AS Subject_Archived, c.Is_Archived AS Course_Archived
            FROM tbl_exam e
            INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
            INNER JOIN tbl_course c ON s.Course_ID = c.Course_ID
            WHERE e.Exam_ID = ?
            LIMIT 1
        ');
        $curriculumStmt->execute([$examId]);
        $curriculum = $curriculumStmt->fetch(PDO::FETCH_ASSOC);
        if ($curriculum && (
            (int)($curriculum['Subject_Archived'] ?? 0) === 1
            || (int)($curriculum['Course_Archived'] ?? 0) === 1
        )) {
            $pdo->rollBack();
            sendError('This exam is no longer available', 403);
        }
    }
    
    // Validate exam has questions assigned
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
    
    // Validate exam is assigned to user's active, non-archived batch
    $batchActive = activeArchiveCondition($pdo, 'tbl_batch', 'b');
    $batchAssignmentQuery = "
        SELECT COUNT(*) as batch_match 
        FROM tbl_exam_batch eb
        INNER JOIN tbl_batch b ON eb.Batch_ID = b.Batch_ID
        INNER JOIN tbl_user_batch ub ON eb.Batch_ID = ub.Batch_ID
        WHERE eb.Exam_ID = ? 
        AND ub.User_ID = ? 
        AND ub.Status = 'Active'
        AND {$batchActive}
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
    
    // Validate exam status is Published
    if ($exam['Status'] !== 'Published') {
        $pdo->rollBack();
        sendError('This exam is not available', 400);
    }

    $scheduleError = validateExamScheduleStarted($exam);
    if ($scheduleError !== null) {
        $pdo->rollBack();
        sendError($scheduleError, 403);
    }
    
    // Check if deadline has passed 
    $deadlineError = validateExamDeadlineWithGrace($exam);
    if ($deadlineError !== null) {
        $pdo->rollBack();
        sendError($deadlineError, 403);
    }

    // Lock this trainee's sittings so a second request cannot open another retake.
    $lockedSessions = lockUserExamSessions($pdo, (int)$userId, (int)$examId);
    $activeSession = null;
    foreach ($lockedSessions as $lockedSession) {
        if (empty($lockedSession['Time_Ended'])) {
            $activeSession = $lockedSession;
            break;
        }
    }

    if ($activeSession && (int)($activeSession['Cheating_Locked'] ?? 0) === 1) {
    $pdo->rollBack();
    sendError(
        'This exam is locked due to recorded violations. Please contact the administrator.',
        403
    );
}

    if ($activeSession) {
        $sessionId = (int)$activeSession['Session_ID'];
        $durationMinutes = (int)($exam['Duration'] ?? 0);

        if (finalizeSessionIfDurationExpired(
            $pdo,
            $sessionId,
            $activeSession['Time_Started'],
            $durationMinutes
        )) {
            $pdo->commit();
            sendError('Exam time has expired. Your attempt has been submitted.', 403);
        }

        $pdo->commit();
        sendSuccess([
            'session_id' => $sessionId,
            'exam_title' => $exam['Title'],
            'message' => 'Resuming existing exam session'
        ]);
    }

    // Remove missed-deadline placeholders so never-started examinees can start after reopen
    clearRetakeEligibleAttemptsForUser($pdo, (int)$userId, (int)$examId);
    lockUserExamSessions($pdo, (int)$userId, (int)$examId);

    $attemptGate = evaluateNewAttemptGate($pdo, (int)$userId, (int)$examId);
    if (empty($attemptGate['allowed'])) {
        $pdo->rollBack();
        sendError('You have already completed this exam', 403);
    }

    // Validate exam access code
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
    
    $isRetake = !empty($attemptGate['is_retake']);
    if ($isRetake && !consumeRetakeGrant($pdo, (int)$attemptGate['grant_session_id'])) {
        $pdo->rollBack();
        sendError('You have already completed this exam', 403);
    }

    // Create new exam session
    if ($isRetake) {
        $insertQuery = "
            INSERT INTO tbl_exam_session (Exam_ID, User_ID, Time_Started, Is_Retake)
            VALUES (?, ?, NOW(), 1)
        ";
    } else {
        $insertQuery = "
            INSERT INTO tbl_exam_session (Exam_ID, User_ID, Time_Started)
            VALUES (?, ?, NOW())
        ";
    }
    $stmt = $pdo->prepare($insertQuery);
    $stmt->execute([$examId, $userId]);
    
    $sessionId = $pdo->lastInsertId();
    
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
