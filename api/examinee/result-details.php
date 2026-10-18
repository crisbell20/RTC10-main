<?php

// Examinee Result Details API

header('Content-Type: application/json');
session_start();

require_once '../config/connection-pdo.php';
/** @var PDO $pdo */
require_once '../includes/exam-code-utils.php';
require_once '../includes/exam-session-utils.php';
require_once '../includes/archive-utils.php';

function sendSuccess($data = []) {
    http_response_code(200);
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

function sendError($message, $statusCode = 400) {
    http_response_code($statusCode);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// Validate session
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Examinee') {
    sendError('Unauthorized', 401);
}

$userId = $_SESSION['user_id'];
$resultId = $_GET['result_id'] ?? null;

if (!$resultId) {
    sendError('Missing result_id');
}

try {
    // Set PDO error mode
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $hasResponseReview = responseReviewColumnExists($pdo);
    $reviewSelect = $hasResponseReview ? 'e.Allow_Response_Review,' : '';
    
    // Get result summary — hide when exam/subject/course is archived
    $activeCurriculum = examineeActiveResultCurriculumCondition($pdo, 'e', 's', 'c');
    $stmt = $pdo->prepare("
        SELECT 
            r.Result_ID,
            r.Score,
            r.Percentage,
            r.Remarks,
            r.Submission_Date,
            e.Exam_ID,
            e.Title as exam_title,
            e.Passing_Score,
            {$reviewSelect}
            es.Session_ID,
            es.Time_Started,
            es.Time_Ended
        FROM tbl_result r
        INNER JOIN tbl_exam_session es ON r.Session_ID = es.Session_ID
        INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
        INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
        INNER JOIN tbl_course c ON s.Course_ID = c.Course_ID
        WHERE r.Result_ID = ? AND es.User_ID = ?
          AND {$activeCurriculum}
    ");
    
    if (!$stmt->execute([$resultId, $userId])) {
        error_log("SQL Error: " . print_r($stmt->errorInfo(), true));
        sendError('Database query failed', 500);
    }
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        sendError('Result not found or access denied', 404);
    }
    
    // Get additional exam details
    $stmt = $pdo->prepare('
        SELECT 
            e.Description as exam_description,
            e.Duration,
            e.Subject_ID,
            s.Subject_Name,
            s.Course_ID,
            c.Course_Name
        FROM tbl_exam e
        LEFT JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
        LEFT JOIN tbl_course c ON s.Course_ID = c.Course_ID
        WHERE e.Exam_ID = ?
    ');
    $stmt->execute([$result['Exam_ID']]);
    $examDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Merge exam details into result
    if ($examDetails) {
        $result['exam_description'] = $examDetails['exam_description'] ?? '';
        $result['Duration_Minutes'] = $examDetails['Duration'] ?? 0;
        $result['Subject_Name'] = $examDetails['Subject_Name'] ?? '';
        $result['Course_Name'] = $examDetails['Course_Name'] ?? '';
    }
    
    $result['time_taken'] = formatExamTimeTaken(
        $result['Time_Started'] ?? null,
        $result['Time_Ended'] ?? null,
        countExamSessionAnswers(
            $pdo,
            (int)($result['Session_ID'] ?? 0),
            (int)($result['Exam_ID'] ?? 0)
        )
    );
    
    // Set defaults for optional fields
    $result['exam_description'] = $result['exam_description'] ?? '';
    $result['Duration_Minutes'] = $result['Duration_Minutes'] ?? 0;
    $result['Subject_Name'] = $result['Subject_Name'] ?? 'N/A';
    $result['Course_Name'] = $result['Course_Name'] ?? 'N/A';

    $reviewAllowed = examAllowsResponseReview($result, $hasResponseReview);
    $result['review_allowed'] = $reviewAllowed;

    $stmt = $pdo->prepare('
        SELECT COUNT(*) as total
        FROM tbl_exam_question
        WHERE Exam_ID = ?
    ');
    $stmt->execute([$result['Exam_ID']]);
    $totalData = $stmt->fetch(PDO::FETCH_ASSOC);
    $result['total_questions'] = (int)($totalData['total'] ?? 0);
    if (empty($result['Time_Ended'])) {
        $result['is_in_progress'] = true;
        $result['is_latest'] = false;
        $result['attempt_number'] = null;
        $result['Remarks'] = 'In progress';
        $result['status'] = 'In progress';
    } else {
        $result['is_in_progress'] = false;
        applyExamineeGradeToResult($result);
    }

    if (!$reviewAllowed) {
        $result['review_message'] = 'Response viewing for this exam has been disabled. You can see your score summary only.';
        sendSuccess($result);
    }
    
    // Get questions with answers
    $stmt = $pdo->prepare('
        SELECT 
            eq.Question_ID,
            qb.Question_Text,
            qb.Question_Type,
            a.Answer_ID,
            a.Choice_ID as user_answer_id,
            a.Is_Correct as user_is_correct,
            c_user.Choice_Text as user_answer_text
        FROM tbl_exam_question eq
        INNER JOIN tbl_question_bank qb ON eq.Question_ID = qb.Question_ID
        LEFT JOIN tbl_answer a ON eq.Question_ID = a.Question_ID AND a.Result_ID = ?
        LEFT JOIN tbl_choice c_user ON a.Choice_ID = c_user.Choice_ID
        WHERE eq.Exam_ID = ?
        ORDER BY eq.Question_ID
    ');
    $stmt->execute([$result['Result_ID'], $result['Exam_ID']]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all choices for each question
    foreach ($questions as &$question) {
        $stmt = $pdo->prepare('
            SELECT 
                Choice_ID,
                Choice_Text,
                Is_Correct
            FROM tbl_choice
            WHERE Question_ID = ?
            ORDER BY Choice_ID
        ');
        $stmt->execute([$question['Question_ID']]);
        $question['choices'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Find correct answer
        foreach ($question['choices'] as $choice) {
            if ($choice['Is_Correct']) {
                $question['correct_answer_id'] = $choice['Choice_ID'];
                $question['correct_answer_text'] = $choice['Choice_Text'];
                break;
            }
        }
    }
    
    $result['questions'] = $questions;

    $historyFinished = finishedSittingPredicateSql('es_hist');
    $historyStmt = $pdo->prepare("
        SELECT r_hist.Result_ID, r_hist.Submission_Date, es_hist.Exam_ID, es_hist.Time_Started, es_hist.Time_Ended
        FROM tbl_result r_hist
        INNER JOIN tbl_exam_session es_hist ON es_hist.Session_ID = r_hist.Session_ID
        WHERE es_hist.Exam_ID = ? AND es_hist.User_ID = ? AND {$historyFinished}
    ");
    $historyStmt->execute([(int)$result['Exam_ID'], (int)$userId]);
    foreach (annotateAttemptHistory($historyStmt->fetchAll(PDO::FETCH_ASSOC)) as $historyRow) {
        if ((int)$historyRow['Result_ID'] === (int)$result['Result_ID']) {
            $result['attempt_number'] = (int)$historyRow['attempt_number'];
            $result['is_latest'] = !empty($historyRow['is_latest']);
            break;
        }
    }

    sendSuccess($result);
    
} catch (PDOException $e) {
    error_log("PDO Error in result-details.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendError('An unexpected error occurred. Please try again later.', 500);
} catch (Exception $e) {
    error_log("Error in result-details.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendError('An unexpected error occurred. Please try again later.', 500);
}
?>
