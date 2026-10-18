<?php

// CCMD - Dashboard API

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'CCMD') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/connection-pdo.php';
require_once __DIR__ . '/../includes/archive-utils.php';
require_once __DIR__ . '/../includes/exam-participant-utils.php';

function ccmdOpenSittingCondition(): string
{
    return "es.Time_Started IS NOT NULL
        AND es.Time_Ended IS NULL
        AND (es.Cheating_Locked = 0 OR es.Cheating_Locked IS NULL)
        AND (e.Duration IS NULL OR e.Duration <= 0 OR DATE_ADD(es.Time_Started, INTERVAL e.Duration MINUTE) >= NOW())
        AND (e.Deadline IS NULL OR e.Deadline >= NOW())";
}

$action = $_GET['action'] ?? '';

try {
    // Get dashboard statistics
    if ($action === 'stats') {
        // Total active exams (Published, not archived)
        $examActive = activeArchiveCondition($pdo, 'tbl_exam', 'e');
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM tbl_exam e WHERE e.Status = 'Published' AND {$examActive}");
        $activeExams = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Trainees inside a published exam right now. Closed, archived, expired, and locked sittings do not count.
        $activeParticipants = 0;
        try {
            $subjectActive = activeArchiveCondition($pdo, 'tbl_subject', 's');
            $courseActive = activeArchiveCondition($pdo, 'tbl_course', 'c');
            $openSitting = ccmdOpenSittingCondition();
            $stmt = $pdo->query("
                SELECT COUNT(DISTINCT es.User_ID) as count
                FROM tbl_exam_session es
                INNER JOIN tbl_exam e ON e.Exam_ID = es.Exam_ID
                INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
                INNER JOIN tbl_course c ON s.Course_ID = c.Course_ID
                WHERE e.Status = 'Published'
                  AND {$examActive}
                  AND {$subjectActive}
                  AND {$courseActive}
                  AND {$openSitting}
            ");
            $activeParticipants = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        } catch (Exception $e) {
            error_log("Error getting active participants: " . $e->getMessage());
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => [
                'active_exams' => $activeExams,
                'active_participants' => $activeParticipants,
            ]
        ]);
        exit;
    }

    // Get live exam monitoring data
    if ($action === 'live_exams') {
        try {
            $examActive = activeArchiveCondition($pdo, 'tbl_exam', 'e');
            $subjectActive = activeArchiveCondition($pdo, 'tbl_subject', 's');
            $courseActive = activeArchiveCondition($pdo, 'tbl_course', 'c');
            $openSitting = ccmdOpenSittingCondition();
            $stmt = $pdo->query("
                SELECT 
                    e.Exam_ID,
                    e.Title as Exam_Name,
                    s.Subject_Name,
                    c.Course_Name,
                    e.Status,
                    COALESCE(COUNT(DISTINCT CASE WHEN {$openSitting} THEN es.User_ID END), 0) as Active_Participants
                FROM tbl_exam e
                INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
                INNER JOIN tbl_course c ON s.Course_ID = c.Course_ID
                LEFT JOIN tbl_exam_session es ON e.Exam_ID = es.Exam_ID
                WHERE e.Status = 'Published'
                  AND {$examActive}
                  AND {$subjectActive}
                  AND {$courseActive}
                GROUP BY e.Exam_ID
                ORDER BY e.Schedule_Date DESC
            ");
            
            $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($exams as &$exam) {
                $exam['Total_Participants'] = count(listExamMonitorExaminees($pdo, (int)$exam['Exam_ID']));
            }
            unset($exam);

            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $exams]);
            exit;
        } catch (Exception $e) {
            error_log("Error in live_exams: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'An unexpected error occurred. Please try again later.']);
            exit;
        }
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);

} catch (Exception $e) {
    error_log("CCMD Dashboard Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred. Please try again later.']);
}
?>
