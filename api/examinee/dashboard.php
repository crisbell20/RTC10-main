<?php
/**
 * Examinee Dashboard API
 * Provides statistics, available exams, and recent results for authenticated examinees
 */

header('Content-Type: application/json');
session_start();

require_once '../config/connection-pdo.php';
require_once '../includes/exam-code-utils.php';
require_once '../includes/exam-participant-utils.php';
require_once '../includes/validation-utils.php';
require_once '../includes/exam-deadline-finalize-utils.php';

/**
 * Send success response
 */
function sendSuccess($data) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
    exit;
}

/**
 * Send error response
 */
function sendError($message, $statusCode = 400) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'message' => $message
    ]);
    exit;
}

/**
 * Validate session and examinee role
 */
function validateExamineeSession() {
    // Check if session exists
    if (!isset($_SESSION['user_id'])) {
        sendError('Unauthorized. Please log in.', 401);
    }
    
    // Check if user is an examinee
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Examinee') {
        sendError('Access denied. This resource is only available to examinees.', 403);
    }
    
    return $_SESSION['user_id'];
}

// Validate session
$userId = validateExamineeSession();

try {
    runOverdueExamFinalizationOnce($pdo);
} catch (Exception $e) {
    error_log('Auto-finalize exams error: ' . $e->getMessage());
}

// Get action parameter
$action = $_GET['action'] ?? 'statistics';

try {
    switch ($action) {
        case 'statistics':
            // Get dashboard statistics
            getDashboardStatistics($pdo, $userId);
            break;
            
        case 'available-exams':
            // Get available exams
            getAvailableExams($pdo, $userId);
            break;
            
        case 'recent-results':
            // Get recent results
            getRecentResults($pdo, $userId);
            break;
            
        case 'all-results':
            // Get all results
            getAllResults($pdo, $userId);
            break;
            
        default:
            sendError('Invalid action parameter', 400);
    }
} catch (PDOException $e) {
    error_log("Database error in dashboard.php: " . $e->getMessage());
    sendError('Database error: Failed to retrieve data', 500);
} catch (Exception $e) {
    error_log("Error in dashboard.php: " . $e->getMessage());
    sendError('An error occurred while processing your request', 500);
}

/**
 * Get dashboard statistics
 */
function getDashboardStatistics($pdo, $userId) {
    // Count available exams (Published, not completed by user)
    $availableQuery = "
        SELECT COUNT(DISTINCT e.Exam_ID) as count
        FROM tbl_exam e
        INNER JOIN tbl_exam_batch eb ON e.Exam_ID = eb.Exam_ID
        INNER JOIN tbl_batch b ON eb.Batch_ID = b.Batch_ID
        INNER JOIN tbl_user_batch ub ON b.Batch_ID = ub.Batch_ID
        WHERE e.Status = 'Published'
        AND ub.User_ID = ?
        AND ub.Status = 'Active'
        AND NOT EXISTS (
            SELECT 1
            FROM tbl_exam_session es_done
            INNER JOIN tbl_result r ON r.Session_ID = es_done.Session_ID
            WHERE es_done.Exam_ID = e.Exam_ID
              AND es_done.User_ID = ?
              AND es_done.Time_Ended IS NOT NULL
        )
    ";
    $stmt = $pdo->prepare($availableQuery);
    $stmt->execute([$userId, $userId]);
    $availableCount = $stmt->fetch()['count'] ?? 0;
    
    // Count completed exams
    $completedQuery = "
        SELECT COUNT(DISTINCT es.Session_ID) as count
        FROM tbl_exam_session es
        WHERE es.User_ID = ?
        AND es.Time_Ended IS NOT NULL
    ";
    $stmt = $pdo->prepare($completedQuery);
    $stmt->execute([$userId]);
    $completedCount = $stmt->fetch()['count'] ?? 0;
    
    // Calculate average score and pass/fail counts
    $scoresQuery = "
        SELECT 
            AVG(r.Percentage) as avg_score,
            SUM(CASE WHEN r.Percentage >= e.Passing_Score THEN 1 ELSE 0 END) AS passed_count,
            SUM(CASE WHEN r.Percentage < e.Passing_Score THEN 1 ELSE 0 END) AS failed_count
        FROM tbl_result r
        INNER JOIN tbl_exam_session es ON r.Session_ID = es.Session_ID
        INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
        WHERE es.User_ID = ?
        AND es.Time_Ended IS NOT NULL
        AND r.Percentage IS NOT NULL
    ";
    $stmt = $pdo->prepare($scoresQuery);
    $stmt->execute([$userId]);
    $scores = $stmt->fetch();
    
    $averageScore = $scores['avg_score'] ?? 0;
    $passedCount = (int)($scores['passed_count'] ?? 0);
    $failedCount = (int)($scores['failed_count'] ?? 0);
    
    // Format response
    $statistics = [
        'exams_available' => (int)$availableCount,
        'exams_completed' => (int)$completedCount,
        'passed_count' => $passedCount,
        'failed_count' => $failedCount,
        'average_score' => round((float)$averageScore, 1),
        'completion_rate' => $availableCount > 0 
            ? round(($completedCount / ($completedCount + $availableCount)) * 100, 1) 
            : 0
    ];
    
    sendSuccess($statistics);
}

/**
 * Get available exams
 */
function getAvailableExams($pdo, $userId) {
    $hasExamCode = false;
    try {
        $colCheck = $pdo->query("SHOW COLUMNS FROM tbl_exam LIKE 'Exam_Code'");
        $hasExamCode = $colCheck && $colCheck->rowCount() > 0;
    } catch (Exception $e) {
        $hasExamCode = false;
    }

    $codeSelect = $hasExamCode ? 'e.Exam_Code,' : '';
    $archivedFilter = $hasExamCode ? 'AND (e.Is_Archived = 0 OR e.Is_Archived IS NULL)' : '';
    $participantFilter = '';
    $queryParams = [$userId, $userId, $userId];

    if (examParticipantTableExists($pdo)) {
        $participantFilter = "
        AND (
            NOT EXISTS (SELECT 1 FROM tbl_exam_participant ep_all WHERE ep_all.Exam_ID = e.Exam_ID)
            OR EXISTS (
                SELECT 1 FROM tbl_exam_participant ep_user
                WHERE ep_user.Exam_ID = e.Exam_ID AND ep_user.User_ID = ?
            )
        )";
        $queryParams[] = $userId;
    }

    $query = "
        SELECT DISTINCT
            e.Exam_ID,
            e.Title,
            e.Description,
            e.Schedule_Date,
            e.Deadline,
            e.Duration,
            e.Passing_Score,
            {$codeSelect}
            s.Subject_Name,
            c.Course_Name,
            COUNT(DISTINCT eq.Question_ID) as Question_Count,
            es.Time_Started,
            es.Session_ID
        FROM tbl_exam e
        INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
        INNER JOIN tbl_course c ON s.Course_ID = c.Course_ID
        INNER JOIN tbl_exam_batch eb ON e.Exam_ID = eb.Exam_ID
        INNER JOIN tbl_batch b ON eb.Batch_ID = b.Batch_ID
        INNER JOIN tbl_user_batch ub ON b.Batch_ID = ub.Batch_ID
        LEFT JOIN tbl_exam_question eq ON e.Exam_ID = eq.Exam_ID
        LEFT JOIN tbl_exam_session es ON e.Exam_ID = es.Exam_ID AND es.User_ID = ? AND es.Time_Ended IS NULL
        WHERE e.Status = 'Published'
        {$archivedFilter}
        AND ub.User_ID = ?
        AND ub.Status = 'Active'
        {$participantFilter}
        AND NOT EXISTS (
            SELECT 1
            FROM tbl_exam_session es_done
            INNER JOIN tbl_result r ON r.Session_ID = es_done.Session_ID
            WHERE es_done.Exam_ID = e.Exam_ID
              AND es_done.User_ID = ?
              AND es_done.Time_Ended IS NOT NULL
        )
        GROUP BY e.Exam_ID
        ORDER BY e.Deadline ASC, e.Schedule_Date ASC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($queryParams);
    $exams = $stmt->fetchAll();
    
    // Format dates and calculate status
    foreach ($exams as &$exam) {
        if ($exam['Schedule_Date']) {
            $exam['Schedule_Date'] = date('Y-m-d H:i:s', strtotime($exam['Schedule_Date']));
        }
        
        if ($exam['Deadline']) {
            $exam['Deadline'] = date('Y-m-d H:i:s', strtotime($exam['Deadline']));
        }
        
        // If exam has been started, calculate time remaining for the exam itself
        if ($exam['Time_Started']) {
            $exam['Time_Started'] = date('Y-m-d H:i:s', strtotime($exam['Time_Started']));
            $exam['has_started'] = true;
            
            // Calculate deadline based on start time + duration
            if ($exam['Duration']) {
                $startTime = strtotime($exam['Time_Started']);
                $deadline = $startTime + ($exam['Duration'] * 60); // Duration in minutes
                $exam['exam_deadline'] = date('Y-m-d H:i:s', $deadline);
                $exam['time_remaining'] = max(0, $deadline - time());
            }
        } else {
            $exam['has_started'] = false;
            $exam['Time_Started'] = null;
        }

        $exam['requires_exam_code'] = $hasExamCode && !empty($exam['Exam_Code']);
        $exam['schedule_locked'] = validateExamScheduleStarted($exam) !== null;
        unset($exam['Exam_Code']);
    }
    
    sendSuccess($exams);
}

/**
 * Get recent results
 */
function getRecentResults($pdo, $userId) {
    $hasResponseReview = responseReviewColumnExists($pdo);
    $reviewSelect = $hasResponseReview ? 'e.Allow_Response_Review,' : '';

    $query = "
        SELECT 
            r.Result_ID,
            r.Score,
            r.Percentage,
            r.Remarks,
            r.Submission_Date,
            e.Title as exam_title,
            e.Passing_Score,
            {$reviewSelect}
            es.Session_ID
        FROM tbl_result r
        INNER JOIN tbl_exam_session es ON r.Session_ID = es.Session_ID
        INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
        WHERE es.User_ID = ?
        ORDER BY r.Submission_Date DESC
        LIMIT 10
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$userId]);
    $results = $stmt->fetchAll();
    
    // Add pass/fail status and format dates
    foreach ($results as &$result) {
        $result['status'] = $result['Percentage'] >= $result['Passing_Score'] ? 'Passed' : 'Failed';
        $result['can_view_details'] = examAllowsResponseReview($result, $hasResponseReview);
        if ($hasResponseReview) {
            unset($result['Allow_Response_Review']);
        }
        if ($result['Submission_Date']) {
            $result['Submission_Date'] = date('Y-m-d H:i:s', strtotime($result['Submission_Date']));
        }
    }
    
    sendSuccess($results);
}

/**
 * Get all results for the user
 */
function getAllResults($pdo, $userId) {
    $hasResponseReview = responseReviewColumnExists($pdo);
    $reviewSelect = $hasResponseReview ? 'e.Allow_Response_Review,' : '';

    $query = "
        SELECT 
            r.Result_ID,
            r.Score,
            r.Percentage,
            r.Remarks,
            r.Submission_Date,
            e.Title as exam_title,
            e.Passing_Score,
            e.Duration,
            {$reviewSelect}
            s.Subject_Name,
            es.Session_ID,
            es.Time_Started,
            es.Time_Ended,
            TIMESTAMPDIFF(MINUTE, es.Time_Started, es.Time_Ended) as time_taken_minutes,
            (SELECT COUNT(*) FROM tbl_exam_question eq WHERE eq.Exam_ID = e.Exam_ID) as total_questions
        FROM tbl_result r
        INNER JOIN tbl_exam_session es ON r.Session_ID = es.Session_ID
        INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
        LEFT JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
        WHERE es.User_ID = ?
        ORDER BY r.Submission_Date DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$userId]);
    $results = $stmt->fetchAll();
    
    // Format results
    foreach ($results as &$result) {
        $result['can_view_details'] = examAllowsResponseReview($result, $hasResponseReview);
        if ($hasResponseReview) {
            unset($result['Allow_Response_Review']);
        }

        // Calculate time taken
        if ($result['time_taken_minutes']) {
            $hours = floor($result['time_taken_minutes'] / 60);
            $minutes = $result['time_taken_minutes'] % 60;
            if ($hours > 0) {
                $result['time_taken'] = $hours . 'h ' . $minutes . 'm';
            } else {
                $result['time_taken'] = $minutes . 'm';
            }
        } else {
            $result['time_taken'] = 'N/A';
        }
        
        // Format submission date
        if ($result['Submission_Date']) {
            $result['Submission_Date'] = date('Y-m-d H:i:s', strtotime($result['Submission_Date']));
        }
    }
    
    sendSuccess($results);
}
