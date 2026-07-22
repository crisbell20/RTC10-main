<?php
/**
 * Submit Exam API
 * Ends the exam session and calculates the final score
 */

header('Content-Type: application/json');
session_start();

require_once '../config/connection-pdo.php';
require_once '../includes/exam-code-utils.php';
require_once '../includes/exam-session-utils.php';
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

// Validate session
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Examinee') {
    sendError('Unauthorized', 401);
}

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$session_id = $input['session_id'] ?? null;

if (!$session_id) {
    sendError('Missing session_id');
}

try {
    $pdo->beginTransaction();

    $context = loadActiveExamSessionContext($pdo, (int)$session_id, (int)$userId);
    if (empty($context['valid'])) {
        $pdo->rollBack();
        sendError($context['message'] ?? 'Invalid session', $context['status'] ?? 400);
    }

    $session = $context['session'];
    $payload = completeExamSessionSubmission($pdo, (int)$session_id);

    $hasResponseReview = responseReviewColumnExists($pdo);
    $reviewSelect = $hasResponseReview ? ', Allow_Response_Review' : '';
    $stmt = $pdo->prepare("SELECT Passing_Score{$reviewSelect} FROM tbl_exam WHERE Exam_ID = ?");
    $stmt->execute([$session['Exam_ID']]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $canReview = examAllowsResponseReview($exam, $hasResponseReview);

    $pdo->commit();

    sendSuccess([
        'score' => $payload['score'],
        'total_questions' => $payload['total_questions'],
        'percentage' => $payload['percentage'],
        'remarks' => $payload['remarks'],
        'passing_score' => $payload['passing_score'],
        'can_review_responses' => $canReview,
        'result_id' => $payload['result_id'],
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in submit-exam.php: " . $e->getMessage());
    sendError('Failed to submit exam', 500);
}
?>
