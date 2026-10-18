<?php

/**
 * Resolve enrollment batch for a section (creates one if missing).
 */
function getEnrollmentBatchId(PDO $pdo, $section_id): ?int
{
    // Prefer a non-archived enrollment batch when several exist for the same section.
    $hasArchive = false;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM tbl_batch LIKE 'Is_Archived'");
        $hasArchive = $col && $col->rowCount() > 0;
    } catch (Exception $e) {
        $hasArchive = false;
    }

    if ($hasArchive) {
        $stmt = $pdo->prepare('
            SELECT Batch_ID
            FROM tbl_batch
            WHERE Section_ID = ?
            ORDER BY CASE WHEN Is_Archived = 0 OR Is_Archived IS NULL THEN 0 ELSE 1 END, Batch_ID DESC
            LIMIT 1
        ');
    } else {
        $stmt = $pdo->prepare('SELECT Batch_ID FROM tbl_batch WHERE Section_ID = ? ORDER BY Batch_ID DESC LIMIT 1');
    }
    $stmt->execute([$section_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return (int)$row['Batch_ID'];
    }

    $sectionStmt = $pdo->prepare('
        SELECT s.Section_ID, s.Section_Name, s.Batch_ID as Parent_Batch_ID, b.Course_ID
        FROM tbl_academic_section s
        INNER JOIN tbl_batch b ON s.Batch_ID = b.Batch_ID
        WHERE s.Section_ID = ?
    ');
    $sectionStmt->execute([$section_id]);
    $section = $sectionStmt->fetch(PDO::FETCH_ASSOC);
    if (!$section) {
        return null;
    }

    $createStmt = $pdo->prepare('INSERT INTO tbl_batch (Course_ID, Section_ID, Batch_Name, Status) VALUES (?, ?, ?, ?)');
    $createStmt->execute([
        $section['Course_ID'],
        $section_id,
        $section['Section_Name'] . ' Enrollment',
        'Active'
    ]);

    return (int)$pdo->lastInsertId();
}

/**
 * Delete the enrollment batch linked to a section (if any).
 */
function deleteEnrollmentBatchForSection(PDO $pdo, $section_id): void
{
    $stmt = $pdo->prepare('DELETE FROM tbl_batch WHERE Section_ID = ?');
    $stmt->execute([$section_id]);
}

/**
 * Delete enrollment batches for all sections under a parent course batch.
 */
function deleteEnrollmentBatchesForParentBatch(PDO $pdo, $parent_batch_id): void
{
    $stmt = $pdo->prepare('SELECT Section_ID FROM tbl_academic_section WHERE Batch_ID = ?');
    $stmt->execute([$parent_batch_id]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        deleteEnrollmentBatchForSection($pdo, $row['Section_ID']);
    }
}

/**
 * SQL fragment: exclude orphaned enrollment batches from course-level batch lists.
 */
function courseBatchVisibilityCondition(string $batchAlias = 'b'): string
{
    $batch = $batchAlias;
    return "({$batch}.Section_ID IS NULL OR {$batch}.Section_ID = 0)
            AND (
                EXISTS (SELECT 1 FROM tbl_academic_section s WHERE s.Batch_ID = {$batch}.Batch_ID)
                OR {$batch}.Batch_Name NOT LIKE '% Enrollment'
            )";
}

/**
 * Remove orphaned enrollment batches left from prior deletes (Section_ID cleared by FK).
 */
function deleteOrphanedEnrollmentBatchesForCourse(PDO $pdo, $course_id): void
{
    $stmt = $pdo->prepare("
        DELETE FROM tbl_batch
        WHERE Course_ID = ?
          AND (Section_ID IS NULL OR Section_ID = 0)
          AND Batch_Name LIKE '% Enrollment'
          AND NOT EXISTS (
              SELECT 1 FROM tbl_academic_section s WHERE s.Batch_ID = tbl_batch.Batch_ID
          )
    ");
    $stmt->execute([$course_id]);
}

/**
 * Check whether tbl_user_batch exists.
 */
function examineeEnrollmentTableExists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'tbl_user_batch'");
        $exists = $stmt && $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $exists = false;
    }

    return $exists;
}

/**
 * Get an examinee's active enrollment, optionally excluding a target batch.
 */
function getExamineeActiveEnrollment(PDO $pdo, int $userId, ?int $excludeBatchId = null): ?array
{
    if (!examineeEnrollmentTableExists($pdo) || $userId <= 0) {
        return null;
    }

    $sql = '
        SELECT ub.Batch_ID,
               b.Section_ID,
               c.Course_Name,
               s.Section_Name,
               COALESCE(pb.Batch_Name, b.Batch_Name) AS Batch_Name
        FROM tbl_user_batch ub
        INNER JOIN tbl_batch b ON ub.Batch_ID = b.Batch_ID
        INNER JOIN tbl_course c ON b.Course_ID = c.Course_ID
        LEFT JOIN tbl_academic_section s ON b.Section_ID = s.Section_ID
        LEFT JOIN tbl_batch pb ON s.Batch_ID = pb.Batch_ID
        WHERE ub.User_ID = ? AND ub.Status = ?
    ';
    $params = [$userId, 'Active'];

    if ($excludeBatchId !== null && $excludeBatchId > 0) {
        $sql .= ' AND ub.Batch_ID != ?';
        $params[] = $excludeBatchId;
    }

    $sql .= ' ORDER BY ub.Date_Enrolled DESC LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function formatExamineeEnrollmentLabel(array $enrollment): string
{
    $course = trim((string)($enrollment['Course_Name'] ?? '')) ?: 'Unknown course';
    $section = trim((string)($enrollment['Section_Name'] ?? '')) ?: 'Unknown section';
    $batch = trim((string)($enrollment['Batch_Name'] ?? '')) ?: 'Unknown batch';

    return "{$course} → {$section} → {$batch}";
}

/**
 * Block assignment when examinee already has a different active enrollment.
 */
function validateExamineeSingleEnrollment(PDO $pdo, int $userId, int $targetBatchId): ?string
{
    $existing = getExamineeActiveEnrollment($pdo, $userId, $targetBatchId);
    if ($existing === null) {
        return null;
    }

    return 'Trainee is already assigned to ' . formatExamineeEnrollmentLabel($existing)
        . '. Remove them from that assignment first.';
}

/**
 * User IDs with an active batch enrollment.
 *
 * @return int[]
 */
function getEnrolledExamineeUserIds(PDO $pdo): array
{
    if (!examineeEnrollmentTableExists($pdo)) {
        return [];
    }

    $stmt = $pdo->query('
        SELECT DISTINCT ub.User_ID
        FROM tbl_user_batch ub
        WHERE ub.Status = "Active"
    ');

    if (!$stmt) {
        return [];
    }

    return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'User_ID'));
}

/**
 * Resolve section ID from an enrollment batch.
 */
function getSectionIdForEnrollmentBatch(PDO $pdo, int $batchId): ?int
{
    $stmt = $pdo->prepare('SELECT Section_ID FROM tbl_batch WHERE Batch_ID = ?');
    $stmt->execute([$batchId]);
    $sectionId = $stmt->fetchColumn();

    return $sectionId ? (int)$sectionId : null;
}

/**
 * @return array{valid:bool, message?:string, capacity?:int, assigned_count?:int, available_slots?:int}
 */
function validateSectionCapacityForEnrollmentBatch(PDO $pdo, int $enrollmentBatchId, array $userIds): array
{
    if (!examineeEnrollmentTableExists($pdo)) {
        return ['valid' => false, 'message' => 'Enrollment table not available. Please run the database migration.'];
    }

    $sectionId = getSectionIdForEnrollmentBatch($pdo, $enrollmentBatchId);
    if (!$sectionId) {
        return ['valid' => false, 'message' => 'Batch is not linked to a section'];
    }

    $sectionStmt = $pdo->prepare('SELECT Section_ID, Section_Name, Capacity FROM tbl_academic_section WHERE Section_ID = ?');
    $sectionStmt->execute([$sectionId]);
    $section = $sectionStmt->fetch(PDO::FETCH_ASSOC);
    if (!$section) {
        return ['valid' => false, 'message' => 'Section not found'];
    }

    $capacity = (int)$section['Capacity'];
    $assignedStmt = $pdo->prepare('
        SELECT COUNT(DISTINCT ub.User_ID)
        FROM tbl_user_batch ub
        INNER JOIN tbl_batch b ON ub.Batch_ID = b.Batch_ID
        WHERE b.Section_ID = ?
    ');
    $assignedStmt->execute([$sectionId]);
    $assignedCount = (int)$assignedStmt->fetchColumn();

    $alreadyStmt = $pdo->prepare('
        SELECT DISTINCT ub.User_ID
        FROM tbl_user_batch ub
        INNER JOIN tbl_batch b ON ub.Batch_ID = b.Batch_ID
        WHERE b.Section_ID = ?
    ');
    $alreadyStmt->execute([$sectionId]);
    $alreadyInSection = array_flip(array_map('intval', $alreadyStmt->fetchAll(PDO::FETCH_COLUMN)));

    $newCount = 0;
    $seen = [];
    foreach ($userIds as $userId) {
        $userId = (int)$userId;
        if ($userId <= 0 || isset($seen[$userId])) {
            continue;
        }
        $seen[$userId] = true;
        if (!isset($alreadyInSection[$userId])) {
            $newCount++;
        }
    }

    if ($assignedCount + $newCount > $capacity) {
        require_once __DIR__ . '/validation-utils.php';

        return [
            'valid' => false,
            'message' => formatSectionCapacityMessage($capacity, $assignedCount),
            'capacity' => $capacity,
            'assigned_count' => $assignedCount,
            'available_slots' => max(0, $capacity - $assignedCount),
        ];
    }

    return [
        'valid' => true,
        'capacity' => $capacity,
        'assigned_count' => $assignedCount,
        'available_slots' => max(0, $capacity - $assignedCount),
    ];
}

/**
 * Assign an examinee to batch.
 *
 * @return array{success:bool, message:string, assigned?:bool, reactivated?:bool}
 */
function assignExamineeToEnrollmentBatch(PDO $pdo, int $userId, int $enrollmentBatchId, string $status = 'Active'): array
{
    if (!examineeEnrollmentTableExists($pdo)) {
        return ['success' => false, 'message' => 'Enrollment table not available. Please run the database migration.', 'assigned' => false];
    }

    $roleCheck = $pdo->prepare('
        SELECT r.Role_Name
        FROM tbl_user u
        INNER JOIN tbl_role r ON u.Role_ID = r.Role_ID
        WHERE u.User_ID = ?
        LIMIT 1
    ');
    $roleCheck->execute([$userId]);
    $roleName = (string)($roleCheck->fetchColumn() ?: '');

    if ($roleName !== 'Examinee') {
        return ['success' => false, 'message' => 'Only trainees can be assigned to batches', 'assigned' => false];
    }

    $status = $status !== '' ? $status : 'Active';

    $existing = $pdo->prepare('SELECT User_Batch_ID, Status FROM tbl_user_batch WHERE User_ID = ? AND Batch_ID = ? LIMIT 1');
    $existing->execute([$userId, $enrollmentBatchId]);
    $row = $existing->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        if (($row['Status'] ?? '') === 'Active') {
            return ['success' => true, 'message' => 'User already assigned to batch', 'assigned' => false];
        }

        $singleEnrollmentError = validateExamineeSingleEnrollment($pdo, $userId, $enrollmentBatchId);
        if ($singleEnrollmentError !== null) {
            return ['success' => false, 'message' => $singleEnrollmentError, 'assigned' => false];
        }

        $upd = $pdo->prepare('UPDATE tbl_user_batch SET Status = ?, Date_Enrolled = NOW() WHERE User_Batch_ID = ?');
        $upd->execute([$status, (int)$row['User_Batch_ID']]);

        return ['success' => true, 'message' => 'User assignment reactivated successfully', 'assigned' => true, 'reactivated' => true];
    }

    $singleEnrollmentError = validateExamineeSingleEnrollment($pdo, $userId, $enrollmentBatchId);
    if ($singleEnrollmentError !== null) {
        return ['success' => false, 'message' => $singleEnrollmentError, 'assigned' => false];
    }

    $stmt = $pdo->prepare('INSERT INTO tbl_user_batch (User_ID, Batch_ID, Status, Date_Enrolled) VALUES (?, ?, ?, NOW())');
    $stmt->execute([$userId, $enrollmentBatchId, $status]);

    return ['success' => true, 'message' => 'User assigned to batch successfully', 'assigned' => true];
}

/**
 * Remove all batch/section enrollments for a user (course access via tbl_user_batch).
 */
function removeAllUserBatchEnrollments(PDO $pdo, int $userId): int
{
    if ($userId <= 0 || !examineeEnrollmentTableExists($pdo)) {
        return 0;
    }

    $stmt = $pdo->prepare('DELETE FROM tbl_user_batch WHERE User_ID = ?');
    $stmt->execute([$userId]);

    return $stmt->rowCount();
}

/**
 * Remove user from all exam participant allowlists.
 */
function removeUserFromAllExamParticipants(PDO $pdo, int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }

    try {
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'tbl_exam_participant'");
        if (!$tableCheck || $tableCheck->rowCount() === 0) {
            return 0;
        }
    } catch (Exception $e) {
        return 0;
    }

    $stmt = $pdo->prepare('DELETE FROM tbl_exam_participant WHERE User_ID = ?');
    $stmt->execute([$userId]);

    return $stmt->rowCount();
}

/**
 * Drop curriculum links when a user is archived (batches/sections and exam participants).
 *
 * @return array{batch_enrollments_removed:int, exam_participants_removed:int}
 */
function clearUserCurriculumAssignments(PDO $pdo, int $userId): array
{
    return [
        'batch_enrollments_removed' => removeAllUserBatchEnrollments($pdo, $userId),
        'exam_participants_removed' => removeUserFromAllExamParticipants($pdo, $userId),
    ];
}
