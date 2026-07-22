<?php
/**
 * Shared validation helpers for business rules and integrity checks.
 */

require_once __DIR__ . '/exam-session-utils.php';

const EXAM_DEADLINE_GRACE_SECONDS = 0;

/**
 * Parse datetimes stored from datetime-local / MySQL (space or "T" separator).
 */
function parseStoredDateTimeTimestamp($value): ?int
{
    if ($value === null || trim((string)$value) === '') {
        return null;
    }

    $normalized = str_replace('T', ' ', trim((string)$value));
    $ts = strtotime($normalized);

    return $ts === false ? null : $ts;
}

/**
 * Normalize incoming schedule/deadline values for tbl_exam datetime columns.
 */
function normalizeStoredDateTimeInput(?string $value): ?string
{
    $ts = parseStoredDateTimeTimestamp($value);
    if ($ts === null) {
        return null;
    }

    return date('Y-m-d H:i:s', $ts);
}

function normalizeEntityName(?string $name): string
{
    return strtolower(trim((string)$name));
}

function courseNameExists(PDO $pdo, string $courseName, ?int $excludeCourseId = null): bool
{
    $sql = 'SELECT Course_ID FROM tbl_course WHERE LOWER(TRIM(Course_Name)) = ?';
    $params = [normalizeEntityName($courseName)];

    if ($excludeCourseId > 0) {
        $sql .= ' AND Course_ID != ?';
        $params[] = $excludeCourseId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (bool)$stmt->fetch();
}

function batchNameExistsInCourse(PDO $pdo, int $courseId, string $batchName, ?int $excludeBatchId = null): bool
{
    $sql = '
        SELECT Batch_ID FROM tbl_batch
        WHERE Course_ID = ?
          AND LOWER(TRIM(Batch_Name)) = ?
          AND (Section_ID IS NULL OR Section_ID = 0)
    ';
    $params = [$courseId, normalizeEntityName($batchName)];

    if ($excludeBatchId > 0) {
        $sql .= ' AND Batch_ID != ?';
        $params[] = $excludeBatchId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (bool)$stmt->fetch();
}

function sectionNameExistsInBatch(PDO $pdo, int $batchId, string $sectionName, ?int $excludeSectionId = null): bool
{
    $sql = '
        SELECT Section_ID FROM tbl_academic_section
        WHERE Batch_ID = ?
          AND LOWER(TRIM(Section_Name)) = ?
    ';
    $params = [$batchId, normalizeEntityName($sectionName)];

    if ($excludeSectionId > 0) {
        $sql .= ' AND Section_ID != ?';
        $params[] = $excludeSectionId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (bool)$stmt->fetch();
}

function moduleNameExistsInCourse(PDO $pdo, int $courseId, string $moduleName, ?int $excludeModuleId = null): bool
{
    $sql = '
        SELECT Module_ID FROM tbl_module
        WHERE Course_ID = ?
          AND LOWER(TRIM(Module_Name)) = ?
    ';
    $params = [$courseId, normalizeEntityName($moduleName)];

    if ($excludeModuleId > 0) {
        $sql .= ' AND Module_ID != ?';
        $params[] = $excludeModuleId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (bool)$stmt->fetch();
}

function moduleNameExistsInCourseSafe(PDO $pdo, int $courseId, string $moduleName, ?int $excludeModuleId = null): bool
{
    try {
        return moduleNameExistsInCourse($pdo, $courseId, $moduleName, $excludeModuleId);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * @return array{valid:bool, message?:string, session?:array, exam?:array, time_started?:string}
 */
function loadActiveExamSessionContext(PDO $pdo, int $sessionId, int $userId): array
{
    $stmt = $pdo->prepare('
        SELECT
            es.Session_ID,
            es.Exam_ID,
            es.User_ID,
            es.Time_Started,
            es.Time_Ended,
            e.Title,
            e.Status,
            e.Schedule_Date,
            e.Deadline,
            e.Duration,
            e.Is_Archived
        FROM tbl_exam_session es
        INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
        WHERE es.Session_ID = ?
    ');
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        return ['valid' => false, 'message' => 'Invalid session', 'status' => 404];
    }

    if ((int)$session['User_ID'] !== $userId) {
        return ['valid' => false, 'message' => 'Invalid session', 'status' => 403];
    }

    if (!empty($session['Time_Ended'])) {
        return ['valid' => false, 'message' => 'Exam session has already ended', 'status' => 400];
    }

    if (($session['Status'] ?? '') !== 'Published') {
        return ['valid' => false, 'message' => 'This exam is not available', 'status' => 400];
    }

    if (!empty($session['Is_Archived'])) {
        return ['valid' => false, 'message' => 'This exam is no longer available', 'status' => 403];
    }

    $scheduleError = validateExamScheduleStarted($session);
    if ($scheduleError !== null) {
        return ['valid' => false, 'message' => $scheduleError, 'status' => 403];
    }

    $deadlineError = validateExamDeadlineWithGrace($session);
    if ($deadlineError !== null) {
        return ['valid' => false, 'message' => $deadlineError, 'status' => 403];
    }

    $examId = (int)$session['Exam_ID'];
    $duration = (int)($session['Duration'] ?? 0);
    $timeStarted = reviveExpiredEmptySession(
        $pdo,
        $sessionId,
        $examId,
        $session['Time_Started'],
        $duration
    );

    if (isSessionDurationExpired($timeStarted, $duration)) {
        return ['valid' => false, 'message' => 'Exam time has expired', 'status' => 403];
    }

    return [
        'valid' => true,
        'session' => $session,
        'exam' => $session,
        'time_started' => $timeStarted,
    ];
}

function validateExamScheduleStarted(array $exam): ?string
{
    $scheduleTs = parseStoredDateTimeTimestamp($exam['Schedule_Date'] ?? null);
    if ($scheduleTs === null) {
        return null;
    }

    if (time() < $scheduleTs) {
        return 'This exam is not yet available. Please wait until the scheduled start time.';
    }

    return null;
}

function validateExamDeadlineWithGrace(array $exam, ?int $graceSeconds = null): ?string
{
    $deadlineTs = parseStoredDateTimeTimestamp($exam['Deadline'] ?? null);
    if ($deadlineTs === null) {
        return null;
    }

    $grace = $graceSeconds ?? EXAM_DEADLINE_GRACE_SECONDS;

    if (time() > ($deadlineTs + $grace)) {
        return 'This exam deadline has passed';
    }

    return null;
}

function questionBelongsToExam(PDO $pdo, int $questionId, int $examId): bool
{
    $stmt = $pdo->prepare('
        SELECT 1 FROM tbl_exam_question
        WHERE Exam_ID = ? AND Question_ID = ?
        LIMIT 1
    ');
    $stmt->execute([$examId, $questionId]);

    return (bool)$stmt->fetchColumn();
}

function choiceBelongsToQuestion(PDO $pdo, int $choiceId, int $questionId): bool
{
    $stmt = $pdo->prepare('
        SELECT 1 FROM tbl_choice
        WHERE Choice_ID = ? AND Question_ID = ?
        LIMIT 1
    ');
    $stmt->execute([$choiceId, $questionId]);

    return (bool)$stmt->fetchColumn();
}

function sectionHasEnrolledTrainees(PDO $pdo, int $sectionId): int
{
    try {
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'tbl_user_batch'");
        if (!$tableCheck || $tableCheck->rowCount() === 0) {
            return 0;
        }

        $stmt = $pdo->prepare('
            SELECT COUNT(DISTINCT ub.User_ID)
            FROM tbl_user_batch ub
            INNER JOIN tbl_batch b ON ub.Batch_ID = b.Batch_ID
            WHERE b.Section_ID = ?
        ');
        $stmt->execute([$sectionId]);

        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function courseHasActiveBatches(PDO $pdo, int $courseId): int
{
    $stmt = $pdo->prepare('
        SELECT COUNT(*)
        FROM tbl_batch
        WHERE Course_ID = ?
          AND (Section_ID IS NULL OR Section_ID = 0)
    ');
    $stmt->execute([$courseId]);

    return (int)$stmt->fetchColumn();
}

function batchHasActiveSections(PDO $pdo, int $batchId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM tbl_academic_section WHERE Batch_ID = ?');
    $stmt->execute([$batchId]);

    return (int)$stmt->fetchColumn();
}

function subjectHasLinkedExams(PDO $pdo, int $subjectId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM tbl_exam WHERE Subject_ID = ?');
    $stmt->execute([$subjectId]);

    return (int)$stmt->fetchColumn();
}

function subjectHasLinkedQuestions(PDO $pdo, int $subjectId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM tbl_question_bank WHERE Subject_ID = ?');
    $stmt->execute([$subjectId]);

    return (int)$stmt->fetchColumn();
}

function examHasActiveSessions(PDO $pdo, int $examId): int
{
    $stmt = $pdo->prepare('
        SELECT COUNT(*)
        FROM tbl_exam_session
        WHERE Exam_ID = ?
          AND Time_Ended IS NULL
    ');
    $stmt->execute([$examId]);

    return (int)$stmt->fetchColumn();
}

function examHasPublishedStatus(PDO $pdo, int $examId): bool
{
    $stmt = $pdo->prepare('SELECT Status FROM tbl_exam WHERE Exam_ID = ?');
    $stmt->execute([$examId]);
    $status = $stmt->fetchColumn();

    return $status === 'Published';
}

function detectDuplicateSectionIds(array $sectionIds): ?string
{
    $seen = [];
    foreach ($sectionIds as $sectionId) {
        $sectionId = (int)$sectionId;
        if ($sectionId <= 0) {
            continue;
        }
        if (isset($seen[$sectionId])) {
            return 'This section is already assigned to the exam';
        }
        $seen[$sectionId] = true;
    }

    return null;
}

function formatSectionCapacityMessage(int $capacity, int $assignedCount): string
{
    return "Section is full ({$assignedCount}/{$capacity}).";
}
