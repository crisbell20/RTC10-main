<?php
/**
 * Automatic purge of archived records past retention period.
 */

require_once __DIR__ . '/archive-utils.php';
require_once __DIR__ . '/audit-log-utils.php';

/**
 * @return array<string,int>
 */
function purgeAllArchivedRecords(PDO $pdo, int $retentionDays = ARCHIVE_RETENTION_DAYS): array
{
    $results = [
        'exams' => 0,
        'courses' => 0,
        'batches' => 0,
        'sections' => 0,
        'questions' => 0,
        'users' => 0,
    ];

    $results['questions'] = purgeArchivedQuestions($pdo, $retentionDays);
    $results['sections'] = purgeArchivedSections($pdo, $retentionDays);
    $results['batches'] = purgeArchivedBatches($pdo, $retentionDays);
    $results['courses'] = purgeArchivedCourses($pdo, $retentionDays);
    $results['exams'] = purgeArchivedExams($pdo, $retentionDays);
    $results['users'] = purgeArchivedUsers($pdo, $retentionDays);

    return $results;
}

function purgeArchivedExams(PDO $pdo, int $retentionDays): int
{
    if (!archiveColumnExists($pdo, 'tbl_exam')) {
        return 0;
    }

    $findStmt = $pdo->prepare('
        SELECT e.Exam_ID
        FROM tbl_exam e
        WHERE e.Is_Archived = 1
          AND e.Archived_At IS NOT NULL
          AND e.Archived_At < DATE_SUB(NOW(), INTERVAL ? DAY)
          AND NOT EXISTS (
              SELECT 1 FROM tbl_exam_session es WHERE es.Exam_ID = e.Exam_ID
          )
    ');
    $findStmt->execute([$retentionDays]);
    $examIds = array_map('intval', $findStmt->fetchAll(PDO::FETCH_COLUMN));

    if (empty($examIds)) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($examIds), '?'));

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM tbl_exam_code_attempt WHERE Exam_ID IN ({$placeholders})")->execute($examIds);
        $pdo->prepare("DELETE FROM tbl_exam WHERE Exam_ID IN ({$placeholders})")->execute($examIds);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

    return count($examIds);
}

function purgeArchivedQuestions(PDO $pdo, int $retentionDays): int
{
    if (!archiveColumnExists($pdo, 'tbl_question_bank')) {
        return 0;
    }

    $findStmt = $pdo->prepare('
        SELECT qb.Question_ID
        FROM tbl_question_bank qb
        WHERE qb.Is_Archived = 1
          AND qb.Archived_At IS NOT NULL
          AND qb.Archived_At < DATE_SUB(NOW(), INTERVAL ? DAY)
          AND NOT EXISTS (
              SELECT 1 FROM tbl_exam_question eq WHERE eq.Question_ID = qb.Question_ID
          )
    ');
    $findStmt->execute([$retentionDays]);
    $questionIds = array_map('intval', $findStmt->fetchAll(PDO::FETCH_COLUMN));

    if (empty($questionIds)) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($questionIds), '?'));

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM tbl_choice WHERE Question_ID IN ({$placeholders})")->execute($questionIds);
        $pdo->prepare("DELETE FROM tbl_question_bank WHERE Question_ID IN ({$placeholders})")->execute($questionIds);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

    return count($questionIds);
}

function purgeArchivedSections(PDO $pdo, int $retentionDays): int
{
    if (!archiveColumnExists($pdo, 'tbl_academic_section')) {
        return 0;
    }

    $findStmt = $pdo->prepare('
        SELECT sec.Section_ID
        FROM tbl_academic_section sec
        WHERE sec.Is_Archived = 1
          AND sec.Archived_At IS NOT NULL
          AND sec.Archived_At < DATE_SUB(NOW(), INTERVAL ? DAY)
          AND NOT EXISTS (
              SELECT 1 FROM tbl_user_batch ub
              INNER JOIN tbl_batch eb ON ub.Batch_ID = eb.Batch_ID
              WHERE eb.Section_ID = sec.Section_ID
          )
    ');
    $findStmt->execute([$retentionDays]);
    $sectionIds = array_map('intval', $findStmt->fetchAll(PDO::FETCH_COLUMN));

    if (empty($sectionIds)) {
        return 0;
    }

    $deleted = 0;
    foreach ($sectionIds as $sectionId) {
        $pdo->prepare('DELETE FROM tbl_batch WHERE Section_ID = ? AND Is_Archived = 1')->execute([$sectionId]);
        $stmt = $pdo->prepare('DELETE FROM tbl_academic_section WHERE Section_ID = ? AND Is_Archived = 1');
        $stmt->execute([$sectionId]);
        if ($stmt->rowCount() > 0) {
            $deleted++;
        }
    }

    return $deleted;
}

function purgeArchivedBatches(PDO $pdo, int $retentionDays): int
{
    if (!archiveColumnExists($pdo, 'tbl_batch')) {
        return 0;
    }

    $findStmt = $pdo->prepare('
        SELECT b.Batch_ID
        FROM tbl_batch b
        WHERE b.Is_Archived = 1
          AND b.Archived_At IS NOT NULL
          AND b.Archived_At < DATE_SUB(NOW(), INTERVAL ? DAY)
          AND (b.Section_ID IS NULL OR b.Section_ID = 0)
          AND NOT EXISTS (
              SELECT 1 FROM tbl_academic_section sec
              WHERE sec.Batch_ID = b.Batch_ID
                AND (sec.Is_Archived = 0 OR sec.Is_Archived IS NULL)
          )
          AND NOT EXISTS (
              SELECT 1 FROM tbl_user_batch ub WHERE ub.Batch_ID = b.Batch_ID
          )
    ');
    $findStmt->execute([$retentionDays]);
    $batchIds = array_map('intval', $findStmt->fetchAll(PDO::FETCH_COLUMN));

    if (empty($batchIds)) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($batchIds), '?'));
    $stmt = $pdo->prepare("DELETE FROM tbl_batch WHERE Batch_ID IN ({$placeholders})");
    $stmt->execute($batchIds);

    return count($batchIds);
}

function purgeArchivedCourses(PDO $pdo, int $retentionDays): int
{
    if (!archiveColumnExists($pdo, 'tbl_course')) {
        return 0;
    }

    $findStmt = $pdo->prepare('
        SELECT c.Course_ID
        FROM tbl_course c
        WHERE c.Is_Archived = 1
          AND c.Archived_At IS NOT NULL
          AND c.Archived_At < DATE_SUB(NOW(), INTERVAL ? DAY)
          AND NOT EXISTS (
              SELECT 1 FROM tbl_batch b
              WHERE b.Course_ID = c.Course_ID
                AND (b.Is_Archived = 0 OR b.Is_Archived IS NULL)
          )
          AND NOT EXISTS (
              SELECT 1 FROM tbl_exam e
              INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
              WHERE s.Course_ID = c.Course_ID
          )
    ');
    $findStmt->execute([$retentionDays]);
    $courseIds = array_map('intval', $findStmt->fetchAll(PDO::FETCH_COLUMN));

    if (empty($courseIds)) {
        return 0;
    }

    $deleted = 0;
    foreach ($courseIds as $courseId) {
        $pdo->beginTransaction();
        try {
            purgeArchivedBatchesForCourse($pdo, $courseId, $retentionDays);
            $stmt = $pdo->prepare('DELETE FROM tbl_course WHERE Course_ID = ? AND Is_Archived = 1');
            $stmt->execute([$courseId]);
            if ($stmt->rowCount() > 0) {
                $deleted++;
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    return $deleted;
}

function purgeArchivedBatchesForCourse(PDO $pdo, int $courseId, int $retentionDays): void
{
    if (!archiveColumnExists($pdo, 'tbl_batch')) {
        return;
    }

    $batchStmt = $pdo->prepare('
        SELECT Batch_ID FROM tbl_batch
        WHERE Course_ID = ?
          AND Is_Archived = 1
          AND Archived_At IS NOT NULL
          AND Archived_At < DATE_SUB(NOW(), INTERVAL ? DAY)
    ');
    $batchStmt->execute([$courseId, $retentionDays]);
    $batchIds = array_map('intval', $batchStmt->fetchAll(PDO::FETCH_COLUMN));

    foreach ($batchIds as $batchId) {
        purgeArchivedSectionsForBatch($pdo, $batchId, $retentionDays);
        $pdo->prepare('DELETE FROM tbl_batch WHERE Batch_ID = ?')->execute([$batchId]);
    }
}

function purgeArchivedSectionsForBatch(PDO $pdo, int $batchId, int $retentionDays): void
{
    if (!archiveColumnExists($pdo, 'tbl_academic_section')) {
        return;
    }

    $sectionStmt = $pdo->prepare('
        SELECT Section_ID FROM tbl_academic_section
        WHERE Batch_ID = ?
          AND Is_Archived = 1
          AND Archived_At IS NOT NULL
          AND Archived_At < DATE_SUB(NOW(), INTERVAL ? DAY)
    ');
    $sectionStmt->execute([$batchId, $retentionDays]);
    $sectionIds = array_map('intval', $sectionStmt->fetchAll(PDO::FETCH_COLUMN));

    foreach ($sectionIds as $sectionId) {
        $pdo->prepare('DELETE FROM tbl_academic_section WHERE Section_ID = ?')->execute([$sectionId]);
    }
}

function purgeArchivedUsers(PDO $pdo, int $retentionDays): int
{
    if (!archiveColumnExists($pdo, 'tbl_user')) {
        return 0;
    }

    $findStmt = $pdo->prepare('
        SELECT u.User_ID
        FROM tbl_user u
        WHERE u.Is_Archived = 1
          AND u.Archived_At IS NOT NULL
          AND u.Archived_At < DATE_SUB(NOW(), INTERVAL ? DAY)
          AND NOT EXISTS (
              SELECT 1 FROM tbl_exam_session es WHERE es.User_ID = u.User_ID
          )
    ');
    $findStmt->execute([$retentionDays]);
    $userIds = array_map('intval', $findStmt->fetchAll(PDO::FETCH_COLUMN));

    if (empty($userIds)) {
        return 0;
    }

    $deleted = 0;
    foreach ($userIds as $userId) {
        $stmt = $pdo->prepare('DELETE FROM tbl_user WHERE User_ID = ? AND Is_Archived = 1');
        $stmt->execute([$userId]);
        if ($stmt->rowCount() > 0) {
            $deleted++;
        }
    }

    return $deleted;
}

/**
 * @return array<string,int>
 */
function countEligibleForPurgeAll(PDO $pdo, int $retentionDays = ARCHIVE_RETENTION_DAYS): array
{
    $counts = [
        'exams' => 0,
        'courses' => 0,
        'batches' => 0,
        'sections' => 0,
        'questions' => 0,
        'users' => 0,
        'total' => 0,
    ];

    if (archiveColumnExists($pdo, 'tbl_exam')) {
        $stmt = $pdo->prepare('
            SELECT COUNT(*) FROM tbl_exam e
            WHERE e.Is_Archived = 1
              AND e.Archived_At IS NOT NULL
              AND e.Archived_At < DATE_SUB(NOW(), INTERVAL ? DAY)
              AND NOT EXISTS (SELECT 1 FROM tbl_exam_session es WHERE es.Exam_ID = e.Exam_ID)
        ');
        $stmt->execute([$retentionDays]);
        $counts['exams'] = (int)$stmt->fetchColumn();
    }

    if (archiveColumnExists($pdo, 'tbl_question_bank')) {
        $stmt = $pdo->prepare('
            SELECT COUNT(*) FROM tbl_question_bank qb
            WHERE qb.Is_Archived = 1
              AND qb.Archived_At IS NOT NULL
              AND qb.Archived_At < DATE_SUB(NOW(), INTERVAL ? DAY)
              AND NOT EXISTS (SELECT 1 FROM tbl_exam_question eq WHERE eq.Question_ID = qb.Question_ID)
        ');
        $stmt->execute([$retentionDays]);
        $counts['questions'] = (int)$stmt->fetchColumn();
    }

    if (archiveColumnExists($pdo, 'tbl_academic_section')) {
        $stmt = $pdo->prepare('
            SELECT COUNT(*) FROM tbl_academic_section sec
            WHERE sec.Is_Archived = 1
              AND sec.Archived_At IS NOT NULL
              AND sec.Archived_At < DATE_SUB(NOW(), INTERVAL ? DAY)
              AND NOT EXISTS (
                  SELECT 1 FROM tbl_user_batch ub
                  INNER JOIN tbl_batch eb ON ub.Batch_ID = eb.Batch_ID
                  WHERE eb.Section_ID = sec.Section_ID
              )
        ');
        $stmt->execute([$retentionDays]);
        $counts['sections'] = (int)$stmt->fetchColumn();
    }

    if (archiveColumnExists($pdo, 'tbl_batch')) {
        $stmt = $pdo->prepare('
            SELECT COUNT(*) FROM tbl_batch b
            WHERE b.Is_Archived = 1
              AND b.Archived_At IS NOT NULL
              AND b.Archived_At < DATE_SUB(NOW(), INTERVAL ? DAY)
              AND (b.Section_ID IS NULL OR b.Section_ID = 0)
              AND NOT EXISTS (
                  SELECT 1 FROM tbl_academic_section sec
                  WHERE sec.Batch_ID = b.Batch_ID
                    AND (sec.Is_Archived = 0 OR sec.Is_Archived IS NULL)
              )
              AND NOT EXISTS (
                  SELECT 1 FROM tbl_user_batch ub WHERE ub.Batch_ID = b.Batch_ID
              )
        ');
        $stmt->execute([$retentionDays]);
        $counts['batches'] = (int)$stmt->fetchColumn();
    }

    if (archiveColumnExists($pdo, 'tbl_course')) {
        $stmt = $pdo->prepare('
            SELECT COUNT(*) FROM tbl_course c
            WHERE c.Is_Archived = 1
              AND c.Archived_At IS NOT NULL
              AND c.Archived_At < DATE_SUB(NOW(), INTERVAL ? DAY)
              AND NOT EXISTS (
                  SELECT 1 FROM tbl_batch b
                  WHERE b.Course_ID = c.Course_ID AND (b.Is_Archived = 0 OR b.Is_Archived IS NULL)
              )
              AND NOT EXISTS (
                  SELECT 1 FROM tbl_exam e
                  INNER JOIN tbl_subject s ON e.Subject_ID = s.Subject_ID
                  WHERE s.Course_ID = c.Course_ID
              )
        ');
        $stmt->execute([$retentionDays]);
        $counts['courses'] = (int)$stmt->fetchColumn();
    }

    if (archiveColumnExists($pdo, 'tbl_user')) {
        $stmt = $pdo->prepare('
            SELECT COUNT(*) FROM tbl_user u
            WHERE u.Is_Archived = 1
              AND u.Archived_At IS NOT NULL
              AND u.Archived_At < DATE_SUB(NOW(), INTERVAL ? DAY)
              AND NOT EXISTS (SELECT 1 FROM tbl_exam_session es WHERE es.User_ID = u.User_ID)
        ');
        $stmt->execute([$retentionDays]);
        $counts['users'] = (int)$stmt->fetchColumn();
    }

    $counts['total'] = array_sum([
        $counts['exams'],
        $counts['courses'],
        $counts['batches'],
        $counts['sections'],
        $counts['questions'],
        $counts['users'],
    ]);

    return $counts;
}
