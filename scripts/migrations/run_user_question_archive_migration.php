<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../api/config/connection-pdo.php';

echo "=== USER & QUESTION ARCHIVE MIGRATION ===\n\n";

$alterations = [
    'tbl_question_bank' => [
        "ADD COLUMN `Is_Archived` tinyint(1) NOT NULL DEFAULT 0",
        "ADD COLUMN `Archived_At` datetime DEFAULT NULL",
    ],
    'tbl_user' => [
        "ADD COLUMN `Is_Archived` tinyint(1) NOT NULL DEFAULT 0",
        "ADD COLUMN `Archived_At` datetime DEFAULT NULL",
    ],
];

try {
    foreach ($alterations as $table => $columns) {
        foreach ($columns as $columnDef) {
            preg_match('/`([^`]+)`/', $columnDef, $matches);
            $columnName = $matches[1] ?? 'column';

            try {
                $pdo->exec("ALTER TABLE `{$table}` {$columnDef}");
                echo "OK: {$table}.{$columnName}\n";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                    echo "SKIP: {$table}.{$columnName} already exists\n";
                } else {
                    throw $e;
                }
            }
        }
    }

    echo "\nMigration completed.\n";
} catch (Exception $e) {
    echo 'Migration failed: ' . $e->getMessage() . "\n";
    exit(1);
}
