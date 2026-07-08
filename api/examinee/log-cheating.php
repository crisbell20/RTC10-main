<?php
/**
 * Log cheating / proctoring events during an active exam session.
 */

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../config/connection-pdo.php';

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Examinee') {
    sendError('Unauthorized', 401);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$sessionId = (int)($input['session_id'] ?? 0);
$type = trim((string)($input['type'] ?? ''));
$detail = trim((string)($input['detail'] ?? ''));

$allowedTypes = [
    'FULLSCREEN_EXIT',
    'FULLSCREEN_DENIED',
    'TAB_SWITCH',
    'WINDOW_BLUR',
    'VISIBILITY_HIDDEN',
];

if (!$sessionId || $type === '' || !in_array($type, $allowedTypes, true)) {
    sendError('Invalid session_id or event type');
}

$userId = (int)$_SESSION['user_id'];

try {
    $stmt = $pdo->prepare('
        SELECT es.Session_ID, es.User_ID, es.Exam_ID, es.Time_Ended
        FROM tbl_exam_session es
        WHERE es.Session_ID = ?
        LIMIT 1
    ');
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session || (int)$session['User_ID'] !== $userId) {
        sendError('Invalid exam session', 403);
    }

    if (!empty($session['Time_Ended'])) {
        sendError('Exam session has already ended', 400);
    }

    $logType = $detail !== '' ? ($type . ': ' . substr($detail, 0, 180)) : $type;

    $logStmt = $pdo->prepare('
        INSERT INTO tbl_cheating_log (Session_ID, Type, Timestamp)
        VALUES (?, ?, NOW())
    ');
    $logStmt->execute([$sessionId, substr($logType, 0, 100)]);

    $reasonMap = [
        'FULLSCREEN_EXIT' => 'Exited fullscreen mode during exam',
        'FULLSCREEN_DENIED' => 'Refused or failed to enter fullscreen mode',
        'TAB_SWITCH' => 'Switched tab or minimized window during exam',
        'WINDOW_BLUR' => 'Window lost focus during exam',
        'VISIBILITY_HIDDEN' => 'Exam page became hidden during exam',
    ];

    $reason = $reasonMap[$type] ?? 'Suspicious activity detected during exam';
    if ($detail !== '') {
        $reason .= ' — ' . substr($detail, 0, 200);
    }

    $countStmt = $pdo->prepare('SELECT COUNT(*) AS total FROM tbl_cheating_log WHERE Session_ID = ?');
    $countStmt->execute([$sessionId]);
    $violationCount = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    $incidentId = null;
    $incidentCreated = false;

    $incidentCheck = $pdo->prepare('
        SELECT Incident_ID, Status
        FROM tbl_cheating_incident
        WHERE Session_ID = ?
        ORDER BY Timestamp DESC
        LIMIT 1
    ');
    $incidentCheck->execute([$sessionId]);
    $existingIncident = $incidentCheck->fetch(PDO::FETCH_ASSOC);

    $shouldTrackIncident = in_array($type, ['FULLSCREEN_EXIT', 'TAB_SWITCH', 'VISIBILITY_HIDDEN', 'FULLSCREEN_DENIED'], true);

    if ($shouldTrackIncident) {
        $fullReason = $reason . ' (Total violations: ' . $violationCount . ')';

        if ($existingIncident && ($existingIncident['Status'] ?? '') === 'Open') {
            $updateStmt = $pdo->prepare('
                UPDATE tbl_cheating_incident
                SET Reason = ?, Timestamp = NOW()
                WHERE Incident_ID = ?
            ');
            $updateStmt->execute([$fullReason, $existingIncident['Incident_ID']]);
            $incidentId = (int)$existingIncident['Incident_ID'];
        } else {
            $insertIncident = $pdo->prepare('
                INSERT INTO tbl_cheating_incident (Session_ID, Reason, Timestamp, Status)
                VALUES (?, ?, NOW(), \'Open\')
            ');
            $insertIncident->execute([$sessionId, $fullReason]);
            $incidentId = (int)$pdo->lastInsertId();
            $incidentCreated = true;
        }
    }

    sendSuccess([
        'logged' => true,
        'violation_count' => $violationCount,
        'incident_id' => $incidentId,
        'incident_created' => $incidentCreated,
    ]);
} catch (PDOException $e) {
    error_log('log-cheating.php DB error: ' . $e->getMessage());
    sendError('Failed to log proctoring event', 500);
} catch (Exception $e) {
    error_log('log-cheating.php error: ' . $e->getMessage());
    sendError('Failed to log proctoring event', 500);
}
