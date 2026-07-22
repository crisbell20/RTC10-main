<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'CCMD'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/connection-pdo.php';
require_once __DIR__ . '/../includes/exam-code-utils.php';
require_once __DIR__ . '/../includes/audit-log-utils.php';
require_once __DIR__ . '/../includes/module-utils.php';
require_once __DIR__ . '/../includes/archive-utils.php';
require_once __DIR__ . '/../includes/archive-purge-utils.php';

$request_method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$userRole = $_SESSION['user_role'] ?? '';
$hasExamCode = examCodeColumnsExist($pdo);
$hasResponseReview = responseReviewColumnExists($pdo);
$retentionDays = ARCHIVE_RETENTION_DAYS;

function appendExamRetentionMeta(array &$exam, int $retentionDays): void
{
    $sessions = (int)($exam['Session_Count'] ?? 0);
    appendRetentionMeta($exam, $retentionDays, $sessions > 0);
}

function appendUserRetentionMeta(array &$user, int $retentionDays): void
{
    $sessions = (int)($user['Session_Count'] ?? 0);
    appendRetentionMeta($user, $retentionDays, $sessions > 0);
}

function appendQuestionRetentionMeta(array &$question, int $retentionDays): void
{
    $usage = (int)($question['exam_usage_count'] ?? 0);
    appendRetentionMeta($question, $retentionDays, $usage > 0);
}

function appendBatchRetentionMeta(array &$batch, int $retentionDays): void
{
    $activeSections = (int)($batch['active_sections'] ?? 0);
    $enrollments = (int)($batch['enrollment_count'] ?? 0);
    appendRetentionMeta($batch, $retentionDays, $activeSections > 0 || $enrollments > 0);
}

function appendSectionRetentionMeta(array &$section, int $retentionDays): void
{
    $enrollments = (int)($section['enrollment_count'] ?? 0);
    appendRetentionMeta($section, $retentionDays, $enrollments > 0);
}

function appendModuleRetentionMeta(array &$module, int $retentionDays): void
{
    $activeSubjects = (int)($module['active_subjects'] ?? 0);
    appendRetentionMeta($module, $retentionDays, $activeSubjects > 0);
}

function appendSubjectRetentionMeta(array &$subject, int $retentionDays): void
{
    $examCount = (int)($subject['exam_count'] ?? 0);
    $activeQuestions = (int)($subject['active_questions'] ?? 0);
    appendRetentionMeta($subject, $retentionDays, $examCount > 0 || $activeQuestions > 0);
}

try {
    if ($request_method === 'GET' && $action === 'summary') {
        backfillCascadeArchives($pdo);

        $counts = [
            'exams' => $hasExamCode ? countArchived($pdo, 'tbl_exam') : 0,
            'courses' => countArchived($pdo, 'tbl_course'),
            'modules' => moduleTableExists($pdo) ? countArchived($pdo, 'tbl_module') : 0,
            'subjects' => countArchived($pdo, 'tbl_subject'),
            'batches' => countArchived($pdo, 'tbl_batch'),
            'sections' => countArchived($pdo, 'tbl_academic_section'),
            'questions' => countArchived($pdo, 'tbl_question_bank'),
            'users' => countArchived($pdo, 'tbl_user'),
        ];
        $eligible = countEligibleForPurgeAll($pdo, $retentionDays);

        echo json_encode([
            'success' => true,
            'data' => $counts,
            'archive_enabled' => $hasExamCode || archiveColumnExists($pdo, 'tbl_course'),
            'retention_days' => $retentionDays,
            'retention_years' => 2,
            'eligible_for_purge' => $eligible['total'],
            'eligible_breakdown' => $eligible,
        ]);
        exit;
    }

    if ($request_method === 'GET' && $action === 'list_exams') {
        if (!$hasExamCode) {
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'Archive feature is not available. Run the database migration.']);
            exit;
        }

        $course_id = $_GET['course_id'] ?? null;
        $module_id = $_GET['module_id'] ?? null;
        $subject_id = $_GET['subject_id'] ?? null;
        $search = trim($_GET['search'] ?? '');
        $date_from = trim($_GET['date_from'] ?? '');
        $date_to = trim($_GET['date_to'] ?? '');
        $curriculum = subjectCurriculumJoin($pdo);

        $codeColumns = 'e.Exam_Code, e.Exam_Code_Generated_At, e.Exam_Code_Reset_Count, e.Is_Archived, e.Archived_At,';
        $reviewColumn = $hasResponseReview ? 'e.Allow_Response_Review,' : '';

        $sql = "
            SELECT e.Exam_ID, e.User_ID, e.Subject_ID, e.Title, e.Description,
                   e.Schedule_Date, e.Deadline, e.Duration, e.Passing_Score, e.Status,
                   e.Is_Randomized, e.Time_Limit,
                   {$codeColumns}{$reviewColumn}
                   s.Subject_Name, s.Subject_Code,
                   {$curriculum['module_select']}
                   {$curriculum['course_id_select']} AS Course_ID,
                   c.Course_Name,
                   MAX(u.Fullname) AS Created_By,
                   COUNT(DISTINCT eq.Question_ID) AS Question_Count,
                   COUNT(DISTINCT eb.Batch_ID) AS Batch_Count,
                   COUNT(DISTINCT es.Session_ID) AS Session_Count,
                   COUNT(DISTINCT CASE WHEN es.Time_Ended IS NOT NULL THEN es.Session_ID END) AS Finished_Count
            FROM tbl_exam e
            INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
            {$curriculum['join']}
            LEFT JOIN tbl_user u ON e.User_ID = u.User_ID
            LEFT JOIN tbl_exam_question eq ON e.Exam_ID = eq.Exam_ID
            LEFT JOIN tbl_exam_batch eb ON e.Exam_ID = eb.Exam_ID
            LEFT JOIN tbl_exam_session es ON e.Exam_ID = es.Exam_ID
            WHERE e.Is_Archived = 1
        ";
        $params = [];

        if ($course_id) {
            $sql .= " AND {$curriculum['course_filter']} = ?";
            $params[] = $course_id;
        }
        if ($module_id && $curriculum['module_filter']) {
            $sql .= " AND {$curriculum['module_filter']} = ?";
            $params[] = $module_id;
        }
        if ($subject_id) {
            $sql .= ' AND e.Subject_ID = ?';
            $params[] = $subject_id;
        }
        if ($search !== '') {
            $sql .= ' AND (e.Title LIKE ? OR c.Course_Name LIKE ? OR s.Subject_Name LIKE ? OR s.Subject_Code LIKE ?)';
            $like = '%' . $search . '%';
            $params = array_merge($params, [$like, $like, $like, $like]);
        }
        if ($date_from !== '') {
            $sql .= ' AND DATE(e.Archived_At) >= ?';
            $params[] = $date_from;
        }
        if ($date_to !== '') {
            $sql .= ' AND DATE(e.Archived_At) <= ?';
            $params[] = $date_to;
        }

        $groupFields = 'e.Exam_ID, e.Exam_Code, e.Exam_Code_Generated_At, e.Exam_Code_Reset_Count, e.Is_Archived, e.Archived_At';
        if ($hasResponseReview) {
            $groupFields .= ', e.Allow_Response_Review';
        }

        $sql .= " GROUP BY {$groupFields}";
        $sql .= ' ORDER BY e.Archived_At DESC, e.Title ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($exams as &$exam) {
            appendExamRetentionMeta($exam, $retentionDays);
        }
        unset($exam);

        echo json_encode([
            'success' => true,
            'data' => $exams,
            'total' => count($exams),
            'retention_days' => $retentionDays,
        ]);
        exit;
    }

    if ($request_method === 'GET' && $action === 'list_courses') {
        if (!archiveColumnExists($pdo, 'tbl_course')) {
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'Course archive is not available. Run the curriculum archive migration.']);
            exit;
        }

        $search = trim($_GET['search'] ?? '');
        $sql = 'SELECT Course_ID, Course_Name, Details, Duration, Is_Archived, Archived_At
                FROM tbl_course WHERE Is_Archived = 1';
        $params = [];

        if ($search !== '') {
            $sql .= ' AND (Course_Name LIKE ? OR Details LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= ' ORDER BY Archived_At DESC, Course_Name ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($courses as &$course) {
            appendRetentionMeta($course, $retentionDays);
        }
        unset($course);

        echo json_encode(['success' => true, 'data' => $courses, 'retention_days' => $retentionDays]);
        exit;
    }

    if ($request_method === 'GET' && $action === 'list_modules') {
        if (!moduleTableExists($pdo) || !archiveColumnExists($pdo, 'tbl_module')) {
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'Module archive is not available. Run the curriculum archive migration.']);
            exit;
        }

        $search = trim($_GET['search'] ?? '');
        $course_id = $_GET['course_id'] ?? null;
        $date_from = trim($_GET['date_from'] ?? '');
        $date_to = trim($_GET['date_to'] ?? '');

        $sql = "SELECT m.Module_ID, m.Module_Name, m.Description, m.Sort_Order, m.Is_Archived, m.Archived_At,
                       c.Course_ID, c.Course_Name,
                       (SELECT COUNT(*) FROM tbl_subject sub
                        WHERE sub.Module_ID = m.Module_ID
                          AND (sub.Is_Archived = 0 OR sub.Is_Archived IS NULL)) AS active_subjects
                FROM tbl_module m
                INNER JOIN tbl_course c ON m.Course_ID = c.Course_ID
                WHERE m.Is_Archived = 1";
        $params = [];

        if ($course_id) {
            $sql .= ' AND m.Course_ID = ?';
            $params[] = $course_id;
        }
        if ($search !== '') {
            $sql .= ' AND (m.Module_Name LIKE ? OR m.Description LIKE ? OR c.Course_Name LIKE ?)';
            $like = '%' . $search . '%';
            $params = array_merge($params, [$like, $like, $like]);
        }
        if ($date_from !== '') {
            $sql .= ' AND DATE(m.Archived_At) >= ?';
            $params[] = $date_from;
        }
        if ($date_to !== '') {
            $sql .= ' AND DATE(m.Archived_At) <= ?';
            $params[] = $date_to;
        }

        $sql .= ' ORDER BY m.Archived_At DESC, c.Course_Name ASC, m.Sort_Order ASC, m.Module_Name ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($modules as &$module) {
            appendModuleRetentionMeta($module, $retentionDays);
        }
        unset($module);

        echo json_encode(['success' => true, 'data' => $modules, 'retention_days' => $retentionDays]);
        exit;
    }

    if ($request_method === 'GET' && $action === 'list_subjects') {
        if (!archiveColumnExists($pdo, 'tbl_subject')) {
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'Subject archive is not available. Run the curriculum archive migration.']);
            exit;
        }

        $search = trim($_GET['search'] ?? '');
        $course_id = $_GET['course_id'] ?? null;
        $date_from = trim($_GET['date_from'] ?? '');
        $date_to = trim($_GET['date_to'] ?? '');
        $curriculum = subjectCurriculumJoin($pdo);

        $sql = "SELECT s.Subject_ID, s.Subject_Name, s.Subject_Code, s.Description, s.Is_Archived, s.Archived_At,
                       {$curriculum['module_select']}
                       {$curriculum['course_id_select']} AS Course_ID,
                       c.Course_Name,
                       (SELECT COUNT(*) FROM tbl_exam e WHERE e.Subject_ID = s.Subject_ID) AS exam_count,
                       (SELECT COUNT(*) FROM tbl_question_bank q
                        WHERE q.Subject_ID = s.Subject_ID
                          AND (q.Is_Archived = 0 OR q.Is_Archived IS NULL)) AS active_questions
                FROM tbl_subject s
                {$curriculum['join']}
                WHERE s.Is_Archived = 1";
        $params = [];

        if ($course_id) {
            $sql .= " AND {$curriculum['course_filter']} = ?";
            $params[] = $course_id;
        }
        if ($search !== '') {
            $like = '%' . $search . '%';
            if (moduleSchemaEnabled($pdo)) {
                $sql .= ' AND (s.Subject_Name LIKE ? OR s.Subject_Code LIKE ? OR c.Course_Name LIKE ? OR m.Module_Name LIKE ?)';
                $params = array_merge($params, [$like, $like, $like, $like]);
            } else {
                $sql .= ' AND (s.Subject_Name LIKE ? OR s.Subject_Code LIKE ? OR c.Course_Name LIKE ?)';
                $params = array_merge($params, [$like, $like, $like]);
            }
        }
        if ($date_from !== '') {
            $sql .= ' AND DATE(s.Archived_At) >= ?';
            $params[] = $date_from;
        }
        if ($date_to !== '') {
            $sql .= ' AND DATE(s.Archived_At) <= ?';
            $params[] = $date_to;
        }

        $sql .= ' ORDER BY s.Archived_At DESC, c.Course_Name ASC, s.Subject_Name ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($subjects as &$subject) {
            appendSubjectRetentionMeta($subject, $retentionDays);
        }
        unset($subject);

        echo json_encode(['success' => true, 'data' => $subjects, 'retention_days' => $retentionDays]);
        exit;
    }

    if ($request_method === 'GET' && $action === 'list_batches') {
        if (!archiveColumnExists($pdo, 'tbl_batch')) {
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'Batch archive is not available. Run the curriculum archive migration.']);
            exit;
        }

        $search = trim($_GET['search'] ?? '');
        $course_id = $_GET['course_id'] ?? null;

        $hasUserBatch = false;
        try {
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'tbl_user_batch'");
            $hasUserBatch = $tableCheck && $tableCheck->rowCount() > 0;
        } catch (Exception $e) {
            $hasUserBatch = false;
        }

        $enrollmentSelect = $hasUserBatch
            ? '(SELECT COUNT(*) FROM tbl_user_batch ub WHERE ub.Batch_ID = b.Batch_ID) AS enrollment_count'
            : '0 AS enrollment_count';

        $sql = "SELECT b.Batch_ID, b.Batch_Name, b.Date_Started, b.Date_Ended, b.Is_Archived, b.Archived_At,
                       b.Section_ID, c.Course_ID, c.Course_Name,
                       s.Section_Name,
                       (SELECT COUNT(*) FROM tbl_academic_section sec
                        WHERE sec.Batch_ID = b.Batch_ID
                          AND (sec.Is_Archived = 0 OR sec.Is_Archived IS NULL)) AS active_sections,
                       {$enrollmentSelect}
                FROM tbl_batch b
                INNER JOIN tbl_course c ON b.Course_ID = c.Course_ID
                LEFT JOIN tbl_academic_section s ON b.Section_ID = s.Section_ID
                WHERE b.Is_Archived = 1";
        $params = [];

        if ($course_id) {
            $sql .= ' AND b.Course_ID = ?';
            $params[] = $course_id;
        }
        if ($search !== '') {
            $sql .= ' AND (b.Batch_Name LIKE ? OR c.Course_Name LIKE ? OR s.Section_Name LIKE ?)';
            $like = '%' . $search . '%';
            $params = array_merge($params, [$like, $like, $like]);
        }

        $sql .= ' ORDER BY b.Archived_At DESC, b.Batch_Name ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($batches as &$batch) {
            appendBatchRetentionMeta($batch, $retentionDays);
        }
        unset($batch);

        echo json_encode(['success' => true, 'data' => $batches, 'retention_days' => $retentionDays]);
        exit;
    }

    if ($request_method === 'GET' && $action === 'list_sections') {
        if (!archiveColumnExists($pdo, 'tbl_academic_section')) {
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'Section archive is not available. Run the curriculum archive migration.']);
            exit;
        }

        $search = trim($_GET['search'] ?? '');
        $course_id = $_GET['course_id'] ?? null;

        $hasUserBatch = false;
        try {
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'tbl_user_batch'");
            $hasUserBatch = $tableCheck && $tableCheck->rowCount() > 0;
        } catch (Exception $e) {
            $hasUserBatch = false;
        }

        $enrollmentSelect = $hasUserBatch
            ? '(SELECT COUNT(DISTINCT ub.User_ID)
                FROM tbl_user_batch ub
                INNER JOIN tbl_batch eb ON ub.Batch_ID = eb.Batch_ID
                WHERE eb.Section_ID = sec.Section_ID) AS enrollment_count'
            : '0 AS enrollment_count';

        $sql = "SELECT sec.Section_ID, sec.Section_Name, sec.Capacity, sec.Is_Archived, sec.Archived_At,
                       b.Batch_ID, b.Batch_Name, c.Course_ID, c.Course_Name,
                       {$enrollmentSelect}
                FROM tbl_academic_section sec
                INNER JOIN tbl_batch b ON sec.Batch_ID = b.Batch_ID
                INNER JOIN tbl_course c ON b.Course_ID = c.Course_ID
                WHERE sec.Is_Archived = 1";
        $params = [];

        if ($course_id) {
            $sql .= ' AND c.Course_ID = ?';
            $params[] = $course_id;
        }
        if ($search !== '') {
            $sql .= ' AND (sec.Section_Name LIKE ? OR b.Batch_Name LIKE ? OR c.Course_Name LIKE ?)';
            $like = '%' . $search . '%';
            $params = array_merge($params, [$like, $like, $like]);
        }

        $sql .= ' ORDER BY sec.Archived_At DESC, sec.Section_Name ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($sections as &$section) {
            appendSectionRetentionMeta($section, $retentionDays);
        }
        unset($section);

        echo json_encode(['success' => true, 'data' => $sections, 'retention_days' => $retentionDays]);
        exit;
    }

    if ($request_method === 'GET' && $action === 'list_questions') {
        if (!archiveColumnExists($pdo, 'tbl_question_bank')) {
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'Question archive is not available. Run the user & question archive migration.']);
            exit;
        }

        $search = trim($_GET['search'] ?? '');
        $course_id = $_GET['course_id'] ?? null;
        $curriculum = subjectCurriculumJoin($pdo);

        $sql = "SELECT q.Question_ID, q.Question_Text, q.Question_Type, q.Is_Archived, q.Archived_At,
                       s.Subject_Name, {$curriculum['module_select']}
                       {$curriculum['course_id_select']} AS Course_ID,
                       c.Course_Name,
                       (SELECT COUNT(DISTINCT eq.Exam_ID) FROM tbl_exam_question eq WHERE eq.Question_ID = q.Question_ID) AS exam_usage_count
                FROM tbl_question_bank q
                INNER JOIN tbl_subject s ON q.Subject_ID = s.Subject_ID
                {$curriculum['join']}
                WHERE q.Is_Archived = 1";
        $params = [];

        if ($course_id) {
            $sql .= " AND {$curriculum['course_filter']} = ?";
            $params[] = $course_id;
        }
        if ($search !== '') {
            $sql .= ' AND (q.Question_Text LIKE ? OR s.Subject_Name LIKE ? OR c.Course_Name LIKE ?)';
            $like = '%' . $search . '%';
            $params = array_merge($params, [$like, $like, $like]);
        }

        $sql .= ' ORDER BY q.Archived_At DESC, q.Question_ID DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($questions as &$question) {
            appendQuestionRetentionMeta($question, $retentionDays);
        }
        unset($question);

        echo json_encode(['success' => true, 'data' => $questions, 'retention_days' => $retentionDays]);
        exit;
    }

    if ($request_method === 'GET' && $action === 'list_users') {
        if (!archiveColumnExists($pdo, 'tbl_user')) {
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'User archive is not available. Run the user & question archive migration.']);
            exit;
        }

        $search = trim($_GET['search'] ?? '');
        $rankCol = personnelRankColumnExists($pdo) ? 'u.Personnel_Rank,' : '';

        $sql = "SELECT u.User_ID, u.Fullname, u.Email, u.Academic_Number, {$rankCol}
                       r.Role_Name, u.Status, u.Is_Archived, u.Archived_At,
                       (SELECT COUNT(*) FROM tbl_exam_session es WHERE es.User_ID = u.User_ID) AS Session_Count
                FROM tbl_user u
                LEFT JOIN tbl_role r ON u.Role_ID = r.Role_ID
                WHERE u.Is_Archived = 1";
        $params = [];

        if ($search !== '') {
            $sql .= ' AND (u.Fullname LIKE ? OR u.Email LIKE ? OR u.Academic_Number LIKE ?)';
            $like = '%' . $search . '%';
            $params = array_merge($params, [$like, $like, $like]);
        }

        $sql .= ' ORDER BY u.Archived_At DESC, u.Fullname ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($users as &$user) {
            appendUserRetentionMeta($user, $retentionDays);
        }
        unset($user);

        echo json_encode(['success' => true, 'data' => $users, 'retention_days' => $retentionDays]);
        exit;
    }

    if ($request_method === 'GET' && $action === 'purge_preview') {
        if ($userRole !== 'Admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Admin access required']);
            exit;
        }

        $days = max(ARCHIVE_RETENTION_DAYS_MIN, min(ARCHIVE_RETENTION_DAYS_MAX, (int)($_GET['days'] ?? $retentionDays)));
        $eligible = countEligibleForPurgeAll($pdo, $days);

        echo json_encode([
            'success' => true,
            'eligible_count' => $eligible['total'],
            'eligible_breakdown' => $eligible,
            'days' => $days,
        ]);
        exit;
    }

    if ($request_method === 'POST' && $action === 'purge') {
        if ($userRole !== 'Admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Admin access required']);
            exit;
        }

        $raw = json_decode(file_get_contents('php://input'), true) ?? [];
        $days = max(ARCHIVE_RETENTION_DAYS_MIN, min(ARCHIVE_RETENTION_DAYS_MAX, (int)($raw['days'] ?? $_POST['days'] ?? $retentionDays)));

        $results = purgeAllArchivedRecords($pdo, $days);
        $total = array_sum($results);

        if ($total > 0) {
            auditFromSession($pdo, 'SYSTEM', 'PURGE_ARCHIVED_RECORDS', json_encode($results), 'SUCCESS', null, null);
        }

        echo json_encode([
            'success' => true,
            'message' => $total > 0
                ? "Permanently removed {$total} archived record(s) older than {$days} days."
                : "No archived records eligible for purge (older than {$days} days).",
            'deleted' => $total,
            'breakdown' => $results,
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
