<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? [];

$identifier = trim($input['identifier'] ?? $input['email'] ?? $input['academic_number'] ?? '');
$password = $input['password'] ?? '';

if ($identifier === '' || $password === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Academic number or email and password are required',
    ]);
    exit;
}

try {
    require_once __DIR__ . '/../config/connection-pdo.php';
    require_once __DIR__ . '/../includes/audit-log-utils.php';
    require_once __DIR__ . '/../includes/user-identity-utils.php';

    if (!($pdo instanceof PDO)) {
        throw new Exception('Database connection not established');
    }

    $user = findUserByLoginIdentifier($pdo, $identifier);

    if (!$user) {
        writeAuditLog($pdo, [
            'user_id' => null,
            'module' => 'AUTH',
            'action' => 'LOGIN_FAILED',
            'outcome' => 'Failed login attempt for identifier: ' . $identifier,
            'status' => 'FAILED',
        ]);
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid academic number, email, or password',
        ]);
        exit;
    }

    $passwordHash = $user['Password_Hash'] ?? $user['password_hash'] ?? '';
    $loginLabel = $user['Academic_Number'] ?: $user['Email'];

    if (empty($passwordHash) || !password_verify($password, $passwordHash)) {
        writeAuditLog($pdo, [
            'user_id' => (int)$user['User_ID'],
            'user_role' => $user['Role_Name'] ?? null,
            'module' => 'AUTH',
            'action' => 'LOGIN_FAILED',
            'outcome' => "Invalid password for {$loginLabel}",
            'status' => 'FAILED',
            'entity_type' => 'user',
            'entity_id' => (int)$user['User_ID'],
        ]);
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid academic number, email, or password',
        ]);
        exit;
    }

    if (isset($user['Is_Archived']) && (int)$user['Is_Archived'] === 1) {
        writeAuditLog($pdo, [
            'user_id' => (int)$user['User_ID'],
            'user_role' => $user['Role_Name'] ?? null,
            'module' => 'AUTH',
            'action' => 'LOGIN_FAILED',
            'outcome' => "Archived account login attempt: {$loginLabel}",
            'status' => 'FAILED',
            'entity_type' => 'user',
            'entity_id' => (int)$user['User_ID'],
        ]);
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'This account has been archived. Contact an administrator to restore access.',
        ]);
        exit;
    }

    if ($user['Status'] !== 'Active') {
        writeAuditLog($pdo, [
            'user_id' => (int)$user['User_ID'],
            'user_role' => $user['Role_Name'] ?? null,
            'module' => 'AUTH',
            'action' => 'LOGIN_FAILED',
            'outcome' => "Inactive account login attempt: {$loginLabel}",
            'status' => 'FAILED',
            'entity_type' => 'user',
            'entity_id' => (int)$user['User_ID'],
        ]);
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Account is not active',
        ]);
        exit;
    }

    $requires_captcha = in_array($user['Role_Name'], ['Admin', 'CCMD'], true);

    if ($requires_captcha) {
        $captchaNum1 = random_int(1, 10);
        $captchaNum2 = random_int(1, 10);

        $_SESSION['captcha_user_id'] = $user['User_ID'];
        $_SESSION['captcha_role'] = $user['Role_Name'];
        $_SESSION['captcha_name'] = $user['Fullname'];
        $_SESSION['captcha_email'] = $user['Email'];
        $_SESSION['captcha_academic_number'] = $user['Academic_Number'] ?? null;
        $_SESSION['captcha_rank'] = trim((string)($user['Personnel_Rank'] ?? '')) ?: null;
        $_SESSION['captcha_must_change_password'] = isset($user['Must_Change_Password']) ? (int)$user['Must_Change_Password'] : 0;
        $_SESSION['captcha_answer'] = $captchaNum1 + $captchaNum2;
        session_write_close();

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'requires_captcha' => true,
            'message' => 'Please complete security verification',
            'captcha_num1' => $captchaNum1,
            'captcha_num2' => $captchaNum2,
            'user' => [
                'id' => $user['User_ID'],
                'role' => $user['Role_Name'],
                'name' => $user['Fullname'],
            ],
        ]);
        exit;
    }

    $_SESSION['user_id'] = $user['User_ID'];
    $_SESSION['user_email'] = $user['Email'];
    $_SESSION['user_academic_number'] = $user['Academic_Number'] ?? null;
    $_SESSION['user_rank'] = trim((string)($user['Personnel_Rank'] ?? '')) ?: null;
    $_SESSION['user_role'] = $user['Role_Name'];
    $_SESSION['user_name'] = $user['Fullname'];
    $_SESSION['must_change_password'] = isset($user['Must_Change_Password']) ? (int)$user['Must_Change_Password'] : 0;

    writeAuditLog($pdo, [
        'user_id' => (int)$user['User_ID'],
        'user_role' => $user['Role_Name'] ?? null,
        'module' => 'AUTH',
        'action' => 'LOGIN',
        'outcome' => ($user['Academic_Number'] ?: $user['Email']) . ' logged in',
        'status' => 'SUCCESS',
        'entity_type' => 'user',
        'entity_id' => (int)$user['User_ID'],
    ]);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'id' => $user['User_ID'],
            'academic_number' => $user['Academic_Number'] ?? null,
            'name' => $user['Fullname'],
            'role' => $user['Role_Name'],
            'must_change_password' => isset($user['Must_Change_Password']) ? (bool)$user['Must_Change_Password'] : false,
        ],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
    ]);
}
