<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'CCMD'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/connection-pdo.php';
require_once __DIR__ . '/../includes/batch-enrollment-utils.php';
require_once __DIR__ . '/../includes/audit-log-utils.php';
require_once __DIR__ . '/../includes/archive-utils.php';
require_once __DIR__ . '/../includes/validation-utils.php';

$request_method = $_SERVER['REQUEST_METHOD'];
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? [];
$action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? '');

try {
    // Get sections by course (through batches)
    if ($request_method === 'GET' && $action === 'list') {
        $course_id = $_GET['course_id'] ?? '';
        
        if (empty($course_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Course ID is required']);
            exit;
        }

        // Get sections from batches that belong to this course
        $stmt = $pdo->prepare('
            SELECT DISTINCT s.Section_ID, s.Section_Name, s.Capacity 
            FROM tbl_academic_section s
            INNER JOIN tbl_batch b ON s.Batch_ID = b.Batch_ID
            WHERE b.Course_ID = ?
            ORDER BY s.Section_Name
        ');
        $stmt->execute([$course_id]);
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $sections]);
        exit;
    }

    // Add section (under batch or legacy course)
    if ($request_method === 'POST' && $action === 'add') {
        $batch_id = $input['batch_id'] ?? $_POST['batch_id'] ?? '';
        $course_id = $input['course_id'] ?? $_POST['course_id'] ?? '';
        $section_name = $input['section_name'] ?? $_POST['section_name'] ?? '';
        $capacity = $input['capacity'] ?? $_POST['capacity'] ?? 40;

        if (empty($section_name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Section name is required']);
            exit;
        }

        if ((int)$capacity < 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Capacity must be at least 1']);
            exit;
        }

        // New hierarchy: section under parent batch
        if (!empty($batch_id)) {
            $batchCheck = $pdo->prepare('
                SELECT b.Batch_ID, b.Course_ID
                FROM tbl_batch b
                WHERE b.Batch_ID = ?
                  AND (
                      b.Section_ID IS NULL
                      OR b.Section_ID = 0
                      OR EXISTS (
                          SELECT 1
                          FROM tbl_academic_section s
                          WHERE s.Batch_ID = b.Batch_ID
                      )
                  )
            ');
            $batchCheck->execute([$batch_id]);
            $parentBatch = $batchCheck->fetch(PDO::FETCH_ASSOC);

            if (!$parentBatch) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid batch ID. Use a course batch, not a section enrollment batch.']);
                exit;
            }

            if (sectionNameExistsInBatch($pdo, (int)$batch_id, $section_name)) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'A section with this name already exists in this batch']);
                exit;
            }

            $stmt = $pdo->prepare('INSERT INTO tbl_academic_section (Batch_ID, Section_Name, Capacity) VALUES (?, ?, ?)');
            $stmt->execute([$batch_id, $section_name, $capacity]);
            $section_id = (int)$pdo->lastInsertId();

            $existingEnrollment = $pdo->prepare('SELECT Batch_ID FROM tbl_batch WHERE Section_ID = ? LIMIT 1');
            $existingEnrollment->execute([$section_id]);
            $enrollmentBatch = $existingEnrollment->fetch(PDO::FETCH_ASSOC);

            if (!$enrollmentBatch) {
                $enrollStmt = $pdo->prepare('INSERT INTO tbl_batch (Course_ID, Section_ID, Batch_Name, Status) VALUES (?, ?, ?, ?)');
                $enrollStmt->execute([
                    $parentBatch['Course_ID'],
                    $section_id,
                    $section_name . ' Enrollment',
                    'Active'
                ]);
                $enrollmentBatch = ['Batch_ID' => (int)$pdo->lastInsertId()];
            }

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Section added successfully',
                'section_id' => $section_id,
                'enrollment_batch_id' => (int)$enrollmentBatch['Batch_ID']
            ]);
            exit;
        }

        // Legacy: section under course (creates/uses default batch)
        if (empty($course_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Batch ID or Course ID is required']);
            exit;
        }

        $courseCheck = $pdo->prepare('SELECT Course_ID FROM tbl_course WHERE Course_ID = ?');
        $courseCheck->execute([$course_id]);
        if (!$courseCheck->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid course ID: ' . $course_id]);
            exit;
        }

        $batchStmt = $pdo->prepare('SELECT Batch_ID FROM tbl_batch WHERE Course_ID = ? AND Batch_Name = ? LIMIT 1');
        $defaultBatchName = 'Default Batch';
        $batchStmt->execute([$course_id, $defaultBatchName]);
        $batch = $batchStmt->fetch(PDO::FETCH_ASSOC);

        if (!$batch) {
            $createBatchStmt = $pdo->prepare('INSERT INTO tbl_batch (Course_ID, Batch_Name, Status) VALUES (?, ?, ?)');
            $createBatchStmt->execute([$course_id, $defaultBatchName, 'Active']);
            $batch_id = $pdo->lastInsertId();
        } else {
            $batch_id = $batch['Batch_ID'];
        }

        if (sectionNameExistsInBatch($pdo, (int)$batch_id, $section_name)) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'A section with this name already exists in this batch']);
            exit;
        }

        $stmt = $pdo->prepare('INSERT INTO tbl_academic_section (Batch_ID, Section_Name, Capacity) VALUES (?, ?, ?)');
        $stmt->execute([$batch_id, $section_name, $capacity]);
        $section_id = (int)$pdo->lastInsertId();

        $courseStmt = $pdo->prepare('SELECT Course_ID FROM tbl_batch WHERE Batch_ID = ?');
        $courseStmt->execute([$batch_id]);
        $courseRow = $courseStmt->fetch(PDO::FETCH_ASSOC);

        if ($courseRow) {
            $enrollStmt = $pdo->prepare('INSERT INTO tbl_batch (Course_ID, Section_ID, Batch_Name, Status) VALUES (?, ?, ?, ?)');
            $enrollStmt->execute([
                $courseRow['Course_ID'],
                $section_id,
                $section_name . ' Enrollment',
                'Active'
            ]);
        }

        http_response_code(201);
        echo json_encode(['success' => true, 'message' => 'Section added successfully', 'section_id' => $section_id]);
        exit;
    }

    // Update section
    if ($request_method === 'POST' && $action === 'update') {
        $section_id = $input['section_id'] ?? $_POST['section_id'] ?? '';
        $section_name = $input['section_name'] ?? $_POST['section_name'] ?? '';
        $capacity = $input['capacity'] ?? $_POST['capacity'] ?? 40;

        if (empty($section_id) || empty($section_name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Section ID and name are required']);
            exit;
        }

        $assignedCount = 0;
        try {
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'tbl_user_batch'");
            if ($tableCheck && $tableCheck->rowCount() > 0) {
                $assignedStmt = $pdo->prepare('
                    SELECT COUNT(DISTINCT ub.User_ID) as assigned_count
                    FROM tbl_user_batch ub
                    INNER JOIN tbl_batch b ON ub.Batch_ID = b.Batch_ID
                    WHERE b.Section_ID = ?
                ');
                $assignedStmt->execute([$section_id]);
                $assignedCount = (int)($assignedStmt->fetch(PDO::FETCH_ASSOC)['assigned_count'] ?? 0);
            }
        } catch (Exception $e) {
            $assignedCount = 0;
        }

        if ((int)$capacity < $assignedCount) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => "Cannot reduce capacity below currently assigned trainees. Currently Assigned: {$assignedCount}, Requested Capacity: {$capacity}."
            ]);
            exit;
        }

        $parentBatchStmt = $pdo->prepare('SELECT Batch_ID FROM tbl_academic_section WHERE Section_ID = ?');
        $parentBatchStmt->execute([$section_id]);
        $parentBatchId = (int)$parentBatchStmt->fetchColumn();
        if ($parentBatchId > 0 && sectionNameExistsInBatch($pdo, $parentBatchId, $section_name, (int)$section_id)) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'A section with this name already exists in this batch']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE tbl_academic_section SET Section_Name = ?, Capacity = ? WHERE Section_ID = ?');
        $stmt->execute([$section_name, $capacity, $section_id]);

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Section updated successfully']);
        exit;
    }

    // Archive section
    if ($request_method === 'POST' && $action === 'archive') {
        $section_id = (int)($input['section_id'] ?? $_POST['section_id'] ?? 0);
        if ($section_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Section ID is required']);
            exit;
        }

        $enrolledCount = sectionHasEnrolledTrainees($pdo, $section_id);
        if ($enrolledCount > 0) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => "Cannot archive section while {$enrolledCount} trainee(s) are still enrolled. Remove assignments first.",
            ]);
            exit;
        }

        if (!archiveRecord($pdo, 'tbl_academic_section', 'Section_ID', $section_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unable to archive section. Run the archive migration first.']);
            exit;
        }

        auditFromSession($pdo, 'SECTION', 'ARCHIVE_SECTION', "Section {$section_id} archived", 'SUCCESS', 'section', $section_id);
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Section archived successfully']);
        exit;
    }

    if ($request_method === 'POST' && $action === 'restore') {
        $section_id = (int)($input['section_id'] ?? $_POST['section_id'] ?? 0);
        if ($section_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Section ID is required']);
            exit;
        }

        $metaStmt = $pdo->prepare('
            SELECT sec.Section_ID, sec.Batch_ID, b.Course_ID
            FROM tbl_academic_section sec
            INNER JOIN tbl_batch b ON sec.Batch_ID = b.Batch_ID
            WHERE sec.Section_ID = ?
            LIMIT 1
        ');
        $metaStmt->execute([$section_id]);
        $meta = $metaStmt->fetch(PDO::FETCH_ASSOC);

        if (!$meta) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Section not found']);
            exit;
        }

        if (!restoreRecord($pdo, 'tbl_academic_section', 'Section_ID', $section_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => restoreFailureMessage($pdo, 'tbl_academic_section', 'Section_ID', $section_id, 'section')]);
            exit;
        }

        $ancestors = restoreCurriculumAncestors($pdo, (int)$meta['Batch_ID'], (int)$meta['Course_ID']);

        auditFromSession($pdo, 'SECTION', 'RESTORE_SECTION', "Section {$section_id} restored", 'SUCCESS', 'section', $section_id);

        $message = 'Section restored successfully';
        if (!empty($ancestors)) {
            $message = 'Section restored successfully (parent ' . implode(' and ', $ancestors) . ' also restored). Other sections in this batch remain archived until restored individually.';
        } else {
            $message = 'Section restored successfully. Other sections in this batch remain archived until restored individually.';
        }

        echo json_encode(['success' => true, 'message' => $message]);
        exit;
    }

    if ($request_method === 'POST' && $action === 'delete') {
        archiveDeletionBlockedResponse();
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
