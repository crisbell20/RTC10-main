<?php
/**
 * Centralized audit logging for PNP RTC X.
 * Append-only: never update or delete via application except retention purge (Admin).
 */

function getClientIpAddress(): string
{
    $candidates = [
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ];

    foreach ($candidates as $value) {
        if (!$value) {
            continue;
        }
        $parts = array_map('trim', explode(',', (string)$value));
        if (!empty($parts[0])) {
            return substr($parts[0], 0, 45);
        }
    }

    return 'unknown';
}

function auditLogTableExists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'tbl_audit_log'");
        $exists = $stmt && $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $exists = false;
    }

    return $exists;
}

function auditLogExtendedColumnsExist(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    if (!auditLogTableExists($pdo)) {
        $exists = false;
        return false;
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM tbl_audit_log LIKE 'Module'");
        $exists = $stmt && $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $exists = false;
    }

    return $exists;
}

/**
 * Write an audit log entry. Never throws to callers.
 *
 * @param array{
 *   user_id?: int|null,
 *   user_role?: string|null,
 *   module?: string,
 *   action: string,
 *   outcome?: string,
 *   status?: string,
 *   entity_type?: string|null,
 *   entity_id?: int|null,
 *   ip_address?: string|null
 * } $entry
 */
function writeAuditLog(PDO $pdo, array $entry): void
{
    if (!auditLogTableExists($pdo)) {
        return;
    }

    $action = trim((string)($entry['action'] ?? ''));
    if ($action === '') {
        return;
    }

    $userId = isset($entry['user_id']) ? ($entry['user_id'] !== null ? (int)$entry['user_id'] : null) : null;
    $module = trim((string)($entry['module'] ?? inferAuditModuleFromAction($action)));
    $outcome = trim((string)($entry['outcome'] ?? ''));
    $status = strtoupper(trim((string)($entry['status'] ?? 'SUCCESS')));
    if (!in_array($status, ['SUCCESS', 'FAILED'], true)) {
        $status = 'SUCCESS';
    }
    $entityType = isset($entry['entity_type']) ? trim((string)$entry['entity_type']) : null;
    $entityId = isset($entry['entity_id']) && $entry['entity_id'] !== null ? (int)$entry['entity_id'] : null;
    $userRole = isset($entry['user_role']) ? trim((string)$entry['user_role']) : null;
    $ipAddress = isset($entry['ip_address']) ? trim((string)$entry['ip_address']) : getClientIpAddress();

    try {
        if (auditLogExtendedColumnsExist($pdo)) {
            $stmt = $pdo->prepare('
                INSERT INTO tbl_audit_log
                    (User_ID, Action, Module, Outcome, Status, Entity_Type, Entity_ID, IP_Address, User_Role)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $userId,
                $action,
                $module ?: null,
                $outcome ?: null,
                $status,
                $entityType ?: null,
                $entityId,
                $ipAddress ?: null,
                $userRole ?: null,
            ]);
            return;
        }

        if ($userId === null) {
            return;
        }

        $legacyOutcome = $outcome !== '' ? $outcome : $action;
        if ($status === 'FAILED') {
            $legacyOutcome = '[FAILED] ' . $legacyOutcome;
        }

        $stmt = $pdo->prepare('INSERT INTO tbl_audit_log (User_ID, Action, Outcome) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $action, substr($legacyOutcome, 0, 100)]);
    } catch (Exception $e) {
        error_log('Failed to write audit log: ' . $e->getMessage());
    }
}

function auditFromSession(
    PDO $pdo,
    string $module,
    string $action,
    string $outcome,
    string $status = 'SUCCESS',
    ?string $entityType = null,
    ?int $entityId = null
): void {
    writeAuditLog($pdo, [
        'user_id' => isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
        'user_role' => $_SESSION['user_role'] ?? null,
        'module' => $module,
        'action' => $action,
        'outcome' => $outcome,
        'status' => $status,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
    ]);
}

function inferAuditModuleFromAction(string $action): string
{
    $prefix = strtoupper(explode('_', $action)[0] ?? '');
    $map = [
        'LOGIN' => 'AUTH',
        'LOGOUT' => 'AUTH',
        'PASSWORD' => 'AUTH',
        'CREATE' => 'SYSTEM',
        'UPDATE' => 'SYSTEM',
        'DELETE' => 'SYSTEM',
        'VIEW' => 'SYSTEM',
        'EXPORT' => 'REPORT',
        'IMPORT' => 'QUESTION',
        'RESET' => 'EXAM',
        'ARCHIVE' => 'EXAM',
        'RESTORE' => 'EXAM',
        'CLOSE' => 'EXAM',
        'TOGGLE' => 'EXAM',
        'ASSIGN' => 'BATCH',
        'REMOVE' => 'BATCH',
        'PURGE' => 'SYSTEM',
    ];

    foreach ($map as $key => $module) {
        if (str_starts_with(strtoupper($action), $key)) {
            return $module;
        }
    }

    if (str_contains(strtoupper($action), 'EXAM')) {
        return 'EXAM';
    }
    if (str_contains(strtoupper($action), 'USER')) {
        return 'USER';
    }
    if (str_contains(strtoupper($action), 'QUESTION')) {
        return 'QUESTION';
    }
    if (str_contains(strtoupper($action), 'COURSE')) {
        return 'COURSE';
    }
    if (str_contains(strtoupper($action), 'SUBJECT')) {
        return 'SUBJECT';
    }
    if (str_contains(strtoupper($action), 'BATCH')) {
        return 'BATCH';
    }

    return 'SYSTEM';
}

function getAuditModuleOptions(): array
{
    return ['AUTH', 'USER', 'EXAM', 'QUESTION', 'COURSE', 'SUBJECT', 'BATCH', 'REPORT', 'SYSTEM'];
}

function getAuditActionOptions(): array
{
    return [
        'LOGIN', 'LOGIN_FAILED', 'LOGOUT', 'PASSWORD_CHANGE',
        'CREATE_USER', 'UPDATE_USER', 'DELETE_USER', 'RESET_PASSWORD',
        'CREATE_EXAM', 'UPDATE_EXAM', 'DELETE_EXAM', 'ARCHIVE_EXAM', 'RESTORE_EXAM', 'CLOSE_EXAM',
        'RESET_EXAM_CODE', 'TOGGLE_RESPONSE_REVIEW', 'VIEW_EXAM_RESPONSE', 'ASSIGN_EXAM_QUESTIONS', 'ASSIGN_EXAM_BATCH',
        'CREATE_QUESTION', 'UPDATE_QUESTION', 'DELETE_QUESTION', 'IMPORT_QUESTIONS',
        'CREATE_COURSE', 'UPDATE_COURSE', 'DELETE_COURSE',
        'CREATE_SUBJECT', 'UPDATE_SUBJECT', 'DELETE_SUBJECT',
        'ASSIGN_USER_BATCH', 'REMOVE_USER_BATCH',
        'EXPORT_REPORT', 'EXPORT_EXAM_RESPONSES', 'EXPORT_AUDIT_LOGS',
        'PURGE_AUDIT_LOGS',
    ];
}
