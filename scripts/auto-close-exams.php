<?php
// Auto-close exams
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from the command line.');
}

require_once __DIR__ . '/../api/config/connection-pdo.php';
require_once __DIR__ . '/../api/includes/exam-deadline-finalize-utils.php';

try {
    $totals = finalizeAllOverdueExams($pdo);

    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] Auto-closed {$totals['exams_closed']} exam(s); "
        . "auto-submitted {$totals['auto_submitted']} session(s); "
        . "marked {$totals['missed_failed']} missed deadline failure(s)";
    if ($totals['errors'] > 0) {
        echo "; {$totals['errors']} error(s)";
    }
    echo "\n";

    if ($totals['exams_closed'] > 0 || $totals['auto_submitted'] > 0 || $totals['missed_failed'] > 0) {
        error_log(sprintf(
            'Auto-close/finalize at %s: closed=%d submitted=%d missed=%d errors=%d',
            $timestamp,
            $totals['exams_closed'],
            $totals['auto_submitted'],
            $totals['missed_failed'],
            $totals['errors']
        ));
    }
} catch (PDOException $e) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] Error: " . $e->getMessage() . "\n";
    error_log('Auto-close exams error: ' . $e->getMessage());
}
