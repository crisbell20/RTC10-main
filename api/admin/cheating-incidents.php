<?php
/**
 * Admin — cheating incident management (list, logs, status updates).
 */

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/connection-pdo.php';
require_once __DIR__ . '/../includes/audit-log-utils.php';
require_once __DIR__ . '/../includes/cheating-log-utils.php';

$requestMethod = $_SERVER['REQUEST_METHOD'];
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? [];
$action = $_GET['action'] ?? ($input['action'] ?? '');

const INCIDENT_STATUSES = ['Open', 'Reviewed', 'Dismissed', 'Escalated'];

function sendSuccess($data = []) {
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

function sendError(string $message, int $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

try {
    if ($requestMethod === 'GET' && $action === 'stats') {
        if (!cheatingIncidentTableExists($pdo)) {
            sendSuccess(['open_incidents' => 0, 'total_incidents' => 0]);
        }

        $open = (int)$pdo->query("SELECT COUNT(*) FROM tbl_cheating_incident WHERE Status = 'Open'")->fetchColumn();
        $total = (int)$pdo->query('SELECT COUNT(*) FROM tbl_cheating_incident')->fetchColumn();
        sendSuccess(['open_incidents' => $open, 'total_incidents' => $total]);
    }

    if ($requestMethod === 'GET' && $action === 'list') {
        if (!cheatingIncidentTableExists($pdo)) {
            sendSuccess(['incidents' => []]);
        }

        $status = trim($_GET['status'] ?? '');
        $examId = !empty($_GET['exam_id']) ? (int)$_GET['exam_id'] : null;
        $limit = min(100, max(5, (int)($_GET['limit'] ?? 50)));

        $params = [];
        $filters = ' WHERE 1=1';

        if ($status !== '' && in_array($status, INCIDENT_STATUSES, true)) {
            $filters .= ' AND ci.Status = ?';
            $params[] = $status;
        }

        if ($examId) {
            $filters .= ' AND es.Exam_ID = ?';
            $params[] = $examId;
        }

        $stmt = $pdo->prepare("
            SELECT
                ci.Incident_ID,
                ci.Session_ID,
                ci.Reason,
                ci.Timestamp,
                ci.Status,
                u.User_ID,
                u.Fullname AS Trainee_Name,
                u.Academic_Number,
                e.Exam_ID,
                e.Title AS Exam_Name,
                c.Course_Name,
                es.Time_Started,
                es.Time_Ended,
                (
                    SELECT COUNT(*) FROM tbl_cheating_log cl WHERE cl.Session_ID = ci.Session_ID
                ) AS violation_count,
                (
                    SELECT cl.Type
                    FROM tbl_cheating_log cl
                    WHERE cl.Session_ID = ci.Session_ID
                    ORDER BY cl.Timestamp DESC, cl.Cheating_Log_ID DESC
                    LIMIT 1
                ) AS latest_log_type
            FROM tbl_cheating_incident ci
            INNER JOIN tbl_exam_session es ON ci.Session_ID = es.Session_ID
            INNER JOIN tbl_user u ON es.User_ID = u.User_ID
            INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
            INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
            INNER JOIN tbl_course c ON s.Course_ID = c.Course_ID
            {$filters}
            ORDER BY
                CASE ci.Status WHEN 'Open' THEN 0 WHEN 'Escalated' THEN 1 ELSE 2 END,
                ci.Timestamp DESC
            LIMIT {$limit}
        ");
        $stmt->execute($params);
        $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($incidents as &$incident) {
            $latestType = (string)($incident['latest_log_type'] ?? '');
            if ($latestType !== '') {
                $label = formatCheatingEventLabel($latestType);
            } else {
                $label = formatCheatingIncidentLatestLabel((string)($incident['Reason'] ?? ''));
            }
            $incident['latest_violation_label'] = $label;
            $incident['proctor_summary'] = formatCheatingProctorLine(
                (string)($incident['Academic_Number'] ?? ''),
                (string)($incident['Trainee_Name'] ?? ''),
                $label
            );
        }
        unset($incident);
        sendSuccess(['incidents' => $incidents]);
    }

    if ($requestMethod === 'GET' && $action === 'logs') {
        $sessionId = (int)($_GET['session_id'] ?? 0);
        if ($sessionId <= 0) {
            sendError('session_id is required');
        }

        if (!cheatingLogTableExists($pdo)) {
            sendSuccess([
                'session_id' => $sessionId,
                'trainee' => null,
                'exam' => null,
                'session' => null,
                'logs' => [],
                'total' => 0,
            ]);
        }

        $metaStmt = $pdo->prepare('
            SELECT
                es.Session_ID,
                es.Time_Started,
                es.Time_Ended,
                u.Fullname,
                u.Academic_Number,
                e.Exam_ID,
                e.Title AS Exam_Name
            FROM tbl_exam_session es
            INNER JOIN tbl_user u ON es.User_ID = u.User_ID
            INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
            WHERE es.Session_ID = ?
        ');
        $metaStmt->execute([$sessionId]);
        $meta = $metaStmt->fetch(PDO::FETCH_ASSOC);

        if (!$meta) {
            sendError('Session not found', 404);
        }

        $logStmt = $pdo->prepare('
            SELECT Cheating_Log_ID, Session_ID, Type, Timestamp
            FROM tbl_cheating_log
            WHERE Session_ID = ?
            ORDER BY Timestamp ASC, Cheating_Log_ID ASC
        ');
        $logStmt->execute([$sessionId]);
        $logs = array_map('enrichCheatingLogRow', $logStmt->fetchAll(PDO::FETCH_ASSOC));
        $academicNumber = (string)($meta['Academic_Number'] ?? '');
        $fullname = (string)($meta['Fullname'] ?? '');
        foreach ($logs as &$log) {
            $log['proctor_line'] = formatCheatingProctorLine(
                $academicNumber,
                $fullname,
                (string)($log['event_label'] ?? '')
            );
        }
        unset($log);

        sendSuccess([
            'session_id' => $sessionId,
            'trainee' => [
                'Fullname' => $meta['Fullname'],
                'Academic_Number' => $meta['Academic_Number'],
            ],
            'exam' => [
                'Exam_ID' => (int)$meta['Exam_ID'],
                'Exam_Name' => $meta['Exam_Name'],
            ],
            'session' => [
                'Time_Started' => $meta['Time_Started'],
                'Time_Ended' => $meta['Time_Ended'],
            ],
            'logs' => $logs,
            'total' => count($logs),
        ]);
    }

    if ($requestMethod === 'POST' && $action === 'update_status') {
        $incidentId = (int)($input['incident_id'] ?? 0);
        $status = trim($input['status'] ?? '');

        if ($incidentId <= 0) {
            sendError('incident_id is required');
        }

        if (!in_array($status, INCIDENT_STATUSES, true)) {
            sendError('Invalid status. Use Open, Reviewed, Dismissed, or Escalated.');
        }

        if (!cheatingIncidentTableExists($pdo)) {
            sendError('Incident table is not available', 503);
        }

        $check = $pdo->prepare('
            SELECT ci.Incident_ID, ci.Status, u.Fullname, e.Title
            FROM tbl_cheating_incident ci
            INNER JOIN tbl_exam_session es ON ci.Session_ID = es.Session_ID
            INNER JOIN tbl_user u ON es.User_ID = u.User_ID
            INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
            WHERE ci.Incident_ID = ?
        ');
        $check->execute([$incidentId]);
        $incident = $check->fetch(PDO::FETCH_ASSOC);

        if (!$incident) {
            sendError('Incident not found', 404);
        }

        $update = $pdo->prepare('UPDATE tbl_cheating_incident SET Status = ? WHERE Incident_ID = ?');
        $update->execute([$status, $incidentId]);

        $trainee = $incident['Fullname'] ?? 'Trainee';
        $exam = $incident['Title'] ?? 'Exam';
        auditFromSession(
            $pdo,
            'EXAM',
            'UPDATE_CHEATING_INCIDENT',
            "Cheating incident for {$trainee} ({$exam}) set to {$status}",
            'SUCCESS',
            'incident',
            $incidentId
        );

        sendSuccess([
            'incident_id' => $incidentId,
            'status' => $status,
            'message' => 'Incident status updated',
        ]);
    }

    sendError('Invalid action');
} catch (PDOException $e) {
    error_log('cheating-incidents.php DB error: ' . $e->getMessage());
    sendError('Database error', 500);
} catch (Exception $e) {
    error_log('cheating-incidents.php error: ' . $e->getMessage());
    sendError('Server error', 500);
}
