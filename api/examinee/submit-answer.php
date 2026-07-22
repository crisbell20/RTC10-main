<?php
/**
 * Submit Answer API
 * Saves examinee's answer for a question
 */

header('Content-Type: application/json');
session_start();

require_once '../config/connection-pdo.php';
require_once '../includes/validation-utils.php';

function sendSuccess($data = []) {
    http_response_code(200);
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

function sendError($message, $statusCode = 400) {
    http_response_code($statusCode);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Examinee') {
    sendError('Unauthorized', 401);
}

$userId = (int)$_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$session_id = isset($input['session_id']) ? (int)$input['session_id'] : 0;
$question_id = isset($input['question_id']) ? (int)$input['question_id'] : 0;
$choice_id = isset($input['choice_id']) ? (int)$input['choice_id'] : 0;

if ($session_id <= 0 || $question_id <= 0) {
    sendError('Missing required parameters');
}

if ($choice_id <= 0) {
    sendError('Please select an answer before saving');
}

try {
    $context = loadActiveExamSessionContext($pdo, $session_id, $userId);
    if (empty($context['valid'])) {
        sendError($context['message'] ?? 'Invalid session', $context['status'] ?? 400);
    }

    $examId = (int)$context['session']['Exam_ID'];

    if (!questionBelongsToExam($pdo, $question_id, $examId)) {
        sendError('Invalid answer for this exam.', 400);
    }

    if (!choiceBelongsToQuestion($pdo, $choice_id, $question_id)) {
        sendError('Invalid answer for this exam.', 400);
    }

    $stmt = $pdo->prepare('SELECT Answer_ID FROM tbl_answer WHERE Session_ID = ? AND Question_ID = ?');
    $stmt->execute([$session_id, $question_id]);
    $existing = $stmt->fetch();

    $stmt = $pdo->prepare('SELECT Choice_ID FROM tbl_choice WHERE Question_ID = ? AND Is_Correct = 1');
    $stmt->execute([$question_id]);
    $correct = $stmt->fetch();
    $is_correct = ($correct && $choice_id == $correct['Choice_ID']) ? 1 : 0;

    if ($existing) {
        $stmt = $pdo->prepare('UPDATE tbl_answer SET Choice_ID = ?, Is_Correct = ? WHERE Answer_ID = ?');
        $stmt->execute([$choice_id, $is_correct, $existing['Answer_ID']]);
    } else {
        $stmt = $pdo->prepare('SELECT Result_ID FROM tbl_result WHERE Session_ID = ?');
        $stmt->execute([$session_id]);
        $result = $stmt->fetch();

        if (!$result) {
            $stmt = $pdo->prepare('INSERT INTO tbl_result (Session_ID, Score, Percentage) VALUES (?, 0, 0)');
            $stmt->execute([$session_id]);
            $result_id = $pdo->lastInsertId();
        } else {
            $result_id = $result['Result_ID'];
        }

        $stmt = $pdo->prepare('INSERT INTO tbl_answer (Result_ID, Session_ID, Question_ID, Choice_ID, Is_Correct) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$result_id, $session_id, $question_id, $choice_id, $is_correct]);
    }

    sendSuccess(['message' => 'Answer saved']);

} catch (Exception $e) {
    error_log("Error in submit-answer.php: " . $e->getMessage());
    sendError('Failed to save answer', 500);
}
?>
