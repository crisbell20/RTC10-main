<?php

// Record an examinee internet disconnect during an active exam sitting.
 
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../config/connection-pdo.php';
require_once __DIR__ . '/../includes/audit-log-utils.php';

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

function msToTimestamp(int $ms): int {
    if ($ms > 20000000000) {
        return (int)floor($ms / 1000);
    }
    return $ms;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Examinee') {
    sendError('Unauthorized', 401);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$sessionId = (int)($input['session_id'] ?? 0);
$disconnectedRaw = (int)($input['disconnected_at'] ?? 0);
$restoredRaw = (int)($input['restored_at'] ?? 0);

if ($sessionId <= 0 || $disconnectedRaw <= 0 || $restoredRaw <= 0) {
    sendError('session_id, disconnected_at, and restored_at are required');
}

$disconnectedTs = msToTimestamp($disconnectedRaw);
$restoredTs = msToTimestamp($restoredRaw);
$nowTs = time();

if ($restoredTs > $nowTs + 120) {
    $restoredTs = $nowTs;
}

if ($disconnectedTs > $restoredTs) {
    sendError('Invalid disconnect window');
}

$duration = $restoredTs - $disconnectedTs;
if ($duration > 12 * 3600) {
    $disconnectedTs = $restoredTs - (12 * 3600);
    $duration = 12 * 3600;
}

if ($duration < 1) {
    $duration = 1;
}

$userId = (int)$_SESSION['user_id'];

try {
    $stmt = $pdo->prepare('
        SELECT es.Session_ID, es.User_ID, es.Exam_ID, es.Time_Started, e.Title
        FROM tbl_exam_session es
        INNER JOIN tbl_exam e ON e.Exam_ID = es.Exam_ID
        WHERE es.Session_ID = ?
        LIMIT 1
    ');
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session || (int)$session['User_ID'] !== $userId) {
        sendError('Invalid exam session', 403);
    }

    $startedTs = !empty($session['Time_Started']) ? strtotime((string)$session['Time_Started']) : false;
    if ($startedTs !== false && $disconnectedTs < $startedTs) {
        $disconnectedTs = $startedTs;
        $duration = max(1, $restoredTs - $disconnectedTs);
    }

    $examTitle = trim((string)($session['Title'] ?? 'exam'));
    $outcome = 'Connection lost "' . $examTitle . '"';

    auditFromSession(
        $pdo,
        'EXAM',
        'CONNECTION_LOST',
        $outcome,
        'SUCCESS',
        'exam',
        (int)$session['Exam_ID']
    );

    sendSuccess(['logged' => true]);
} catch (Exception $e) {
    error_log('log-connection.php error: ' . $e->getMessage());
    sendError('Failed to record connection loss', 500);
}
