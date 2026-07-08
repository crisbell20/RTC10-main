<?php
/**
 * Examinee Profile API
 * Read-only profile data for the logged-in trainee (own record only).
 */

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../config/connection-pdo.php';
require_once __DIR__ . '/../includes/exam-code-utils.php';
require_once __DIR__ . '/../includes/audit-log-utils.php';

function sendSuccess($data) {
    http_response_code(200);
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

function sendError($message, $statusCode = 400) {
    http_response_code($statusCode);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    sendError('Unauthorized. Please log in.', 401);
}

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Examinee') {
    sendError('Access denied. This resource is only available to examinees.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

$userId = (int)$_SESSION['user_id'];

try {
    $rankCol = personnelRankColumnExists($pdo) ? 'u.Personnel_Rank,' : '';

    $userStmt = $pdo->prepare("
        SELECT
            u.User_ID,
            u.Fullname,
            u.Email,
            u.Username,
            u.Academic_Number,
            {$rankCol}
            u.Status,
            u.Must_Change_Password,
            u.Date_Created,
            r.Role_Name
        FROM tbl_user u
        LEFT JOIN tbl_role r ON u.Role_ID = r.Role_ID
        WHERE u.User_ID = ?
        LIMIT 1
    ");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendError('User not found', 404);
    }

    unset($user['User_ID']);

    $user['Must_Change_Password'] = (int)($user['Must_Change_Password'] ?? 0);
    if ($user['Date_Created']) {
        $user['Date_Created'] = date('Y-m-d H:i:s', strtotime($user['Date_Created']));
    }

    $enrollments = [];
    try {
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'tbl_user_batch'");
        if ($tableCheck && $tableCheck->rowCount() > 0) {
            $batchStmt = $pdo->prepare("
                SELECT
                    b.Batch_ID,
                    b.Batch_Name,
                    b.Date_Started,
                    b.Date_Ended,
                    b.Status AS Batch_Status,
                    s.Section_Name,
                    c.Course_Name,
                    ub.Status AS Enrollment_Status,
                    ub.Date_Enrolled
                FROM tbl_user_batch ub
                INNER JOIN tbl_batch b ON ub.Batch_ID = b.Batch_ID
                LEFT JOIN tbl_academic_section s ON b.Section_ID = s.Section_ID
                INNER JOIN tbl_course c ON b.Course_ID = c.Course_ID
                WHERE ub.User_ID = ?
                ORDER BY ub.Date_Enrolled DESC
            ");
            $batchStmt->execute([$userId]);
            $enrollments = $batchStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($enrollments as &$row) {
                if (!empty($row['Date_Enrolled'])) {
                    $row['Date_Enrolled'] = date('Y-m-d H:i:s', strtotime($row['Date_Enrolled']));
                }
            }
            unset($row);
        }
    } catch (PDOException $e) {
        error_log('Profile enrollments query error: ' . $e->getMessage());
    }

    $statsStmt = $pdo->prepare("
        SELECT
            COUNT(DISTINCT es.Session_ID) AS exams_completed,
            AVG(r.Percentage) AS average_score,
            SUM(CASE WHEN r.Remarks = 'Passed' THEN 1 ELSE 0 END) AS passed_count,
            SUM(CASE WHEN r.Remarks = 'Failed' THEN 1 ELSE 0 END) AS failed_count
        FROM tbl_exam_session es
        LEFT JOIN tbl_result r ON r.Session_ID = es.Session_ID
        WHERE es.User_ID = ?
        AND es.Time_Ended IS NOT NULL
    ");
    $statsStmt->execute([$userId]);
    $statsRow = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $availableCount = 0;
    try {
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'tbl_user_batch'");
        if ($tableCheck && $tableCheck->rowCount() > 0) {
            $hasExamCode = false;
            try {
                $colCheck = $pdo->query("SHOW COLUMNS FROM tbl_exam LIKE 'Exam_Code'");
                $hasExamCode = $colCheck && $colCheck->rowCount() > 0;
            } catch (Exception $e) {
                $hasExamCode = false;
            }
            $archivedFilter = $hasExamCode ? 'AND (e.Is_Archived = 0 OR e.Is_Archived IS NULL)' : '';

            $availableStmt = $pdo->prepare("
                SELECT COUNT(DISTINCT e.Exam_ID) AS count
                FROM tbl_exam e
                INNER JOIN tbl_exam_batch eb ON e.Exam_ID = eb.Exam_ID
                INNER JOIN tbl_batch b ON eb.Batch_ID = b.Batch_ID
                INNER JOIN tbl_user_batch ub ON b.Batch_ID = ub.Batch_ID
                LEFT JOIN tbl_exam_session es ON e.Exam_ID = es.Exam_ID AND es.User_ID = ?
                WHERE e.Status = 'Published'
                {$archivedFilter}
                AND ub.User_ID = ?
                AND ub.Status = 'Active'
                AND (es.Session_ID IS NULL OR es.Time_Ended IS NULL)
            ");
            $availableStmt->execute([$userId, $userId]);
            $availableCount = (int)($availableStmt->fetch()['count'] ?? 0);
        }
    } catch (PDOException $e) {
        error_log('Profile available exams query error: ' . $e->getMessage());
    }

    $lastExamStmt = $pdo->prepare("
        SELECT
            e.Title AS exam_title,
            r.Submission_Date,
            r.Percentage,
            r.Remarks
        FROM tbl_result r
        INNER JOIN tbl_exam_session es ON r.Session_ID = es.Session_ID
        INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
        WHERE es.User_ID = ?
        ORDER BY r.Submission_Date DESC
        LIMIT 1
    ");
    $lastExamStmt->execute([$userId]);
    $lastExam = $lastExamStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($lastExam && !empty($lastExam['Submission_Date'])) {
        $lastExam['Submission_Date'] = date('Y-m-d H:i:s', strtotime($lastExam['Submission_Date']));
    }

    $lastLogin = null;
    if (auditLogTableExists($pdo)) {
        $loginStmt = $pdo->prepare("
            SELECT Timestamp
            FROM tbl_audit_log
            WHERE User_ID = ?
            AND Action = 'LOGIN'
            AND Status = 'SUCCESS'
            ORDER BY Timestamp DESC
            LIMIT 1
        ");
        $loginStmt->execute([$userId]);
        $loginRow = $loginStmt->fetch(PDO::FETCH_ASSOC);
        if ($loginRow && !empty($loginRow['Timestamp'])) {
            $lastLogin = date('Y-m-d H:i:s', strtotime($loginRow['Timestamp']));
        }
    }

    $completed = (int)($statsRow['exams_completed'] ?? 0);
    $averageScore = round((float)($statsRow['average_score'] ?? 0), 1);

    sendSuccess([
        'profile' => $user,
        'enrollments' => $enrollments,
        'exam_summary' => [
            'exams_available' => $availableCount,
            'exams_completed' => $completed,
            'average_score' => $averageScore,
            'passed_count' => (int)($statsRow['passed_count'] ?? 0),
            'failed_count' => (int)($statsRow['failed_count'] ?? 0),
            'completion_rate' => ($completed + $availableCount) > 0
                ? round(($completed / ($completed + $availableCount)) * 100, 1)
                : 0,
            'last_exam' => $lastExam,
        ],
        'last_login' => $lastLogin,
    ]);
} catch (PDOException $e) {
    error_log('Database error in profile.php: ' . $e->getMessage());
    sendError('Database error: Failed to retrieve profile', 500);
} catch (Exception $e) {
    error_log('Error in profile.php: ' . $e->getMessage());
    sendError('An error occurred while processing your request', 500);
}
