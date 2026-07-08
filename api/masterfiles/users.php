<?php
header('Content-Type: application/json');
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

$userRole = $_SESSION['user_role'] ?? '';
if (!in_array($userRole, ['Admin', 'CCMD'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Forbidden: Admin or CCMD access required'
    ]);
    exit;
}

// Use correct path to shared PDO config from /api/masterfiles
require_once __DIR__ . '/../config/connection-pdo.php';
require_once __DIR__ . '/../includes/exam-code-utils.php';
require_once __DIR__ . '/../includes/audit-log-utils.php';

$request_method = $_SERVER['REQUEST_METHOD'];
// Read JSON/body once so we can also accept action in JSON payload
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? [];
$action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? '');

// CCMD may only read user lists; mutations require Admin
if ($request_method === 'POST' && $userRole !== 'Admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Forbidden: Admin access required'
    ]);
    exit;
}

try {
    // Get all users
    if ($request_method === 'GET' && $action === 'list') {
        $rankCol = personnelRankColumnExists($pdo) ? 'u.Personnel_Rank,' : '';
        $usersQuery = "SELECT u.User_ID, u.Fullname, u.Email, u.Username, u.Academic_Number, {$rankCol} r.Role_Name, u.Status, u.Date_Created 
                       FROM tbl_user u 
                       LEFT JOIN tbl_role r ON u.Role_ID = r.Role_ID 
                       ORDER BY u.Date_Created DESC";
        $usersStmt = $pdo->prepare($usersQuery);
        $usersStmt->execute();
        $users = $usersStmt->fetchAll();

        // Get batch assignments for each examinee
        $hasUserBatch = false;
        try {
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'tbl_user_batch'");
            $hasUserBatch = $tableCheck && $tableCheck->rowCount() > 0;
        } catch (Exception $e) {
            $hasUserBatch = false;
        }

        foreach ($users as &$user) {
            if ($user['Role_Name'] === 'Examinee' && $hasUserBatch) {
                $batchQuery = 'SELECT b.Batch_ID, b.Batch_Name, s.Section_Name, c.Course_Name, ub.Status
                               FROM tbl_user_batch ub
                               INNER JOIN tbl_batch b ON ub.Batch_ID = b.Batch_ID
                               INNER JOIN tbl_academic_section s ON b.Section_ID = s.Section_ID
                               INNER JOIN tbl_course c ON b.Course_ID = c.Course_ID
                               WHERE ub.User_ID = ? AND ub.Status = "Active"
                               ORDER BY ub.Date_Enrolled DESC';
                $batchStmt = $pdo->prepare($batchQuery);
                $batchStmt->execute([$user['User_ID']]);
                $user['batches'] = $batchStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $user['batches'] = [];
            }
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $users
        ]);
        exit;
    }

    // Get all roles
    if ($request_method === 'GET' && $action === 'roles') {
        $rolesQuery = 'SELECT Role_ID, Role_Name FROM tbl_role ORDER BY Role_Name';
        $rolesStmt = $pdo->prepare($rolesQuery);
        $rolesStmt->execute();
        $roles = $rolesStmt->fetchAll();

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $roles
        ]);
        exit;
    }

    if ($request_method === 'GET' && $action === 'personnel_ranks') {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => getPersonnelRankOptions()
        ]);
        exit;
    }

    // Add new user
    if ($request_method === 'POST' && $action === 'add') {
        $fullname = trim($input['fullname'] ?? $_POST['fullname'] ?? '');
        $email = trim($input['email'] ?? $_POST['email'] ?? '');
        $username = trim($input['username'] ?? $_POST['username'] ?? '');
        $password = $input['password'] ?? $_POST['password'] ?? '';
        $role_id = $input['role_id'] ?? $_POST['role_id'] ?? '';
        $academic_number = $input['academic_number'] ?? $_POST['academic_number'] ?? null;
        $personnel_rank = trim($input['personnel_rank'] ?? $_POST['personnel_rank'] ?? '') ?: null;

        // Validate fullname - must contain only letters, spaces, dots, and hyphens
        if (empty($fullname)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Full name is required']);
            exit;
        }
        
        if (!preg_match("/^[a-zA-Z\s.\-']+$/", $fullname)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Full name must contain only letters, spaces, dots, hyphens, and apostrophes']);
            exit;
        }
        
        if (strlen($fullname) < 2 || strlen($fullname) > 100) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Full name must be between 2 and 100 characters']);
            exit;
        }

        // Validate email
        if (empty($email)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email is required']);
            exit;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid email format']);
            exit;
        }

        // Validate username - alphanumeric, underscore, hyphen only
        if (empty($username)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Username is required']);
            exit;
        }
        
        if (!preg_match("/^[a-zA-Z0-9_\-]+$/", $username)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Username must contain only letters, numbers, underscores, and hyphens']);
            exit;
        }
        
        if (!preg_match("/[a-zA-Z]/", $username)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Username must contain at least one letter']);
            exit;
        }
        
        if (strlen($username) < 3 || strlen($username) > 50) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Username must be between 3 and 50 characters']);
            exit;
        }

        // Validate role
        if (empty($role_id) || !is_numeric($role_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid role is required']);
            exit;
        }

        // Resolve role name to know if this is an Examinee
        $roleName = null;
        if (!empty($role_id)) {
            $roleStmt = $pdo->prepare('SELECT Role_Name FROM tbl_role WHERE Role_ID = ?');
            $roleStmt->execute([$role_id]);
            $roleName = $roleStmt->fetchColumn() ?: null;
            
            if (!$roleName) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid role selected']);
                exit;
            }
        }

        // For Examinee accounts, use default password if none is provided
        if ($roleName === 'Examinee' && empty($password)) {
            $password = 'PNPRTC10';
        }

        // Validate password
        if (empty($password)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Password is required']);
            exit;
        }
        
        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
            exit;
        }

        // Check if email already exists
        $checkStmt = $pdo->prepare('SELECT Email FROM tbl_user WHERE Email = ?');
        $checkStmt->execute([$email]);
        if ($checkStmt->rowCount() > 0) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'Email already exists'
            ]);
            exit;
        }

        // Check if username already exists
        $checkUsernameStmt = $pdo->prepare('SELECT Username FROM tbl_user WHERE Username = ?');
        $checkUsernameStmt->execute([$username]);
        if ($checkUsernameStmt->rowCount() > 0) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'Username already exists'
            ]);
            exit;
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $hasRank = personnelRankColumnExists($pdo);

        // For Examinee: mark Must_Change_Password = 1 so they are forced to change on first login
        if ($roleName === 'Examinee') {
            if ($hasRank) {
                $stmt = $pdo->prepare('INSERT INTO tbl_user (Role_ID, Fullname, Email, Username, Password_Hash, Must_Change_Password, Academic_Number, Personnel_Rank, Date_Created, Status) VALUES (?, ?, ?, ?, ?, 1, ?, ?, NOW(), ?)');
                $stmt->execute([$role_id, $fullname, $email, $username, $passwordHash, $academic_number, $personnel_rank, 'Active']);
            } else {
                $stmt = $pdo->prepare('INSERT INTO tbl_user (Role_ID, Fullname, Email, Username, Password_Hash, Must_Change_Password, Academic_Number, Date_Created, Status) VALUES (?, ?, ?, ?, ?, 1, ?, NOW(), ?)');
                $stmt->execute([$role_id, $fullname, $email, $username, $passwordHash, $academic_number, 'Active']);
            }
        } else {
            if ($hasRank) {
                $stmt = $pdo->prepare('INSERT INTO tbl_user (Role_ID, Fullname, Email, Username, Password_Hash, Academic_Number, Personnel_Rank, Date_Created, Status) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)');
                $stmt->execute([$role_id, $fullname, $email, $username, $passwordHash, $academic_number, $personnel_rank, 'Active']);
            } else {
                $stmt = $pdo->prepare('INSERT INTO tbl_user (Role_ID, Fullname, Email, Username, Password_Hash, Academic_Number, Date_Created, Status) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)');
                $stmt->execute([$role_id, $fullname, $email, $username, $passwordHash, $academic_number, 'Active']);
            }
        }

        $newUserId = (int)$pdo->lastInsertId();
        auditFromSession($pdo, 'USER', 'CREATE_USER', "Created user {$fullname} (ID {$newUserId})", 'SUCCESS', 'user', $newUserId);

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'User added successfully',
            'user_id' => $newUserId
        ]);
        exit;
    }

    // Update user
    if ($request_method === 'POST' && $action === 'update') {
        $user_id = $input['user_id'] ?? $_POST['user_id'] ?? '';
        $fullname = trim($input['fullname'] ?? $_POST['fullname'] ?? '');
        $email = trim($input['email'] ?? $_POST['email'] ?? '');
        $role_id = $input['role_id'] ?? $_POST['role_id'] ?? '';
        $status = $input['status'] ?? $_POST['status'] ?? 'Active';
        $academic_number = $input['academic_number'] ?? $_POST['academic_number'] ?? null;
        $personnel_rank = trim($input['personnel_rank'] ?? $_POST['personnel_rank'] ?? '') ?: null;
        $new_password = $input['new_password'] ?? $_POST['new_password'] ?? null;

        // Validate user ID
        if (empty($user_id) || !is_numeric($user_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid user ID is required']);
            exit;
        }

        // Validate fullname
        if (empty($fullname)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Full name is required']);
            exit;
        }
        
        if (!preg_match("/^[a-zA-Z\s.\-']+$/", $fullname)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Full name must contain only letters, spaces, dots, hyphens, and apostrophes']);
            exit;
        }
        
        if (strlen($fullname) < 2 || strlen($fullname) > 100) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Full name must be between 2 and 100 characters']);
            exit;
        }

        // Validate email
        if (empty($email)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email is required']);
            exit;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid email format']);
            exit;
        }

        // Validate role
        if (empty($role_id) || !is_numeric($role_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid role is required']);
            exit;
        }

        // Validate status
        if (!in_array($status, ['Active', 'Inactive'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Status must be Active or Inactive']);
            exit;
        }

        // Check if email already exists (excluding current user)
        $checkStmt = $pdo->prepare('SELECT Email FROM tbl_user WHERE Email = ? AND User_ID != ?');
        $checkStmt->execute([$email, $user_id]);
        if ($checkStmt->rowCount() > 0) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'Email already exists'
            ]);
            exit;
        }

        // Update user info
        if (personnelRankColumnExists($pdo)) {
            $stmt = $pdo->prepare('UPDATE tbl_user SET Fullname = ?, Email = ?, Role_ID = ?, Academic_Number = ?, Personnel_Rank = ?, Status = ? WHERE User_ID = ?');
            $stmt->execute([$fullname, $email, $role_id, $academic_number, $personnel_rank, $status, $user_id]);
        } else {
            $stmt = $pdo->prepare('UPDATE tbl_user SET Fullname = ?, Email = ?, Role_ID = ?, Academic_Number = ?, Status = ? WHERE User_ID = ?');
            $stmt->execute([$fullname, $email, $role_id, $academic_number, $status, $user_id]);
        }

        // If new password is provided, update it
        if (!empty($new_password)) {
            if (strlen($new_password) < 6) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Password must be at least 6 characters'
                ]);
                exit;
            }
            
            $passwordHash = password_hash($new_password, PASSWORD_BCRYPT);
            $pwdStmt = $pdo->prepare('UPDATE tbl_user SET Password_Hash = ? WHERE User_ID = ?');
            $pwdStmt->execute([$passwordHash, $user_id]);
        }

        auditFromSession($pdo, 'USER', 'UPDATE_USER', "Updated user {$fullname} (ID {$user_id})", 'SUCCESS', 'user', (int)$user_id);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'User updated successfully'
        ]);
        exit;
    }

    // Delete user
    if ($request_method === 'POST' && $action === 'delete') {
        $user_id = $input['user_id'] ?? $_POST['user_id'] ?? '';

        if (empty($user_id)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'User ID is required'
            ]);
            exit;
        }

        $stmt = $pdo->prepare('DELETE FROM tbl_user WHERE User_ID = ?');
        $stmt->execute([$user_id]);

        auditFromSession($pdo, 'USER', 'DELETE_USER', "Deleted user ID {$user_id}", 'SUCCESS', 'user', (int)$user_id);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
        exit;
    }

    // Reset user password (Admin only)
    if ($request_method === 'POST' && $action === 'reset_password') {
        $user_id = $input['user_id'] ?? $_POST['user_id'] ?? '';
        $new_password = $input['new_password'] ?? $_POST['new_password'] ?? '';

        if (empty($user_id) || empty($new_password)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'User ID and new password are required'
            ]);
            exit;
        }

        if (strlen($new_password) < 6) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Password must be at least 6 characters'
            ]);
            exit;
        }

        // Hash the new password
        $passwordHash = password_hash($new_password, PASSWORD_BCRYPT);

        // Update the password
        $stmt = $pdo->prepare('UPDATE tbl_user SET Password_Hash = ? WHERE User_ID = ?');
        $stmt->execute([$passwordHash, $user_id]);

        auditFromSession($pdo, 'USER', 'RESET_PASSWORD', "Admin reset password for user ID {$user_id}", 'SUCCESS', 'user', (int)$user_id);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Password reset successfully'
        ]);
        exit;
    }

    // Invalid action
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
