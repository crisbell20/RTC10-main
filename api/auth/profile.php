<?php

// Profile API - Admin & CCMD

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../config/connection-pdo.php';
require_once __DIR__ . '/../includes/audit-log-utils.php';
require_once __DIR__ . '/../includes/person-name-utils.php';

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

$role = $_SESSION['user_role'] ?? '';
if (!in_array($role, ['Admin', 'CCMD'], true)) {
    sendError('Access denied. This resource is only available to administrators.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

$userId = (int)$_SESSION['user_id'];

// Update email
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $email = trim((string)($input['email'] ?? ''));
// Validate email
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendError('A valid email address is required');
    }
// Check email if already used
    try {
        $dupStmt = $pdo->prepare('SELECT User_ID FROM tbl_user WHERE Email = ? AND User_ID <> ? LIMIT 1');
        $dupStmt->execute([$email, $userId]);
        if ($dupStmt->fetch()) {
            sendError('That email address is already in use', 409);
        }

        $update = $pdo->prepare('UPDATE tbl_user SET Email = ? WHERE User_ID = ?');
        $update->execute([$email, $userId]);

        auditFromSession($pdo, 'USER', 'UPDATE_PROFILE', 'Updated email', 'SUCCESS', 'user', $userId);

        sendSuccess(['email' => $email, 'message' => 'Email updated successfully']);
    } catch (PDOException $e) {
        error_log('Staff profile update DB error: ' . $e->getMessage());
        sendError('Failed to update email', 500);
    }
}

try {
    $userStmt = $pdo->prepare('
        SELECT
            u.User_ID,
            ' . personNameSql('u') . ' AS Fullname,
            u.Firstname,
            u.Middlename,
            u.Lastname,
            u.Email,
            u.Status,
            u.Must_Change_Password,
            u.Date_Created,
            r.Role_Name
        FROM tbl_user u
        LEFT JOIN tbl_role r ON u.Role_ID = r.Role_ID
        WHERE u.User_ID = ?
        LIMIT 1
    ');
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

    // Get last login
    $lastLogin = null;
    if (auditLogTableExists($pdo)) {
        $loginStmt = $pdo->prepare('
            SELECT Timestamp
            FROM tbl_audit_log
            WHERE User_ID = ?
              AND Action = \'LOGIN\'
              AND Status = \'SUCCESS\'
            ORDER BY Timestamp DESC
            LIMIT 1
        ');
        $loginStmt->execute([$userId]);
        $loginRow = $loginStmt->fetch(PDO::FETCH_ASSOC);
        if ($loginRow && !empty($loginRow['Timestamp'])) {
            $lastLogin = date('Y-m-d H:i:s', strtotime($loginRow['Timestamp']));
        }
    }

    sendSuccess([
        'profile' => $user,
        'last_login' => $lastLogin,
    ]);
} catch (PDOException $e) {
    error_log('Staff profile DB error: ' . $e->getMessage());
    sendError('Failed to retrieve profile', 500);
} catch (Exception $e) {
    error_log('Staff profile error: ' . $e->getMessage());
    sendError('An error occurred while processing your request', 500);
}
