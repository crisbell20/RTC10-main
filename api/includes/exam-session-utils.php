<?php
/**
 * Helpers for exam session lifecycle (start, resume, reopen, retake).
 */

/**
 * SQL fragment: answer row counts as a response for the current exam.
 */
function examAnswerIsSubmittedSql(string $answerAlias = 'a'): string {
    return "({$answerAlias}.Choice_ID IS NOT NULL OR ({$answerAlias}.Answer_Correct IS NOT NULL AND TRIM({$answerAlias}.Answer_Correct) != ''))";
}

/**
 * Count all saved answers for a session (any question).
 */
function countSessionAnswers(PDO $pdo, int $sessionId): int {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM tbl_answer WHERE Session_ID = ?');
    $stmt->execute([$sessionId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Count saved answers that belong to the exam's current question set.
 */
function countExamSessionAnswers(PDO $pdo, int $sessionId, int $examId): int {
    $submitted = examAnswerIsSubmittedSql('a');
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM tbl_answer a
        INNER JOIN tbl_exam_question eq ON eq.Question_ID = a.Question_ID AND eq.Exam_ID = ?
        WHERE a.Session_ID = ?
          AND {$submitted}
    ");
    $stmt->execute([$examId, $sessionId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Live score for display — only answers on this exam's current questions.
 *
 * @return array{score:int, answered_count:int, raw_percent:float}
 */
function resolveLiveExamSessionScore(PDO $pdo, int $sessionId, int $examId, int $totalItems): array {
    $submitted = examAnswerIsSubmittedSql('a');
    $stmt = $pdo->prepare("
        SELECT
            (SELECT COUNT(*)
             FROM tbl_answer a
             INNER JOIN tbl_exam_question eq ON eq.Question_ID = a.Question_ID AND eq.Exam_ID = ?
             WHERE a.Session_ID = ? AND a.Is_Correct = 1 AND {$submitted}
            ) AS correct_count,
            (SELECT COUNT(*)
             FROM tbl_answer a
             INNER JOIN tbl_exam_question eq ON eq.Question_ID = a.Question_ID AND eq.Exam_ID = ?
             WHERE a.Session_ID = ? AND {$submitted}
            ) AS answered_count
    ");
    $stmt->execute([$examId, $sessionId, $examId, $sessionId]);
    $counts = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $score = (int)($counts['correct_count'] ?? 0);
    $answeredCount = (int)($counts['answered_count'] ?? 0);
    $rawPercent = $totalItems > 0 ? round(($score / $totalItems) * 100, 2) : 0.0;

    return [
        'score' => $score,
        'answered_count' => $answeredCount,
        'raw_percent' => $rawPercent,
    ];
}

/**
 * Saved choice answers for resume (question_id => choice_id).
 */
function fetchExamSessionSavedAnswers(PDO $pdo, int $sessionId, int $examId): array {
    $stmt = $pdo->prepare('
        SELECT a.Question_ID, a.Choice_ID
        FROM tbl_answer a
        INNER JOIN tbl_exam_question eq ON eq.Question_ID = a.Question_ID AND eq.Exam_ID = ?
        WHERE a.Session_ID = ?
          AND a.Choice_ID IS NOT NULL
    ');
    $stmt->execute([$examId, $sessionId]);

    $saved = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $saved[(int)$row['Question_ID']] = (int)$row['Choice_ID'];
    }

    return $saved;
}

/**
 * Whether the per-examinee duration window has elapsed.
 */
function isSessionDurationExpired(?string $timeStarted, ?int $durationMinutes): bool {
    if (!$timeStarted || !$durationMinutes || $durationMinutes <= 0) {
        return false;
    }

    $deadline = strtotime($timeStarted) + ($durationMinutes * 60);
    return time() > $deadline;
}

/**
 * Remove answers and placeholder results for a session (keeps the session row).
 */
function clearSessionAttemptData(PDO $pdo, int $sessionId): void {
    $stmt = $pdo->prepare('DELETE FROM tbl_answer WHERE Session_ID = ?');
    $stmt->execute([$sessionId]);

    $stmt = $pdo->prepare('DELETE FROM tbl_result WHERE Session_ID = ?');
    $stmt->execute([$sessionId]);
}

/**
 * Reset an active session timer to now.
 */
function resetSessionTimer(PDO $pdo, int $sessionId): void {
    $stmt = $pdo->prepare('
        UPDATE tbl_exam_session
        SET Time_Started = NOW(), Time_Ended = NULL
        WHERE Session_ID = ?
    ');
    $stmt->execute([$sessionId]);
}

/**
 * Delete a session (answers/results cascade via FK).
 */
function deleteExamSession(PDO $pdo, int $sessionId): void {
    $stmt = $pdo->prepare('DELETE FROM tbl_exam_session WHERE Session_ID = ?');
    $stmt->execute([$sessionId]);
}

/**
 * Completed attempt with no saved answers — e.g. auto-closed or accidental open.
 */
function isVoidCompletedSession(PDO $pdo, int $sessionId): bool {
    $stmt = $pdo->prepare('
        SELECT Time_Ended
        FROM tbl_exam_session
        WHERE Session_ID = ?
    ');
    $stmt->execute([$sessionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['Time_Ended'])) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT Exam_ID FROM tbl_exam_session WHERE Session_ID = ?');
    $stmt->execute([$sessionId]);
    $examId = (int)$stmt->fetchColumn();
    if ($examId <= 0) {
        return countSessionAnswers($pdo, $sessionId) === 0;
    }

    return countExamSessionAnswers($pdo, $sessionId, $examId) === 0;
}

/**
 * If timer expired with no answers, restart the session so the examinee gets a fresh window.
 * Returns the effective Time_Started value.
 */
function reviveExpiredEmptySession(PDO $pdo, int $sessionId, int $examId, ?string $timeStarted, ?int $durationMinutes): string {
    if (!isSessionDurationExpired($timeStarted, $durationMinutes)) {
        return $timeStarted ?? date('Y-m-d H:i:s');
    }

    if (countExamSessionAnswers($pdo, $sessionId, $examId) > 0) {
        return $timeStarted ?? date('Y-m-d H:i:s');
    }

    clearSessionAttemptData($pdo, $sessionId);
    resetSessionTimer($pdo, $sessionId);

    $stmt = $pdo->prepare('SELECT Time_Started FROM tbl_exam_session WHERE Session_ID = ?');
    $stmt->execute([$sessionId]);
    return (string)$stmt->fetchColumn();
}

/**
 * Whether the user has a completed attempt worth blocking a new session.
 */
function userHasCompletedExamAttempt(PDO $pdo, int $userId, int $examId): bool {
    $stmt = $pdo->prepare('
        SELECT 1
        FROM tbl_exam_session es
        INNER JOIN tbl_result r ON r.Session_ID = es.Session_ID
        WHERE es.User_ID = ?
          AND es.Exam_ID = ?
          AND es.Time_Ended IS NOT NULL
        LIMIT 1
    ');
    $stmt->execute([$userId, $examId]);

    return (bool)$stmt->fetchColumn();
}

/**
 * End session, score saved answers, and upsert tbl_result (same rules as manual submit).
 *
 * @return array{score:int,total_questions:int,percentage:float,remarks:string,passing_score:float,result_id:int}
 */
function completeExamSessionSubmission(PDO $pdo, int $sessionId): array {
    require_once __DIR__ . '/exam-code-utils.php';

    $stmt = $pdo->prepare('
        SELECT es.Session_ID, es.Exam_ID, es.User_ID, es.Time_Ended
        FROM tbl_exam_session es
        WHERE es.Session_ID = ?
    ');
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        throw new InvalidArgumentException('Invalid session');
    }

    $examId = (int)$session['Exam_ID'];

    if (empty($session['Time_Ended'])) {
        $stmt = $pdo->prepare('UPDATE tbl_exam_session SET Time_Ended = NOW() WHERE Session_ID = ?');
        $stmt->execute([$sessionId]);
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) AS total_questions FROM tbl_exam_question WHERE Exam_ID = ?');
    $stmt->execute([$examId]);
    $totalQuestions = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total_questions'] ?? 0);

    $live = resolveLiveExamSessionScore($pdo, $sessionId, $examId, $totalQuestions);
    $score = $live['score'];
    $percentage = $live['raw_percent'];

    $hasResponseReview = responseReviewColumnExists($pdo);
    $reviewSelect = $hasResponseReview ? ', Allow_Response_Review' : '';
    $stmt = $pdo->prepare("SELECT Passing_Score{$reviewSelect} FROM tbl_exam WHERE Exam_ID = ?");
    $stmt->execute([$examId]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $passingScore = (float)($exam['Passing_Score'] ?? 50);
    $remarks = $percentage >= $passingScore ? 'Passed' : 'Failed';

    $stmt = $pdo->prepare('SELECT Result_ID FROM tbl_result WHERE Session_ID = ?');
    $stmt->execute([$sessionId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $resultId = (int)$existing['Result_ID'];
        $stmt = $pdo->prepare('
            UPDATE tbl_result
            SET Score = ?, Percentage = ?, Remarks = ?, Submission_Date = NOW()
            WHERE Result_ID = ?
        ');
        $stmt->execute([$score, $percentage, $remarks, $resultId]);
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO tbl_result (Session_ID, Score, Percentage, Remarks, Submission_Date)
            VALUES (?, ?, ?, ?, NOW())
        ');
        $stmt->execute([$sessionId, $score, $percentage, $remarks]);
        $resultId = (int)$pdo->lastInsertId();
    }

    return [
        'score' => $score,
        'total_questions' => $totalQuestions,
        'percentage' => round($percentage, 2),
        'remarks' => $remarks,
        'passing_score' => $passingScore,
        'result_id' => $resultId,
    ];
}

/**
 * Remove void completed sessions so the examinee can retake after reopen.
 */
function purgeVoidCompletedSessions(PDO $pdo, int $examId): int {
    $stmt = $pdo->prepare('
        SELECT Session_ID
        FROM tbl_exam_session
        WHERE Exam_ID = ?
          AND Time_Ended IS NOT NULL
    ');
    $stmt->execute([$examId]);
    $deleted = 0;

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $sessionId) {
        if (isVoidCompletedSession($pdo, (int)$sessionId)) {
            deleteExamSession($pdo, (int)$sessionId);
            $deleted++;
        }
    }

    return $deleted;
}

/**
 * Clear attempts eligible for retake (no saved answers): ended void + abandoned in-progress.
 */
function clearRetakeEligibleAttemptsForUser(PDO $pdo, int $userId, int $examId): int {
    if ($userId <= 0 || $examId <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare('
        SELECT Session_ID, Time_Ended
        FROM tbl_exam_session
        WHERE User_ID = ? AND Exam_ID = ?
    ');
    $stmt->execute([$userId, $examId]);
    $removed = 0;

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sessionId = (int)$row['Session_ID'];
        $ended = !empty($row['Time_Ended']);

        if ($ended && !isVoidCompletedSession($pdo, $sessionId)) {
            continue;
        }

        if (!$ended && countExamSessionAnswers($pdo, $sessionId, $examId) > 0) {
            continue;
        }

        deleteExamSession($pdo, $sessionId);
        $removed++;
    }

    return $removed;
}

/**
 * Reset in-progress sessions with saved answers; remove empty in-progress sessions.
 *
 * @return array{reset:int,removed:int}
 */
function resetActiveSessionsForReopen(PDO $pdo, int $examId): array {
    $stmt = $pdo->prepare('
        SELECT Session_ID
        FROM tbl_exam_session
        WHERE Exam_ID = ?
          AND Time_Started IS NOT NULL
          AND Time_Ended IS NULL
    ');
    $stmt->execute([$examId]);
    $reset = 0;
    $removed = 0;

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $sessionId) {
        $sessionId = (int)$sessionId;
        if (countExamSessionAnswers($pdo, $sessionId, $examId) === 0) {
            deleteExamSession($pdo, $sessionId);
            $removed++;
            continue;
        }

        clearSessionAttemptData($pdo, $sessionId);
        resetSessionTimer($pdo, $sessionId);
        $reset++;
    }

    return ['reset' => $reset, 'removed' => $removed];
}

/**
 * Prepare all examinee sessions when an exam is reopened or republished.
 *
 * @return array{active_reset:int,active_removed:int,void_removed:int}
 */
function prepareExamSessionsForReopen(PDO $pdo, int $examId): array {
    $voidRemoved = purgeVoidCompletedSessions($pdo, $examId);
    $activeStats = resetActiveSessionsForReopen($pdo, $examId);

    return [
        'active_reset' => $activeStats['reset'],
        'active_removed' => $activeStats['removed'],
        'void_removed' => $voidRemoved,
    ];
}
