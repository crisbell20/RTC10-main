<?php
/**
 * System-wide configurable settings (key/value store).
 */

require_once __DIR__ . '/archive-utils.php';
require_once __DIR__ . '/audit-log-utils.php';

const SYSTEM_SETTING_ARCHIVE_RETENTION = 'archive_retention_days';
const SYSTEM_SETTING_AUDIT_RETENTION = 'audit_log_retention_days';
const SYSTEM_SETTING_LOGIN_MAX_ATTEMPTS = 'login_max_attempts';
const SYSTEM_SETTING_LOGIN_LOCKOUT_MINUTES = 'login_lockout_minutes';
const SYSTEM_SETTING_IDLE_TIMEOUT_MINUTES = 'idle_timeout_minutes';
const SYSTEM_SETTING_CHEATING_ATTEMPT_LIMIT = 'cheating_attempt_limit';

const LOGIN_MAX_ATTEMPTS_DEFAULT = 5;
const LOGIN_MAX_ATTEMPTS_MIN = 3;
const LOGIN_MAX_ATTEMPTS_MAX = 10;
const LOGIN_LOCKOUT_MINUTES_DEFAULT = 15;
const LOGIN_LOCKOUT_MINUTES_MIN = 5;
const LOGIN_LOCKOUT_MINUTES_MAX = 60;
const IDLE_TIMEOUT_MINUTES_DEFAULT = 5;
const IDLE_TIMEOUT_MINUTES_MIN = 5;
const IDLE_TIMEOUT_MINUTES_MAX = 60;
const CHEATING_ATTEMPT_LIMIT_DEFAULT = 5;
const CHEATING_ATTEMPT_LIMIT_MIN = 1;
const CHEATING_ATTEMPT_LIMIT_MAX = 20;

/**
 * @return array<string,string>
 */
function systemSettingDefaults(): array
{
    return [
        SYSTEM_SETTING_ARCHIVE_RETENTION => (string)ARCHIVE_RETENTION_DAYS,
        SYSTEM_SETTING_AUDIT_RETENTION => (string)AUDIT_LOG_RETENTION_DAYS,
        SYSTEM_SETTING_LOGIN_MAX_ATTEMPTS => (string)LOGIN_MAX_ATTEMPTS_DEFAULT,
        SYSTEM_SETTING_LOGIN_LOCKOUT_MINUTES => (string)LOGIN_LOCKOUT_MINUTES_DEFAULT,
        SYSTEM_SETTING_IDLE_TIMEOUT_MINUTES => (string)IDLE_TIMEOUT_MINUTES_DEFAULT,
        SYSTEM_SETTING_CHEATING_ATTEMPT_LIMIT => (string)CHEATING_ATTEMPT_LIMIT_DEFAULT,
    ];
}

function systemSettingsTableExists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'tbl_system_setting'");
        $exists = $stmt && $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $exists = false;
    }

    return $exists;
}

function ensureSystemSettingsTable(PDO $pdo): bool
{
    if (systemSettingsTableExists($pdo)) {
        return true;
    }

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tbl_system_setting (
                Setting_Key VARCHAR(64) NOT NULL PRIMARY KEY,
                Setting_Value VARCHAR(255) NOT NULL,
                Updated_At DATETIME DEFAULT NULL,
                Updated_By INT DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        seedSystemSettingDefaults($pdo);

        return true;
    } catch (Exception $e) {
        error_log('ensureSystemSettingsTable failed: ' . $e->getMessage());
        return false;
    }
}

function seedSystemSettingDefaults(PDO $pdo): void
{
    static $seeded = false;
    if ($seeded) {
        return;
    }

    try {
        $insert = $pdo->prepare('
            INSERT INTO tbl_system_setting (Setting_Key, Setting_Value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE Setting_Value = Setting_Value
        ');
        foreach (systemSettingDefaults() as $key => $value) {
            $insert->execute([$key, $value]);
        }
        $seeded = true;
    } catch (Exception $e) {
        error_log('seedSystemSettingDefaults failed: ' . $e->getMessage());
    }
}

function clampArchiveRetentionDays(int $days): int
{
    return max(ARCHIVE_RETENTION_DAYS_MIN, min(ARCHIVE_RETENTION_DAYS_MAX, $days));
}

function clampAuditLogRetentionDays(int $days): int
{
    return max(AUDIT_LOG_RETENTION_DAYS_MIN, min(AUDIT_LOG_RETENTION_DAYS_MAX, $days));
}

function getSystemSetting(PDO $pdo, string $key, ?string $default = null): ?string
{
    $default = $default ?? (systemSettingDefaults()[$key] ?? null);
    if (!ensureSystemSettingsTable($pdo)) {
        return $default;
    }

    seedSystemSettingDefaults($pdo);

    $stmt = $pdo->prepare('SELECT Setting_Value FROM tbl_system_setting WHERE Setting_Key = ? LIMIT 1');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();

    return $value !== false ? (string)$value : $default;
}

function getArchiveRetentionDays(PDO $pdo): int
{
    $raw = (int)getSystemSetting($pdo, SYSTEM_SETTING_ARCHIVE_RETENTION, (string)ARCHIVE_RETENTION_DAYS);
    return clampArchiveRetentionDays($raw > 0 ? $raw : ARCHIVE_RETENTION_DAYS);
}

function getAuditLogRetentionDays(PDO $pdo): int
{
    $raw = (int)getSystemSetting($pdo, SYSTEM_SETTING_AUDIT_RETENTION, (string)AUDIT_LOG_RETENTION_DAYS);
    return clampAuditLogRetentionDays($raw > 0 ? $raw : AUDIT_LOG_RETENTION_DAYS);
}

/**
 * @return array{
 *   archive_retention_days:int,
 *   audit_log_retention_days:int,
 *   archive_retention_min:int,
 *   archive_retention_max:int,
 *   audit_log_retention_min:int,
 *   audit_log_retention_max:int
 * }
 */
function getRetentionSettings(PDO $pdo): array
{
    return [
        'archive_retention_days' => getArchiveRetentionDays($pdo),
        'audit_log_retention_days' => getAuditLogRetentionDays($pdo),
        'archive_retention_min' => ARCHIVE_RETENTION_DAYS_MIN,
        'archive_retention_max' => ARCHIVE_RETENTION_DAYS_MAX,
        'audit_log_retention_min' => AUDIT_LOG_RETENTION_DAYS_MIN,
        'audit_log_retention_max' => AUDIT_LOG_RETENTION_DAYS_MAX,
    ];
}

/**
 * @param array<string,int> $settings
 */
function saveRetentionSettings(PDO $pdo, array $settings, int $userId): array
{
    if (!ensureSystemSettingsTable($pdo)) {
        throw new RuntimeException('System settings table is not available');
    }

    $archiveDays = clampArchiveRetentionDays((int)($settings['archive_retention_days'] ?? ARCHIVE_RETENTION_DAYS));
    $auditDays = clampAuditLogRetentionDays((int)($settings['audit_log_retention_days'] ?? AUDIT_LOG_RETENTION_DAYS));

    upsertSystemSetting($pdo, SYSTEM_SETTING_ARCHIVE_RETENTION, (string)$archiveDays, $userId);
    upsertSystemSetting($pdo, SYSTEM_SETTING_AUDIT_RETENTION, (string)$auditDays, $userId);

    return getRetentionSettings($pdo);
}

function upsertSystemSetting(PDO $pdo, string $key, string $value, int $userId): void
{
    $upsert = $pdo->prepare('
        INSERT INTO tbl_system_setting (Setting_Key, Setting_Value, Updated_At, Updated_By)
        VALUES (?, ?, NOW(), ?)
        ON DUPLICATE KEY UPDATE Setting_Value = VALUES(Setting_Value), Updated_At = NOW(), Updated_By = VALUES(Updated_By)
    ');
    $upsert->execute([$key, $value, $userId]);
}

function clampIntRange(int $value, int $min, int $max, int $fallback): int
{
    if ($value <= 0) {
        $value = $fallback;
    }
    return max($min, min($max, $value));
}

function clampLoginMaxAttempts(int $attempts): int
{
    return clampIntRange($attempts, LOGIN_MAX_ATTEMPTS_MIN, LOGIN_MAX_ATTEMPTS_MAX, LOGIN_MAX_ATTEMPTS_DEFAULT);
}

function clampLoginLockoutMinutes(int $minutes): int
{
    return clampIntRange($minutes, LOGIN_LOCKOUT_MINUTES_MIN, LOGIN_LOCKOUT_MINUTES_MAX, LOGIN_LOCKOUT_MINUTES_DEFAULT);
}

function clampIdleTimeoutMinutes(int $minutes): int
{
    return clampIntRange($minutes, IDLE_TIMEOUT_MINUTES_MIN, IDLE_TIMEOUT_MINUTES_MAX, IDLE_TIMEOUT_MINUTES_DEFAULT);
}

function clampCheatingAttemptLimit(int $limit): int
{
    return clampIntRange($limit, CHEATING_ATTEMPT_LIMIT_MIN, CHEATING_ATTEMPT_LIMIT_MAX,CHEATING_ATTEMPT_LIMIT_DEFAULT);
}

function getLoginMaxAttempts(PDO $pdo): int
{
    $raw = (int)getSystemSetting($pdo, SYSTEM_SETTING_LOGIN_MAX_ATTEMPTS, (string)LOGIN_MAX_ATTEMPTS_DEFAULT);
    return clampLoginMaxAttempts($raw);
}

function getLoginLockoutMinutes(PDO $pdo): int
{
    $raw = (int)getSystemSetting($pdo, SYSTEM_SETTING_LOGIN_LOCKOUT_MINUTES, (string)LOGIN_LOCKOUT_MINUTES_DEFAULT);
    return clampLoginLockoutMinutes($raw);
}

function getIdleTimeoutMinutes(PDO $pdo): int
{
    $raw = (int)getSystemSetting($pdo, SYSTEM_SETTING_IDLE_TIMEOUT_MINUTES, (string)IDLE_TIMEOUT_MINUTES_DEFAULT);
    return clampIdleTimeoutMinutes($raw);
}

function getCheatingAttemptLimit(PDO $pdo): int
{
    $raw = (int)getSystemSetting($pdo, SYSTEM_SETTING_CHEATING_ATTEMPT_LIMIT, (string)CHEATING_ATTEMPT_LIMIT_DEFAULT);
    return clampCheatingAttemptLimit($raw);
}

/**
 * @return array{
 *   login_max_attempts:int,
 *   login_lockout_minutes:int,
 *   idle_timeout_minutes:int,
 *   login_max_attempts_min:int,
 *   login_max_attempts_max:int,
 *   login_lockout_minutes_min:int,
 *   login_lockout_minutes_max:int,
 *   idle_timeout_minutes_min:int,
 *   idle_timeout_minutes_max:int,
 *   cheating_attempt_limit:int,
 *   cheating_attempt_limit_min:int,
 *   cheating_attempt_limit_max:int
 * }
 */
function getSecuritySettings(PDO $pdo): array
{
    return [
        'login_max_attempts' => getLoginMaxAttempts($pdo),
        'login_lockout_minutes' => getLoginLockoutMinutes($pdo),
        'idle_timeout_minutes' => getIdleTimeoutMinutes($pdo),
        'cheating_attempt_limit' => getCheatingAttemptLimit($pdo),
        'login_max_attempts_min' => LOGIN_MAX_ATTEMPTS_MIN,
        'login_max_attempts_max' => LOGIN_MAX_ATTEMPTS_MAX,
        'login_lockout_minutes_min' => LOGIN_LOCKOUT_MINUTES_MIN,
        'login_lockout_minutes_max' => LOGIN_LOCKOUT_MINUTES_MAX,
        'idle_timeout_minutes_min' => IDLE_TIMEOUT_MINUTES_MIN,
        'idle_timeout_minutes_max' => IDLE_TIMEOUT_MINUTES_MAX,
        'cheating_attempt_limit_min' => CHEATING_ATTEMPT_LIMIT_MIN,
        'cheating_attempt_limit_max' => CHEATING_ATTEMPT_LIMIT_MAX,
    ];
}

/**
 * @return array{
 *   archive_retention_days:int,
 *   audit_log_retention_days:int,
 *   archive_retention_min:int,
 *   archive_retention_max:int,
 *   audit_log_retention_min:int,
 *   audit_log_retention_max:int,
 *   login_max_attempts:int,
 *   login_lockout_minutes:int,
 *   idle_timeout_minutes:int,
 *   login_max_attempts_min:int,
 *   login_max_attempts_max:int,
 *   login_lockout_minutes_min:int,
 *   login_lockout_minutes_max:int,
 *   idle_timeout_minutes_min:int,
 *   idle_timeout_minutes_max:int
 * }
 */
function getAdminSystemSettings(PDO $pdo): array
{
    return array_merge(getRetentionSettings($pdo), getSecuritySettings($pdo));
}

/**
 * @param array<string,int> $settings
 */
function saveSecuritySettings(PDO $pdo, array $settings, int $userId): array
{
    if (!ensureSystemSettingsTable($pdo)) {
        throw new RuntimeException('System settings table is not available');
    }

    $maxAttempts = clampLoginMaxAttempts((int)($settings['login_max_attempts'] ?? LOGIN_MAX_ATTEMPTS_DEFAULT));
    $lockoutMinutes = clampLoginLockoutMinutes((int)($settings['login_lockout_minutes'] ?? LOGIN_LOCKOUT_MINUTES_DEFAULT));
    $idleMinutes = clampIdleTimeoutMinutes((int)($settings['idle_timeout_minutes'] ?? IDLE_TIMEOUT_MINUTES_DEFAULT));
    $cheatingAttemptLimit = clampCheatingAttemptLimit((int)($settings['cheating_attempt_limit'] ?? CHEATING_ATTEMPT_LIMIT_DEFAULT));

    upsertSystemSetting($pdo, SYSTEM_SETTING_LOGIN_MAX_ATTEMPTS, (string)$maxAttempts, $userId);
    upsertSystemSetting($pdo, SYSTEM_SETTING_LOGIN_LOCKOUT_MINUTES, (string)$lockoutMinutes, $userId);
    upsertSystemSetting($pdo, SYSTEM_SETTING_IDLE_TIMEOUT_MINUTES, (string)$idleMinutes, $userId);
    upsertSystemSetting($pdo, SYSTEM_SETTING_CHEATING_ATTEMPT_LIMIT, (string)$cheatingAttemptLimit,$userId);
    return getSecuritySettings($pdo);
}
