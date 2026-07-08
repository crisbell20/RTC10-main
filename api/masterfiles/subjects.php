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

try {
    // List subjects, optionally filtered by course
    if ($request_method === 'GET' && $action === 'list') {
        $course_id = $_GET['course_id'] ?? null;

        if ($course_id) {
            $stmt = $pdo->prepare('SELECT Subject_ID, Course_ID, Subject_Name, Subject_Code, Description FROM tbl_subject WHERE Course_ID = ? ORDER BY Subject_Name');
            $stmt->execute([$course_id]);
        } else {
            $stmt = $pdo->query('SELECT Subject_ID, Course_ID, Subject_Name, Subject_Code, Description FROM tbl_subject ORDER BY Subject_Name');
        }

        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $subjects]);
        exit;
    }

    // Add subject
    if ($request_method === 'POST' && $action === 'add') {
        $course_id = $input['course_id'] ?? $_POST['course_id'] ?? '';
        $name = $input['subject_name'] ?? $_POST['subject_name'] ?? '';
        $code = $input['subject_code'] ?? $_POST['subject_code'] ?? '';
        $desc = $input['description'] ?? $_POST['description'] ?? null;

        if (empty($course_id) || empty($name) || empty($code)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Course, subject name and code are required']);
            exit;
        }

        // Check if subject code already exists
        $checkStmt = $pdo->prepare('SELECT Subject_ID FROM tbl_subject WHERE Subject_Code = ?');
        $checkStmt->execute([$code]);
        if ($checkStmt->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Subject code already exists. Please use a different code.']);
            exit;
        }

        $stmt = $pdo->prepare('INSERT INTO tbl_subject (Course_ID, Subject_Name, Subject_Code, Description) VALUES (?, ?, ?, ?)');
        $stmt->execute([$course_id, $name, $code, $desc]);

        http_response_code(201);
        $subjectId = (int)$pdo->lastInsertId();
        auditFromSession($pdo, 'SUBJECT', 'CREATE_SUBJECT', "Subject {$name} created (ID {$subjectId})", 'SUCCESS', 'subject', $subjectId);
        echo json_encode(['success' => true, 'message' => 'Subject added', 'subject_id' => $subjectId]);
        exit;
    }

    // Update subject
    if ($request_method === 'POST' && $action === 'update') {
        $subject_id = $input['subject_id'] ?? $_POST['subject_id'] ?? '';
        $name = $input['subject_name'] ?? $_POST['subject_name'] ?? '';
        $code = $input['subject_code'] ?? $_POST['subject_code'] ?? '';
        $desc = $input['description'] ?? $_POST['description'] ?? null;

        if (empty($subject_id) || empty($name) || empty($code)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Subject ID, name and code are required']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE tbl_subject SET Subject_Name = ?, Subject_Code = ?, Description = ? WHERE Subject_ID = ?');
        $stmt->execute([$name, $code, $desc, $subject_id]);

        http_response_code(200);
        auditFromSession($pdo, 'SUBJECT', 'UPDATE_SUBJECT', "Subject ID {$subject_id} updated", 'SUCCESS', 'subject', (int)$subject_id);
        echo json_encode(['success' => true, 'message' => 'Subject updated']);
        exit;
    }

    // Delete subject
    if ($request_method === 'POST' && $action === 'delete') {
        $subject_id = $input['subject_id'] ?? $_POST['subject_id'] ?? '';

        if (empty($subject_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Subject ID is required']);
            exit;
        }

        $stmt = $pdo->prepare('DELETE FROM tbl_subject WHERE Subject_ID = ?');
        $stmt->execute([$subject_id]);

        http_response_code(200);
        auditFromSession($pdo, 'SUBJECT', 'DELETE_SUBJECT', "Subject ID {$subject_id} deleted", 'SUCCESS', 'subject', (int)$subject_id);
        echo json_encode(['success' => true, 'message' => 'Subject deleted']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
} catch (PDOException $e) {
    error_log("Database error in subjects.php: " . $e->getMessage());
    
    // Check for duplicate entry error
    if ($e->getCode() == 23000) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Subject code already exists. Please use a different code.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    error_log("Error in subjects.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>

