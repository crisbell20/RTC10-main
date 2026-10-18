<?php

// Soft-archive helpers for masterfile entities.

const ARCHIVE_RETENTION_DAYS = 730; // 2 years
const ARCHIVE_RETENTION_DAYS_MIN = 10;
const ARCHIVE_RETENTION_DAYS_MAX = 3650;

function archiveColumnExists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE 'Is_Archived'");
        $cache[$table] = $stmt && $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $cache[$table] = false;
    }

    return $cache[$table];
}

function activeArchiveCondition(PDO $pdo, string $table, string $alias = ''): string
{
    if (!archiveColumnExists($pdo, $table)) {
        return '1=1';
    }

    $prefix = $alias !== '' ? "{$alias}." : '';
    return "({$prefix}Is_Archived = 0 OR {$prefix}Is_Archived IS NULL)";
}

function archivedOnlyCondition(PDO $pdo, string $table, string $alias = ''): string
{
    if (!archiveColumnExists($pdo, $table)) {
        return '0=1';
    }

    $prefix = $alias !== '' ? "{$alias}." : '';
    return "{$prefix}Is_Archived = 1";
}

// SQL AND-fragment: exam/subject/course/batch must all be non-archived
function examineeActiveCurriculumCondition(
    PDO $pdo,
    string $examAlias = 'e',
    string $subjectAlias = 's',
    string $courseAlias = 'c',
    string $batchAlias = 'b'
): string {
    return implode(' AND ', [
        activeArchiveCondition($pdo, 'tbl_exam', $examAlias),
        activeArchiveCondition($pdo, 'tbl_subject', $subjectAlias),
        activeArchiveCondition($pdo, 'tbl_course', $courseAlias),
        activeArchiveCondition($pdo, 'tbl_batch', $batchAlias),
    ]);
}

// SQL AND-fragment for examinee results/performance
function examineeActiveResultCurriculumCondition(
    PDO $pdo,
    string $examAlias = 'e',
    string $subjectAlias = 's',
    string $courseAlias = 'c'
): string {
    return implode(' AND ', [
        activeArchiveCondition($pdo, 'tbl_exam', $examAlias),
        activeArchiveCondition($pdo, 'tbl_subject', $subjectAlias),
        activeArchiveCondition($pdo, 'tbl_course', $courseAlias),
    ]);
}

function archiveRecord(PDO $pdo, string $table, string $idColumn, int $id): bool
{
    if (!archiveColumnExists($pdo, $table)) {
        return false;
    }

    $stmt = $pdo->prepare("UPDATE `{$table}` SET Is_Archived = 1, Archived_At = NOW() WHERE {$idColumn} = ? AND (Is_Archived = 0 OR Is_Archived IS NULL)");
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}

function restoreRecord(PDO $pdo, string $table, string $idColumn, int $id): bool
{
    if (!archiveColumnExists($pdo, $table)) {
        return false;
    }

    $stmt = $pdo->prepare("UPDATE `{$table}` SET Is_Archived = 0, Archived_At = NULL WHERE {$idColumn} = ? AND Is_Archived = 1");
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}


function archiveQuestionsForSubject(PDO $pdo, int $subjectId): int
{
    if ($subjectId <= 0 || !archiveColumnExists($pdo, 'tbl_question_bank') || !archiveColumnExists($pdo, 'tbl_subject')) {
        return 0;
    }

    $stmt = $pdo->prepare('
        UPDATE tbl_question_bank q
        INNER JOIN tbl_subject s ON s.Subject_ID = q.Subject_ID
        SET q.Is_Archived = 1,
            q.Archived_At = s.Archived_At
        WHERE q.Subject_ID = ?
          AND s.Is_Archived = 1
          AND (q.Is_Archived = 0 OR q.Is_Archived IS NULL)
    ');
    $stmt->execute([$subjectId]);

    return $stmt->rowCount();
}

function restoreQuestionsArchivedWithSubject(PDO $pdo, int $subjectId): int
{
    if ($subjectId <= 0 || !archiveColumnExists($pdo, 'tbl_question_bank') || !archiveColumnExists($pdo, 'tbl_subject')) {
        return 0;
    }

    $stampStmt = $pdo->prepare('SELECT Archived_At FROM tbl_subject WHERE Subject_ID = ? AND Is_Archived = 1 LIMIT 1');
    $stampStmt->execute([$subjectId]);
    $archivedAt = $stampStmt->fetchColumn();
    if (!$archivedAt) {
        return 0;
    }

    $stmt = $pdo->prepare('
        UPDATE tbl_question_bank
        SET Is_Archived = 0,
            Archived_At = NULL
        WHERE Subject_ID = ?
          AND Is_Archived = 1
          AND Archived_At = ?
    ');
    $stmt->execute([$subjectId, $archivedAt]);

    return $stmt->rowCount();
}

function archiveQuestionsUnderArchivedSubjects(PDO $pdo): int
{
    if (!archiveColumnExists($pdo, 'tbl_question_bank') || !archiveColumnExists($pdo, 'tbl_subject')) {
        return 0;
    }

    $stmt = $pdo->query('
        SELECT Subject_ID
        FROM tbl_subject
        WHERE Is_Archived = 1
    ');
    $archived = 0;
    foreach (array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)) as $subjectId) {
        $archived += archiveQuestionsForSubject($pdo, $subjectId);
    }

    return $archived;
}

function archiveSubjectRecord(PDO $pdo, int $subjectId): bool
{
    $archived = archiveRecord($pdo, 'tbl_subject', 'Subject_ID', $subjectId);
    if ($archived) {
        archiveQuestionsForSubject($pdo, $subjectId);
    }

    return $archived;
}

function restoreSubjectRecord(PDO $pdo, int $subjectId): bool
{
    restoreQuestionsArchivedWithSubject($pdo, $subjectId);

    return restoreRecord($pdo, 'tbl_subject', 'Subject_ID', $subjectId);
}

// Explain why a restore failed
function restoreFailureMessage(PDO $pdo, string $table, string $idColumn, int $id, string $label = 'record'): string
{
    if (!archiveColumnExists($pdo, $table)) {
        return "Archive columns are missing on {$table}. Run the database migration first.";
    }

    $stmt = $pdo->prepare("SELECT Is_Archived FROM `{$table}` WHERE {$idColumn} = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return ucfirst($label) . ' not found.';
    }

    if ((int)($row['Is_Archived'] ?? 0) !== 1) {
        return ucfirst($label) . ' is already active.';
    }

    return 'Unable to restore ' . $label . '. Please try again.';
}

// Restore course/batch ancestor chain so a child is visible again in Manage Courses
function restoreCurriculumAncestors(PDO $pdo, ?int $batchId, ?int $courseId): array
{
    $restored = [];

    if ($courseId > 0 && restoreRecord($pdo, 'tbl_course', 'Course_ID', $courseId)) {
        $restored[] = 'course';
    }

    if ($batchId > 0 && restoreRecord($pdo, 'tbl_batch', 'Batch_ID', $batchId)) {
        $restored[] = 'batch';
    }

    return $restored;
}

/**
 * Restore course/module ancestors so a subject is visible again in Manage Subjects.
 */
function restoreSubjectAncestors(PDO $pdo, ?int $moduleId, ?int $courseId): array
{
    $restored = [];

    if ($courseId > 0 && restoreRecord($pdo, 'tbl_course', 'Course_ID', $courseId)) {
        $restored[] = 'course';
    }

    if ($moduleId > 0 && archiveColumnExists($pdo, 'tbl_module') && restoreRecord($pdo, 'tbl_module', 'Module_ID', $moduleId)) {
        $restored[] = 'module';
    }

    return $restored;
}

/**
 * Archive all active child rows linked by a foreign key.
 */
function archiveActiveChildren(PDO $pdo, string $table, string $pkColumn, string $fkColumn, int $parentId): int
{
    if ($parentId <= 0 || !archiveColumnExists($pdo, $table)) {
        return 0;
    }

    $active = activeArchiveCondition($pdo, $table);
    $stmt = $pdo->prepare("SELECT {$pkColumn} FROM `{$table}` WHERE {$fkColumn} = ? AND {$active}");
    $stmt->execute([$parentId]);

    $archived = 0;
    foreach (array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)) as $id) {
        if ($id > 0 && archiveRecord($pdo, $table, $pkColumn, $id)) {
            $archived++;
        }
    }

    return $archived;
}

/**
 * Archive every active section under a batch.
 */
function archiveSectionsForBatch(PDO $pdo, int $batchId): int
{
    return archiveActiveChildren($pdo, 'tbl_academic_section', 'Section_ID', 'Batch_ID', $batchId);
}

/**
 * Ensure tbl_user_batch.Status supports Inactive for soft-unassign on archive.
 */
function ensureUserBatchInactiveStatus(PDO $pdo): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    try {
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'tbl_user_batch'");
        if (!$tableCheck || $tableCheck->rowCount() === 0) {
            $ready = false;
            return false;
        }

        $col = $pdo->query("SHOW COLUMNS FROM tbl_user_batch LIKE 'Status'")->fetch(PDO::FETCH_ASSOC);
        $type = strtolower((string)($col['Type'] ?? ''));
        if ($type === '' || strpos($type, 'inactive') !== false) {
            $ready = $type !== '';
            return $ready;
        }

        $pdo->exec("ALTER TABLE tbl_user_batch MODIFY COLUMN Status enum('Active','Completed','Inactive') DEFAULT 'Active'");
        $ready = true;
        return true;
    } catch (Exception $e) {
        error_log('ensureUserBatchInactiveStatus: ' . $e->getMessage());
        $ready = false;
        return false;
    }
}

/**
 * Soft-inactivate Active enrollments for the given batch IDs (reversible on restore).
 *
 * @param int[] $batchIds
 */
function inactivateEnrollmentsForBatches(PDO $pdo, array $batchIds): int
{
    $batchIds = array_values(array_filter(array_map('intval', $batchIds), static fn($id) => $id > 0));
    if (empty($batchIds) || !ensureUserBatchInactiveStatus($pdo)) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($batchIds), '?'));
    $stmt = $pdo->prepare("
        UPDATE tbl_user_batch
        SET Status = 'Inactive'
        WHERE Batch_ID IN ({$placeholders})
          AND Status = 'Active'
    ");
    $stmt->execute($batchIds);

    return $stmt->rowCount();
}

/**
 * Reactivate enrollments that were soft-inactivated for the given batch IDs.
 *
 * @param int[] $batchIds
 */
function reactivateEnrollmentsForBatches(PDO $pdo, array $batchIds): int
{
    $batchIds = array_values(array_filter(array_map('intval', $batchIds), static fn($id) => $id > 0));
    if (empty($batchIds) || !ensureUserBatchInactiveStatus($pdo)) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($batchIds), '?'));
    $stmt = $pdo->prepare("
        UPDATE tbl_user_batch
        SET Status = 'Active'
        WHERE Batch_ID IN ({$placeholders})
          AND Status = 'Inactive'
    ");
    $stmt->execute($batchIds);

    return $stmt->rowCount();
}

/**
 * Batch IDs under a course
 *
 * @return int[]
 */
function batchIdsForCourse(PDO $pdo, int $courseId): array
{
    if ($courseId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare('SELECT Batch_ID FROM tbl_batch WHERE Course_ID = ?');
    $stmt->execute([$courseId]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Parent batch + enrollment batches linked via its sections.
 *
 * @return int[]
 */
function batchIdsForBatchTree(PDO $pdo, int $batchId): array
{
    if ($batchId <= 0) {
        return [];
    }

    $ids = [$batchId];
    $stmt = $pdo->prepare('
        SELECT DISTINCT eb.Batch_ID
        FROM tbl_academic_section sec
        INNER JOIN tbl_batch eb ON eb.Section_ID = sec.Section_ID
        WHERE sec.Batch_ID = ?
    ');
    $stmt->execute([$batchId]);
    foreach (array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)) as $id) {
        if ($id > 0 && !in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }

    return $ids;
}

/**
 * Soft-inactivate enrollments for every batch under a course.
 */
function inactivateEnrollmentsForCourse(PDO $pdo, int $courseId): int
{
    return inactivateEnrollmentsForBatches($pdo, batchIdsForCourse($pdo, $courseId));
}

/**
 * Reactivate soft-inactivated enrollments for every batch under a course.
 */
function reactivateEnrollmentsForCourse(PDO $pdo, int $courseId): int
{
    return reactivateEnrollmentsForBatches($pdo, batchIdsForCourse($pdo, $courseId));
}

/**
 * Count in-progress exam sessions for exams belonging to a course's subjects.
 */
function countActiveExamSessionsForCourse(PDO $pdo, int $courseId): int
{
    if ($courseId <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare('
        SELECT COUNT(*)
        FROM tbl_exam_session es
        INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
        INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
        WHERE s.Course_ID = ?
          AND es.Time_Ended IS NULL
    ');
    $stmt->execute([$courseId]);

    return (int)$stmt->fetchColumn();
}

/**
 * Count in-progress sessions for exams linked to the given subject IDs.
 *
 * @param int[] $subjectIds
 */
function countActiveExamSessionsForSubjects(PDO $pdo, array $subjectIds): int
{
    $subjectIds = array_values(array_filter(array_map('intval', $subjectIds), static fn($id) => $id > 0));
    if (empty($subjectIds)) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($subjectIds), '?'));
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM tbl_exam_session es
        INNER JOIN tbl_exam e ON es.Exam_ID = e.Exam_ID
        WHERE e.Subject_ID IN ({$placeholders})
          AND es.Time_Ended IS NULL
    ");
    $stmt->execute($subjectIds);

    return (int)$stmt->fetchColumn();
}

/**
 * Archive an exam and close it if published.
 */
function archiveExamRecord(PDO $pdo, int $examId): bool
{
    if ($examId <= 0 || !archiveColumnExists($pdo, 'tbl_exam')) {
        return false;
    }

    if (!function_exists('finalizeExamSessionsOnClose')) {
        require_once __DIR__ . '/exam-deadline-finalize-utils.php';
    }
    finalizeExamSessionsOnClose($pdo, $examId);

    $stmt = $pdo->prepare("
        UPDATE tbl_exam
        SET Is_Archived = 1,
            Archived_At = NOW(),
            Status = CASE WHEN Status = 'Published' THEN 'Closed' ELSE Status END
        WHERE Exam_ID = ?
          AND (Is_Archived = 0 OR Is_Archived IS NULL)
    ");
    $stmt->execute([$examId]);

    return $stmt->rowCount() > 0;
}

/**
 * Restore an archived exam
 */
function restoreExamRecord(PDO $pdo, int $examId): bool
{
    if ($examId <= 0 || !archiveColumnExists($pdo, 'tbl_exam')) {
        return false;
    }

    $stmt = $pdo->prepare('
        UPDATE tbl_exam
        SET Is_Archived = 0,
            Archived_At = NULL
        WHERE Exam_ID = ?
          AND Is_Archived = 1
    ');
    $stmt->execute([$examId]);

    return $stmt->rowCount() > 0;
}

/**
 * Archive active exams for the given subject IDs.
 *
 * @param int[] $subjectIds
 */
function archiveExamsForSubjects(PDO $pdo, array $subjectIds): int
{
    $subjectIds = array_values(array_filter(array_map('intval', $subjectIds), static fn($id) => $id > 0));
    if (empty($subjectIds) || !archiveColumnExists($pdo, 'tbl_exam')) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($subjectIds), '?'));
    $active = activeArchiveCondition($pdo, 'tbl_exam');
    $stmt = $pdo->prepare("
        SELECT Exam_ID
        FROM tbl_exam
        WHERE Subject_ID IN ({$placeholders})
          AND {$active}
    ");
    $stmt->execute($subjectIds);

    $archived = 0;
    foreach (array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)) as $examId) {
        if (archiveExamRecord($pdo, $examId)) {
            $archived++;
        }
    }

    return $archived;
}

/**
 * Restore archived exams for the given subject IDs.
 *
 * @param int[] $subjectIds
 */
function restoreExamsForSubjects(PDO $pdo, array $subjectIds): int
{
    $subjectIds = array_values(array_filter(array_map('intval', $subjectIds), static fn($id) => $id > 0));
    if (empty($subjectIds) || !archiveColumnExists($pdo, 'tbl_exam')) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($subjectIds), '?'));
    $stmt = $pdo->prepare("
        SELECT Exam_ID
        FROM tbl_exam
        WHERE Subject_ID IN ({$placeholders})
          AND Is_Archived = 1
    ");
    $stmt->execute($subjectIds);

    $restored = 0;
    foreach (array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)) as $examId) {
        if (restoreExamRecord($pdo, $examId)) {
            $restored++;
        }
    }

    return $restored;
}

/**
 * Subject IDs belonging to a course (module-linked + orphan).
 *
 * @return int[]
 */
function subjectIdsForCourse(PDO $pdo, int $courseId): array
{
    if ($courseId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare('SELECT Subject_ID FROM tbl_subject WHERE Course_ID = ?');
    $stmt->execute([$courseId]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Subject IDs under a module.
 *
 * @return int[]
 */
function subjectIdsForModule(PDO $pdo, int $moduleId): array
{
    if ($moduleId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare('SELECT Subject_ID FROM tbl_subject WHERE Module_ID = ?');
    $stmt->execute([$moduleId]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Archive batches that belong to sections under a parent batch.
 */
function archiveEnrollmentBatchesForParentBatch(PDO $pdo, int $parentBatchId): int
{
    if ($parentBatchId <= 0 || !archiveColumnExists($pdo, 'tbl_batch')) {
        return 0;
    }

    $active = activeArchiveCondition($pdo, 'tbl_batch', 'eb');
    $stmt = $pdo->prepare("
        SELECT eb.Batch_ID
        FROM tbl_batch eb
        INNER JOIN tbl_academic_section sec ON eb.Section_ID = sec.Section_ID
        WHERE sec.Batch_ID = ?
          AND {$active}
    ");
    $stmt->execute([$parentBatchId]);

    $archived = 0;
    foreach (array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)) as $enrollmentBatchId) {
        if ($enrollmentBatchId > 0 && archiveRecord($pdo, 'tbl_batch', 'Batch_ID', $enrollmentBatchId)) {
            $archived++;
        }
    }

    return $archived;
}

/**
 * Restore batches under sections of a parent batch.
 */
function restoreEnrollmentBatchesForParentBatch(PDO $pdo, int $parentBatchId): int
{
    if ($parentBatchId <= 0 || !archiveColumnExists($pdo, 'tbl_batch')) {
        return 0;
    }

    $stmt = $pdo->prepare('
        SELECT eb.Batch_ID
        FROM tbl_batch eb
        INNER JOIN tbl_academic_section sec ON eb.Section_ID = sec.Section_ID
        WHERE sec.Batch_ID = ?
          AND eb.Is_Archived = 1
    ');
    $stmt->execute([$parentBatchId]);

    $restored = 0;
    foreach (array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)) as $enrollmentBatchId) {
        if ($enrollmentBatchId > 0 && restoreRecord($pdo, 'tbl_batch', 'Batch_ID', $enrollmentBatchId)) {
            $restored++;
        }
    }

    return $restored;
}

/**
 * Archive a batch and cascade to enrollment batches, sections, and soft-unassign users.
 *
 * @return array{batch: bool, sections: int, enrollment_batches: int, enrollments: int}
 */
function archiveBatchWithSections(PDO $pdo, int $batchId): array
{
    $result = [
        'batch' => false,
        'sections' => 0,
        'enrollment_batches' => 0,
        'enrollments' => 0,
    ];

    if ($batchId <= 0) {
        return $result;
    }

    if (!archiveRecord($pdo, 'tbl_batch', 'Batch_ID', $batchId)) {
        return $result;
    }

    $result['batch'] = true;
    $result['enrollments'] = inactivateEnrollmentsForBatches($pdo, batchIdsForBatchTree($pdo, $batchId));
    $result['enrollment_batches'] = archiveEnrollmentBatchesForParentBatch($pdo, $batchId);
    $result['sections'] = archiveSectionsForBatch($pdo, $batchId);

    return $result;
}

/**
 * Whether a batch end date is on or before today (triggers auto-archive).
 */
function batchEndDateIsPast(?string $dateEnded): bool
{
    if ($dateEnded === null || trim($dateEnded) === '') {
        return false;
    }

    $ended = substr(trim($dateEnded), 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ended)) {
        return false;
    }

    return $ended <= date('Y-m-d');
}

/**
 * Validate a restore end date (must be after today). Returns error message or null.
 */
function validateRestoreBatchEndDate(?string $dateEnded, ?string $dateStarted = null): ?string
{
    if ($dateEnded === null || trim($dateEnded) === '') {
        return 'A new end date is required because this batch already ended.';
    }

    $ended = substr(trim($dateEnded), 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ended)) {
        return 'Invalid end date format. Use YYYY-MM-DD.';
    }

    if ($ended <= date('Y-m-d')) {
        return 'New end date must be after today so the batch is not auto-archived again.';
    }

    if ($dateStarted) {
        $started = substr(trim($dateStarted), 0, 10);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $started) && $ended < $started) {
            return 'End date cannot be earlier than the start date.';
        }
    }

    return null;
}

/**
 * Set or clear Date_Ended on a batch (used when restoring overdue batches).
 */
function updateBatchEndDate(PDO $pdo, int $batchId, ?string $dateEnded): bool
{
    if ($batchId <= 0) {
        return false;
    }

    $value = ($dateEnded !== null && trim($dateEnded) !== '')
        ? substr(trim($dateEnded), 0, 10)
        : null;

    $stmt = $pdo->prepare('UPDATE tbl_batch SET Date_Ended = ? WHERE Batch_ID = ?');
    $stmt->execute([$value, $batchId]);

    return $stmt->rowCount() >= 0;
}

/**
 * Clear past end dates on parent batches under a course so restore is not undone by auto-archive.
 *
 * @return int Number of batches updated
 */
function clearPastEndDatesForCourseBatches(PDO $pdo, int $courseId): int
{
    if ($courseId <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare("
        UPDATE tbl_batch
        SET Date_Ended = NULL
        WHERE Course_ID = ?
          AND (Is_Archived = 0 OR Is_Archived IS NULL)
          AND (Section_ID IS NULL OR Section_ID = 0)
          AND Date_Ended IS NOT NULL
          AND DATE(Date_Ended) <= CURDATE()
    ");
    $stmt->execute([$courseId]);

    return $stmt->rowCount();
}

/**
 * Restore a batch and cascade to sections, enrollment batches, and re-activate enrollments.
 *
 * @return array{batch: bool, sections: int, enrollment_batches: int, enrollments: int, ancestors: string[]}
 */
function restoreBatchWithSections(PDO $pdo, int $batchId): array
{
    $result = [
        'batch' => false,
        'sections' => 0,
        'enrollment_batches' => 0,
        'enrollments' => 0,
        'ancestors' => [],
    ];

    if ($batchId <= 0) {
        return $result;
    }

    $metaStmt = $pdo->prepare('SELECT Batch_ID, Course_ID FROM tbl_batch WHERE Batch_ID = ? LIMIT 1');
    $metaStmt->execute([$batchId]);
    $meta = $metaStmt->fetch(PDO::FETCH_ASSOC);
    if (!$meta) {
        return $result;
    }

    $result['ancestors'] = restoreCurriculumAncestors($pdo, null, (int)$meta['Course_ID']);
    $result['batch'] = restoreRecord($pdo, 'tbl_batch', 'Batch_ID', $batchId);

    if (archiveColumnExists($pdo, 'tbl_academic_section')) {
        $secStmt = $pdo->prepare('SELECT Section_ID FROM tbl_academic_section WHERE Batch_ID = ? AND Is_Archived = 1');
        $secStmt->execute([$batchId]);
        foreach (array_map('intval', $secStmt->fetchAll(PDO::FETCH_COLUMN)) as $sectionId) {
            if ($sectionId > 0 && restoreRecord($pdo, 'tbl_academic_section', 'Section_ID', $sectionId)) {
                $result['sections']++;
            }
        }
    }

    $result['enrollment_batches'] = restoreEnrollmentBatchesForParentBatch($pdo, $batchId);
    $result['enrollments'] = reactivateEnrollmentsForBatches($pdo, batchIdsForBatchTree($pdo, $batchId));

    return $result;
}

/**
 * Archive all active batches (and their sections) for a course.
 *
 * @return array{batches: int, sections: int, enrollment_batches: int, enrollments: int}
 */
function archiveBatchesForCourse(PDO $pdo, int $courseId): array
{
    $totals = ['batches' => 0, 'sections' => 0, 'enrollment_batches' => 0, 'enrollments' => 0];

    if ($courseId <= 0 || !archiveColumnExists($pdo, 'tbl_batch')) {
        return $totals;
    }

    // Soft-unassign once for the whole course before archiving batch rows.
    $totals['enrollments'] = inactivateEnrollmentsForCourse($pdo, $courseId);

    $active = activeArchiveCondition($pdo, 'tbl_batch');
    // Parent/course batches first (not section enrollment rows).
    $stmt = $pdo->prepare("
        SELECT Batch_ID
        FROM tbl_batch
        WHERE Course_ID = ?
          AND {$active}
          AND (Section_ID IS NULL OR Section_ID = 0)
        ORDER BY Batch_ID
    ");
    $stmt->execute([$courseId]);

    foreach (array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)) as $batchId) {
        $result = archiveBatchWithSections($pdo, $batchId);
        if ($result['batch']) {
            $totals['batches']++;
        }
        $totals['sections'] += $result['sections'];
        $totals['enrollment_batches'] += $result['enrollment_batches'];
        // enrollments already counted at course level; ignore per-batch counts
    }

    // Any remaining active enrollment batches (orphaned / already-unlinked).
    $remainStmt = $pdo->prepare("
        SELECT Batch_ID
        FROM tbl_batch
        WHERE Course_ID = ?
          AND {$active}
          AND Section_ID IS NOT NULL
          AND Section_ID <> 0
    ");
    $remainStmt->execute([$courseId]);
    foreach (array_map('intval', $remainStmt->fetchAll(PDO::FETCH_COLUMN)) as $enrollmentBatchId) {
        if ($enrollmentBatchId > 0 && archiveRecord($pdo, 'tbl_batch', 'Batch_ID', $enrollmentBatchId)) {
            $totals['enrollment_batches']++;
        }
    }

    return $totals;
}

/**
 * Archive all active subjects under a module.
 */
function archiveSubjectsForModule(PDO $pdo, int $moduleId): int
{
    if ($moduleId <= 0 || !archiveColumnExists($pdo, 'tbl_subject')) {
        return 0;
    }

    $active = activeArchiveCondition($pdo, 'tbl_subject');
    $stmt = $pdo->prepare("SELECT Subject_ID FROM tbl_subject WHERE Module_ID = ? AND {$active}");
    $stmt->execute([$moduleId]);

    $archived = 0;
    foreach (array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)) as $subjectId) {
        if (archiveSubjectRecord($pdo, $subjectId)) {
            $archived++;
        }
    }

    return $archived;
}

/**
 * Archive course-level subjects that are not tied to a module.
 */
function archiveOrphanSubjectsForCourse(PDO $pdo, int $courseId): int
{
    if ($courseId <= 0 || !archiveColumnExists($pdo, 'tbl_subject')) {
        return 0;
    }

    $active = activeArchiveCondition($pdo, 'tbl_subject', 's');
    $stmt = $pdo->prepare("
        SELECT s.Subject_ID
        FROM tbl_subject s
        WHERE s.Course_ID = ?
          AND (s.Module_ID IS NULL OR s.Module_ID = 0)
          AND {$active}
    ");
    $stmt->execute([$courseId]);

    $archived = 0;
    foreach (array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)) as $subjectId) {
        if ($subjectId > 0 && archiveSubjectRecord($pdo, $subjectId)) {
            $archived++;
        }
    }

    return $archived;
}

/**
 * Archive a module and cascade to subjects and their exams.
 *
 * @return array{module: bool, subjects: int, exams: int}
 */
function archiveModuleWithSubjects(PDO $pdo, int $moduleId): array
{
    $empty = ['module' => false, 'subjects' => 0, 'exams' => 0];

    if ($moduleId <= 0 || !archiveColumnExists($pdo, 'tbl_module')) {
        return $empty;
    }

    if (!archiveRecord($pdo, 'tbl_module', 'Module_ID', $moduleId)) {
        return $empty;
    }

    $subjectIds = subjectIdsForModule($pdo, $moduleId);
    $examsArchived = archiveExamsForSubjects($pdo, $subjectIds);
    $subjectsArchived = archiveSubjectsForModule($pdo, $moduleId);

    return [
        'module' => true,
        'subjects' => $subjectsArchived,
        'exams' => $examsArchived,
    ];
}

/**
 * Restore a module and cascade to subjects and their exams.
 *
 * @return array{module: bool, subjects: int, exams: int, ancestors: string[]}
 */
function restoreModuleWithSubjects(PDO $pdo, int $moduleId): array
{
    $result = [
        'module' => false,
        'subjects' => 0,
        'exams' => 0,
        'ancestors' => [],
    ];

    if ($moduleId <= 0 || !archiveColumnExists($pdo, 'tbl_module')) {
        return $result;
    }

    $metaStmt = $pdo->prepare('SELECT Module_ID, Course_ID FROM tbl_module WHERE Module_ID = ? LIMIT 1');
    $metaStmt->execute([$moduleId]);
    $meta = $metaStmt->fetch(PDO::FETCH_ASSOC);
    if (!$meta) {
        return $result;
    }

    $result['ancestors'] = restoreCurriculumAncestors($pdo, null, (int)$meta['Course_ID']);
    $result['module'] = restoreRecord($pdo, 'tbl_module', 'Module_ID', $moduleId);

    if (archiveColumnExists($pdo, 'tbl_subject')) {
        $subjStmt = $pdo->prepare('SELECT Subject_ID FROM tbl_subject WHERE Module_ID = ? AND Is_Archived = 1');
        $subjStmt->execute([$moduleId]);
        $subjectIds = array_map('intval', $subjStmt->fetchAll(PDO::FETCH_COLUMN));
        foreach ($subjectIds as $subjectId) {
            if ($subjectId > 0 && restoreSubjectRecord($pdo, $subjectId)) {
                $result['subjects']++;
            }
        }
        $result['exams'] = restoreExamsForSubjects($pdo, $subjectIds ?: subjectIdsForModule($pdo, $moduleId));
    }

    return $result;
}

/**
 * Archive all active modules (and their subjects/exams) for a course.
 *
 * @return array{modules: int, subjects: int, exams: int}
 */
function archiveModulesForCourse(PDO $pdo, int $courseId): array
{
    $totals = ['modules' => 0, 'subjects' => 0, 'exams' => 0];

    if ($courseId <= 0) {
        return $totals;
    }

    if (!function_exists('moduleTableExists')) {
        require_once __DIR__ . '/module-utils.php';
    }

    if (function_exists('moduleTableExists') && moduleTableExists($pdo) && archiveColumnExists($pdo, 'tbl_module')) {
        $active = activeArchiveCondition($pdo, 'tbl_module');
        $stmt = $pdo->prepare("SELECT Module_ID FROM tbl_module WHERE Course_ID = ? AND {$active}");
        $stmt->execute([$courseId]);

        foreach (array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)) as $moduleId) {
            $result = archiveModuleWithSubjects($pdo, $moduleId);
            if ($result['module']) {
                $totals['modules']++;
            }
            $totals['subjects'] += $result['subjects'];
            $totals['exams'] += $result['exams'];
        }
    }

    $orphanIds = [];
    if (archiveColumnExists($pdo, 'tbl_subject')) {
        $active = activeArchiveCondition($pdo, 'tbl_subject', 's');
        $orphanStmt = $pdo->prepare("
            SELECT s.Subject_ID
            FROM tbl_subject s
            WHERE s.Course_ID = ?
              AND (s.Module_ID IS NULL OR s.Module_ID = 0)
              AND {$active}
        ");
        $orphanStmt->execute([$courseId]);
        $orphanIds = array_map('intval', $orphanStmt->fetchAll(PDO::FETCH_COLUMN));
    }

    $totals['exams'] += archiveExamsForSubjects($pdo, $orphanIds);
    $totals['subjects'] += archiveOrphanSubjectsForCourse($pdo, $courseId);

    return $totals;
}

/**
 * Archive a course and cascade to batches, sections, modules, subjects, exams, and enrollments.
 *
 * @return array<string, int|bool>
 */
function archiveCourseWithDescendants(PDO $pdo, int $courseId): array
{
    $result = [
        'course' => false,
        'batches' => 0,
        'sections' => 0,
        'enrollment_batches' => 0,
        'modules' => 0,
        'subjects' => 0,
        'exams' => 0,
        'enrollments' => 0,
    ];

    if ($courseId <= 0) {
        return $result;
    }

    if (!archiveRecord($pdo, 'tbl_course', 'Course_ID', $courseId)) {
        return $result;
    }

    $result['course'] = true;

    // Archive curriculum content (includes exams under subjects).
    $moduleTotals = archiveModulesForCourse($pdo, $courseId);
    $result['modules'] = $moduleTotals['modules'];
    $result['subjects'] = $moduleTotals['subjects'];
    $result['exams'] = $moduleTotals['exams'];

    // Soft-unassign + archive batches/sections/enrollment batches.
    $batchTotals = archiveBatchesForCourse($pdo, $courseId);
    $result['batches'] = $batchTotals['batches'];
    $result['sections'] = $batchTotals['sections'];
    $result['enrollment_batches'] = $batchTotals['enrollment_batches'];
    $result['enrollments'] = $batchTotals['enrollments'];

    return $result;
}

/**
 * Restore a course and cascade to batches, sections, modules, subjects, exams, and enrollments.
 *
 * @return array<string, int|bool>
 */
function restoreCourseWithDescendants(PDO $pdo, int $courseId): array
{
    $result = [
        'course' => false,
        'batches' => 0,
        'sections' => 0,
        'enrollment_batches' => 0,
        'modules' => 0,
        'subjects' => 0,
        'exams' => 0,
        'enrollments' => 0,
        'end_dates_cleared' => 0,
    ];

    if ($courseId <= 0) {
        return $result;
    }

    if (!restoreRecord($pdo, 'tbl_course', 'Course_ID', $courseId)) {
        return $result;
    }
    $result['course'] = true;

    if (!function_exists('moduleTableExists')) {
        require_once __DIR__ . '/module-utils.php';
    }

    if (function_exists('moduleTableExists') && moduleTableExists($pdo) && archiveColumnExists($pdo, 'tbl_module')) {
        $modStmt = $pdo->prepare('SELECT Module_ID FROM tbl_module WHERE Course_ID = ? AND Is_Archived = 1');
        $modStmt->execute([$courseId]);
        foreach (array_map('intval', $modStmt->fetchAll(PDO::FETCH_COLUMN)) as $moduleId) {
            $modResult = restoreModuleWithSubjects($pdo, $moduleId);
            if ($modResult['module']) {
                $result['modules']++;
            }
            $result['subjects'] += $modResult['subjects'];
            $result['exams'] += $modResult['exams'];
        }
    }

    // Orphan subjects (no module) + their exams.
    if (archiveColumnExists($pdo, 'tbl_subject')) {
        $orphanStmt = $pdo->prepare("
            SELECT Subject_ID
            FROM tbl_subject
            WHERE Course_ID = ?
              AND (Module_ID IS NULL OR Module_ID = 0)
              AND Is_Archived = 1
        ");
        $orphanStmt->execute([$courseId]);
        $orphanIds = array_map('intval', $orphanStmt->fetchAll(PDO::FETCH_COLUMN));
        foreach ($orphanIds as $subjectId) {
            if ($subjectId > 0 && restoreSubjectRecord($pdo, $subjectId)) {
                $result['subjects']++;
            }
        }
        $result['exams'] += restoreExamsForSubjects($pdo, $orphanIds);
    }

    // Any remaining archived exams for this course (safety net).
    $allSubjectIds = subjectIdsForCourse($pdo, $courseId);
    $alreadyRestoredExams = (int)$result['exams'];
    $extraExams = restoreExamsForSubjects($pdo, $allSubjectIds);
    $result['exams'] = $alreadyRestoredExams + $extraExams;

    if (archiveColumnExists($pdo, 'tbl_batch')) {
        $parentStmt = $pdo->prepare("
            SELECT Batch_ID
            FROM tbl_batch
            WHERE Course_ID = ?
              AND Is_Archived = 1
              AND (Section_ID IS NULL OR Section_ID = 0)
        ");
        $parentStmt->execute([$courseId]);
        foreach (array_map('intval', $parentStmt->fetchAll(PDO::FETCH_COLUMN)) as $batchId) {
            $batchResult = restoreBatchWithSections($pdo, $batchId);
            if ($batchResult['batch']) {
                $result['batches']++;
            }
            $result['sections'] += $batchResult['sections'];
            $result['enrollment_batches'] += $batchResult['enrollment_batches'];
        }

        // Remaining enrollment batches.
        $enrollStmt = $pdo->prepare("
            SELECT Batch_ID
            FROM tbl_batch
            WHERE Course_ID = ?
              AND Is_Archived = 1
              AND Section_ID IS NOT NULL
              AND Section_ID <> 0
        ");
        $enrollStmt->execute([$courseId]);
        foreach (array_map('intval', $enrollStmt->fetchAll(PDO::FETCH_COLUMN)) as $enrollmentBatchId) {
            if ($enrollmentBatchId > 0 && restoreRecord($pdo, 'tbl_batch', 'Batch_ID', $enrollmentBatchId)) {
                $result['enrollment_batches']++;
            }
        }
    }

    $result['enrollments'] = reactivateEnrollmentsForCourse($pdo, $courseId);
    $result['end_dates_cleared'] = clearPastEndDatesForCourseBatches($pdo, $courseId);

    return $result;
}

/**
 * Backfill cascade archives for legacy rows archived before cascade logic existed.
 *
 * @return array{sections: int, subjects: int, batches: int, modules: int, exams: int, enrollments: int}
 */
function backfillCascadeArchives(PDO $pdo): array
{
    $fixed = ['sections' => 0, 'subjects' => 0, 'batches' => 0, 'modules' => 0, 'exams' => 0, 'enrollments' => 0];

    if (archiveColumnExists($pdo, 'tbl_batch') && archiveColumnExists($pdo, 'tbl_academic_section')) {
        $stmt = $pdo->query('
            SELECT sec.Section_ID
            FROM tbl_academic_section sec
            INNER JOIN tbl_batch b ON sec.Batch_ID = b.Batch_ID
            WHERE b.Is_Archived = 1
              AND (sec.Is_Archived = 0 OR sec.Is_Archived IS NULL)
        ');
        foreach (array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)) as $sectionId) {
            if ($sectionId > 0 && archiveRecord($pdo, 'tbl_academic_section', 'Section_ID', $sectionId)) {
                $fixed['sections']++;
            }
        }
    }

    if (archiveColumnExists($pdo, 'tbl_module') && archiveColumnExists($pdo, 'tbl_subject')) {
        $stmt = $pdo->query('
            SELECT s.Subject_ID
            FROM tbl_subject s
            INNER JOIN tbl_module m ON s.Module_ID = m.Module_ID
            WHERE m.Is_Archived = 1
              AND (s.Is_Archived = 0 OR s.Is_Archived IS NULL)
        ');
        foreach (array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)) as $subjectId) {
            if ($subjectId > 0 && archiveSubjectRecord($pdo, $subjectId)) {
                $fixed['subjects']++;
            }
        }
    }

    if (archiveColumnExists($pdo, 'tbl_course') && archiveColumnExists($pdo, 'tbl_batch')) {
        $stmt = $pdo->query('
            SELECT b.Batch_ID
            FROM tbl_batch b
            INNER JOIN tbl_course c ON b.Course_ID = c.Course_ID
            WHERE c.Is_Archived = 1
              AND (b.Is_Archived = 0 OR b.Is_Archived IS NULL)
              AND (b.Section_ID IS NULL OR b.Section_ID = 0)
        ');
        foreach (array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)) as $batchId) {
            $result = archiveBatchWithSections($pdo, $batchId);
            if ($result['batch']) {
                $fixed['batches']++;
            }
            $fixed['sections'] += $result['sections'];
            $fixed['enrollments'] += $result['enrollments'];
        }

        $enrollStmt = $pdo->query('
            SELECT b.Batch_ID
            FROM tbl_batch b
            INNER JOIN tbl_course c ON b.Course_ID = c.Course_ID
            WHERE c.Is_Archived = 1
              AND (b.Is_Archived = 0 OR b.Is_Archived IS NULL)
              AND b.Section_ID IS NOT NULL
              AND b.Section_ID <> 0
        ');
        foreach (array_map('intval', $enrollStmt->fetchAll(PDO::FETCH_COLUMN)) as $enrollmentBatchId) {
            if ($enrollmentBatchId > 0 && archiveRecord($pdo, 'tbl_batch', 'Batch_ID', $enrollmentBatchId)) {
                $fixed['batches']++;
            }
        }
    }

    if (archiveColumnExists($pdo, 'tbl_course') && archiveColumnExists($pdo, 'tbl_module')) {
        $stmt = $pdo->query('
            SELECT m.Module_ID
            FROM tbl_module m
            INNER JOIN tbl_course c ON m.Course_ID = c.Course_ID
            WHERE c.Is_Archived = 1
              AND (m.Is_Archived = 0 OR m.Is_Archived IS NULL)
        ');
        foreach (array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)) as $moduleId) {
            $result = archiveModuleWithSubjects($pdo, $moduleId);
            if ($result['module']) {
                $fixed['modules']++;
            }
            $fixed['subjects'] += $result['subjects'];
            $fixed['exams'] += $result['exams'];
        }
    }

    if (archiveColumnExists($pdo, 'tbl_course') && archiveColumnExists($pdo, 'tbl_subject')) {
        $stmt = $pdo->query('
            SELECT s.Subject_ID
            FROM tbl_subject s
            INNER JOIN tbl_course c ON s.Course_ID = c.Course_ID
            WHERE c.Is_Archived = 1
              AND (s.Module_ID IS NULL OR s.Module_ID = 0)
              AND (s.Is_Archived = 0 OR s.Is_Archived IS NULL)
        ');
        $orphanIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $fixed['exams'] += archiveExamsForSubjects($pdo, $orphanIds);
        foreach ($orphanIds as $subjectId) {
            if ($subjectId > 0 && archiveSubjectRecord($pdo, $subjectId)) {
                $fixed['subjects']++;
            }
        }
    }

    if (archiveColumnExists($pdo, 'tbl_exam') && archiveColumnExists($pdo, 'tbl_subject')) {
        $stmt = $pdo->query('
            SELECT e.Exam_ID
            FROM tbl_exam e
            INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
            WHERE s.Is_Archived = 1
              AND (e.Is_Archived = 0 OR e.Is_Archived IS NULL)
        ');
        foreach (array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)) as $examId) {
            if ($examId > 0 && archiveExamRecord($pdo, $examId)) {
                $fixed['exams']++;
            }
        }
    }

    if (ensureUserBatchInactiveStatus($pdo) && archiveColumnExists($pdo, 'tbl_batch')) {
        $stmt = $pdo->query("
            UPDATE tbl_user_batch ub
            INNER JOIN tbl_batch b ON ub.Batch_ID = b.Batch_ID
            SET ub.Status = 'Inactive'
            WHERE b.Is_Archived = 1
              AND ub.Status = 'Active'
        ");
        $fixed['enrollments'] += (int)$stmt->rowCount();
    }

    archiveQuestionsUnderArchivedSubjects($pdo);

    return $fixed;
}

/**
 * Build a human-readable cascade archive summary for API responses.
 *
 * @param array<string, int|bool> $counts
 */
function formatCascadeArchiveMessage(string $entityLabel, array $counts): string
{
    $parts = [ucfirst($entityLabel) . ' archived successfully'];
    $details = [];

    if (!empty($counts['batches'])) {
        $details[] = (int)$counts['batches'] . ' batch(es)';
    }
    if (!empty($counts['sections'])) {
        $details[] = (int)$counts['sections'] . ' section(s)';
    }
    if (!empty($counts['enrollment_batches'])) {
        $details[] = (int)$counts['enrollment_batches'] . ' enrollment batch(es)';
    }
    if (!empty($counts['modules'])) {
        $details[] = (int)$counts['modules'] . ' module(s)';
    }
    if (!empty($counts['subjects'])) {
        $details[] = (int)$counts['subjects'] . ' subject(s)';
    }
    if (!empty($counts['exams'])) {
        $details[] = (int)$counts['exams'] . ' exam(s)';
    }

    if (!empty($details)) {
        $parts[] = implode(', ', $details) . ' also archived';
    }

    if (!empty($counts['enrollments'])) {
        $parts[] = (int)$counts['enrollments'] . ' trainee assignment(s) deactivated';
    }

    if ($entityLabel === 'course') {
        $parts[] = 'Restore this course from Archive to bring related records and assignments back';
    } elseif ($entityLabel === 'batch' || $entityLabel === 'module') {
        $parts[] = 'Restore this ' . $entityLabel . ' from Archive to bring related records back';
    } else {
        $parts[] = 'Restore children individually from Archive to bring them back';
    }

    return implode('. ', $parts) . '.';
}

/**
 * Build a human-readable cascade restore summary for API responses.
 *
 * @param array<string, int|bool> $counts
 */
function formatCascadeRestoreMessage(string $entityLabel, array $counts): string
{
    $parts = [ucfirst($entityLabel) . ' restored successfully'];
    $details = [];

    if (!empty($counts['batches'])) {
        $details[] = (int)$counts['batches'] . ' batch(es)';
    }
    if (!empty($counts['sections'])) {
        $details[] = (int)$counts['sections'] . ' section(s)';
    }
    if (!empty($counts['enrollment_batches'])) {
        $details[] = (int)$counts['enrollment_batches'] . ' enrollment batch(es)';
    }
    if (!empty($counts['modules'])) {
        $details[] = (int)$counts['modules'] . ' module(s)';
    }
    if (!empty($counts['subjects'])) {
        $details[] = (int)$counts['subjects'] . ' subject(s)';
    }
    if (!empty($counts['exams'])) {
        $details[] = (int)$counts['exams'] . ' exam(s)';
    }

    if (!empty($details)) {
        $parts[] = implode(', ', $details) . ' also restored';
    }

    if (!empty($counts['enrollments'])) {
        $parts[] = (int)$counts['enrollments'] . ' trainee assignment(s) reactivated';
    }

    if (!empty($counts['end_dates_cleared'])) {
        $parts[] = (int)$counts['end_dates_cleared'] . ' overdue batch end date(s) cleared — set new end dates in Manage Courses';
    }

    if (!empty($counts['exams'])) {
        $parts[] = 'Restored exams remain Closed — republish them if needed';
    }

    return implode('. ', $parts) . '.';
}

/**
 * Archive active batches whose end date has been reached (Date_Ended <= today).
 *
 * @return int Number of batches archived
 */
function autoArchiveExpiredBatches(PDO $pdo, bool $audit = false): int
{
    if (!archiveColumnExists($pdo, 'tbl_batch')) {
        return 0;
    }

    $stmt = $pdo->prepare('
        SELECT Batch_ID
        FROM tbl_batch
        WHERE (Is_Archived = 0 OR Is_Archived IS NULL)
          AND Date_Ended IS NOT NULL
          AND DATE(Date_Ended) <= CURDATE()
    ');
    $stmt->execute();
    $batchIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    if (empty($batchIds)) {
        return 0;
    }

    if ($audit && !function_exists('auditBatchLabel')) {
        require_once __DIR__ . '/audit-log-utils.php';
    }

    $archived = 0;
    foreach ($batchIds as $batchId) {
        $result = archiveBatchWithSections($pdo, $batchId);
        if (!$result['batch']) {
            continue;
        }

        $archived++;

        if ($audit && function_exists('writeAuditLog')) {
            $label = auditBatchLabel($pdo, $batchId);
            $notes = [];
            if ($result['sections'] > 0) {
                $notes[] = "{$result['sections']} section(s)";
            }
            if (!empty($result['enrollments'])) {
                $notes[] = "{$result['enrollments']} assignment(s) deactivated";
            }
            $sectionNote = !empty($notes) ? ' (' . implode(', ', $notes) . ')' : '';
            writeAuditLog($pdo, [
                'user_id' => null,
                'user_role' => 'System',
                'module' => 'BATCH',
                'action' => 'AUTO_ARCHIVE_BATCH',
                'outcome' => "Batch {$label} auto-archived (end date reached){$sectionNote}",
                'status' => 'SUCCESS',
                'entity_type' => 'batch',
                'entity_id' => $batchId,
            ]);
        }
    }

    return $archived;
}

function countArchived(PDO $pdo, string $table): int
{
    if (!archiveColumnExists($pdo, $table)) {
        return 0;
    }

    $stmt = $pdo->query("SELECT COUNT(*) FROM `{$table}` WHERE Is_Archived = 1");
    return (int)$stmt->fetchColumn();
}

/**
 * Count archived parent/course batches only (hide section enrollment helper batches).
 */
function countArchivedParentBatches(PDO $pdo): int
{
    if (!archiveColumnExists($pdo, 'tbl_batch')) {
        return 0;
    }

    if (!function_exists('courseBatchVisibilityCondition')) {
        require_once __DIR__ . '/batch-enrollment-utils.php';
    }

    $visibility = courseBatchVisibilityCondition('b');
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM tbl_batch b
        WHERE b.Is_Archived = 1
          AND {$visibility}
    ");

    return (int)$stmt->fetchColumn();
}

/**
 * Attach retention countdown metadata for an archived row.
 *
 * @param array<string,mixed> $row
 */
function appendRetentionMeta(array &$row, int $retentionDays): void
{
    if (empty($row['Archived_At'])) {
        $row['Retention_Status'] = 'unknown';
        $row['Days_Until_Purge'] = null;
        $row['Days_Overdue'] = null;
        $row['Purge_Eligible_At'] = null;
        return;
    }

    $archivedTs = strtotime((string)$row['Archived_At']);
    $purgeTs = strtotime('+' . $retentionDays . ' days', $archivedTs);
    $nowTs = time();

    $row['Purge_Eligible_At'] = date('Y-m-d H:i:s', $purgeTs);

    if ($nowTs >= $purgeTs) {
        $row['Retention_Status'] = 'eligible';
        $row['Days_Until_Purge'] = 0;
        $row['Days_Overdue'] = (int)floor(($nowTs - $purgeTs) / 86400);
        return;
    }

    $row['Retention_Status'] = 'pending';
    $row['Days_Until_Purge'] = (int)ceil(($purgeTs - $nowTs) / 86400);
    $row['Days_Overdue'] = 0;
}

function archiveDeletionBlockedResponse(): void
{
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Direct deletion is disabled. Archive this record instead. Archived records are permanently removed after 2 years.',
    ]);
    exit;
}
