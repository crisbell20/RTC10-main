<?php
/**
 * CCMD Live Exam Monitoring API
 */

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'CCMD') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/connection-pdo.php';
require_once __DIR__ . '/../includes/exam-participant-utils.php';

$action = $_GET['action'] ?? '';

function sendSuccess($data) {
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

function sendError($message, $statusCode = 400) {
    http_response_code($statusCode);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function countAssignedExaminees(PDO $pdo, int $examId): int {
    return count(listExamMonitorExaminees($pdo, $examId));
}

function monitorParticipantUserIds(PDO $pdo, int $examId): array {
    return array_values(array_unique(array_map(
        fn(array $row) => (int)$row['User_ID'],
        listExamMonitorExaminees($pdo, $examId)
    )));
}

function sessionProgressCounts(PDO $pdo, array $sessionIds, int $examId): array
{
    $sessionIds = array_values(array_filter(array_map('intval', $sessionIds)));
    if (empty($sessionIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($sessionIds), '?'));
    $stmt = $pdo->prepare("
        SELECT
            es.Session_ID,
            (
                SELECT COUNT(*)
                FROM tbl_answer a
                INNER JOIN tbl_exam_question eq ON eq.Question_ID = a.Question_ID AND eq.Exam_ID = ?
                WHERE a.Session_ID = es.Session_ID
                  AND (a.Choice_ID IS NOT NULL OR (a.Answer_Correct IS NOT NULL AND TRIM(a.Answer_Correct) != ''))
            ) AS answered_count,
            (
                SELECT COUNT(*)
                FROM tbl_answer a
                INNER JOIN tbl_exam_question eq ON eq.Question_ID = a.Question_ID AND eq.Exam_ID = ?
                WHERE a.Session_ID = es.Session_ID
                  AND a.Is_Correct = 1
                  AND (a.Choice_ID IS NOT NULL OR (a.Answer_Correct IS NOT NULL AND TRIM(a.Answer_Correct) != ''))
            ) AS correct_count
        FROM tbl_exam_session es
        WHERE es.Session_ID IN ({$placeholders})
    ");
    $stmt->execute(array_merge([$examId, $examId], $sessionIds));

    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[(int)$row['Session_ID']] = [
            'answered_count' => (int)($row['answered_count'] ?? 0),
            'correct_count' => (int)($row['correct_count'] ?? 0),
        ];
    }

    return $map;
}

function formatTimeRemaining(?string $timeStarted, ?int $durationMinutes): ?array {
    if (!$timeStarted || !$durationMinutes) {
        return null;
    }

    $deadline = strtotime($timeStarted) + ($durationMinutes * 60);
    $remaining = $deadline - time();

    if ($remaining <= 0) {
        return ['seconds' => 0, 'display' => '00:00:00', 'expired' => true];
    }

    $hours = floor($remaining / 3600);
    $minutes = floor(($remaining % 3600) / 60);
    $seconds = $remaining % 60;

    return [
        'seconds' => $remaining,
        'display' => sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds),
        'expired' => false,
    ];
}

try {
    if ($action === 'live_exams') {
        $stmt = $pdo->query("
            SELECT
                e.Exam_ID,
                e.Title AS Exam_Name,
                s.Subject_Name,
                c.Course_Name,
                e.Status,
                e.Duration,
                e.Deadline,
                COALESCE(SUM(CASE WHEN es.Time_Ended IS NULL AND es.Time_Started IS NOT NULL THEN 1 ELSE 0 END), 0) AS Active_Participants,
                COALESCE(SUM(CASE WHEN es.Time_Ended IS NOT NULL THEN 1 ELSE 0 END), 0) AS Completed_Participants,
                COUNT(DISTINCT es.Session_ID) AS Total_Sessions
            FROM tbl_exam e
            INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
            INNER JOIN tbl_course c ON s.Course_ID = c.Course_ID
            LEFT JOIN tbl_exam_session es ON e.Exam_ID = es.Exam_ID
            WHERE e.Status = 'Published'
            GROUP BY e.Exam_ID, e.Title, s.Subject_Name, c.Course_Name, e.Status, e.Duration, e.Deadline
            ORDER BY Active_Participants DESC, e.Deadline ASC
        ");
        sendSuccess($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($action === 'exam_summary') {
        $examId = (int)($_GET['exam_id'] ?? 0);
        if (!$examId) {
            sendError('exam_id is required');
        }

        $stmt = $pdo->prepare('
            SELECT e.Exam_ID, e.Title, e.Duration, e.Deadline, e.Status,
                   s.Subject_Name, c.Course_Name,
                   (SELECT COUNT(*) FROM tbl_exam_question eq WHERE eq.Exam_ID = e.Exam_ID) AS question_count
            FROM tbl_exam e
            INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
            INNER JOIN tbl_course c ON s.Course_ID = c.Course_ID
            WHERE e.Exam_ID = ?
        ');
        $stmt->execute([$examId]);
        $exam = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$exam) {
            sendError('Exam not found', 404);
        }

        $assigned = countAssignedExaminees($pdo, $examId);
        $participantIds = monitorParticipantUserIds($pdo, $examId);

        $inProgress = 0;
        $completed = 0;
        if (!empty($participantIds)) {
            $idPlaceholders = implode(',', array_fill(0, count($participantIds), '?'));
            $stmt = $pdo->prepare("
                SELECT
                    SUM(CASE WHEN es.Time_Ended IS NULL AND es.Time_Started IS NOT NULL THEN 1 ELSE 0 END) AS in_progress,
                    SUM(CASE WHEN es.Time_Ended IS NOT NULL THEN 1 ELSE 0 END) AS completed
                FROM tbl_exam_session es
                WHERE es.Exam_ID = ?
                  AND es.User_ID IN ({$idPlaceholders})
            ");
            $stmt->execute(array_merge([$examId], $participantIds));
            $counts = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $inProgress = (int)($counts['in_progress'] ?? 0);
            $completed = (int)($counts['completed'] ?? 0);
        }
        $notStarted = max(0, $assigned - $completed - $inProgress);

        sendSuccess([
            'exam' => $exam,
            'assigned' => $assigned,
            'in_progress' => $inProgress,
            'completed' => $completed,
            'not_started' => $notStarted,
        ]);
    }

    if ($action === 'live_sessions') {
        $examId = (int)($_GET['exam_id'] ?? 0);
        if (!$examId) {
            sendError('exam_id is required');
        }

        $search = trim($_GET['search'] ?? '');
        $statusFilter = $_GET['status'] ?? 'all';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(10, (int)($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $stmt = $pdo->prepare('
            SELECT e.Duration
            FROM tbl_exam e
            WHERE e.Exam_ID = ?
        ');
        $stmt->execute([$examId]);
        $examRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$examRow) {
            sendError('Exam not found', 404);
        }
        $duration = (int)($examRow['Duration'] ?? 0);
        $qStmt = $pdo->prepare('SELECT COUNT(*) AS c FROM tbl_exam_question WHERE Exam_ID = ?');
        $qStmt->execute([$examId]);
        $questionCount = (int)($qStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

        $participants = listExamMonitorExaminees($pdo, $examId);
        $participantIds = array_map(fn($p) => (int)$p['User_ID'], $participants);

        $sessionsByUser = [];
        if (!empty($participantIds)) {
            $idPlaceholders = implode(',', array_fill(0, count($participantIds), '?'));
            $sessionStmt = $pdo->prepare("
                SELECT Session_ID, User_ID, Time_Started, Time_Ended
                FROM tbl_exam_session
                WHERE Exam_ID = ?
                  AND User_ID IN ({$idPlaceholders})
                ORDER BY Session_ID DESC
            ");
            $sessionStmt->execute(array_merge([$examId], $participantIds));
            foreach ($sessionStmt->fetchAll(PDO::FETCH_ASSOC) as $sessionRow) {
                $uid = (int)$sessionRow['User_ID'];
                if (!isset($sessionsByUser[$uid])) {
                    $sessionsByUser[$uid] = $sessionRow;
                }
            }
        }

        $sessionIds = array_map(fn($row) => (int)$row['Session_ID'], $sessionsByUser);
        $progressBySession = sessionProgressCounts($pdo, $sessionIds, $examId);

        $searchLower = $search !== '' ? strtolower($search) : '';

        $processed = [];
        foreach ($participants as $participant) {
            $userId = (int)$participant['User_ID'];
            $fullname = (string)($participant['Fullname'] ?? '');
            $academic = (string)($participant['Academic_Number'] ?? '');

            if ($searchLower !== '') {
                $haystack = strtolower($fullname . ' ' . $academic);
                if (strpos($haystack, $searchLower) === false) {
                    continue;
                }
            }

            $session = $sessionsByUser[$userId] ?? null;
            $timeStarted = $session['Time_Started'] ?? null;
            $timeEnded = $session['Time_Ended'] ?? null;
            $sessionId = isset($session['Session_ID']) ? (int)$session['Session_ID'] : null;

            if ($timeStarted && !$timeEnded) {
                $status = 'in_progress';
            } elseif ($timeEnded) {
                $status = 'completed';
            } else {
                $status = 'not_started';
            }

            if ($statusFilter !== 'all' && $status !== $statusFilter) {
                continue;
            }

            $progress = $sessionId && isset($progressBySession[$sessionId])
                ? $progressBySession[$sessionId]
                : ['answered_count' => 0, 'correct_count' => 0];

            $answered = (int)$progress['answered_count'];
            $answeredProgress = $questionCount > 0 ? round(($answered / $questionCount) * 100, 1) : 0;
            $correctCount = (int)$progress['correct_count'];
            $examScore = $status === 'completed' ? $correctCount : null;
            $scoreDisplay = null;
            $scoreProgress = null;

            if ($status === 'completed') {
                $scoreDisplay = $questionCount > 0
                    ? ($correctCount . '/' . $questionCount)
                    : (string)$correctCount;
                if ($questionCount > 0) {
                    $scoreProgress = round(($correctCount / $questionCount) * 100, 1);
                }
            }

            $timeRemaining = ($status === 'in_progress')
                ? formatTimeRemaining($timeStarted, $duration)
                : null;

            $processed[] = [
                'User_ID' => $userId,
                'Fullname' => $fullname,
                'Academic_Number' => $academic,
                'Session_ID' => $sessionId,
                'batch_names' => (string)($participant['Batch_Name'] ?? ''),
                'status' => $status,
                'answered_count' => $answered,
                'question_count' => $questionCount,
                'progress' => $answeredProgress,
                'answered_progress' => $answeredProgress,
                'score' => $examScore,
                'score_display' => $scoreDisplay,
                'score_progress' => $scoreProgress,
                'time_started' => $timeStarted,
                'time_ended' => $timeEnded,
                'time_remaining' => $timeRemaining,
            ];
        }

        usort($processed, function ($a, $b) {
            $order = ['in_progress' => 0, 'not_started' => 1, 'completed' => 2];
            $ao = $order[$a['status']] ?? 3;
            $bo = $order[$b['status']] ?? 3;
            if ($ao !== $bo) {
                return $ao <=> $bo;
            }
            return strcasecmp((string)$a['Fullname'], (string)$b['Fullname']);
        });

        $total = count($processed);
        $paged = array_slice($processed, $offset, $limit);

        sendSuccess([
            'rows' => $paged,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => max(1, (int)ceil($total / $limit)),
            ],
            'question_count' => $questionCount,
        ]);
    }

    sendError('Invalid action');
} catch (PDOException $e) {
    error_log('CCMD monitor.php DB error: ' . $e->getMessage());
    sendError('Database error', 500);
} catch (Exception $e) {
    error_log('CCMD monitor.php error: ' . $e->getMessage());
    sendError('Server error', 500);
}
