<?php

// Examinee Exam Questions API
// Loads exam questions


header('Content-Type: application/json');
session_start();

require_once '../config/connection-pdo.php';
/** @var PDO $pdo */
require_once '../includes/exam-session-utils.php';
require_once '../includes/module-utils.php';

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

$request_method = $_SERVER['REQUEST_METHOD'];

try {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!is_array($input)) {
        $input = [];
    }
    $action = $_GET['action'] ?? ($input['action'] ?? '');

    if ($request_method === 'GET' && $action === 'ping') {
        header('Cache-Control: no-store, no-cache, must-revalidate');
        $sessionId = (int)($_GET['session_id'] ?? 0);
        $token = normalizeExamOccupancyToken($_GET['occupancy_token'] ?? '');
        if ($sessionId > 0 && $token !== '') {
            $stmt = $pdo->prepare('SELECT User_ID FROM tbl_exam_session WHERE Session_ID = ? LIMIT 1');
            $stmt->execute([$sessionId]);
            if ((int)$stmt->fetchColumn() === (int)$userId) {
                touchExamSessionOccupancy($pdo, $sessionId, $token);
            }
        }
        sendSuccess(['ok' => true, 't' => time()]);
    }

    if (($request_method === 'POST' || $request_method === 'GET') && $action === 'release_occupancy') {
        $sessionId = (int)($input['session_id'] ?? $_GET['session_id'] ?? 0);
        $token = normalizeExamOccupancyToken($input['occupancy_token'] ?? $_GET['occupancy_token'] ?? '');
        if ($sessionId <= 0 || $token === '') {
            sendSuccess(['released' => false]);
        }

        $stmt = $pdo->prepare('SELECT User_ID FROM tbl_exam_session WHERE Session_ID = ? LIMIT 1');
        $stmt->execute([$sessionId]);
        $ownerId = (int)$stmt->fetchColumn();
        if ($ownerId === (int)$userId) {
            releaseExamSessionOccupancy($pdo, $sessionId, $token);
        }

        sendSuccess(['released' => true]);
    }

    // Get questions for an exam session with randomization support
    if ($request_method === 'GET' && $action === 'get_session_questions') {
        $session_id = $_GET['session_id'] ?? '';

        if (empty($session_id) || !is_numeric($session_id)) {
            sendError('Invalid session_id parameter', 400);
        }

        // Get session and exam details
        $curriculum = subjectCurriculumJoin($pdo);
        $stmt = $pdo->prepare("
            SELECT 
                es.Session_ID, 
                es.Exam_ID, 
                es.User_ID, 
                es.Time_Started,
                es.Time_Ended,
                es.Cheating_Locked,
                es.Cheating_Locked_At,
                es.Cheating_Unlocked_At,
                e.Is_Randomized,
                e.Duration,
                e.Title,
                e.Status,
                s.Subject_Name,
                c.Course_Name
            FROM tbl_exam_session es
            INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
            INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
            {$curriculum['join']}
            WHERE es.Session_ID = ?
        ");
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
            
        if ((int)($session['Cheating_Locked'] ?? 0) === 1) {
            sendError('This exam is locked due to recorded violations. Please contact the administrator.', 403);
            }
            
        if (($session['Status'] ?? '') !== 'Published') {
            sendError('This exam is not available', 400);
            }

        $duration = (int)($session['Duration'] ?? 0);
        $exam_id = (int)$session['Exam_ID'];
        $time_started = $session['Time_Started'];

        if (finalizeSessionIfDurationExpired($pdo, (int)$session_id, $time_started, $duration)) {
            sendError('Exam time has expired. Your attempt has been submitted.', 403);
        }

        $occupancyToken = normalizeExamOccupancyToken($_GET['occupancy_token'] ?? '');
        if ($occupancyToken === '' || !claimExamSessionOccupancy($pdo, (int)$session_id, $occupancyToken)) {
            sendError(examOccupancyConflictMessage(), 409);
        }

        $time_expired = false;

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

        // Apply randomization if enabled
        if ($is_randomized == 1) {
            // Use Session_ID as seed for consistent randomization 
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
        
        if (!empty($questions)) {
            $questionIds = array_column($questions, 'Question_ID');
            $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
            $choiceStmt = $pdo->prepare("
                SELECT Question_ID, Choice_ID, Choice_Text
                FROM tbl_choice
                WHERE Question_ID IN ({$placeholders})
                ORDER BY Question_ID ASC, Choice_ID ASC
            ");
            $choiceStmt->execute($questionIds);

            $choicesByQuestion = [];
            foreach ($choiceStmt->fetchAll(PDO::FETCH_ASSOC) as $choice) {
                $qid = (int)$choice['Question_ID'];
                $choicesByQuestion[$qid][] = [
                    'Choice_ID' => (int)$choice['Choice_ID'],
                    'Choice_Text' => $choice['Choice_Text'],
                ];
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
            'exam_title' => $session['Title'],
            'subject_name' => $session['Subject_Name'] ?? '',
            'course_name' => $session['Course_Name'] ?? '',
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
