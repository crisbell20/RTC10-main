<?php

// CCMD - Enrolled Trainees API
 
header('Content-Type: application/json');
session_start();
// Check if user is CCMD
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'CCMD') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/connection-pdo.php';
require_once __DIR__ . '/../includes/archive-utils.php';
require_once __DIR__ . '/../includes/person-name-utils.php';

function sendJson(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

function userBatchTableExists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'tbl_user_batch'");
        $exists = $stmt && $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $exists = false;
    }

    return $exists;
}

// Match exams assigned
function assignedExamMatchSql(
    string $enrollBatchAlias = 'enroll_b',
    string $sectionAlias = 'sec',
    string $assignedBatchAlias = 'assigned_b',
    string $examBatchAlias = 'eb'
): string {
    return "(
        {$examBatchAlias}.Batch_ID = {$enrollBatchAlias}.Batch_ID
        OR {$assignedBatchAlias}.Section_ID = {$sectionAlias}.Section_ID
        OR (
            ({$assignedBatchAlias}.Section_ID IS NULL OR {$assignedBatchAlias}.Section_ID = 0)
            AND {$sectionAlias}.Batch_ID = {$assignedBatchAlias}.Batch_ID
        )
    )";
}

function assignedExamCountSql(PDO $pdo, string $enrollBatchAlias, string $sectionAlias): string
{
    $activeExam = activeArchiveCondition($pdo, 'tbl_exam', 'e');
    $matchSql = assignedExamMatchSql($enrollBatchAlias, $sectionAlias, 'assigned_b', 'eb');

    // Count exams assigned to the trainee
    return "(
        SELECT COUNT(DISTINCT e.Exam_ID)
        FROM tbl_exam e
        INNER JOIN tbl_exam_batch eb ON e.Exam_ID = eb.Exam_ID
        INNER JOIN tbl_batch assigned_b ON eb.Batch_ID = assigned_b.Batch_ID
        WHERE {$activeExam}
          AND e.Status IN ('Published', 'Closed')
          AND {$matchSql}
    )";
}

function buildEnrolledTraineesQuery(PDO $pdo, array $filters): array
{
    $assignedCount = assignedExamCountSql($pdo, 'enroll_b', 'sec');
    $userActive = activeArchiveCondition($pdo, 'tbl_user', 'u');

    $sql = "
        SELECT
            u.User_ID AS user_id,
            u.Personnel_Rank AS personnel_rank,
            " . personNameSql('u') . " AS fullname,
            u.Badge_Number AS badge_number,
            c.Course_ID AS course_id,
            c.Course_Name AS course_name,
            parent_b.Batch_ID AS batch_id,
            parent_b.Batch_Name AS batch_name,
            sec.Section_ID AS section_id,
            sec.Section_Name AS section_name,
            ub.Status AS enrollment_status,
            ub.Date_Enrolled AS date_enrolled,
            ub.User_Batch_ID AS user_batch_id,
            {$assignedCount} AS assigned_exam_count
        FROM tbl_user_batch ub
        INNER JOIN tbl_user u ON ub.User_ID = u.User_ID
        INNER JOIN tbl_role r ON u.Role_ID = r.Role_ID
        INNER JOIN tbl_batch enroll_b ON ub.Batch_ID = enroll_b.Batch_ID
        INNER JOIN tbl_academic_section sec ON enroll_b.Section_ID = sec.Section_ID
        INNER JOIN tbl_batch parent_b ON sec.Batch_ID = parent_b.Batch_ID
        INNER JOIN tbl_course c ON parent_b.Course_ID = c.Course_ID
        WHERE r.Role_Name = 'Examinee'
          AND {$userActive}
    ";
    $params = [];

    if (!empty($filters['course_id'])) {
        $sql .= ' AND c.Course_ID = ?';
        $params[] = (int)$filters['course_id'];
    }
    if (!empty($filters['batch_id'])) {
        $sql .= ' AND parent_b.Batch_ID = ?';
        $params[] = (int)$filters['batch_id'];
    }
    if (!empty($filters['section_id'])) {
        $sql .= ' AND sec.Section_ID = ?';
        $params[] = (int)$filters['section_id'];
    }
    if (!empty($filters['enrollment_status'])) {
        $sql .= ' AND ub.Status = ?';
        $params[] = $filters['enrollment_status'];
    }
    if (!empty($filters['search'])) {
        $sql .= ' AND (' . personNameSql('u') . ' LIKE ? OR u.Badge_Number LIKE ? OR u.Personnel_Rank LIKE ?)';
        $like = '%' . $filters['search'] . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    return [$sql, $params];
}

// List assigned exams for a trainee
function listAssignedExamsForTrainee(PDO $pdo, int $userId, int $sectionId): array
{
    if ($userId <= 0 || $sectionId <= 0) {
        return [];
    }

    $activeExam = activeArchiveCondition($pdo, 'tbl_exam', 'e');
    $matchSql = assignedExamMatchSql('enroll_b', 'sec', 'assigned_b', 'eb');

    $sql = "
        SELECT DISTINCT
            e.Exam_ID AS exam_id,
            e.Title AS exam_title,
            e.Status AS exam_status,
            (
                SELECT es.Time_Ended
                FROM tbl_exam_session es
                WHERE es.Exam_ID = e.Exam_ID AND es.User_ID = ?
                ORDER BY es.Session_ID DESC
                LIMIT 1
            ) AS last_submitted_at,
            (
                SELECT CASE
                    WHEN es2.Time_Ended IS NOT NULL THEN 'completed'
                    WHEN es2.Time_Started IS NOT NULL AND es2.Time_Ended IS NULL THEN 'in_progress'
                    ELSE 'not_started'
                END
                FROM tbl_exam_session es2
                WHERE es2.Exam_ID = e.Exam_ID AND es2.User_ID = ?
                ORDER BY es2.Session_ID DESC
                LIMIT 1
            ) AS session_status
        FROM tbl_exam e
        INNER JOIN tbl_exam_batch eb ON e.Exam_ID = eb.Exam_ID
        INNER JOIN tbl_batch assigned_b ON eb.Batch_ID = assigned_b.Batch_ID
        INNER JOIN tbl_user_batch ub ON ub.User_ID = ?
        INNER JOIN tbl_batch enroll_b ON ub.Batch_ID = enroll_b.Batch_ID
        INNER JOIN tbl_academic_section sec ON enroll_b.Section_ID = sec.Section_ID
        WHERE sec.Section_ID = ?
          AND {$activeExam}
          AND e.Status IN ('Published', 'Closed')
          AND {$matchSql}
        ORDER BY e.Schedule_Date DESC, e.Exam_ID DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $userId, $userId, $sectionId]);

    return array_map(static function (array $row): array {
        $row['session_status'] = $row['session_status'] ?? 'not_started';
        return $row;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

try {
    if (!userBatchTableExists($pdo)) {
        sendJson(['success' => false, 'message' => 'Assignment data is not available'], 503);
    }

    $action = $_GET['action'] ?? '';
    $requestMethod = $_SERVER['REQUEST_METHOD'];

    if ($requestMethod === 'GET' && ($action === 'list' || $action === 'export')) {
        $filters = [
            'course_id' => trim($_GET['course_id'] ?? ''),
            'batch_id' => trim($_GET['batch_id'] ?? ''),
            'section_id' => trim($_GET['section_id'] ?? ''),
            'enrollment_status' => trim($_GET['enrollment_status'] ?? ''),
            'search' => trim($_GET['search'] ?? ''),
        ];

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = $action === 'export'
            ? max(1, min(10000, (int)($_GET['limit'] ?? 5000)))
            : max(1, min(100, (int)($_GET['per_page'] ?? 25)));

        [$baseSql, $params] = buildEnrolledTraineesQuery($pdo, $filters);

        $countSql = 'SELECT COUNT(*) AS total FROM (' . $baseSql . ') AS roster';
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $offset = $action === 'export' ? 0 : ($page - 1) * $perPage;
        $listSql = $baseSql . ' ORDER BY c.Course_Name, parent_b.Batch_Name, sec.Section_Name, ' . personNameSql('u')
            . ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;

        $stmt = $pdo->prepare($listSql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['assigned_exam_count'] = (int)($row['assigned_exam_count'] ?? 0);
        }
        unset($row);

        sendJson([
            'success' => true,
            'data' => $rows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 0,
            ],
        ]);
    }

    if ($requestMethod === 'GET' && $action === 'assigned_exams') {
        $userId = (int)($_GET['user_id'] ?? 0);
        $sectionId = (int)($_GET['section_id'] ?? 0);

        if (!$userId || !$sectionId) {
            sendJson(['success' => false, 'message' => 'user_id and section_id are required'], 400);
        }

        $userStmt = $pdo->prepare('
            SELECT u.User_ID, ' . personNameSql('u') . ' AS Fullname, u.Badge_Number
            FROM tbl_user u
            INNER JOIN tbl_role r ON u.Role_ID = r.Role_ID
            WHERE u.User_ID = ? AND r.Role_Name = \'Examinee\'
            LIMIT 1
        ');
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            sendJson(['success' => false, 'message' => 'Trainee not found'], 404);
        }

        sendJson([
            'success' => true,
            'data' => [
                'trainee' => $user,
                'exams' => listAssignedExamsForTrainee($pdo, $userId, $sectionId),
            ],
        ]);
    }

    sendJson(['success' => false, 'message' => 'Invalid action'], 400);
} catch (Exception $e) {
    error_log('CCMD enrolled-trainees.php error: ' . $e->getMessage());
    sendJson(['success' => false, 'message' => 'An unexpected error occurred. Please try again later.'], 500);
}
