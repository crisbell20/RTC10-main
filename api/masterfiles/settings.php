<?php
/**
 * Admin system settings API.
 */

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/connection-pdo.php';
require_once __DIR__ . '/../includes/audit-log-utils.php';
require_once __DIR__ . '/../includes/system-settings-utils.php';

$requestMethod = $_SERVER['REQUEST_METHOD'];
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? [];
$action = $_GET['action'] ?? ($input['action'] ?? 'get');

function sendSuccess(array $data = []): void
{
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

function sendError(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

try {
    if ($requestMethod === 'GET' && $action === 'get') {
        sendSuccess(getAdminSystemSettings($pdo));
    }

    if ($requestMethod === 'POST' && $action === 'update') {
        $archiveDays = (int)($input['archive_retention_days'] ?? 0);
        $auditDays = (int)($input['audit_log_retention_days'] ?? 0);

        if ($archiveDays <= 0 || $auditDays <= 0) {
            sendError('Retention days must be greater than zero');
        }

        $before = getRetentionSettings($pdo);
        $saved = saveRetentionSettings($pdo, [
            'archive_retention_days' => $archiveDays,
            'audit_log_retention_days' => $auditDays,
        ], (int)$_SESSION['user_id']);

        auditFromSession(
            $pdo,
            'SYSTEM',
            'UPDATE_SYSTEM_SETTINGS',
            sprintf(
                'Retention updated: archive %d→%d days, audit logs %d→%d days',
                $before['archive_retention_days'],
                $saved['archive_retention_days'],
                $before['audit_log_retention_days'],
                $saved['audit_log_retention_days']
            ),
            'SUCCESS',
            'system_setting',
            null
        );

        sendSuccess([
            'settings' => $saved,
            'message' => 'System settings saved',
        ]);
    }

    if ($requestMethod === 'POST' && $action === 'update_security') {
        $maxAttempts = (int)($input['login_max_attempts'] ?? 0);
        $lockoutMinutes = (int)($input['login_lockout_minutes'] ?? 0);
        $idleMinutes = (int)($input['idle_timeout_minutes'] ?? 0);
        $cheatingAttemptLimit = (int)($input['cheating_attempt_limit'] ?? 0);

        if ($maxAttempts <= 0 || $lockoutMinutes <= 0 || $idleMinutes <= 0 || $cheatingAttemptLimit <= 0) {
            sendError('Security policy values must be greater than zero');
        }

        $before = getSecuritySettings($pdo);
        $saved = saveSecuritySettings($pdo, [
            'login_max_attempts' => $maxAttempts,
            'login_lockout_minutes' => $lockoutMinutes,
            'idle_timeout_minutes' => $idleMinutes,
            'cheating_attempt_limit' => $cheatingAttemptLimit,
], (int)$_SESSION['user_id']);

        auditFromSession(
            $pdo,
            'SYSTEM',
            'UPDATE_SECURITY_POLICY',
            sprintf(
                'Security policy updated: max attempts %d→%d, lockout %d→%d min, auto logout %d→%d min, cheating limit %d→%d',
                $before['login_max_attempts'],
                $saved['login_max_attempts'],
                $before['login_lockout_minutes'],
                $saved['login_lockout_minutes'],
                $before['idle_timeout_minutes'],
                $saved['idle_timeout_minutes'],
                $before['cheating_attempt_limit'],
                $saved['cheating_attempt_limit']
            ),
            'SUCCESS',
            'system_setting',
            null
        );

        sendSuccess([
            'settings' => $saved,
            'message' => 'Security policy saved',
        ]);
    }

    sendError('Invalid action');
} catch (Exception $e) {
    error_log('settings.php error: ' . $e->getMessage());
    sendError('An unexpected error occurred. Please try again later.', 500);
}
