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
require_once __DIR__ . '/../includes/module-utils.php';
require_once __DIR__ . '/../includes/question-publish-utils.php';
require_once __DIR__ . '/../includes/archive-utils.php';

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
        $isPublished = array_key_exists('is_published', $input)
            ? ((int)$input['is_published'] === 1 ? 1 : 0)
            : 1;

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

        if (questionPublishedColumnExists($pdo)) {
            $stmt = $pdo->prepare('INSERT INTO tbl_question_bank (Subject_ID, User_ID, Question_Text, Question_Type, Is_Published, Added_Date) VALUES (?, ?, ?, ?, ?, NOW())');
            $stmt->execute([$subject_id, $user_id, $question_text, $question_type, $isPublished]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO tbl_question_bank (Subject_ID, User_ID, Question_Text, Question_Type, Added_Date) VALUES (?, ?, ?, ?, NOW())');
            $stmt->execute([$subject_id, $user_id, $question_text, $question_type]);
        }
        $question_id = $pdo->lastInsertId();

        // Insert answers
        foreach ($answers as $answer) {
            $stmt = $pdo->prepare('INSERT INTO tbl_choice (Question_ID, Choice_Text, Is_Correct) VALUES (?, ?, ?)');
            $stmt->execute([$question_id, $answer['answer_text'], $answer['is_correct'] ? 1 : 0]);
        }

        $pdo->commit();

        auditFromSession($pdo, 'QUESTION', 'CREATE_QUESTION', 'Question added to ' . auditSubjectLabel($pdo, (int)$subject_id) . ': ' . auditQuestionPreview($pdo, (int)$question_id), 'SUCCESS', 'question', (int)$question_id);

        $message = $isPublished === 0
            ? 'Exam draft question saved. It will be added to the question bank when the exam is published.'
            : 'Question added successfully';

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'question_id' => $question_id,
            'is_published' => $isPublished
        ]);
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

        auditFromSession($pdo, 'QUESTION', 'UPDATE_QUESTION', 'Question updated: ' . auditQuestionPreview($pdo, (int)$question_id), 'SUCCESS', 'question', (int)$question_id);

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Question updated successfully']);
        exit;
    }

    // Archive question
    if ($request_method === 'POST' && $action === 'archive_question') {
        $question_id = (int)($input['question_id'] ?? $_GET['question_id'] ?? 0);

        if ($question_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Question ID is required']);
            exit;
        }

        if (!archiveRecord($pdo, 'tbl_question_bank', 'Question_ID', $question_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unable to archive question. Run the archive migration first.']);
            exit;
        }

        $questionPreview = auditQuestionPreview($pdo, $question_id);
        auditFromSession($pdo, 'QUESTION', 'ARCHIVE_QUESTION', "Question archived: {$questionPreview}", 'SUCCESS', 'question', $question_id);

        echo json_encode(['success' => true, 'message' => 'Question archived successfully']);
        exit;
    }

    // Restore archived question
    if ($request_method === 'POST' && $action === 'restore_question') {
        $question_id = (int)($input['question_id'] ?? $_GET['question_id'] ?? 0);

        if ($question_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Question ID is required']);
            exit;
        }

        if (!restoreRecord($pdo, 'tbl_question_bank', 'Question_ID', $question_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => restoreFailureMessage($pdo, 'tbl_question_bank', 'Question_ID', $question_id, 'question')]);
            exit;
        }

        $questionPreview = auditQuestionPreview($pdo, $question_id);
        auditFromSession($pdo, 'QUESTION', 'RESTORE_QUESTION', "Question restored: {$questionPreview}", 'SUCCESS', 'question', $question_id);

        echo json_encode(['success' => true, 'message' => 'Question restored successfully']);
        exit;
    }

    // Delete question — disabled; use archive instead
    if (($request_method === 'DELETE' || $request_method === 'POST') && $action === 'delete_question') {
        archiveDeletionBlockedResponse();
    }

    // Import questions from Excel
    if ($request_method === 'POST' && $action === 'import_questions') {
        $questions = $input['questions'] ?? [];
        $default_subject_id = $input['subject_id'] ?? null;
        $user_id = $_SESSION['user_id'];

        if (empty($questions)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No questions provided']);
            exit;
        }

        $default_subject_id = $default_subject_id ? (int)$default_subject_id : null;
        if ($default_subject_id) {
            $subjectCheck = $pdo->prepare('SELECT Subject_ID FROM tbl_subject WHERE Subject_ID = ? LIMIT 1');
            $subjectCheck->execute([$default_subject_id]);
            if (!$subjectCheck->fetch(PDO::FETCH_ASSOC)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Selected subject not found']);
                exit;
            }
        }

        $imported = 0;
        $failed = 0;
        $errors = [];

        $pdo->beginTransaction();

        try {
            foreach ($questions as $index => $q) {
                $rowNum = $index + 2; // Excel row number (accounting for header)

                if (empty($q['Question']) || !trim((string)$q['Question'])) {
                    $failed++;
                    $errors[] = "Row $rowNum: Question text is required";
                    continue;
                }

                $subject_id = null;
                if ($default_subject_id) {
                    $subject_id = $default_subject_id;
                } elseif (!empty($q['Subject'])) {
                    $stmt = $pdo->prepare('SELECT Subject_ID FROM tbl_subject WHERE Subject_Name = ? OR Subject_Code = ? LIMIT 1');
                    $stmt->execute([$q['Subject'], $q['Subject']]);
                    $subject = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$subject) {
                        $failed++;
                        $errors[] = "Row $rowNum: Subject '{$q['Subject']}' not found";
                        continue;
                    }
                    $subject_id = (int)$subject['Subject_ID'];
                } else {
                    $failed++;
                    $errors[] = "Row $rowNum: No subject provided";
                    continue;
                }

                $question_text = trim((string)$q['Question']);
                $question_type = $q['Question_Type'] ?? 'Multiple Choice';

                $correct_answer = strtoupper(trim((string)($q['Correct_Answer'] ?? '')));
                if (!in_array($correct_answer, ['A', 'B', 'C', 'D', 'E'], true)) {
                    $failed++;
                    $errors[] = "Row $rowNum: Correct_Answer must be A, B, C, D, or E";
                    continue;
                }

                $answers = [];
                foreach (['A', 'B', 'C', 'D', 'E'] as $option) {
                    $optionKey = 'Option_' . $option;
                    $optionText = trim((string)($q[$optionKey] ?? ''));
                    if ($optionText !== '') {
                        $answers[] = [
                            'text' => $optionText,
                            'is_correct' => ($option === $correct_answer)
                        ];
                    }
                }

                foreach (['A', 'B', 'C', 'D'] as $requiredOption) {
                    $optionKey = 'Option_' . $requiredOption;
                    if (trim((string)($q[$optionKey] ?? '')) === '') {
                        $failed++;
                        $errors[] = "Row $rowNum: Option_{$requiredOption} is required";
                        continue 2;
                    }
                }

                if (count($answers) < 4) {
                    $failed++;
                    $errors[] = "Row $rowNum: At least options A through D are required";
                    continue;
                }

                $correctOptionKey = 'Option_' . $correct_answer;
                if (trim((string)($q[$correctOptionKey] ?? '')) === '') {
                    $failed++;
                    $errors[] = "Row $rowNum: Correct_Answer is {$correct_answer} but Option_{$correct_answer} is empty";
                    continue;
                }

                if (questionPublishedColumnExists($pdo)) {
                    $stmt = $pdo->prepare('INSERT INTO tbl_question_bank (Subject_ID, User_ID, Question_Text, Question_Type, Is_Published, Added_Date) VALUES (?, ?, ?, ?, 1, NOW())');
                } else {
                    $stmt = $pdo->prepare('INSERT INTO tbl_question_bank (Subject_ID, User_ID, Question_Text, Question_Type, Added_Date) VALUES (?, ?, ?, ?, NOW())');
                }
                $stmt->execute([$subject_id, $user_id, $question_text, $question_type]);
                $question_id = $pdo->lastInsertId();

                foreach ($answers as $answer) {
                    $stmt = $pdo->prepare('INSERT INTO tbl_choice (Question_ID, Choice_Text, Is_Correct) VALUES (?, ?, ?)');
                    $stmt->execute([$question_id, $answer['text'], $answer['is_correct'] ? 1 : 0]);
                }

                $imported++;
            }

            $pdo->commit();

            $subjectLabel = auditSubjectLabel($pdo, (int)$subject_id);
            auditFromSession($pdo, 'QUESTION', 'IMPORT_QUESTIONS', "Imported {$imported} questions to {$subjectLabel} (failed: {$failed})", 'SUCCESS', 'question', null);

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

    // Import into exam as draft questions (not visible in bank until exam publish)
    if ($request_method === 'POST' && $action === 'import_exam_questions') {
        $questions = $input['questions'] ?? [];
        $exam_id = (int)($input['exam_id'] ?? 0);
        $default_subject_id = (int)($input['subject_id'] ?? 0);
        $user_id = (int)$_SESSION['user_id'];

        if ($exam_id <= 0 || $default_subject_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Exam ID and subject ID are required']);
            exit;
        }

        if (empty($questions)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No questions provided']);
            exit;
        }

        $examStmt = $pdo->prepare('SELECT Exam_ID, Subject_ID, Title FROM tbl_exam WHERE Exam_ID = ? LIMIT 1');
        $examStmt->execute([$exam_id]);
        $examRow = $examStmt->fetch(PDO::FETCH_ASSOC);
        if (!$examRow) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Exam not found']);
            exit;
        }

        if ((int)$examRow['Subject_ID'] !== $default_subject_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Subject does not match this exam']);
            exit;
        }

        $imported = 0;
        $failed = 0;
        $errors = [];
        $createdQuestions = [];

        $pdo->beginTransaction();

        try {
            $orderStmt = $pdo->prepare('SELECT COALESCE(MAX(Question_Order), 0) FROM tbl_exam_question WHERE Exam_ID = ?');
            $orderStmt->execute([$exam_id]);
            $questionOrder = (int)$orderStmt->fetchColumn();

            foreach ($questions as $index => $q) {
                $rowNum = $index + 2;

                if (empty($q['Question']) || !trim((string)$q['Question'])) {
                    $failed++;
                    $errors[] = "Row $rowNum: Question text is required";
                    continue;
                }

                $subject_id = $default_subject_id;
                $question_text = trim((string)$q['Question']);
                $question_type = $q['Question_Type'] ?? 'Multiple Choice';

                $correct_answer = strtoupper(trim((string)($q['Correct_Answer'] ?? '')));
                if (!in_array($correct_answer, ['A', 'B', 'C', 'D', 'E'], true)) {
                    $failed++;
                    $errors[] = "Row $rowNum: Correct_Answer must be A, B, C, D, or E";
                    continue;
                }

                $answers = [];
                foreach (['A', 'B', 'C', 'D', 'E'] as $option) {
                    $optionKey = 'Option_' . $option;
                    $optionText = trim((string)($q[$optionKey] ?? ''));
                    if ($optionText !== '') {
                        $answers[] = [
                            'text' => $optionText,
                            'is_correct' => ($option === $correct_answer),
                        ];
                    }
                }

                foreach (['A', 'B', 'C', 'D'] as $requiredOption) {
                    $optionKey = 'Option_' . $requiredOption;
                    if (trim((string)($q[$optionKey] ?? '')) === '') {
                        $failed++;
                        $errors[] = "Row $rowNum: Option_{$requiredOption} is required";
                        continue 2;
                    }
                }

                if (count($answers) < 4) {
                    $failed++;
                    $errors[] = "Row $rowNum: At least options A through D are required";
                    continue;
                }

                $correctOptionKey = 'Option_' . $correct_answer;
                if (trim((string)($q[$correctOptionKey] ?? '')) === '') {
                    $failed++;
                    $errors[] = "Row $rowNum: Correct_Answer is {$correct_answer} but Option_{$correct_answer} is empty";
                    continue;
                }

                if (questionPublishedColumnExists($pdo)) {
                    $stmt = $pdo->prepare('INSERT INTO tbl_question_bank (Subject_ID, User_ID, Question_Text, Question_Type, Is_Published, Added_Date) VALUES (?, ?, ?, ?, 0, NOW())');
                    $stmt->execute([$subject_id, $user_id, $question_text, $question_type]);
                } else {
                    $stmt = $pdo->prepare('INSERT INTO tbl_question_bank (Subject_ID, User_ID, Question_Text, Question_Type, Added_Date) VALUES (?, ?, ?, ?, NOW())');
                    $stmt->execute([$subject_id, $user_id, $question_text, $question_type]);
                }
                $question_id = (int)$pdo->lastInsertId();

                foreach ($answers as $answer) {
                    $stmt = $pdo->prepare('INSERT INTO tbl_choice (Question_ID, Choice_Text, Is_Correct) VALUES (?, ?, ?)');
                    $stmt->execute([$question_id, $answer['text'], $answer['is_correct'] ? 1 : 0]);
                }

                $questionOrder++;
                $eqStmt = $pdo->prepare('INSERT INTO tbl_exam_question (Exam_ID, Question_ID, Question_Order, Date_Added) VALUES (?, ?, ?, NOW())');
                $eqStmt->execute([$exam_id, $question_id, $questionOrder]);

                $choiceStmt = $pdo->prepare('SELECT Choice_ID, Choice_Text, Is_Correct FROM tbl_choice WHERE Question_ID = ? ORDER BY Choice_ID');
                $choiceStmt->execute([$question_id]);
                $choices = $choiceStmt->fetchAll(PDO::FETCH_ASSOC);

                $createdQuestions[] = [
                    'Question_ID' => $question_id,
                    'Question_Text' => $question_text,
                    'Is_Published' => 0,
                    'choices' => $choices,
                ];

                $imported++;
            }

            $pdo->commit();

            $examLabel = $examRow['Title'] ?? ('Exam #' . $exam_id);
            auditFromSession(
                $pdo,
                'EXAM',
                'IMPORT_EXAM_QUESTIONS',
                "Imported {$imported} draft question(s) into exam {$examLabel} (failed: {$failed})",
                'SUCCESS',
                'exam',
                $exam_id
            );

            $message = "Import completed. Added {$imported} question(s) to this exam (exam drafts until published).";
            if ($failed > 0) {
                $message .= " Failed: {$failed}.";
            }

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => $message,
                'imported' => $imported,
                'failed' => $failed,
                'errors' => $errors,
                'questions' => $createdQuestions,
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
        $module_id = $_GET['module_id'] ?? '';
        $subject_id = $_GET['subject_id'] ?? '';
        $search = trim($_GET['search'] ?? '');
        $curriculum = subjectCurriculumJoin($pdo);

        $query = 'SELECT q.Question_ID, q.Subject_ID, q.Question_Text, q.Question_Type, q.Added_Date,
                         s.Subject_Name, s.Subject_Code,
                         ' . $curriculum['module_select'] . '
                         ' . $curriculum['course_id_select'] . ' AS Course_ID,
                         c.Course_Name,
                         u.Fullname AS Created_By,
                         (SELECT COUNT(DISTINCT eq.Exam_ID)
                          FROM tbl_exam_question eq
                          WHERE eq.Question_ID = q.Question_ID) AS exam_usage_count
                  FROM tbl_question_bank q
                  INNER JOIN tbl_subject s ON q.Subject_ID = s.Subject_ID
                  ' . $curriculum['join'] . '
                  INNER JOIN tbl_user u ON q.User_ID = u.User_ID
                  WHERE 1=1';

        $params = [];

        if (!empty($course_id)) {
            $query .= ' AND ' . $curriculum['course_filter'] . ' = ?';
            $params[] = $course_id;
        }

        if (!empty($module_id) && $curriculum['module_filter']) {
            $query .= ' AND ' . $curriculum['module_filter'] . ' = ?';
            $params[] = $module_id;
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

        $query .= publishedQuestionsWhereClause($pdo, 'q');
        $query .= ' AND ' . activeArchiveCondition($pdo, 'tbl_question_bank', 'q');
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
