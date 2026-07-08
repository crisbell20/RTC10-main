<?php
/**
 * CCMD Live Exam Monitoring API
 */

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'CCMD') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/connection-pdo.php';

$action = $_GET['action'] ?? '';

function sendSuccess($data) {
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

function sendError($message, $statusCode = 400) {
    http_response_code($statusCode);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function countAssignedExaminees(PDO $pdo, int $examId): int {
    try {
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'tbl_user_batch'");
        if (!$tableCheck || $tableCheck->rowCount() === 0) {
            return 0;
        }
    } catch (Exception $e) {
        return 0;
    }

    $stmt = $pdo->prepare('
        SELECT COUNT(DISTINCT ub.User_ID) AS total
        FROM tbl_exam_batch eb
        INNER JOIN tbl_user_batch ub ON eb.Batch_ID = ub.Batch_ID
        WHERE eb.Exam_ID = ?
          AND ub.Status = \'Active\'
    ');
    $stmt->execute([$examId]);
    return (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
}

function formatTimeRemaining(?string $timeStarted, ?int $durationMinutes): ?array {
    if (!$timeStarted || !$durationMinutes) {
        return null;
    }

    $deadline = strtotime($timeStarted) + ($durationMinutes * 60);
    $remaining = $deadline - time();

    if ($remaining <= 0) {
        return ['seconds' => 0, 'display' => '00:00:00', 'expired' => true];
    }

    $hours = floor($remaining / 3600);
    $minutes = floor(($remaining % 3600) / 60);
    $seconds = $remaining % 60;

    return [
        'seconds' => $remaining,
        'display' => sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds),
        'expired' => false,
    ];
}

try {
    if ($action === 'live_exams') {
        $stmt = $pdo->query("
            SELECT
                e.Exam_ID,
                e.Title AS Exam_Name,
                s.Subject_Name,
                c.Course_Name,
                e.Status,
                e.Duration,
                e.Deadline,
                COALESCE(SUM(CASE WHEN es.Time_Ended IS NULL AND es.Time_Started IS NOT NULL THEN 1 ELSE 0 END), 0) AS Active_Participants,
                COALESCE(SUM(CASE WHEN es.Time_Ended IS NOT NULL THEN 1 ELSE 0 END), 0) AS Completed_Participants,
                COUNT(DISTINCT es.Session_ID) AS Total_Sessions
            FROM tbl_exam e
            INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
            INNER JOIN tbl_course c ON s.Course_ID = c.Course_ID
            LEFT JOIN tbl_exam_session es ON e.Exam_ID = es.Exam_ID
            WHERE e.Status = 'Published'
            GROUP BY e.Exam_ID, e.Title, s.Subject_Name, c.Course_Name, e.Status, e.Duration, e.Deadline
            ORDER BY Active_Participants DESC, e.Deadline ASC
        ");
        sendSuccess($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($action === 'exam_summary') {
        $examId = (int)($_GET['exam_id'] ?? 0);
        if (!$examId) {
            sendError('exam_id is required');
        }

        $stmt = $pdo->prepare('
            SELECT e.Exam_ID, e.Title, e.Duration, e.Deadline, e.Status,
                   s.Subject_Name, c.Course_Name,
                   (SELECT COUNT(*) FROM tbl_exam_question eq WHERE eq.Exam_ID = e.Exam_ID) AS question_count
            FROM tbl_exam e
            INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
            INNER JOIN tbl_course c ON s.Course_ID = c.Course_ID
            WHERE e.Exam_ID = ?
        ');
        $stmt->execute([$examId]);
        $exam = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$exam) {
            sendError('Exam not found', 404);
        }

        $assigned = countAssignedExaminees($pdo, $examId);

        $stmt = $pdo->prepare('
            SELECT
                SUM(CASE WHEN es.Time_Ended IS NULL AND es.Time_Started IS NOT NULL THEN 1 ELSE 0 END) AS in_progress,
                SUM(CASE WHEN es.Time_Ended IS NOT NULL THEN 1 ELSE 0 END) AS completed
            FROM tbl_exam_session es
            WHERE es.Exam_ID = ?
        ');
        $stmt->execute([$examId]);
        $counts = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $inProgress = (int)($counts['in_progress'] ?? 0);
        $completed = (int)($counts['completed'] ?? 0);
        $notStarted = max(0, $assigned - $completed - $inProgress);

        $stmt = $pdo->prepare('
            SELECT COUNT(DISTINCT ci.Incident_ID) AS open_incidents
            FROM tbl_cheating_incident ci
            INNER JOIN tbl_exam_session es ON ci.Session_ID = es.Session_ID
            WHERE es.Exam_ID = ? AND ci.Status = \'Open\'
        ');
        $stmt->execute([$examId]);
        $openIncidents = (int)($stmt->fetch(PDO::FETCH_ASSOC)['open_incidents'] ?? 0);

        sendSuccess([
            'exam' => $exam,
            'assigned' => $assigned,
            'in_progress' => $inProgress,
            'completed' => $completed,
            'not_started' => $notStarted,
            'open_incidents' => $openIncidents,
        ]);
    }

    if ($action === 'live_sessions') {
        $examId = (int)($_GET['exam_id'] ?? 0);
        if (!$examId) {
            sendError('exam_id is required');
        }

        $search = trim($_GET['search'] ?? '');
        $statusFilter = $_GET['status'] ?? 'all';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(10, (int)($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $stmt = $pdo->prepare('
            SELECT e.Duration
            FROM tbl_exam e
            WHERE e.Exam_ID = ?
        ');
        $stmt->execute([$examId]);
        $examRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$examRow) {
            sendError('Exam not found', 404);
        }
        $duration = (int)($examRow['Duration'] ?? 0);
        $questionCount = 0;
        $qStmt = $pdo->prepare('SELECT COUNT(*) AS c FROM tbl_exam_question WHERE Exam_ID = ?');
        $qStmt->execute([$examId]);
        $questionCount = (int)($qStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

        $params = [$examId, $examId];
        $searchSql = '';
        if ($search !== '') {
            $searchSql = ' AND (u.Fullname LIKE ? OR u.Academic_Number LIKE ? OR u.Username LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "
            SELECT
                u.User_ID,
                u.Fullname,
                u.Academic_Number,
                u.Username,
                es.Session_ID,
                es.Time_Started,
                es.Time_Ended,
                (
                    SELECT GROUP_CONCAT(DISTINCT b.Batch_Name ORDER BY b.Batch_Name SEPARATOR ', ')
                    FROM tbl_exam_batch eb
                    INNER JOIN tbl_user_batch ub2 ON eb.Batch_ID = ub2.Batch_ID AND ub2.User_ID = u.User_ID AND ub2.Status = 'Active'
                    INNER JOIN tbl_batch b ON eb.Batch_ID = b.Batch_ID
                    WHERE eb.Exam_ID = ?
                ) AS batch_names,
                (
                    SELECT COUNT(*)
                    FROM tbl_answer a
                    WHERE a.Session_ID = es.Session_ID
                ) AS answered_count,
                (
                    SELECT COUNT(*)
                    FROM tbl_cheating_log cl
                    WHERE cl.Session_ID = es.Session_ID
                ) AS violation_count,
                (
                    SELECT COUNT(*)
                    FROM tbl_cheating_incident ci
                    WHERE ci.Session_ID = es.Session_ID AND ci.Status = 'Open'
                ) AS open_incident
            FROM tbl_exam_batch eb
            INNER JOIN tbl_user_batch ub ON eb.Batch_ID = ub.Batch_ID
            INNER JOIN tbl_user u ON ub.User_ID = u.User_ID
            LEFT JOIN tbl_exam_session es ON es.Exam_ID = eb.Exam_ID AND es.User_ID = u.User_ID
            WHERE eb.Exam_ID = ?
              AND ub.Status = 'Active'
              {$searchSql}
            GROUP BY u.User_ID, u.Fullname, u.Academic_Number, u.Username, es.Session_ID, es.Time_Started, es.Time_Ended
            ORDER BY
                CASE
                    WHEN es.Time_Ended IS NULL AND es.Time_Started IS NOT NULL THEN 0
                    WHEN es.Time_Ended IS NOT NULL THEN 2
                    ELSE 1
                END,
                u.Fullname ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $processed = [];
        foreach ($rows as $row) {
            if ($row['Time_Started'] && !$row['Time_Ended']) {
                $status = 'in_progress';
            } elseif ($row['Time_Ended']) {
                $status = 'completed';
            } else {
                $status = 'not_started';
            }

            if ($statusFilter !== 'all' && $status !== $statusFilter) {
                continue;
            }

            $answered = (int)($row['answered_count'] ?? 0);
            $progress = $questionCount > 0 ? round(($answered / $questionCount) * 100, 1) : 0;
            $timeRemaining = ($status === 'in_progress')
                ? formatTimeRemaining($row['Time_Started'], $duration)
                : null;

            $processed[] = [
                'User_ID' => (int)$row['User_ID'],
                'Fullname' => $row['Fullname'],
                'Academic_Number' => $row['Academic_Number'],
                'Username' => $row['Username'],
                'Session_ID' => $row['Session_ID'] ? (int)$row['Session_ID'] : null,
                'batch_names' => $row['batch_names'],
                'status' => $status,
                'answered_count' => $answered,
                'question_count' => $questionCount,
                'progress' => $progress,
                'time_started' => $row['Time_Started'],
                'time_ended' => $row['Time_Ended'],
                'time_remaining' => $timeRemaining,
                'violation_count' => (int)($row['violation_count'] ?? 0),
                'has_open_incident' => (int)($row['open_incident'] ?? 0) > 0,
            ];
        }

        $total = count($processed);
        $paged = array_slice($processed, $offset, $limit);

        sendSuccess([
            'rows' => $paged,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => max(1, (int)ceil($total / $limit)),
            ],
            'question_count' => $questionCount,
        ]);
    }

    if ($action === 'incidents') {
        $examId = !empty($_GET['exam_id']) ? (int)$_GET['exam_id'] : null;
        $limit = min(50, max(5, (int)($_GET['limit'] ?? 20)));

        $params = [];
        $examFilter = '';
        if ($examId) {
            $examFilter = ' AND es.Exam_ID = ?';
            $params[] = $examId;
        }
        $params[] = $limit;

        $stmt = $pdo->prepare("
            SELECT
                ci.Incident_ID,
                ci.Session_ID,
                ci.Reason,
                ci.Timestamp,
                ci.Status,
                u.Fullname AS Trainee_Name,
                u.Academic_Number,
                e.Exam_ID,
                e.Title AS Exam_Name,
                (
                    SELECT COUNT(*) FROM tbl_cheating_log cl WHERE cl.Session_ID = ci.Session_ID
                ) AS violation_count
            FROM tbl_cheating_incident ci
            INNER JOIN tbl_exam_session es ON ci.Session_ID = es.Session_ID
            INNER JOIN tbl_user u ON es.User_ID = u.User_ID
            INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
            WHERE 1=1 {$examFilter}
            ORDER BY ci.Timestamp DESC
            LIMIT ?
        ");
        $stmt->execute($params);
        sendSuccess($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    sendError('Invalid action');
} catch (PDOException $e) {
    error_log('CCMD monitor.php DB error: ' . $e->getMessage());
    sendError('Database error', 500);
} catch (Exception $e) {
    error_log('CCMD monitor.php error: ' . $e->getMessage());
    sendError('Server error', 500);
}
