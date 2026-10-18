<?php

//  Admin — cheating incident management

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
require_once __DIR__ . '/../includes/archive-utils.php';
require_once __DIR__ . '/../includes/person-name-utils.php';

$requestMethod = $_SERVER['REQUEST_METHOD'];
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? [];
$action = $_GET['action'] ?? ($input['action'] ?? '');

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
    $activeExamSql = activeArchiveCondition($pdo, 'tbl_exam', 'e');

// Incidents statistics
    if ($requestMethod === 'GET' && $action === 'stats') {
        if (!cheatingIncidentTableExists($pdo)) {
            sendSuccess(['total_incidents' => 0, 'exams_with_incidents' => 0]);
        }
        //count total incidents
        $total = (int)$pdo->query("
            SELECT COUNT(*)
            FROM tbl_cheating_incident ci
            INNER JOIN tbl_exam_session es ON ci.Session_ID = es.Session_ID
            INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
            WHERE {$activeExamSql}
        ")->fetchColumn();
        //count exams with incidents
        $examsWithIncidents = (int)$pdo->query("
            SELECT COUNT(DISTINCT es.Exam_ID)
            FROM tbl_cheating_incident ci
            INNER JOIN tbl_exam_session es ON ci.Session_ID = es.Session_ID
            INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
            WHERE {$activeExamSql}
        ")->fetchColumn();
        sendSuccess(['total_incidents' => $total, 'exams_with_incidents' => $examsWithIncidents]);
    }


    if ($requestMethod === 'GET' && $action === 'list') {
        if (!cheatingIncidentTableExists($pdo)) {
            sendSuccess(['incidents' => []]);
        }

        $examId = !empty($_GET['exam_id']) ? (int)$_GET['exam_id'] : null;
        $courseId = !empty($_GET['course_id']) ? (int)$_GET['course_id'] : null;
        $limit = min(100, max(5, (int)($_GET['limit'] ?? 50)));

        $params = [];
        $filters = " WHERE {$activeExamSql}";

        if ($examId) {
            $filters .= ' AND es.Exam_ID = ?';
            $params[] = $examId;
        }

        if ($courseId) {
            $filters .= ' AND c.Course_ID = ?';
            $params[] = $courseId;
        }

        $batchSql = userBatchTableExists($pdo) ? cheatingTraineeBatchNameSql() . ' AS Batch_Name' : 'NULL AS Batch_Name';
        $sectionSql = userBatchTableExists($pdo) ? cheatingTraineeSectionNameSql() . ' AS Section_Name' : 'NULL AS Section_Name';

        $stmt = $pdo->prepare("
        SELECT
            ci.Incident_ID,
            ci.Session_ID,
            ci.Reason,
            ci.Timestamp,
            u.User_ID,
            " . personNameSql('u') . " AS Trainee_Name,
            u.Badge_Number,
            e.Exam_ID,
            e.Title AS Exam_Name,
            c.Course_Name,
            {$batchSql}, {$sectionSql},
            es.Time_Started,
            es.Time_Ended,
            es.Cheating_Locked,
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
            ORDER BY ci.Timestamp DESC
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
                (string)($incident['Badge_Number'] ?? ''),
                (string)($incident['Trainee_Name'] ?? ''),
                $label
            );
        }
        unset($incident);
        sendSuccess(['incidents' => $incidents]);
    }

    if ($requestMethod === 'GET' && $action === 'exam_summary') {
        if (!cheatingIncidentTableExists($pdo)) {
            sendSuccess(['exams' => []]);
        }

        $courseId = !empty($_GET['course_id']) ? (int)$_GET['course_id'] : null;

        $params = [];
        $filters = " WHERE {$activeExamSql}";
        if ($courseId) {
            $filters .= ' AND c.Course_ID = ?';
            $params[] = $courseId;
        }

        $stmt = $pdo->prepare("
            SELECT
                e.Exam_ID,
                e.Title AS Exam_Name,
                c.Course_ID,
                c.Course_Name,
                s.Subject_Name,
                COUNT(*) AS incident_count,
                MAX(ci.Timestamp) AS latest_incident
            FROM tbl_cheating_incident ci
            INNER JOIN tbl_exam_session es ON ci.Session_ID = es.Session_ID
            INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
            INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
            INNER JOIN tbl_course c ON s.Course_ID = c.Course_ID
            {$filters}
            GROUP BY e.Exam_ID, e.Title, c.Course_ID, c.Course_Name, s.Subject_Name
            ORDER BY latest_incident DESC, e.Title ASC
        ");
        $stmt->execute($params);
        $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $assignmentsByExam = [];
        if (userBatchTableExists($pdo) && $exams) {
            $batchSql = cheatingTraineeBatchNameSql();
            $sectionSql = cheatingTraineeSectionNameSql();
            $assignStmt = $pdo->prepare("
                SELECT DISTINCT
                    e.Exam_ID,
                    {$batchSql} AS batch_name,
                    {$sectionSql} AS section_name
                FROM tbl_cheating_incident ci
                INNER JOIN tbl_exam_session es ON ci.Session_ID = es.Session_ID
                INNER JOIN tbl_user u ON es.User_ID = u.User_ID
                INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
                INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
                INNER JOIN tbl_course c ON s.Course_ID = c.Course_ID
                {$filters}
            ");
            $assignStmt->execute($params);
            foreach ($assignStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $examId = (int)$row['Exam_ID'];
                $batchName = trim((string)($row['batch_name'] ?? ''));
                $sectionName = trim((string)($row['section_name'] ?? ''));
                if ($batchName === '' && $sectionName === '') {
                    continue;
                }
                $label = $batchName && $sectionName
                    ? "{$batchName} • {$sectionName}"
                    : ($batchName ?: $sectionName);
                $assignmentsByExam[$examId][] = [
                    'batch_name' => $batchName,
                    'section_name' => $sectionName,
                    'label' => $label,
                ];
            }
        }

        foreach ($exams as &$exam) {
            $exam['Assignments'] = $assignmentsByExam[(int)$exam['Exam_ID']] ?? [];
        }
        unset($exam);

        sendSuccess(['exams' => $exams]);
    }
 // Cheating logs
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

        $metaStmt = $pdo->prepare("
            SELECT
            es.Session_ID,
            es.Time_Started,
            es.Time_Ended,
            es.Cheating_Locked,
            es.Cheating_Locked_At,
            es.Cheating_Unlocked_At,
                " . personNameSql('u') . " AS Fullname,
                u.Badge_Number,
                e.Exam_ID,
                e.Title AS Exam_Name
            FROM tbl_exam_session es
            INNER JOIN tbl_user u ON es.User_ID = u.User_ID
            INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
            WHERE es.Session_ID = ?
              AND {$activeExamSql}
        ");
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
        $badgeNumber = (string)($meta['Badge_Number'] ?? '');
        $fullname = (string)($meta['Fullname'] ?? '');
        foreach ($logs as &$log) {
            $log['proctor_line'] = formatCheatingProctorLine(
                $badgeNumber,
                $fullname,
                (string)($log['event_label'] ?? '')
            );
        }
        unset($log);

        sendSuccess([
            'session_id' => $sessionId,
            'trainee' => [
                'Fullname' => $meta['Fullname'],
                'Badge_Number' => $meta['Badge_Number'],
            ],
            'exam' => [
                'Exam_ID' => (int)$meta['Exam_ID'],
                'Exam_Name' => $meta['Exam_Name'],
            ],
            'session' => [
                'Session_ID' => (int)$meta['Session_ID'],
                'Time_Started' => $meta['Time_Started'],
                'Time_Ended' => $meta['Time_Ended'],
                'Cheating_Locked' => (int)$meta['Cheating_Locked'],
                'Cheating_Locked_At' => $meta['Cheating_Locked_At'],
                'Cheating_Unlocked_At' => $meta['Cheating_Unlocked_At'],
            ],
                'logs' => $logs,
                'total' => count($logs),
        ]);
    }

    // Unlock a locked exam session
if ($requestMethod === 'POST' && $action === 'unlock_session') {
    $sessionId = (int)($input['session_id'] ?? 0);

    if ($sessionId <= 0) {
        sendError('session_id is required');
    }

    $stmt = $pdo->prepare("
        SELECT
            Session_ID,
            Cheating_Locked,
            Cheating_Locked_At
        FROM tbl_exam_session
        WHERE Session_ID = ?
    ");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        sendError('Exam session not found', 404);
    }

    if ((int)$session['Cheating_Locked'] !== 1) {
        sendError('This exam session is not currently locked');
    }

    $stmt = $pdo->prepare("
        UPDATE tbl_exam_session
        SET
            Cheating_Locked = 0,
            Cheating_Unlocked_At = NOW(),
            Cheating_Unlocked_By = ?
        WHERE Session_ID = ?
    ");
    $stmt->execute([
        (int)$_SESSION['user_id'],
        $sessionId
    ]);

    sendSuccess([
        'session_id' => $sessionId,
        'cheating_locked' => false,
        'cheating_unlocked_at' => date('Y-m-d H:i:s'),
        'cheating_unlocked_by' => (int)$_SESSION['user_id'],
    ]);
}

    sendError('Invalid action');
} catch (PDOException $e) {
    error_log('cheating-incidents.php DB error: ' . $e->getMessage());
    sendError('An unexpected error occurred. Please try again later.', 500);
} catch (Exception $e) {
    error_log('cheating-incidents.php error: ' . $e->getMessage());
    sendError('An unexpected error occurred. Please try again later.', 500);
}
