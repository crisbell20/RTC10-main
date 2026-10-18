<?php

//  Admin — Dashboard statistics

header('Content-Type: application/json');
session_start();

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/connection-pdo.php';
require_once __DIR__ . '/../includes/archive-utils.php';
require_once __DIR__ . '/../includes/cheating-log-utils.php';

if (!isset($pdo)) {
    if (isset($conn) && $conn instanceof PDO) {
        $pdo = $conn;
    } elseif (isset($dbh) && $dbh instanceof PDO) {
        $pdo = $dbh;
    } elseif (isset($db) && $db instanceof PDO) {
        $pdo = $db;
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection unavailable']);
        exit;
    }
}

try {
    $stats = [];

    // Active users (non-archived, Status = Active)
    $userActive = activeArchiveCondition($pdo, 'tbl_user', 'u');
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM tbl_user u WHERE u.Status = 'Active' AND {$userActive}");
    $stats['total_users'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Active courses (non-archived)
    $courseActive = activeArchiveCondition($pdo, 'tbl_course', 'c');
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM tbl_course c WHERE {$courseActive}");
    $stats['total_courses'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Active exams (published, not archived)
    $examActive = activeArchiveCondition($pdo, 'tbl_exam', 'e');
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM tbl_exam e WHERE e.Status = 'Published' AND {$examActive}");
    $stats['active_exams'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Cheating incidents (active exams only)
    $stats['total_incidents'] = 0;
    $stats['exams_with_incidents'] = 0;
    if (cheatingIncidentTableExists($pdo)) {
        $activeExamSql = activeArchiveCondition($pdo, 'tbl_exam', 'e');
        $stats['total_incidents'] = (int) $pdo->query("
            SELECT COUNT(*)
            FROM tbl_cheating_incident ci
            INNER JOIN tbl_exam_session es ON ci.Session_ID = es.Session_ID
            INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
            WHERE {$activeExamSql}
        ")->fetchColumn();
        $stats['exams_with_incidents'] = (int) $pdo->query("
            SELECT COUNT(DISTINCT es.Exam_ID)
            FROM tbl_cheating_incident ci
            INNER JOIN tbl_exam_session es ON ci.Session_ID = es.Session_ID
            INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
            WHERE {$activeExamSql}
        ")->fetchColumn();
    }

    // Users by role (non-archived)
    $roleUserActive = activeArchiveCondition($pdo, 'tbl_user', 'u');
    $stmt = $pdo->prepare("
        SELECT r.Role_Name, COUNT(u.User_ID) AS count
        FROM tbl_role r
        LEFT JOIN tbl_user u ON u.Role_ID = r.Role_ID AND {$roleUserActive}
        GROUP BY r.Role_ID, r.Role_Name
    ");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats['by_role'][$row['Role_Name']] = (int) $row['count'];
    }

    echo json_encode(['success' => true, 'stats' => $stats]);
} catch (Exception $e) {
    error_log('admin/stats.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred. Please try again later.']);
}
