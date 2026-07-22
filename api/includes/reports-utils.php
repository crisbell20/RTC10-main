<?php
/**
 * Reports & Analytics query helpers — filters, joins, and status evaluation.
 */

require_once __DIR__ . '/exam-type-utils.php';
require_once __DIR__ . '/module-utils.php';
require_once __DIR__ . '/archive-utils.php';

/**
 * Build shared WHERE clause for finished exam results.
 */
function buildResultFilters(PDO $pdo, array $get): array
{
    $dateRange = $get['date_range'] ?? null;
    $courseId = !empty($get['course_id']) ? (int)$get['course_id'] : null;
    $batchId = !empty($get['batch_id']) ? (int)$get['batch_id'] : null;
    $sectionId = !empty($get['section_id']) ? (int)$get['section_id'] : null;
    $examType = resolveExamTypeFilter($get);
    $search = trim($get['search'] ?? '');

    $examActive = activeArchiveCondition($pdo, 'tbl_exam', 'e');
    $where = ['es.Time_Ended IS NOT NULL', $examActive];
    $params = [];

    if ($dateRange !== null && $dateRange !== '' && $dateRange !== 'all') {
        $days = (int)$dateRange;
        if ($days > 0) {
            $where[] = 'r.Submission_Date >= DATE_SUB(NOW(), INTERVAL ? DAY)';
            $params[] = $days;
        }
    }
    if ($courseId) {
        $curriculum = subjectCurriculumJoin($pdo);
        $where[] = "{$curriculum['course_filter']} = ?";
        $params[] = $courseId;
    }
    if ($sectionId) {
        $where[] = 'es.User_ID IN (
            SELECT ub.User_ID FROM tbl_user_batch ub
            INNER JOIN tbl_batch eb ON ub.Batch_ID = eb.Batch_ID
            WHERE ub.Status = \'Active\' AND eb.Section_ID = ?
        )';
        $params[] = $sectionId;
    } elseif ($batchId) {
        $where[] = 'es.User_ID IN (
            SELECT ub.User_ID FROM tbl_user_batch ub
            INNER JOIN tbl_batch eb ON ub.Batch_ID = eb.Batch_ID
            LEFT JOIN tbl_academic_section sec ON eb.Section_ID = sec.Section_ID
            WHERE ub.Status = \'Active\'
              AND (eb.Batch_ID = ? OR sec.Batch_ID = ?)
        )';
        $params[] = $batchId;
        $params[] = $batchId;
    }
    if ($examType !== null && examTypeColumnExists($pdo)) {
        $where[] = 'e.Exam_Type = ?';
        $params[] = $examType;
    }
    if ($search !== '') {
        $where[] = '(u.Fullname LIKE ? OR u.Academic_Number LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
    }

    return [
        'sql' => implode(' AND ', $where),
        'params' => $params,
        'needs_user' => $search !== '' || $batchId !== null || $sectionId !== null,
        'exam_type' => $examType,
    ];
}

/**
 * Standard result joins including course (and module when enabled).
 */
function baseResultJoins(PDO $pdo, bool $needsUser = false): string
{
    $curriculum = subjectCurriculumJoin($pdo);
    $userJoin = $needsUser ? 'INNER JOIN tbl_user u ON es.User_ID = u.User_ID' : '';

    return "
        FROM tbl_result r
        INNER JOIN tbl_exam_session es ON r.Session_ID = es.Session_ID
        INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
        INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
        {$curriculum['join']}
        {$userJoin}
    ";
}

/**
 * Legacy at-risk evaluation when no exam-type-specific rules apply.
 */
function studentStatusLegacy(
    int $examsTaken,
    int $passedCount,
    float $avgPercentage,
    int $missingOverdue = 0,
    int $eligibleAssigned = 0,
    int $effectiveFailed = 0
): string {
    if ($eligibleAssigned === 0 && $examsTaken === 0 && $missingOverdue === 0) {
        return 'No data';
    }

    if ($missingOverdue >= 1) {
        return 'At risk';
    }

    $passRate = $eligibleAssigned > 0 ? ($passedCount / $eligibleAssigned) * 100 : 0;
    if ($passRate < 60 || $effectiveFailed >= 2 || ($examsTaken > 0 && $avgPercentage < 60)) {
        return 'At risk';
    }
    if ($passRate < 80) {
        return 'Needs review';
    }
    return 'On track';
}

/**
 * Evaluate student status; uses type-specific rules for POST_TEST / COMPREHENSIVE filters.
 */
function evaluateStudentStatus(
    ?string $examType,
    int $examsTaken,
    int $passedCount,
    float $avgPercentage,
    float $avgPassingScore = 0,
    int $missingOverdue = 0,
    int $eligibleAssigned = 0,
    int $effectiveFailed = 0
): string {
    if ($missingOverdue >= 1) {
        return 'At risk';
    }

    if ($examsTaken === 0 && $eligibleAssigned === 0) {
        return 'No data';
    }

    if (usesTypeSpecificAtRisk($examType)) {
        if ($examsTaken === 0) {
            return 'No data';
        }
        return $avgPercentage < $avgPassingScore ? 'At risk' : 'On track';
    }

    return studentStatusLegacy(
        $examsTaken,
        $passedCount,
        $avgPercentage,
        $missingOverdue,
        $eligibleAssigned,
        $effectiveFailed
    );
}

/**
 * SQL expression for chronological exam ordering in performance trend.
 */
function reportsTrendDateExpression(): string
{
    return 'DATE(COALESCE(e.Schedule_Date, MIN(r.Submission_Date)))';
}

/**
 * Attach best/weakest subject per student for summary rows.
 */
function attachSubjectHighlights(PDO $pdo, array $filters, array &$students): void
{
    if (empty($students)) {
        return;
    }

    $filter = buildResultFilters($pdo, $filters);
    $curriculum = subjectCurriculumJoin($pdo);
    $sql = '
        SELECT es.User_ID, s.Subject_Name, ROUND(AVG(r.Percentage), 1) AS avg_percentage
        FROM tbl_result r
        INNER JOIN tbl_exam_session es ON r.Session_ID = es.Session_ID
        INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
        INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
        ' . $curriculum['join'] . '
        INNER JOIN tbl_user u ON es.User_ID = u.User_ID
        WHERE ' . $filter['sql'] . '
        GROUP BY es.User_ID, s.Subject_ID, s.Subject_Name
    ';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($filter['params']);
    $byUser = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $byUser[(int)$row['User_ID']][] = $row;
    }

    foreach ($students as &$student) {
        $subjects = $byUser[(int)$student['User_ID']] ?? [];
        $student['best_subject'] = null;
        $student['weakest_subject'] = null;
        if (!$subjects) {
            continue;
        }
        usort($subjects, fn($a, $b) => (float)$b['avg_percentage'] <=> (float)$a['avg_percentage']);
        $student['best_subject'] = $subjects[0]['Subject_Name'];
        $student['weakest_subject'] = count($subjects) > 1
            ? $subjects[count($subjects) - 1]['Subject_Name']
            : null;
    }
    unset($student);
}

/**
 * Build at-risk student rows from aggregated per-user stats.
 */
function buildAtRiskStudentRows(array $rows, ?string $examType, int $limit = 10): array
{
    $atRisk = [];
    foreach ($rows as $row) {
        $examsTaken = (int)($row['exams_taken'] ?? 0);
        $passedCount = (int)($row['passed_count'] ?? 0);
        $avg = (float)($row['avg_percentage'] ?? 0);
        $avgPassing = (float)($row['avg_passing_score'] ?? 0);
        $missingOverdue = (int)($row['missing_overdue'] ?? 0);
        $eligibleAssigned = (int)($row['eligible_assigned'] ?? $examsTaken);
        $effectiveFailed = (int)($row['effective_failed'] ?? max(0, $examsTaken - $passedCount));

        $status = evaluateStudentStatus(
            $examType,
            $examsTaken,
            $passedCount,
            $avg,
            $avgPassing,
            $missingOverdue,
            $eligibleAssigned,
            $effectiveFailed
        );
        if ($status !== 'At risk') {
            continue;
        }

        if (!isset($row['pass_rate'])) {
            $row['pass_rate'] = $eligibleAssigned > 0
                ? round(($passedCount / $eligibleAssigned) * 100, 1)
                : 0;
        }
        $row['status'] = $status;
        $atRisk[] = $row;
    }

    usort($atRisk, fn($a, $b) => (float)($a['avg_percentage'] ?? 0) <=> (float)($b['avg_percentage'] ?? 0));
    return array_slice($atRisk, 0, $limit);
}
