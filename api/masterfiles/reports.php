<?php
/**
 * Reports & Analytics API — overview, student summary, subject summary, at-risk.
 */

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'CCMD'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/connection-pdo.php';
require_once __DIR__ . '/../includes/audit-log-utils.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function sendSuccess($data) {
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

function sendError($message, $statusCode = 400) {
    http_response_code($statusCode);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function buildResultFilters(array $get): array {
    $dateRange = $get['date_range'] ?? '30';
    $courseId = !empty($get['course_id']) ? (int)$get['course_id'] : null;
    $subjectId = !empty($get['subject_id']) ? (int)$get['subject_id'] : null;
    $batchId = !empty($get['batch_id']) ? (int)$get['batch_id'] : null;
    $search = trim($get['search'] ?? '');

    $where = ['es.Time_Ended IS NOT NULL'];
    $params = [];

    if ($dateRange !== 'all') {
        $days = (int)$dateRange;
        if ($days > 0) {
            $where[] = 'r.Submission_Date >= DATE_SUB(NOW(), INTERVAL ? DAY)';
            $params[] = $days;
        }
    }
    if ($courseId) {
        $where[] = 'c.Course_ID = ?';
        $params[] = $courseId;
    }
    if ($subjectId) {
        $where[] = 's.Subject_ID = ?';
        $params[] = $subjectId;
    }
    if ($batchId) {
        $where[] = 'es.User_ID IN (
            SELECT ub.User_ID FROM tbl_user_batch ub
            WHERE ub.Batch_ID = ? AND ub.Status = \'Active\'
        )';
        $params[] = $batchId;
    }
    if ($search !== '') {
        $where[] = '(u.Fullname LIKE ? OR u.Academic_Number LIKE ? OR u.Username LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    return [
        'sql' => implode(' AND ', $where),
        'params' => $params,
        'needs_user' => $search !== '' || $batchId !== null
    ];
}

function baseResultJoins(bool $needsUser = false): string {
    $userJoin = $needsUser ? 'INNER JOIN tbl_user u ON es.User_ID = u.User_ID' : '';
    return "
        FROM tbl_result r
        INNER JOIN tbl_exam_session es ON r.Session_ID = es.Session_ID
        INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
        INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
        INNER JOIN tbl_course c ON s.Course_ID = c.Course_ID
        {$userJoin}
    ";
}

function studentStatus(int $examsTaken, int $passedCount, float $avgPercentage): string {
    if ($examsTaken === 0) {
        return 'No data';
    }
    $passRate = ($passedCount / $examsTaken) * 100;
    $failedCount = $examsTaken - $passedCount;
    if ($passRate < 60 || $failedCount >= 2 || $avgPercentage < 60) {
        return 'At risk';
    }
    if ($passRate < 80) {
        return 'Needs review';
    }
    return 'On track';
}

function attachSubjectHighlights(PDO $pdo, array $filters, array &$students): void {
    if (empty($students)) {
        return;
    }
    $filter = buildResultFilters($filters);
    $sql = '
        SELECT es.User_ID, s.Subject_Name, ROUND(AVG(r.Percentage), 1) AS avg_percentage
        FROM tbl_result r
        INNER JOIN tbl_exam_session es ON r.Session_ID = es.Session_ID
        INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
        INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
        INNER JOIN tbl_course c ON s.Course_ID = c.Course_ID
        INNER JOIN tbl_user u ON es.User_ID = u.User_ID
        WHERE ' . $filter['sql'] . '
        GROUP BY es.User_ID, s.Subject_ID, s.Subject_Name
    ';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($filter['params']);
    $byUser = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $byUser[(int)$row['User_ID']][] = $row;
    }
    foreach ($students as &$student) {
        $subjects = $byUser[(int)$student['User_ID']] ?? [];
        $student['best_subject'] = null;
        $student['weakest_subject'] = null;
        if (!$subjects) {
            continue;
        }
        usort($subjects, fn($a, $b) => (float)$b['avg_percentage'] <=> (float)$a['avg_percentage']);
        $student['best_subject'] = $subjects[0]['Subject_Name'];
        $student['weakest_subject'] = $subjects[count($subjects) - 1]['Subject_Name'];
    }
    unset($student);
}

try {
    if ($action === 'batches') {
        $courseId = !empty($_GET['course_id']) ? (int)$_GET['course_id'] : null;
        $sql = 'SELECT Batch_ID, Batch_Name, Course_ID FROM tbl_batch WHERE Status = \'Active\'';
        $params = [];
        if ($courseId) {
            $sql .= ' AND Course_ID = ?';
            $params[] = $courseId;
        }
        $sql .= ' ORDER BY Batch_Name ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        sendSuccess($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($action === 'metrics') {
        $filter = buildResultFilters($_GET);
        $joins = baseResultJoins($filter['needs_user']);
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT es.User_ID) AS active_examinees,
                   COUNT(DISTINCT e.Exam_ID) AS exams_administered,
                   COUNT(r.Result_ID) AS finished_submissions,
                   ROUND(AVG(r.Percentage), 1) AS avg_score,
                   SUM(CASE WHEN r.Percentage >= e.Passing_Score THEN 1 ELSE 0 END) AS passed_count
            {$joins} WHERE {$filter['sql']}
        ");
        $stmt->execute($filter['params']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $finished = (int)($row['finished_submissions'] ?? 0);
        $passed = (int)($row['passed_count'] ?? 0);
        sendSuccess([
            'active_examinees' => (int)($row['active_examinees'] ?? 0),
            'exams_administered' => (int)($row['exams_administered'] ?? 0),
            'finished_submissions' => $finished,
            'avg_score' => (float)($row['avg_score'] ?? 0),
            'pass_rate' => $finished > 0 ? round(($passed / $finished) * 100, 1) : 0,
            'passed_count' => $passed,
            'failed_count' => $finished - $passed
        ]);
    }

    if ($action === 'performance_trend') {
        $filter = buildResultFilters($_GET);
        $joins = baseResultJoins($filter['needs_user']);
        $stmt = $pdo->prepare("
            SELECT DATE(r.Submission_Date) AS date, ROUND(AVG(r.Percentage), 1) AS avg_score, COUNT(*) AS submission_count
            {$joins} WHERE {$filter['sql']}
            GROUP BY DATE(r.Submission_Date) ORDER BY date ASC
        ");
        $stmt->execute($filter['params']);
        sendSuccess($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($action === 'pass_fail') {
        $filter = buildResultFilters($_GET);
        $joins = baseResultJoins($filter['needs_user']);
        $stmt = $pdo->prepare("
            SELECT SUM(CASE WHEN r.Percentage >= e.Passing_Score THEN 1 ELSE 0 END) AS passed,
                   SUM(CASE WHEN r.Percentage < e.Passing_Score THEN 1 ELSE 0 END) AS failed
            {$joins} WHERE {$filter['sql']}
        ");
        $stmt->execute($filter['params']);
        sendSuccess($stmt->fetch(PDO::FETCH_ASSOC));
    }

    if ($action === 'top_subjects') {
        $filter = buildResultFilters($_GET);
        $joins = baseResultJoins($filter['needs_user']);
        $stmt = $pdo->prepare("
            SELECT s.Subject_Name, ROUND(AVG(r.Percentage), 1) AS avg_score,
                   ROUND(SUM(CASE WHEN r.Percentage >= e.Passing_Score THEN 1 ELSE 0 END) / COUNT(*) * 100, 1) AS pass_rate,
                   COUNT(*) AS attempt_count
            {$joins} WHERE {$filter['sql']}
            GROUP BY s.Subject_ID, s.Subject_Name HAVING attempt_count >= 1
            ORDER BY avg_score DESC LIMIT 10
        ");
        $stmt->execute($filter['params']);
        sendSuccess($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($action === 'weak_subjects') {
        $filter = buildResultFilters($_GET);
        $joins = baseResultJoins($filter['needs_user']);
        $stmt = $pdo->prepare("
            SELECT s.Subject_Name, ROUND(AVG(r.Percentage), 1) AS avg_score,
                   ROUND(SUM(CASE WHEN r.Percentage >= e.Passing_Score THEN 1 ELSE 0 END) / COUNT(*) * 100, 1) AS pass_rate,
                   COUNT(*) AS attempt_count
            {$joins} WHERE {$filter['sql']}
            GROUP BY s.Subject_ID, s.Subject_Name HAVING attempt_count >= 3
            ORDER BY pass_rate ASC, avg_score ASC LIMIT 5
        ");
        $stmt->execute($filter['params']);
        sendSuccess($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($action === 'participation') {
        $filter = buildResultFilters($_GET);
        $joins = baseResultJoins($filter['needs_user']);
        $stmt = $pdo->prepare("
            SELECT e.Exam_ID, e.Title AS exam_title, COUNT(DISTINCT es.User_ID) AS participant_count,
                   ROUND(AVG(r.Percentage), 1) AS avg_score
            {$joins} WHERE {$filter['sql']}
            GROUP BY e.Exam_ID, e.Title ORDER BY participant_count DESC LIMIT 10
        ");
        $stmt->execute($filter['params']);
        sendSuccess($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($action === 'student_summary') {
        $filter = buildResultFilters($_GET);
        $statusFilter = $_GET['student_status'] ?? 'all';
        $sql = "
            SELECT u.User_ID, u.Fullname, u.Academic_Number,
                (SELECT GROUP_CONCAT(DISTINCT b.Batch_Name ORDER BY b.Batch_Name SEPARATOR ', ')
                 FROM tbl_user_batch ub INNER JOIN tbl_batch b ON ub.Batch_ID = b.Batch_ID
                 WHERE ub.User_ID = u.User_ID AND ub.Status = 'Active') AS batch_names,
                COUNT(r.Result_ID) AS exams_taken, ROUND(AVG(r.Percentage), 1) AS avg_percentage,
                SUM(CASE WHEN r.Percentage >= e.Passing_Score THEN 1 ELSE 0 END) AS passed_count,
                SUM(CASE WHEN r.Percentage < e.Passing_Score THEN 1 ELSE 0 END) AS failed_count,
                MAX(r.Submission_Date) AS last_submission
            FROM tbl_result r
            INNER JOIN tbl_exam_session es ON r.Session_ID = es.Session_ID
            INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
            INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
            INNER JOIN tbl_course c ON s.Course_ID = c.Course_ID
            INNER JOIN tbl_user u ON es.User_ID = u.User_ID
            WHERE {$filter['sql']}
            GROUP BY u.User_ID, u.Fullname, u.Academic_Number
            ORDER BY avg_percentage DESC, passed_count DESC, u.Fullname ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($filter['params']);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        attachSubjectHighlights($pdo, $_GET, $students);
        foreach ($students as &$student) {
            $examsTaken = (int)$student['exams_taken'];
            $passedCount = (int)$student['passed_count'];
            $avg = (float)$student['avg_percentage'];
            $student['pass_rate'] = $examsTaken > 0 ? round(($passedCount / $examsTaken) * 100, 1) : 0;
            $student['status'] = studentStatus($examsTaken, $passedCount, $avg);
        }
        unset($student);
        if ($statusFilter === 'at_risk') {
            $students = array_values(array_filter($students, fn($s) => $s['status'] === 'At risk'));
        } elseif ($statusFilter === 'on_track') {
            $students = array_values(array_filter($students, fn($s) => $s['status'] === 'On track'));
        } elseif ($statusFilter === 'needs_review') {
            $students = array_values(array_filter($students, fn($s) => $s['status'] === 'Needs review'));
        }
        sendSuccess($students);
    }

    if ($action === 'at_risk') {
        $_GET['student_status'] = 'at_risk';
        $filter = buildResultFilters($_GET);
        $stmt = $pdo->prepare("
            SELECT u.User_ID, u.Fullname, u.Academic_Number, COUNT(r.Result_ID) AS exams_taken,
                   ROUND(AVG(r.Percentage), 1) AS avg_percentage,
                   SUM(CASE WHEN r.Percentage >= e.Passing_Score THEN 1 ELSE 0 END) AS passed_count,
                   SUM(CASE WHEN r.Percentage < e.Passing_Score THEN 1 ELSE 0 END) AS failed_count
            FROM tbl_result r
            INNER JOIN tbl_exam_session es ON r.Session_ID = es.Session_ID
            INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
            INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
            INNER JOIN tbl_course c ON s.Course_ID = c.Course_ID
            INNER JOIN tbl_user u ON es.User_ID = u.User_ID
            WHERE {$filter['sql']}
            GROUP BY u.User_ID, u.Fullname, u.Academic_Number
        ");
        $stmt->execute($filter['params']);
        $atRisk = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $examsTaken = (int)$row['exams_taken'];
            $passedCount = (int)$row['passed_count'];
            $status = studentStatus($examsTaken, $passedCount, (float)$row['avg_percentage']);
            if ($status === 'At risk') {
                $row['pass_rate'] = $examsTaken > 0 ? round(($passedCount / $examsTaken) * 100, 1) : 0;
                $row['status'] = $status;
                $atRisk[] = $row;
            }
        }
        usort($atRisk, fn($a, $b) => (float)$a['avg_percentage'] <=> (float)$b['avg_percentage']);
        sendSuccess(array_slice($atRisk, 0, 10));
    }

    if ($action === 'student_detail') {
        $userId = (int)($_GET['user_id'] ?? 0);
        if (!$userId) {
            sendError('user_id is required');
        }
        $filter = buildResultFilters($_GET);
        $stmt = $pdo->prepare('SELECT User_ID, Fullname, Academic_Number, Username FROM tbl_user WHERE User_ID = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            sendError('Student not found', 404);
        }
        $stmt = $pdo->prepare("
            SELECT r.Result_ID, r.Score, r.Percentage, r.Remarks, r.Submission_Date,
                   e.Exam_ID, e.Title AS exam_title, e.Passing_Score, s.Subject_Name, c.Course_Name,
                   CASE WHEN r.Percentage >= e.Passing_Score THEN 'Passed' ELSE 'Failed' END AS status
            FROM tbl_result r
            INNER JOIN tbl_exam_session es ON r.Session_ID = es.Session_ID
            INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
            INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
            INNER JOIN tbl_course c ON s.Course_ID = c.Course_ID
            WHERE es.User_ID = ? AND {$filter['sql']}
            ORDER BY r.Submission_Date DESC
        ");
        $stmt->execute(array_merge([$userId], $filter['params']));
        $attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $examsTaken = count($attempts);
        $passedCount = count(array_filter($attempts, fn($a) => $a['status'] === 'Passed'));
        $avg = $examsTaken > 0 ? round(array_sum(array_column($attempts, 'Percentage')) / $examsTaken, 1) : 0;
        sendSuccess([
            'student' => $user,
            'summary' => [
                'exams_taken' => $examsTaken,
                'avg_percentage' => $avg,
                'pass_rate' => $examsTaken > 0 ? round(($passedCount / $examsTaken) * 100, 1) : 0,
                'status' => studentStatus($examsTaken, $passedCount, (float)$avg)
            ],
            'attempts' => $attempts
        ]);
    }

    if ($action === 'subject_summary') {
        $filter = buildResultFilters($_GET);
        $joins = baseResultJoins($filter['needs_user']);
        $stmt = $pdo->prepare("
            SELECT s.Subject_ID, s.Subject_Name, s.Subject_Code, c.Course_Name,
                   COUNT(r.Result_ID) AS attempts, COUNT(DISTINCT es.User_ID) AS students,
                   COUNT(DISTINCT e.Exam_ID) AS exams, ROUND(AVG(r.Percentage), 1) AS avg_percentage,
                   ROUND(SUM(CASE WHEN r.Percentage >= e.Passing_Score THEN 1 ELSE 0 END) / COUNT(*) * 100, 1) AS pass_rate,
                   ROUND(MAX(r.Percentage), 1) AS highest, ROUND(MIN(r.Percentage), 1) AS lowest
            {$joins} WHERE {$filter['sql']}
            GROUP BY s.Subject_ID, s.Subject_Name, s.Subject_Code, c.Course_Name
            ORDER BY pass_rate ASC, avg_percentage ASC
        ");
        $stmt->execute($filter['params']);
        sendSuccess($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($action === 'subject_detail') {
        $subjectId = (int)($_GET['subject_id'] ?? 0);
        if (!$subjectId) {
            sendError('subject_id is required');
        }
        $filter = buildResultFilters($_GET);
        $extraWhere = $filter['sql'] . ' AND s.Subject_ID = ?';
        $params = array_merge($filter['params'], [$subjectId]);
        $stmt = $pdo->prepare('
            SELECT s.Subject_ID, s.Subject_Name, s.Subject_Code, c.Course_Name
            FROM tbl_subject s INNER JOIN tbl_course c ON s.Course_ID = c.Course_ID WHERE s.Subject_ID = ?
        ');
        $stmt->execute([$subjectId]);
        $subject = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$subject) {
            sendError('Subject not found', 404);
        }
        $joins = baseResultJoins($filter['needs_user']);
        $stmt = $pdo->prepare("
            SELECT e.Exam_ID, e.Title AS exam_title, COUNT(r.Result_ID) AS attempts,
                   COUNT(DISTINCT es.User_ID) AS students, ROUND(AVG(r.Percentage), 1) AS avg_percentage,
                   ROUND(SUM(CASE WHEN r.Percentage >= e.Passing_Score THEN 1 ELSE 0 END) / COUNT(*) * 100, 1) AS pass_rate
            {$joins} WHERE {$extraWhere}
            GROUP BY e.Exam_ID, e.Title ORDER BY avg_percentage ASC
        ");
        $stmt->execute($params);
        sendSuccess(['subject' => $subject, 'exams' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($action === 'detailed_results') {
        $filter = buildResultFilters($_GET);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;
        $joins = "
            FROM tbl_result r
            INNER JOIN tbl_exam_session es ON r.Session_ID = es.Session_ID
            INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
            INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
            INNER JOIN tbl_course c ON s.Course_ID = c.Course_ID
            INNER JOIN tbl_user u ON es.User_ID = u.User_ID
        ";
        $stmt = $pdo->prepare("SELECT COUNT(*) AS total {$joins} WHERE {$filter['sql']}");
        $stmt->execute($filter['params']);
        $total = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        $stmt = $pdo->prepare("
            SELECT r.Result_ID, u.User_ID, u.Fullname AS examinee_name, e.Exam_ID, e.Title AS exam_title,
                   s.Subject_Name, r.Submission_Date, r.Score, r.Percentage,
                   CASE WHEN r.Percentage >= e.Passing_Score THEN 'Passed' ELSE 'Failed' END AS status, e.Passing_Score
            {$joins} WHERE {$filter['sql']}
            ORDER BY r.Submission_Date DESC LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($filter['params']);
        sendSuccess([
            'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'pages' => max(1, (int)ceil($total / $limit))]
        ]);
    }

    if ($action === 'log_export') {
        $raw = json_decode(file_get_contents('php://input'), true) ?? [];
        $reportType = trim((string)($raw['report_type'] ?? $_POST['report_type'] ?? 'overview'));
        $rowCount = max(0, (int)($raw['row_count'] ?? $_POST['row_count'] ?? 0));
        auditFromSession(
            $pdo,
            'REPORT',
            'EXPORT_REPORT',
            "Exported {$reportType} report CSV ({$rowCount} rows)",
            'SUCCESS',
            'report',
            null
        );
        sendSuccess(['logged' => true]);
    }

    sendError('Invalid action');
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
