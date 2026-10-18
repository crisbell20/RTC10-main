<?php

// Clone an exam per batch/section so each assignment is its own exam card

require_once __DIR__ . '/exam-code-utils.php';
require_once __DIR__ . '/batch-enrollment-utils.php';
require_once __DIR__ . '/exam-participant-utils.php';
require_once __DIR__ . '/exam-assignment-utils.php';
require_once __DIR__ . '/validation-utils.php';

function resolveSectionIdsToExamBatchIds(PDO $pdo, array $sectionIds): array
{
    $batchIds = [];
    foreach ($sectionIds as $sectionId) {
        $sectionId = (int)$sectionId;
        if ($sectionId <= 0) {
            continue;
        }
        $enrollmentBatchId = getEnrollmentBatchId($pdo, $sectionId);
        if ($enrollmentBatchId) {
            $batchIds[] = (int)$enrollmentBatchId;
        }
    }

    return array_values(array_unique($batchIds));
}

function replaceExamBatchAssignments(PDO $pdo, int $examId, array $batchIds): void
{
    $stmt = $pdo->prepare('DELETE FROM tbl_exam_batch WHERE Exam_ID = ?');
    $stmt->execute([$examId]);

    if (empty($batchIds)) {
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO tbl_exam_batch (Exam_ID, Batch_ID, Date_Assigned) VALUES (?, ?, NOW())');
    foreach ($batchIds as $batchId) {
        $batchId = (int)$batchId;
        if ($batchId > 0) {
            $stmt->execute([$examId, $batchId]);
        }
    }
}

function replaceExamQuestionAssignments(PDO $pdo, int $examId, array $questionIds): void
{
    $stmt = $pdo->prepare('DELETE FROM tbl_exam_question WHERE Exam_ID = ?');
    $stmt->execute([$examId]);

    if (empty($questionIds)) {
        return;
    }

    $stmt = $pdo->prepare('
        INSERT INTO tbl_exam_question (Exam_ID, Question_ID, Question_Order, Date_Added)
        VALUES (?, ?, ?, NOW())
    ');
    $order = 1;
    foreach ($questionIds as $questionId) {
        $questionId = (int)$questionId;
        if ($questionId > 0) {
            $stmt->execute([$examId, $questionId, $order]);
            $order++;
        }
    }
}

function copyExamQuestionsToExam(PDO $pdo, int $sourceExamId, int $targetExamId): void
{
    $stmt = $pdo->prepare('
        INSERT INTO tbl_exam_question (Exam_ID, Question_ID, Question_Order, Date_Added)
        SELECT ?, Question_ID, Question_Order, NOW()
        FROM tbl_exam_question
        WHERE Exam_ID = ?
    ');
    $stmt->execute([$targetExamId, $sourceExamId]);
}

function describeExamAssignment(PDO $pdo, int $examId): array
{
    $assignments = fetchExamAssignments($pdo, $examId);
    $first = $assignments[0] ?? [];

    $code = null;
    try {
        $stmt = $pdo->prepare('SELECT Exam_Code FROM tbl_exam WHERE Exam_ID = ?');
        $stmt->execute([$examId]);
        $code = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $code = null;
    }

    return [
        'exam_id' => $examId,
        'exam_code' => $code ?: null,
        'section_id' => isset($first['section_id']) ? (int)$first['section_id'] : null,
        'label' => (string)($first['label'] ?? ''),
        'cloned' => false,
    ];
}

// Insert a full copy of an exam
function cloneExamRecord(PDO $pdo, int $sourceExamId): int
{
    $stmt = $pdo->prepare('SELECT * FROM tbl_exam WHERE Exam_ID = ?');
    $stmt->execute([$sourceExamId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException('Source exam not found');
    }

    unset($row['Exam_ID']);

    if (array_key_exists('Exam_Code', $row)) {
        $row['Exam_Code'] = generateUniqueExamCode($pdo);
        if (array_key_exists('Exam_Code_Generated_At', $row)) {
            $row['Exam_Code_Generated_At'] = date('Y-m-d H:i:s');
        }
        if (array_key_exists('Exam_Code_Reset_Count', $row)) {
            $row['Exam_Code_Reset_Count'] = 0;
        }
    }

    $columns = array_keys($row);
    $quoted = array_map(static function (string $name): string {
        return '`' . str_replace('`', '', $name) . '`';
    }, $columns);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));

    $sql = 'INSERT INTO tbl_exam (' . implode(', ', $quoted) . ') VALUES (' . $placeholders . ')';
    $pdo->prepare($sql)->execute(array_values($row));
    $newId = (int)$pdo->lastInsertId();
    if ($newId <= 0) {
        throw new RuntimeException('Failed to clone exam');
    }

    copyExamQuestionsToExam($pdo, $sourceExamId, $newId);

    return $newId;
}

/**
 * Keep the source exam on the first section; clone a new exam for every extra section.
 *
 * @param list<int> $sectionIds
 * @return list<array{exam_id:int,exam_code:?string,section_id:?int,label:string,cloned:bool}>
 */
function splitExamToSections(PDO $pdo, int $examId, array $sectionIds): array
{
    $sectionIds = array_values(array_unique(array_filter(array_map('intval', $sectionIds))));
    $created = [];

    if (empty($sectionIds)) {
        replaceExamBatchAssignments($pdo, $examId, []);
        if (examParticipantTableExists($pdo)) {
            syncExamParticipantsFromSections($pdo, $examId);
        }
        $created[] = describeExamAssignment($pdo, $examId);
        return $created;
    }

    $firstSectionId = $sectionIds[0];
    replaceExamBatchAssignments($pdo, $examId, resolveSectionIdsToExamBatchIds($pdo, [$firstSectionId]));
    if (examParticipantTableExists($pdo)) {
        syncExamParticipantsFromSections($pdo, $examId);
    }
    $created[] = describeExamAssignment($pdo, $examId);

    for ($i = 1, $n = count($sectionIds); $i < $n; $i++) {
        $sectionId = $sectionIds[$i];
        $cloneId = cloneExamRecord($pdo, $examId);
        replaceExamBatchAssignments($pdo, $cloneId, resolveSectionIdsToExamBatchIds($pdo, [$sectionId]));
        if (examParticipantTableExists($pdo)) {
            syncExamParticipantsFromSections($pdo, $cloneId);
        }
        $row = describeExamAssignment($pdo, $cloneId);
        $row['cloned'] = true;
        $created[] = $row;
    }

    return $created;
}
