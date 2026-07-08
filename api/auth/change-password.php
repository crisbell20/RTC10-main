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

$input = json_decode(file_get_contents('php://input'), true) ?? [];

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

// Password complexity: 8+ chars, upper, lower, digit, special
if (
    strlen($newPassword) < 8 ||
    !preg_match('/[A-Z]/', $newPassword) ||
    !preg_match('/[a-z]/', $newPassword) ||
    !preg_match('/\d/', $newPassword) ||
    !preg_match('/[^A-Za-z0-9]/', $newPassword)
) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Password must be at least 8 characters and include upper, lower, number, and special character'
    ]);
    exit;
}

try {
    // Load current user
    $stmt = $pdo->prepare('SELECT User_ID, Password_Hash FROM tbl_user WHERE User_ID = ?');
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

    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $update = $pdo->prepare('UPDATE tbl_user SET Password_Hash = ?, Must_Change_Password = 0 WHERE User_ID = ?');
    $update->execute([$newHash, $user['User_ID']]);

    $_SESSION['must_change_password'] = 0;

    auditFromSession($pdo, 'AUTH', 'PASSWORD_CHANGE', 'User changed password', 'SUCCESS', 'user', (int)$user['User_ID']);

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>

