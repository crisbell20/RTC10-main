<?php
/**
 * Finalize overdue exams: auto-submit in-progress sessions and fail assigned no-shows.
 */

require_once __DIR__ . '/validation-utils.php';
require_once __DIR__ . '/exam-participant-utils.php';
require_once __DIR__ . '/exam-code-utils.php';

/**
 * Exam IDs whose deadline (+ grace) has passed and should be finalized.
 *
 * @return list<int>
 */
function fetchExamsDueForFinalization(PDO $pdo, ?int $onlyExamId = null): array {
    $archivedFilter = '';
    try {
        $colCheck = $pdo->query("SHOW COLUMNS FROM tbl_exam LIKE 'Is_Archived'");
        if ($colCheck && $colCheck->rowCount() > 0) {
            $archivedFilter = 'AND (e.Is_Archived = 0 OR e.Is_Archived IS NULL)';
        }
    } catch (Exception $e) {
        $archivedFilter = '';
    }

    $graceMinutes = (int)(EXAM_DEADLINE_GRACE_SECONDS / 60);
    $params = [];
    $examFilter = '';

    if ($onlyExamId !== null && $onlyExamId > 0) {
        $examFilter = 'AND e.Exam_ID = ?';
        $params[] = $onlyExamId;
    }

    $sql = "
        SELECT e.Exam_ID
        FROM tbl_exam e
        WHERE e.Deadline IS NOT NULL
          AND DATE_ADD(e.Deadline, INTERVAL {$graceMinutes} MINUTE) < NOW()
          AND e.Status IN ('Published', 'Closed')
          {$archivedFilter}
          {$examFilter}
        ORDER BY e.Exam_ID
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Active examinee user IDs assigned to an exam (batch + optional participant allowlist).
 *
 * @return list<int>
 */
function fetchAssignedExamineeUserIds(PDO $pdo, int $examId): array {
    if ($examId <= 0) {
        return [];
    }

    if (examParticipantTableExists($pdo)) {
        $participantIds = getExamParticipantIds($pdo, $examId);
        if (!empty($participantIds)) {
            $eligible = [];
            foreach ($participantIds as $userId) {
                if (isExamParticipant($pdo, $examId, $userId)) {
                    $stmt = $pdo->prepare('SELECT 1 FROM tbl_user_batch WHERE User_ID = ? AND Status = \'Active\' LIMIT 1');
                    $stmt->execute([$userId]);
                    if ($stmt->fetchColumn()) {
                        $eligible[] = $userId;
                    }
                }
            }

            return $eligible;
        }
    }

    $participantFilter = '';
    $params = [$examId];

    if (examParticipantTableExists($pdo)) {
        $participantFilter = "
          AND (
              NOT EXISTS (SELECT 1 FROM tbl_exam_participant ep_all WHERE ep_all.Exam_ID = ?)
              OR EXISTS (
                  SELECT 1 FROM tbl_exam_participant ep_user
                  WHERE ep_user.Exam_ID = ? AND ep_user.User_ID = u.User_ID
              )
          )";
        $params[] = $examId;
        $params[] = $examId;
    }

    $stmt = $pdo->prepare("
        SELECT DISTINCT u.User_ID
        FROM tbl_exam_batch eb
        INNER JOIN tbl_user_batch ub ON eb.Batch_ID = ub.Batch_ID
        INNER JOIN tbl_user u ON ub.User_ID = u.User_ID
        WHERE eb.Exam_ID = ?
          AND ub.Status = 'Active'
          {$participantFilter}
        ORDER BY u.User_ID
    ");
    $stmt->execute($params);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Record a failed attempt for an assigned examinee who never submitted before the deadline.
 */
function recordMissedDeadlineFailure(PDO $pdo, int $examId, int $userId): void {
    if (userHasCompletedExamAttempt($pdo, $userId, $examId)) {
        return;
    }

    $stmt = $pdo->prepare('
        SELECT Session_ID
        FROM tbl_exam_session
        WHERE Exam_ID = ? AND User_ID = ? AND Time_Ended IS NULL
        ORDER BY Session_ID DESC
        LIMIT 1
    ');
    $stmt->execute([$examId, $userId]);
    $activeSessionId = (int)$stmt->fetchColumn();

    if ($activeSessionId > 0) {
        completeExamSessionSubmission($pdo, $activeSessionId);
        return;
    }

    $stmt = $pdo->prepare('
        SELECT es.Session_ID
        FROM tbl_exam_session es
        LEFT JOIN tbl_result r ON r.Session_ID = es.Session_ID
        WHERE es.Exam_ID = ? AND es.User_ID = ?
          AND es.Time_Ended IS NOT NULL
          AND r.Result_ID IS NULL
        ORDER BY es.Session_ID DESC
        LIMIT 1
    ');
    $stmt->execute([$examId, $userId]);
    $orphanSessionId = (int)$stmt->fetchColumn();

    if ($orphanSessionId > 0) {
        completeExamSessionSubmission($pdo, $orphanSessionId);
        return;
    }

    $stmt = $pdo->prepare('SELECT Deadline FROM tbl_exam WHERE Exam_ID = ?');
    $stmt->execute([$examId]);
    $deadline = $stmt->fetchColumn();
    $startedAt = $deadline ? date('Y-m-d H:i:s', parseStoredDateTimeTimestamp($deadline) ?: time()) : date('Y-m-d H:i:s');

    $stmt = $pdo->prepare('
        INSERT INTO tbl_exam_session (Exam_ID, User_ID, Time_Started, Time_Ended)
        VALUES (?, ?, ?, NOW())
    ');
    $stmt->execute([$examId, $userId, $startedAt]);
    $sessionId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare('
        INSERT INTO tbl_result (Session_ID, Score, Percentage, Remarks, Submission_Date)
        VALUES (?, 0, 0, \'Failed\', NOW())
    ');
    $stmt->execute([$sessionId]);
}

/**
 * Finalize one exam after deadline (+ grace): auto-submit active sessions, then fail no-shows.
 *
 * @return array{auto_submitted:int,missed_failed:int,errors:int}
 */
function finalizeExamAfterDeadline(PDO $pdo, int $examId): array {
    $stats = ['auto_submitted' => 0, 'missed_failed' => 0, 'errors' => 0];

    if ($examId <= 0) {
        return $stats;
    }

    $stmt = $pdo->prepare('
        SELECT Exam_ID
        FROM tbl_exam
        WHERE Exam_ID = ?
          AND Deadline IS NOT NULL
          AND DATE_ADD(Deadline, INTERVAL ? MINUTE) < NOW()
    ');
    $graceMinutes = (int)(EXAM_DEADLINE_GRACE_SECONDS / 60);
    $stmt->execute([$examId, $graceMinutes]);
    if (!$stmt->fetchColumn()) {
        return $stats;
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('
            SELECT Session_ID
            FROM tbl_exam_session
            WHERE Exam_ID = ? AND Time_Started IS NOT NULL AND Time_Ended IS NULL
        ');
        $stmt->execute([$examId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $sessionId) {
            try {
                completeExamSessionSubmission($pdo, (int)$sessionId);
                $stats['auto_submitted']++;
            } catch (Exception $e) {
                error_log('finalizeExamAfterDeadline auto-submit session ' . $sessionId . ': ' . $e->getMessage());
                $stats['errors']++;
            }
        }

        foreach (fetchAssignedExamineeUserIds($pdo, $examId) as $userId) {
            if (userHasCompletedExamAttempt($pdo, $userId, $examId)) {
                continue;
            }

            try {
                recordMissedDeadlineFailure($pdo, $examId, $userId);
                $stats['missed_failed']++;
            } catch (Exception $e) {
                error_log('finalizeExamAfterDeadline missed user ' . $userId . ' exam ' . $examId . ': ' . $e->getMessage());
                $stats['errors']++;
            }
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return $stats;
}

/**
 * Close Published exams past deadline (+ grace) and finalize all overdue attempts.
 *
 * @return array{exams_closed:int,auto_submitted:int,missed_failed:int,errors:int}
 */
function finalizeAllOverdueExams(PDO $pdo): array {
    $totals = [
        'exams_closed' => 0,
        'auto_submitted' => 0,
        'missed_failed' => 0,
        'errors' => 0,
    ];

    try {
        $graceMinutes = (int)(EXAM_DEADLINE_GRACE_SECONDS / 60);
        $closeSql = "
            UPDATE tbl_exam
            SET Status = 'Closed'
            WHERE Status = 'Published'
              AND Deadline IS NOT NULL
              AND DATE_ADD(Deadline, INTERVAL {$graceMinutes} MINUTE) < NOW()
        ";
        $totals['exams_closed'] = (int)$pdo->exec($closeSql);
    } catch (PDOException $e) {
        error_log('finalizeAllOverdueExams close: ' . $e->getMessage());
    }

    foreach (fetchExamsDueForFinalization($pdo) as $examId) {
        $stats = finalizeExamAfterDeadline($pdo, $examId);
        $totals['auto_submitted'] += $stats['auto_submitted'];
        $totals['missed_failed'] += $stats['missed_failed'];
        $totals['errors'] += $stats['errors'];
    }

    return $totals;
}

/**
 * Run full overdue finalization at most once per PHP request.
 */
function runOverdueExamFinalizationOnce(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    finalizeAllOverdueExams($pdo);
}
