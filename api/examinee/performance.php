<?php
/**
 * Examinee Performance API
 * Personal analytics: overview, subject breakdown, study recommendations.
 */

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../config/connection-pdo.php';
require_once __DIR__ . '/../includes/exam-code-utils.php';

function sendSuccess($data) {
    http_response_code(200);
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

function sendError($message, $statusCode = 400) {
    http_response_code($statusCode);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    sendError('Unauthorized. Please log in.', 401);
}

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Examinee') {
    sendError('Access denied. This resource is only available to examinees.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

$userId = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? 'overview';

function buildDateFilter(string $dateRange): array {
    $where = 'es.Time_Ended IS NOT NULL';
    $params = [];

    if ($dateRange !== 'all') {
        $days = (int)$dateRange;
        if ($days > 0) {
            $where .= ' AND r.Submission_Date >= DATE_SUB(NOW(), INTERVAL ? DAY)';
            $params[] = $days;
        }
    }

    return ['sql' => $where, 'params' => $params];
}

function overallStatus(int $examsTaken, int $passedCount, float $avgPercentage): string {
    if ($examsTaken === 0) {
        return 'No data';
    }
    $passRate = ($passedCount / $examsTaken) * 100;
    $failedCount = $examsTaken - $passedCount;
    if ($passRate < 60 || $failedCount >= 2 || $avgPercentage < 60) {
        return 'At risk';
    }
    if ($passRate < 80) {
        return 'Needs review';
    }
    return 'On track';
}

function subjectPerformanceStatus(array $subject): string {
    $exams = (int)($subject['exams_taken'] ?? 0);
    $avg = (float)($subject['avg_percentage'] ?? 0);
    $passRate = (float)($subject['pass_rate'] ?? 0);
    $failed = (int)($subject['failed_count'] ?? 0);

    if ($exams === 0) {
        return 'No data';
    }
    if ($exams === 1) {
        return $avg >= 75 && $passRate >= 100 ? 'Strong' : 'Needs focus';
    }
    if ($passRate < 60 || $failed >= 2 || $avg < 60) {
        return 'At risk';
    }
    if ($avg >= 80 && $passRate >= 80) {
        return 'Strong';
    }
    if (($subject['trend'] ?? '') === 'improving') {
        return 'Improving';
    }
    if (($subject['trend'] ?? '') === 'declining') {
        return 'Needs focus';
    }
    if ($passRate < 80 || $avg < 70) {
        return 'Needs focus';
    }
    return 'On track';
}

function computeSubjectTrend(PDO $pdo, int $userId, int $subjectId, array $dateFilter): string {
    $sql = "
        SELECT r.Percentage
        FROM tbl_result r
        INNER JOIN tbl_exam_session es ON r.Session_ID = es.Session_ID
        INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
        WHERE es.User_ID = ? AND e.Subject_ID = ? AND {$dateFilter['sql']}
        ORDER BY r.Submission_Date DESC
        LIMIT 2
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$userId, $subjectId], $dateFilter['params']));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) < 2) {
        return 'stable';
    }

    $latest = (float)$rows[0]['Percentage'];
    $previous = (float)$rows[1]['Percentage'];
    $diff = $latest - $previous;

    if ($diff >= 5) {
        return 'improving';
    }
    if ($diff <= -5) {
        return 'declining';
    }
    return 'stable';
}

function fetchSubjectRollup(PDO $pdo, int $userId, array $dateFilter): array {
    $stmt = $pdo->prepare("
        SELECT
            s.Subject_ID,
            s.Subject_Name,
            s.Subject_Code,
            c.Course_Name,
            COUNT(r.Result_ID) AS exams_taken,
            ROUND(AVG(r.Percentage), 1) AS avg_percentage,
            ROUND(SUM(CASE WHEN r.Percentage >= e.Passing_Score THEN 1 ELSE 0 END) / COUNT(*) * 100, 1) AS pass_rate,
            SUM(CASE WHEN r.Percentage >= e.Passing_Score THEN 1 ELSE 0 END) AS passed_count,
            SUM(CASE WHEN r.Percentage < e.Passing_Score THEN 1 ELSE 0 END) AS failed_count,
            ROUND(MAX(r.Percentage), 1) AS highest,
            ROUND(MIN(r.Percentage), 1) AS lowest,
            MAX(r.Submission_Date) AS last_exam_date
        FROM tbl_result r
        INNER JOIN tbl_exam_session es ON r.Session_ID = es.Session_ID
        INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
        INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
        INNER JOIN tbl_course c ON s.Course_ID = c.Course_ID
        WHERE es.User_ID = ? AND {$dateFilter['sql']}
        GROUP BY s.Subject_ID, s.Subject_Name, s.Subject_Code, c.Course_Name
        ORDER BY avg_percentage ASC, s.Subject_Name ASC
    ");
    $stmt->execute(array_merge([$userId], $dateFilter['params']));
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($subjects as &$subject) {
        $subject['exams_taken'] = (int)$subject['exams_taken'];
        $subject['passed_count'] = (int)$subject['passed_count'];
        $subject['failed_count'] = (int)$subject['failed_count'];
        $subject['avg_percentage'] = (float)$subject['avg_percentage'];
        $subject['pass_rate'] = (float)$subject['pass_rate'];
        $subject['trend'] = computeSubjectTrend($pdo, $userId, (int)$subject['Subject_ID'], $dateFilter);
        $subject['status'] = subjectPerformanceStatus(array_merge($subject, ['trend' => $subject['trend']]));
    }
    unset($subject);

    return $subjects;
}

function fetchPerformanceTrend(PDO $pdo, int $userId, array $dateFilter): array {
    $stmt = $pdo->prepare("
        SELECT
            r.Submission_Date AS date,
            ROUND(r.Percentage, 1) AS score,
            e.Title AS exam_title,
            s.Subject_Name AS subject_name
        FROM tbl_result r
        INNER JOIN tbl_exam_session es ON r.Session_ID = es.Session_ID
        INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
        INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
        WHERE es.User_ID = ? AND {$dateFilter['sql']}
        ORDER BY r.Submission_Date ASC
    ");
    $stmt->execute(array_merge([$userId], $dateFilter['params']));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        if (!empty($row['date'])) {
            $row['date'] = date('Y-m-d H:i:s', strtotime($row['date']));
        }
        $row['score'] = (float)($row['score'] ?? 0);
    }
    unset($row);

    return $rows;
}

function fetchOverallSummary(PDO $pdo, int $userId, array $dateFilter): array {
    $stmt = $pdo->prepare("
        SELECT
            COUNT(r.Result_ID) AS exams_taken,
            ROUND(AVG(r.Percentage), 1) AS avg_percentage,
            SUM(CASE WHEN r.Percentage >= e.Passing_Score THEN 1 ELSE 0 END) AS passed_count,
            SUM(CASE WHEN r.Percentage < e.Passing_Score THEN 1 ELSE 0 END) AS failed_count,
            ROUND(MAX(r.Percentage), 1) AS highest_score,
            ROUND(MIN(r.Percentage), 1) AS lowest_score
        FROM tbl_result r
        INNER JOIN tbl_exam_session es ON r.Session_ID = es.Session_ID
        INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
        WHERE es.User_ID = ? AND {$dateFilter['sql']}
    ");
    $stmt->execute(array_merge([$userId], $dateFilter['params']));
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $examsTaken = (int)($row['exams_taken'] ?? 0);
    $passedCount = (int)($row['passed_count'] ?? 0);
    $avg = (float)($row['avg_percentage'] ?? 0);

    return [
        'exams_taken' => $examsTaken,
        'avg_percentage' => $avg,
        'passed_count' => $passedCount,
        'failed_count' => (int)($row['failed_count'] ?? 0),
        'pass_rate' => $examsTaken > 0 ? round(($passedCount / $examsTaken) * 100, 1) : 0,
        'highest_score' => (float)($row['highest_score'] ?? 0),
        'lowest_score' => (float)($row['lowest_score'] ?? 0),
        'status' => overallStatus($examsTaken, $passedCount, $avg),
    ];
}

function fetchUntakenExamsBySubject(PDO $pdo, int $userId): array {
    $hasUserBatch = false;
    try {
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'tbl_user_batch'");
        $hasUserBatch = $tableCheck && $tableCheck->rowCount() > 0;
    } catch (Exception $e) {
        return [];
    }

    if (!$hasUserBatch) {
        return [];
    }

    $hasExamCode = false;
    try {
        $colCheck = $pdo->query("SHOW COLUMNS FROM tbl_exam LIKE 'Exam_Code'");
        $hasExamCode = $colCheck && $colCheck->rowCount() > 0;
    } catch (Exception $e) {
        $hasExamCode = false;
    }
    $archivedFilter = $hasExamCode ? 'AND (e.Is_Archived = 0 OR e.Is_Archived IS NULL)' : '';

    $stmt = $pdo->prepare("
        SELECT DISTINCT
            s.Subject_ID,
            s.Subject_Name,
            e.Exam_ID,
            e.Title AS exam_title,
            e.Deadline
        FROM tbl_exam e
        INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
        INNER JOIN tbl_exam_batch eb ON e.Exam_ID = eb.Exam_ID
        INNER JOIN tbl_batch b ON eb.Batch_ID = b.Batch_ID
        INNER JOIN tbl_user_batch ub ON b.Batch_ID = ub.Batch_ID
        LEFT JOIN tbl_exam_session es ON e.Exam_ID = es.Exam_ID AND es.User_ID = ? AND es.Time_Ended IS NOT NULL
        WHERE e.Status = 'Published'
        {$archivedFilter}
        AND ub.User_ID = ?
        AND ub.Status = 'Active'
        AND es.Session_ID IS NULL
        ORDER BY s.Subject_Name ASC, e.Deadline ASC
    ");
    $stmt->execute([$userId, $userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $bySubject = [];
    foreach ($rows as $row) {
        $sid = (int)$row['Subject_ID'];
        if (!isset($bySubject[$sid])) {
            $bySubject[$sid] = [
                'Subject_ID' => $sid,
                'Subject_Name' => $row['Subject_Name'],
                'exams' => [],
            ];
        }
        $bySubject[$sid]['exams'][] = [
            'Exam_ID' => (int)$row['Exam_ID'],
            'exam_title' => $row['exam_title'],
            'Deadline' => $row['Deadline'] ? date('Y-m-d H:i:s', strtotime($row['Deadline'])) : null,
        ];
    }

    return array_values($bySubject);
}

function buildRecommendations(array $subjects, array $untakenBySubject): array {
    $recommendations = [];

    foreach ($subjects as $subject) {
        $priority = 0;
        $reasons = [];
        $sid = (int)$subject['Subject_ID'];
        $name = $subject['Subject_Name'];
        $avg = (float)$subject['avg_percentage'];
        $failed = (int)$subject['failed_count'];
        $trend = $subject['trend'] ?? 'stable';
        $status = $subject['status'] ?? '';

        if ($status === 'At risk') {
            $priority += 100;
            $reasons[] = 'below passing standards or multiple failures';
        }
        if ($failed >= 1 && ($subject['exams_taken'] ?? 0) > 0) {
            $priority += 40 + ($failed * 10);
            if ($failed >= 2) {
                $reasons[] = "{$failed} failed attempts";
            } else {
                $reasons[] = 'recent failure';
            }
        }
        if ($avg < 70) {
            $priority += 30;
            $reasons[] = "average score is {$avg}%";
        }
        if ($trend === 'declining') {
            $priority += 25;
            $reasons[] = 'scores are declining';
        }
        if ($status === 'Needs focus') {
            $priority += 15;
        }

        if ($priority > 0) {
            $recommendations[] = [
                'priority' => $priority,
                'type' => 'study',
                'subject_id' => $sid,
                'subject_name' => $name,
                'message' => "Focus on {$name}: " . implode(', ', array_unique($reasons)) . '. Review previous results and study ahead for upcoming exams.',
                'action' => 'view_subject',
            ];
        } elseif ($status === 'Strong') {
            $recommendations[] = [
                'priority' => 5,
                'type' => 'maintain',
                'subject_id' => $sid,
                'subject_name' => $name,
                'message' => "Strong in {$name} (avg {$avg}%). Maintain with light review.",
                'action' => 'view_subject',
            ];
        }
    }

    foreach ($untakenBySubject as $group) {
        $count = count($group['exams'] ?? []);
        if ($count === 0) {
            continue;
        }
        $recommendations[] = [
            'priority' => 50,
            'type' => 'pending',
            'subject_id' => (int)$group['Subject_ID'],
            'subject_name' => $group['Subject_Name'],
            'message' => "{$count} exam" . ($count > 1 ? 's' : '') . " available in {$group['Subject_Name']} but not yet taken. Prepare before the deadline.",
            'action' => 'take_exam',
        ];
    }

    usort($recommendations, fn($a, $b) => $b['priority'] <=> $a['priority']);

    return array_slice($recommendations, 0, 8);
}

try {
    $dateRange = $_GET['date_range'] ?? 'all';
    if (!in_array($dateRange, ['30', '90', 'all'], true)) {
        $dateRange = 'all';
    }
    $dateFilter = buildDateFilter($dateRange);

    switch ($action) {
        case 'overview': {
            $summary = fetchOverallSummary($pdo, $userId, $dateFilter);
            $subjects = fetchSubjectRollup($pdo, $userId, $dateFilter);
            $trend = fetchPerformanceTrend($pdo, $userId, $dateFilter);
            $untaken = fetchUntakenExamsBySubject($pdo, $userId);

            $strongSubjects = array_values(array_filter($subjects, fn($s) => in_array($s['status'], ['Strong', 'On track', 'Improving'], true)));
            usort($strongSubjects, fn($a, $b) => (float)$b['avg_percentage'] <=> (float)$a['avg_percentage']);
            $strongSubjects = array_slice($strongSubjects, 0, 3);

            $weakSubjects = array_values(array_filter($subjects, fn($s) => in_array($s['status'], ['At risk', 'Needs focus'], true)));
            usort($weakSubjects, fn($a, $b) => (float)$a['avg_percentage'] <=> (float)$b['avg_percentage']);
            $weakSubjects = array_slice($weakSubjects, 0, 3);

            sendSuccess([
                'date_range' => $dateRange,
                'summary' => $summary,
                'strong_subjects' => $strongSubjects,
                'weak_subjects' => $weakSubjects,
                'trend' => $trend,
                'recommendations' => buildRecommendations($subjects, $untaken),
            ]);
            break;
        }

        case 'subjects': {
            sendSuccess([
                'date_range' => $dateRange,
                'subjects' => fetchSubjectRollup($pdo, $userId, $dateFilter),
            ]);
            break;
        }

        case 'subject_detail': {
            $subjectId = (int)($_GET['subject_id'] ?? 0);
            if (!$subjectId) {
                sendError('subject_id is required');
            }

            $stmt = $pdo->prepare('
                SELECT s.Subject_ID, s.Subject_Name, s.Subject_Code, c.Course_Name
                FROM tbl_subject s
                INNER JOIN tbl_course c ON s.Course_ID = c.Course_ID
                WHERE s.Subject_ID = ?
            ');
            $stmt->execute([$subjectId]);
            $subject = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$subject) {
                sendError('Subject not found', 404);
            }

            $subjects = fetchSubjectRollup($pdo, $userId, $dateFilter);
            $subjectSummary = null;
            foreach ($subjects as $s) {
                if ((int)$s['Subject_ID'] === $subjectId) {
                    $subjectSummary = $s;
                    break;
                }
            }

            $stmt = $pdo->prepare("
                SELECT
                    r.Result_ID,
                    r.Score,
                    r.Percentage,
                    r.Remarks,
                    r.Submission_Date,
                    e.Exam_ID,
                    e.Title AS exam_title,
                    e.Passing_Score,
                    e.Duration,
                    CASE WHEN r.Percentage >= e.Passing_Score THEN 'Passed' ELSE 'Failed' END AS status,
                    TIMESTAMPDIFF(MINUTE, es.Time_Started, es.Time_Ended) AS time_taken_minutes,
                    (SELECT COUNT(*) FROM tbl_exam_question eq WHERE eq.Exam_ID = e.Exam_ID) AS total_questions
                FROM tbl_result r
                INNER JOIN tbl_exam_session es ON r.Session_ID = es.Session_ID
                INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
                WHERE es.User_ID = ? AND e.Subject_ID = ? AND {$dateFilter['sql']}
                ORDER BY r.Submission_Date DESC
            ");
            $stmt->execute(array_merge([$userId, $subjectId], $dateFilter['params']));
            $attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($attempts as &$attempt) {
                if (!empty($attempt['Submission_Date'])) {
                    $attempt['Submission_Date'] = date('Y-m-d H:i:s', strtotime($attempt['Submission_Date']));
                }
                $mins = (int)($attempt['time_taken_minutes'] ?? 0);
                if ($mins > 0) {
                    $hours = floor($mins / 60);
                    $minutes = $mins % 60;
                    $attempt['time_taken'] = $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
                } else {
                    $attempt['time_taken'] = 'N/A';
                }
            }
            unset($attempt);

            $stmt = $pdo->prepare("
                SELECT
                    DATE(r.Submission_Date) AS date,
                    ROUND(r.Percentage, 1) AS score,
                    e.Title AS exam_title
                FROM tbl_result r
                INNER JOIN tbl_exam_session es ON r.Session_ID = es.Session_ID
                INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
                WHERE es.User_ID = ? AND e.Subject_ID = ? AND {$dateFilter['sql']}
                ORDER BY r.Submission_Date ASC
            ");
            $stmt->execute(array_merge([$userId, $subjectId], $dateFilter['params']));
            $subjectTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $suggestions = [];
            if ($subjectSummary) {
                $status = $subjectSummary['status'];
                $avg = (float)$subjectSummary['avg_percentage'];
                $trend = $subjectSummary['trend'];

                if ($status === 'At risk') {
                    $suggestions[] = 'This subject needs immediate attention. Review incorrect answers from your failed exams.';
                } elseif ($status === 'Needs focus') {
                    $suggestions[] = 'Your performance in this subject is below target. Dedicate extra study time here.';
                } elseif ($status === 'Strong') {
                    $suggestions[] = 'You are performing well in this subject. Maintain with periodic review.';
                }

                if ($trend === 'declining') {
                    $suggestions[] = 'Your recent scores are dropping. Revisit material from your last exam before the next one.';
                } elseif ($trend === 'improving') {
                    $suggestions[] = 'Your scores are improving. Keep up the momentum.';
                }

                if ((int)$subjectSummary['failed_count'] > 0 && !empty($attempts)) {
                    $latest = $attempts[0];
                    if ($latest['status'] === 'Failed') {
                        $suggestions[] = "Review your latest exam \"{$latest['exam_title']}\" and focus on questions you missed.";
                    }
                }
            } elseif (empty($attempts)) {
                $suggestions[] = 'No completed exams in this subject yet. Check available exams on your dashboard.';
            }

            sendSuccess([
                'date_range' => $dateRange,
                'subject' => $subject,
                'summary' => $subjectSummary,
                'attempts' => $attempts,
                'trend' => $subjectTrend,
                'suggestions' => array_values(array_unique($suggestions)),
            ]);
            break;
        }

        default:
            sendError('Invalid action parameter', 400);
    }
} catch (PDOException $e) {
    error_log('Database error in performance.php: ' . $e->getMessage());
    sendError('Database error: Failed to retrieve performance data', 500);
} catch (Exception $e) {
    error_log('Error in performance.php: ' . $e->getMessage());
    sendError('An error occurred while processing your request', 500);
}
