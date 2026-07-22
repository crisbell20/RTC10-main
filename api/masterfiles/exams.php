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
require_once __DIR__ . '/../includes/module-utils.php';
require_once __DIR__ . '/../includes/exam-assignment-utils.php';
require_once __DIR__ . '/../includes/exam-participant-utils.php';
require_once __DIR__ . '/../includes/question-publish-utils.php';
require_once __DIR__ . '/../includes/exam-type-utils.php';
require_once __DIR__ . '/../includes/validation-utils.php';
require_once __DIR__ . '/../includes/exam-deadline-finalize-utils.php';

$request_method = $_SERVER['REQUEST_METHOD'];
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? [];
$action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? '');
$hasExamCode = examCodeColumnsExist($pdo);
$hasResponseReview = responseReviewColumnExists($pdo);
$userRole = $_SESSION['user_role'] ?? '';

function requireAdminRole(): void {
    global $userRole;
    if ($userRole !== 'Admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only administrators can perform this action']);
        exit;
    }
}

/**
 * GROUP BY fields for exam list queries (MySQL ONLY_FULL_GROUP_BY safe).
 */
function examListGroupByFields(PDO $pdo, bool $hasExamCode, bool $hasResponseReview): string
{
    $curriculum = subjectCurriculumJoin($pdo);
    $fields = [
        'e.Exam_ID',
        'e.User_ID',
        'e.Subject_ID',
        'e.Title',
        'e.Description',
        'e.Schedule_Date',
        'e.Deadline',
        'e.Duration',
        'e.Passing_Score',
        'e.Status',
        'e.Is_Randomized',
        'e.Time_Limit',
        's.Subject_Name',
        's.Subject_Code',
        'c.Course_Name',
        $curriculum['course_id_select'],
    ];

    if (examTypeColumnExists($pdo)) {
        $fields[] = 'e.Exam_Type';
    }

    if (moduleSchemaEnabled($pdo)) {
        $fields[] = 'm.Module_ID';
        $fields[] = 'm.Module_Name';
    }

    if ($hasExamCode) {
        $fields = array_merge($fields, [
            'e.Exam_Code',
            'e.Exam_Code_Generated_At',
            'e.Exam_Code_Reset_Count',
            'e.Is_Archived',
            'e.Archived_At',
        ]);
    }

    if ($hasResponseReview) {
        $fields[] = 'e.Allow_Response_Review';
    }

    return implode(', ', $fields);
}

/**
 * Validate required exam scheduling/scoring fields.
 * Returns error message string or null when valid.
 */
function validateExamCoreFields($deadline, $duration, $passing_score, $schedule_date = null): ?string {
    if ($deadline === null || $deadline === '') {
        return 'Deadline is required';
    }

    if ($duration === null || $duration === '' || (int)$duration <= 0) {
        return 'Duration must be greater than 0 minutes';
    }

    if ($passing_score === null || $passing_score === '' || !is_numeric($passing_score)) {
        return 'Passing score is required';
    }

    $passing = (float)$passing_score;
    if ($passing < 0 || $passing > 100) {
        return 'Passing score must be between 0 and 100';
    }

    if ($schedule_date && $deadline && strtotime($deadline) < strtotime($schedule_date)) {
        return 'Due date must not be earlier than the schedule date';
    }

    return null;
}

try {
    try {
        runOverdueExamFinalizationOnce($pdo);
    } catch (Exception $e) {
        error_log('Auto-finalize exams on list: ' . $e->getMessage());
    }

    // List exams, optional filters: course_id, subject_id
    if ($request_method === 'GET' && $action === 'list') {
        $course_id = $_GET['course_id'] ?? null;
        $subject_id = $_GET['subject_id'] ?? null;
        $module_id = $_GET['module_id'] ?? null;
        $archived_only = ($_GET['archived_only'] ?? '') === '1';
        $curriculum = subjectCurriculumJoin($pdo);

        $codeColumns = $hasExamCode
            ? 'e.Exam_Code, e.Exam_Code_Generated_At, e.Exam_Code_Reset_Count, e.Is_Archived, e.Archived_At,'
            : '';
        $reviewColumn = $hasResponseReview ? 'e.Allow_Response_Review,' : '';

        $examTypeSelect = examTypeColumnExists($pdo) ? 'e.Exam_Type,' : "'MODULAR' AS Exam_Type,";

        $sql = "
            SELECT e.Exam_ID, e.User_ID, e.Subject_ID, e.Title, {$examTypeSelect} e.Description,
                   e.Schedule_Date, e.Deadline, e.Duration, e.Passing_Score, e.Status,
                   e.Is_Randomized, e.Time_Limit,
                   {$codeColumns}{$reviewColumn}
                   s.Subject_Name, s.Subject_Code,
                   {$curriculum['module_select']}
                   {$curriculum['course_id_select']} AS Course_ID,
                   c.Course_Name,
                   COUNT(DISTINCT eq.Question_ID) as Question_Count,
                   COUNT(DISTINCT eb.Batch_ID) as Batch_Count,
                   COUNT(DISTINCT es.Session_ID) as Session_Count,
                   COUNT(DISTINCT CASE WHEN es.Time_Ended IS NOT NULL THEN es.Session_ID END) as Finished_Count
            FROM tbl_exam e
            INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
            {$curriculum['join']}
            LEFT JOIN tbl_exam_question eq ON e.Exam_ID = eq.Exam_ID
            LEFT JOIN tbl_exam_batch eb ON e.Exam_ID = eb.Exam_ID
            LEFT JOIN tbl_exam_session es ON e.Exam_ID = es.Exam_ID
            WHERE 1=1
        ";
        $params = [];

        if ($hasExamCode) {
            if ($archived_only) {
                $sql .= ' AND e.Is_Archived = 1';
            } else {
                $sql .= ' AND (e.Is_Archived = 0 OR e.Is_Archived IS NULL)';
            }
        }

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

        $groupFields = examListGroupByFields($pdo, $hasExamCode, $hasResponseReview);
        $sql .= " GROUP BY {$groupFields}";
        $sql .= ' ORDER BY e.Exam_ID DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

        attachExamAssignments($pdo, $exams);
        ensureExamAssignmentsFromParticipants($pdo, $exams);
        attachExamParticipantCounts($pdo, $exams);

        if ($hasExamCode) {
            foreach ($exams as &$exam) {
                if (empty($exam['Exam_Code'])) {
                    $code = generateUniqueExamCode($pdo);
                    $upd = $pdo->prepare('
                        UPDATE tbl_exam
                        SET Exam_Code = ?, Exam_Code_Generated_At = NOW()
                        WHERE Exam_ID = ?
                    ');
                    $upd->execute([$code, $exam['Exam_ID']]);
                    $exam['Exam_Code'] = $code;
                    $exam['Exam_Code_Generated_At'] = date('Y-m-d H:i:s');
                }
            }
            unset($exam);
        }

        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $exams]);
        exit;
    }

    // Add exam
    if ($request_method === 'POST' && $action === 'add') {
        requireAdminRole();
        $subject_id = $input['subject_id'] ?? $_POST['subject_id'] ?? '';
        $title = $input['title'] ?? $_POST['title'] ?? '';
        $typeResult = resolveExamTypeForSave($pdo, $input, $title, null);
        if ($typeResult['error'] !== null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $typeResult['error']]);
            exit;
        }
        $exam_type = $typeResult['exam_type'];
        $description = $input['description'] ?? $_POST['description'] ?? null;
        $schedule_date = $input['schedule_date'] ?? $_POST['schedule_date'] ?? null;
        $deadline = $input['deadline'] ?? $_POST['deadline'] ?? null;
        $schedule_date = normalizeStoredDateTimeInput($schedule_date);
        $deadline = normalizeStoredDateTimeInput($deadline);
        $duration = $input['duration'] ?? $_POST['duration'] ?? null;
        $passing_score = $input['passing_score'] ?? $_POST['passing_score'] ?? null;
        $status = $input['status'] ?? $_POST['status'] ?? 'Draft';
        $is_randomized = (int)($input['is_randomized'] ?? $_POST['is_randomized'] ?? 0);
        $time_limit = $input['time_limit'] ?? $_POST['time_limit'] ?? null;
        $user_id = $_SESSION['user_id'];

        if (empty($subject_id) || empty($title)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Subject and title are required']);
            exit;
        }

        $coreError = validateExamCoreFields($deadline, $duration, $passing_score, $schedule_date);
        if ($coreError !== null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $coreError]);
            exit;
        }

        $examCode = null;
        $hasExamType = examTypeColumnExists($pdo);
        if ($hasExamCode) {
            $examCode = generateUniqueExamCode($pdo);
            $typeColumn = $hasExamType ? 'Exam_Type, ' : '';
            $typePlaceholder = $hasExamType ? '?, ' : '';
            $stmt = $pdo->prepare('
                INSERT INTO tbl_exam
                (User_ID, Subject_ID, Title, ' . ($hasExamType ? 'Exam_Type, ' : '') . 'Description, Schedule_Date, Deadline, Duration,
                 Passing_Score, Status, Is_Randomized, Time_Limit, Exam_Code, Exam_Code_Generated_At)
                VALUES (?, ?, ?, ' . ($hasExamType ? '?, ' : '') . '?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ');
            $params = [
                $user_id,
                $subject_id,
                $title,
            ];
            if ($hasExamType) {
                $params[] = $exam_type;
            }
            $params = array_merge($params, [
                $description,
                $schedule_date ?: null,
                $deadline ?: null,
                $duration ?: null,
                $passing_score ?: null,
                $status,
                $is_randomized,
                $time_limit ?: null,
                $examCode,
            ]);
            $stmt->execute($params);
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO tbl_exam
                (User_ID, Subject_ID, Title, ' . ($hasExamType ? 'Exam_Type, ' : '') . 'Description, Schedule_Date, Deadline, Duration,
                 Passing_Score, Status, Is_Randomized, Time_Limit)
                VALUES (?, ?, ?, ' . ($hasExamType ? '?, ' : '') . '?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $params = [
                $user_id,
                $subject_id,
                $title,
            ];
            if ($hasExamType) {
                $params[] = $exam_type;
            }
            $params = array_merge($params, [
                $description,
                $schedule_date ?: null,
                $deadline ?: null,
                $duration ?: null,
                $passing_score ?: null,
                $status,
                $is_randomized,
                $time_limit ?: null,
            ]);
            $stmt->execute($params);
        }

        $examId = (int)$pdo->lastInsertId();
        logExamAuditAction($pdo, (int)$user_id, 'CREATE_EXAM', "Exam \"{$title}\" created");

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Exam added',
            'exam_id' => $examId,
            'exam_code' => $examCode,
            'exam_type' => $exam_type,
        ]);
        exit;
    }

    // Update exam
    if ($request_method === 'POST' && $action === 'update') {
        requireAdminRole();
        $exam_id = $input['exam_id'] ?? $_POST['exam_id'] ?? '';
        $subject_id = $input['subject_id'] ?? $_POST['subject_id'] ?? '';
        $title = $input['title'] ?? $_POST['title'] ?? '';
        $examIdInt = is_numeric($exam_id) ? (int)$exam_id : 0;
        $typeResult = resolveExamTypeForSave($pdo, $input, $title, $examIdInt > 0 ? $examIdInt : null);
        if ($typeResult['error'] !== null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $typeResult['error']]);
            exit;
        }
        $exam_type = $typeResult['exam_type'];
        $description = $input['description'] ?? $_POST['description'] ?? null;
        $schedule_date = $input['schedule_date'] ?? $_POST['schedule_date'] ?? null;
        $deadline = $input['deadline'] ?? $_POST['deadline'] ?? null;
        $schedule_date = normalizeStoredDateTimeInput($schedule_date);
        $deadline = normalizeStoredDateTimeInput($deadline);
        $duration = $input['duration'] ?? $_POST['duration'] ?? null;
        $passing_score = $input['passing_score'] ?? $_POST['passing_score'] ?? null;
        $status = $input['status'] ?? $_POST['status'] ?? 'Draft';
        $is_randomized = (int)($input['is_randomized'] ?? $_POST['is_randomized'] ?? 0);
        $time_limit = $input['time_limit'] ?? $_POST['time_limit'] ?? null;

        if (empty($exam_id) || empty($subject_id) || empty($title)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Exam ID, subject and title are required']);
            exit;
        }

        $coreError = validateExamCoreFields($deadline, $duration, $passing_score, $schedule_date);
        if ($coreError !== null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $coreError]);
            exit;
        }

        if ($hasExamCode) {
            $archivedCheck = $pdo->prepare('SELECT Is_Archived FROM tbl_exam WHERE Exam_ID = ?');
            $archivedCheck->execute([$exam_id]);
            $archivedRow = $archivedCheck->fetch(PDO::FETCH_ASSOC);
            if ($archivedRow && (int)$archivedRow['Is_Archived'] === 1) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Archived exams cannot be edited. Restore it first.']);
                exit;
            }
        }

        $prevStatusStmt = $pdo->prepare('SELECT Status FROM tbl_exam WHERE Exam_ID = ?');
        $prevStatusStmt->execute([$exam_id]);
        $previousStatus = (string)($prevStatusStmt->fetchColumn() ?: '');

        if ($status === 'Published') {
            $stmt = $pdo->prepare('SELECT COUNT(*) as question_count FROM tbl_exam_question WHERE Exam_ID = ?');
            $stmt->execute([$exam_id]);
            $question_count = $stmt->fetch(PDO::FETCH_ASSOC)['question_count'];

            if ($question_count == 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Cannot publish exam without questions']);
                exit;
            }

            $stmt = $pdo->prepare('SELECT COUNT(*) as batch_count FROM tbl_exam_batch WHERE Exam_ID = ?');
            $stmt->execute([$exam_id]);
            $batch_count = $stmt->fetch(PDO::FETCH_ASSOC)['batch_count'];

            if ($batch_count == 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Cannot publish exam without batch assignments']);
                exit;
            }

            if (examParticipantTableExists($pdo)) {
                $participant_count = countExamParticipants($pdo, (int)$exam_id);
                if ($participant_count === 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Cannot publish exam without at least one selected participant']);
                    exit;
                }
            }
        }

        $hasExamType = examTypeColumnExists($pdo);
        $examTypeSet = $hasExamType ? "Exam_Type = ?,\n                   " : '';
        $stmt = $pdo->prepare('
            UPDATE tbl_exam
               SET Subject_ID = ?,
                   Title = ?,
                   ' . $examTypeSet . 'Description = ?,
                   Schedule_Date = ?,
                   Deadline = ?,
                   Duration = ?,
                   Passing_Score = ?,
                   Status = ?,
                   Is_Randomized = ?,
                   Time_Limit = ?
             WHERE Exam_ID = ?
        ');
        $updateParams = [$subject_id, $title];
        if ($hasExamType) {
            $updateParams[] = $exam_type;
        }
        $updateParams = array_merge($updateParams, [
            $description,
            $schedule_date ?: null,
            $deadline ?: null,
            $duration ?: null,
            $passing_score ?: null,
            $status,
            $is_randomized,
            $time_limit ?: null,
            $exam_id,
        ]);
        $stmt->execute($updateParams);

        $sessionStats = null;
        if ($status === 'Published' && in_array($previousStatus, ['Closed', 'Draft'], true)) {
            $sessionStats = prepareExamSessionsForReopen($pdo, (int)$exam_id);
        }

        logExamAuditAction($pdo, (int)$_SESSION['user_id'], 'UPDATE_EXAM', "Exam \"{$title}\" updated");

        $promotedCount = 0;
        if ($status === 'Published') {
            $promotedCount = promoteExamDraftQuestions($pdo, (int)$exam_id);
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $status === 'Published' && $promotedCount > 0
                ? "Exam updated. {$promotedCount} draft question(s) added to the question bank."
                : 'Exam updated',
            'promoted_questions' => $promotedCount,
            'exam_type' => $exam_type,
            'session_stats' => $sessionStats,
        ]);
        exit;
    }

    // Reset exam access code
    if ($request_method === 'POST' && $action === 'reset_code') {
        requireAdminRole();
        if (!$hasExamCode) {
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'Exam code feature is not available. Run the database migration.']);
            exit;
        }

        $exam_id = $input['exam_id'] ?? $_POST['exam_id'] ?? '';
        if (empty($exam_id)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Exam ID is required']);
            exit;
        }

        $examCode = generateUniqueExamCode($pdo);
        $stmt = $pdo->prepare('
            UPDATE tbl_exam
            SET Exam_Code = ?,
                Exam_Code_Generated_At = NOW(),
                Exam_Code_Reset_Count = Exam_Code_Reset_Count + 1
            WHERE Exam_ID = ?
        ');
        $stmt->execute([$examCode, $exam_id]);

        $examLabel = auditExamLabel($pdo, (int)$exam_id);
        logExamAuditAction($pdo, (int)$_SESSION['user_id'], 'RESET_EXAM_CODE', "Exam code reset for \"{$examLabel}\"");

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Exam code reset successfully',
            'exam_code' => $examCode
        ]);
        exit;
    }

    // Archive exam
    if ($request_method === 'POST' && $action === 'archive') {
        requireAdminRole();
        $exam_id = $input['exam_id'] ?? $_POST['exam_id'] ?? '';
        if (empty($exam_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Exam ID is required']);
            exit;
        }

        $activeSessions = examHasActiveSessions($pdo, (int)$exam_id);
        if ($activeSessions > 0) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => "Cannot archive exam while {$activeSessions} examinee session(s) are still in progress.",
            ]);
            exit;
        }

        if ($hasExamCode) {
            $stmt = $pdo->prepare('
                UPDATE tbl_exam
                SET Is_Archived = 1,
                    Archived_At = NOW(),
                    Status = CASE WHEN Status = \'Published\' THEN \'Closed\' ELSE Status END
                WHERE Exam_ID = ?
            ');
        } else {
            $stmt = $pdo->prepare("UPDATE tbl_exam SET Status = 'Closed' WHERE Exam_ID = ?");
        }
        $stmt->execute([$exam_id]);

        $examLabel = auditExamLabel($pdo, (int)$exam_id);
        logExamAuditAction($pdo, (int)$_SESSION['user_id'], 'ARCHIVE_EXAM', "Exam \"{$examLabel}\" archived");

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Exam archived successfully']);
        exit;
    }

    // Restore archived exam (Admin or CCMD)
    if ($request_method === 'POST' && $action === 'restore') {
        if (!$hasExamCode) {
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'Archive feature is not available. Run the database migration.']);
            exit;
        }

        $exam_id = $input['exam_id'] ?? $_POST['exam_id'] ?? '';
        if (empty($exam_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Exam ID is required']);
            exit;
        }

        $stmt = $pdo->prepare('
            UPDATE tbl_exam
            SET Is_Archived = 0,
                Archived_At = NULL
            WHERE Exam_ID = ?
        ');
        $stmt->execute([$exam_id]);

        $examLabel = auditExamLabel($pdo, (int)$exam_id);
        logExamAuditAction($pdo, (int)$_SESSION['user_id'], 'RESTORE_EXAM', "Exam \"{$examLabel}\" restored");

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Exam restored successfully']);
        exit;
    }

    // Reopen a closed exam and reset stale examinee sessions
    if ($request_method === 'POST' && $action === 'reopen') {
        requireAdminRole();
        $exam_id = $input['exam_id'] ?? $_POST['exam_id'] ?? '';
        if (empty($exam_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Exam ID is required']);
            exit;
        }

        $stmt = $pdo->prepare('
            SELECT Exam_ID, Title, Subject_ID, Description, Duration, Passing_Score, Is_Randomized, Deadline, Status
            FROM tbl_exam
            WHERE Exam_ID = ?
        ');
        $stmt->execute([$exam_id]);
        $exam = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$exam) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Exam not found']);
            exit;
        }

        $deadline = trim((string)($input['deadline'] ?? ''));
        if ($deadline === '') {
            $deadline = $exam['Deadline'] ?? '';
        }

        if ($deadline === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'A deadline is required to reopen this exam']);
            exit;
        }

        $deadlineTs = strtotime($deadline);
        if ($deadlineTs === false) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid deadline format']);
            exit;
        }

        if ($deadlineTs <= time()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Deadline must be in the future when reopening an exam']);
            exit;
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare('
            UPDATE tbl_exam
            SET Status = \'Published\',
                Schedule_Date = NOW(),
                Deadline = ?
            WHERE Exam_ID = ?
        ');
        $stmt->execute([date('Y-m-d H:i:s', $deadlineTs), $exam_id]);

        $sessionStats = prepareExamSessionsForReopen($pdo, (int)$exam_id);

        $pdo->commit();

        $examLabel = auditExamLabel($pdo, (int)$exam_id);
        logExamAuditAction(
            $pdo,
            (int)$_SESSION['user_id'],
            'REOPEN_EXAM',
            "Exam \"{$examLabel}\" reopened ({$sessionStats['active_reset']} in-progress reset, "
            . "{$sessionStats['active_removed']} empty in-progress removed, "
            . "{$sessionStats['void_removed']} no-answer attempt(s) cleared)"
        );

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Exam reopened successfully. No-answer failures and empty in-progress attempts were cleared; other in-progress attempts were reset.',
            'session_stats' => $sessionStats,
        ]);
        exit;
    }

    // Close exam
    if ($request_method === 'POST' && $action === 'close') {
        requireAdminRole();
        $exam_id = $input['exam_id'] ?? $_POST['exam_id'] ?? '';
        if (empty($exam_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Exam ID is required']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE tbl_exam SET Status = 'Closed' WHERE Exam_ID = ?");
        $stmt->execute([$exam_id]);

        $examLabel = auditExamLabel($pdo, (int)$exam_id);
        logExamAuditAction($pdo, (int)$_SESSION['user_id'], 'CLOSE_EXAM', "Exam \"{$examLabel}\" closed");

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Exam closed successfully']);
        exit;
    }

    // Toggle response review (examinee question breakdown after submission)
    if ($request_method === 'POST' && $action === 'toggle_response_review') {
        requireAdminRole();
        if (!$hasResponseReview) {
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'Response review feature is not available. Run the database migration.']);
            exit;
        }

        $exam_id = $input['exam_id'] ?? $_POST['exam_id'] ?? '';
        if (empty($exam_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Exam ID is required']);
            exit;
        }

        $stmt = $pdo->prepare('SELECT Allow_Response_Review FROM tbl_exam WHERE Exam_ID = ?');
        $stmt->execute([$exam_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Exam not found']);
            exit;
        }

        $newValue = (int)!((int)$row['Allow_Response_Review']);
        $upd = $pdo->prepare('UPDATE tbl_exam SET Allow_Response_Review = ? WHERE Exam_ID = ?');
        $upd->execute([$newValue, $exam_id]);

        $examLabel = auditExamLabel($pdo, (int)$exam_id);
        $label = $newValue ? 'enabled' : 'disabled';
        logExamAuditAction($pdo, (int)$_SESSION['user_id'], 'TOGGLE_RESPONSE_REVIEW', "Response review {$label} for exam \"{$examLabel}\"");

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $newValue ? 'Response review enabled' : 'Response review disabled',
            'allow_response_review' => $newValue
        ]);
        exit;
    }

    // Delete exam — disabled; use archive instead
    if ($request_method === 'POST' && $action === 'delete') {
        requireAdminRole();
        require_once __DIR__ . '/../includes/archive-utils.php';
        archiveDeletionBlockedResponse();
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
