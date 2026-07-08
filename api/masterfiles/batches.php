<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'CCMD'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/connection-pdo.php';
require_once __DIR__ . '/../includes/audit-log-utils.php';

$request_method = $_SERVER['REQUEST_METHOD'];
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? [];
$action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? '');

/**
 * Check whether tbl_user_batch exists.
 */
function hasUserBatchTable(PDO $pdo) {
    try {
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'tbl_user_batch'");
        return $tableCheck && $tableCheck->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Resolve enrollment batch for a section (creates one if missing).
 */
function getEnrollmentBatchId(PDO $pdo, $section_id) {
    $stmt = $pdo->prepare('SELECT Batch_ID FROM tbl_batch WHERE Section_ID = ? LIMIT 1');
    $stmt->execute([$section_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return (int)$row['Batch_ID'];
    }

    $sectionStmt = $pdo->prepare('
        SELECT s.Section_ID, s.Section_Name, s.Batch_ID as Parent_Batch_ID, b.Course_ID
        FROM tbl_academic_section s
        INNER JOIN tbl_batch b ON s.Batch_ID = b.Batch_ID
        WHERE s.Section_ID = ?
    ');
    $sectionStmt->execute([$section_id]);
    $section = $sectionStmt->fetch(PDO::FETCH_ASSOC);
    if (!$section) {
        return null;
    }

    $createStmt = $pdo->prepare('INSERT INTO tbl_batch (Course_ID, Section_ID, Batch_Name, Status) VALUES (?, ?, ?, ?)');
    $createStmt->execute([
        $section['Course_ID'],
        $section_id,
        $section['Section_Name'] . ' Enrollment',
        'Active'
    ]);

    return (int)$pdo->lastInsertId();
}

/**
 * Get the section ID for a batch.
 */
function getSectionIdForBatch(PDO $pdo, $batch_id) {
    $stmt = $pdo->prepare('SELECT Section_ID FROM tbl_batch WHERE Batch_ID = ?');
    $stmt->execute([$batch_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['Section_ID'] : null;
}

/**
 * Get section capacity details and current trainee count across all batches.
 */
function getSectionCapacityInfo(PDO $pdo, $section_id) {
    $stmt = $pdo->prepare('SELECT Section_ID, Section_Name, Capacity FROM tbl_academic_section WHERE Section_ID = ?');
    $stmt->execute([$section_id]);
    $section = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$section) {
        return null;
    }

    $capacity = (int)$section['Capacity'];
    $assigned = 0;
    $assignedUserIds = [];

    if (hasUserBatchTable($pdo)) {
        $countStmt = $pdo->prepare('
            SELECT COUNT(DISTINCT ub.User_ID) as assigned_count
            FROM tbl_user_batch ub
            INNER JOIN tbl_batch b ON ub.Batch_ID = b.Batch_ID
            WHERE b.Section_ID = ?
        ');
        $countStmt->execute([$section_id]);
        $assigned = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['assigned_count'] ?? 0);

        $idsStmt = $pdo->prepare('
            SELECT DISTINCT ub.User_ID
            FROM tbl_user_batch ub
            INNER JOIN tbl_batch b ON ub.Batch_ID = b.Batch_ID
            WHERE b.Section_ID = ?
        ');
        $idsStmt->execute([$section_id]);
        $assignedUserIds = array_map('intval', array_column($idsStmt->fetchAll(PDO::FETCH_ASSOC), 'User_ID'));
    }

    return [
        'section_id' => (int)$section['Section_ID'],
        'section_name' => $section['Section_Name'],
        'capacity' => $capacity,
        'assigned_count' => $assigned,
        'available_slots' => max(0, $capacity - $assigned),
        'assigned_user_ids' => $assignedUserIds
    ];
}

/**
 * Count how many selected users would consume new section slots.
 */
function countNewSectionAssignments(PDO $pdo, $section_id, array $user_ids) {
    $info = getSectionCapacityInfo($pdo, $section_id);
    if (!$info) {
        return 0;
    }

    $alreadyInSection = array_flip($info['assigned_user_ids']);
    $seen = [];
    $newCount = 0;

    foreach ($user_ids as $user_id) {
        $user_id = (int)$user_id;
        if ($user_id <= 0 || isset($seen[$user_id])) {
            continue;
        }
        $seen[$user_id] = true;

        if (!isset($alreadyInSection[$user_id])) {
            $newCount++;
        }
    }

    return $newCount;
}

/**
 * Validate that assigning users would not exceed section capacity.
 */
function validateSectionCapacityForAssignment(PDO $pdo, $batch_id, array $user_ids) {
    $section_id = getSectionIdForBatch($pdo, $batch_id);
    if (!$section_id) {
        return ['valid' => false, 'message' => 'Batch is not linked to a section'];
    }

    $info = getSectionCapacityInfo($pdo, $section_id);
    if (!$info) {
        return ['valid' => false, 'message' => 'Section not found'];
    }

    $newAssignments = countNewSectionAssignments($pdo, $section_id, $user_ids);

    if ($info['assigned_count'] + $newAssignments > $info['capacity']) {
        return [
            'valid' => false,
            'message' => "Section capacity exceeded. Maximum: {$info['capacity']}, Currently Assigned: {$info['assigned_count']}.",
            'capacity' => $info['capacity'],
            'assigned_count' => $info['assigned_count'],
            'available_slots' => $info['available_slots']
        ];
    }

    return ['valid' => true, 'capacity_info' => $info, 'new_assignments' => $newAssignments];
}

/**
 * Verify that a user exists and has the Examinee role.
 */
function validateExamineeUser(PDO $pdo, $user_id) {
    $userCheck = $pdo->prepare('SELECT Role_ID FROM tbl_user WHERE User_ID = ?');
    $userCheck->execute([$user_id]);
    $user = $userCheck->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return ['valid' => false, 'message' => 'User not found'];
    }

    $roleCheck = $pdo->prepare('SELECT Role_Name FROM tbl_role WHERE Role_ID = ?');
    $roleCheck->execute([$user['Role_ID']]);
    $role = $roleCheck->fetch(PDO::FETCH_ASSOC);

    if (!$role || $role['Role_Name'] !== 'Examinee') {
        return ['valid' => false, 'message' => 'Only examinees can be assigned to batches'];
    }

    return ['valid' => true];
}

/**
 * Assign a single examinee to a batch via tbl_user_batch.
 */
function assignExamineeToBatch(PDO $pdo, $user_id, $batch_id, $status = 'Active') {
    if (!hasUserBatchTable($pdo)) {
        return ['success' => false, 'message' => 'Enrollment table not available. Please run the database migration.'];
    }

    $validation = validateExamineeUser($pdo, $user_id);
    if (!$validation['valid']) {
        return ['success' => false, 'message' => $validation['message']];
    }

    $existing = $pdo->prepare('SELECT User_Batch_ID FROM tbl_user_batch WHERE User_ID = ? AND Batch_ID = ?');
    $existing->execute([$user_id, $batch_id]);
    if ($existing->fetch()) {
        return ['success' => true, 'assigned' => false, 'message' => 'User already assigned to batch'];
    }

    $stmt = $pdo->prepare('INSERT INTO tbl_user_batch (User_ID, Batch_ID, Status, Date_Enrolled) VALUES (?, ?, ?, NOW())');
    $stmt->execute([$user_id, $batch_id, $status]);

    return ['success' => true, 'assigned' => true, 'message' => 'User assigned to batch successfully'];
}

try {
    // Get batches by section
    if ($request_method === 'GET' && $action === 'list') {
        $section_id = $_GET['section_id'] ?? '';
        
        if (empty($section_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Section ID is required']);
            exit;
        }

        $stmt = $pdo->prepare('SELECT Batch_ID, Batch_Name, Date_Started, Date_Ended FROM tbl_batch WHERE Section_ID = ? ORDER BY Batch_Name');
        $stmt->execute([$section_id]);
        $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $batches]);
        exit;
    }

    // Add batch (course-level or legacy section-level)
    if ($request_method === 'POST' && $action === 'add') {
        $course_id = $input['course_id'] ?? $_POST['course_id'] ?? '';
        $section_id = $input['section_id'] ?? $_POST['section_id'] ?? '';
        $batch_name = $input['batch_name'] ?? $_POST['batch_name'] ?? '';
        $date_started = $input['date_started'] ?? $_POST['date_started'] ?? null;
        $date_ended = $input['date_ended'] ?? $_POST['date_ended'] ?? null;

        if (empty($batch_name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Batch name is required']);
            exit;
        }

        // New hierarchy: batch under course
        if (!empty($course_id)) {
            $courseCheck = $pdo->prepare('SELECT Course_ID FROM tbl_course WHERE Course_ID = ?');
            $courseCheck->execute([(int)$course_id]);
            if (!$courseCheck->fetch()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid course ID']);
                exit;
            }

            $stmt = $pdo->prepare('INSERT INTO tbl_batch (Course_ID, Section_ID, Batch_Name, Date_Started, Date_Ended, Status) VALUES (?, NULL, ?, ?, ?, ?)');
            $stmt->execute([(int)$course_id, $batch_name, $date_started ?: null, $date_ended ?: null, 'Active']);

            http_response_code(201);
            echo json_encode(['success' => true, 'message' => 'Batch added successfully', 'batch_id' => $pdo->lastInsertId()]);
            exit;
        }

        // Legacy: batch under section
        if (empty($section_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Course ID or Section ID is required']);
            exit;
        }

        $courseStmt = $pdo->prepare('
            SELECT b.Course_ID 
            FROM tbl_academic_section s
            INNER JOIN tbl_batch b ON s.Batch_ID = b.Batch_ID
            WHERE s.Section_ID = ?
            LIMIT 1
        ');
        $courseStmt->execute([$section_id]);
        $courseData = $courseStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$courseData) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid section ID or section not linked to a course']);
            exit;
        }
        
        $course_id = $courseData['Course_ID'];

        $stmt = $pdo->prepare('INSERT INTO tbl_batch (Course_ID, Section_ID, Batch_Name, Date_Started, Date_Ended, Status) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$course_id, $section_id, $batch_name, $date_started ?: null, $date_ended ?: null, 'Active']);

        http_response_code(201);
        echo json_encode(['success' => true, 'message' => 'Batch added successfully', 'batch_id' => $pdo->lastInsertId()]);
        exit;
    }

    // Update batch
    if ($request_method === 'POST' && $action === 'update') {
        $batch_id = $input['batch_id'] ?? $_POST['batch_id'] ?? '';
        $batch_name = $input['batch_name'] ?? $_POST['batch_name'] ?? '';
        $date_started = $input['date_started'] ?? $_POST['date_started'] ?? null;
        $date_ended = $input['date_ended'] ?? $_POST['date_ended'] ?? null;

        if (empty($batch_id) || empty($batch_name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Batch ID and name are required']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE tbl_batch SET Batch_Name = ?, Date_Started = ?, Date_Ended = ? WHERE Batch_ID = ?');
        $stmt->execute([$batch_name, $date_started ?: null, $date_ended ?: null, $batch_id]);

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Batch updated successfully']);
        exit;
    }

    // Delete batch
    if ($request_method === 'POST' && $action === 'delete') {
        $batch_id = $input['batch_id'] ?? $_POST['batch_id'] ?? '';

        if (empty($batch_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Batch ID is required']);
            exit;
        }

        $stmt = $pdo->prepare('DELETE FROM tbl_batch WHERE Batch_ID = ?');
        $stmt->execute([$batch_id]);

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Batch deleted successfully']);
        exit;
    }

    // ========== USER-BATCH MANAGEMENT (tbl_user_batch) ==========

    // Get section capacity info by batch or section
    if ($request_method === 'GET' && $action === 'section_capacity') {
        $batch_id = $_GET['batch_id'] ?? '';
        $section_id = $_GET['section_id'] ?? '';

        if (!empty($section_id)) {
            $info = getSectionCapacityInfo($pdo, $section_id);
            if (!$info) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Section not found']);
                exit;
            }
            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $info]);
            exit;
        }

        if (empty($batch_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Batch ID or Section ID is required']);
            exit;
        }

        $section_id = getSectionIdForBatch($pdo, $batch_id);
        if (!$section_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Batch is not linked to a section']);
            exit;
        }

        $info = getSectionCapacityInfo($pdo, $section_id);
        if (!$info) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Section not found']);
            exit;
        }

        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $info]);
        exit;
    }

    // Get users assigned to a section
    if ($request_method === 'GET' && $action === 'users_by_section') {
        $section_id = $_GET['section_id'] ?? '';

        if (empty($section_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Section ID is required']);
            exit;
        }

        if (!hasUserBatchTable($pdo)) {
            http_response_code(200);
            echo json_encode(['success' => true, 'data' => []]);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT DISTINCT u.User_ID, u.Fullname, u.Email, u.Username, u.Academic_Number, ub.Status, ub.Date_Enrolled
            FROM tbl_user_batch ub
            INNER JOIN tbl_batch b ON ub.Batch_ID = b.Batch_ID
            INNER JOIN tbl_user u ON ub.User_ID = u.User_ID
            WHERE b.Section_ID = ?
            ORDER BY u.Fullname
        ");
        $stmt->execute([$section_id]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $users]);
        exit;
    }

    // Get users by batch
    if ($request_method === 'GET' && $action === 'users_by_batch') {
        $batch_id = $_GET['batch_id'] ?? '';
        
        if (empty($batch_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Batch ID is required']);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT u.User_ID, u.Fullname, u.Email, u.Username, u.Academic_Number, ub.Status, ub.Date_Enrolled
            FROM tbl_user_batch ub
            INNER JOIN tbl_user u ON ub.User_ID = u.User_ID
            WHERE ub.Batch_ID = ?
            ORDER BY u.Fullname
        ");
        $stmt->execute([$batch_id]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $users]);
        exit;
    }

    // Get batches by user
    if ($request_method === 'GET' && $action === 'batches_by_user') {
        $user_id = $_GET['user_id'] ?? '';
        
        if (empty($user_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'User ID is required']);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT b.Batch_ID, b.Batch_Name, b.Date_Started, b.Date_Ended,
                   s.Section_Name, s.Section_ID,
                   c.Course_Name, c.Course_ID,
                   ub.Status, ub.Date_Enrolled
            FROM tbl_user_batch ub
            INNER JOIN tbl_batch b ON ub.Batch_ID = b.Batch_ID
            INNER JOIN tbl_academic_section s ON b.Section_ID = s.Section_ID
            INNER JOIN tbl_course c ON b.Course_ID = c.Course_ID
            WHERE ub.User_ID = ?
            ORDER BY ub.Date_Enrolled DESC
        ");
        $stmt->execute([$user_id]);
        $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $batches]);
        exit;
    }

    // Assign user to batch
    if ($request_method === 'POST' && $action === 'assign_user') {
        $user_id = $input['user_id'] ?? $_POST['user_id'] ?? '';
        $batch_id = $input['batch_id'] ?? $_POST['batch_id'] ?? '';
        $status = $input['status'] ?? $_POST['status'] ?? 'Active';

        if (empty($user_id) || empty($batch_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'User ID and Batch ID are required']);
            exit;
        }

        $capacityCheck = validateSectionCapacityForAssignment($pdo, $batch_id, [$user_id]);
        if (!$capacityCheck['valid']) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => $capacityCheck['message'],
                'capacity' => $capacityCheck['capacity'] ?? null,
                'assigned_count' => $capacityCheck['assigned_count'] ?? null,
                'available_slots' => $capacityCheck['available_slots'] ?? null
            ]);
            exit;
        }

        $result = assignExamineeToBatch($pdo, $user_id, $batch_id, $status);
        if (!$result['success']) {
            http_response_code($result['message'] === 'User not found' ? 404 : 400);
            echo json_encode(['success' => false, 'message' => $result['message']]);
            exit;
        }

        if (!empty($result['assigned'])) {
            auditFromSession($pdo, 'BATCH', 'ASSIGN_USER_BATCH', "User {$user_id} assigned to batch {$batch_id}", 'SUCCESS', 'batch', (int)$batch_id);
        }

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => $result['message']]);
        exit;
    }

    // Assign multiple users to batch or section
    if ($request_method === 'POST' && $action === 'assign_users') {
        $batch_id = $input['batch_id'] ?? $_POST['batch_id'] ?? '';
        $section_id = $input['section_id'] ?? $_POST['section_id'] ?? '';
        $user_ids = $input['user_ids'] ?? $_POST['user_ids'] ?? [];
        $status = $input['status'] ?? $_POST['status'] ?? 'Active';

        if (!empty($section_id)) {
            $batch_id = getEnrollmentBatchId($pdo, $section_id);
            if (!$batch_id) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Section not found']);
                exit;
            }
        }

        if (empty($batch_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Batch ID or Section ID is required']);
            exit;
        }

        if (!is_array($user_ids) || count($user_ids) === 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'At least one user ID is required']);
            exit;
        }

        $capacityCheck = validateSectionCapacityForAssignment($pdo, $batch_id, $user_ids);
        if (!$capacityCheck['valid']) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => $capacityCheck['message'],
                'capacity' => $capacityCheck['capacity'] ?? null,
                'assigned_count' => $capacityCheck['assigned_count'] ?? null,
                'available_slots' => $capacityCheck['available_slots'] ?? null,
                'skipped_count' => 0
            ]);
            exit;
        }

        $assignedCount = 0;
        $skippedCount = 0;
        $errors = [];

        foreach ($user_ids as $user_id) {
            if (empty($user_id)) {
                continue;
            }

            $result = assignExamineeToBatch($pdo, $user_id, $batch_id, $status);
            if (!$result['success']) {
                $errors[] = $result['message'];
                continue;
            }

            if (!empty($result['assigned'])) {
                $assignedCount++;
            } else {
                $skippedCount++;
            }
        }

        if ($assignedCount === 0 && count($errors) > 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $errors[0],
                'assigned_count' => 0,
                'skipped_count' => $skippedCount
            ]);
            exit;
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $assignedCount > 0
                ? "{$assignedCount} examinee(s) assigned successfully"
                : 'All selected examinees were already assigned',
            'assigned_count' => $assignedCount,
            'skipped_count' => $skippedCount
        ]);
        exit;
    }

    // Remove user from batch or section
    if ($request_method === 'POST' && $action === 'remove_user') {
        $user_id = $input['user_id'] ?? $_POST['user_id'] ?? '';
        $batch_id = $input['batch_id'] ?? $_POST['batch_id'] ?? '';
        $section_id = $input['section_id'] ?? $_POST['section_id'] ?? '';

        if (!empty($section_id)) {
            $batch_id = getEnrollmentBatchId($pdo, $section_id);
            if (!$batch_id) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Section not found']);
                exit;
            }
        }

        if (empty($user_id) || empty($batch_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'User ID and Batch ID or Section ID are required']);
            exit;
        }

        if (!hasUserBatchTable($pdo)) {
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'Enrollment table not available. Please run the database migration.']);
            exit;
        }

        $stmt = $pdo->prepare('DELETE FROM tbl_user_batch WHERE Batch_ID = ? AND User_ID = ?');
        $stmt->execute([$batch_id, $user_id]);

        auditFromSession($pdo, 'BATCH', 'REMOVE_USER_BATCH', "User {$user_id} removed from batch {$batch_id}", 'SUCCESS', 'batch', (int)$batch_id);

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'User removed from batch successfully']);
        exit;
    }

    // Update user assignment status
    if ($request_method === 'POST' && $action === 'update_user_status') {
        $user_id = $input['user_id'] ?? $_POST['user_id'] ?? '';
        $batch_id = $input['batch_id'] ?? $_POST['batch_id'] ?? '';
        $status = $input['status'] ?? $_POST['status'] ?? 'Active';

        if (empty($user_id) || empty($batch_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'User ID and Batch ID are required']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE tbl_user_batch SET Status = ? WHERE Batch_ID = ? AND User_ID = ?');
        $stmt->execute([$status, $batch_id, $user_id]);

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Assignment status updated successfully']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
