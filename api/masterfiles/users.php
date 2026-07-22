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
require_once __DIR__ . '/../includes/password-utils.php';
require_once __DIR__ . '/../includes/archive-utils.php';
require_once __DIR__ . '/../includes/user-identity-utils.php';
require_once __DIR__ . '/../includes/user-import-utils.php';
require_once __DIR__ . '/../includes/batch-enrollment-utils.php';
require_once __DIR__ . '/../includes/validation-utils.php';

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
        $usersQuery = "SELECT u.User_ID, u.Fullname, u.Email, u.Academic_Number, {$rankCol} r.Role_Name, u.Status, u.Date_Created 
                       FROM tbl_user u 
                       LEFT JOIN tbl_role r ON u.Role_ID = r.Role_ID 
                       WHERE " . activeArchiveCondition($pdo, 'tbl_user', 'u') . "
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
                $batchQuery = 'SELECT b.Batch_ID,
                                      COALESCE(pb.Batch_Name, b.Batch_Name) AS Batch_Name,
                                      s.Section_Name,
                                      c.Course_Name,
                                      ub.Status
                               FROM tbl_user_batch ub
                               INNER JOIN tbl_batch b ON ub.Batch_ID = b.Batch_ID
                               LEFT JOIN tbl_academic_section s ON b.Section_ID = s.Section_ID
                               LEFT JOIN tbl_batch pb ON s.Batch_ID = pb.Batch_ID
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

    if ($request_method === 'GET' && $action === 'next_academic_number') {
        if ($userRole !== 'Admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden: Admin access required']);
            exit;
        }

        try {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => ['academic_number' => getNextAcademicNumber($pdo)],
            ]);
        } catch (RuntimeException $e) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Add new user
    if ($request_method === 'POST' && $action === 'add') {
        $fullname = trim($input['fullname'] ?? $_POST['fullname'] ?? '');
        $email = trim($input['email'] ?? $_POST['email'] ?? '');
        $password = $input['password'] ?? $_POST['password'] ?? '';
        $role_id = $input['role_id'] ?? $_POST['role_id'] ?? '';
        $academic_number = normalizeAcademicNumber($input['academic_number'] ?? $_POST['academic_number'] ?? null);
        $personnel_rank = trim($input['personnel_rank'] ?? $_POST['personnel_rank'] ?? '') ?: null;
        $section_id = (int)($input['section_id'] ?? $_POST['section_id'] ?? 0);

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

        // Validate role
        if (empty($role_id) || !is_numeric($role_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid role is required']);
            exit;
        }

        $roleName = resolveRoleName($pdo, $role_id);
        if (!$roleName) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid role selected']);
            exit;
        }

        if (isExamineeRoleName($roleName)) {
            $resolvedAcademic = resolveExamineeAcademicNumber($pdo, $academic_number);
            if ($resolvedAcademic['error'] !== null) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $resolvedAcademic['error']]);
                exit;
            }

            $academic_number = $resolvedAcademic['number'];

            $uniqueError = ensureAcademicNumberAvailable($pdo, $academic_number);
            if ($uniqueError !== null) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => $uniqueError]);
                exit;
            }

            $email = resolveStoredEmail($roleName, $email, $academic_number);

            if ($section_id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Please select a course, batch, and section for the examinee']);
                exit;
            }

            $enrollmentBatchId = getEnrollmentBatchId($pdo, $section_id);
            if (!$enrollmentBatchId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid section selected']);
                exit;
            }
        } else {
            if (empty($email)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Email is required for staff accounts']);
                exit;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid email format']);
                exit;
            }

            $academic_number = null;
        }

        $rankError = validatePersonnelRank($personnel_rank);
        if ($rankError !== null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $rankError]);
            exit;
        }

        // For Examinee accounts, use default password if none is provided
        if ($roleName === 'Examinee' && empty($password)) {
            $password = DEFAULT_EXAMINEE_PASSWORD;
        }

        // Validate password
        if (empty($password)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Password is required']);
            exit;
        }

        $passwordError = validatePasswordPolicy($password);
        if ($passwordError !== null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $passwordError]);
            exit;
        }

        // Check if email already exists (staff or optional examinee contact email)
        if (!isExamineePlaceholderEmail($email)) {
            $checkStmt = $pdo->prepare('SELECT Email FROM tbl_user WHERE Email = ?');
            $checkStmt->execute([$email]);
            if ($checkStmt->rowCount() > 0) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'message' => 'Email already registered.',
                ]);
                exit;
            }
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $hasRank = personnelRankColumnExists($pdo);

        // For Examinee: mark Must_Change_Password = 1 so they are forced to change on first login
        if ($roleName === 'Examinee') {
            if ($hasRank) {
                $stmt = $pdo->prepare('INSERT INTO tbl_user (Role_ID, Fullname, Email, Password_Hash, Must_Change_Password, Academic_Number, Personnel_Rank, Date_Created, Status) VALUES (?, ?, ?, ?, 1, ?, ?, NOW(), ?)');
                $stmt->execute([$role_id, $fullname, $email, $passwordHash, $academic_number, $personnel_rank, 'Active']);
            } else {
                $stmt = $pdo->prepare('INSERT INTO tbl_user (Role_ID, Fullname, Email, Password_Hash, Must_Change_Password, Academic_Number, Date_Created, Status) VALUES (?, ?, ?, ?, 1, ?, NOW(), ?)');
                $stmt->execute([$role_id, $fullname, $email, $passwordHash, $academic_number, 'Active']);
            }
        } else {
            if ($hasRank) {
                $stmt = $pdo->prepare('INSERT INTO tbl_user (Role_ID, Fullname, Email, Password_Hash, Academic_Number, Personnel_Rank, Date_Created, Status) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)');
                $stmt->execute([$role_id, $fullname, $email, $passwordHash, $academic_number, $personnel_rank, 'Active']);
            } else {
                $stmt = $pdo->prepare('INSERT INTO tbl_user (Role_ID, Fullname, Email, Password_Hash, Academic_Number, Date_Created, Status) VALUES (?, ?, ?, ?, ?, NOW(), ?)');
                $stmt->execute([$role_id, $fullname, $email, $passwordHash, $academic_number, 'Active']);
            }
        }

        $newUserId = (int)$pdo->lastInsertId();

        if (isExamineeRoleName($roleName) && $section_id > 0) {
            $capacityCheck = validateSectionCapacityForEnrollmentBatch($pdo, $enrollmentBatchId, [$newUserId]);
            if (!$capacityCheck['valid']) {
                $pdo->prepare('DELETE FROM tbl_user WHERE User_ID = ?')->execute([$newUserId]);
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'message' => $capacityCheck['message'],
                ]);
                exit;
            }

            $assignResult = assignExamineeToEnrollmentBatch($pdo, $newUserId, $enrollmentBatchId, 'Active');
            if (empty($assignResult['success'])) {
                $pdo->prepare('DELETE FROM tbl_user WHERE User_ID = ?')->execute([$newUserId]);
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'message' => $assignResult['message'] ?? 'Unable to assign examinee to section',
                ]);
                exit;
            }
        }

        auditFromSession($pdo, 'USER', 'CREATE_USER', "Created user {$fullname}", 'SUCCESS', 'user', $newUserId);

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
        $academic_number = normalizeAcademicNumber($input['academic_number'] ?? $_POST['academic_number'] ?? null);
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

        // Validate email format when provided
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
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

        $roleName = resolveRoleName($pdo, $role_id);
        if (!$roleName) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid role selected']);
            exit;
        }

        if (isExamineeRoleName($roleName)) {
            $academicError = validateAcademicNumberFormat($academic_number);
            if ($academicError !== null) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $academicError]);
                exit;
            }

            $uniqueError = ensureAcademicNumberAvailable($pdo, $academic_number, (int)$user_id);
            if ($uniqueError !== null) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => $uniqueError]);
                exit;
            }

            $email = resolveStoredEmail($roleName, $email, $academic_number);
        } else {
            if (empty($email)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Email is required for staff accounts']);
                exit;
            }

            $academic_number = null;
        }

        $rankError = validatePersonnelRank($personnel_rank);
        if ($rankError !== null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $rankError]);
            exit;
        }

        // Validate status
        if (!in_array($status, ['Active', 'Inactive', 'Suspended'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Status must be Active, Inactive, or Suspended']);
            exit;
        }

        // Check if email already exists (excluding current user)
        if (!isExamineePlaceholderEmail($email)) {
            $checkStmt = $pdo->prepare('SELECT Email FROM tbl_user WHERE Email = ? AND User_ID != ?');
            $checkStmt->execute([$email, $user_id]);
            if ($checkStmt->rowCount() > 0) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'message' => 'Email already registered.',
                ]);
                exit;
            }
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
            $passwordError = validatePasswordPolicy($new_password);
            if ($passwordError !== null) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $passwordError]);
                exit;
            }

            $currentHashStmt = $pdo->prepare('SELECT Password_Hash FROM tbl_user WHERE User_ID = ?');
            $currentHashStmt->execute([$user_id]);
            $currentHash = $currentHashStmt->fetchColumn();
            if ($currentHash && password_verify($new_password, $currentHash)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'New password must be different from the current password']);
                exit;
            }

            $passwordHash = password_hash($new_password, PASSWORD_BCRYPT);
            $pwdStmt = $pdo->prepare('UPDATE tbl_user SET Password_Hash = ? WHERE User_ID = ?');
            $pwdStmt->execute([$passwordHash, $user_id]);
        }

        auditFromSession($pdo, 'USER', 'UPDATE_USER', 'Updated user ' . auditUserLabel($pdo, (int)$user_id), 'SUCCESS', 'user', (int)$user_id);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'User updated successfully'
        ]);
        exit;
    }

    // Archive user
    if ($request_method === 'POST' && $action === 'archive') {
        $user_id = (int)($input['user_id'] ?? $_POST['user_id'] ?? 0);

        if ($user_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'User ID is required']);
            exit;
        }

        if ($user_id === (int)$_SESSION['user_id']) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'You cannot archive your own account']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            if (!archiveRecord($pdo, 'tbl_user', 'User_ID', $user_id)) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Unable to archive user. Run the archive migration first.']);
                exit;
            }

            $pdo->prepare('UPDATE tbl_user SET Status = ? WHERE User_ID = ?')->execute(['Inactive', $user_id]);

            $cleared = clearUserCurriculumAssignments($pdo, $user_id);

            $pdo->commit();

            $userLabel = auditUserLabel($pdo, $user_id);
            $detail = "Archived user {$userLabel}";
            if (($cleared['batch_enrollments_removed'] ?? 0) > 0) {
                $detail .= "; removed {$cleared['batch_enrollments_removed']} batch/section enrollment(s)";
            }
            if (($cleared['exam_participants_removed'] ?? 0) > 0) {
                $detail .= "; removed from {$cleared['exam_participants_removed']} exam participant list(s)";
            }
            auditFromSession($pdo, 'USER', 'ARCHIVE_USER', $detail, 'SUCCESS', 'user', $user_id);

            echo json_encode(['success' => true, 'message' => 'User archived successfully']);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('users.php archive error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to archive user']);
        }
        exit;
    }

    // Restore archived user
    if ($request_method === 'POST' && $action === 'restore') {
        $user_id = (int)($input['user_id'] ?? $_POST['user_id'] ?? 0);

        if ($user_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'User ID is required']);
            exit;
        }

        if (!restoreRecord($pdo, 'tbl_user', 'User_ID', $user_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => restoreFailureMessage($pdo, 'tbl_user', 'User_ID', $user_id, 'user')]);
            exit;
        }

        $pdo->prepare('UPDATE tbl_user SET Status = ? WHERE User_ID = ?')->execute(['Active', $user_id]);

        $userLabel = auditUserLabel($pdo, $user_id);
        auditFromSession($pdo, 'USER', 'RESTORE_USER', "Restored user {$userLabel}", 'SUCCESS', 'user', $user_id);

        echo json_encode(['success' => true, 'message' => 'User restored successfully']);
        exit;
    }

    // Delete user — disabled; use archive instead
    if ($request_method === 'POST' && $action === 'delete') {
        archiveDeletionBlockedResponse();
    }

    // Preview bulk examinee import
    if ($request_method === 'POST' && $action === 'preview_bulk_examinees') {
        $section_id = (int)($input['section_id'] ?? $_POST['section_id'] ?? 0);
        $rows = $input['rows'] ?? $_POST['rows'] ?? [];

        if (!is_array($rows) || count($rows) === 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No import rows provided']);
            exit;
        }

        $result = previewBulkExamineeImport($pdo, $rows, $section_id);
        if (empty($result['success'])) {
            http_response_code(400);
        }

        echo json_encode($result);
        exit;
    }

    // Commit bulk examinee import
    if ($request_method === 'POST' && $action === 'commit_bulk_examinees') {
        $section_id = (int)($input['section_id'] ?? $_POST['section_id'] ?? 0);
        $rows = $input['rows'] ?? $_POST['rows'] ?? [];

        if (!is_array($rows) || count($rows) === 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No import rows provided']);
            exit;
        }

        $result = commitBulkExamineeImport($pdo, $rows, $section_id);
        if (empty($result['success'])) {
            http_response_code(empty($result['capacity']) ? 400 : 409);
        } else {
            http_response_code(200);
        }

        echo json_encode($result);
        exit;
    }

    // Reset user password (Admin only)
    if ($request_method === 'POST' && $action === 'reset_password') {
        $user_id = (int)($input['user_id'] ?? $_POST['user_id'] ?? 0);
        $new_password = $input['new_password'] ?? $_POST['new_password'] ?? '';

        if ($user_id <= 0 || empty($new_password)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'User ID and new password are required'
            ]);
            exit;
        }

        $userCheck = $pdo->prepare('SELECT User_ID, Fullname FROM tbl_user WHERE User_ID = ?');
        $userCheck->execute([$user_id]);
        $targetUser = $userCheck->fetch(PDO::FETCH_ASSOC);

        if (!$targetUser) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }

        $passwordError = validatePasswordPolicy($new_password);
        if ($passwordError !== null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $passwordError]);
            exit;
        }

        $passwordHash = password_hash($new_password, PASSWORD_BCRYPT);
        $userLabel = auditUserLabel($pdo, $user_id);

        $stmt = $pdo->prepare('UPDATE tbl_user SET Password_Hash = ? WHERE User_ID = ?');
        $stmt->execute([$passwordHash, $user_id]);

        auditFromSession($pdo, 'USER', 'RESET_PASSWORD', "Admin reset password for {$userLabel}", 'SUCCESS', 'user', (int)$user_id);

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
