<?php
/**
 * Exam code generation and validation helpers.
 */

require_once __DIR__ . '/audit-log-utils.php';

/**
 * Normalize user-entered exam code for comparison.
 */
function normalizeExamCode($code) {
    return strtoupper(preg_replace('/[\s\-]/', '', trim((string)$code)));
}

/**
 * Generate a unique 6-character exam code (Google Classroom style).
 */
function generateUniqueExamCode(PDO $pdo, int $length = 6): string {
    $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $maxAttempts = 100;

    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }

        $stmt = $pdo->prepare('
            SELECT Exam_ID
            FROM tbl_exam
            WHERE Exam_Code = ?
              AND (Is_Archived = 0 OR Is_Archived IS NULL)
            LIMIT 1
        ');
        $stmt->execute([$code]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            return $code;
        }
    }

    throw new RuntimeException('Unable to generate a unique exam code');
}

/**
 * Check whether exam code columns exist (migration applied).
 */
function examCodeColumnsExist(PDO $pdo): bool {
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM tbl_exam LIKE 'Exam_Code'");
        $exists = $stmt && $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $exists = false;
    }

    return $exists;
}

/**
 * Log an exam code verification attempt when attempt table exists.
 */
function logExamCodeAttempt(PDO $pdo, int $examId, int $userId, bool $success): void {
    try {
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'tbl_exam_code_attempt'");
        if (!$tableCheck || $tableCheck->rowCount() === 0) {
            return;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $pdo->prepare('
            INSERT INTO tbl_exam_code_attempt (Exam_ID, User_ID, IP_Address, Success)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$examId, $userId, $ip, $success ? 1 : 0]);
    } catch (Exception $e) {
        error_log('Failed to log exam code attempt: ' . $e->getMessage());
    }
}

/**
 * Return failed attempt count in the last 15 minutes for rate limiting.
 */
function getRecentFailedCodeAttempts(PDO $pdo, int $examId, int $userId): int {
    try {
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'tbl_exam_code_attempt'");
        if (!$tableCheck || $tableCheck->rowCount() === 0) {
            return 0;
        }

        $stmt = $pdo->prepare('
            SELECT COUNT(*) AS fail_count
            FROM tbl_exam_code_attempt
            WHERE Exam_ID = ?
              AND User_ID = ?
              AND Success = 0
              AND Attempted_At >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ');
        $stmt->execute([$examId, $userId]);
        return (int)($stmt->fetch(PDO::FETCH_ASSOC)['fail_count'] ?? 0);
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Write a simple audit log entry when audit table is available.
 * @deprecated Use writeAuditLog() / auditFromSession() from audit-log-utils.php
 */
function logExamAuditAction(PDO $pdo, int $userId, string $action, string $outcome): void {
    writeAuditLog($pdo, [
        'user_id' => $userId,
        'user_role' => $_SESSION['user_role'] ?? null,
        'module' => 'EXAM',
        'action' => $action,
        'outcome' => $outcome,
        'status' => 'SUCCESS',
        'entity_type' => 'exam',
    ]);
}

/**
 * Check whether Allow_Response_Review column exists.
 */
function responseReviewColumnExists(PDO $pdo): bool {
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM tbl_exam LIKE 'Allow_Response_Review'");
        $exists = $stmt && $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $exists = false;
    }

    return $exists;
}

/**
 * Determine if examinees may view question-level responses for an exam row.
 */
function examAllowsResponseReview(array $examRow, bool $columnExists = true): bool {
    if (!$columnExists) {
        return true;
    }

    return !empty($examRow['Allow_Response_Review']);
}

/**
 * Check whether Personnel_Rank column exists on tbl_user.
 */
function personnelRankColumnExists(PDO $pdo): bool {
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM tbl_user LIKE 'Personnel_Rank'");
        $exists = $stmt && $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $exists = false;
    }

    return $exists;
}

/**
 * Grade scale: (score / total items) * 50 + 50  →  range 50–100.
 */
function computeGradePercent(float $score, int $totalItems): float {
    if ($totalItems <= 0) {
        return 50.0;
    }

    $ratio = $score / $totalItems;
    if ($ratio < 0) {
        $ratio = 0;
    } elseif ($ratio > 1) {
        $ratio = 1;
    }

    return round(($ratio * 50) + 50, 2);
}

/**
 * Map exam Passing_Score (0–100 raw %) to grade-scale passing threshold.
 */
function computeGradePassingThreshold(float $passingScorePercent): float {
    $pct = max(0, min(100, $passingScorePercent));
    return round(($pct / 100) * 50 + 50, 2);
}

/**
 * Standard PNP personnel ranks for dropdowns.
 */
function getPersonnelRankOptions(): array {
    return [
        'Pat', 'PCpl', 'PSSg', 'SSg', 'TSg', 'MSg',
        'PCMS', 'PSMS', 'SMS', 'PCMaj', 'PMaj',
        'PLt', 'P01Lt', 'P02Lt', 'PCpt', 'PMaj (Comm)',
        'PLtCol', 'PCol', 'PBGen', 'PMGen', 'PLtGen', 'PGen'
    ];
}
