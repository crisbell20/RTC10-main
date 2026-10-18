<?php
/**
 * Reports & Analytics query helpers — filters, joins, and status evaluation.
 */

require_once __DIR__ . '/exam-type-utils.php';
require_once __DIR__ . '/module-utils.php';
require_once __DIR__ . '/archive-utils.php';
require_once __DIR__ . '/exam-code-utils.php';
require_once __DIR__ . '/person-name-utils.php';

/**
 * Build shared WHERE clause for finished exam results.
 */
function buildResultFilters(PDO $pdo, array $get): array
{
    $dateRange = $get['date_range'] ?? null;
    $courseId = !empty($get['course_id']) ? (int)$get['course_id'] : null;
    $batchId = !empty($get['batch_id']) ? (int)$get['batch_id'] : null;
    $sectionId = !empty($get['section_id']) ? (int)$get['section_id'] : null;
    $subjectId = !empty($get['subject_id']) ? (int)$get['subject_id'] : null;
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
    if ($subjectId) {
        $where[] = 'e.Subject_ID = ?';
        $params[] = $subjectId;
    }
    if ($search !== '') {
        $where[] = '(' . personNameSql('u') . ' LIKE ? OR u.Badge_Number LIKE ?)';
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
 * Trainee ranking status for Reports By Examinee (75% average threshold).
 */
function evaluateTraineeRankingStatus(array $student): string
{
    $taken = (int)($student['exams_taken'] ?? 0);
    $eligible = (int)($student['eligible_assigned'] ?? 0);
    if ($taken === 0 && $eligible === 0) {
        return 'No data';
    }

    $avg = (float)($student['avg_grade'] ?? $student['avg_percentage'] ?? 0);
    return $avg >= 75 ? 'On track' : 'At risk';
}

/**
 * Subject status using the same 75% rule as examinee performance.
 */
function evaluateSubjectPerformanceStatus(float $avgPercentage, int $examsTaken): string
{
    if ($examsTaken === 0) {
        return 'No data';
    }

    return $avgPercentage >= 75 ? 'On track' : 'At risk';
}

/**
 * Build per-subject performance for a trainee from finished attempts.
 * Matches examinee performance.php: PNP grade average and pass rate per subject.
 *
 * @param array<int,array<string,mixed>> $attempts
 * @return array{subjects: array<int,array<string,mixed>>, at_risk_subjects: array<int,array<string,mixed>>}
 */
function buildStudentSubjectPerformanceRollup(array $attempts): array
{
    $grouped = [];

    foreach ($attempts as $attempt) {
        $subjectId = (int)($attempt['Subject_ID'] ?? 0);
        if ($subjectId <= 0) {
            continue;
        }

        if (!isset($grouped[$subjectId])) {
            $grouped[$subjectId] = [
                'Subject_ID' => $subjectId,
                'Subject_Name' => (string)($attempt['Subject_Name'] ?? ''),
                'Course_Name' => (string)($attempt['Course_Name'] ?? ''),
                'scores' => [],
                'passed_count' => 0,
            ];
        }

        $gradeScore = (float)($attempt['grade_percent'] ?? examineeGradeScoreFromRow($attempt));
        $grouped[$subjectId]['scores'][] = $gradeScore;
        if (examineePassedOnGradeScale($attempt)) {
            $grouped[$subjectId]['passed_count']++;
        }
    }

    $subjects = [];
    foreach ($grouped as $data) {
        $examsTaken = count($data['scores']);
        $passedCount = (int)$data['passed_count'];
        $avg = $examsTaken > 0 ? round(array_sum($data['scores']) / $examsTaken, 1) : 0.0;
        $subjects[] = [
            'Subject_ID' => (int)$data['Subject_ID'],
            'Subject_Name' => $data['Subject_Name'],
            'Course_Name' => $data['Course_Name'],
            'exams_taken' => $examsTaken,
            'avg_percentage' => $avg,
            'pass_rate' => $examsTaken > 0 ? round(($passedCount / $examsTaken) * 100, 1) : 0.0,
            'status' => evaluateSubjectPerformanceStatus($avg, $examsTaken),
        ];
    }

    usort($subjects, fn($a, $b) => (float)$a['avg_percentage'] <=> (float)$b['avg_percentage']);

    $atRiskSubjects = array_values(array_filter(
        $subjects,
        fn(array $subject): bool => ($subject['status'] ?? '') === 'At risk'
    ));

    return [
        'subjects' => $subjects,
        'at_risk_subjects' => $atRiskSubjects,
    ];
}

/**
 * Summarize trainee counts for the By Examinee tab cards.
 *
 * @param array<int,array<string,mixed>> $students
 * @return array{total_examinees: int, on_track: int, at_risk: int}
 */
function summarizeTraineeStatusCounts(array $students): array
{
    $onTrack = 0;
    $atRisk = 0;

    foreach ($students as $student) {
        $status = (string)($student['status'] ?? '');
        if ($status === 'On track') {
            $onTrack++;
        } elseif ($status === 'At risk') {
            $atRisk++;
        }
    }

    return [
        'total_examinees' => count($students),
        'on_track' => $onTrack,
        'at_risk' => $atRisk,
    ];
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

        $status = evaluateTraineeRankingStatus($row);
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

/**
 * Join exam question counts for grade-scale calculations.
 */
function reportsQuestionCountJoin(string $examAlias = 'e'): string
{
    return "LEFT JOIN (
        SELECT Exam_ID, COUNT(*) AS question_count
        FROM tbl_exam_question
        GROUP BY Exam_ID
    ) qc ON qc.Exam_ID = {$examAlias}.Exam_ID";
}

/**
 * SQL expression: PNP grade percent from score and item count.
 */
function reportsGradePercentSql(string $scoreAlias = 'r.Score', string $totalAlias = 'qc.question_count'): string
{
    return "ROUND(({$scoreAlias} / GREATEST(COALESCE({$totalAlias}, 0), 1)) * 50 + 50, 1)";
}

/**
 * Add raw + grade display fields to a report result row.
 */
function enrichReportResultRow(array &$row): void
{
    $totalQuestions = (int)($row['total_questions'] ?? $row['question_count'] ?? 0);
    $score = (float)($row['Score'] ?? 0);
    $rawPercent = round((float)($row['Percentage'] ?? 0), 1);
    $passingRaw = (float)($row['Passing_Score'] ?? 50);

    $row['total_questions'] = $totalQuestions;
    $row['raw_percent'] = $rawPercent;
    $row['grade_percent'] = computeGradePercent($score, $totalQuestions);
    $row['score_display'] = $totalQuestions > 0
        ? ((int)$score . '/' . $totalQuestions)
        : (string)(int)$score;
    $row['grade_passing_score'] = computeGradePassingThreshold($passingRaw);
    $row['Percentage'] = $rawPercent;

    $passed = $rawPercent >= $passingRaw;
    $row['status'] = $passed ? 'Passed' : 'Failed';
    $row['Remarks'] = $row['status'];
}
