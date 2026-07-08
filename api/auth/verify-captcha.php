<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/connection-pdo.php';
require_once __DIR__ . '/../includes/audit-log-utils.php';

$request_method = $_SERVER['REQUEST_METHOD'];

if ($request_method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Check if CAPTCHA session exists
    if (!isset($_SESSION['captcha_user_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No pending verification']);
        exit;
    }
    
    // Complete login - set session variables
    $_SESSION['user_id'] = $_SESSION['captcha_user_id'];
    $_SESSION['user_role'] = $_SESSION['captcha_role'];
    $_SESSION['user_name'] = $_SESSION['captcha_name'];
    $_SESSION['user_email'] = $_SESSION['captcha_email'];
    $_SESSION['must_change_password'] = $_SESSION['captcha_must_change_password'] ?? 0;

    writeAuditLog($pdo, [
        'user_id' => (int)$_SESSION['user_id'],
        'user_role' => $_SESSION['user_role'] ?? null,
        'module' => 'AUTH',
        'action' => 'LOGIN',
        'outcome' => 'Admin/CCMD login completed after CAPTCHA verification',
        'status' => 'SUCCESS',
        'entity_type' => 'user',
        'entity_id' => (int)$_SESSION['user_id'],
    ]);
    
    // Clear CAPTCHA session
    unset($_SESSION['captcha_user_id']);
    unset($_SESSION['captcha_role']);
    unset($_SESSION['captcha_name']);
    unset($_SESSION['captcha_email']);
    unset($_SESSION['captcha_must_change_password']);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Verification successful'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
