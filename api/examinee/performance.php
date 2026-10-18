<?php

// Examinee Performance API

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../config/connection-pdo.php';
require_once __DIR__ . '/../includes/exam-code-utils.php';
require_once __DIR__ . '/../includes/exam-session-utils.php';
require_once __DIR__ . '/../includes/archive-utils.php';

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
        sendError('Access denied. This resource is only available to trainees.', 403);
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
    return $avgPercentage >= 75 ? 'On track' : 'At risk';
}

function subjectPerformanceStatus(array $subject): string {
    $exams = (int)($subject['exams_taken'] ?? 0);
    $avg = (float)($subject['avg_percentage'] ?? 0);

    if ($exams === 0) {
        return 'No data';
    }

    return $avg >= 75 ? 'On track' : 'At risk';
}

function fetchGradedResults(PDO $pdo, int $userId, array $dateFilter, ?int $subjectId = null): array {
    $subjectSql = $subjectId ? ' AND e.Subject_ID = ?' : '';
    $params = [$userId];
    if ($subjectId) {
        $params[] = $subjectId;
    }
    $params = array_merge($params, $dateFilter['params']);
    $activeCurriculum = examineeActiveResultCurriculumCondition($pdo, 'e', 's', 'c');

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
            e.Subject_ID,
            s.Subject_Name,
            c.Course_Name,
            (SELECT COUNT(*) FROM tbl_exam_question eq WHERE eq.Exam_ID = e.Exam_ID) AS total_questions
        FROM tbl_result r
        INNER JOIN tbl_exam_session es ON r.Session_ID = es.Session_ID
        INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
        INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
        INNER JOIN tbl_course c ON s.Course_ID = c.Course_ID
        WHERE es.User_ID = ?{$subjectSql}
          AND {$activeCurriculum}
          AND {$dateFilter['sql']}
          AND " . latestFinishedResultMatchSql('es', 'r') . "
        ORDER BY r.Submission_Date ASC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        applyExamineeGradeToResult($row);
        if (!empty($row['Submission_Date'])) {
            $row['Submission_Date'] = date('Y-m-d H:i:s', strtotime($row['Submission_Date']));
        }
    }
    unset($row);

    return $rows;
}

function computeSubjectTrendFromRows(array $rows): string {
    if (count($rows) < 2) {
        return 'stable';
    }

    $latest = (float)$rows[count($rows) - 1]['Percentage'];
    $previous = (float)$rows[count($rows) - 2]['Percentage'];
    $diff = $latest - $previous;

    if ($diff >= 5) {
        return 'improving';
    }
    if ($diff <= -5) {
        return 'declining';
    }
    return 'stable';
}

function computeSubjectTrend(PDO $pdo, int $userId, int $subjectId, array $dateFilter): string {
    $rows = fetchGradedResults($pdo, $userId, $dateFilter, $subjectId);
    return computeSubjectTrendFromRows($rows);
}

function fetchSubjectRollup(PDO $pdo, int $userId, array $dateFilter): array {
    $rows = fetchGradedResults($pdo, $userId, $dateFilter);
    $grouped = [];

    foreach ($rows as $row) {
        $subjectId = (int)$row['Subject_ID'];
        if (!isset($grouped[$subjectId])) {
            $grouped[$subjectId] = [
                'Subject_ID' => $subjectId,
                'Subject_Name' => $row['Subject_Name'],
                'Course_Name' => $row['Course_Name'],
                'scores' => [],
                'passed_count' => 0,
                'failed_count' => 0,
                'last_exam_date' => null,
            ];
        }

        $grade = (float)$row['Percentage'];
        $grouped[$subjectId]['scores'][] = $grade;
        if ($row['Remarks'] === 'Passed') {
            $grouped[$subjectId]['passed_count']++;
        } else {
            $grouped[$subjectId]['failed_count']++;
        }
        $grouped[$subjectId]['last_exam_date'] = $row['Submission_Date'];
    }

    $subjects = [];
    foreach ($grouped as $subjectId => $data) {
        $scores = $data['scores'];
        $examsTaken = count($scores);
        $passedCount = (int)$data['passed_count'];
        $subjects[] = [
            'Subject_ID' => $subjectId,
            'Subject_Name' => $data['Subject_Name'],
            'Course_Name' => $data['Course_Name'],
            'exams_taken' => $examsTaken,
            'avg_percentage' => round(array_sum($scores) / $examsTaken, 1),
            'pass_rate' => round(($passedCount / $examsTaken) * 100, 1),
            'passed_count' => $passedCount,
            'failed_count' => (int)$data['failed_count'],
            'highest' => round(max($scores), 1),
            'lowest' => round(min($scores), 1),
            'last_exam_date' => $data['last_exam_date'],
        ];
    }

    usort($subjects, fn($a, $b) => ($a['avg_percentage'] <=> $b['avg_percentage']) ?: strcmp($a['Subject_Name'], $b['Subject_Name']));

    foreach ($subjects as &$subject) {
        $subjectRows = array_values(array_filter(
            $rows,
            fn($row) => (int)$row['Subject_ID'] === (int)$subject['Subject_ID']
        ));
        $subject['trend'] = computeSubjectTrendFromRows($subjectRows);
        $subject['status'] = subjectPerformanceStatus(array_merge($subject, ['trend' => $subject['trend']]));
    }
    unset($subject);

    return $subjects;
}

function fetchPerformanceTrend(PDO $pdo, int $userId, array $dateFilter): array {
    $rows = fetchGradedResults($pdo, $userId, $dateFilter);

    return array_map(static function (array $row): array {
        return [
            'date' => $row['Submission_Date'],
            'score' => round((float)$row['Percentage'], 1),
            'exam_title' => $row['exam_title'],
            'subject_name' => $row['Subject_Name'],
        ];
    }, $rows);
}

function fetchOverallSummary(PDO $pdo, int $userId, array $dateFilter): array {
    $rows = fetchGradedResults($pdo, $userId, $dateFilter);
    $examsTaken = count($rows);

    if ($examsTaken === 0) {
        return [
            'exams_taken' => 0,
            'avg_percentage' => 0,
            'passed_count' => 0,
            'failed_count' => 0,
            'pass_rate' => 0,
            'highest_score' => 0,
            'lowest_score' => 0,
            'status' => overallStatus(0, 0, 0),
        ];
    }

    $scores = array_map(fn($row) => (float)$row['Percentage'], $rows);
    $passedCount = count(array_filter($rows, fn($row) => $row['Remarks'] === 'Passed'));
    $avg = round(array_sum($scores) / $examsTaken, 1);

    return [
        'exams_taken' => $examsTaken,
        'avg_percentage' => $avg,
        'passed_count' => $passedCount,
        'failed_count' => $examsTaken - $passedCount,
        'pass_rate' => round(($passedCount / $examsTaken) * 100, 1),
        'highest_score' => round(max($scores), 1),
        'lowest_score' => round(min($scores), 1),
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

    $curriculumActive = examineeActiveCurriculumCondition($pdo, 'e', 's', 'c', 'b');

    $stmt = $pdo->prepare("
        SELECT DISTINCT
            s.Subject_ID,
            s.Subject_Name,
            e.Exam_ID,
            e.Title AS exam_title,
            e.Deadline
        FROM tbl_exam e
        INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
        INNER JOIN tbl_course c ON s.Course_ID = c.Course_ID
        INNER JOIN tbl_exam_batch eb ON e.Exam_ID = eb.Exam_ID
        INNER JOIN tbl_batch b ON eb.Batch_ID = b.Batch_ID
        INNER JOIN tbl_user_batch ub ON b.Batch_ID = ub.Batch_ID
        LEFT JOIN tbl_exam_session es ON e.Exam_ID = es.Exam_ID AND es.User_ID = ? AND es.Time_Ended IS NOT NULL
        WHERE e.Status = 'Published'
        AND {$curriculumActive}
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
            $reasons[] = 'average score is below 75%';
        }
        if ($failed >= 1 && ($subject['exams_taken'] ?? 0) > 0) {
            $priority += 40 + ($failed * 10);
            if ($failed >= 2) {
                $reasons[] = "{$failed} failed attempts";
            } else {
                $reasons[] = 'recent failure';
            }
        }
        if ($trend === 'declining') {
            $priority += 25;
            $reasons[] = 'scores are declining';
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

            $strongSubjects = array_values(array_filter($subjects, fn($s) => ($s['status'] ?? '') === 'On track'));
            usort($strongSubjects, fn($a, $b) => (float)$b['avg_percentage'] <=> (float)$a['avg_percentage']);

            $weakSubjects = array_values(array_filter($subjects, fn($s) => ($s['status'] ?? '') === 'At risk'));
            usort($weakSubjects, fn($a, $b) => (float)$a['avg_percentage'] <=> (float)$b['avg_percentage']);

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
                SELECT s.Subject_ID, s.Subject_Name, c.Course_Name
                FROM tbl_subject s
                INNER JOIN tbl_course c ON s.Course_ID = c.Course_ID
                WHERE s.Subject_ID = ?
                  AND ' . activeArchiveCondition($pdo, 'tbl_subject', 's') . '
                  AND ' . activeArchiveCondition($pdo, 'tbl_course', 'c') . '
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

            $activeExam = activeArchiveCondition($pdo, 'tbl_exam', 'e');
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
                    es.Session_ID,
                    es.Time_Started,
                    es.Time_Ended,
                    (SELECT COUNT(*) FROM tbl_exam_question eq WHERE eq.Exam_ID = e.Exam_ID) AS total_questions
                FROM tbl_result r
                INNER JOIN tbl_exam_session es ON r.Session_ID = es.Session_ID
                INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
                WHERE es.User_ID = ? AND e.Subject_ID = ?
                  AND {$activeExam}
                  AND {$dateFilter['sql']}
                  AND " . finishedSittingPredicateSql('es') . "
                ORDER BY COALESCE(es.Time_Ended, r.Submission_Date) DESC, r.Result_ID DESC
            ");
            $stmt->execute(array_merge([$userId, $subjectId], $dateFilter['params']));
            $attempts = annotateAttemptHistory($stmt->fetchAll(PDO::FETCH_ASSOC));

            foreach ($attempts as &$attempt) {
                applyExamineeGradeToResult($attempt);
                $attempt['status'] = $attempt['Remarks'];
                if (!empty($attempt['Submission_Date'])) {
                    $attempt['Submission_Date'] = date('Y-m-d H:i:s', strtotime($attempt['Submission_Date']));
                }
                $attempt['time_taken'] = formatExamTimeTaken(
                    $attempt['Time_Started'] ?? null,
                    $attempt['Time_Ended'] ?? null,
                    countExamSessionAnswers(
                        $pdo,
                        (int)($attempt['Session_ID'] ?? 0),
                        (int)($attempt['Exam_ID'] ?? 0)
                    )
                );
            }
            unset($attempt);

            sendSuccess([
                'date_range' => $dateRange,
                'subject' => $subject,
                'summary' => $subjectSummary,
                'attempts' => $attempts,
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
