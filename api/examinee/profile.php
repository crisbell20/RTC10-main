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

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? [];
    $email = trim((string)($input['email'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendError('A valid email address is required');
    }

    try {
        $dupStmt = $pdo->prepare('SELECT User_ID FROM tbl_user WHERE Email = ? AND User_ID <> ? LIMIT 1');
        $dupStmt->execute([$email, $userId]);
        if ($dupStmt->fetch()) {
            sendError('That email address is already in use', 409);
        }

        $nameStmt = $pdo->prepare('SELECT Fullname FROM tbl_user WHERE User_ID = ? LIMIT 1');
        $nameStmt->execute([$userId]);
        $fullName = (string)($nameStmt->fetchColumn() ?: 'Trainee');

        $update = $pdo->prepare('UPDATE tbl_user SET Email = ? WHERE User_ID = ?');
        $update->execute([$email, $userId]);

        auditFromSession($pdo, 'USER', 'UPDATE_PROFILE', "{$fullName} updated email", 'SUCCESS', 'user', $userId);

        sendSuccess(['email' => $email, 'message' => 'Email updated successfully']);
    } catch (PDOException $e) {
        error_log('Profile update DB error: ' . $e->getMessage());
        sendError('Failed to update email', 500);
    }
}

try {
    $rankCol = personnelRankColumnExists($pdo) ? 'u.Personnel_Rank,' : '';

    $userStmt = $pdo->prepare("
        SELECT
            u.User_ID,
            u.Fullname,
            u.Email,
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
                    COALESCE(pb.Batch_Name, b.Batch_Name) AS Batch_Name,
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
                LEFT JOIN tbl_batch pb ON s.Batch_ID = pb.Batch_ID
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

    sendSuccess([
        'profile' => $user,
        'enrollments' => $enrollments,
        'last_login' => $lastLogin,
    ]);
} catch (PDOException $e) {
    error_log('Database error in profile.php: ' . $e->getMessage());
    sendError('Database error: Failed to retrieve profile', 500);
} catch (Exception $e) {
    error_log('Error in profile.php: ' . $e->getMessage());
    sendError('An error occurred while processing your request', 500);
}
