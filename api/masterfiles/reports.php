<?php
/**
 * Reports & Analytics API — overview, student summary, subject summary, at-risk.
 */

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'CCMD'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/connection-pdo.php';
require_once __DIR__ . '/../includes/audit-log-utils.php';
require_once __DIR__ . '/../includes/batch-enrollment-utils.php';
require_once __DIR__ . '/../includes/archive-utils.php';
require_once __DIR__ . '/../includes/exam-type-utils.php';
require_once __DIR__ . '/../includes/reports-utils.php';
require_once __DIR__ . '/../includes/reports-assignment-utils.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function sendSuccess($data) {
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

function sendError($message, $statusCode = 400) {
    http_response_code($statusCode);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

try {
    $latestResultSql = ' AND ' . latestFinishedResultMatchSql('es', 'r');

    if ($action === 'batches') {
        $courseId = !empty($_GET['course_id']) ? (int)$_GET['course_id'] : null;
        $batchVisibility = courseBatchVisibilityCondition('b');
        $batchActive = activeArchiveCondition($pdo, 'tbl_batch', 'b');
        $sql = "SELECT b.Batch_ID, b.Batch_Name, b.Course_ID
                FROM tbl_batch b
                WHERE b.Status = 'Active' AND {$batchVisibility} AND {$batchActive}";
        $params = [];
        if ($courseId) {
            $sql .= ' AND b.Course_ID = ?';
            $params[] = $courseId;
        }
        $sql .= ' ORDER BY b.Batch_Name ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        sendSuccess($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($action === 'sections') {
        $batchId = !empty($_GET['batch_id']) ? (int)$_GET['batch_id'] : null;
        if (!$batchId) {
            sendSuccess([]);
        }
        $sectionActive = activeArchiveCondition($pdo, 'tbl_academic_section', 's');
        $stmt = $pdo->prepare("
            SELECT s.Section_ID, s.Section_Name, s.Batch_ID
            FROM tbl_academic_section s
            WHERE s.Batch_ID = ? AND {$sectionActive}
            ORDER BY s.Section_Name ASC
        ");
        $stmt->execute([$batchId]);
        sendSuccess($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($action === 'metrics') {
        $filter = buildResultFilters($pdo, $_GET);
        $examType = $filter['exam_type'];
        $joins = baseResultJoins($pdo, $filter['needs_user']);

        // Overall average and submission counts (attempt-level aggregates).
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT es.User_ID) AS active_examinees,
                   COUNT(DISTINCT e.Exam_ID) AS exams_administered,
                   COUNT(r.Result_ID) AS finished_submissions,
                   ROUND(AVG(r.Percentage), 1) AS avg_score,
                   SUM(CASE WHEN r.Percentage >= e.Passing_Score THEN 1 ELSE 0 END) AS passed_count
            {$joins} WHERE {$filter['sql']}{$latestResultSql}
        ");
        $stmt->execute($filter['params']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $finished = (int)($row['finished_submissions'] ?? 0);
        $passed = (int)($row['passed_count'] ?? 0);

        $assignmentMetrics = fetchStudentReportMetrics($pdo, $_GET);
        $cohortPass = computeCohortAssignmentPassRate($assignmentMetrics);
        $passRate = (float)$cohortPass['pass_rate'];
        $obligationPassed = (int)$cohortPass['passed_obligations'];
        $obligationEligible = (int)$cohortPass['eligible_obligations'];
        $avgScore = computeCohortMeanStudentAverage($assignmentMetrics);
        $avgGrade = computeCohortMeanStudentGradeAverage($assignmentMetrics);

        $subjectId = !empty($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
        if ($subjectId) {
            $assignedStudents = countStudentsWithAssignments($assignmentMetrics);
        } else {
            $assignedStudents = countEnrolledExamineesInScope($pdo, $_GET);
        }
        $examsInScope = countExamsInReportsScope($pdo, $_GET);

        $atRiskRows = buildAtRiskStudentRows($assignmentMetrics, $examType, PHP_INT_MAX);
        $atRiskCount = count($atRiskRows);
        $cohortExaminees = count($assignmentMetrics);
        $atRiskPercent = $cohortExaminees > 0 ? round(($atRiskCount / $cohortExaminees) * 100, 1) : 0;

        sendSuccess([
            'active_examinees' => $assignedStudents,
            'exams_administered' => $examsInScope,
            'students_with_results' => (int)($row['active_examinees'] ?? 0),
            'exams_with_results' => (int)($row['exams_administered'] ?? 0),
            'finished_submissions' => $finished,
            'avg_score' => $avgScore,
            'avg_grade' => $avgGrade,
            'pass_rate' => $passRate,
            'pass_rate_mode' => 'assignment',
            'passed_count' => $obligationPassed,
            'failed_count' => max(0, $obligationEligible - $obligationPassed),
            'eligible_obligations' => $obligationEligible,
            'attempts_passed' => $passed,
            'at_risk_count' => $atRiskCount,
            'at_risk_percent' => $atRiskPercent,
        ]);
    }

    if ($action === 'performance_trend') {
        $filter = buildResultFilters($pdo, $_GET);
        $joins = baseResultJoins($pdo, $filter['needs_user']);
        $questionJoin = reportsQuestionCountJoin();
        $gradeAvg = reportsGradePercentSql();
        $trendDate = reportsTrendDateExpression();

        // Average score per exam, ordered chronologically by schedule/completion date.
        $stmt = $pdo->prepare("
            SELECT e.Exam_ID,
                   e.Title AS exam_title,
                   {$trendDate} AS date,
                   ROUND(AVG(r.Percentage), 1) AS avg_raw_score,
                   ROUND(AVG({$gradeAvg}), 1) AS avg_grade,
                   COUNT(r.Result_ID) AS submission_count
            {$joins}
            {$questionJoin}
            WHERE {$filter['sql']}{$latestResultSql}
            GROUP BY e.Exam_ID, e.Title, e.Schedule_Date
            ORDER BY {$trendDate} ASC, e.Exam_ID ASC
        ");
        $stmt->execute($filter['params']);
        sendSuccess($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($action === 'pass_fail') {
        $assignmentMetrics = fetchStudentReportMetrics($pdo, $_GET);
        $cohortPass = computeCohortAssignmentPassRate($assignmentMetrics);
        $passed = (int)$cohortPass['passed_obligations'];
        $eligible = (int)$cohortPass['eligible_obligations'];
        $failed = max(0, $eligible - $passed);
        $passRate = (float)$cohortPass['pass_rate'];

        sendSuccess([
            'passed' => $passed,
            'failed' => $failed,
            'total' => $eligible,
            'pass_rate' => $passRate,
            'failed_rate' => $eligible > 0 ? round(($failed / $eligible) * 100, 1) : 0.0,
            'mode' => 'assignment',
        ]);
    }

    if ($action === 'top_subjects') {
        $filter = buildResultFilters($pdo, $_GET);
        $joins = baseResultJoins($pdo, $filter['needs_user']);
        $questionJoin = reportsQuestionCountJoin();
        $gradeAvg = reportsGradePercentSql();
        $stmt = $pdo->prepare("
            SELECT s.Subject_Name,
                   ROUND(AVG(r.Percentage), 1) AS avg_raw_score,
                   ROUND(AVG({$gradeAvg}), 1) AS avg_grade,
                   ROUND(100.0 * SUM(CASE WHEN r.Percentage >= e.Passing_Score THEN 1 ELSE 0 END) / COUNT(r.Result_ID), 1) AS pass_rate,
                   COUNT(r.Result_ID) AS attempt_count
            {$joins}
            {$questionJoin}
            WHERE {$filter['sql']}{$latestResultSql}
            GROUP BY s.Subject_ID, s.Subject_Name
            HAVING attempt_count >= 1
            ORDER BY avg_grade DESC
            LIMIT 5
        ");
        $stmt->execute($filter['params']);
        sendSuccess($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($action === 'weak_subjects') {
        $filter = buildResultFilters($pdo, $_GET);
        $joins = baseResultJoins($pdo, $filter['needs_user']);
        $questionJoin = reportsQuestionCountJoin();
        $gradeAvg = reportsGradePercentSql();
        $stmt = $pdo->prepare("
            SELECT s.Subject_Name,
                   ROUND(AVG(r.Percentage), 1) AS avg_raw_score,
                   ROUND(AVG({$gradeAvg}), 1) AS avg_grade,
                   ROUND(100.0 * SUM(CASE WHEN r.Percentage >= e.Passing_Score THEN 1 ELSE 0 END) / COUNT(r.Result_ID), 1) AS pass_rate,
                   COUNT(r.Result_ID) AS attempt_count
            {$joins}
            {$questionJoin}
            WHERE {$filter['sql']}{$latestResultSql}
            GROUP BY s.Subject_ID, s.Subject_Name
            HAVING attempt_count >= 1
            ORDER BY avg_grade ASC
            LIMIT 5
        ");
        $stmt->execute($filter['params']);
        sendSuccess($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($action === 'module_performance') {
        if (!moduleSchemaEnabled($pdo)) {
            sendSuccess([]);
        }

        $filter = buildResultFilters($pdo, $_GET);
        $joins = baseResultJoins($pdo, $filter['needs_user']);
        $questionJoin = reportsQuestionCountJoin();
        $gradeAvg = reportsGradePercentSql();
        $moduleActive = activeArchiveCondition($pdo, 'tbl_module', 'm');

        $stmt = $pdo->prepare("
            SELECT m.Module_ID,
                   m.Module_Name,
                   m.Sort_Order,
                   ROUND(AVG(r.Percentage), 1) AS avg_raw_score,
                   ROUND(AVG({$gradeAvg}), 1) AS avg_grade,
                   COUNT(r.Result_ID) AS attempt_count
            {$joins}
            {$questionJoin}
            WHERE {$filter['sql']}{$latestResultSql} AND {$moduleActive}
            GROUP BY m.Module_ID, m.Module_Name, m.Sort_Order
            HAVING attempt_count >= 1
            ORDER BY m.Sort_Order ASC, m.Module_Name ASC
        ");
        $stmt->execute($filter['params']);
        sendSuccess($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($action === 'participation') {
        $filter = buildResultFilters($pdo, $_GET);
        $joins = baseResultJoins($pdo, $filter['needs_user']);
        $questionJoin = reportsQuestionCountJoin();
        $gradeAvg = reportsGradePercentSql();
        $stmt = $pdo->prepare("
            SELECT e.Exam_ID, e.Title AS exam_title, COUNT(DISTINCT es.User_ID) AS participant_count,
                   ROUND(AVG(r.Percentage), 1) AS avg_raw_score,
                   ROUND(AVG({$gradeAvg}), 1) AS avg_grade
            {$joins}
            {$questionJoin}
            WHERE {$filter['sql']}{$latestResultSql}
            GROUP BY e.Exam_ID, e.Title ORDER BY participant_count DESC LIMIT 10
        ");
        $stmt->execute($filter['params']);
        sendSuccess($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($action === 'student_summary') {
        $filter = buildResultFilters($pdo, $_GET);
        $examType = $filter['exam_type'];
        $statusFilter = $_GET['student_status'] ?? 'all';
        $subjectId = !empty($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
        $subjectName = null;

        $students = fetchStudentReportMetrics($pdo, $_GET);

        if ($subjectId) {
            $stmt = $pdo->prepare('SELECT Subject_Name FROM tbl_subject WHERE Subject_ID = ?');
            $stmt->execute([$subjectId]);
            $subjectName = (string)($stmt->fetchColumn() ?: '');

            $students = array_values(array_filter(
                $students,
                static fn(array $student): bool => (int)($student['exams_assigned'] ?? 0) >= 1
            ));
        } else {
            // Subject highlights no longer shown in trainee rankings table.
        }

        $summary = summarizeTraineeStatusCounts($students);

        if ($statusFilter === 'at_risk') {
            $students = array_values(array_filter($students, fn($s) => $s['status'] === 'At risk'));
        } elseif ($statusFilter === 'on_track') {
            $students = array_values(array_filter($students, fn($s) => $s['status'] === 'On track'));
        }

        sortStudentsByPerformanceDesc($students);

        foreach ($students as $index => &$student) {
            $student['standing'] = $index + 1;
        }
        unset($student);

        sendSuccess([
            'students' => $students,
            'meta' => [
                'mode' => $subjectId ? 'by_subject' : 'all',
                'subject_id' => $subjectId ?: null,
                'subject_name' => $subjectId ? $subjectName : null,
                'summary' => $summary,
            ],
        ]);
    }

    if ($action === 'at_risk') {
        $filter = buildResultFilters($pdo, $_GET);
        $examType = $filter['exam_type'];

        $allMetrics = fetchStudentReportMetrics($pdo, $_GET);
        $allAtRisk = buildAtRiskStudentRows($allMetrics, $examType, PHP_INT_MAX);
        $students = array_slice($allAtRisk, 0, 10);
        $totalExaminees = count($allMetrics);
        $totalAtRisk = count($allAtRisk);

        sendSuccess([
            'students' => $students,
            'summary' => [
                'total_at_risk' => $totalAtRisk,
                'displayed' => count($students),
                'total_examinees' => $totalExaminees,
                'percent' => $totalExaminees > 0 ? round(($totalAtRisk / $totalExaminees) * 100, 1) : 0,
            ],
        ]);
    }

    if ($action === 'student_detail') {
        $userId = (int)($_GET['user_id'] ?? 0);
        if (!$userId) {
            sendError('user_id is required');
        }
        $filter = buildResultFilters($pdo, $_GET);
        $examType = $filter['exam_type'];
        $curriculum = subjectCurriculumJoin($pdo);

        $stmt = $pdo->prepare('SELECT User_ID, ' . personNameSql() . ' AS Fullname, Badge_Number FROM tbl_user WHERE User_ID = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            sendError('Trainee not found', 404);
        }

        $examTypeSelect = examTypeColumnExists($pdo) ? 'e.Exam_Type,' : '';

        $stmt = $pdo->prepare("
            SELECT r.Result_ID, r.Score, r.Percentage, r.Remarks, r.Submission_Date,
                   e.Exam_ID, e.Title AS exam_title, e.Passing_Score, {$examTypeSelect}
                   s.Subject_ID, s.Subject_Name, c.Course_Name,
                   es.Time_Started, es.Time_Ended,
                   CASE WHEN r.Percentage >= e.Passing_Score THEN 'Passed' ELSE 'Failed' END AS status,
                   (SELECT COUNT(*) FROM tbl_exam_question eq WHERE eq.Exam_ID = e.Exam_ID) AS total_questions
            FROM tbl_result r
            INNER JOIN tbl_exam_session es ON r.Session_ID = es.Session_ID
            INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
            INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
            {$curriculum['join']}
            WHERE es.User_ID = ? AND {$filter['sql']}
            ORDER BY r.Submission_Date DESC
        ");
        $stmt->execute(array_merge([$userId], $filter['params']));
        $attempts = annotateAttemptHistory($stmt->fetchAll(PDO::FETCH_ASSOC));
        $completedAttempts = [];
        foreach ($attempts as &$attempt) {
            enrichReportResultRow($attempt);
            if (!empty($attempt['is_in_progress'])) {
                $attempt['status'] = 'In progress';
                $attempt['Remarks'] = 'In progress';
                $attempt['attempt_number'] = null;
                $attempt['is_latest'] = false;
                continue;
            }
            $completedAttempts[] = $attempt;
        }
        unset($attempt);

        $assignmentRow = null;
        foreach (fetchStudentReportMetrics($pdo, $_GET) as $metricRow) {
            if ((int)$metricRow['User_ID'] === $userId) {
                $assignmentRow = $metricRow;
                break;
            }
        }

        $examsTaken = (int)($assignmentRow['exams_taken'] ?? count($completedAttempts));
        $passedCount = (int)($assignmentRow['passed_count'] ?? count(array_filter($completedAttempts, fn($a) => $a['status'] === 'Passed')));
        $avg = (float)($assignmentRow['avg_percentage'] ?? ($examsTaken > 0
            ? round(array_sum(array_column($completedAttempts, 'raw_percent')) / $examsTaken, 1)
            : 0));
        $avgGrade = (float)($assignmentRow['avg_grade'] ?? ($examsTaken > 0
            ? round(array_sum(array_column($completedAttempts, 'grade_percent')) / $examsTaken, 1)
            : 0));
        $avgPassing = (float)($assignmentRow['avg_passing_score'] ?? ($examsTaken > 0
            ? round(array_sum(array_column($completedAttempts, 'Passing_Score')) / $examsTaken, 1)
            : 0));

        $subjectPerformance = buildStudentSubjectPerformanceRollup($completedAttempts);

        sendSuccess([
            'student' => $user,
            'summary' => [
                'exams_assigned' => (int)($assignmentRow['exams_assigned'] ?? 0),
                'exams_taken' => $examsTaken,
                'missing_overdue' => (int)($assignmentRow['missing_overdue'] ?? 0),
                'eligible_assigned' => (int)($assignmentRow['eligible_assigned'] ?? $examsTaken),
                'avg_percentage' => $avg,
                'avg_grade' => $avgGrade,
                'pass_rate' => (float)($assignmentRow['pass_rate'] ?? ($examsTaken > 0 ? round(($passedCount / $examsTaken) * 100, 1) : 0)),
                'status' => $assignmentRow['status'] ?? evaluateTraineeRankingStatus([
                    'exams_taken' => $examsTaken,
                    'eligible_assigned' => (int)($assignmentRow['eligible_assigned'] ?? $examsTaken),
                    'avg_grade' => $avgGrade,
                    'avg_percentage' => $avg,
                ]),
            ],
            'subjects' => $subjectPerformance['subjects'],
            'at_risk_subjects' => $subjectPerformance['at_risk_subjects'],
            'attempts' => $attempts,
        ]);
    }

    if ($action === 'subject_summary') {
        sendSuccess(fetchSubjectReportMetrics($pdo, $_GET));
    }

    if ($action === 'subject_detail') {
        $subjectId = (int)($_GET['subject_id'] ?? 0);
        if (!$subjectId) {
            sendError('subject_id is required');
        }
        $curriculum = subjectCurriculumJoin($pdo);

        $stmt = $pdo->prepare("
            SELECT s.Subject_ID, s.Subject_Name, c.Course_Name
            FROM tbl_subject s
            {$curriculum['join']}
            WHERE s.Subject_ID = ?
        ");
        $stmt->execute([$subjectId]);
        $subject = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$subject) {
            sendError('Subject not found', 404);
        }

        sendSuccess([
            'subject' => $subject,
            'exams' => fetchSubjectExamMetrics($pdo, $_GET, $subjectId),
        ]);
    }

    if ($action === 'detailed_results') {
        $filter = buildResultFilters($pdo, $_GET);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;
        $curriculum = subjectCurriculumJoin($pdo);
        $joins = "
            FROM tbl_result r
            INNER JOIN tbl_exam_session es ON r.Session_ID = es.Session_ID
            INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
            INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
            {$curriculum['join']}
            INNER JOIN tbl_user u ON es.User_ID = u.User_ID
        ";
        $officialResultSql = ' AND ' . finishedSittingPredicateSql('es') . ' AND ' . latestFinishedResultMatchSql('es', 'r');
        $stmt = $pdo->prepare("SELECT COUNT(*) AS total {$joins} WHERE {$filter['sql']}{$officialResultSql}");
        $stmt->execute($filter['params']);
        $total = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        $stmt = $pdo->prepare("
            SELECT r.Result_ID, u.User_ID, " . personNameSql('u') . " AS examinee_name, e.Exam_ID, e.Title AS exam_title,
                   s.Subject_Name, r.Submission_Date, r.Score, r.Percentage,
                   CASE WHEN r.Percentage >= e.Passing_Score THEN 'Passed' ELSE 'Failed' END AS status, e.Passing_Score,
                   (SELECT COUNT(*) FROM tbl_exam_question eq WHERE eq.Exam_ID = e.Exam_ID) AS total_questions
            {$joins} WHERE {$filter['sql']}{$officialResultSql}
            ORDER BY r.Submission_Date DESC LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($filter['params']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            enrichReportResultRow($row);
        }
        unset($row);
        sendSuccess([
            'rows' => $rows,
            'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'pages' => max(1, (int)ceil($total / $limit))],
        ]);
    }

    if ($action === 'log_export') {
        $raw = json_decode(file_get_contents('php://input'), true) ?? [];
        $reportType = trim((string)($raw['report_type'] ?? $_POST['report_type'] ?? 'overview'));
        $rowCount = max(0, (int)($raw['row_count'] ?? $_POST['row_count'] ?? 0));
        auditFromSession(
            $pdo,
            'REPORT',
            'EXPORT_REPORT',
            "Exported {$reportType} report CSV ({$rowCount} rows)",
            'SUCCESS',
            'report',
            null
        );
        sendSuccess(['logged' => true]);
    }

    sendError('Invalid action');
} catch (Exception $e) {
    error_log('reports.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred. Please try again later.']);
}
