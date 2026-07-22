<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'CCMD') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/connection-pdo.php';

$action = $_GET['action'] ?? '';

try {
    // Get dashboard statistics
    if ($action === 'stats') {
        // Total active exams (Published status)
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM tbl_exam WHERE Status = 'Published'");
        $activeExams = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Total participants - users who have started exams
        $activeParticipants = 0;
        try {
            $stmt = $pdo->query("SELECT COUNT(DISTINCT User_ID) as count FROM tbl_exam_session WHERE Time_Started IS NOT NULL AND Time_Ended IS NULL");
            $activeParticipants = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        } catch (Exception $e) {
            error_log("Error getting active participants: " . $e->getMessage());
        }

        // Completed exams today
        $completedToday = 0;
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM tbl_exam_session WHERE Time_Ended IS NOT NULL AND DATE(Time_Ended) = CURDATE()");
            $completedToday = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        } catch (Exception $e) {
            error_log("Error getting completed exams: " . $e->getMessage());
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => [
                'active_exams' => $activeExams,
                'active_participants' => $activeParticipants,
                'completed_today' => $completedToday
            ]
        ]);
        exit;
    }

    // Get live exam monitoring data
    if ($action === 'live_exams') {
        try {
            $stmt = $pdo->query("
                SELECT 
                    e.Exam_ID,
                    e.Title as Exam_Name,
                    s.Subject_Name,
                    c.Course_Name,
                    e.Status,
                    COALESCE(COUNT(DISTINCT es.Session_ID), 0) as Total_Participants,
                    COALESCE(SUM(CASE WHEN es.Time_Started IS NOT NULL AND es.Time_Ended IS NULL THEN 1 ELSE 0 END), 0) as Active_Participants
                FROM tbl_exam e
                INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
                INNER JOIN tbl_course c ON s.Course_ID = c.Course_ID
                LEFT JOIN tbl_exam_session es ON e.Exam_ID = es.Exam_ID
                WHERE e.Status = 'Published'
                GROUP BY e.Exam_ID
                ORDER BY e.Schedule_Date DESC
            ");
            
            $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $exams]);
            exit;
        } catch (Exception $e) {
            error_log("Error in live_exams: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error loading exams: ' . $e->getMessage()]);
            exit;
        }
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);

} catch (Exception $e) {
    error_log("CCMD Dashboard Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
