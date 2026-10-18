<?php

// Exam batch/section assignment

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

    // Enrollment helper batches are named "{Section} Enrollment" — never use that as the display batch.
    if ($batch !== '' && preg_match('/\s+Enrollment$/i', $batch)) {
        $batch = trim(preg_replace('/\s+Enrollment$/i', '', $batch));
        // After stripping, bare section-name leftovers are not a real parent batch label.
        if ($section !== '' && strcasecmp($batch, $section) === 0) {
            $batch = '';
        }
    }

    // Always show "Batch • Section" when both are known
    if ($batch !== '' && $section !== '') {
        return $batch . ' • ' . $section;
    }

    return $batch !== '' ? $batch : ($section !== '' ? $section : 'Unknown');
}

function normalizeExamAssignmentRow(array $row): array
{
    $parentBatchId = !empty($row['Parent_Batch_ID']) ? (int)$row['Parent_Batch_ID'] : null;
    $batchName = trim((string)($row['Parent_Batch_Name'] ?? $row['Batch_Name'] ?? ''));
    $sectionName = trim((string)($row['Section_Name'] ?? ''));
    $sectionId = !empty($row['Section_ID']) ? (int)$row['Section_ID'] : null;

    if ($batchName === '' || preg_match('/\s+Enrollment$/i', $batchName)) {
        $fallback = trim((string)($row['Batch_Name'] ?? ''));
        if ($fallback !== '' && preg_match('/\s+Enrollment$/i', $fallback)) {
            $fallback = trim(preg_replace('/\s+Enrollment$/i', '', $fallback));
            if ($sectionName !== '' && strcasecmp($fallback, $sectionName) === 0) {
                $fallback = '';
            }
        }
        if ($batchName === '' || preg_match('/\s+Enrollment$/i', $batchName)) {
            $batchName = $fallback !== '' ? $fallback : $batchName;
        }
    }

    return [
        'batch_id' => isset($row['Batch_ID']) ? (int)$row['Batch_ID'] : null,
        'parent_batch_id' => $parentBatchId,
        'batch_name' => $batchName,
        'section_id' => $sectionId,
        'section_name' => $sectionName,
        'course_id' => isset($row['Course_ID']) ? (int)$row['Course_ID'] : null,
        'course_name' => $row['Course_Name'] ?? '',
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
        // Enrollment-batch rows: one section via b.Section_ID.
        // Parent-batch rows: expand to every section under that parent.
        $sql = "
            SELECT
                eb.Exam_ID,
                eb.Batch_ID,
                COALESCE(pb.Batch_ID, b.Batch_ID) AS Parent_Batch_ID,
                COALESCE(pb.Batch_Name, b.Batch_Name) AS Parent_Batch_Name,
                COALESCE(pb.Batch_Name, b.Batch_Name) AS Batch_Name,
                s.Section_ID,
                s.Section_Name,
                COALESCE(pb.Course_ID, b.Course_ID) AS Course_ID,
                COALESCE(c.Course_Name, '') AS Course_Name
            FROM tbl_exam_batch eb
            INNER JOIN tbl_batch b ON eb.Batch_ID = b.Batch_ID
            INNER JOIN tbl_academic_section s ON s.Section_ID = b.Section_ID
            LEFT JOIN tbl_batch pb ON s.Batch_ID = pb.Batch_ID
            LEFT JOIN tbl_course c ON c.Course_ID = COALESCE(pb.Course_ID, b.Course_ID)
            WHERE eb.Exam_ID IN ({$placeholders})
              AND b.Section_ID IS NOT NULL
              AND b.Section_ID <> 0

            UNION ALL

            SELECT
                eb.Exam_ID,
                eb.Batch_ID,
                b.Batch_ID AS Parent_Batch_ID,
                b.Batch_Name AS Parent_Batch_Name,
                b.Batch_Name AS Batch_Name,
                s.Section_ID,
                s.Section_Name,
                b.Course_ID AS Course_ID,
                COALESCE(c.Course_Name, '') AS Course_Name
            FROM tbl_exam_batch eb
            INNER JOIN tbl_batch b ON eb.Batch_ID = b.Batch_ID
            INNER JOIN tbl_academic_section s ON s.Batch_ID = b.Batch_ID
            LEFT JOIN tbl_course c ON c.Course_ID = b.Course_ID
            WHERE eb.Exam_ID IN ({$placeholders})
              AND (b.Section_ID IS NULL OR b.Section_ID = 0)

            ORDER BY Exam_ID, Parent_Batch_Name, Section_Name
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($examIds, $examIds));
    } else {
        $sql = "
            SELECT
                eb.Exam_ID,
                eb.Batch_ID,
                b.Batch_ID AS Parent_Batch_ID,
                b.Batch_Name AS Parent_Batch_Name,
                b.Batch_Name AS Batch_Name,
                NULL AS Section_ID,
                b.Batch_Name AS Section_Name,
                b.Course_ID,
                COALESCE(c.Course_Name, '') AS Course_Name
            FROM tbl_exam_batch eb
            INNER JOIN tbl_batch b ON eb.Batch_ID = b.Batch_ID
            LEFT JOIN tbl_course c ON b.Course_ID = c.Course_ID
            WHERE eb.Exam_ID IN ({$placeholders})
            ORDER BY eb.Exam_ID, b.Batch_Name
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($examIds);
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $map = [];
    foreach ($rows as $row) {
        $examId = (int)$row['Exam_ID'];
        if (!isset($map[$examId])) {
            $map[$examId] = [];
        }

        $normalized = normalizeExamAssignmentRow($row);
        $sectionId = (int)($normalized['section_id'] ?? 0);
        $parentBatchId = (int)($normalized['parent_batch_id'] ?? 0);

        $dedupeKey = $parentBatchId . ':' . $sectionId;
        if ($sectionId <= 0) {
            $dedupeKey = 'batch:' . (int)($normalized['batch_id'] ?? 0);
        }

        $existingIdx = null;
        foreach ($map[$examId] as $idx => $existing) {
            $existingParent = (int)($existing['parent_batch_id'] ?? 0);
            $existingSection = (int)($existing['section_id'] ?? 0);
            $existingKey = $existingSection > 0
                ? ($existingParent . ':' . $existingSection)
                : ('batch:' . (int)($existing['batch_id'] ?? 0));
            if ($existingKey === $dedupeKey) {
                $existingIdx = $idx;
                break;
            }
        }

        if ($existingIdx !== null) {
            $existingLabel = (string)($map[$examId][$existingIdx]['label'] ?? '');
            $newLabel = (string)($normalized['label'] ?? '');
            if (strlen($newLabel) > strlen($existingLabel)) {
                $map[$examId][$existingIdx] = $normalized;
            }
            continue;
        }

        $map[$examId][] = $normalized;
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
