<?php
/**
 * Examinee Exam Questions API
 * Loads exam questions for an active exam session with randomization support
 */

header('Content-Type: application/json');
session_start();

require_once '../config/connection-pdo.php';
require_once '../includes/exam-session-utils.php';

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

// Get request parameters
$request_method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    // Get questions for an exam session with randomization support
    if ($request_method === 'GET' && $action === 'get_session_questions') {
        $session_id = $_GET['session_id'] ?? '';

        if (empty($session_id) || !is_numeric($session_id)) {
            sendError('Invalid session_id parameter', 400);
        }

        // Get session and exam details, verify ownership
        $stmt = $pdo->prepare('
            SELECT 
                es.Session_ID, 
                es.Exam_ID, 
                es.User_ID, 
                es.Time_Started,
                es.Time_Ended,
                e.Is_Randomized,
                e.Duration,
                e.Title,
                e.Status
            FROM tbl_exam_session es
            INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
            WHERE es.Session_ID = ?
        ');
        $stmt->execute([$session_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            sendError('Session not found', 404);
        }

        // Verify the session belongs to the authenticated user
        if ($session['User_ID'] != $userId) {
            sendError('Access denied. This session does not belong to you.', 403);
        }

        if (!empty($session['Time_Ended'])) {
            sendError('This exam has already been submitted', 400);
        }

        if (($session['Status'] ?? '') !== 'Published') {
            sendError('This exam is not available', 400);
        }

        $duration = (int)($session['Duration'] ?? 0);
        $exam_id = (int)$session['Exam_ID'];
        $time_started = reviveExpiredEmptySession(
            $pdo,
            (int)$session_id,
            $exam_id,
            $session['Time_Started'],
            $duration
        );

        // Check if time has expired (after optional revival for empty attempts)
        $time_expired = isSessionDurationExpired($time_started, $duration);

        $is_randomized = $session['Is_Randomized'];

        // Get all questions for the exam
        $stmt = $pdo->prepare('
            SELECT 
                eq.Exam_Question_ID,
                eq.Question_ID,
                eq.Question_Order,
                q.Question_Text,
                q.Question_Type,
                s.Subject_Name,
                s.Subject_ID
            FROM tbl_exam_question eq
            INNER JOIN tbl_question_bank q ON eq.Question_ID = q.Question_ID
            INNER JOIN tbl_subject s ON q.Subject_ID = s.Subject_ID
            WHERE eq.Exam_ID = ?
            ORDER BY eq.Question_Order ASC
        ');
        $stmt->execute([$exam_id]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Apply randomization if enabled (Requirement 9.2)
        if ($is_randomized == 1) {
            // Use Session_ID as seed for consistent randomization (Requirement 9.4, 9.5)
            mt_srand($session_id);
            
            // Shuffle the questions array
            $indices = array_keys($questions);
            shuffle($indices);
            
            $shuffled_questions = [];
            foreach ($indices as $index) {
                $shuffled_questions[] = $questions[$index];
            }
            $questions = $shuffled_questions;
            
            // Reset random seed to avoid affecting other operations
            mt_srand();
        }
        // If Is_Randomized = 0, questions are already ordered by Question_Order (Requirement 9.3)

        if (!empty($questions)) {
            $questionIds = array_column($questions, 'Question_ID');
            $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
            $choiceStmt = $pdo->prepare("
                SELECT Question_ID, Choice_ID, Choice_Text, Is_Correct
                FROM tbl_choice
                WHERE Question_ID IN ({$placeholders})
                ORDER BY Question_ID ASC, Choice_ID ASC
            ");
            $choiceStmt->execute($questionIds);

            $choicesByQuestion = [];
            foreach ($choiceStmt->fetchAll(PDO::FETCH_ASSOC) as $choice) {
                $qid = (int)$choice['Question_ID'];
                unset($choice['Question_ID']);
                $choicesByQuestion[$qid][] = $choice;
            }

            foreach ($questions as &$question) {
                $question['answers'] = $choicesByQuestion[(int)$question['Question_ID']] ?? [];
            }
            unset($question);
        }

        $savedAnswers = fetchExamSessionSavedAnswers($pdo, (int)$session_id, $exam_id);

        sendSuccess([
            'questions' => $questions,
            'saved_answers' => $savedAnswers,
            'is_randomized' => $is_randomized,
            'session_id' => $session_id,
            'time_started' => $time_started,
            'duration' => $duration,
            'time_expired' => $time_expired,
            'exam_title' => $session['Title']
        ]);
    }

    sendError('Invalid action', 400);

} catch (PDOException $e) {
    error_log("Database error in exam-questions.php: " . $e->getMessage());
    sendError('Failed to load exam questions. Please try again.', 500);
} catch (Exception $e) {
    error_log("Error in exam-questions.php: " . $e->getMessage());
    sendError('An error occurred while loading exam questions', 500);
}
