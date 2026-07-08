<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'CCMD'])) {
    http_response_code(403);
    $role = $_SESSION['user_role'] ?? 'not set';
    $userId = $_SESSION['user_id'] ?? 'not set';
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized. Required: Admin or CCMD. Current role: ' . $role,
        'debug' => [
            'user_id' => $userId,
            'user_role' => $role,
            'session_exists' => isset($_SESSION['user_id'])
        ]
    ]);
    exit;
}

require_once __DIR__ . '/../config/connection-pdo.php';
require_once __DIR__ . '/../includes/audit-log-utils.php';

$request_method = $_SERVER['REQUEST_METHOD'];
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? [];
$action = $_GET['action'] ?? ($input['action'] ?? '');

// Debug logging
error_log("exam-questions.php - Method: $request_method, Action: $action, Input: " . json_encode($input));

try {
    // Assign questions to an exam
    if ($request_method === 'POST' && $action === 'assign') {
        $exam_id = $input['exam_id'] ?? '';
        $question_ids = $input['question_ids'] ?? [];

        if (empty($exam_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Exam ID is required']);
            exit;
        }

        // Verify exam exists
        $stmt = $pdo->prepare('SELECT Exam_ID FROM tbl_exam WHERE Exam_ID = ?');
        $stmt->execute([$exam_id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Exam not found']);
            exit;
        }

        $pdo->beginTransaction();

        // Delete existing assignments for this exam
        $stmt = $pdo->prepare('DELETE FROM tbl_exam_question WHERE Exam_ID = ?');
        $stmt->execute([$exam_id]);

        // Insert new assignments with sequential Question_Order
        if (!empty($question_ids)) {
            $stmt = $pdo->prepare('INSERT INTO tbl_exam_question (Exam_ID, Question_ID, Question_Order, Date_Added) VALUES (?, ?, ?, NOW())');
            $order = 1;
            foreach ($question_ids as $question_id) {
                $stmt->execute([$exam_id, $question_id, $order]);
                $order++;
            }
        }

        $pdo->commit();

        auditFromSession($pdo, 'EXAM', 'ASSIGN_EXAM_QUESTIONS', count($question_ids) . " questions assigned to exam {$exam_id}", 'SUCCESS', 'exam', (int)$exam_id);

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Questions assigned successfully', 'count' => count($question_ids)]);
        exit;
    }

    // Get assigned questions for an exam (alias for 'get')
    if ($request_method === 'GET' && ($action === 'get' || $action === 'list')) {
        $exam_id = $_GET['exam_id'] ?? '';

        if (empty($exam_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Exam ID is required', 'debug' => ['exam_id' => $exam_id, 'get' => $_GET]]);
            exit;
        }

        // Verify exam exists
        $stmt = $pdo->prepare('SELECT Exam_ID FROM tbl_exam WHERE Exam_ID = ?');
        $stmt->execute([$exam_id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Exam not found', 'exam_id' => $exam_id]);
            exit;
        }

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

        // Get choices for each question
        foreach ($questions as &$question) {
            $stmt = $pdo->prepare('SELECT Choice_ID, Choice_Text, Is_Correct FROM tbl_choice WHERE Question_ID = ? ORDER BY Choice_ID');
            $stmt->execute([$question['Question_ID']]);
            $question['choices'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $questions]);
        exit;
    }

    // Add multiple questions to exam
    if ($request_method === 'POST' && $action === 'add_multiple') {
        $exam_id = $input['exam_id'] ?? '';
        $question_ids = $input['question_ids'] ?? [];

        if (empty($exam_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Exam ID is required']);
            exit;
        }

        if (empty($question_ids) || !is_array($question_ids)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Question IDs are required']);
            exit;
        }

        $pdo->beginTransaction();

        // Get current max order
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(Question_Order), 0) as max_order FROM tbl_exam_question WHERE Exam_ID = ?');
        $stmt->execute([$exam_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $order = $result['max_order'] + 1;

        // Insert new questions
        $stmt = $pdo->prepare('INSERT INTO tbl_exam_question (Exam_ID, Question_ID, Question_Order, Date_Added) VALUES (?, ?, ?, NOW())');
        foreach ($question_ids as $question_id) {
            $stmt->execute([$exam_id, $question_id, $order]);
            $order++;
        }

        $pdo->commit();

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Questions added successfully', 'count' => count($question_ids)]);
        exit;
    }

    // Remove a question from exam
    if ($request_method === 'POST' && $action === 'remove') {
        $exam_id = $input['exam_id'] ?? '';
        $question_id = $input['question_id'] ?? '';

        if (empty($exam_id) || empty($question_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Exam ID and Question ID are required']);
            exit;
        }

        $stmt = $pdo->prepare('DELETE FROM tbl_exam_question WHERE Exam_ID = ? AND Question_ID = ?');
        $stmt->execute([$exam_id, $question_id]);

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Question removed successfully']);
        exit;
    }

    // Reorder questions in an exam
    if ($request_method === 'POST' && $action === 'reorder') {
        $exam_id = $input['exam_id'] ?? '';
        $order = $input['order'] ?? [];

        if (empty($exam_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Exam ID is required']);
            exit;
        }

        if (empty($order) || !is_array($order)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Order array is required']);
            exit;
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare('UPDATE tbl_exam_question SET Question_Order = ? WHERE Exam_ID = ? AND Question_ID = ?');
        foreach ($order as $item) {
            $question_id = $item['question_id'] ?? '';
            $new_order = $item['order'] ?? '';
            
            if (!empty($question_id) && !empty($new_order)) {
                $stmt->execute([$new_order, $exam_id, $question_id]);
            }
        }

        $pdo->commit();

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Question order updated']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action', 'received_action' => $action, 'method' => $request_method]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
