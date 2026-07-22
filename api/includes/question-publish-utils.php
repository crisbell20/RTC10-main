<?php

/**
 * Helpers for exam-only draft questions (hidden from bank until exam publish).
 */

function questionPublishedColumnExists(PDO $pdo): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM tbl_question_bank LIKE 'Is_Published'");
    $cache = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    return $cache;
}

function promoteExamDraftQuestions(PDO $pdo, int $examId): int
{
    if (!questionPublishedColumnExists($pdo)) {
        return 0;
    }

    $stmt = $pdo->prepare('
        UPDATE tbl_question_bank qb
        INNER JOIN tbl_exam_question eq ON eq.Question_ID = qb.Question_ID
        SET qb.Is_Published = 1
        WHERE eq.Exam_ID = ? AND qb.Is_Published = 0
    ');
    $stmt->execute([$examId]);
    return $stmt->rowCount();
}

function cleanupOrphanUnpublishedQuestions(PDO $pdo): int
{
    if (!questionPublishedColumnExists($pdo)) {
        return 0;
    }

    $choiceStmt = $pdo->prepare('
        DELETE c FROM tbl_choice c
        INNER JOIN tbl_question_bank qb ON qb.Question_ID = c.Question_ID
        LEFT JOIN tbl_exam_question eq ON eq.Question_ID = qb.Question_ID
        WHERE qb.Is_Published = 0 AND eq.Question_ID IS NULL
    ');
    $choiceStmt->execute();

    $stmt = $pdo->prepare('
        DELETE qb FROM tbl_question_bank qb
        LEFT JOIN tbl_exam_question eq ON eq.Question_ID = qb.Question_ID
        WHERE qb.Is_Published = 0 AND eq.Question_ID IS NULL
    ');
    $stmt->execute();
    return $stmt->rowCount();
}

function publishedQuestionsWhereClause(PDO $pdo, string $alias = 'q'): string
{
    return questionPublishedColumnExists($pdo) ? " AND {$alias}.Is_Published = 1" : '';
}
