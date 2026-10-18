<?php

// Exam responses API

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
require_once __DIR__ . '/../includes/person-name-utils.php';

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
    $row['time_taken'] = formatExamTimeTaken(
        $row['Time_Started'] ?? null,
        $row['Time_Ended'] ?? null,
        (int)($row['answered_count'] ?? 0)
    );
    $row['Score'] = $score;
    $row['Percentage'] = $rawPercent;
    $row['Personnel_Rank'] = trim((string)($row['Personnel_Rank'] ?? '')) ?: null;
}

/**
 * Validate and return batch/section filter IDs against exam assignments.
 *
 * @return array{0:int,1:int}
 */
function parseAssignmentFilterParams(array $assignments): array {
    $batchId = (int)($_GET['batch_id'] ?? 0);
    $sectionId = (int)($_GET['section_id'] ?? 0);

    $validBatchIds = [];
    $validSectionIds = [];
    foreach ($assignments as $assignment) {
        $parentBatchId = (int)($assignment['parent_batch_id'] ?? 0);
        $batchIdFallback = (int)($assignment['batch_id'] ?? 0);
        $resolvedBatchId = $parentBatchId > 0 ? $parentBatchId : $batchIdFallback;
        if ($resolvedBatchId > 0) {
            $validBatchIds[$resolvedBatchId] = true;
        }

        $assignmentSectionId = (int)($assignment['section_id'] ?? 0);
        if ($assignmentSectionId > 0) {
            $validSectionIds[$assignmentSectionId] = true;
        }
    }

    if ($batchId > 0 && !isset($validBatchIds[$batchId])) {
        $batchId = 0;
    }
    if ($sectionId > 0 && !isset($validSectionIds[$sectionId])) {
        $sectionId = 0;
    }

    return [$batchId, $sectionId];
}

/**
 * SQL EXISTS clause matching user enrollment to exam assignment filters.
 *
 * @return array{sql:string, params:array<int, mixed>}
 */
function buildExamEnrollmentFilterClause(PDO $pdo, int $examId, int $batchId, int $sectionId, string $userAlias = 'u'): array {
    if ($batchId <= 0 && $sectionId <= 0) {
        return ['sql' => '', 'params' => []];
    }

    if (examBatchHasSectionColumn($pdo)) {
        $sql = "
            AND EXISTS (
                SELECT 1
                FROM tbl_user_batch ub_f
                INNER JOIN tbl_exam_batch eb_f ON eb_f.Batch_ID = ub_f.Batch_ID AND eb_f.Exam_ID = ?
                INNER JOIN tbl_batch b_f ON ub_f.Batch_ID = b_f.Batch_ID
                LEFT JOIN tbl_academic_section s_f ON b_f.Section_ID = s_f.Section_ID
                LEFT JOIN tbl_batch pb_f ON s_f.Batch_ID = pb_f.Batch_ID
                WHERE ub_f.User_ID = {$userAlias}.User_ID
                  AND ub_f.Status = 'Active'
        ";
        $params = [$examId];

        if ($batchId > 0) {
            $sql .= ' AND COALESCE(pb_f.Batch_ID, b_f.Batch_ID) = ?';
            $params[] = $batchId;
        }
        if ($sectionId > 0) {
            $sql .= ' AND s_f.Section_ID = ?';
            $params[] = $sectionId;
        }

        $sql .= '
            )
        ';

        return ['sql' => $sql, 'params' => $params];
    }

    if ($batchId <= 0) {
        return ['sql' => '', 'params' => []];
    }

    return [
        'sql' => "
            AND EXISTS (
                SELECT 1
                FROM tbl_user_batch ub_f
                INNER JOIN tbl_exam_batch eb_f ON eb_f.Batch_ID = ub_f.Batch_ID AND eb_f.Exam_ID = ?
                INNER JOIN tbl_batch b_f ON ub_f.Batch_ID = b_f.Batch_ID
                WHERE ub_f.User_ID = {$userAlias}.User_ID
                  AND ub_f.Status = 'Active'
                  AND b_f.Batch_ID = ?
            )
        ",
        'params' => [$examId, $batchId],
    ];
}

function formatTimeTaken(?string $timeStarted, ?string $timeEnded, ?int $answeredCount = null): string {
    return formatExamTimeTaken($timeStarted, $timeEnded, $answeredCount);
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
            " . personNameSql('u') . " AS Fullname,
            u.Badge_Number,
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

    $result['Duration_Minutes'] = $result['Duration'] ?? 0;

    $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM tbl_exam_question WHERE Exam_ID = ?');
    $stmt->execute([$examId]);
    $result['total_questions'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    $sessionId = (int)($result['Session_ID'] ?? 0);
    $live = resolveLiveSessionScore($pdo, $sessionId, $examId, $result['total_questions']);
    $score = (float)$live['score'];
    $rawPercent = $live['raw_percent'];
    $result['time_taken'] = formatExamTimeTaken(
        $result['Time_Started'] ?? null,
        $result['Time_Ended'] ?? null,
        (int)($live['answered_count'] ?? 0)
    );

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

    $historyFinished = finishedSittingPredicateSql('es_hist');
    $historyStmt = $pdo->prepare("
        SELECT
            r_hist.Result_ID,
            r_hist.Score,
            r_hist.Percentage,
            r_hist.Remarks,
            r_hist.Submission_Date,
            es_hist.Exam_ID,
            es_hist.Time_Started,
            es_hist.Time_Ended
        FROM tbl_result r_hist
        INNER JOIN tbl_exam_session es_hist ON es_hist.Session_ID = r_hist.Session_ID
        WHERE es_hist.Exam_ID = ?
          AND es_hist.User_ID = ?
          AND {$historyFinished}
        ORDER BY r_hist.Submission_Date ASC, r_hist.Result_ID ASC
    ");
    $historyStmt->execute([$examId, (int)$result['User_ID']]);
    $history = annotateAttemptHistory($historyStmt->fetchAll(PDO::FETCH_ASSOC));
    $result['attempt_history'] = array_map(static function (array $row): array {
        return [
            'Result_ID' => (int)$row['Result_ID'],
            'attempt_number' => (int)($row['attempt_number'] ?? 0),
            'is_latest' => !empty($row['is_latest']),
            'Score' => $row['Score'],
            'Percentage' => $row['Percentage'],
            'Remarks' => $row['Remarks'],
            'Submission_Date' => $row['Submission_Date'],
        ];
    }, $history);
    foreach ($history as $historyRow) {
        if ((int)$historyRow['Result_ID'] === (int)$result['Result_ID']) {
            $result['attempt_number'] = (int)($historyRow['attempt_number'] ?? 1);
            $result['is_latest'] = !empty($historyRow['is_latest']);
            break;
        }
    }

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

        $finishedSql = finishedSittingPredicateSql('es');
        $latestSql = latestFinishedResultMatchSql('es', 'r');
        $stmt = $pdo->prepare("
            SELECT es.Session_ID, es.User_ID
            FROM tbl_exam_session es
            INNER JOIN tbl_result r ON r.Session_ID = es.Session_ID
            WHERE es.Exam_ID = ?
              AND {$finishedSql}
              AND {$latestSql}
        ");
        $stmt->execute([$examId]);
        $finishedSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $finishedCount = count($finishedSessions);
        $finishedUserCount = count(fetchExamFinishedSittingUserIds($pdo, $examId));
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
            'not_submitted_count' => max(0, $assignedCount - $finishedUserCount),
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
        [$filterBatchId, $filterSectionId] = parseAssignmentFilterParams($exam['Assignments'] ?? []);
        $enrollmentFilter = buildExamEnrollmentFilterClause($pdo, $examId, $filterBatchId, $filterSectionId, 'u');
        $hasPersonnelRank = personnelRankColumnExists($pdo);
        $rankSelect = $hasPersonnelRank ? 'u.Personnel_Rank,' : 'NULL AS Personnel_Rank,';
        $enrollmentSelect = userExamEnrollmentSelectSql($pdo, 'u');

        $finishedSql = finishedSittingPredicateSql('es');
        $latestSql = latestFinishedResultMatchSql('es', 'r');
        $retakeSelect = retakeColumnsExist($pdo)
            ? 'es.Retake_Granted, es.Is_Retake,'
            : '0 AS Retake_Granted, 0 AS Is_Retake,';
        $attemptCountFinished = finishedSittingPredicateSql('es_count');
        $sql = "
            SELECT
                r.Result_ID,
                es.Session_ID,
                u.User_ID,
                " . personNameSql('u') . " AS Fullname,
                u.Badge_Number,
                {$rankSelect}
                {$enrollmentSelect},
                r.Score,
                r.Percentage,
                r.Remarks,
                r.Submission_Date,
                es.Time_Started,
                es.Time_Ended,
                es.Cheating_Locked,
                {$retakeSelect}
                (
                    SELECT COUNT(*)
                    FROM tbl_exam_session es_count
                    INNER JOIN tbl_result r_count ON r_count.Session_ID = es_count.Session_ID
                    WHERE es_count.Exam_ID = es.Exam_ID
                      AND es_count.User_ID = es.User_ID
                      AND {$attemptCountFinished}
                ) AS attempt_count,
                e.Passing_Score
            FROM tbl_result r
            INNER JOIN tbl_exam_session es ON r.Session_ID = es.Session_ID
            INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
            INNER JOIN tbl_user u ON es.User_ID = u.User_ID
            WHERE es.Exam_ID = ?
              AND {$finishedSql}
              AND {$latestSql}
        ";
        $params = prependExamEnrollmentParams($pdo, $examId, [$examId]);

        if ($search !== '') {
            $sql .= ' AND (' . personNameSql('u') . ' LIKE ? OR u.Badge_Number LIKE ?';
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

        $sql .= $enrollmentFilter['sql'];
        $params = array_merge($params, $enrollmentFilter['params']);

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

        $exam = fetchExamOrFail($pdo, $examId);
        [$filterBatchId, $filterSectionId] = parseAssignmentFilterParams($exam['Assignments'] ?? []);
        $enrollmentFilter = buildExamEnrollmentFilterClause($pdo, $examId, $filterBatchId, $filterSectionId, 'u');

        $enrollmentSelect = userExamEnrollmentSelectSql($pdo, 'u');
        $hasPersonnelRank = personnelRankColumnExists($pdo);
        $rankSelect = $hasPersonnelRank ? 'u.Personnel_Rank,' : 'NULL AS Personnel_Rank,';
        $participantFilter = '';
        $finishedSql = examUserHasFinishedSittingSql('u.User_ID', (string)$examId);
        $params = prependExamEnrollmentParams($pdo, $examId, [$examId]);

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
                " . personNameSql('u') . " AS Fullname,
                u.Badge_Number,
                {$rankSelect}
                {$enrollmentSelect}
            FROM tbl_exam_batch eb
            INNER JOIN tbl_user_batch ub ON eb.Batch_ID = ub.Batch_ID
            INNER JOIN tbl_user u ON ub.User_ID = u.User_ID
            WHERE eb.Exam_ID = ?
              AND ub.Status = 'Active'
              {$participantFilter}
              {$enrollmentFilter['sql']}
              AND NOT {$finishedSql}
            ORDER BY " . personNameSql('u') . " ASC
        ");
        $params = array_merge($params, $enrollmentFilter['params']);
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

    if ($action === 'grant_retake') {
        if (($_SESSION['user_role'] ?? '') !== 'Admin') {
            sendError('Only an administrator can allow a retake.', 403);
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            sendError('Method not allowed', 405);
        }

        $resultId = (int)($_GET['result_id'] ?? 0);
        if (!$examId || !$resultId) {
            sendError('exam_id and result_id are required');
        }

        $exam = fetchExamOrFail($pdo, $examId);

        if ((int)($exam['Is_Archived'] ?? 0) === 1) {
            sendError('This exam is archived.', 403);
        }

        if (($exam['Status'] ?? '') !== 'Published') {
            sendError('This exam is not open. Publish it before allowing a retake.', 403);
        }

        $scheduleStmt = $pdo->prepare('SELECT Schedule_Date, Deadline FROM tbl_exam WHERE Exam_ID = ?');
        $scheduleStmt->execute([$examId]);
        $schedule = $scheduleStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $exam['Schedule_Date'] = $schedule['Schedule_Date'] ?? null;
        $exam['Deadline'] = $schedule['Deadline'] ?? null;

        $scheduleError = validateExamScheduleStarted($exam);
        if ($scheduleError !== null) {
            sendError($scheduleError, 403);
        }

        $deadlineError = validateExamDeadlineWithGrace($exam);
        if ($deadlineError !== null) {
            sendError('The exam deadline has passed. Extend the deadline before allowing a retake.', 403);
        }

        if (!retakeColumnsExist($pdo)) {
            sendError('Retake authorization is not available until the database update is applied.', 503);
        }

        $stmt = $pdo->prepare('
            SELECT
                r.Result_ID,
                es.Session_ID,
                es.User_ID,
                es.Time_Started,
                es.Time_Ended,
                es.Is_Retake,
                es.Cheating_Locked
            FROM tbl_result r
            INNER JOIN tbl_exam_session es ON r.Session_ID = es.Session_ID
            WHERE r.Result_ID = ?
              AND es.Exam_ID = ?
            LIMIT 1
        ');
        $stmt->execute([$resultId, $examId]);
        $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attempt) {
            sendError('Submitted attempt not found', 404);
        }

        if (empty($attempt['Time_Ended'])) {
            sendError('This attempt is still in progress.', 409);
        }

        if ((int)($attempt['Is_Retake'] ?? 0) === 1) {
            sendError('A retake cannot be authorized from a retake attempt.', 409);
        }

        if (empty($attempt['Time_Started']) || sessionStartedAndEndedTogether(
            $attempt['Time_Started'] ?? null,
            $attempt['Time_Ended'] ?? null
        )) {
            sendError('A retake can be authorized only for a completed original attempt.', 409);
        }

        $sessionId = (int)$attempt['Session_ID'];
        $userId = (int)$attempt['User_ID'];
        $totalItems = (int)($exam['question_count'] ?? 0);
        $live = resolveLiveSessionScore($pdo, $sessionId, $examId, $totalItems);
        $scoreLabel = $totalItems > 0
            ? ($live['score'] . '/' . $totalItems)
            : (string)$live['score'];
        $passingScore = (float)($exam['Passing_Score'] ?? 50);
        $remarks = $live['raw_percent'] >= $passingScore ? 'Passed' : 'Failed';
        $examineeLabel = auditUserLabel($pdo, $userId);
        $examLabel = auditExamLabel($pdo, $examId);

        $pdo->beginTransaction();
        try {
            lockUserExamSessions($pdo, $userId, $examId);
            $finishedCount = countFinishedSittings($pdo, $userId, $examId);
            if ($finishedCount >= 2) {
                $pdo->rollBack();
                sendError('This trainee has already used their one retake.', 409);
            }
            if ($finishedCount !== 1) {
                $pdo->rollBack();
                sendError('A retake can be authorized only after the original sitting is completed.', 409);
            }

            $openRetake = $pdo->prepare('
                SELECT Session_ID
                FROM tbl_exam_session
                WHERE User_ID = ? AND Exam_ID = ? AND Time_Ended IS NULL AND Is_Retake = 1
                LIMIT 1
            ');
            $openRetake->execute([$userId, $examId]);
            if ($openRetake->fetchColumn()) {
                $pdo->rollBack();
                sendError('A retake is already in progress for this trainee.', 409);
            }

            $grantUpdate = $pdo->prepare('
                UPDATE tbl_exam_session
                SET Retake_Granted = 1
                WHERE Session_ID = ?
                  AND Exam_ID = ?
                  AND User_ID = ?
                  AND Retake_Granted = 0
                  AND Is_Retake = 0
                  AND Time_Started IS NOT NULL
                  AND Time_Ended IS NOT NULL
                  AND Time_Started <> Time_Ended
            ');
            $grantUpdate->execute([$sessionId, $examId, $userId]);
            if ($grantUpdate->rowCount() !== 1) {
                $pdo->rollBack();
                sendError('A retake is already authorized for this trainee.', 409);
            }

            auditFromSession(
                $pdo,
                'EXAM',
                'GRANT_EXAM_RETAKE',
                "Granted one retake to {$examineeLabel} for \"{$examLabel}\" (original result kept: {$scoreLabel}, {$remarks}).",
                'SUCCESS',
                'exam',
                $examId
            );
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        sendSuccess([
            'result_id' => $resultId,
            'user_id' => $userId,
            'message' => 'Retake allowed',
        ]);
    }

    sendError('Invalid action');
} catch (Exception $e) {
    error_log('exam-responses.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred. Please try again later.']);
}
