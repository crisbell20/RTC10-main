<?php
header('Content-Type: application/json');
session_start();

// Require admin role
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/connection-pdo.php';
require_once __DIR__ . '/../includes/exam-code-utils.php';
require_once __DIR__ . '/../includes/archive-utils.php';

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

    // Total courses (treated as active courses)
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM tbl_course");
    $stats['total_courses'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Active exams (published, not archived)
    $examSql = "SELECT COUNT(*) AS total FROM tbl_exam WHERE Status = 'Published'";
    if (examCodeColumnsExist($pdo)) {
        $examSql .= " AND (Is_Archived = 0 OR Is_Archived IS NULL)";
    }
    $stmt = $pdo->query($examSql);
    $stats['active_exams'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stats['open_incidents'] = 0;
    try {
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'tbl_cheating_incident'");
        if ($tableCheck && $tableCheck->rowCount() > 0) {
            $stmt = $pdo->query("SELECT COUNT(*) AS total FROM tbl_cheating_incident WHERE Status = 'Open'");
            $stats['open_incidents'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        }
    } catch (Exception $e) {
        $stats['open_incidents'] = 0;
    }

    // Users by role
    $stmt = $pdo->prepare("
        SELECT r.Role_Name, COUNT(u.User_ID) AS count
        FROM tbl_role r
        LEFT JOIN tbl_user u ON u.Role_ID = r.Role_ID
        GROUP BY r.Role_ID, r.Role_Name
    ");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats['by_role'][$row['Role_Name']] = (int) $row['count'];
    }

    echo json_encode(['success' => true, 'stats' => $stats]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
