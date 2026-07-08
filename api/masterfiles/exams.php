<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'CCMD'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/connection-pdo.php';
require_once __DIR__ . '/../includes/exam-code-utils.php';

$request_method = $_SERVER['REQUEST_METHOD'];
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? [];
$action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? '');
$hasExamCode = examCodeColumnsExist($pdo);
$hasResponseReview = responseReviewColumnExists($pdo);

try {
    // List exams, optional filters: course_id, subject_id
    if ($request_method === 'GET' && $action === 'list') {
        $course_id = $_GET['course_id'] ?? null;
        $subject_id = $_GET['subject_id'] ?? null;
        $archived_only = ($_GET['archived_only'] ?? '') === '1';

        $codeColumns = $hasExamCode
            ? 'e.Exam_Code, e.Exam_Code_Generated_At, e.Exam_Code_Reset_Count, e.Is_Archived, e.Archived_At,'
            : '';
        $reviewColumn = $hasResponseReview ? 'e.Allow_Response_Review,' : '';

        $sql = "
            SELECT e.Exam_ID, e.User_ID, e.Subject_ID, e.Title, e.Description,
                   e.Schedule_Date, e.Deadline, e.Duration, e.Passing_Score, e.Status,
                   e.Is_Randomized, e.Time_Limit,
                   {$codeColumns}{$reviewColumn}
                   s.Subject_Name, s.Subject_Code, s.Course_ID,
                   c.Course_Name,
                   COUNT(DISTINCT eq.Question_ID) as Question_Count,
                   COUNT(DISTINCT eb.Batch_ID) as Batch_Count,
                   COUNT(DISTINCT es.Session_ID) as Session_Count,
                   COUNT(DISTINCT CASE WHEN es.Time_Ended IS NOT NULL THEN es.Session_ID END) as Finished_Count
            FROM tbl_exam e
            INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
            INNER JOIN tbl_course c ON s.Course_ID = c.Course_ID
            LEFT JOIN tbl_exam_question eq ON e.Exam_ID = eq.Exam_ID
            LEFT JOIN tbl_exam_batch eb ON e.Exam_ID = eb.Exam_ID
            LEFT JOIN tbl_exam_session es ON e.Exam_ID = es.Exam_ID
            WHERE 1=1
        ";
        $params = [];

        if ($hasExamCode) {
            if ($archived_only) {
                $sql .= ' AND e.Is_Archived = 1';
            } else {
                $sql .= ' AND (e.Is_Archived = 0 OR e.Is_Archived IS NULL)';
            }
        }

        if ($course_id) {
            $sql .= ' AND s.Course_ID = ?';
            $params[] = $course_id;
        }
        if ($subject_id) {
            $sql .= ' AND e.Subject_ID = ?';
            $params[] = $subject_id;
        }

        $groupFields = 'e.Exam_ID';
        if ($hasExamCode) {
            $groupFields .= ', e.Exam_Code, e.Exam_Code_Generated_At, e.Exam_Code_Reset_Count, e.Is_Archived, e.Archived_At';
        }
        if ($hasResponseReview) {
            $groupFields .= ', e.Allow_Response_Review';
        }

        $sql .= " GROUP BY {$groupFields}";
        $sql .= ' ORDER BY c.Course_Name, s.Subject_Name, e.Title';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($hasExamCode) {
            foreach ($exams as &$exam) {
                if (empty($exam['Exam_Code'])) {
                    $code = generateUniqueExamCode($pdo);
                    $upd = $pdo->prepare('
                        UPDATE tbl_exam
                        SET Exam_Code = ?, Exam_Code_Generated_At = NOW()
                        WHERE Exam_ID = ?
                    ');
                    $upd->execute([$code, $exam['Exam_ID']]);
                    $exam['Exam_Code'] = $code;
                    $exam['Exam_Code_Generated_At'] = date('Y-m-d H:i:s');
                }
            }
            unset($exam);
        }

        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $exams]);
        exit;
    }

    // Add exam
    if ($request_method === 'POST' && $action === 'add') {
        $subject_id = $input['subject_id'] ?? $_POST['subject_id'] ?? '';
        $title = $input['title'] ?? $_POST['title'] ?? '';
        $description = $input['description'] ?? $_POST['description'] ?? null;
        $schedule_date = $input['schedule_date'] ?? $_POST['schedule_date'] ?? null;
        $deadline = $input['deadline'] ?? $_POST['deadline'] ?? null;
        $duration = $input['duration'] ?? $_POST['duration'] ?? null;
        $passing_score = $input['passing_score'] ?? $_POST['passing_score'] ?? null;
        $status = $input['status'] ?? $_POST['status'] ?? 'Draft';
        $is_randomized = (int)($input['is_randomized'] ?? $_POST['is_randomized'] ?? 0);
        $time_limit = $input['time_limit'] ?? $_POST['time_limit'] ?? null;
        $user_id = $_SESSION['user_id'];

        if (empty($subject_id) || empty($title)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Subject and title are required']);
            exit;
        }

        $examCode = null;
        if ($hasExamCode) {
            $examCode = generateUniqueExamCode($pdo);
            $stmt = $pdo->prepare('
                INSERT INTO tbl_exam
                (User_ID, Subject_ID, Title, Description, Schedule_Date, Deadline, Duration,
                 Passing_Score, Status, Is_Randomized, Time_Limit, Exam_Code, Exam_Code_Generated_At)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ');
            $stmt->execute([
                $user_id,
                $subject_id,
                $title,
                $description,
                $schedule_date ?: null,
                $deadline ?: null,
                $duration ?: null,
                $passing_score ?: null,
                $status,
                $is_randomized,
                $time_limit ?: null,
                $examCode
            ]);
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO tbl_exam
                (User_ID, Subject_ID, Title, Description, Schedule_Date, Deadline, Duration,
                 Passing_Score, Status, Is_Randomized, Time_Limit)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $user_id,
                $subject_id,
                $title,
                $description,
                $schedule_date ?: null,
                $deadline ?: null,
                $duration ?: null,
                $passing_score ?: null,
                $status,
                $is_randomized,
                $time_limit ?: null
            ]);
        }

        $examId = (int)$pdo->lastInsertId();
        logExamAuditAction($pdo, (int)$user_id, 'CREATE_EXAM', "Exam {$examId} created");

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Exam added',
            'exam_id' => $examId,
            'exam_code' => $examCode
        ]);
        exit;
    }

    // Update exam
    if ($request_method === 'POST' && $action === 'update') {
        $exam_id = $input['exam_id'] ?? $_POST['exam_id'] ?? '';
        $subject_id = $input['subject_id'] ?? $_POST['subject_id'] ?? '';
        $title = $input['title'] ?? $_POST['title'] ?? '';
        $description = $input['description'] ?? $_POST['description'] ?? null;
        $schedule_date = $input['schedule_date'] ?? $_POST['schedule_date'] ?? null;
        $deadline = $input['deadline'] ?? $_POST['deadline'] ?? null;
        $duration = $input['duration'] ?? $_POST['duration'] ?? null;
        $passing_score = $input['passing_score'] ?? $_POST['passing_score'] ?? null;
        $status = $input['status'] ?? $_POST['status'] ?? 'Draft';
        $is_randomized = (int)($input['is_randomized'] ?? $_POST['is_randomized'] ?? 0);
        $time_limit = $input['time_limit'] ?? $_POST['time_limit'] ?? null;

        if (empty($exam_id) || empty($subject_id) || empty($title)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Exam ID, subject and title are required']);
            exit;
        }

        if ($hasExamCode) {
            $archivedCheck = $pdo->prepare('SELECT Is_Archived FROM tbl_exam WHERE Exam_ID = ?');
            $archivedCheck->execute([$exam_id]);
            $archivedRow = $archivedCheck->fetch(PDO::FETCH_ASSOC);
            if ($archivedRow && (int)$archivedRow['Is_Archived'] === 1) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Archived exams cannot be edited. Restore it first.']);
                exit;
            }
        }

        if ($status === 'Published') {
            $stmt = $pdo->prepare('SELECT COUNT(*) as question_count FROM tbl_exam_question WHERE Exam_ID = ?');
            $stmt->execute([$exam_id]);
            $question_count = $stmt->fetch(PDO::FETCH_ASSOC)['question_count'];

            if ($question_count == 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Cannot publish exam without questions']);
                exit;
            }

            $stmt = $pdo->prepare('SELECT COUNT(*) as batch_count FROM tbl_exam_batch WHERE Exam_ID = ?');
            $stmt->execute([$exam_id]);
            $batch_count = $stmt->fetch(PDO::FETCH_ASSOC)['batch_count'];

            if ($batch_count == 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Cannot publish exam without batch assignments']);
                exit;
            }
        }

        $stmt = $pdo->prepare('
            UPDATE tbl_exam
               SET Subject_ID = ?,
                   Title = ?,
                   Description = ?,
                   Schedule_Date = ?,
                   Deadline = ?,
                   Duration = ?,
                   Passing_Score = ?,
                   Status = ?,
                   Is_Randomized = ?,
                   Time_Limit = ?
             WHERE Exam_ID = ?
        ');
        $stmt->execute([
            $subject_id,
            $title,
            $description,
            $schedule_date ?: null,
            $deadline ?: null,
            $duration ?: null,
            $passing_score ?: null,
            $status,
            $is_randomized,
            $time_limit ?: null,
            $exam_id
        ]);

        logExamAuditAction($pdo, (int)$_SESSION['user_id'], 'UPDATE_EXAM', "Exam {$exam_id} updated");

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Exam updated']);
        exit;
    }

    // Reset exam access code
    if ($request_method === 'POST' && $action === 'reset_code') {
        if (!$hasExamCode) {
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'Exam code feature is not available. Run the database migration.']);
            exit;
        }

        $exam_id = $input['exam_id'] ?? $_POST['exam_id'] ?? '';
        if (empty($exam_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Exam ID is required']);
            exit;
        }

        $examCode = generateUniqueExamCode($pdo);
        $stmt = $pdo->prepare('
            UPDATE tbl_exam
            SET Exam_Code = ?,
                Exam_Code_Generated_At = NOW(),
                Exam_Code_Reset_Count = Exam_Code_Reset_Count + 1
            WHERE Exam_ID = ?
        ');
        $stmt->execute([$examCode, $exam_id]);

        logExamAuditAction($pdo, (int)$_SESSION['user_id'], 'RESET_EXAM_CODE', "Exam {$exam_id} code reset");

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Exam code reset successfully',
            'exam_code' => $examCode
        ]);
        exit;
    }

    // Archive exam
    if ($request_method === 'POST' && $action === 'archive') {
        $exam_id = $input['exam_id'] ?? $_POST['exam_id'] ?? '';
        if (empty($exam_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Exam ID is required']);
            exit;
        }

        if ($hasExamCode) {
            $stmt = $pdo->prepare('
                UPDATE tbl_exam
                SET Is_Archived = 1,
                    Archived_At = NOW(),
                    Status = CASE WHEN Status = \'Published\' THEN \'Closed\' ELSE Status END
                WHERE Exam_ID = ?
            ');
        } else {
            $stmt = $pdo->prepare("UPDATE tbl_exam SET Status = 'Closed' WHERE Exam_ID = ?");
        }
        $stmt->execute([$exam_id]);

        logExamAuditAction($pdo, (int)$_SESSION['user_id'], 'ARCHIVE_EXAM', "Exam {$exam_id} archived");

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Exam archived successfully']);
        exit;
    }

    // Restore archived exam
    if ($request_method === 'POST' && $action === 'restore') {
        if (!$hasExamCode) {
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'Archive feature is not available. Run the database migration.']);
            exit;
        }

        $exam_id = $input['exam_id'] ?? $_POST['exam_id'] ?? '';
        if (empty($exam_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Exam ID is required']);
            exit;
        }

        $stmt = $pdo->prepare('
            UPDATE tbl_exam
            SET Is_Archived = 0,
                Archived_At = NULL
            WHERE Exam_ID = ?
        ');
        $stmt->execute([$exam_id]);

        logExamAuditAction($pdo, (int)$_SESSION['user_id'], 'RESTORE_EXAM', "Exam {$exam_id} restored");

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Exam restored successfully']);
        exit;
    }

    // Close exam
    if ($request_method === 'POST' && $action === 'close') {
        $exam_id = $input['exam_id'] ?? $_POST['exam_id'] ?? '';
        if (empty($exam_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Exam ID is required']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE tbl_exam SET Status = 'Closed' WHERE Exam_ID = ?");
        $stmt->execute([$exam_id]);

        logExamAuditAction($pdo, (int)$_SESSION['user_id'], 'CLOSE_EXAM', "Exam {$exam_id} closed");

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Exam closed successfully']);
        exit;
    }

    // Toggle response review (examinee question breakdown after submission)
    if ($request_method === 'POST' && $action === 'toggle_response_review') {
        if (!$hasResponseReview) {
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'Response review feature is not available. Run the database migration.']);
            exit;
        }

        $exam_id = $input['exam_id'] ?? $_POST['exam_id'] ?? '';
        if (empty($exam_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Exam ID is required']);
            exit;
        }

        $stmt = $pdo->prepare('SELECT Allow_Response_Review FROM tbl_exam WHERE Exam_ID = ?');
        $stmt->execute([$exam_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Exam not found']);
            exit;
        }

        $newValue = (int)!((int)$row['Allow_Response_Review']);
        $upd = $pdo->prepare('UPDATE tbl_exam SET Allow_Response_Review = ? WHERE Exam_ID = ?');
        $upd->execute([$newValue, $exam_id]);

        $label = $newValue ? 'enabled' : 'disabled';
        logExamAuditAction($pdo, (int)$_SESSION['user_id'], 'TOGGLE_RESPONSE_REVIEW', "Exam {$exam_id} response review {$label}");

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $newValue ? 'Response review enabled' : 'Response review disabled',
            'allow_response_review' => $newValue
        ]);
        exit;
    }

    // Delete exam
    if ($request_method === 'POST' && $action === 'delete') {
        $exam_id = $input['exam_id'] ?? $_POST['exam_id'] ?? '';

        if (empty($exam_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Exam ID is required']);
            exit;
        }

        $sessionStmt = $pdo->prepare('SELECT COUNT(*) AS session_count FROM tbl_exam_session WHERE Exam_ID = ?');
        $sessionStmt->execute([$exam_id]);
        $sessionCount = (int)($sessionStmt->fetch(PDO::FETCH_ASSOC)['session_count'] ?? 0);

        if ($sessionCount > 0) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'Cannot delete an exam with student sessions. Archive it instead.'
            ]);
            exit;
        }

        $statusStmt = $pdo->prepare('SELECT Status FROM tbl_exam WHERE Exam_ID = ?');
        $statusStmt->execute([$exam_id]);
        $statusRow = $statusStmt->fetch(PDO::FETCH_ASSOC);

        if ($statusRow && $statusRow['Status'] === 'Published') {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'Cannot delete a published exam. Close or archive it instead.'
            ]);
            exit;
        }

        $stmt = $pdo->prepare('DELETE FROM tbl_exam WHERE Exam_ID = ?');
        $stmt->execute([$exam_id]);

        logExamAuditAction($pdo, (int)$_SESSION['user_id'], 'DELETE_EXAM', "Exam {$exam_id} deleted");

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Exam deleted']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
