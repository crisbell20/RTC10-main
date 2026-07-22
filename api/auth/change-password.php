<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/connection-pdo.php';
require_once __DIR__ . '/../includes/audit-log-utils.php';
require_once __DIR__ . '/../includes/password-utils.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim((string)($input['action'] ?? ''));

if ($action === 'dismiss_must_change') {
    if (($_SESSION['user_role'] ?? '') !== 'Examinee') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only examinees may dismiss the password change prompt']);
        exit;
    }

    try {
        $stmt = $pdo->prepare('SELECT User_ID, Fullname, Must_Change_Password FROM tbl_user WHERE User_ID = ? LIMIT 1');
        $stmt->execute([(int)$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }

        if ((int)($user['Must_Change_Password'] ?? 0) !== 1) {
            $_SESSION['must_change_password'] = 0;
            echo json_encode(['success' => true, 'message' => 'Password change is not required']);
            exit;
        }

        $update = $pdo->prepare('UPDATE tbl_user SET Must_Change_Password = 0 WHERE User_ID = ?');
        $update->execute([(int)$user['User_ID']]);

        $_SESSION['must_change_password'] = 0;

        auditFromSession(
            $pdo,
            'AUTH',
            'PASSWORD_CHANGE_DISMISSED',
            "{$user['Fullname']} dismissed first-login password change",
            'SUCCESS',
            'user',
            (int)$user['User_ID']
        );

        echo json_encode(['success' => true, 'message' => 'You can change your password later from your profile.']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

$currentPassword = $input['current_password'] ?? '';
$newPassword = $input['new_password'] ?? '';
$confirmPassword = $input['confirm_password'] ?? '';

if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'All password fields are required']);
    exit;
}

if ($newPassword !== $confirmPassword) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'New password and confirmation do not match']);
    exit;
}

$passwordError = validatePasswordPolicy($newPassword);
if ($passwordError !== null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $passwordError]);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT User_ID, Fullname, Password_Hash FROM tbl_user WHERE User_ID = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    if (!password_verify($currentPassword, $user['Password_Hash'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit;
    }

    if (password_verify($newPassword, $user['Password_Hash'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'New password must be different from your current password']);
        exit;
    }

    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $update = $pdo->prepare('UPDATE tbl_user SET Password_Hash = ?, Must_Change_Password = 0 WHERE User_ID = ?');
    $update->execute([$newHash, $user['User_ID']]);

    $_SESSION['must_change_password'] = 0;

    auditFromSession($pdo, 'AUTH', 'PASSWORD_CHANGE', "{$user['Fullname']} changed password", 'SUCCESS', 'user', (int)$user['User_ID']);

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
