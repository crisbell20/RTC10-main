<?php
/**
 * Create tbl_exam_subject and backfill from primary exam subjects.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$root = dirname(__DIR__, 2);
require_once $root . '/api/config/connection-pdo.php';
require_once $root . '/api/includes/exam-subject-utils.php';

echo "=== Exam Subject Scope Migration ===\n\n";

try {
    if (!examSubjectTableExists($pdo)) {
        $sql = file_get_contents($root . '/scripts/migrations/add_exam_subject.sql');
        if ($sql === false) {
            throw new RuntimeException('Migration SQL file not found');
        }
        $pdo->exec($sql);
        echo "Created tbl_exam_subject.\n";
    } else {
        echo "tbl_exam_subject already exists.\n";
    }

    $backfilled = backfillExamSubjectsFromPrimary($pdo);
    echo "Backfilled {$backfilled} primary subject link(s).\n";
    echo "\nMigration completed successfully.\n";
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}
