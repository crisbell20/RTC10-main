<?php

// Courses API

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
require_once __DIR__ . '/../includes/batch-enrollment-utils.php';
require_once __DIR__ . '/../includes/archive-utils.php';
require_once __DIR__ . '/../includes/validation-utils.php';

$request_method = $_SERVER['REQUEST_METHOD'];
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? [];
$action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? '');

try {
    // Get all courses with sections and batches
    if ($request_method === 'GET' && $action === 'list') {
        $coursesQuery = 'SELECT Course_ID, Course_Name, Details, Duration, User_ID 
                         FROM tbl_course 
                         WHERE ' . activeArchiveCondition($pdo, 'tbl_course') . '
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
        backfillCascadeArchives($pdo);
        autoArchiveExpiredBatches($pdo, true);

        $hasUserBatch = false;
        try {
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'tbl_user_batch'");
            $hasUserBatch = $tableCheck && $tableCheck->rowCount() > 0;
        } catch (Exception $e) {
            $hasUserBatch = false;
        }

        $courseActive = activeArchiveCondition($pdo, 'tbl_course');
        $coursesQuery = "SELECT Course_ID, Course_Name, Details, Duration
                         FROM tbl_course
                         WHERE {$courseActive}
                         ORDER BY Course_Name";
        $coursesStmt = $pdo->prepare($coursesQuery);
        $coursesStmt->execute();
        $courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);

        $batchVisibility = courseBatchVisibilityCondition('b');
        $batchActive = activeArchiveCondition($pdo, 'tbl_batch', 'b');
        $batchesStmt = $pdo->prepare("
            SELECT Batch_ID, Batch_Name, Date_Started, Date_Ended, Course_ID
            FROM tbl_batch b
            WHERE b.Course_ID = ? AND {$batchVisibility} AND {$batchActive}
            ORDER BY Batch_Name
        ");

        $legacyBatchesStmt = $pdo->prepare("
            SELECT DISTINCT b.Batch_ID, b.Batch_Name, b.Date_Started, b.Date_Ended, b.Course_ID
            FROM tbl_academic_section s
            INNER JOIN tbl_batch b ON s.Batch_ID = b.Batch_ID
            WHERE b.Course_ID = ?
              AND {$batchVisibility}
              AND {$batchActive}
            ORDER BY b.Batch_Name
        ");

        $sectionActive = activeArchiveCondition($pdo, 'tbl_academic_section');
        $sectionsStmt = $pdo->prepare("
            SELECT Section_ID, Section_Name, Capacity, Batch_ID
            FROM tbl_academic_section
            WHERE Batch_ID = ? AND {$sectionActive}
            ORDER BY Section_Name
        ");

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
        }

        foreach ($courses as &$course) {
            deleteOrphanedEnrollmentBatchesForCourse($pdo, $course['Course_ID']);

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
                    } else {
                        $section['assigned_count'] = 0;
                    }
                    $section['examinees'] = [];

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
        $user_id = $_SESSION['user_id'];

        if (empty($course_name)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Course name is required'
            ]);
            exit;
        }

        if (courseNameExists($pdo, $course_name)) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'A course with this name already exists',
            ]);
            exit;
        }

        $stmt = $pdo->prepare('INSERT INTO tbl_course (User_ID, Course_Name, Details, Duration) VALUES (?, ?, ?, NULL)');
        $stmt->execute([$user_id, $course_name, $details]);

        $courseId = (int)$pdo->lastInsertId();
        auditFromSession($pdo, 'COURSE', 'CREATE_COURSE', "Course {$course_name} created", 'SUCCESS', 'course', $courseId);

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

        if (empty($course_id) || empty($course_name)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Course ID and name are required'
            ]);
            exit;
        }

        if (courseNameExists($pdo, $course_name, (int)$course_id)) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'A course with this name already exists',
            ]);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE tbl_course SET Course_Name = ?, Details = ? WHERE Course_ID = ?');
        $stmt->execute([$course_name, $details, $course_id]);

        auditFromSession($pdo, 'COURSE', 'UPDATE_COURSE', "Course {$course_name} updated", 'SUCCESS', 'course', (int)$course_id);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Course updated successfully'
        ]);
        exit;
    }

    // Archive course (soft delete)
    if ($request_method === 'POST' && $action === 'archive') {
        $course_id = (int)($input['course_id'] ?? $_POST['course_id'] ?? 0);

        if ($course_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Course ID is required']);
            exit;
        }

        $activeSessions = countActiveExamSessionsForCourse($pdo, $course_id);
        if ($activeSessions > 0) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => "Cannot archive course while {$activeSessions} trainee session(s) are still in progress. Wait for exams to finish or finalize them first.",
            ]);
            exit;
        }

        $result = archiveCourseWithDescendants($pdo, $course_id);
        if (!$result['course']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unable to archive course. Run the archive migration first.']);
            exit;
        }

        $courseLabel = auditCourseLabel($pdo, $course_id);
        $detail = "Course {$courseLabel} archived";
        if (!empty($result['exams'])) {
            $detail .= "; {$result['exams']} exam(s)";
        }
        if (!empty($result['enrollments'])) {
            $detail .= "; {$result['enrollments']} assignment(s) deactivated";
        }
        auditFromSession($pdo, 'COURSE', 'ARCHIVE_COURSE', $detail, 'SUCCESS', 'course', $course_id);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => formatCascadeArchiveMessage('course', $result),
            'cascade' => $result,
        ]);
        exit;
    }

    // Restore archived course (full cascade)
    if ($request_method === 'POST' && $action === 'restore') {
        $course_id = (int)($input['course_id'] ?? $_POST['course_id'] ?? 0);

        if ($course_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Course ID is required']);
            exit;
        }

        $result = restoreCourseWithDescendants($pdo, $course_id);
        if (!$result['course']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => restoreFailureMessage($pdo, 'tbl_course', 'Course_ID', $course_id, 'course')]);
            exit;
        }

        $courseLabel = auditCourseLabel($pdo, $course_id);
        $detail = "Course {$courseLabel} restored";
        if (!empty($result['exams'])) {
            $detail .= "; {$result['exams']} exam(s)";
        }
        if (!empty($result['enrollments'])) {
            $detail .= "; {$result['enrollments']} assignment(s) reactivated";
        }
        auditFromSession($pdo, 'COURSE', 'RESTORE_COURSE', $detail, 'SUCCESS', 'course', $course_id);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => formatCascadeRestoreMessage('course', $result),
            'cascade' => $result,
        ]);
        exit;
    }

    // Delete course — disabled; use archive instead
    if ($request_method === 'POST' && $action === 'delete') {
        archiveDeletionBlockedResponse();
    }

    // Invalid action
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action'
    ]);

} catch (Exception $e) {
    error_log('courses.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again later.'
    ]);
}
?>
