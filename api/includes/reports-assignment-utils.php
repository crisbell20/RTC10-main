<?php
/**
 * Assignment-aware report metrics — assigned vs taken vs overdue missing exams.
 */

require_once __DIR__ . '/reports-utils.php';
require_once __DIR__ . '/exam-participant-utils.php';
require_once __DIR__ . '/exam-session-utils.php';

const REPORTS_EXAM_DEADLINE_GRACE_SECONDS = 0;

function reportsExamIsOverdue(?string $deadline): bool
{
    if ($deadline === null || trim($deadline) === '') {
        return false;
    }

    $deadlineTs = strtotime($deadline);
    if ($deadlineTs === false) {
        return false;
    }

    return time() > ($deadlineTs + REPORTS_EXAM_DEADLINE_GRACE_SECONDS);
}

/**
 * SQL condition matching examinee enrollment to an exam batch assignment.
 */
function reportsExamAssignmentMatchSql(
    string $userAlias = 'u',
    string $examAlias = 'e',
    string $enrollBatchAlias = 'enroll_b',
    string $sectionAlias = 'sec'
): string {
    return "EXISTS (
        SELECT 1
        FROM tbl_exam_batch eb
        INNER JOIN tbl_batch assigned_b ON eb.Batch_ID = assigned_b.Batch_ID
        INNER JOIN tbl_user_batch ub_assign ON ub_assign.User_ID = {$userAlias}.User_ID
            AND ub_assign.Status = 'Active'
        INNER JOIN tbl_batch {$enrollBatchAlias} ON ub_assign.Batch_ID = {$enrollBatchAlias}.Batch_ID
        LEFT JOIN tbl_academic_section {$sectionAlias} ON {$enrollBatchAlias}.Section_ID = {$sectionAlias}.Section_ID
        WHERE eb.Exam_ID = {$examAlias}.Exam_ID
          AND (
              eb.Batch_ID = ub_assign.Batch_ID
              OR (
                  (assigned_b.Section_ID IS NULL OR assigned_b.Section_ID = 0)
                  AND {$sectionAlias}.Batch_ID = eb.Batch_ID
              )
          )
    )";
}

/**
 * Participant allowlist SQL (same semantics as examinee dashboard).
 */
function reportsExamParticipantSql(string $userAlias = 'u', string $examAlias = 'e'): string
{
    return "(
        NOT EXISTS (SELECT 1 FROM tbl_exam_participant ep_all WHERE ep_all.Exam_ID = {$examAlias}.Exam_ID)
        OR EXISTS (
            SELECT 1 FROM tbl_exam_participant ep_user
            WHERE ep_user.Exam_ID = {$examAlias}.Exam_ID AND ep_user.User_ID = {$userAlias}.User_ID
        )
    )";
}

/**
 * Enrollment scope for cohort filters (course / batch / section).
 *
 * @return array{sql:string,params:array<int,mixed>,joins:string}
 */
function buildEnrollmentScope(PDO $pdo, array $get): array
{
    $courseId = !empty($get['course_id']) ? (int)$get['course_id'] : null;
    $batchId = !empty($get['batch_id']) ? (int)$get['batch_id'] : null;
    $sectionId = !empty($get['section_id']) ? (int)$get['section_id'] : null;

    $joins = "
        FROM tbl_user u
        INNER JOIN tbl_role r ON u.Role_ID = r.Role_ID
        INNER JOIN tbl_user_batch ub ON ub.User_ID = u.User_ID
        INNER JOIN tbl_batch enroll_b ON ub.Batch_ID = enroll_b.Batch_ID
        LEFT JOIN tbl_academic_section sec ON enroll_b.Section_ID = sec.Section_ID
        LEFT JOIN tbl_batch parent_b ON sec.Batch_ID = parent_b.Batch_ID
        INNER JOIN tbl_course c_enroll ON c_enroll.Course_ID = COALESCE(parent_b.Course_ID, enroll_b.Course_ID)
    ";

    $where = ["r.Role_Name = 'Examinee'", "ub.Status = 'Active'"];
    $params = [];

    if ($sectionId) {
        $where[] = 'sec.Section_ID = ?';
        $params[] = $sectionId;
    } elseif ($batchId) {
        $where[] = '(enroll_b.Batch_ID = ? OR sec.Batch_ID = ?)';
        $params[] = $batchId;
        $params[] = $batchId;
    }

    if ($courseId) {
        $where[] = 'c_enroll.Course_ID = ?';
        $params[] = $courseId;
    }

    return [
        'sql' => implode(' AND ', $where),
        'params' => $params,
        'joins' => $joins,
    ];
}

/**
 * Active/closed exam scope for assignment metrics (excludes Draft).
 *
 * @return array{sql:string,params:array<int,mixed>,curriculum_join:string,course_filter:string}
 */
function buildAssignedExamScope(PDO $pdo, array $get): array
{
    $courseId = !empty($get['course_id']) ? (int)$get['course_id'] : null;
    $examType = resolveExamTypeFilter($get);
    $curriculum = subjectCurriculumJoin($pdo);

    $where = ["e.Status IN ('Published', 'Closed')", activeArchiveCondition($pdo, 'tbl_exam', 'e')];
    $params = [];

    if ($courseId) {
        $where[] = "{$curriculum['course_filter']} = ?";
        $params[] = $courseId;
    }

    if ($examType !== null && examTypeColumnExists($pdo)) {
        $where[] = 'e.Exam_Type = ?';
        $params[] = $examType;
    }

    return [
        'sql' => implode(' AND ', $where),
        'params' => $params,
        'curriculum_join' => $curriculum['join'],
        'course_filter' => $curriculum['course_filter'],
    ];
}

/**
 * Distinct examinees with active enrollment in course / batch / section filters.
 */
function countEnrolledExamineesInScope(PDO $pdo, array $get): int
{
    $scope = buildEnrollmentScope($pdo, $get);
    $userActive = activeArchiveCondition($pdo, 'tbl_user', 'u');

    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT u.User_ID) AS total
        {$scope['joins']}
        WHERE {$scope['sql']}
          AND {$userActive}
    ");
    $stmt->execute($scope['params']);

    return (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
}

/**
 * Exams in report scope (Draft excluded), respecting course and optional batch/section assignment.
 */
function countExamsInReportsScope(PDO $pdo, array $get): int
{
    $batchId = !empty($get['batch_id']) ? (int)$get['batch_id'] : null;
    $sectionId = !empty($get['section_id']) ? (int)$get['section_id'] : null;

    $where = ["e.Status <> 'Draft'", activeArchiveCondition($pdo, 'tbl_exam', 'e')];
    $params = [];

    $curriculum = subjectCurriculumJoin($pdo);
    $courseId = !empty($get['course_id']) ? (int)$get['course_id'] : null;
    if ($courseId) {
        $where[] = "{$curriculum['course_filter']} = ?";
        $params[] = $courseId;
    }

    $examType = resolveExamTypeFilter($get);
    if ($examType !== null && examTypeColumnExists($pdo)) {
        $where[] = 'e.Exam_Type = ?';
        $params[] = $examType;
    }

    if ($sectionId) {
        $where[] = 'EXISTS (
            SELECT 1
            FROM tbl_exam_batch eb
            INNER JOIN tbl_academic_section sec ON sec.Batch_ID = eb.Batch_ID
            WHERE eb.Exam_ID = e.Exam_ID
              AND sec.Section_ID = ?
        )';
        $params[] = $sectionId;
    } elseif ($batchId) {
        $where[] = 'EXISTS (
            SELECT 1
            FROM tbl_exam_batch eb
            WHERE eb.Exam_ID = e.Exam_ID
              AND eb.Batch_ID = ?
        )';
        $params[] = $batchId;
    }

    $sql = "
        SELECT COUNT(DISTINCT e.Exam_ID) AS total
        FROM tbl_exam e
        INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
        {$curriculum['join']}
        WHERE " . implode(' AND ', $where);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
}

/**
 * Cohort pass rate using the same rules as per-student pass_rate (assignment-aware).
 *
 * @param array<int,array<string,mixed>> $students
 * @return array{pass_rate:float, passed_obligations:int, eligible_obligations:int}
 */
function computeCohortAssignmentPassRate(array $students): array
{
    $passed = 0;
    $eligible = 0;
    foreach ($students as $student) {
        $passed += (int)($student['passed_count'] ?? 0);
        $eligible += (int)($student['eligible_assigned'] ?? 0);
    }

    return [
        'pass_rate' => $eligible > 0 ? round(($passed / $eligible) * 100, 1) : 0.0,
        'passed_obligations' => $passed,
        'eligible_obligations' => $eligible,
    ];
}

/**
 * Mean of each student's assignment-aware avg_percentage (equal weight per student).
 *
 * @param array<int,array<string,mixed>> $students
 */
function computeCohortMeanStudentAverage(array $students): float
{
    if ($students === []) {
        return 0.0;
    }

    $sum = 0.0;
    foreach ($students as $student) {
        $sum += (float)($student['avg_percentage'] ?? 0);
    }

    return round($sum / count($students), 1);
}

/**
 * Shared assignment + finished-result context for report rollups.
 *
 * @return array{
 *   assignment_rows:array<int,array<string,mixed>>,
 *   result_rows:array<int,array<string,mixed>>,
 *   taken_by_user_exam:array<string,array<string,mixed>>,
 *   profiles:array<int,array<string,mixed>>
 * }
 */
function loadReportsAssignmentContext(PDO $pdo, array $get): array
{
    $scope = buildEnrollmentScope($pdo, $get);
    $examScope = buildAssignedExamScope($pdo, $get);
    $assignmentMatch = reportsExamAssignmentMatchSql();
    $participantSql = examParticipantTableExists($pdo)
        ? ' AND ' . reportsExamParticipantSql()
        : '';
    $examTypeSelect = examTypeColumnExists($pdo) ? ', e.Exam_Type' : '';

    $assignmentSql = "
        SELECT DISTINCT
            u.User_ID,
            u.Fullname,
            u.Academic_Number,
            e.Exam_ID,
            e.Title AS exam_title,
            e.Deadline,
            e.Passing_Score,
            s.Subject_ID,
            s.Subject_Name,
            s.Subject_Code,
            c.Course_Name
            {$examTypeSelect}
        {$scope['joins']}
        INNER JOIN tbl_exam e ON {$assignmentMatch}
        INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
        {$examScope['curriculum_join']}
        WHERE {$scope['sql']}
          AND {$examScope['sql']}
          {$participantSql}
    ";
    $assignmentParams = array_merge($scope['params'], $examScope['params']);
    $stmt = $pdo->prepare($assignmentSql);
    $stmt->execute($assignmentParams);
    $assignmentRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filter = buildResultFilters($pdo, $get);
    $curriculum = subjectCurriculumJoin($pdo);
    $answerSubmitted = examAnswerIsSubmittedSql('a');
    $resultSql = "
        SELECT es.User_ID,
               e.Exam_ID,
               e.Title AS exam_title,
               e.Deadline,
               r.Percentage,
               e.Passing_Score,
               s.Subject_ID,
               s.Subject_Name,
               s.Subject_Code,
               c.Course_Name,
               CASE WHEN r.Percentage >= e.Passing_Score THEN 1 ELSE 0 END AS passed,
               (
                   SELECT COUNT(*)
                   FROM tbl_answer a
                   INNER JOIN tbl_exam_question eq ON eq.Question_ID = a.Question_ID AND eq.Exam_ID = e.Exam_ID
                   WHERE a.Session_ID = es.Session_ID
                     AND {$answerSubmitted}
               ) AS answered_count
               {$examTypeSelect}
        FROM tbl_result r
        INNER JOIN tbl_exam_session es ON r.Session_ID = es.Session_ID
        INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
        INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
        {$curriculum['join']}
        INNER JOIN tbl_user u ON es.User_ID = u.User_ID
        WHERE {$filter['sql']}
    ";
    $resultStmt = $pdo->prepare($resultSql);
    $resultStmt->execute($filter['params']);
    $resultRows = $resultStmt->fetchAll(PDO::FETCH_ASSOC);

    $takenByUserExam = [];
    foreach ($resultRows as $row) {
        $key = (int)$row['User_ID'] . ':' . (int)$row['Exam_ID'];
        $takenByUserExam[$key] = $row;
    }

    $profileSql = "
        SELECT u.User_ID, u.Fullname, u.Academic_Number,
            (SELECT GROUP_CONCAT(DISTINCT COALESCE(pb.Batch_Name, enroll_b.Batch_Name) ORDER BY COALESCE(pb.Batch_Name, enroll_b.Batch_Name) SEPARATOR ', ')
             FROM tbl_user_batch ub2
             INNER JOIN tbl_batch enroll_b ON ub2.Batch_ID = enroll_b.Batch_ID
             LEFT JOIN tbl_academic_section sec2 ON enroll_b.Section_ID = sec2.Section_ID
             LEFT JOIN tbl_batch pb ON sec2.Batch_ID = pb.Batch_ID
             WHERE ub2.User_ID = u.User_ID AND ub2.Status = 'Active') AS batch_names,
            (SELECT GROUP_CONCAT(DISTINCT sec3.Section_Name ORDER BY sec3.Section_Name SEPARATOR ', ')
             FROM tbl_user_batch ub3
             INNER JOIN tbl_batch enroll_b ON ub3.Batch_ID = enroll_b.Batch_ID
             LEFT JOIN tbl_academic_section sec3 ON enroll_b.Section_ID = sec3.Section_ID
             WHERE ub3.User_ID = u.User_ID AND ub3.Status = 'Active' AND sec3.Section_Name IS NOT NULL) AS section_names
        {$scope['joins']}
        WHERE {$scope['sql']}
        GROUP BY u.User_ID, u.Fullname, u.Academic_Number
    ";
    $profileStmt = $pdo->prepare($profileSql);
    $profileStmt->execute($scope['params']);
    $profiles = [];
    foreach ($profileStmt->fetchAll(PDO::FETCH_ASSOC) as $profile) {
        $profiles[(int)$profile['User_ID']] = $profile;
    }

    return [
        'assignment_rows' => $assignmentRows,
        'result_rows' => $resultRows,
        'taken_by_user_exam' => $takenByUserExam,
        'profiles' => $profiles,
    ];
}

/**
 * Score used for one due assignment obligation (0% when overdue missing).
 */
function reportsObligationPercentage(?array $resultRow, ?string $deadline): ?float
{
    if ($resultRow !== null) {
        $answeredCount = (int)($resultRow['answered_count'] ?? 0);
        $deadlineForCheck = $deadline ?? ($resultRow['Deadline'] ?? null);

        if ($answeredCount === 0 && reportsExamIsOverdue($deadlineForCheck)) {
            return 0.0;
        }

        return (float)$resultRow['Percentage'];
    }

    if (reportsExamIsOverdue($deadline)) {
        return 0.0;
    }

    return null;
}

/**
 * @param array<string,mixed> $bucket
 */
function applyAssignmentMetricWithScoreRange(array &$bucket, ?array $resultRow, ?string $deadline): void
{
    $eligibleBefore = (int)($bucket['eligible_assigned'] ?? 0);
    applyStudentExamAssignmentMetric($bucket, $resultRow, $deadline);

    if ((int)($bucket['eligible_assigned'] ?? 0) <= $eligibleBefore) {
        return;
    }

    $score = reportsObligationPercentage($resultRow, $deadline);
    if ($score === null) {
        return;
    }

    $min = $bucket['score_min'] ?? null;
    $max = $bucket['score_max'] ?? null;
    $bucket['score_min'] = $min === null ? $score : min((float)$min, $score);
    $bucket['score_max'] = $max === null ? $score : max((float)$max, $score);
}

/**
 * @param array<string,mixed> $bucket
 * @return array{avg_percentage:float,pass_rate:float,lowest:float,highest:float}
 */
function finalizeAssignmentRollupMetrics(array $bucket): array
{
    $eligible = (int)($bucket['eligible_assigned'] ?? 0);
    $passed = (int)($bucket['passed_count'] ?? 0);

    return [
        'avg_percentage' => $eligible > 0
            ? round((float)($bucket['percentage_sum'] ?? 0) / $eligible, 1)
            : 0.0,
        'pass_rate' => $eligible > 0
            ? round(($passed / $eligible) * 100, 1)
            : 0.0,
        'lowest' => $bucket['score_min'] !== null ? round((float)$bucket['score_min'], 1) : 0.0,
        'highest' => $bucket['score_max'] !== null ? round((float)$bucket['score_max'], 1) : 0.0,
    ];
}

/**
 * @return array<string,mixed>
 */
function newSubjectReportMetricsBucket(array $row): array
{
    return [
        'Subject_ID' => (int)$row['Subject_ID'],
        'Subject_Name' => $row['Subject_Name'] ?? '',
        'Subject_Code' => $row['Subject_Code'] ?? '',
        'Course_Name' => $row['Course_Name'] ?? '',
        'exams_assigned' => 0,
        'exams_taken' => 0,
        'passed_count' => 0,
        'failed_count' => 0,
        'missing_overdue' => 0,
        'eligible_assigned' => 0,
        'effective_failed' => 0,
        'upcoming_untaken' => 0,
        'percentage_sum' => 0.0,
        'passing_score_sum' => 0.0,
        'score_min' => null,
        'score_max' => null,
        'student_ids' => [],
        'exam_ids' => [],
    ];
}

/**
 * @return array<string,mixed>
 */
function newExamReportMetricsBucket(array $row): array
{
    $bucket = newSubjectReportMetricsBucket($row);
    $bucket['Exam_ID'] = (int)$row['Exam_ID'];
    $bucket['exam_title'] = $row['exam_title'] ?? '';
    $bucket['Passing_Score'] = $row['Passing_Score'] ?? null;
    if (isset($row['Exam_Type'])) {
        $bucket['Exam_Type'] = $row['Exam_Type'];
    }

    return $bucket;
}

/**
 * Roll up assignment metrics per subject (same rules as By Student / overview).
 *
 * @return array<int,array<string,mixed>>
 */
function fetchSubjectReportMetrics(PDO $pdo, array $get): array
{
    $ctx = loadReportsAssignmentContext($pdo, $get);
    $bySubject = [];
    $processedAssignmentKeys = [];

    foreach ($ctx['assignment_rows'] as $row) {
        $subjectId = (int)$row['Subject_ID'];
        $userId = (int)$row['User_ID'];
        $examId = (int)$row['Exam_ID'];
        $key = $userId . ':' . $examId;
        $processedAssignmentKeys[$key] = true;

        if (!isset($bySubject[$subjectId])) {
            $bySubject[$subjectId] = newSubjectReportMetricsBucket($row);
        }

        $bySubject[$subjectId]['student_ids'][$userId] = true;
        $bySubject[$subjectId]['exam_ids'][$examId] = true;
        $bySubject[$subjectId]['exams_assigned']++;

        applyAssignmentMetricWithScoreRange(
            $bySubject[$subjectId],
            $ctx['taken_by_user_exam'][$key] ?? null,
            $row['Deadline'] ?? null
        );
    }

    foreach ($ctx['result_rows'] as $row) {
        $subjectId = (int)$row['Subject_ID'];
        $userId = (int)$row['User_ID'];
        $examId = (int)$row['Exam_ID'];
        $key = $userId . ':' . $examId;

        if (isset($processedAssignmentKeys[$key]) || !isset($ctx['profiles'][$userId])) {
            continue;
        }

        if (!isset($bySubject[$subjectId])) {
            $bySubject[$subjectId] = newSubjectReportMetricsBucket($row);
        }

        $bySubject[$subjectId]['student_ids'][$userId] = true;
        $bySubject[$subjectId]['exam_ids'][$examId] = true;
        $bySubject[$subjectId]['exams_assigned']++;
        applyAssignmentMetricWithScoreRange($bySubject[$subjectId], $row, $row['Deadline'] ?? null);
    }

    $subjects = [];
    foreach ($bySubject as $bucket) {
        if ((int)$bucket['eligible_assigned'] <= 0) {
            continue;
        }

        $metrics = finalizeAssignmentRollupMetrics($bucket);
        $subjects[] = [
            'Subject_ID' => (int)$bucket['Subject_ID'],
            'Subject_Name' => $bucket['Subject_Name'],
            'Subject_Code' => $bucket['Subject_Code'],
            'Course_Name' => $bucket['Course_Name'],
            'exams' => count($bucket['exam_ids']),
            'students' => count($bucket['student_ids']),
            'avg_percentage' => $metrics['avg_percentage'],
            'pass_rate' => $metrics['pass_rate'],
            'lowest' => $metrics['lowest'],
            'highest' => $metrics['highest'],
        ];
    }

    usort($subjects, function ($a, $b) {
        $passCompare = (float)$a['pass_rate'] <=> (float)$b['pass_rate'];
        if ($passCompare !== 0) {
            return $passCompare;
        }

        return (float)$a['avg_percentage'] <=> (float)$b['avg_percentage'];
    });

    return $subjects;
}

/**
 * Per-exam assignment metrics within one subject.
 *
 * @return array<int,array<string,mixed>>
 */
function fetchSubjectExamMetrics(PDO $pdo, array $get, int $subjectId): array
{
    $ctx = loadReportsAssignmentContext($pdo, $get);
    $byExam = [];
    $processedAssignmentKeys = [];

    foreach ($ctx['assignment_rows'] as $row) {
        if ((int)$row['Subject_ID'] !== $subjectId) {
            continue;
        }

        $userId = (int)$row['User_ID'];
        $examId = (int)$row['Exam_ID'];
        $key = $userId . ':' . $examId;
        $processedAssignmentKeys[$key] = true;

        if (!isset($byExam[$examId])) {
            $byExam[$examId] = newExamReportMetricsBucket($row);
        }

        $byExam[$examId]['student_ids'][$userId] = true;
        $byExam[$examId]['exams_assigned']++;
        applyAssignmentMetricWithScoreRange(
            $byExam[$examId],
            $ctx['taken_by_user_exam'][$key] ?? null,
            $row['Deadline'] ?? null
        );
    }

    foreach ($ctx['result_rows'] as $row) {
        if ((int)$row['Subject_ID'] !== $subjectId) {
            continue;
        }

        $userId = (int)$row['User_ID'];
        $examId = (int)$row['Exam_ID'];
        $key = $userId . ':' . $examId;

        if (isset($processedAssignmentKeys[$key]) || !isset($ctx['profiles'][$userId])) {
            continue;
        }

        if (!isset($byExam[$examId])) {
            $byExam[$examId] = newExamReportMetricsBucket($row);
        }

        $byExam[$examId]['student_ids'][$userId] = true;
        $byExam[$examId]['exams_assigned']++;
        applyAssignmentMetricWithScoreRange($byExam[$examId], $row, $row['Deadline'] ?? null);
    }

    $exams = [];
    foreach ($byExam as $bucket) {
        if ((int)$bucket['eligible_assigned'] <= 0) {
            continue;
        }

        $metrics = finalizeAssignmentRollupMetrics($bucket);
        $exams[] = [
            'Exam_ID' => (int)$bucket['Exam_ID'],
            'exam_title' => $bucket['exam_title'],
            'Passing_Score' => $bucket['Passing_Score'],
            'Exam_Type' => $bucket['Exam_Type'] ?? null,
            'students' => count($bucket['student_ids']),
            'attempts' => (int)$bucket['eligible_assigned'],
            'avg_percentage' => $metrics['avg_percentage'],
            'pass_rate' => $metrics['pass_rate'],
        ];
    }

    usort($exams, fn($a, $b) => (float)$a['avg_percentage'] <=> (float)$b['avg_percentage']);

    return $exams;
}

/**
 * Fetch per-student assignment metrics for reports / at-risk.
 *
 * @return array<int,array<string,mixed>>
 */
function fetchStudentReportMetrics(PDO $pdo, array $get): array
{
    $ctx = loadReportsAssignmentContext($pdo, $get);
    $assignmentRows = $ctx['assignment_rows'];
    $resultRows = $ctx['result_rows'];
    $takenByUserExam = $ctx['taken_by_user_exam'];
    $profiles = $ctx['profiles'];

    $byUser = [];
    $processedAssignmentKeys = [];

    foreach ($assignmentRows as $row) {
        $userId = (int)$row['User_ID'];
        $examId = (int)$row['Exam_ID'];
        $key = $userId . ':' . $examId;
        $processedAssignmentKeys[$key] = true;

        if (!isset($byUser[$userId])) {
            $profile = $profiles[$userId] ?? $row;
            $byUser[$userId] = newStudentReportMetricsBucket($userId, $profile, $row);
        }

        $byUser[$userId]['exams_assigned']++;

        applyStudentExamAssignmentMetric(
            $byUser[$userId],
            $takenByUserExam[$key] ?? null,
            $row['Deadline'] ?? null
        );
    }

    foreach ($resultRows as $row) {
        $userId = (int)$row['User_ID'];
        $examId = (int)$row['Exam_ID'];
        $key = $userId . ':' . $examId;

        if (isset($processedAssignmentKeys[$key]) || !isset($profiles[$userId])) {
            continue;
        }

        if (!isset($byUser[$userId])) {
            $byUser[$userId] = newStudentReportMetricsBucket($userId, $profiles[$userId], $row);
        }

        $byUser[$userId]['exams_assigned']++;
        applyStudentExamAssignmentMetric($byUser[$userId], $row, $row['Deadline'] ?? null);
    }

    $examType = resolveExamTypeFilter($get);
    $students = [];

    foreach ($byUser as &$student) {
        $taken = (int)$student['exams_taken'];
        $eligible = (int)$student['eligible_assigned'];
        $passed = (int)$student['passed_count'];

        $student['avg_percentage'] = $eligible > 0
            ? round($student['percentage_sum'] / $eligible, 1)
            : 0.0;
        $student['avg_passing_score'] = $taken > 0
            ? round($student['passing_score_sum'] / $taken, 1)
            : 0.0;
        $student['pass_rate'] = $eligible > 0
            ? round(($passed / $eligible) * 100, 1)
            : 0.0;
        $student['status'] = evaluateStudentStatus(
            $examType,
            $taken,
            $passed,
            (float)$student['avg_percentage'],
            (float)$student['avg_passing_score'],
            (int)$student['missing_overdue'],
            $eligible,
            (int)$student['effective_failed']
        );

        unset($student['percentage_sum'], $student['passing_score_sum']);
        $students[] = $student;
    }
    unset($student);

    usort($students, fn($a, $b) => (float)$a['avg_percentage'] <=> (float)$b['avg_percentage']);

    return $students;
}

/**
 * Default per-student metrics bucket for report rows.
 */
function newStudentReportMetricsBucket(int $userId, array $profile, array $fallback = []): array
{
    return [
        'User_ID' => $userId,
        'Fullname' => $profile['Fullname'] ?? $fallback['Fullname'] ?? '',
        'Academic_Number' => $profile['Academic_Number'] ?? $fallback['Academic_Number'] ?? '',
        'batch_names' => $profile['batch_names'] ?? null,
        'section_names' => $profile['section_names'] ?? null,
        'exams_assigned' => 0,
        'exams_taken' => 0,
        'passed_count' => 0,
        'failed_count' => 0,
        'missing_overdue' => 0,
        'eligible_assigned' => 0,
        'effective_failed' => 0,
        'upcoming_untaken' => 0,
        'percentage_sum' => 0.0,
        'passing_score_sum' => 0.0,
    ];
}

/**
 * Overdue with no saved answers → Missing; otherwise taken or upcoming.
 */
function applyStudentExamAssignmentMetric(array &$student, ?array $resultRow, ?string $deadline): void
{
    if ($resultRow !== null) {
        $answeredCount = (int)($resultRow['answered_count'] ?? 0);
        $deadlineForCheck = $deadline ?? ($resultRow['Deadline'] ?? null);

        if ($answeredCount === 0 && reportsExamIsOverdue($deadlineForCheck)) {
            applyMissingOverdueToStudentBucket($student);
            return;
        }

        applyTakenExamToStudentBucket($student, $resultRow);
        return;
    }

    if (reportsExamIsOverdue($deadline)) {
        applyMissingOverdueToStudentBucket($student);
        return;
    }

    $student['upcoming_untaken']++;
}

function applyMissingOverdueToStudentBucket(array &$student): void
{
    $student['missing_overdue']++;
    $student['eligible_assigned']++;
    $student['effective_failed']++;
}

/**
 * Apply a finished attempt to a student metrics bucket.
 */
function applyTakenExamToStudentBucket(array &$student, array $taken): void
{
    $student['exams_taken']++;
    $student['eligible_assigned']++;
    $student['passing_score_sum'] += (float)$taken['Passing_Score'];
    $pct = (float)$taken['Percentage'];
    $student['percentage_sum'] += $pct;
    if ((int)$taken['passed'] === 1) {
        $student['passed_count']++;
    } else {
        $student['failed_count']++;
        $student['effective_failed']++;
    }
}
