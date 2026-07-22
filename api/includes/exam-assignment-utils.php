<?php

/**
 * Exam batch/section assignment helpers.
 */

function examBatchHasSectionColumn(PDO $pdo): bool
{
    static $hasSection = null;
    if ($hasSection !== null) {
        return $hasSection;
    }

    $columns = $pdo->query('SHOW COLUMNS FROM tbl_batch')->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'Field');
    $hasSection = in_array('Section_ID', $columnNames, true);

    return $hasSection;
}

function formatExamAssignmentLabel(?string $batchName, ?string $sectionName): string
{
    $batch = trim((string)$batchName);
    $section = trim((string)$sectionName);

    if ($batch !== '' && $section !== '' && strcasecmp($batch, $section) !== 0) {
        return $batch . ' • ' . $section;
    }

    return $batch !== '' ? $batch : ($section !== '' ? $section : 'Unknown');
}

function normalizeExamAssignmentRow(array $row): array
{
    $batchName = $row['Batch_Name'] ?? '';
    $sectionName = $row['Section_Name'] ?? '';

    return [
        'batch_id' => isset($row['Batch_ID']) ? (int)$row['Batch_ID'] : null,
        'batch_name' => $batchName,
        'section_id' => !empty($row['Section_ID']) ? (int)$row['Section_ID'] : null,
        'section_name' => $sectionName,
        'label' => formatExamAssignmentLabel($batchName, $sectionName),
    ];
}

function fetchExamAssignmentsMap(PDO $pdo, array $examIds): array
{
    $examIds = array_values(array_unique(array_filter(array_map('intval', $examIds))));
    if (empty($examIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($examIds), '?'));

    if (examBatchHasSectionColumn($pdo)) {
        $sql = "
            SELECT
                eb.Exam_ID,
                eb.Batch_ID,
                COALESCE(pb.Batch_Name, b.Batch_Name) AS Batch_Name,
                COALESCE(s.Section_ID, b.Section_ID) AS Section_ID,
                COALESCE(s.Section_Name, b.Batch_Name) AS Section_Name
            FROM tbl_exam_batch eb
            INNER JOIN tbl_batch b ON eb.Batch_ID = b.Batch_ID
            LEFT JOIN tbl_academic_section s ON b.Section_ID = s.Section_ID
            LEFT JOIN tbl_batch pb ON s.Batch_ID = pb.Batch_ID
            WHERE eb.Exam_ID IN ({$placeholders})
            ORDER BY eb.Exam_ID, Batch_Name, Section_Name
        ";
    } else {
        $sql = "
            SELECT
                eb.Exam_ID,
                eb.Batch_ID,
                b.Batch_Name,
                NULL AS Section_ID,
                b.Batch_Name AS Section_Name
            FROM tbl_exam_batch eb
            INNER JOIN tbl_batch b ON eb.Batch_ID = b.Batch_ID
            WHERE eb.Exam_ID IN ({$placeholders})
            ORDER BY eb.Exam_ID, b.Batch_Name
        ";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($examIds);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $map = [];
    foreach ($rows as $row) {
        $examId = (int)$row['Exam_ID'];
        if (!isset($map[$examId])) {
            $map[$examId] = [];
        }
        $map[$examId][] = normalizeExamAssignmentRow($row);
    }

    return $map;
}

function fetchExamAssignments(PDO $pdo, int $examId): array
{
    $map = fetchExamAssignmentsMap($pdo, [$examId]);
    return $map[$examId] ?? [];
}

function buildExamAssignmentsSummary(array $assignments): string
{
    if (empty($assignments)) {
        return '';
    }

    return implode('; ', array_column($assignments, 'label'));
}

function userExamEnrollmentSelectSql(PDO $pdo, string $userAlias = 'u'): string
{
    if (examBatchHasSectionColumn($pdo)) {
        return "
            (
                SELECT GROUP_CONCAT(
                    DISTINCT COALESCE(pb.Batch_Name, b.Batch_Name)
                    ORDER BY COALESCE(pb.Batch_Name, b.Batch_Name)
                    SEPARATOR ', '
                )
                FROM tbl_user_batch ub
                INNER JOIN tbl_exam_batch eb ON eb.Batch_ID = ub.Batch_ID AND eb.Exam_ID = ?
                INNER JOIN tbl_batch b ON ub.Batch_ID = b.Batch_ID
                LEFT JOIN tbl_academic_section s ON b.Section_ID = s.Section_ID
                LEFT JOIN tbl_batch pb ON s.Batch_ID = pb.Batch_ID
                WHERE ub.User_ID = {$userAlias}.User_ID
                  AND ub.Status = 'Active'
            ) AS batch_names,
            (
                SELECT GROUP_CONCAT(
                    DISTINCT COALESCE(s.Section_Name, b.Batch_Name)
                    ORDER BY COALESCE(s.Section_Name, b.Batch_Name)
                    SEPARATOR ', '
                )
                FROM tbl_user_batch ub
                INNER JOIN tbl_exam_batch eb ON eb.Batch_ID = ub.Batch_ID AND eb.Exam_ID = ?
                INNER JOIN tbl_batch b ON ub.Batch_ID = b.Batch_ID
                LEFT JOIN tbl_academic_section s ON b.Section_ID = s.Section_ID
                WHERE ub.User_ID = {$userAlias}.User_ID
                  AND ub.Status = 'Active'
            ) AS section_names
        ";
    }

    return "
        (
            SELECT GROUP_CONCAT(DISTINCT b.Batch_Name ORDER BY b.Batch_Name SEPARATOR ', ')
            FROM tbl_user_batch ub
            INNER JOIN tbl_exam_batch eb ON eb.Batch_ID = ub.Batch_ID AND eb.Exam_ID = ?
            INNER JOIN tbl_batch b ON ub.Batch_ID = b.Batch_ID
            WHERE ub.User_ID = {$userAlias}.User_ID
              AND ub.Status = 'Active'
        ) AS batch_names,
        NULL AS section_names
    ";
}

function userExamEnrollmentPlaceholderCount(PDO $pdo): int
{
    return examBatchHasSectionColumn($pdo) ? 2 : 1;
}

function prependExamEnrollmentParams(PDO $pdo, int $examId, array $params = []): array
{
    $prefix = array_fill(0, userExamEnrollmentPlaceholderCount($pdo), $examId);
    return array_merge($prefix, $params);
}

function attachExamAssignments(PDO $pdo, array &$exams): void
{
    if (empty($exams)) {
        return;
    }

    $examIds = array_column($exams, 'Exam_ID');
    $map = fetchExamAssignmentsMap($pdo, $examIds);

    foreach ($exams as &$exam) {
        $examId = (int)($exam['Exam_ID'] ?? 0);
        $assignments = $map[$examId] ?? [];
        $exam['Assignments'] = $assignments;
        $exam['Assignment_Summary'] = buildExamAssignmentsSummary($assignments);
    }
    unset($exam);
}

/**
 * When participants exist but tbl_exam_batch is empty, repair links and refresh assignment labels.
 */
function ensureExamAssignmentsFromParticipants(PDO $pdo, array &$exams): void
{
    if (empty($exams)) {
        return;
    }

    require_once __DIR__ . '/exam-participant-utils.php';
    if (!examParticipantTableExists($pdo)) {
        return;
    }

    foreach ($exams as &$exam) {
        $examId = (int)($exam['Exam_ID'] ?? 0);
        if ($examId <= 0 || !empty($exam['Assignments'])) {
            continue;
        }

        if (countExamParticipants($pdo, $examId) === 0) {
            continue;
        }

        repairExamBatchAssignmentsFromParticipants($pdo, $examId);
        $assignments = fetchExamAssignments($pdo, $examId);
        if (empty($assignments)) {
            $assignments = inferExamAssignmentsFromParticipants($pdo, $examId);
        }

        $exam['Assignments'] = $assignments;
        $exam['Assignment_Summary'] = buildExamAssignmentsSummary($assignments);
    }
    unset($exam);
}
