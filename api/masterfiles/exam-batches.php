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
$action = $_GET['action'] ?? ($input['action'] ?? '');

// Debug logging
error_log("exam-batches.php - Method: $request_method, Action: $action, Input: " . json_encode($input));

try {
    // Assign batches to an exam
    if ($request_method === 'POST' && $action === 'assign') {
        $exam_id = $input['exam_id'] ?? '';
        $batch_ids = $input['batch_ids'] ?? [];

        if (empty($exam_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Exam ID is required']);
            exit;
        }

        // Verify exam exists
        $stmt = $pdo->prepare('SELECT Exam_ID FROM tbl_exam WHERE Exam_ID = ?');
        $stmt->execute([$exam_id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Exam not found']);
            exit;
        }

        $pdo->beginTransaction();

        // Delete existing batch assignments for this exam
        $stmt = $pdo->prepare('DELETE FROM tbl_exam_batch WHERE Exam_ID = ?');
        $stmt->execute([$exam_id]);

        // Insert new batch assignments
        if (!empty($batch_ids)) {
            $stmt = $pdo->prepare('INSERT INTO tbl_exam_batch (Exam_ID, Batch_ID, Date_Assigned) VALUES (?, ?, NOW())');
            foreach ($batch_ids as $batch_id) {
                $stmt->execute([$exam_id, $batch_id]);
            }
        }

        $pdo->commit();

        auditFromSession($pdo, 'EXAM', 'ASSIGN_EXAM_BATCH', count($batch_ids) . " batches assigned to exam {$exam_id}", 'SUCCESS', 'exam', (int)$exam_id);

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Batches assigned successfully', 'count' => count($batch_ids)]);
        exit;
    }

    // Get assigned batches for an exam
    if ($request_method === 'GET' && $action === 'get') {
        $exam_id = $_GET['exam_id'] ?? '';

        if (empty($exam_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Exam ID is required']);
            exit;
        }

        // Check if tbl_batch has Course_ID (old schema) or Section_ID (new schema)
        $columns = $pdo->query("SHOW COLUMNS FROM tbl_batch")->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = array_column($columns, 'Field');
        
        if (in_array('Section_ID', $columnNames)) {
            // New schema with Section_ID
            $stmt = $pdo->prepare('
                SELECT 
                    eb.Exam_Batch_ID,
                    eb.Batch_ID,
                    b.Batch_Name,
                    c.Course_Name
                FROM tbl_exam_batch eb
                INNER JOIN tbl_batch b ON eb.Batch_ID = b.Batch_ID
                INNER JOIN tbl_academic_section s ON b.Section_ID = s.Section_ID
                INNER JOIN tbl_course c ON b.Course_ID = c.Course_ID
                WHERE eb.Exam_ID = ?
                ORDER BY c.Course_Name, b.Batch_Name
            ');
        } else {
            // Old schema with Course_ID
            $stmt = $pdo->prepare('
                SELECT 
                    eb.Exam_Batch_ID,
                    eb.Batch_ID,
                    b.Batch_Name,
                    c.Course_Name
                FROM tbl_exam_batch eb
                INNER JOIN tbl_batch b ON eb.Batch_ID = b.Batch_ID
                INNER JOIN tbl_course c ON b.Course_ID = c.Course_ID
                WHERE eb.Exam_ID = ?
                ORDER BY c.Course_Name, b.Batch_Name
            ');
        }
        
        $stmt->execute([$exam_id]);
        $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $batches]);
        exit;
    }

    // List all batches for selection UI
    if ($request_method === 'GET' && $action === 'list') {
        // Check if tbl_batch has Course_ID (old schema) or Section_ID (new schema)
        $columns = $pdo->query("SHOW COLUMNS FROM tbl_batch")->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = array_column($columns, 'Field');
        
        if (in_array('Section_ID', $columnNames)) {
            // New schema with Section_ID
            $stmt = $pdo->prepare('
                SELECT 
                    b.Batch_ID,
                    b.Batch_Name,
                    c.Course_Name,
                    c.Course_ID
                FROM tbl_batch b
                INNER JOIN tbl_academic_section s ON b.Section_ID = s.Section_ID
                INNER JOIN tbl_course c ON b.Course_ID = c.Course_ID
                ORDER BY c.Course_Name, b.Batch_Name
            ');
            $stmt->execute();
        } else {
            // Old schema with Course_ID
            $stmt = $pdo->prepare('
                SELECT 
                    b.Batch_ID,
                    b.Batch_Name,
                    c.Course_Name,
                    c.Course_ID
                FROM tbl_batch b
                INNER JOIN tbl_course c ON b.Course_ID = c.Course_ID
                ORDER BY c.Course_Name, b.Batch_Name
            ');
            $stmt->execute();
        }
        
        $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $batches]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action', 'received_action' => $action, 'method' => $request_method]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
