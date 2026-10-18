<?php

// Login brute-force protection

require_once __DIR__ . '/system-settings-utils.php';

const LOGIN_MAX_ATTEMPTS = LOGIN_MAX_ATTEMPTS_DEFAULT;
const LOGIN_LOCKOUT_MINUTES = LOGIN_LOCKOUT_MINUTES_DEFAULT;

function loginAttemptTableExists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'tbl_login_attempt'");
        $exists = $stmt && $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $exists = false;
    }

    return $exists;
}

function loginAttemptUserColumnExists(PDO $pdo, bool $refresh = false): bool
{
    static $exists = null;
    if ($refresh) {
        $exists = null;
    }
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM tbl_login_attempt LIKE 'User_ID'");
        $exists = $stmt && $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $exists = false;
    }

    return $exists;
}

function ensureLoginAttemptUserColumn(PDO $pdo): bool
{
    static $ready = false;
    if ($ready) {
        return true;
    }

    if (!loginAttemptUserColumnExists($pdo)) {
        try {
            $pdo->exec('ALTER TABLE tbl_login_attempt ADD COLUMN User_ID INT NULL AFTER Attempt_ID');
        } catch (Exception $e) {
            // Duplicate column is fine if another request added it first.
        }
        if (!loginAttemptUserColumnExists($pdo, true)) {
            return false;
        }
    }

    try {
        $pdo->exec('
            ALTER TABLE tbl_login_attempt
            ADD CONSTRAINT fk_login_attempt_user
            FOREIGN KEY (User_ID) REFERENCES tbl_user (User_ID)
            ON DELETE SET NULL ON UPDATE CASCADE
        ');
    } catch (Exception $e) {
        // Constraint may already exist.
    }

    $ready = true;
    return true;
}

function resolveLoginAttemptUserId(PDO $pdo, string $identifier): ?int
{
    $identifier = trim($identifier);
    if ($identifier === '') {
        return null;
    }

    try {
        $stmt = $pdo->prepare('
            SELECT User_ID
            FROM tbl_user
            WHERE LOWER(Email) = ?
               OR LOWER(Badge_Number) = ?
            LIMIT 1
        ');
        $stmt->execute([$identifier, $identifier]);
        $userId = (int)$stmt->fetchColumn();
        return $userId > 0 ? $userId : null;
    } catch (Exception $e) {
        return null;
    }
}

function ensureLoginAttemptTable(PDO $pdo): bool
{
    if (loginAttemptTableExists($pdo)) {
        ensureLoginAttemptUserColumn($pdo);
        return true;
    }

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tbl_login_attempt (
                Attempt_ID INT NOT NULL AUTO_INCREMENT,
                User_ID INT NULL,
                Scope VARCHAR(20) NOT NULL,
                Scope_Value VARCHAR(128) NOT NULL,
                Failed_Count INT NOT NULL DEFAULT 0,
                Locked_Until DATETIME DEFAULT NULL,
                Last_Failed_At DATETIME DEFAULT NULL,
                PRIMARY KEY (Attempt_ID),
                UNIQUE KEY uq_login_attempt_scope (Scope, Scope_Value),
                KEY idx_login_attempt_locked (Locked_Until),
                KEY idx_login_attempt_user (User_ID),
                CONSTRAINT fk_login_attempt_user
                    FOREIGN KEY (User_ID) REFERENCES tbl_user (User_ID)
                    ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        return loginAttemptTableExists($pdo);
    } catch (Exception $e) {
        error_log('ensureLoginAttemptTable: ' . $e->getMessage());
        return false;
    }
}

function getLoginAttemptPolicy(PDO $pdo): array
{
    static $policy = null;
    if ($policy !== null) {
        return $policy;
    }

    $policy = [
        'max_attempts' => getLoginMaxAttempts($pdo),
        'lockout_minutes' => getLoginLockoutMinutes($pdo),
    ];

    return $policy;
}

function normalizeLoginIdentifier(string $identifier): string
{
    return strtolower(trim($identifier));
}

function getLoginLockoutMessage(DateTimeInterface $lockedUntil): string
{
    $seconds = max(0, $lockedUntil->getTimestamp() - time());
    $minutes = (int)ceil($seconds / 60);

    if ($minutes <= 1) {
        return 'Too many failed login attempts. Please try again in about 1 minute.';
    }

    return "Too many failed login attempts. Please try again in about {$minutes} minutes.";
}

function getLoginAttemptRow(PDO $pdo, string $scope, string $scopeValue): ?array
{
    if (!ensureLoginAttemptTable($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare('
        SELECT Failed_Count, Locked_Until, Last_Failed_At
        FROM tbl_login_attempt
        WHERE Scope = ? AND Scope_Value = ?
        LIMIT 1
    ');
    $stmt->execute([$scope, $scopeValue]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function resetLoginAttemptRow(PDO $pdo, string $scope, string $scopeValue): void
{
    if (!ensureLoginAttemptTable($pdo)) {
        return;
    }

    $stmt = $pdo->prepare('
        UPDATE tbl_login_attempt
        SET Failed_Count = 0,
            Locked_Until = NULL,
            Last_Failed_At = NULL
        WHERE Scope = ? AND Scope_Value = ?
    ');
    $stmt->execute([$scope, $scopeValue]);
}

function deleteLoginAttemptRow(PDO $pdo, string $scope, string $scopeValue): void
{
    if (!ensureLoginAttemptTable($pdo)) {
        return;
    }

    $stmt = $pdo->prepare('DELETE FROM tbl_login_attempt WHERE Scope = ? AND Scope_Value = ?');
    $stmt->execute([$scope, $scopeValue]);
}

function resolveExpiredLoginLock(PDO $pdo, string $scope, string $scopeValue, ?array $row): ?array
{
    if (!$row || empty($row['Locked_Until'])) {
        return $row;
    }

    $lockedUntil = new DateTimeImmutable($row['Locked_Until']);
    if ($lockedUntil > new DateTimeImmutable()) {
        return $row;
    }

    resetLoginAttemptRow($pdo, $scope, $scopeValue);
    return [
        'Failed_Count' => 0,
        'Locked_Until' => null,
        'Last_Failed_At' => null,
    ];
}

function getActiveLoginLock(PDO $pdo, string $scope, string $scopeValue): ?DateTimeImmutable
{
    $row = getLoginAttemptRow($pdo, $scope, $scopeValue);
    if (!$row) {
        return null;
    }

    $row = resolveExpiredLoginLock($pdo, $scope, $scopeValue, $row);
    if (empty($row['Locked_Until'])) {
        return null;
    }

    $lockedUntil = new DateTimeImmutable($row['Locked_Until']);
    return $lockedUntil > new DateTimeImmutable() ? $lockedUntil : null;
}

function checkLoginLockout(PDO $pdo, string $identifier): ?array
{
    if (!ensureLoginAttemptTable($pdo)) {
        return null;
    }

    $identifierKey = normalizeLoginIdentifier($identifier);
    $lockedUntil = getActiveLoginLock($pdo, 'identifier', $identifierKey);
    if ($lockedUntil) {
        return [
            'scope' => 'identifier',
            'locked_until' => $lockedUntil->format('Y-m-d H:i:s'),
            'message' => getLoginLockoutMessage($lockedUntil),
        ];
    }

    return null;
}

function recordLoginFailure(PDO $pdo, string $identifier): array
{
    $policy = getLoginAttemptPolicy($pdo);
    $maxAttempts = $policy['max_attempts'];

    if (!ensureLoginAttemptTable($pdo)) {
        return [
            'locked' => false,
            'attempts_remaining' => $maxAttempts,
            'message' => 'Invalid badge number, email, or password',
        ];
    }

    $identifierKey = normalizeLoginIdentifier($identifier);
    $userId = resolveLoginAttemptUserId($pdo, $identifierKey);
    $nowLockedUntil = incrementLoginAttemptScope($pdo, 'identifier', $identifierKey, $userId);

    if ($nowLockedUntil) {
        return [
            'locked' => true,
            'attempts_remaining' => 0,
            'locked_until' => $nowLockedUntil->format('Y-m-d H:i:s'),
            'message' => getLoginLockoutMessage($nowLockedUntil),
        ];
    }

    $identifierRow = getLoginAttemptRow($pdo, 'identifier', $identifierKey);
    $failedCount = (int)($identifierRow['Failed_Count'] ?? 0);
    $attemptsRemaining = max(0, $maxAttempts - $failedCount);

    $message = 'Invalid badge number, email, or password';
    if ($attemptsRemaining > 0 && $attemptsRemaining < $maxAttempts) {
        $message .= " ({$attemptsRemaining} attempt" . ($attemptsRemaining === 1 ? '' : 's') . ' remaining)';
    }

    return [
        'locked' => false,
        'attempts_remaining' => $attemptsRemaining,
        'message' => $message,
    ];
}

function incrementLoginAttemptScope(PDO $pdo, string $scope, string $scopeValue, ?int $userId = null): ?DateTimeImmutable
{
    $policy = getLoginAttemptPolicy($pdo);
    $row = getLoginAttemptRow($pdo, $scope, $scopeValue);
    $row = resolveExpiredLoginLock($pdo, $scope, $scopeValue, $row);

    $failedCount = (int)($row['Failed_Count'] ?? 0) + 1;
    $lockedUntil = null;
    $hasUserColumn = loginAttemptUserColumnExists($pdo);

    if ($failedCount >= $policy['max_attempts']) {
        $lockedUntil = (new DateTimeImmutable())->modify('+' . $policy['lockout_minutes'] . ' minutes');
    }

    if ($row) {
        if ($hasUserColumn) {
            $stmt = $pdo->prepare('
                UPDATE tbl_login_attempt
                SET Failed_Count = ?,
                    Locked_Until = ?,
                    Last_Failed_At = NOW(),
                    User_ID = COALESCE(?, User_ID)
                WHERE Scope = ? AND Scope_Value = ?
            ');
            $stmt->execute([
                $failedCount,
                $lockedUntil ? $lockedUntil->format('Y-m-d H:i:s') : null,
                $userId,
                $scope,
                $scopeValue,
            ]);
        } else {
            $stmt = $pdo->prepare('
                UPDATE tbl_login_attempt
                SET Failed_Count = ?,
                    Locked_Until = ?,
                    Last_Failed_At = NOW()
                WHERE Scope = ? AND Scope_Value = ?
            ');
            $stmt->execute([
                $failedCount,
                $lockedUntil ? $lockedUntil->format('Y-m-d H:i:s') : null,
                $scope,
                $scopeValue,
            ]);
        }
    } elseif ($hasUserColumn) {
        $stmt = $pdo->prepare('
            INSERT INTO tbl_login_attempt (User_ID, Scope, Scope_Value, Failed_Count, Locked_Until, Last_Failed_At)
            VALUES (?, ?, ?, ?, ?, NOW())
        ');
        $stmt->execute([
            $userId,
            $scope,
            $scopeValue,
            $failedCount,
            $lockedUntil ? $lockedUntil->format('Y-m-d H:i:s') : null,
        ]);
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO tbl_login_attempt (Scope, Scope_Value, Failed_Count, Locked_Until, Last_Failed_At)
            VALUES (?, ?, ?, ?, NOW())
        ');
        $stmt->execute([
            $scope,
            $scopeValue,
            $failedCount,
            $lockedUntil ? $lockedUntil->format('Y-m-d H:i:s') : null,
        ]);
    }

    return $lockedUntil;
}

function clearLoginAttempts(PDO $pdo, string $identifier): void
{
    if (!ensureLoginAttemptTable($pdo)) {
        return;
    }

    deleteLoginAttemptRow($pdo, 'identifier', normalizeLoginIdentifier($identifier));
}

function clearPendingCaptchaSession(): void
{
    unset(
        $_SESSION['captcha_user_id'],
        $_SESSION['captcha_role'],
        $_SESSION['captcha_name'],
        $_SESSION['captcha_email'],
        $_SESSION['captcha_badge_number'],
        $_SESSION['captcha_rank'],
        $_SESSION['captcha_must_change_password'],
        $_SESSION['captcha_answer'],
        $_SESSION['captcha_identifier']
    );
}
