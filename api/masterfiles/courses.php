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

// Check if user is Admin or CCMD
if (!in_array($_SESSION['user_role'] ?? '', ['Admin', 'CCMD'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Forbidden: Admin or CCMD access required'
    ]);
    exit;
}

require_once __DIR__ . '/../config/connection-pdo.php';
require_once __DIR__ . '/../includes/audit-log-utils.php';

$request_method = $_SERVER['REQUEST_METHOD'];
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? [];
$action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? '');

try {
    // Get all courses with sections and batches
    if ($request_method === 'GET' && $action === 'list') {
        $coursesQuery = 'SELECT Course_ID, Course_Name, Details, Duration, User_ID 
                         FROM tbl_course 
                         ORDER BY Course_Name';
        $coursesStmt = $pdo->prepare($coursesQuery);
        $coursesStmt->execute();
        $courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);

        // Get sections for each course through batches
        // Structure: Course → Batch → Section
        foreach ($courses as &$course) {
            // Get sections from batches that belong to this course
            $sectionsQuery = 'SELECT DISTINCT s.Section_ID, s.Section_Name, s.Capacity, s.Batch_ID
                              FROM tbl_academic_section s
                              INNER JOIN tbl_batch b ON s.Batch_ID = b.Batch_ID
                              WHERE b.Course_ID = ?
                              ORDER BY s.Section_Name';
            $sectionsStmt = $pdo->prepare($sectionsQuery);
            $sectionsStmt->execute([$course['Course_ID']]);
            $sections = $sectionsStmt->fetchAll(PDO::FETCH_ASSOC);

            // Get batches for each section
            foreach ($sections as &$section) {
                $batchesQuery = 'SELECT Batch_ID, Batch_Name, Date_Started, Date_Ended 
                                  FROM tbl_batch 
                                  WHERE Section_ID = ? 
                                  ORDER BY Batch_Name';
                $batchesStmt = $pdo->prepare($batchesQuery);
                $batchesStmt->execute([$section['Section_ID']]);
                $section['batches'] = $batchesStmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $course['sections'] = $sections;
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $courses
        ]);
        exit;
    }

    // Get full hierarchy for Manage Courses: Course → Batch → Section → Examinees
    if ($request_method === 'GET' && $action === 'tree') {
        $hasUserBatch = false;
        try {
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'tbl_user_batch'");
            $hasUserBatch = $tableCheck && $tableCheck->rowCount() > 0;
        } catch (Exception $e) {
            $hasUserBatch = false;
        }

        $coursesQuery = 'SELECT Course_ID, Course_Name, Details, Duration
                         FROM tbl_course
                         ORDER BY Course_Name';
        $coursesStmt = $pdo->prepare($coursesQuery);
        $coursesStmt->execute();
        $courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);

        $batchesStmt = $pdo->prepare('
            SELECT Batch_ID, Batch_Name, Date_Started, Date_Ended, Course_ID
            FROM tbl_batch
            WHERE Course_ID = ? AND (Section_ID IS NULL OR Section_ID = 0)
            ORDER BY Batch_Name
        ');

        $legacyBatchesStmt = $pdo->prepare('
            SELECT DISTINCT b.Batch_ID, b.Batch_Name, b.Date_Started, b.Date_Ended, b.Course_ID
            FROM tbl_academic_section s
            INNER JOIN tbl_batch b ON s.Batch_ID = b.Batch_ID
            WHERE b.Course_ID = ?
              AND (b.Section_ID IS NULL OR b.Section_ID = 0)
            ORDER BY b.Batch_Name
        ');

        $sectionsStmt = $pdo->prepare('
            SELECT Section_ID, Section_Name, Capacity, Batch_ID
            FROM tbl_academic_section
            WHERE Batch_ID = ?
            ORDER BY Section_Name
        ');

        $enrollmentStmt = $pdo->prepare('
            SELECT Batch_ID FROM tbl_batch WHERE Section_ID = ? LIMIT 1
        ');

        if ($hasUserBatch) {
            $assignedCountStmt = $pdo->prepare('
                SELECT COUNT(DISTINCT ub.User_ID) as assigned_count
                FROM tbl_user_batch ub
                INNER JOIN tbl_batch b ON ub.Batch_ID = b.Batch_ID
                WHERE b.Section_ID = ?
            ');

            $examineesStmt = $pdo->prepare('
                SELECT DISTINCT u.User_ID, u.Fullname, u.Email, u.Username, u.Academic_Number,
                       ub.Status, ub.Date_Enrolled, ub.Batch_ID
                FROM tbl_user_batch ub
                INNER JOIN tbl_batch b ON ub.Batch_ID = b.Batch_ID
                INNER JOIN tbl_user u ON ub.User_ID = u.User_ID
                WHERE b.Section_ID = ?
                ORDER BY u.Fullname
            ');
        }

        foreach ($courses as &$course) {
            $batchesStmt->execute([$course['Course_ID']]);
            $batches = $batchesStmt->fetchAll(PDO::FETCH_ASSOC);

            $legacyBatchesStmt->execute([$course['Course_ID']]);
            $legacyBatches = $legacyBatchesStmt->fetchAll(PDO::FETCH_ASSOC);
            $batchIds = array_column($batches, 'Batch_ID');
            foreach ($legacyBatches as $legacyBatch) {
                if (!in_array($legacyBatch['Batch_ID'], $batchIds, true)) {
                    $batches[] = $legacyBatch;
                    $batchIds[] = $legacyBatch['Batch_ID'];
                }
            }
            usort($batches, function ($a, $b) {
                return strcmp($a['Batch_Name'], $b['Batch_Name']);
            });

            foreach ($batches as &$batch) {
                $sectionsStmt->execute([$batch['Batch_ID']]);
                $sections = $sectionsStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($sections as &$section) {
                    $enrollmentStmt->execute([$section['Section_ID']]);
                    $enrollment = $enrollmentStmt->fetch(PDO::FETCH_ASSOC);
                    $section['enrollment_batch_id'] = $enrollment ? (int)$enrollment['Batch_ID'] : null;

                    if ($hasUserBatch) {
                        $assignedCountStmt->execute([$section['Section_ID']]);
                        $section['assigned_count'] = (int)($assignedCountStmt->fetch(PDO::FETCH_ASSOC)['assigned_count'] ?? 0);

                        $examineesStmt->execute([$section['Section_ID']]);
                        $section['examinees'] = $examineesStmt->fetchAll(PDO::FETCH_ASSOC);
                    } else {
                        $section['assigned_count'] = 0;
                        $section['examinees'] = [];
                    }

                    $section['available_slots'] = max(0, (int)$section['Capacity'] - $section['assigned_count']);
                }
                unset($section);

                $batch['sections'] = $sections;
            }
            unset($batch);

            $course['batches'] = $batches;
        }
        unset($course);

        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $courses]);
        exit;
    }

    // Add new course
    if ($request_method === 'POST' && $action === 'add') {
        $course_name = $input['course_name'] ?? $_POST['course_name'] ?? '';
        $details = $input['details'] ?? $_POST['details'] ?? '';
        $duration = $input['duration'] ?? $_POST['duration'] ?? '';
        $user_id = $_SESSION['user_id'];

        if (empty($course_name)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Course name is required'
            ]);
            exit;
        }

        $stmt = $pdo->prepare('INSERT INTO tbl_course (User_ID, Course_Name, Details, Duration) VALUES (?, ?, ?, ?)');
        $stmt->execute([$user_id, $course_name, $details, $duration]);

        $courseId = (int)$pdo->lastInsertId();
        auditFromSession($pdo, 'COURSE', 'CREATE_COURSE', "Course {$course_name} created (ID {$courseId})", 'SUCCESS', 'course', $courseId);

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Course added successfully',
            'course_id' => $courseId
        ]);
        exit;
    }

    // Update course
    if ($request_method === 'POST' && $action === 'update') {
        $course_id = $input['course_id'] ?? $_POST['course_id'] ?? '';
        $course_name = $input['course_name'] ?? $_POST['course_name'] ?? '';
        $details = $input['details'] ?? $_POST['details'] ?? '';
        $duration = $input['duration'] ?? $_POST['duration'] ?? '';

        if (empty($course_id) || empty($course_name)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Course ID and name are required'
            ]);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE tbl_course SET Course_Name = ?, Details = ?, Duration = ? WHERE Course_ID = ?');
        $stmt->execute([$course_name, $details, $duration, $course_id]);

        auditFromSession($pdo, 'COURSE', 'UPDATE_COURSE', "Course {$course_name} updated (ID {$course_id})", 'SUCCESS', 'course', (int)$course_id);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Course updated successfully'
        ]);
        exit;
    }

    // Delete course
    if ($request_method === 'POST' && $action === 'delete') {
        $course_id = $input['course_id'] ?? $_POST['course_id'] ?? '';

        if (empty($course_id)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Course ID is required'
            ]);
            exit;
        }

        $stmt = $pdo->prepare('DELETE FROM tbl_course WHERE Course_ID = ?');
        $stmt->execute([$course_id]);

        auditFromSession($pdo, 'COURSE', 'DELETE_COURSE', "Course ID {$course_id} deleted", 'SUCCESS', 'course', (int)$course_id);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Course deleted successfully'
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
