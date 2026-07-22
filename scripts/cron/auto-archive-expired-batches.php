<?php
/**
 * Auto-archive batches whose Date_Ended has been reached.
 * Schedule daily via Windows Task Scheduler or cron.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$root = dirname(__DIR__, 2);
require_once $root . '/api/config/connection-pdo.php';
require_once $root . '/api/includes/archive-utils.php';

$timestamp = date('Y-m-d H:i:s');

try {
    $archived = autoArchiveExpiredBatches($pdo, true);
    echo "[{$timestamp}] Auto-archived {$archived} expired batch(es)\n";
} catch (Exception $e) {
    echo "[{$timestamp}] ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
