<?php
header('Content-Type: application/json');
session_start();

// Get raw input
$rawInput = file_get_contents('php://input');
error_log("Raw input: " . $rawInput);

// Get request data
$input = json_decode($rawInput, true);
error_log("Decoded input: " . print_r($input, true));

$email = $input['email'] ?? '';
$password = $input['password'] ?? '';

error_log("Email: $email, Password length: " . strlen($password));

// Validate input
if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Email and password are required',
        'debug' => [
            'raw_input_length' => strlen($rawInput),
            'decoded_input' => $input,
            'email_empty' => empty($email),
            'password_empty' => empty($password)
        ]
    ]);
    exit;
}

try {
    // Include database connection
    $pdo = null;
    require_once __DIR__ . '/../config/connection-pdo.php';
    require_once __DIR__ . '/../includes/audit-log-utils.php';
    if (!($pdo instanceof PDO)) {
        throw new Exception('Database connection not established');
    }
    
    // Query the tbl_user table for the user
    $query = "SELECT u.*, r.Role_Name FROM tbl_user u 
              LEFT JOIN tbl_role r ON u.Role_ID = r.Role_ID 
              WHERE u.Email = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if user exists
    if (!$user) {
        writeAuditLog($pdo, [
            'user_id' => null,
            'module' => 'AUTH',
            'action' => 'LOGIN_FAILED',
            'outcome' => "Failed login attempt for email: {$email}",
            'status' => 'FAILED',
        ]);
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email or password'
        ]);
        exit;
    }

    // Verify password
    $passwordHash = $user['Password_Hash'] ?? $user['password_hash'] ?? '';

    // Debug logging
    error_log("Login attempt - Email: $email");
    error_log("Password hash exists: " . (!empty($passwordHash) ? 'YES' : 'NO'));
    error_log("Password value before verify: '" . $password . "'");
    error_log("Password length before verify: " . strlen($password));
    $verifyResult = password_verify($password, $passwordHash);
    error_log("Password verify result: " . ($verifyResult ? 'PASS' : 'FAIL'));
    error_log("Password length after verify: " . strlen($password));

    if (empty($passwordHash) || !$verifyResult) {
        writeAuditLog($pdo, [
            'user_id' => (int)$user['User_ID'],
            'user_role' => $user['Role_Name'] ?? null,
            'module' => 'AUTH',
            'action' => 'LOGIN_FAILED',
            'outcome' => "Invalid password for {$email}",
            'status' => 'FAILED',
            'entity_type' => 'user',
            'entity_id' => (int)$user['User_ID'],
        ]);
        error_log("FAILED - Password at error: '" . $password . "' (length: " . strlen($password) . ")");
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email or password',
            'debug' => [
                'email_found' => true,
                'hash_exists' => !empty($passwordHash),
                'password_length' => strlen($password),
                'password_empty' => empty($password),
                'verify_result' => $verifyResult
            ]
        ]);
        exit;
    }

    // Check if user's account is active
    if ($user['Status'] !== 'Active') {
        writeAuditLog($pdo, [
            'user_id' => (int)$user['User_ID'],
            'user_role' => $user['Role_Name'] ?? null,
            'module' => 'AUTH',
            'action' => 'LOGIN_FAILED',
            'outcome' => "Inactive account login attempt: {$email}",
            'status' => 'FAILED',
            'entity_type' => 'user',
            'entity_id' => (int)$user['User_ID'],
        ]);
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Account is not active'
        ]);
        exit;
    }

    // Check if CAPTCHA is required for Admin and CCMD roles
    $requires_captcha = in_array($user['Role_Name'], ['Admin', 'CCMD']);
    
    if ($requires_captcha) {
        // Store user info in session for CAPTCHA verification
        $_SESSION['captcha_user_id'] = $user['User_ID'];
        $_SESSION['captcha_role'] = $user['Role_Name'];
        $_SESSION['captcha_name'] = $user['Fullname'];
        $_SESSION['captcha_email'] = $user['Email'];
        $_SESSION['captcha_must_change_password'] = isset($user['Must_Change_Password']) ? (int)$user['Must_Change_Password'] : 0;
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'requires_captcha' => true,
            'message' => 'Please complete security verification',
            'user' => [
                'id' => $user['User_ID'],
                'role' => $user['Role_Name'],
                'name' => $user['Fullname']
            ]
        ]);
        exit;
    }

    // Set session variables (for Examinee - no CAPTCHA)
    $_SESSION['user_id'] = $user['User_ID'];
    $_SESSION['user_email'] = $user['Email'];
    $_SESSION['user_role'] = $user['Role_Name'];
    $_SESSION['user_name'] = $user['Fullname'];
    $_SESSION['must_change_password'] = isset($user['Must_Change_Password']) ? (int)$user['Must_Change_Password'] : 0;

    writeAuditLog($pdo, [
        'user_id' => (int)$user['User_ID'],
        'user_role' => $user['Role_Name'] ?? null,
        'module' => 'AUTH',
        'action' => 'LOGIN',
        'outcome' => "User {$user['Fullname']} logged in",
        'status' => 'SUCCESS',
        'entity_type' => 'user',
        'entity_id' => (int)$user['User_ID'],
    ]);

    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'id' => $user['User_ID'],
            'email' => $user['Email'],
            'name' => $user['Fullname'],
            'role' => $user['Role_Name'],
            'must_change_password' => isset($user['Must_Change_Password']) ? (bool)$user['Must_Change_Password'] : false
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
