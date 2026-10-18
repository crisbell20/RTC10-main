<?php

// Helpers for exam session lifecycle

/**
 * SQL fragment: answer row counts as a response for the current exam.
 */
function examAnswerIsSubmittedSql(string $answerAlias = 'a'): string {
    return "({$answerAlias}.Choice_ID IS NOT NULL OR ({$answerAlias}.Answer_Correct IS NOT NULL AND TRIM({$answerAlias}.Answer_Correct) != ''))";
}

/**
 * True when this session has at least one saved answer on the exam's current questions.
 */
function examSessionHasSubmittedAnswersSql(string $sessionIdSql, string $examIdSql): string {
    $submitted = examAnswerIsSubmittedSql('a');
    return "
        EXISTS (
            SELECT 1
            FROM tbl_answer a
            INNER JOIN tbl_exam_question eq
                ON eq.Question_ID = a.Question_ID
               AND eq.Exam_ID = {$examIdSql}
            WHERE a.Session_ID = {$sessionIdSql}
              AND {$submitted}
        )
    ";
}

/**
 * True when this user has answered at least one question on the exam.
 */
function examUserHasSubmittedAnswersSql(string $userIdSql, string $examIdSql): string {
    $submitted = examAnswerIsSubmittedSql('a');
    return "
        EXISTS (
            SELECT 1
            FROM tbl_exam_session es_ans
            INNER JOIN tbl_answer a ON a.Session_ID = es_ans.Session_ID
            INNER JOIN tbl_exam_question eq
                ON eq.Question_ID = a.Question_ID
               AND eq.Exam_ID = es_ans.Exam_ID
            WHERE es_ans.Exam_ID = {$examIdSql}
              AND es_ans.User_ID = {$userIdSql}
              AND {$submitted}
        )
    ";
}

/**
 * User IDs who actually answered this exam
 *
 * @return array<int, true>
 */
function fetchExamAnsweredUserIds(PDO $pdo, int $examId): array {
    if ($examId <= 0) {
        return [];
    }

    $submitted = examAnswerIsSubmittedSql('a');
    $stmt = $pdo->prepare("
        SELECT DISTINCT es.User_ID
        FROM tbl_exam_session es
        INNER JOIN tbl_answer a ON a.Session_ID = es.Session_ID
        INNER JOIN tbl_exam_question eq
            ON eq.Question_ID = a.Question_ID
           AND eq.Exam_ID = es.Exam_ID
        WHERE es.Exam_ID = ?
          AND {$submitted}
    ");
    $stmt->execute([$examId]);

    $ids = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $userId) {
        $ids[(int)$userId] = true;
    }

    return $ids;
}

/**
 * True when this user finished a real sitting (started, then ended later — including 0 answers)
 */
function examUserHasFinishedSittingSql(string $userIdSql, string $examIdSql): string {
    return "
        EXISTS (
            SELECT 1
            FROM tbl_exam_session es_done
            INNER JOIN tbl_result r_done ON r_done.Session_ID = es_done.Session_ID
            WHERE es_done.Exam_ID = {$examIdSql}
              AND es_done.User_ID = {$userIdSql}
              AND es_done.Time_Ended IS NOT NULL
              AND es_done.Time_Started IS NOT NULL
              AND es_done.Time_Started <> es_done.Time_Ended
        )
    ";
}

/**
 * User IDs who finished a real sitting on this exam.
 *
 * @return array<int, true>
 */
function fetchExamFinishedSittingUserIds(PDO $pdo, int $examId): array {
    if ($examId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare('
        SELECT DISTINCT es.User_ID
        FROM tbl_exam_session es
        INNER JOIN tbl_result r ON r.Session_ID = es.Session_ID
        WHERE es.Exam_ID = ?
          AND es.Time_Ended IS NOT NULL
          AND es.Time_Started IS NOT NULL
          AND es.Time_Started <> es.Time_Ended
    ');
    $stmt->execute([$examId]);

    $ids = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $userId) {
        $ids[(int)$userId] = true;
    }

    return $ids;
}

/**
 * Count all saved answers for a session
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
 * Elapsed exam time for display (Time_Ended − Time_Started).
 * Never-sat rows (0 answers, or start/end the same) show 00:00:00.
 */
function formatExamTimeTaken(?string $timeStarted, ?string $timeEnded, ?int $answeredCount = null): string {
    if (!$timeStarted || !$timeEnded) {
        return 'N/A';
    }

    if ($answeredCount !== null && $answeredCount <= 0) {
        return '00:00:00';
    }

    if (sessionStartedAndEndedTogether($timeStarted, $timeEnded)) {
        return '00:00:00';
    }

    $start = strtotime($timeStarted);
    $end = strtotime($timeEnded);
    if ($start === false || $end === false) {
        return 'N/A';
    }

    $diff = $end - $start;
    if ($diff < 0) {
        return 'N/A';
    }

    $hours = (int)floor($diff / 3600);
    $minutes = (int)floor(($diff % 3600) / 60);
    $seconds = (int)($diff % 60);

    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
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
    $clearOccupancy = examOccupancyClearAssignmentsSql($pdo);
    $setOccupancy = $clearOccupancy !== '' ? ', ' . $clearOccupancy : '';
    $stmt = $pdo->prepare("
        UPDATE tbl_exam_session
        SET Time_Started = NOW(), Time_Ended = NULL{$setOccupancy}
        WHERE Session_ID = ?
    ");
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
 * True when start and end are the same instant (missed-deadline placeholder, never sat).
 */
function sessionStartedAndEndedTogether(?string $timeStarted, ?string $timeEnded): bool {
    if ($timeStarted === null || $timeEnded === null || $timeStarted === '' || $timeEnded === '') {
        return false;
    }

    $startTs = strtotime($timeStarted);
    $endTs = strtotime($timeEnded);

    return $startTs !== false && $endTs !== false && $startTs === $endTs;
}

/**
 * Completed row that is not a real sitting — missed-deadline no-show placeholder.
 * A real start that expired with 0 answers is a consumed attempt, not void.
 */
function isVoidCompletedSession(PDO $pdo, int $sessionId): bool {
    $stmt = $pdo->prepare('
        SELECT Exam_ID, Time_Started, Time_Ended
        FROM tbl_exam_session
        WHERE Session_ID = ?
    ');
    $stmt->execute([$sessionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['Time_Ended'])) {
        return false;
    }

    if (!sessionStartedAndEndedTogether($row['Time_Started'] ?? null, $row['Time_Ended'] ?? null)) {
        return false;
    }

    $examId = (int)($row['Exam_ID'] ?? 0);
    if ($examId <= 0) {
        return countSessionAnswers($pdo, $sessionId) === 0;
    }

    return countExamSessionAnswers($pdo, $sessionId, $examId) === 0;
}

/**
 * If the per-examinee duration has elapsed, finalize the attempt (0 answers = Failed).
 * Does not restart Time_Started. Returns true when the session was expired and closed.
 */
function finalizeSessionIfDurationExpired(PDO $pdo, int $sessionId, ?string $timeStarted, ?int $durationMinutes): bool {
    if (!isSessionDurationExpired($timeStarted, $durationMinutes)) {
        return false;
    }

    completeExamSessionSubmission($pdo, $sessionId);
    return true;
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

function retakeColumnsExist(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM tbl_exam_session LIKE 'Retake_Granted'");
        $granted = $stmt && $stmt->rowCount() > 0;
        $stmt = $pdo->query("SHOW COLUMNS FROM tbl_exam_session LIKE 'Is_Retake'");
        $exists = $granted && $stmt && $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $exists = false;
    }

    return $exists;
}

/**
 * A real completed sitting. Missed-deadline placeholders start and end together.
 */
function finishedSittingPredicateSql(string $alias = 'es'): string
{
    return "{$alias}.Time_Ended IS NOT NULL
        AND {$alias}.Time_Started IS NOT NULL
        AND {$alias}.Time_Started <> {$alias}.Time_Ended";
}

/**
 * Keep the latest completed result for one trainee and exam.
 * Tie-breaker is Result_ID so the choice stays stable when timestamps match.
 */
function latestFinishedResultMatchSql(string $sessionAlias = 'es', string $resultAlias = 'r'): string
{
    $finished = finishedSittingPredicateSql('es_latest');

    return "{$resultAlias}.Result_ID = (
        SELECT r_latest.Result_ID
        FROM tbl_result r_latest
        INNER JOIN tbl_exam_session es_latest ON es_latest.Session_ID = r_latest.Session_ID
        WHERE es_latest.Exam_ID = {$sessionAlias}.Exam_ID
          AND es_latest.User_ID = {$sessionAlias}.User_ID
          AND {$finished}
        ORDER BY r_latest.Submission_Date DESC, r_latest.Result_ID DESC
        LIMIT 1
    )";
}

/**
 * Exams still open to this trainee: no finished sitting, an unused retake grant,
 * or an in-progress retake session.
 *
 * @return array{sql:string,param_count:int}
 */
function buildAvailableExamAttemptGate(PDO $pdo): array
{
    $finished = finishedSittingPredicateSql('es_done');
    if (!retakeColumnsExist($pdo)) {
        return [
            'sql' => "AND NOT EXISTS (
                SELECT 1
                FROM tbl_exam_session es_done
                INNER JOIN tbl_result r_done ON r_done.Session_ID = es_done.Session_ID
                WHERE es_done.Exam_ID = e.Exam_ID
                  AND es_done.User_ID = ?
                  AND es_done.Time_Ended IS NOT NULL
            )",
            'param_count' => 1,
        ];
    }

    $grantFinished = finishedSittingPredicateSql('es_grant');
    $countFinished = finishedSittingPredicateSql('es_fin');

    return [
        'sql' => "AND (
            NOT EXISTS (
                SELECT 1
                FROM tbl_exam_session es_done
                INNER JOIN tbl_result r_done ON r_done.Session_ID = es_done.Session_ID
                WHERE es_done.Exam_ID = e.Exam_ID
                  AND es_done.User_ID = ?
                  AND {$finished}
            )
            OR EXISTS (
                SELECT 1
                FROM tbl_exam_session es_grant
                INNER JOIN tbl_result r_grant ON r_grant.Session_ID = es_grant.Session_ID
                WHERE es_grant.Exam_ID = e.Exam_ID
                  AND es_grant.User_ID = ?
                  AND es_grant.Retake_Granted = 1
                  AND es_grant.Is_Retake = 0
                  AND {$grantFinished}
                  AND (
                      SELECT COUNT(*)
                      FROM tbl_exam_session es_fin
                      INNER JOIN tbl_result r_fin ON r_fin.Session_ID = es_fin.Session_ID
                      WHERE es_fin.Exam_ID = e.Exam_ID
                        AND es_fin.User_ID = ?
                        AND {$countFinished}
                  ) = 1
            )
            OR EXISTS (
                SELECT 1
                FROM tbl_exam_session es_open
                WHERE es_open.Exam_ID = e.Exam_ID
                  AND es_open.User_ID = ?
                  AND es_open.Time_Ended IS NULL
                  AND es_open.Is_Retake = 1
            )
        )",
        'param_count' => 4,
    ];
}

function countFinishedSittings(PDO $pdo, int $userId, int $examId): int
{
    $finished = finishedSittingPredicateSql('es');
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM tbl_exam_session es
        INNER JOIN tbl_result r ON r.Session_ID = es.Session_ID
        WHERE es.User_ID = ?
          AND es.Exam_ID = ?
          AND {$finished}
    ");
    $stmt->execute([$userId, $examId]);

    return (int)$stmt->fetchColumn();
}

/**
 * Lock this trainee's sessions for the exam so two requests cannot both open a retake.
 *
 * @return array<int, array<string, mixed>>
 */
function lockUserExamSessions(PDO $pdo, int $userId, int $examId): array
{
    $retakeSelect = retakeColumnsExist($pdo) ? ', Retake_Granted, Is_Retake' : '';
    $stmt = $pdo->prepare("
        SELECT Session_ID, Time_Started, Time_Ended, Cheating_Locked{$retakeSelect}
        FROM tbl_exam_session
        WHERE User_ID = ? AND Exam_ID = ?
        FOR UPDATE
    ");
    $stmt->execute([$userId, $examId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * @return array{allowed:bool,is_retake:bool,grant_session_id:int}
 */
function evaluateNewAttemptGate(PDO $pdo, int $userId, int $examId): array
{
    $denied = ['allowed' => false, 'is_retake' => false, 'grant_session_id' => 0];
    $finished = countFinishedSittings($pdo, $userId, $examId);

    if ($finished <= 0) {
        if (userHasCompletedExamAttempt($pdo, $userId, $examId)) {
            return $denied;
        }

        return ['allowed' => true, 'is_retake' => false, 'grant_session_id' => 0];
    }

    if ($finished !== 1 || !retakeColumnsExist($pdo)) {
        return $denied;
    }

    $finishedSql = finishedSittingPredicateSql('es');
    $stmt = $pdo->prepare("
        SELECT es.Session_ID
        FROM tbl_exam_session es
        INNER JOIN tbl_result r ON r.Session_ID = es.Session_ID
        WHERE es.User_ID = ?
          AND es.Exam_ID = ?
          AND es.Retake_Granted = 1
          AND es.Is_Retake = 0
          AND {$finishedSql}
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$userId, $examId]);
    $grantSessionId = (int)$stmt->fetchColumn();
    if ($grantSessionId <= 0) {
        return $denied;
    }

    return ['allowed' => true, 'is_retake' => true, 'grant_session_id' => $grantSessionId];
}

function consumeRetakeGrant(PDO $pdo, int $grantSessionId): bool
{
    $stmt = $pdo->prepare('
        UPDATE tbl_exam_session
        SET Retake_Granted = 0
        WHERE Session_ID = ? AND Retake_Granted = 1
    ');
    $stmt->execute([$grantSessionId]);

    return $stmt->rowCount() === 1;
}

/**
 * Put an unused grant back when an empty retake session is removed before it is completed.
 */
function restoreUnusedRetakeGrant(PDO $pdo, int $userId, int $examId): void
{
    if (!retakeColumnsExist($pdo) || $userId <= 0 || $examId <= 0) {
        return;
    }

    if (countFinishedSittings($pdo, $userId, $examId) !== 1) {
        return;
    }

    $openStmt = $pdo->prepare('
        SELECT Session_ID
        FROM tbl_exam_session
        WHERE User_ID = ? AND Exam_ID = ? AND Time_Ended IS NULL
        LIMIT 1
    ');
    $openStmt->execute([$userId, $examId]);
    if ($openStmt->fetchColumn()) {
        return;
    }

    $finished = finishedSittingPredicateSql('es');
    $stmt = $pdo->prepare("
        UPDATE tbl_exam_session es
        INNER JOIN tbl_result r ON r.Session_ID = es.Session_ID
        SET es.Retake_Granted = 1
        WHERE es.User_ID = ?
          AND es.Exam_ID = ?
          AND es.Is_Retake = 0
          AND es.Retake_Granted = 0
          AND {$finished}
    ");
    $stmt->execute([$userId, $examId]);
}

/**
 * Number completed rows for one exam and mark the official (latest) result.
 * An unfinished placeholder has no attempt number and is not an official result.
 *
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, array<string, mixed>>
 */
function annotateAttemptHistory(array $rows): array
{
    $groups = [];
    foreach ($rows as $index => $row) {
        $examId = (int)($row['Exam_ID'] ?? 0);
        $groups[$examId][] = $index;
    }

    foreach ($groups as $indexes) {
        usort($indexes, static function ($left, $right) use ($rows) {
            $leftDate = (string)($rows[$left]['Submission_Date'] ?? '');
            $rightDate = (string)($rows[$right]['Submission_Date'] ?? '');
            if ($leftDate === $rightDate) {
                return ((int)($rows[$left]['Result_ID'] ?? 0)) <=> ((int)($rows[$right]['Result_ID'] ?? 0));
            }

            return strcmp($leftDate, $rightDate);
        });

        $latestIndex = null;
        $latestKey = null;
        $attemptNumber = 0;
        foreach ($indexes as $index) {
            $rows[$index]['is_latest'] = false;
            $started = (string)($rows[$index]['Time_Started'] ?? '');
            $ended = (string)($rows[$index]['Time_Ended'] ?? '');
            if ($ended === '') {
                $rows[$index]['is_in_progress'] = true;
                $rows[$index]['attempt_number'] = null;
                continue;
            }

            $rows[$index]['is_in_progress'] = false;
            $attemptNumber++;
            $rows[$index]['attempt_number'] = $attemptNumber;

            if ($started !== '' && $started === $ended) {
                continue;
            }

            $key = $ended . '#' . str_pad((string)(int)($rows[$index]['Result_ID'] ?? 0), 12, '0', STR_PAD_LEFT);
            $submitted = (string)($rows[$index]['Submission_Date'] ?? '');
            if ($submitted !== '') {
                $key = $submitted . '#' . str_pad((string)(int)($rows[$index]['Result_ID'] ?? 0), 12, '0', STR_PAD_LEFT);
            }
            if ($latestKey === null || $key > $latestKey) {
                $latestKey = $key;
                $latestIndex = $index;
            }
        }

        if ($latestIndex !== null) {
            $rows[$latestIndex]['is_latest'] = true;
        }
    }

    return $rows;
}

/**
 * End session, score saved answers, and upsert tbl_result (same rules as manual submit).
 *
 * @return array{score:int,total_questions:int,percentage:float,remarks:string,passing_score:float,result_id:int}
 */
function completeExamSessionSubmission(PDO $pdo, int $sessionId): array {
    require_once __DIR__ . '/exam-code-utils.php';

    $stmt = $pdo->prepare('
    SELECT es.Session_ID, es.Exam_ID, es.User_ID, es.Time_Started, es.Time_Ended, e.Duration
    FROM tbl_exam_session es
    INNER JOIN tbl_exam e ON e.Exam_ID = es.Exam_ID
    WHERE es.Session_ID = ?
');
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        throw new InvalidArgumentException('Invalid session');
    }

    $examId = (int)$session['Exam_ID'];

    if (empty($session['Time_Ended'])) {
    $timeEnded = date('Y-m-d H:i:s');
    $durationMinutes = (int)($session['Duration'] ?? 0);
    $startedTimestamp = strtotime($session['Time_Started'] ?? '');

    if ($durationMinutes > 0 && $startedTimestamp !== false) {
        $durationEndTimestamp = $startedTimestamp + ($durationMinutes * 60);
        if (time() >= $durationEndTimestamp) {
            $timeEnded = date('Y-m-d H:i:s', $durationEndTimestamp);
        }
    }

    $clearOccupancy = examOccupancyClearAssignmentsSql($pdo);
    $endSql = $clearOccupancy !== ''
        ? "UPDATE tbl_exam_session SET Time_Ended = ?, {$clearOccupancy} WHERE Session_ID = ?"
        : 'UPDATE tbl_exam_session SET Time_Ended = ? WHERE Session_ID = ?';

    $stmt = $pdo->prepare($endSql);
    $stmt->execute([$timeEnded, $sessionId]);
} else {
    clearExamSessionOccupancy($pdo, $sessionId);
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
 * Remove missed-deadline placeholders so never-started examinees can sit after reopen.
 * Does not delete real sittings that expired with 0 answers.
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
 * Clear missed-deadline placeholders so a never-started examinee can start after reopen.
 * Real started attempts (including 0-answer timeouts) are kept.
 */
function clearRetakeEligibleAttemptsForUser(PDO $pdo, int $userId, int $examId): int {
    if ($userId <= 0 || $examId <= 0) {
        return 0;
    }

    $retakeSelect = retakeColumnsExist($pdo) ? ', Is_Retake' : '';
    $stmt = $pdo->prepare("
        SELECT Session_ID, Time_Started, Time_Ended{$retakeSelect}
        FROM tbl_exam_session
        WHERE User_ID = ? AND Exam_ID = ?
    ");
    $stmt->execute([$userId, $examId]);
    $removed = 0;

    $durationStmt = $pdo->prepare('SELECT Duration FROM tbl_exam WHERE Exam_ID = ?');
    $durationStmt->execute([$examId]);
    $duration = (int)$durationStmt->fetchColumn();

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sessionId = (int)$row['Session_ID'];
        $ended = !empty($row['Time_Ended']);

        if ($ended) {
            if (!isVoidCompletedSession($pdo, $sessionId)) {
                continue;
            }
            deleteExamSession($pdo, $sessionId);
            $removed++;
            continue;
        }

        if (countExamSessionAnswers($pdo, $sessionId, $examId) > 0) {
            continue;
        }

        if (finalizeSessionIfDurationExpired($pdo, $sessionId, $row['Time_Started'] ?? null, $duration)) {
            continue;
        }

        $wasRetake = (int)($row['Is_Retake'] ?? 0) === 1;
        deleteExamSession($pdo, $sessionId);
        if ($wasRetake) {
            restoreUnusedRetakeGrant($pdo, $userId, $examId);
        }
        $removed++;
    }

    return $removed;
}

/**
 * On reopen: finalize duration-expired sittings; clear empty in-window sessions;
 * reset in-progress sessions that still have time and saved answers.
 *
 * @return array{reset:int,removed:int,expired_finalized:int}
 */
function resetActiveSessionsForReopen(PDO $pdo, int $examId): array {
    $retakeSelect = retakeColumnsExist($pdo) ? ', es.Is_Retake, es.User_ID' : ', es.User_ID';
    $stmt = $pdo->prepare("
        SELECT es.Session_ID, es.Time_Started, e.Duration{$retakeSelect}
        FROM tbl_exam_session es
        INNER JOIN tbl_exam e ON e.Exam_ID = es.Exam_ID
        WHERE es.Exam_ID = ?
          AND es.Time_Started IS NOT NULL
          AND es.Time_Ended IS NULL
    ");
    $stmt->execute([$examId]);
    $reset = 0;
    $removed = 0;
    $expiredFinalized = 0;

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sessionId = (int)$row['Session_ID'];
        $duration = (int)($row['Duration'] ?? 0);

        if (finalizeSessionIfDurationExpired($pdo, $sessionId, $row['Time_Started'] ?? null, $duration)) {
            $expiredFinalized++;
            continue;
        }

        if (countExamSessionAnswers($pdo, $sessionId, $examId) === 0) {
            $wasRetake = (int)($row['Is_Retake'] ?? 0) === 1;
            $sessionUserId = (int)($row['User_ID'] ?? 0);
            deleteExamSession($pdo, $sessionId);
            if ($wasRetake) {
                restoreUnusedRetakeGrant($pdo, $sessionUserId, $examId);
            }
            $removed++;
            continue;
        }

        clearSessionAttemptData($pdo, $sessionId);
        resetSessionTimer($pdo, $sessionId);
        $reset++;
    }

    return [
        'reset' => $reset,
        'removed' => $removed,
        'expired_finalized' => $expiredFinalized,
    ];
}

/**
 * Prepare examinee sessions when an exam is reopened or republished.
 *
 * @return array{active_reset:int,active_removed:int,void_removed:int,expired_finalized:int}
 */
function prepareExamSessionsForReopen(PDO $pdo, int $examId): array {
    $voidRemoved = purgeVoidCompletedSessions($pdo, $examId);
    $activeStats = resetActiveSessionsForReopen($pdo, $examId);

    return [
        'active_reset' => $activeStats['reset'],
        'active_removed' => $activeStats['removed'],
        'void_removed' => $voidRemoved,
        'expired_finalized' => $activeStats['expired_finalized'],
    ];
}

function examOccupancyConflictMessage(): string
{
    return 'This exam is already open on another device.';
}

function examOccupancyStaleSeconds(): int
{
    return 35;
}

function examOccupancyColumnExists(PDO $pdo, bool $refresh = false): bool
{
    static $exists = null;
    if ($refresh) {
        $exists = null;
    }
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM tbl_exam_session LIKE 'Occupancy_Token'");
        $exists = $stmt && $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $exists = false;
    }

    return $exists;
}

function examOccupancyAtColumnExists(PDO $pdo, bool $refresh = false): bool
{
    static $exists = null;
    if ($refresh) {
        $exists = null;
    }
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM tbl_exam_session LIKE 'Occupancy_At'");
        $exists = $stmt && $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $exists = false;
    }

    return $exists;
}

function ensureExamOccupancyAtColumn(PDO $pdo): bool
{
    if (examOccupancyAtColumnExists($pdo)) {
        return true;
    }

    try {
        $pdo->exec('ALTER TABLE tbl_exam_session ADD COLUMN Occupancy_At DATETIME DEFAULT NULL');
    } catch (Exception $e) {
        // Duplicate column is fine if another request added it first.
    }

    return examOccupancyAtColumnExists($pdo, true);
}

function ensureExamOccupancyColumn(PDO $pdo): bool
{
    if (!examOccupancyColumnExists($pdo)) {
        try {
            $pdo->exec('ALTER TABLE tbl_exam_session ADD COLUMN Occupancy_Token VARCHAR(64) DEFAULT NULL');
        } catch (Exception $e) {
            // Duplicate column is fine if another request added it first.
        }
        if (!examOccupancyColumnExists($pdo, true)) {
            return false;
        }
    }

    ensureExamOccupancyAtColumn($pdo);
    return true;
}

function examOccupancyClearAssignmentsSql(PDO $pdo): string
{
    if (!examOccupancyColumnExists($pdo)) {
        return '';
    }

    $sql = 'Occupancy_Token = NULL';
    if (examOccupancyAtColumnExists($pdo)) {
        $sql .= ', Occupancy_At = NULL';
    }

    return $sql;
}

function normalizeExamOccupancyToken(?string $token): string
{
    $token = trim((string)$token);
    if ($token === '' || strlen($token) > 64 || !preg_match('/^[A-Za-z0-9_-]+$/', $token)) {
        return '';
    }

    return $token;
}

function claimExamSessionOccupancy(PDO $pdo, int $sessionId, string $token): bool
{
    if ($sessionId <= 0 || $token === '') {
        return false;
    }
    if (!ensureExamOccupancyColumn($pdo)) {
        return true;
    }

    $hasHeartbeat = examOccupancyAtColumnExists($pdo);
    if ($hasHeartbeat) {
        $staleSeconds = examOccupancyStaleSeconds();
        $stmt = $pdo->prepare("
            UPDATE tbl_exam_session
            SET Occupancy_Token = ?, Occupancy_At = NOW()
            WHERE Session_ID = ?
              AND Time_Ended IS NULL
              AND (
                Occupancy_Token IS NULL
                OR Occupancy_Token = ''
                OR Occupancy_Token = ?
                OR Occupancy_At IS NULL
                OR Occupancy_At < DATE_SUB(NOW(), INTERVAL {$staleSeconds} SECOND)
              )
        ");
        $stmt->execute([$token, $sessionId, $token]);
    } else {
        $stmt = $pdo->prepare('
            UPDATE tbl_exam_session
            SET Occupancy_Token = ?
            WHERE Session_ID = ?
              AND Time_Ended IS NULL
              AND (Occupancy_Token IS NULL OR Occupancy_Token = \'\' OR Occupancy_Token = ?)
        ');
        $stmt->execute([$token, $sessionId, $token]);
    }

    $check = $pdo->prepare('SELECT Occupancy_Token FROM tbl_exam_session WHERE Session_ID = ? LIMIT 1');
    $check->execute([$sessionId]);

    return (string)$check->fetchColumn() === $token;
}

function examSessionOccupancyAllows(PDO $pdo, int $sessionId, string $token): bool
{
    if ($sessionId <= 0 || $token === '') {
        return false;
    }
    if (!examOccupancyColumnExists($pdo)) {
        return true;
    }

    $stmt = $pdo->prepare('SELECT Occupancy_Token FROM tbl_exam_session WHERE Session_ID = ? LIMIT 1');
    $stmt->execute([$sessionId]);
    $current = (string)($stmt->fetchColumn() ?: '');

    return $current === '' || $current === $token;
}

function touchExamSessionOccupancy(PDO $pdo, int $sessionId, string $token): void
{
    if ($sessionId <= 0 || $token === '' || !ensureExamOccupancyColumn($pdo) || !examOccupancyAtColumnExists($pdo)) {
        return;
    }

    $stmt = $pdo->prepare('
        UPDATE tbl_exam_session
        SET Occupancy_At = NOW()
        WHERE Session_ID = ?
          AND Time_Ended IS NULL
          AND Occupancy_Token = ?
    ');
    $stmt->execute([$sessionId, $token]);
}

function releaseExamSessionOccupancy(PDO $pdo, int $sessionId, string $token): void
{
    if ($sessionId <= 0 || $token === '' || !ensureExamOccupancyColumn($pdo)) {
        return;
    }

    $clearSql = examOccupancyClearAssignmentsSql($pdo);
    if ($clearSql === '') {
        return;
    }

    $stmt = $pdo->prepare("
        UPDATE tbl_exam_session
        SET {$clearSql}
        WHERE Session_ID = ?
          AND Occupancy_Token = ?
    ");
    $stmt->execute([$sessionId, $token]);
}

function clearExamSessionOccupancy(PDO $pdo, int $sessionId): void
{
    if ($sessionId <= 0 || !examOccupancyColumnExists($pdo)) {
        return;
    }

    $clearSql = examOccupancyClearAssignmentsSql($pdo);
    if ($clearSql === '') {
        return;
    }

    $stmt = $pdo->prepare("UPDATE tbl_exam_session SET {$clearSql} WHERE Session_ID = ?");
    $stmt->execute([$sessionId]);
}
