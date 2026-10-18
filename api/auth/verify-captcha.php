<?php

// Authentication - Verify CAPTCHA API

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config/connection-pdo.php';
require_once __DIR__ . '/../includes/audit-log-utils.php';
require_once __DIR__ . '/../includes/login-attempt-utils.php';

$request_method = $_SERVER['REQUEST_METHOD'];

if ($request_method !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

try {
    if (!isset($_SESSION['captcha_user_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No pending verification']);
        exit;
    }

    $expectedAnswer = isset($_SESSION['captcha_answer'])
        ? (int)$_SESSION['captcha_answer']
        : null;

    if ($expectedAnswer !== null) {
        $userAnswer = filter_var($input['answer'] ?? null, FILTER_VALIDATE_INT);
        if ($userAnswer === false || $userAnswer !== $expectedAnswer) {
            clearPendingCaptchaSession();
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Incorrect security verification. Please log in again.',
            ]);
            exit;
        }
    }

    $_SESSION['user_id'] = $_SESSION['captcha_user_id'];
    $_SESSION['user_role'] = $_SESSION['captcha_role'];
    $_SESSION['user_name'] = $_SESSION['captcha_name'];
    $_SESSION['user_email'] = $_SESSION['captcha_email'];
    $_SESSION['user_badge_number'] = $_SESSION['captcha_badge_number'] ?? null;
    $_SESSION['user_rank'] = $_SESSION['captcha_rank'] ?? null;
    $_SESSION['must_change_password'] = $_SESSION['captcha_must_change_password'] ?? 0;

    writeAuditLog($pdo, [
        'user_id' => (int)$_SESSION['user_id'],
        'user_role' => $_SESSION['user_role'] ?? null,
        'module' => 'AUTH',
        'action' => 'LOGIN',
        'outcome' => 'Logged in successfully',
        'status' => 'SUCCESS',
        'entity_type' => 'user',
        'entity_id' => (int)$_SESSION['user_id'],
    ]);

    $identifier = $_SESSION['captcha_identifier'] ?? $_SESSION['captcha_email'] ?? $_SESSION['captcha_badge_number'] ?? '';
    if ($identifier !== '') {
        clearLoginAttempts($pdo, (string)$identifier);
    }

    unset($_SESSION['captcha_user_id']);
    unset($_SESSION['captcha_role']);
    unset($_SESSION['captcha_name']);
    unset($_SESSION['captcha_email']);
    unset($_SESSION['captcha_badge_number']);
    unset($_SESSION['captcha_rank']);
    unset($_SESSION['captcha_must_change_password']);
    unset($_SESSION['captcha_answer']);
    unset($_SESSION['captcha_identifier']);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Verification successful'
    ]);

} catch (Exception $e) {
    error_log('verify-captcha.php error: ' . $e->getMessage());
    http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'An unexpected error occurred. Please try again later.']);
}
?>
