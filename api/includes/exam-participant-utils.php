<?php

/**
 * Per-exam participant allowlist helpers.
 * Sections define the pool; tbl_exam_participant defines who may access the exam.
 */

function examParticipantTableExists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'tbl_exam_participant'");
        $exists = (bool)$stmt->fetch();
    } catch (PDOException $e) {
        $exists = false;
    }

    return $exists;
}

/**
 * Section IDs covered by an exam's batch assignments (enrollment or parent batch).
 */
function getExamAssignedSectionIds(PDO $pdo, int $examId): array
{
    if ($examId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare('
        SELECT DISTINCT sec.Section_ID
        FROM tbl_exam_batch eb
        INNER JOIN tbl_batch assigned_batch ON eb.Batch_ID = assigned_batch.Batch_ID
        INNER JOIN tbl_academic_section sec ON (
            sec.Section_ID = assigned_batch.Section_ID
            OR (
                (assigned_batch.Section_ID IS NULL OR assigned_batch.Section_ID = 0)
                AND sec.Batch_ID = assigned_batch.Batch_ID
            )
        )
        WHERE eb.Exam_ID = ?
        ORDER BY sec.Section_ID
    ');
    $stmt->execute([$examId]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function getEligibleExamParticipants(PDO $pdo, int $examId): array
{
    if ($examId <= 0) {
        return [];
    }

    $sectionIds = getExamAssignedSectionIds($pdo, $examId);
    if (empty($sectionIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($sectionIds), '?'));
    $sql = "
        SELECT DISTINCT
            u.User_ID,
            u.Fullname,
            u.Academic_Number,
            ub.Status AS Enrollment_Status,
            sec.Section_ID,
            sec.Section_Name,
            COALESCE(pb.Batch_Name, parent_b.Batch_Name, enroll_b.Batch_Name) AS Batch_Name,
            c.Course_Name
        FROM tbl_academic_section sec
        INNER JOIN tbl_batch enroll_b ON enroll_b.Section_ID = sec.Section_ID
        INNER JOIN tbl_user_batch ub ON ub.Batch_ID = enroll_b.Batch_ID
        INNER JOIN tbl_user u ON ub.User_ID = u.User_ID
        INNER JOIN tbl_role r ON u.Role_ID = r.Role_ID
        INNER JOIN tbl_batch parent_b ON sec.Batch_ID = parent_b.Batch_ID
        LEFT JOIN tbl_batch pb ON sec.Batch_ID = pb.Batch_ID
        INNER JOIN tbl_course c ON parent_b.Course_ID = c.Course_ID
        WHERE sec.Section_ID IN ({$placeholders})
          AND ub.Status = 'Active'
          AND r.Role_Name = 'Examinee'
        ORDER BY c.Course_Name, Batch_Name, sec.Section_Name, u.Fullname
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($sectionIds);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $seen = [];
    $participants = [];
    foreach ($rows as $row) {
        $userId = (int)$row['User_ID'];
        if (isset($seen[$userId])) {
            continue;
        }
        $seen[$userId] = true;
        $participants[] = $row;
    }

    return $participants;
}

/**
 * Examinees shown on CCMD live monitor: eligible pool, narrowed to tbl_exam_participant when set.
 */
function listExamMonitorExaminees(PDO $pdo, int $examId): array
{
    if ($examId <= 0) {
        return [];
    }

    $eligible = getEligibleExamParticipants($pdo, $examId);
    if (!examParticipantTableExists($pdo)) {
        return $eligible;
    }

    $selectedIds = getExamParticipantIds($pdo, $examId);
    if (empty($selectedIds)) {
        return $eligible;
    }

    $eligibleByUser = [];
    foreach ($eligible as $row) {
        $eligibleByUser[(int)$row['User_ID']] = $row;
    }

    $filtered = [];
    foreach ($selectedIds as $userId) {
        $userId = (int)$userId;
        if (isset($eligibleByUser[$userId])) {
            $filtered[] = $eligibleByUser[$userId];
        }
    }

    return mergeEligibleWithSavedParticipants($pdo, $filtered, $selectedIds);
}

/**
 * Load examinee profile rows for saved participant IDs (fallback when batch links drift).
 */
function fetchParticipantUsersByIds(PDO $pdo, array $userIds): array
{
    $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
    if (empty($userIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $sql = "
        SELECT DISTINCT
            u.User_ID,
            u.Fullname,
            u.Academic_Number,
            ub.Status AS Enrollment_Status,
            COALESCE(sec.Section_ID, 0) AS Section_ID,
            COALESCE(sec.Section_Name, b.Batch_Name) AS Section_Name,
            COALESCE(pb.Batch_Name, b.Batch_Name) AS Batch_Name,
            COALESCE(c.Course_Name, '') AS Course_Name
        FROM tbl_user u
        INNER JOIN tbl_role r ON u.Role_ID = r.Role_ID
        INNER JOIN tbl_user_batch ub ON ub.User_ID = u.User_ID
        INNER JOIN tbl_batch b ON ub.Batch_ID = b.Batch_ID
        LEFT JOIN tbl_academic_section sec ON b.Section_ID = sec.Section_ID
        LEFT JOIN tbl_batch pb ON sec.Batch_ID = pb.Batch_ID
        LEFT JOIN tbl_course c ON pb.Course_ID = c.Course_ID
        WHERE u.User_ID IN ({$placeholders})
          AND ub.Status = 'Active'
          AND r.Role_Name = 'Examinee'
        ORDER BY c.Course_Name, Batch_Name, Section_Name, u.Fullname
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($userIds);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function mergeEligibleWithSavedParticipants(PDO $pdo, array $eligible, array $selectedIds): array
{
    if (empty($selectedIds)) {
        return $eligible;
    }

    $eligibleByUser = [];
    foreach ($eligible as $row) {
        $eligibleByUser[(int)$row['User_ID']] = true;
    }

    $missing = [];
    foreach ($selectedIds as $userId) {
        $id = (int)$userId;
        if ($id > 0 && !isset($eligibleByUser[$id])) {
            $missing[] = $id;
        }
    }

    if (empty($missing)) {
        return $eligible;
    }

    $seen = array_fill_keys(array_keys($eligibleByUser), true);
    foreach (fetchParticipantUsersByIds($pdo, $missing) as $row) {
        $userId = (int)$row['User_ID'];
        if (isset($seen[$userId])) {
            continue;
        }
        $seen[$userId] = true;
        $eligible[] = $row;
    }

    return $eligible;
}

function getExamParticipantIds(PDO $pdo, int $examId): array
{
    if (!examParticipantTableExists($pdo) || $examId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare('SELECT User_ID FROM tbl_exam_participant WHERE Exam_ID = ? ORDER BY User_ID');
    $stmt->execute([$examId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function countExamParticipants(PDO $pdo, int $examId): int
{
    if (!examParticipantTableExists($pdo) || $examId <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM tbl_exam_participant WHERE Exam_ID = ?');
    $stmt->execute([$examId]);
    return (int)$stmt->fetchColumn();
}

function isExamParticipant(PDO $pdo, int $examId, int $userId): bool
{
    if ($examId <= 0 || $userId <= 0) {
        return false;
    }

    if (!examParticipantTableExists($pdo)) {
        return isUserInExamAssignedSections($pdo, $examId, $userId);
    }

    $count = countExamParticipants($pdo, $examId);
    if ($count === 0) {
        return isUserInExamAssignedSections($pdo, $examId, $userId);
    }

    $stmt = $pdo->prepare('SELECT 1 FROM tbl_exam_participant WHERE Exam_ID = ? AND User_ID = ? LIMIT 1');
    $stmt->execute([$examId, $userId]);
    return (bool)$stmt->fetchColumn();
}

function isUserInExamAssignedSections(PDO $pdo, int $examId, int $userId): bool
{
    if ($examId <= 0 || $userId <= 0) {
        return false;
    }

    $sectionIds = getExamAssignedSectionIds($pdo, $examId);
    if (empty($sectionIds)) {
        return false;
    }

    $placeholders = implode(',', array_fill(0, count($sectionIds), '?'));
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM tbl_user_batch ub
        INNER JOIN tbl_batch enroll_b ON ub.Batch_ID = enroll_b.Batch_ID
        WHERE enroll_b.Section_ID IN ({$placeholders})
          AND ub.User_ID = ?
          AND ub.Status = 'Active'
    ");
    $params = array_merge($sectionIds, [$userId]);
    $stmt->execute($params);

    return (int)$stmt->fetchColumn() > 0;
}

/**
 * Add all eligible examinees from assigned sections; remove participants no longer eligible.
 */
function syncExamParticipantsFromSections(PDO $pdo, int $examId): array
{
    if (!examParticipantTableExists($pdo) || $examId <= 0) {
        return ['added' => 0, 'removed' => 0, 'total' => 0];
    }

    $eligible = getEligibleExamParticipants($pdo, $examId);
    $eligibleIds = array_map(fn($row) => (int)$row['User_ID'], $eligible);
    $eligibleSet = array_fill_keys($eligibleIds, true);

    $currentIds = getExamParticipantIds($pdo, $examId);
    $removed = 0;
    $added = 0;

    if (!empty($currentIds)) {
        foreach ($currentIds as $userId) {
            if (!isset($eligibleSet[$userId])) {
                $del = $pdo->prepare('DELETE FROM tbl_exam_participant WHERE Exam_ID = ? AND User_ID = ?');
                $del->execute([$examId, $userId]);
                $removed++;
            }
        }
    }

    $currentSet = array_fill_keys(getExamParticipantIds($pdo, $examId), true);
    $insert = $pdo->prepare('INSERT INTO tbl_exam_participant (Exam_ID, User_ID, Date_Assigned) VALUES (?, ?, NOW())');

    foreach ($eligibleIds as $userId) {
        if (!isset($currentSet[$userId])) {
            $insert->execute([$examId, $userId]);
            $added++;
        }
    }

    return [
        'added' => $added,
        'removed' => $removed,
        'total' => countExamParticipants($pdo, $examId),
        'eligible' => count($eligibleIds),
    ];
}

function saveExamParticipants(PDO $pdo, int $examId, array $userIds): array
{
    if (!examParticipantTableExists($pdo)) {
        throw new RuntimeException('Participant table is not available. Run the exam participants migration first.');
    }

    $eligible = getEligibleExamParticipants($pdo, $examId);
    $eligible = mergeEligibleWithSavedParticipants($pdo, $eligible, $userIds);
    $eligibleSet = [];
    foreach ($eligible as $row) {
        $eligibleSet[(int)$row['User_ID']] = true;
    }

    $normalized = [];
    foreach ($userIds as $userId) {
        $id = (int)$userId;
        if ($id <= 0 || !isset($eligibleSet[$id])) {
            continue;
        }
        $normalized[$id] = true;
    }
    $selectedIds = array_keys($normalized);

    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM tbl_exam_participant WHERE Exam_ID = ?')->execute([$examId]);

        if (!empty($selectedIds)) {
            $insert = $pdo->prepare('INSERT INTO tbl_exam_participant (Exam_ID, User_ID, Date_Assigned) VALUES (?, ?, NOW())');
            foreach ($selectedIds as $userId) {
                $insert->execute([$examId, $userId]);
            }
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return [
        'selected' => count($selectedIds),
        'eligible' => count($eligibleSet),
    ];
}

function attachExamParticipantCounts(PDO $pdo, array &$exams): void
{
    if (empty($exams) || !examParticipantTableExists($pdo)) {
        foreach ($exams as &$exam) {
            $exam['Participant_Count'] = 0;
            $exam['Eligible_Participant_Count'] = 0;
        }
        unset($exam);
        return;
    }

    $examIds = array_map('intval', array_column($exams, 'Exam_ID'));
    $placeholders = implode(',', array_fill(0, count($examIds), '?'));

    $stmt = $pdo->prepare("SELECT Exam_ID, COUNT(*) AS total FROM tbl_exam_participant WHERE Exam_ID IN ({$placeholders}) GROUP BY Exam_ID");
    $stmt->execute($examIds);
    $counts = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $counts[(int)$row['Exam_ID']] = (int)$row['total'];
    }

    foreach ($exams as &$exam) {
        $examId = (int)($exam['Exam_ID'] ?? 0);
        $exam['Participant_Count'] = $counts[$examId] ?? 0;
        $exam['Eligible_Participant_Count'] = count(getEligibleExamParticipants($pdo, $examId));
    }
    unset($exam);
}

function groupEligibleParticipantsBySection(array $eligible, array $selectedIds): array
{
    $selectedSet = array_fill_keys(array_map('intval', $selectedIds), true);
    $groups = [];

    foreach ($eligible as $row) {
        $sectionKey = (int)($row['Section_ID'] ?? 0) . '|' . ($row['Section_Name'] ?? '');
        if (!isset($groups[$sectionKey])) {
            $groups[$sectionKey] = [
                'section_id' => (int)($row['Section_ID'] ?? 0),
                'section_name' => $row['Section_Name'] ?? '',
                'batch_name' => $row['Batch_Name'] ?? '',
                'course_name' => $row['Course_Name'] ?? '',
                'participants' => [],
            ];
        }

        $userId = (int)$row['User_ID'];
        $groups[$sectionKey]['participants'][] = [
            'user_id' => $userId,
            'fullname' => $row['Fullname'] ?? '',
            'academic_number' => $row['Academic_Number'] ?? '',
            'is_selected' => isset($selectedSet[$userId]),
        ];
    }

    return array_values($groups);
}

/**
 * Infer section assignments from saved participants' enrollments (display / repair fallback).
 */
function inferExamAssignmentsFromParticipants(PDO $pdo, int $examId): array
{
    if ($examId <= 0 || !examParticipantTableExists($pdo)) {
        return [];
    }

    require_once __DIR__ . '/exam-assignment-utils.php';

    $stmt = $pdo->prepare("
        SELECT DISTINCT
            sec.Section_ID,
            sec.Section_Name,
            COALESCE(pb.Batch_Name, parent_b.Batch_Name, enroll_b.Batch_Name) AS Batch_Name
        FROM tbl_exam_participant ep
        INNER JOIN tbl_user_batch ub ON ep.User_ID = ub.User_ID AND ub.Status = 'Active'
        INNER JOIN tbl_batch enroll_b ON ub.Batch_ID = enroll_b.Batch_ID
        INNER JOIN tbl_academic_section sec ON enroll_b.Section_ID = sec.Section_ID
        INNER JOIN tbl_batch parent_b ON sec.Batch_ID = parent_b.Batch_ID
        LEFT JOIN tbl_batch pb ON sec.Batch_ID = pb.Batch_ID
        WHERE ep.Exam_ID = ?
          AND enroll_b.Section_ID IS NOT NULL
        ORDER BY Batch_Name, sec.Section_Name
    ");
    $stmt->execute([$examId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $assignments = [];
    foreach ($rows as $row) {
        $assignments[] = normalizeExamAssignmentRow([
            'Batch_Name' => $row['Batch_Name'] ?? '',
            'Section_ID' => $row['Section_ID'] ?? null,
            'Section_Name' => $row['Section_Name'] ?? '',
        ]);
    }

    return $assignments;
}

/**
 * Re-create missing tbl_exam_batch rows from saved participant enrollments.
 */
function repairExamBatchAssignmentsFromParticipants(PDO $pdo, int $examId): int
{
    if ($examId <= 0 || !examParticipantTableExists($pdo)) {
        return 0;
    }

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM tbl_exam_batch WHERE Exam_ID = ?');
    $countStmt->execute([$examId]);
    if ((int)$countStmt->fetchColumn() > 0) {
        return 0;
    }

    if (countExamParticipants($pdo, $examId) === 0) {
        return 0;
    }

    require_once __DIR__ . '/batch-enrollment-utils.php';

    $inferred = inferExamAssignmentsFromParticipants($pdo, $examId);
    if (empty($inferred)) {
        return 0;
    }

    $insert = $pdo->prepare('INSERT INTO tbl_exam_batch (Exam_ID, Batch_ID, Date_Assigned) VALUES (?, ?, NOW())');
    $added = 0;
    $seenBatchIds = [];

    foreach ($inferred as $assignment) {
        $sectionId = (int)($assignment['section_id'] ?? 0);
        if ($sectionId <= 0) {
            continue;
        }

        $batchId = getEnrollmentBatchId($pdo, $sectionId);
        if (!$batchId || isset($seenBatchIds[$batchId])) {
            continue;
        }

        $seenBatchIds[$batchId] = true;
        $insert->execute([$examId, $batchId]);
        $added++;
    }

    return $added;
}
