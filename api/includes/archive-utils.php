<?php
/**
 * Soft-archive helpers for masterfile entities.
 */

const ARCHIVE_RETENTION_DAYS = 730; // 2 years
const ARCHIVE_RETENTION_DAYS_MIN = 30;
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

/**
 * Explain why a restore failed (migration, not found, or already active).
 */
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

/**
 * Restore a course/batch ancestor chain so a child is visible again in Manage Courses.
 */
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
 * Archive a batch and cascade to all of its sections.
 *
 * @return array{batch: bool, sections: int}
 */
function archiveBatchWithSections(PDO $pdo, int $batchId): array
{
    $batchArchived = $batchId > 0 && archiveRecord($pdo, 'tbl_batch', 'Batch_ID', $batchId);
    $sectionsArchived = archiveSectionsForBatch($pdo, $batchId);

    return [
        'batch' => $batchArchived,
        'sections' => $sectionsArchived,
    ];
}

/**
 * Archive all active batches (and their sections) for a course.
 *
 * @return array{batches: int, sections: int}
 */
function archiveBatchesForCourse(PDO $pdo, int $courseId): array
{
    $totals = ['batches' => 0, 'sections' => 0];

    if ($courseId <= 0 || !archiveColumnExists($pdo, 'tbl_batch')) {
        return $totals;
    }

    $active = activeArchiveCondition($pdo, 'tbl_batch');
    $stmt = $pdo->prepare("SELECT Batch_ID FROM tbl_batch WHERE Course_ID = ? AND {$active}");
    $stmt->execute([$courseId]);

    foreach (array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)) as $batchId) {
        $result = archiveBatchWithSections($pdo, $batchId);
        if ($result['batch']) {
            $totals['batches']++;
        }
        $totals['sections'] += $result['sections'];
    }

    return $totals;
}

/**
 * Archive all active subjects under a module.
 */
function archiveSubjectsForModule(PDO $pdo, int $moduleId): int
{
    return archiveActiveChildren($pdo, 'tbl_subject', 'Subject_ID', 'Module_ID', $moduleId);
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
        if ($subjectId > 0 && archiveRecord($pdo, 'tbl_subject', 'Subject_ID', $subjectId)) {
            $archived++;
        }
    }

    return $archived;
}

/**
 * Archive a module and cascade to all of its subjects.
 *
 * @return array{module: bool, subjects: int}
 */
function archiveModuleWithSubjects(PDO $pdo, int $moduleId): array
{
    $moduleArchived = $moduleId > 0 && archiveColumnExists($pdo, 'tbl_module')
        && archiveRecord($pdo, 'tbl_module', 'Module_ID', $moduleId);
    $subjectsArchived = archiveSubjectsForModule($pdo, $moduleId);

    return [
        'module' => $moduleArchived,
        'subjects' => $subjectsArchived,
    ];
}

/**
 * Archive all active modules (and their subjects) for a course.
 *
 * @return array{modules: int, subjects: int}
 */
function archiveModulesForCourse(PDO $pdo, int $courseId): array
{
    $totals = ['modules' => 0, 'subjects' => 0];

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
        }
    }

    $totals['subjects'] += archiveOrphanSubjectsForCourse($pdo, $courseId);

    return $totals;
}

/**
 * Archive a course and cascade to batches, sections, modules, and subjects.
 *
 * @return array<string, int|bool>
 */
function archiveCourseWithDescendants(PDO $pdo, int $courseId): array
{
    $result = [
        'course' => false,
        'batches' => 0,
        'sections' => 0,
        'modules' => 0,
        'subjects' => 0,
    ];

    if ($courseId <= 0) {
        return $result;
    }

    if (!archiveRecord($pdo, 'tbl_course', 'Course_ID', $courseId)) {
        return $result;
    }

    $result['course'] = true;
    $batchTotals = archiveBatchesForCourse($pdo, $courseId);
    $moduleTotals = archiveModulesForCourse($pdo, $courseId);

    $result['batches'] = $batchTotals['batches'];
    $result['sections'] = $batchTotals['sections'];
    $result['modules'] = $moduleTotals['modules'];
    $result['subjects'] = $moduleTotals['subjects'];

    return $result;
}

/**
 * Backfill cascade archives for legacy rows archived before cascade logic existed.
 *
 * @return array{sections: int, subjects: int, batches: int, modules: int}
 */
function backfillCascadeArchives(PDO $pdo): array
{
    $fixed = ['sections' => 0, 'subjects' => 0, 'batches' => 0, 'modules' => 0];

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
            if ($subjectId > 0 && archiveRecord($pdo, 'tbl_subject', 'Subject_ID', $subjectId)) {
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
        ');
        foreach (array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)) as $batchId) {
            $result = archiveBatchWithSections($pdo, $batchId);
            if ($result['batch']) {
                $fixed['batches']++;
            }
            $fixed['sections'] += $result['sections'];
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
        foreach (array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)) as $subjectId) {
            if ($subjectId > 0 && archiveRecord($pdo, 'tbl_subject', 'Subject_ID', $subjectId)) {
                $fixed['subjects']++;
            }
        }
    }

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

    if (!empty($counts['sections'])) {
        $details[] = (int)$counts['sections'] . ' section(s)';
    }
    if (!empty($counts['batches'])) {
        $details[] = (int)$counts['batches'] . ' batch(es)';
    }
    if (!empty($counts['modules'])) {
        $details[] = (int)$counts['modules'] . ' module(s)';
    }
    if (!empty($counts['subjects'])) {
        $details[] = (int)$counts['subjects'] . ' subject(s)';
    }

    if (!empty($details)) {
        $parts[] = implode(', ', $details) . ' also archived';
    }

    $parts[] = 'Restore children individually from Archive to bring them back';

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
            $sectionNote = $result['sections'] > 0 ? " and {$result['sections']} section(s)" : '';
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
 * Attach retention countdown metadata for an archived row.
 *
 * @param array<string,mixed> $row
 */
function appendRetentionMeta(array &$row, int $retentionDays, bool $protected = false): void
{
    if ($protected) {
        $row['Retention_Status'] = 'protected';
        $row['Days_Until_Purge'] = null;
        $row['Days_Overdue'] = null;
        $row['Purge_Eligible_At'] = null;
        return;
    }

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
