<?php
/**
 * Admin exam responses API — rankings and per-student review for a single exam.
 */

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'CCMD'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/connection-pdo.php';
require_once __DIR__ . '/../includes/exam-code-utils.php';
require_once __DIR__ . '/../includes/exam-participant-utils.php';
require_once __DIR__ . '/../includes/exam-assignment-utils.php';
require_once __DIR__ . '/../includes/exam-session-utils.php';
require_once __DIR__ . '/../includes/exam-item-analysis-utils.php';
require_once __DIR__ . '/../includes/exam-deadline-finalize-utils.php';

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

function fetchExamOrFail(PDO $pdo, int $examId): array {
    $stmt = $pdo->prepare('
        SELECT
            e.Exam_ID,
            e.Title,
            e.Description,
            e.Passing_Score,
            e.Duration,
            e.Status,
            e.Is_Archived,
            s.Subject_Name,
            s.Subject_Code,
            c.Course_ID,
            c.Course_Name,
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

    $exam['Assignments'] = fetchExamAssignments($pdo, $examId);
    $exam['Assignment_Summary'] = buildExamAssignmentsSummary($exam['Assignments']);

    return $exam;
}

function countAssignedExaminees(PDO $pdo, int $examId): int {
    if (examParticipantTableExists($pdo)) {
        $participantCount = countExamParticipants($pdo, $examId);
        if ($participantCount > 0) {
            return $participantCount;
        }
    }

    $stmt = $pdo->prepare('
        SELECT COUNT(DISTINCT ub.User_ID) AS total
        FROM tbl_exam_batch eb
        INNER JOIN tbl_user_batch ub ON eb.Batch_ID = ub.Batch_ID
        WHERE eb.Exam_ID = ?
          AND ub.Status = \'Active\'
    ');
    $stmt->execute([$examId]);
    return (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
}

function applyCompetitionRank(array &$rows): void {
    $rank = 0;
    $position = 0;
    $prevKey = null;

    foreach ($rows as &$row) {
        $position++;
        $key = ($row['grade_percent'] ?? 0) . '|' . ($row['Score'] ?? 0);
        if ($key !== $prevKey) {
            $rank = $position;
            $prevKey = $key;
        }
        $row['standing'] = $rank;
        $row['rank'] = $rank;
    }
    unset($row);
}

/**
 * Score from saved answers on this exam's current questions.
 *
 * @return array{score:int, answered_count:int, raw_percent:float}
 */
function resolveLiveSessionScore(PDO $pdo, int $sessionId, int $examId, int $totalItems): array {
    return resolveLiveExamSessionScore($pdo, $sessionId, $examId, $totalItems);
}

function enrichRankingRow(array &$row, int $totalItems, int $examId, PDO $pdo): void {
    $sessionId = (int)($row['Session_ID'] ?? 0);
    if ($sessionId > 0 && $examId > 0) {
        $live = resolveLiveSessionScore($pdo, $sessionId, $examId, $totalItems);
        $score = (float)$live['score'];
        $rawPercent = $live['raw_percent'];
        $row['answered_count'] = $live['answered_count'];
    } else {
        $score = (float)($row['Score'] ?? 0);
        $rawPercent = round((float)($row['Percentage'] ?? 0), 2);
        $row['answered_count'] = 0;
    }

    $passingScore = (float)($row['Passing_Score'] ?? 50);

    $row['total_items'] = $totalItems;
    $row['score_display'] = $totalItems > 0 ? ($score . '/' . $totalItems) : (string)(int)$score;
    $row['grade_percent'] = computeGradePercent($score, $totalItems);
    $row['raw_percent'] = $rawPercent;
    $row['passing_score'] = $passingScore;
    $passed = $rawPercent >= $passingScore;
    $row['status'] = $passed ? 'Passed' : 'Failed';
    $row['remarks'] = $row['status'];
    $row['time_taken'] = formatTimeTaken($row['Time_Started'] ?? null, $row['Time_Ended'] ?? null);
    $row['Score'] = $score;
    $row['Percentage'] = $rawPercent;
    $row['Personnel_Rank'] = trim((string)($row['Personnel_Rank'] ?? '')) ?: null;
}

function formatTimeTaken(?string $timeStarted, ?string $timeEnded): string {
    if (!$timeStarted || !$timeEnded) {
        return 'N/A';
    }

    $diff = strtotime($timeEnded) - strtotime($timeStarted);
    if ($diff < 0) {
        return 'N/A';
    }

    $hours = floor($diff / 3600);
    $minutes = floor(($diff % 3600) / 60);
    $seconds = $diff % 60;

    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}

function buildResultDetail(PDO $pdo, int $resultId, int $examId): array {
    $hasPersonnelRank = personnelRankColumnExists($pdo);
    $rankSelect = $hasPersonnelRank ? 'u.Personnel_Rank,' : 'NULL AS Personnel_Rank,';
    $enrollmentSelect = userExamEnrollmentSelectSql($pdo, 'u');

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
            u.User_ID,
            u.Fullname,
            u.Academic_Number,
            {$rankSelect}
            {$enrollmentSelect},
            s.Subject_Name,
            c.Course_Name
        FROM tbl_result r
        INNER JOIN tbl_exam_session es ON r.Session_ID = es.Session_ID
        INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
        INNER JOIN tbl_user u ON es.User_ID = u.User_ID
        LEFT JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
        LEFT JOIN tbl_course c ON s.Course_ID = c.Course_ID
        WHERE r.Result_ID = ?
          AND es.Exam_ID = ?
          AND es.Time_Ended IS NOT NULL
    ");
    $stmt->execute(prependExamEnrollmentParams($pdo, $examId, [$resultId, $examId]));
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        sendError('Result not found for this exam', 404);
    }

    $result['time_taken'] = formatTimeTaken($result['Time_Started'], $result['Time_Ended']);
    $result['Duration_Minutes'] = $result['Duration'] ?? 0;

    $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM tbl_exam_question WHERE Exam_ID = ?');
    $stmt->execute([$examId]);
    $result['total_questions'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    $sessionId = (int)($result['Session_ID'] ?? 0);
    $live = resolveLiveSessionScore($pdo, $sessionId, $examId, $result['total_questions']);
    $score = (float)$live['score'];
    $rawPercent = $live['raw_percent'];

    $result['Score'] = $score;
    $result['Percentage'] = $rawPercent;
    $result['answered_count'] = $live['answered_count'];
    $result['grade_percent'] = computeGradePercent($score, $result['total_questions']);
    $result['raw_percent'] = $rawPercent;
    $result['passing_score'] = (float)($result['Passing_Score'] ?? 50);
    $result['score_display'] = $result['total_questions'] > 0
        ? ((int)$score . '/' . $result['total_questions'])
        : (string)(int)$score;
    $passed = $rawPercent >= $result['passing_score'];
    $result['status'] = $passed ? 'Passed' : 'Failed';
    $result['remarks'] = $result['status'];
    $result['Personnel_Rank'] = trim((string)($result['Personnel_Rank'] ?? '')) ?: null;

    $stmt = $pdo->prepare('
        SELECT
            eq.Question_ID,
            qb.Question_Text,
            qb.Question_Type,
            a.Answer_ID,
            a.Choice_ID AS user_answer_id,
            a.Is_Correct AS user_is_correct,
            c_user.Choice_Text AS user_answer_text
        FROM tbl_exam_question eq
        INNER JOIN tbl_question_bank qb ON eq.Question_ID = qb.Question_ID
        LEFT JOIN tbl_answer a ON eq.Question_ID = a.Question_ID AND a.Session_ID = ?
        LEFT JOIN tbl_choice c_user ON a.Choice_ID = c_user.Choice_ID
        WHERE eq.Exam_ID = ?
        ORDER BY eq.Question_ID
    ');
    $stmt->execute([$sessionId, $examId]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($questions as &$question) {
        $stmt = $pdo->prepare('
            SELECT Choice_ID, Choice_Text, Is_Correct
            FROM tbl_choice
            WHERE Question_ID = ?
            ORDER BY Choice_ID
        ');
        $stmt->execute([$question['Question_ID']]);
        $question['choices'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($question['choices'] as $choice) {
            if ($choice['Is_Correct']) {
                $question['correct_answer_id'] = $choice['Choice_ID'];
                $question['correct_answer_text'] = $choice['Choice_Text'];
                break;
            }
        }
    }
    unset($question);

    $result['questions'] = $questions;

    return $result;
}

$action = $_GET['action'] ?? '';
$examId = (int)($_GET['exam_id'] ?? 0);

try {
    if ($action === 'summary') {
        if (!$examId) {
            sendError('exam_id is required');
        }

        $exam = fetchExamOrFail($pdo, $examId);
        try {
            finalizeExamAfterDeadline($pdo, $examId);
        } catch (Exception $e) {
            error_log('exam-responses finalize exam ' . $examId . ': ' . $e->getMessage());
        }

        $assignedCount = countAssignedExaminees($pdo, $examId);
        $totalItems = (int)($exam['question_count'] ?? 0);
        $passingScore = (float)($exam['Passing_Score'] ?? 50);

        $stmt = $pdo->prepare('
            SELECT es.Session_ID
            FROM tbl_exam_session es
            INNER JOIN tbl_result r ON r.Session_ID = es.Session_ID
            WHERE es.Exam_ID = ?
              AND es.Time_Ended IS NOT NULL
        ');
        $stmt->execute([$examId]);
        $finishedSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $finishedCount = count($finishedSessions);
        $passedCount = 0;
        $gradeSum = 0.0;
        $highestGrade = null;
        $lowestGrade = null;

        foreach ($finishedSessions as $row) {
            $live = resolveLiveSessionScore($pdo, (int)$row['Session_ID'], $examId, $totalItems);
            $grade = computeGradePercent((float)$live['score'], $totalItems);
            $rawPercent = $live['raw_percent'];
            $gradeSum += $grade;
            $highestGrade = $highestGrade === null ? $grade : max($highestGrade, $grade);
            $lowestGrade = $lowestGrade === null ? $grade : min($lowestGrade, $grade);
            if ($rawPercent >= $passingScore) {
                $passedCount++;
            }
        }

        $avgGrade = $finishedCount > 0 ? round($gradeSum / $finishedCount, 1) : 0;

        sendSuccess([
            'exam' => $exam,
            'assigned_count' => $assignedCount,
            'finished_count' => $finishedCount,
            'not_submitted_count' => max(0, $assignedCount - $finishedCount),
            'passed_count' => $passedCount,
            'failed_count' => max(0, $finishedCount - $passedCount),
            'avg_grade' => $avgGrade,
            'highest_grade' => $finishedCount > 0 ? round((float)$highestGrade, 1) : null,
            'lowest_grade' => $finishedCount > 0 ? round((float)$lowestGrade, 1) : null,
            'passing_score' => $passingScore,
            'pass_rate' => $finishedCount > 0 ? round(($passedCount / $finishedCount) * 100, 1) : 0
        ]);
    }

    if ($action === 'rankings') {
        if (!$examId) {
            sendError('exam_id is required');
        }

        $exam = fetchExamOrFail($pdo, $examId);
        $totalItems = (int)($exam['question_count'] ?? 0);

        $statusFilter = $_GET['status'] ?? 'all';
        $search = trim($_GET['search'] ?? '');
        $hasPersonnelRank = personnelRankColumnExists($pdo);
        $rankSelect = $hasPersonnelRank ? 'u.Personnel_Rank,' : 'NULL AS Personnel_Rank,';
        $enrollmentSelect = userExamEnrollmentSelectSql($pdo, 'u');

        $sql = "
            SELECT
                r.Result_ID,
                es.Session_ID,
                u.User_ID,
                u.Fullname,
                u.Academic_Number,
                {$rankSelect}
                {$enrollmentSelect},
                r.Score,
                r.Percentage,
                r.Remarks,
                r.Submission_Date,
                es.Time_Started,
                es.Time_Ended,
                e.Passing_Score
            FROM tbl_result r
            INNER JOIN tbl_exam_session es ON r.Session_ID = es.Session_ID
            INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
            INNER JOIN tbl_user u ON es.User_ID = u.User_ID
            WHERE es.Exam_ID = ?
              AND es.Time_Ended IS NOT NULL
        ";
        $params = prependExamEnrollmentParams($pdo, $examId, [$examId]);

        if ($search !== '') {
            $sql .= ' AND (u.Fullname LIKE ? OR u.Academic_Number LIKE ?';
            if ($hasPersonnelRank) {
                $sql .= ' OR u.Personnel_Rank LIKE ?';
            }
            $sql .= ')';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            if ($hasPersonnelRank) {
                $params[] = $like;
            }
        }

        $sql .= ' ORDER BY r.Score DESC, r.Percentage DESC, r.Submission_Date ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            enrichRankingRow($row, $totalItems, $examId, $pdo);
        }
        unset($row);

        usort($rows, function ($a, $b) {
            if ($a['grade_percent'] !== $b['grade_percent']) {
                return $b['grade_percent'] <=> $a['grade_percent'];
            }
            if ($a['Score'] !== $b['Score']) {
                return $b['Score'] <=> $a['Score'];
            }
            return strcmp($a['Submission_Date'] ?? '', $b['Submission_Date'] ?? '');
        });

        applyCompetitionRank($rows);

        if ($statusFilter === 'passed') {
            $rows = array_values(array_filter($rows, fn($r) => ($r['status'] ?? '') === 'Passed'));
        } elseif ($statusFilter === 'failed') {
            $rows = array_values(array_filter($rows, fn($r) => ($r['status'] ?? '') === 'Failed'));
        }

        sendSuccess($rows);
    }

    if ($action === 'not_submitted') {
        if (!$examId) {
            sendError('exam_id is required');
        }

        fetchExamOrFail($pdo, $examId);

        $enrollmentSelect = userExamEnrollmentSelectSql($pdo, 'u');
        $participantFilter = '';
        $params = prependExamEnrollmentParams($pdo, $examId, [$examId, $examId]);

        if (examParticipantTableExists($pdo)) {
            $participantFilter = "
              AND (
                  NOT EXISTS (SELECT 1 FROM tbl_exam_participant ep_all WHERE ep_all.Exam_ID = ?)
                  OR EXISTS (
                      SELECT 1 FROM tbl_exam_participant ep_allow
                      WHERE ep_allow.Exam_ID = ? AND ep_allow.User_ID = u.User_ID
                  )
              )";
            $params[] = $examId;
            $params[] = $examId;
        }

        $stmt = $pdo->prepare("
            SELECT DISTINCT
                u.User_ID,
                u.Fullname,
                u.Academic_Number,
                {$enrollmentSelect}
            FROM tbl_exam_batch eb
            INNER JOIN tbl_user_batch ub ON eb.Batch_ID = ub.Batch_ID
            INNER JOIN tbl_user u ON ub.User_ID = u.User_ID
            WHERE eb.Exam_ID = ?
              AND ub.Status = 'Active'
              {$participantFilter}
              AND u.User_ID NOT IN (
                  SELECT es.User_ID
                  FROM tbl_exam_session es
                  INNER JOIN tbl_result r ON r.Session_ID = es.Session_ID
                  WHERE es.Exam_ID = ?
                    AND es.Time_Ended IS NOT NULL
              )
            ORDER BY u.Fullname ASC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendSuccess($rows);
    }

    if ($action === 'log_export') {
        if (!$examId) {
            sendError('exam_id is required');
        }
        fetchExamOrFail($pdo, $examId);
        $examLabel = auditExamLabel($pdo, $examId);
        auditFromSession($pdo, 'REPORT', 'EXPORT_EXAM_RESPONSES', "Exported rankings CSV for exam \"{$examLabel}\"", 'SUCCESS', 'exam', $examId);
        sendSuccess(['logged' => true]);
    }

    if ($action === 'detail') {
        $resultId = (int)($_GET['result_id'] ?? 0);
        if (!$examId || !$resultId) {
            sendError('exam_id and result_id are required');
        }

        fetchExamOrFail($pdo, $examId);
        $detail = buildResultDetail($pdo, $resultId, $examId);

        $examineeLabel = auditUserLabel($pdo, (int)$detail['User_ID']);
        $examLabel = $detail['exam_title'] ?? auditExamLabel($pdo, $examId);
        logExamAuditAction(
            $pdo,
            (int)$_SESSION['user_id'],
            'VIEW_EXAM_RESPONSE',
            "{$examineeLabel} exam response viewed for \"{$examLabel}\""
        );

        sendSuccess($detail);
    }

    if ($action === 'item_analysis') {
        if (!$examId) {
            sendError('exam_id is required');
        }

        fetchExamOrFail($pdo, $examId);
        $assignedCount = countAssignedExaminees($pdo, $examId);
        $analysis = buildExamItemAnalysis($pdo, $examId, $assignedCount);

        sendSuccess($analysis);
    }

    sendError('Invalid action');
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
