<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../api/config/connection-pdo.php';

$tables = [
    'tbl_course',
    'tbl_batch',
    'tbl_academic_section',
    'tbl_module',
    'tbl_subject',
];

echo "=== CURRICULUM ARCHIVE MIGRATION ===\n\n";

try {
    foreach ($tables as $table) {
        try {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `Is_Archived` tinyint(1) NOT NULL DEFAULT 0");
            echo "OK: {$table}.Is_Archived\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "SKIP: {$table}.Is_Archived already exists\n";
            } else {
                throw $e;
            }
        }

        try {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `Archived_At` datetime DEFAULT NULL");
            echo "OK: {$table}.Archived_At\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "SKIP: {$table}.Archived_At already exists\n";
            } else {
                throw $e;
            }
        }
    }

    echo "\nMigration completed.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
