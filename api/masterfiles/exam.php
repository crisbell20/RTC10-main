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
$action = $_GET['action'] ?? '';
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? [];

try {
    // Add new question
    if ($request_method === 'POST' && $action === 'add_question') {
        $subject_id = $input['subject_id'] ?? '';
        $question_text = $input['question_text'] ?? '';
        $question_type = $input['question_type'] ?? 'Multiple Choice';
        $points = $input['points'] ?? 1;
        $answers = $input['answers'] ?? [];
        $user_id = $_SESSION['user_id'];

        if (empty($subject_id) || empty($question_text)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Subject and question text are required']);
            exit;
        }

        if (empty($answers) || count($answers) < 5) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Exactly 5 answer options are required (A through E)']);
            exit;
        }

        if (count($answers) > 5) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'A maximum of 5 answer options is allowed']);
            exit;
        }

        // Check if at least one answer is marked as correct
        $hasCorrect = false;
        foreach ($answers as $answer) {
            if ($answer['is_correct']) {
                $hasCorrect = true;
                break;
            }
        }

        if (!$hasCorrect) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please select the correct answer']);
            exit;
        }

        $pdo->beginTransaction();

        // Insert question
        $stmt = $pdo->prepare('INSERT INTO tbl_question_bank (Subject_ID, User_ID, Question_Text, Question_Type, Added_Date) VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([$subject_id, $user_id, $question_text, $question_type]);
        $question_id = $pdo->lastInsertId();

        // Insert answers
        foreach ($answers as $answer) {
            $stmt = $pdo->prepare('INSERT INTO tbl_choice (Question_ID, Choice_Text, Is_Correct) VALUES (?, ?, ?)');
            $stmt->execute([$question_id, $answer['answer_text'], $answer['is_correct'] ? 1 : 0]);
        }

        $pdo->commit();

        auditFromSession($pdo, 'QUESTION', 'CREATE_QUESTION', "Question {$question_id} added to subject {$subject_id}", 'SUCCESS', 'question', (int)$question_id);

        http_response_code(201);
        echo json_encode(['success' => true, 'message' => 'Question added successfully', 'question_id' => $question_id]);
        exit;
    }

    // Update question
    if ($request_method === 'POST' && $action === 'update_question') {
        $question_id = $_GET['question_id'] ?? '';
        $subject_id = $input['subject_id'] ?? '';
        $question_text = $input['question_text'] ?? '';
        $points = $input['points'] ?? 1;
        $answers = $input['answers'] ?? [];

        if (empty($question_id) || empty($subject_id) || empty($question_text)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Question ID, subject and question text are required']);
            exit;
        }

        if (empty($answers) || count($answers) < 5) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Exactly 5 answer options are required (A through E)']);
            exit;
        }

        if (count($answers) > 5) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'A maximum of 5 answer options is allowed']);
            exit;
        }

        // Check if at least one answer is marked as correct
        $hasCorrect = false;
        foreach ($answers as $answer) {
            if ($answer['is_correct']) {
                $hasCorrect = true;
                break;
            }
        }

        if (!$hasCorrect) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please select the correct answer']);
            exit;
        }

        $pdo->beginTransaction();

        // Update question
        $stmt = $pdo->prepare('UPDATE tbl_question_bank SET Subject_ID = ?, Question_Text = ? WHERE Question_ID = ?');
        $stmt->execute([$subject_id, $question_text, $question_id]);

        // Delete old answers
        $stmt = $pdo->prepare('DELETE FROM tbl_choice WHERE Question_ID = ?');
        $stmt->execute([$question_id]);

        // Insert new answers
        foreach ($answers as $answer) {
            $stmt = $pdo->prepare('INSERT INTO tbl_choice (Question_ID, Choice_Text, Is_Correct) VALUES (?, ?, ?)');
            $stmt->execute([$question_id, $answer['answer_text'], $answer['is_correct'] ? 1 : 0]);
        }

        $pdo->commit();

        auditFromSession($pdo, 'QUESTION', 'UPDATE_QUESTION', "Question {$question_id} updated", 'SUCCESS', 'question', (int)$question_id);

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Question updated successfully']);
        exit;
    }

    // Delete question
    if (($request_method === 'DELETE' || $request_method === 'POST') && $action === 'delete_question') {
        $question_id = $_GET['question_id'] ?? ($input['question_id'] ?? '');

        if (empty($question_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Question ID is required']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            // Check if question is used in any exams
            $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM tbl_exam_question WHERE Question_ID = ?');
            $stmt->execute([$question_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] > 0) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Cannot delete question - it is assigned to one or more exams']);
                exit;
            }

            // Delete choices first
            $stmt = $pdo->prepare('DELETE FROM tbl_choice WHERE Question_ID = ?');
            $stmt->execute([$question_id]);

            // Delete question
            $stmt = $pdo->prepare('DELETE FROM tbl_question_bank WHERE Question_ID = ?');
            $stmt->execute([$question_id]);

            $pdo->commit();

            auditFromSession($pdo, 'QUESTION', 'DELETE_QUESTION', "Question {$question_id} deleted", 'SUCCESS', 'question', (int)$question_id);

            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Question deleted successfully']);
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error deleting question: ' . $e->getMessage()]);
            exit;
        }
    }

    // Import questions from Excel
    if ($request_method === 'POST' && $action === 'import_questions') {
        $questions = $input['questions'] ?? [];
        $user_id = $_SESSION['user_id'];

        if (empty($questions)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No questions provided']);
            exit;
        }

        $imported = 0;
        $failed = 0;
        $errors = [];

        $pdo->beginTransaction();

        try {
            foreach ($questions as $index => $q) {
                $rowNum = $index + 2; // Excel row number (accounting for header)
                
                // Validate required fields
                if (empty($q['Subject']) || empty($q['Question'])) {
                    $failed++;
                    $errors[] = "Row $rowNum: Missing subject or question";
                    continue;
                }

                // Find subject by name or code
                $stmt = $pdo->prepare('SELECT Subject_ID FROM tbl_subject WHERE Subject_Name = ? OR Subject_Code = ? LIMIT 1');
                $stmt->execute([$q['Subject'], $q['Subject']]);
                $subject = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$subject) {
                    $failed++;
                    $errors[] = "Row $rowNum: Subject '{$q['Subject']}' not found";
                    continue;
                }

                $subject_id = $subject['Subject_ID'];
                $question_text = $q['Question'];
                $question_type = $q['Question_Type'] ?? 'Multiple Choice';
                $points = $q['Points'] ?? 1;

                // Collect answer options
                $answers = [];
                $correct_answer = strtoupper(trim($q['Correct_Answer'] ?? 'A'));
                if (!in_array($correct_answer, ['A', 'B', 'C', 'D', 'E'], true)) {
                    $failed++;
                    $errors[] = "Row $rowNum: Correct_Answer must be A, B, C, D, or E";
                    continue;
                }

                foreach (['A', 'B', 'C', 'D', 'E'] as $option) {
                    $optionKey = 'Option_' . $option;
                    if (!empty($q[$optionKey])) {
                        $answers[] = [
                            'text' => $q[$optionKey],
                            'is_correct' => ($option === $correct_answer)
                        ];
                    }
                }

                if (count($answers) < 5) {
                    $failed++;
                    $errors[] = "Row $rowNum: All 5 answer options (Option_A through Option_E) are required";
                    continue;
                }

                // Insert question
                $stmt = $pdo->prepare('INSERT INTO tbl_question_bank (Subject_ID, User_ID, Question_Text, Question_Type, Added_Date) VALUES (?, ?, ?, ?, NOW())');
                $stmt->execute([$subject_id, $user_id, $question_text, $question_type]);
                $question_id = $pdo->lastInsertId();

                // Insert answers
                foreach ($answers as $answer) {
                    $stmt = $pdo->prepare('INSERT INTO tbl_choice (Question_ID, Choice_Text, Is_Correct) VALUES (?, ?, ?)');
                    $stmt->execute([$question_id, $answer['text'], $answer['is_correct'] ? 1 : 0]);
                }

                $imported++;
            }

            $pdo->commit();

            auditFromSession($pdo, 'QUESTION', 'IMPORT_QUESTIONS', "Imported {$imported} questions (failed: {$failed})", 'SUCCESS', 'question', null);

            $message = "Import completed. Imported: $imported";
            if ($failed > 0) {
                $message .= ", Failed: $failed";
            }

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => $message,
                'imported' => $imported,
                'failed' => $failed,
                'errors' => $errors
            ]);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // Get questions with filters
    if ($request_method === 'GET' && ($action === 'list' || $action === 'get_questions')) {
        $course_id = $_GET['course_id'] ?? '';
        $subject_id = $_GET['subject_id'] ?? '';
        $search = trim($_GET['search'] ?? '');

        $query = 'SELECT q.Question_ID, q.Subject_ID, q.Question_Text, q.Question_Type, q.Added_Date,
                         s.Subject_Name, s.Subject_Code, s.Course_ID,
                         c.Course_Name,
                         u.Fullname AS Created_By,
                         (SELECT COUNT(DISTINCT eq.Exam_ID)
                          FROM tbl_exam_question eq
                          WHERE eq.Question_ID = q.Question_ID) AS exam_usage_count
                  FROM tbl_question_bank q
                  INNER JOIN tbl_subject s ON q.Subject_ID = s.Subject_ID
                  INNER JOIN tbl_course c ON s.Course_ID = c.Course_ID
                  INNER JOIN tbl_user u ON q.User_ID = u.User_ID
                  WHERE 1=1';

        $params = [];

        if (!empty($course_id)) {
            $query .= ' AND s.Course_ID = ?';
            $params[] = $course_id;
        }

        if (!empty($subject_id)) {
            $query .= ' AND q.Subject_ID = ?';
            $params[] = $subject_id;
        }

        if ($search !== '') {
            $query .= ' AND (q.Question_Text LIKE ? OR s.Subject_Name LIKE ? OR c.Course_Name LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $query .= ' ORDER BY c.Course_Name ASC, s.Subject_Name ASC, q.Added_Date DESC';

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get choices for each question
        foreach ($questions as &$question) {
            $stmt = $pdo->prepare('SELECT Choice_ID, Choice_Text, Is_Correct FROM tbl_choice WHERE Question_ID = ? ORDER BY Choice_ID');
            $stmt->execute([$question['Question_ID']]);
            $question['answers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        http_response_code(200);
        echo json_encode(['success' => true, 'questions' => $questions]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
