<?php
/**
 * Permanently remove archived records older than the retention period (2 years).
 * Schedule daily via Windows Task Scheduler or cron.
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from the command line.');
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

$root = dirname(__DIR__, 2);
require_once $root . '/api/config/connection-pdo.php';
require_once $root . '/api/includes/archive-purge-utils.php';
require_once $root . '/api/includes/audit-log-utils.php';
require_once $root . '/api/includes/system-settings-utils.php';

$retentionDays = getArchiveRetentionDays($pdo);
$auditRetentionDays = getAuditLogRetentionDays($pdo);
$timestamp = date('Y-m-d H:i:s');

try {
    $results = purgeAllArchivedRecords($pdo, $retentionDays);
    $total = array_sum($results);

    if ($total > 0) {
        writeAuditLog($pdo, [
            'user_id' => null,
            'user_role' => 'System',
            'module' => 'SYSTEM',
            'action' => 'PURGE_ARCHIVED_RECORDS',
            'outcome' => json_encode($results),
            'status' => 'SUCCESS',
            'entity_type' => null,
            'entity_id' => null,
        ]);
    }

    echo "[{$timestamp}] Purged archived records (retention {$retentionDays} days):\n";
    foreach ($results as $type => $count) {
        echo "  - {$type}: {$count}\n";
    }
    echo "  Total: {$total}\n";

    $auditDeleted = purgeAuditLogsOlderThan($pdo, $auditRetentionDays);
    if ($auditDeleted > 0) {
        writeAuditLog($pdo, [
            'user_id' => null,
            'user_role' => 'System',
            'module' => 'SYSTEM',
            'action' => 'PURGE_AUDIT_LOGS',
            'outcome' => "Auto-purged {$auditDeleted} audit entries older than {$auditRetentionDays} days",
            'status' => 'SUCCESS',
            'entity_type' => 'audit_log',
            'entity_id' => null,
        ]);
    }

    echo "[{$timestamp}] Purged audit logs (retention {$auditRetentionDays} days): {$auditDeleted}\n";
} catch (Exception $e) {
    echo "[{$timestamp}] ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
